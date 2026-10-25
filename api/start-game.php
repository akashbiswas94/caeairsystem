<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/common.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Login required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST required.'], 405);
}

// This endpoint intentionally launches a local desktop process. Keep it local-only.
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    jsonResponse([
        'success' => false,
        'message' => 'Simulator launch is only allowed from localhost.'
    ], 403);
}

try {
    $data = requestJson();
    $role = strtolower(trim((string) ($_SESSION['role'] ?? 'trainee')));
    $isAdminView = $role !== '' && $role !== 'trainee';

    // Trainees may only launch a session for themselves. Admin/instructor users may select a trainee.
    $traineeId = (int) $_SESSION['user_id'];
    if ($isAdminView && !empty($data['trainee_id'])) {
        $traineeId = (int) $data['trainee_id'];
    }

    $stmt = $pdo->prepare('SELECT id, first_name, last_name, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$traineeId]);
    $trainee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trainee) {
        jsonResponse(['success' => false, 'message' => 'Selected trainee does not exist.'], 404);
    }

    // If an admin selected another privileged user by mistake, reject it.
    if ($isAdminView && strtolower((string) ($trainee['role'] ?? 'trainee')) !== 'trainee') {
        jsonResponse(['success' => false, 'message' => 'Select a trainee account.'], 400);
    }

    $scenarioId = !empty($data['scenario_id'])
        ? (int) $data['scenario_id']
        : defaultScenarioId($pdo);

    $stmt = $pdo->prepare('SELECT id FROM scenarios WHERE id = ? LIMIT 1');
    $stmt->execute([$scenarioId]);
    if (!$stmt->fetchColumn()) {
        jsonResponse(['success' => false, 'message' => 'Selected scenario does not exist.'], 404);
    }

    $config = require __DIR__ . '/../config/game.php';

    $findExistingPath = static function (array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    };

    $editorPath = $findExistingPath($config['unreal_editor_candidates'] ?? []);
    $projectPath = $findExistingPath($config['project_candidates'] ?? []);

    if (!$editorPath) {
        jsonResponse([
            'success' => false,
            'message' => 'UnrealEditor.exe was not found. Update config/game.php.'
        ], 500);
    }

    if (!$projectPath) {
        jsonResponse([
            'success' => false,
            'message' => 'The Unreal .uproject file was not found. Update config/game.php.'
        ], 500);
    }

    /*
     * Starting from the dashboard always begins a NEW training run.
     * Any older unfinished runs for this trainee are closed as Review first.
     * This prevents stale NULL-status sessions from appearing as live forever
     * and guarantees that one trainee has only one in-progress session.
     */
    $openStmt = $pdo->prepare(
        'SELECT id, COALESCE(started_at, session_date) AS started
         FROM training_sessions
         WHERE trainee_id = ? AND status IS NULL
         ORDER BY id DESC'
    );
    $openStmt->execute([$traineeId]);
    $openSessions = $openStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($openSessions as $openSession) {
        $oldSessionId = (int) $openSession['id'];

        // timestamp_ms is elapsed milliseconds from the beginning of the run.
        $durationStmt = $pdo->prepare(
            'SELECT CEIL(COALESCE(MAX(timestamp_ms), 0) / 1000)
             FROM session_telemetry
             WHERE session_id = ?'
        );
        $durationStmt->execute([$oldSessionId]);
        $durationSeconds = max(0, (int) ($durationStmt->fetchColumn() ?: 0));

        $closeStmt = $pdo->prepare(
            "UPDATE training_sessions
             SET status = 'Review',
                 duration_seconds = ?,
                 ended_at = TIMESTAMPADD(SECOND, ?, COALESCE(started_at, session_date))
             WHERE id = ? AND status IS NULL"
        );
        $closeStmt->execute([$durationSeconds, $durationSeconds, $oldSessionId]);

        $eventStmt = $pdo->prepare(
            "INSERT INTO session_events (session_id, event_type, payload, created_at)
             VALUES (?, 'session_auto_closed', ?, CURRENT_TIMESTAMP(3))"
        );
        $eventStmt->execute([
            $oldSessionId,
            json_encode([
                'reason' => 'new_dashboard_launch',
                'requested_by' => (int) $_SESSION['user_id'],
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO training_sessions
            (trainee_id, scenario_id, session_date, started_at, status)
         VALUES (?, ?, CURRENT_TIMESTAMP(3), CURRENT_TIMESTAMP(3), NULL)'
    );
    $stmt->execute([$traineeId, $scenarioId]);
    $sessionId = (int) $pdo->lastInsertId();
    $createdSession = true;

    $metricStmt = $pdo->prepare('INSERT IGNORE INTO session_metrics (session_id) VALUES (?)');
    $metricStmt->execute([$sessionId]);

    $eventStmt = $pdo->prepare(
        "INSERT INTO session_events (session_id, event_type, payload, created_at)
         VALUES (?, 'dashboard_launch_requested', ?, CURRENT_TIMESTAMP(3))"
    );
    $eventStmt->execute([
        $sessionId,
        json_encode([
            'source' => $isAdminView ? 'admin_dashboard' : 'trainee_dashboard',
            'requested_by' => (int) $_SESSION['user_id'],
        ], JSON_UNESCAPED_SLASHES),
    ]);

    $map = (string) ($config['map'] ?? '/Game/BoomBoom/Levels/Main_World');
    $arguments = array_values(array_filter(
        $config['arguments'] ?? [],
        static fn ($arg): bool => is_string($arg) && $arg !== ''
    ));

    // Values passed to the shell come only from local configuration, never directly from request input.
    $traineeName = trim(
    (string) ($trainee['first_name'] ?? '') . ' ' .
    (string) ($trainee['last_name'] ?? '')
    );

    /*
    * Make the name command-line safe.
    * Unreal will convert _ back to a space.
    */
    $traineeNameForGame = str_replace(' ', '_', $traineeName);

    $runtimeArguments = [
        '-TraineeName=' . $traineeNameForGame,
        '-TraineeId=' . $traineeId,
        '-SessionId=' . $sessionId,
    ];
    $commandParts = [
        'start',
        '""',
        '"' . str_replace('"', '', $editorPath) . '"',
        '"' . str_replace('"', '', $projectPath) . '"',
        '"' . str_replace('"', '', $map) . '"',
    ];

    foreach ($arguments as $argument) {
        $commandParts[] = '"' . str_replace('"', '', $argument) . '"';
    }
    foreach ($runtimeArguments as $argument) {
    $commandParts[] = '"' . str_replace('"', '', $argument) . '"';
    }

    $command = 'cmd /c ' . implode(' ', $commandParts);

    $handle = @popen($command, 'r');

    if ($handle === false) {
        if ($createdSession) {
            $pdo->prepare('DELETE FROM session_events WHERE session_id = ?')->execute([$sessionId]);
            $pdo->prepare('DELETE FROM session_metrics WHERE session_id = ?')->execute([$sessionId]);
            $pdo->prepare('DELETE FROM training_sessions WHERE id = ?')->execute([$sessionId]);
        }

        jsonResponse([
            'success' => false,
            'message' => 'Windows could not launch Unreal Engine.'
        ], 500);
    }

    @pclose($handle);

    jsonResponse([
        'success' => true,
        'message' => 'Main_World launch requested.',
        'session_id' => $sessionId,
        'trainee_id' => $traineeId,
        'scenario_id' => $scenarioId,
        'reused_session' => false,
    ]);

} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
