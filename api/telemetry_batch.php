<?php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST method required'
    ]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=aar_training;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new InvalidArgumentException('Invalid JSON');
    }

    $sessionId = filter_var(
        $data['session_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $samples = $data['samples'] ?? null;

    if (!$sessionId || $sessionId < 1 ||
        !is_array($samples) ||
        count($samples) < 1 ||
        count($samples) > 100) {
        throw new InvalidArgumentException('Invalid session or samples');
    }

    // Confirm that the training session exists.
    $check = $pdo->prepare(
        'SELECT id FROM training_sessions WHERE id = ?'
    );

    $check->execute([$sessionId]);

    if (!$check->fetchColumn()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Training session not found'
        ]);
        exit;
    }

    $sql = "
        INSERT INTO joystick_telemetry (
            session_id,
            timestamp_seconds,
            joystick_x,
            joystick_y,
            input_change_magnitude,
            ball_position_x,
            ball_position_y,
            ball_position_z,
            ball_velocity_x,
            ball_velocity_y,
            ball_velocity_z,
            correction_count,
            training_stage
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";

    $stmt = $pdo->prepare($sql);

    $pdo->beginTransaction();

    foreach ($samples as $sample) {
        if (!is_array($sample)) {
            throw new InvalidArgumentException('Invalid sample');
        }

        $required = [
            'timestamp',
            'joystick_x',
            'joystick_y',
            'input_change',
            'ball_position',
            'ball_velocity',
            'correction_count'
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $sample)) {
                throw new InvalidArgumentException("Missing field: $key");
            }
        }

        $position = $sample['ball_position'];
        $velocity = $sample['ball_velocity'];

        if (!is_array($position) || !is_array($velocity)) {
            throw new InvalidArgumentException('Invalid vector data');
        }

        $values = [
            $sample['timestamp'],
            $sample['joystick_x'],
            $sample['joystick_y'],
            $sample['input_change'],
            $position['x'] ?? null,
            $position['y'] ?? null,
            $position['z'] ?? null,
            $velocity['x'] ?? null,
            $velocity['y'] ?? null,
            $velocity['z'] ?? null,
            $sample['correction_count'],
        ];

        foreach ($values as $value) {
            if (!is_numeric($value) || !is_finite((float)$value)) {
                throw new InvalidArgumentException('Invalid numeric value');
            }
        }

        $correctionCount = (int)$sample['correction_count'];

        if ($correctionCount < 0 ||
            (float)$correctionCount !== (float)$sample['correction_count']) {
            throw new InvalidArgumentException('Invalid correction count');
        }

        $stage = $sample['training_stage'] ?? 'BasicControl';

        if (!is_string($stage) || strlen($stage) > 100) {
            throw new InvalidArgumentException('Invalid training stage');
        }

        $stmt->execute([
            $sessionId,
            $sample['timestamp'],
            $sample['joystick_x'],
            $sample['joystick_y'],
            $sample['input_change'],
            $position['x'],
            $position['y'],
            $position['z'],
            $velocity['x'],
            $velocity['y'],
            $velocity['z'],
            $correctionCount,
            $stage
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'session_id' => $sessionId,
        'samples_saved' => count($samples)
    ]);

} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Telemetry API error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Server error while saving telemetry'
    ]);
}