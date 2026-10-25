<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['success' => false, 'message' => 'Invalid JSON body.'], 400);
    }

    return $data;
}

function firstTraineeId(PDO $pdo): int
{
    $id = $pdo->query("SELECT id FROM users WHERE role = 'trainee' ORDER BY id ASC LIMIT 1")->fetchColumn();
    if (!$id) {
        throw new RuntimeException('No trainee user exists. Import database.sql first.');
    }
    return (int) $id;
}

function defaultScenarioId(PDO $pdo): int
{
    $id = $pdo->query('SELECT id FROM scenarios ORDER BY id ASC LIMIT 1')->fetchColumn();
    if (!$id) {
        throw new RuntimeException('No scenario exists. Import database.sql first.');
    }
    return (int) $id;
}

function sessionExists(PDO $pdo, int $sessionId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM training_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    return (bool) $stmt->fetchColumn();
}

function latestOpenSessionId(PDO $pdo, ?int $traineeId = null): ?int
{
    if ($traineeId) {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM training_sessions
             WHERE trainee_id = ?
               AND status IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$traineeId]);
    } else {
        $stmt = $pdo->query(
            "SELECT id
             FROM training_sessions
             WHERE status IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
    }

    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function createTrainingSession(PDO $pdo, array $data = []): int
{
    $traineeId = isset($data['trainee_id']) ? (int) $data['trainee_id'] : firstTraineeId($pdo);
    $scenarioId = isset($data['scenario_id']) ? (int) $data['scenario_id'] : defaultScenarioId($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO training_sessions
            (trainee_id, scenario_id, session_date, started_at, status)
         VALUES (?, ?, CURRENT_TIMESTAMP(3), CURRENT_TIMESTAMP(3), NULL)"
    );
    $stmt->execute([$traineeId, $scenarioId]);

    $sessionId = (int) $pdo->lastInsertId();

    $metricStmt = $pdo->prepare('INSERT IGNORE INTO session_metrics (session_id) VALUES (?)');
    $metricStmt->execute([$sessionId]);

    return $sessionId;
}

function resolveSessionId(PDO $pdo, array $data, string $eventType): int
{
    if (!empty($data['session_id'])) {
        $sessionId = (int) $data['session_id'];
        if (!sessionExists($pdo, $sessionId)) {
            throw new RuntimeException('Unknown session_id supplied by Unreal.');
        }
        return $sessionId;
    }

    $traineeId = isset($data['trainee_id']) ? (int) $data['trainee_id'] : null;

    if ($eventType === 'game_started') {
        // A dashboard launch pre-creates an In Progress session before Unreal starts.
        // Unreal Editor can take more than 10 seconds to open, so allow 3 minutes
        // for the first game_started event to attach to that pending session.
        $sql = "SELECT id
                FROM training_sessions
                WHERE status IS NULL
                  AND COALESCE(started_at, session_date)
                      >= CURRENT_TIMESTAMP(3) - INTERVAL 180 SECOND";
        $params = [];

        if ($traineeId) {
            $sql .= ' AND trainee_id = ?';
            $params[] = $traineeId;
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recent = $stmt->fetchColumn();

        if ($recent) {
            return (int) $recent;
        }

        return createTrainingSession($pdo, $data);
    }

    // Flight telemetry and simulator events reuse the newest unfinished session.
    $openSession = latestOpenSessionId($pdo, $traineeId);
    return $openSession ?? createTrainingSession($pdo, $data);
}

function elapsedMilliseconds(PDO $pdo, int $sessionId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(started_at, session_date) FROM training_sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $startedAt = $stmt->fetchColumn();

    if (!$startedAt) {
        return 0;
    }

    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $startedAt)
        ?: new DateTimeImmutable($startedAt);
    $now = new DateTimeImmutable('now');

    $startSeconds = (float) $start->format('U.u');
    $nowSeconds = (float) $now->format('U.u');

    return max(0, (int) round(($nowSeconds - $startSeconds) * 1000));
}

function nullableFloat(array $data, string $key): ?float
{
    return array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== ''
        ? (float) $data[$key]
        : null;
}
