<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'POST JSON telemetry to this endpoint.'], 405);
}

try {
    $data = requestJson();
    $eventType = trim((string) ($data['event'] ?? 'unknown'));
    $sessionId = resolveSessionId($pdo, $data, $eventType);

    // Keep a simple debug log while the Unreal integration is being developed.
    file_put_contents(
        __DIR__ . '/telemetry.log',
        date('Y-m-d H:i:s') . ' ' . json_encode($data, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );

    if ($eventType === 'flight_telemetry') {
        // Current UE Blueprint sends X/Y in centimetres and altitude_m in metres.
        // If x_m/y_m are added later, they take priority.
        $positionX = nullableFloat($data, 'x_m');
        $positionY = nullableFloat($data, 'y_m');

        if ($positionX === null && array_key_exists('x', $data)) {
            $positionX = (float) $data['x'] / 100.0;
        }
        if ($positionY === null && array_key_exists('y', $data)) {
            $positionY = (float) $data['y'] / 100.0;
        }

        $positionZ = nullableFloat($data, 'altitude_m');
        if ($positionZ === null && array_key_exists('z', $data)) {
            $positionZ = (float) $data['z'] / 100.0;
        }

        $timestampMs = isset($data['timestamp_ms'])
            ? max(0, (int) $data['timestamp_ms'])
            : elapsedMilliseconds($pdo, $sessionId);

        $stmt = $pdo->prepare(
            'INSERT INTO session_telemetry (
                session_id, timestamp_ms, aircraft,
                position_x, position_y, position_z, speed_mps,
                rotation_pitch, rotation_yaw, rotation_roll,
                joystick_x, joystick_y, joystick_z, throttle
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $sessionId,
            $timestampMs,
            $data['aircraft'] ?? 'F15',
            $positionX,
            $positionY,
            $positionZ,
            nullableFloat($data, 'speed_mps'),
            nullableFloat($data, 'pitch'),
            nullableFloat($data, 'yaw'),
            nullableFloat($data, 'roll'),
            nullableFloat($data, 'joystick_x'),
            nullableFloat($data, 'joystick_y'),
            nullableFloat($data, 'joystick_z'),
            nullableFloat($data, 'throttle'),
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Flight telemetry stored.',
            'session_id' => $sessionId,
            'telemetry_id' => (int) $pdo->lastInsertId(),
            'timestamp_ms' => $timestampMs,
        ]);
    }

    $eventStmt = $pdo->prepare(
        'INSERT INTO session_events (session_id, event_type, payload) VALUES (?, ?, ?)'
    );
    $eventStmt->execute([
        $sessionId,
        $eventType,
        json_encode($data, JSON_UNESCAPED_SLASHES),
    ]);

    if (in_array($eventType, ['game_finished', 'session_finished'], true)) {
        $duration = elapsedMilliseconds($pdo, $sessionId);
        $stmt = $pdo->prepare(
            "UPDATE training_sessions
             SET status = 'Review',
                 ended_at = CURRENT_TIMESTAMP(3),
                 duration_seconds = ?
             WHERE id = ?"
        );
        $stmt->execute([(int) round($duration / 1000), $sessionId]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Event stored.',
        'session_id' => $sessionId,
        'event_type' => $eventType,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
