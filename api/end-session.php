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

try {
    $data = requestJson();
    $sessionId = isset($data['session_id']) ? (int) $data['session_id'] : 0;

    if ($sessionId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid session ID.'], 400);
    }

    $role = strtolower(trim((string) ($_SESSION['role'] ?? 'trainee')));
    $isAdminView = $role !== '' && $role !== 'trainee';

    $stmt = $pdo->prepare('SELECT id, trainee_id, started_at, session_date, status FROM training_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonResponse(['success' => false, 'message' => 'Session not found.'], 404);
    }

    if (!$isAdminView && (int) $session['trainee_id'] !== (int) $_SESSION['user_id']) {
        jsonResponse(['success' => false, 'message' => 'You cannot end another trainee\'s session.'], 403);
    }

    if ($session['status'] !== null) {
        jsonResponse([
            'success' => true,
            'message' => 'Session is already closed.',
            'session_id' => $sessionId,
        ]);
    }

    $durationMs = elapsedMilliseconds($pdo, $sessionId);
    $durationSeconds = max(0, (int) round($durationMs / 1000));

    $stmt = $pdo->prepare(
        "UPDATE training_sessions
         SET status = 'Review',
             ended_at = CURRENT_TIMESTAMP(3),
             duration_seconds = ?
         WHERE id = ?"
    );
    $stmt->execute([$durationSeconds, $sessionId]);

    $eventStmt = $pdo->prepare(
        "INSERT INTO session_events (session_id, event_type, payload, created_at)
         VALUES (?, 'session_finished', ?, CURRENT_TIMESTAMP(3))"
    );
    $eventStmt->execute([
        $sessionId,
        json_encode(['source' => 'dashboard'], JSON_UNESCAPED_SLASHES),
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Session ended and moved to Review.',
        'session_id' => $sessionId,
        'duration_seconds' => $durationSeconds,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
