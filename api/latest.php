<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/common.php';

try {

    $sessionId = isset($_GET['session_id'])
        ? (int) $_GET['session_id']
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Find session
    |--------------------------------------------------------------------------
    |
    | If session_id is supplied, use it.
    | Otherwise prefer the current unfinished session.
    |
    */

    if ($sessionId <= 0) {

        if (!empty($_SESSION['user_id'])) {

            $traineeId = (int) $_SESSION['user_id'];

            /*
             * First try to find the currently running session.
             */
            $stmt = $pdo->prepare("
                SELECT id
                FROM training_sessions
                WHERE trainee_id = ?
                  AND status IS NULL
                ORDER BY
                    COALESCE(started_at, session_date) DESC,
                    id DESC
                LIMIT 1
            ");

            $stmt->execute([$traineeId]);

            $sessionId = (int) ($stmt->fetchColumn() ?: 0);

            /*
             * If no active session exists, get the latest completed one.
             */
            if ($sessionId <= 0) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM training_sessions
                    WHERE trainee_id = ?
                    ORDER BY
                        COALESCE(started_at, session_date) DESC,
                        id DESC
                    LIMIT 1
                ");

                $stmt->execute([$traineeId]);

                $sessionId = (int) ($stmt->fetchColumn() ?: 0);
            }

        } else {

            /*
             * No logged-in trainee.
             * Mainly useful for local development/testing.
             */

            $stmt = $pdo->query("
                SELECT id
                FROM training_sessions
                WHERE status IS NULL
                ORDER BY
                    COALESCE(started_at, session_date) DESC,
                    id DESC
                LIMIT 1
            ");

            $sessionId = (int) ($stmt->fetchColumn() ?: 0);

            if ($sessionId <= 0) {

                $stmt = $pdo->query("
                    SELECT id
                    FROM training_sessions
                    ORDER BY
                        COALESCE(started_at, session_date) DESC,
                        id DESC
                    LIMIT 1
                ");

                $sessionId = (int) ($stmt->fetchColumn() ?: 0);
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | No session yet
    |--------------------------------------------------------------------------
    */

    if ($sessionId <= 0) {

        jsonResponse([
            'success' => true,
            'session' => null,
            'telemetry' => null,
            'events' => [],
            'server_time' => date(DATE_ATOM),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Session information
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            ts.id,
            ts.trainee_id,
            ts.scenario_id,
            ts.status,
            ts.started_at,
            ts.ended_at,
            ts.duration_seconds,
            ts.overall_score,
            s.scenario_name
        FROM training_sessions ts
        JOIN scenarios s
            ON s.id = ts.scenario_id
        WHERE ts.id = ?
        LIMIT 1
    ");

    $stmt->execute([$sessionId]);

    $session = $stmt->fetch();

    if (!$session) {
        jsonResponse([
            'success' => false,
            'message' => 'Training session not found.'
        ], 404);
    }


    /*
    |--------------------------------------------------------------------------
    | Friendly status
    |--------------------------------------------------------------------------
    */

    $session['status_label'] =
        $session['status'] === null
            ? 'In Progress'
            : $session['status'];


    /*
    |--------------------------------------------------------------------------
    | Latest flight telemetry
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM session_telemetry
        WHERE session_id = ?
        ORDER BY timestamp_ms DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([$sessionId]);

    $telemetry = $stmt->fetch() ?: null;


    /*
    |--------------------------------------------------------------------------
    | Recent events
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            event_type,
            payload,
            created_at
        FROM session_events
        WHERE session_id = ?
        ORDER BY id DESC
        LIMIT 8
    ");

    $stmt->execute([$sessionId]);

    $events = $stmt->fetchAll();


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    jsonResponse([
        'success' => true,
        'session' => $session,
        'telemetry' => $telemetry,
        'events' => $events,
        'server_time' => date(DATE_ATOM),
    ]);

} catch (Throwable $e) {

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}