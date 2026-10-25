<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST required.'], 405);
}

try {
    $data = requestJson();
    if (!isset($data['trainee_id']) && !empty($_SESSION['user_id'])) {
        $data['trainee_id'] = (int) $_SESSION['user_id'];
    }

    $sessionId = createTrainingSession($pdo, $data);

    $stmt = $pdo->prepare('INSERT INTO session_events (session_id, event_type, payload) VALUES (?, ?, ?)');
    $stmt->execute([$sessionId, 'session_created', json_encode($data, JSON_UNESCAPED_SLASHES)]);

    jsonResponse(['success' => true, 'session_id' => $sessionId]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
