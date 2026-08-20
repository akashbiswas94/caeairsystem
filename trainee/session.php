<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$traineeId = $_SESSION["user_id"];

$sessionId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


/*
-----------------------------------------
Get session details
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ts.*,
        s.scenario_name,
        s.difficulty,

        sm.alignment_accuracy,
        sm.position_stability,
        sm.approach_accuracy,
        sm.safety_score,
        sm.reaction_time

    FROM training_sessions ts

    JOIN scenarios s
        ON ts.scenario_id = s.id

    LEFT JOIN session_metrics sm
        ON ts.id = sm.session_id

    WHERE ts.id = ?
    AND ts.trainee_id = ?

    LIMIT 1
");

$stmt->execute([
    $sessionId,
    $traineeId
]);

$session = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$session) {
    die("Training session not found.");
}


/*
-----------------------------------------
Get telemetry data
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM session_telemetry
    WHERE session_id = ?
    ORDER BY timestamp_ms ASC
");

$stmt->execute([$sessionId]);

$telemetryData = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
-----------------------------------------
Get latest telemetry
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM session_telemetry
    WHERE session_id = ?
    ORDER BY timestamp_ms DESC
    LIMIT 1
");

$stmt->execute([$sessionId]);

$latestTelemetry = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>

<html>

<head>
    <title>Training Session Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"> </script>
</head>

<body>
    <div class="container-fluid">

        <div class="row">

            <!-- SIDEBAR -->

            <div class="col-md-2 sidebar">
                <div class="p-4">
                    <h4> AAR Training </h4>
                    <hr>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="#"> Training Sessions</a>
                        <a href="#">Performance</a>
                        <a href="#">Feedback</a>
                        <a href="../logout.php"> Logout </a>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-10 main-content">
                <div class="p-4">
                    <a href="dashboard.php" class="btn btn-outline-secondary mb-3"> Back to Dashboard</a>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Training Session #<?= $session["id"]; ?></h2>
                            <p class="text-muted">
                                <?= htmlspecialchars(
                                    $session["scenario_name"]
                                ); ?>
                            </p>
                        </div>
                        <div>
                            <span class="badge bg-success fs-6">
                                <?= $session["status"]; ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="dashboard-card">
                                <p>Overall Score</p>
                                <h2><?= $session["overall_score"]; ?>%</h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="dashboard-card">
                                <p>Duration</p>
                                <h2><?= gmdate("i:s", $session["duration_seconds"]); ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="dashboard-card">
                                <p>Reaction Time</p>
                                <h2><?= $session["reaction_time"]; ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="dashboard-card">
                                <p>Difficulty</p>
                                <h4><?= htmlspecialchars($session["difficulty"]); ?></h4>
                            </div>
                        </div>

                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="dashboard-card">
                                <h5>Performance Metrics</h5>
                                <hr>
                                <p>Alignment Accuracy</p>
                                <div class="progress mb-3">
                                    <div class="progress-bar" style="width:<?= $session["alignment_accuracy"]; ?>%">
                                        <?= $session["alignment_accuracy"]; ?>%
                                    </div>
                                </div>
                                <p>Position Stability</p>
                                <div class="progress mb-3">
                                    <div class="progress-bar" style="width: <?= $session["position_stability"]; ?>%">
                                        <?= $session["position_stability"]; ?>%
                                    </div>
                                </div>
                                <p>Approach Accuracy</p>
                                <div class="progress mb-3">
                                    <div class="progress-bar" style="width: <?= $session["approach_accuracy"]; ?>%">
                                        <?= $session["approach_accuracy"]; ?>%
                                    </div>
                                </div>
                                <p>Safety Score</p>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?= $session["safety_score"]; ?>%">
                                        <?= $session["safety_score"]; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">

                        <div class="dashboard-card">

                            <h5>
                                Final Aircraft State
                            </h5>

                            <hr>

                            <?php if ($latestTelemetry): ?>

                                <div class="row text-center">

                                    <div class="col-4">

                                        <small>
                                            X Position
                                        </small>

                                        <h4>
                                            <?= $latestTelemetry["position_x"]; ?>
                                        </h4>

                                    </div>


                                    <div class="col-4">

                                        <small>
                                            Y Position
                                        </small>

                                        <h4>
                                            <?= $latestTelemetry["position_y"]; ?>
                                        </h4>

                                    </div>


                                    <div class="col-4">

                                        <small>
                                            Z Position
                                        </small>

                                        <h4>
                                            <?= $latestTelemetry["position_z"]; ?>
                                        </h4>

                                    </div>

                                </div>


                                <hr>


                                <div class="row text-center">

                                    <div class="col-4">

                                        <small>
                                            Pitch
                                        </small>

                                        <h5>
                                            <?= $latestTelemetry["rotation_pitch"]; ?>°
                                        </h5>

                                    </div>


                                    <div class="col-4">

                                        <small>
                                            Yaw
                                        </small>

                                        <h5>
                                            <?= $latestTelemetry["rotation_yaw"]; ?>°
                                        </h5>

                                    </div>


                                    <div class="col-4">

                                        <small>
                                            Roll
                                        </small>

                                        <h5>
                                            <?= $latestTelemetry["rotation_roll"]; ?>°
                                        </h5>

                                    </div>

                                </div>


                                <hr>


                                <div class="row text-center">

                                    <div class="col-6">

                                        <small>
                                            Throttle
                                        </small>

                                        <h5>
                                            <?= round(
                                                $latestTelemetry["throttle"] * 100
                                            ); ?>%
                                        </h5>

                                    </div>


                                    <div class="col-6">

                                        <small>
                                            Telemetry Samples
                                        </small>

                                        <h5>
                                            <?= count($telemetryData); ?>
                                        </h5>

                                    </div>

                                </div>

                            <?php else: ?>

                                <p>
                                    No telemetry data available.
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

</div>
    <div class="dashboard-card mb-4">

        <h5>
            Aircraft Position
        </h5>

        <p class="text-muted">
            X, Y and Z movement throughout the training session.
        </p>

        <canvas
            id="positionChart"
            height="90"
        ></canvas>

    </div>
    <div class="dashboard-card mb-4">

        <h5>
            Aircraft Orientation
        </h5>

        <p class="text-muted">
            Pitch, yaw and roll throughout the session.
        </p>

        <canvas
            id="rotationChart"
            height="90"
        ></canvas>

    </div>
    <div class="dashboard-card mb-4">

        <h5>
            Joystick Input
        </h5>

        <p class="text-muted">
            Controller inputs recorded during the training session.
        </p>

        <canvas
            id="joystickChart"
            height="90"
        ></canvas>

    </div>
    <div class="dashboard-card">

        <h5>
            Raw Telemetry
        </h5>

        <p class="text-muted">
            Recorded aircraft and joystick data.
        </p>

        <div class="table-responsive">

            <table class="table table-hover">

                <thead>

                <tr>

                    <th>
                        Time
                    </th>

                    <th>
                        X
                    </th>

                    <th>
                        Y
                    </th>

                    <th>
                        Z
                    </th>

                    <th>
                        Pitch
                    </th>

                    <th>
                        Yaw
                    </th>

                    <th>
                        Roll
                    </th>

                    <th>
                        Joy X
                    </th>

                    <th>
                        Joy Y
                    </th>

                    <th>
                        Throttle
                    </th>

                </tr>

                </thead>


                <tbody>

                <?php foreach ($telemetryData as $row): ?>

                    <tr>

                        <td>
                            <?= $row["timestamp_ms"]; ?> ms
                        </td>

                        <td>
                            <?= $row["position_x"]; ?>
                        </td>

                        <td>
                            <?= $row["position_y"]; ?>
                        </td>

                        <td>
                            <?= $row["position_z"]; ?>
                        </td>

                        <td>
                            <?= $row["rotation_pitch"]; ?>
                        </td>

                        <td>
                            <?= $row["rotation_yaw"]; ?>
                        </td>

                        <td>
                            <?= $row["rotation_roll"]; ?>
                        </td>

                        <td>
                            <?= $row["joystick_x"]; ?>
                        </td>

                        <td>
                            <?= $row["joystick_y"]; ?>
                        </td>

                        <td>
                            <?= $row["throttle"]; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>
</div>

</div>

</div>

</div>

<script>

const timeLabels = [

<?php foreach ($telemetryData as $row): ?>

    "<?= $row["timestamp_ms"]; ?> ms",

<?php endforeach; ?>

];


/*
====================================
POSITION DATA
====================================
*/

const xData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["position_x"]; ?>,

<?php endforeach; ?>

];

const yData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["position_y"]; ?>,

<?php endforeach; ?>

];

const zData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["position_z"]; ?>,

<?php endforeach; ?>

];


new Chart(
    document.getElementById("positionChart"),
    {

        type: "line",

        data: {

            labels: timeLabels,

            datasets: [

                {
                    label: "X Position",
                    data: xData,
                    tension: 0.3
                },

                {
                    label: "Y Position",
                    data: yData,
                    tension: 0.3
                },

                {
                    label: "Z Position",
                    data: zData,
                    tension: 0.3
                }

            ]

        },

        options: {

            responsive: true,

            interaction: {
                mode: "index",
                intersect: false
            }

        }

    }
);


/*
====================================
ROTATION DATA
====================================
*/

const pitchData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["rotation_pitch"]; ?>,

<?php endforeach; ?>

];

const yawData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["rotation_yaw"]; ?>,

<?php endforeach; ?>

];

const rollData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["rotation_roll"]; ?>,

<?php endforeach; ?>

];


new Chart(
    document.getElementById("rotationChart"),
    {

        type: "line",

        data: {

            labels: timeLabels,

            datasets: [

                {
                    label: "Pitch",
                    data: pitchData,
                    tension: 0.3
                },

                {
                    label: "Yaw",
                    data: yawData,
                    tension: 0.3
                },

                {
                    label: "Roll",
                    data: rollData,
                    tension: 0.3
                }

            ]

        },

        options: {

            responsive: true,

            interaction: {
                mode: "index",
                intersect: false
            }

        }

    }
);


/*
====================================
JOYSTICK DATA
====================================
*/

const joystickX = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["joystick_x"]; ?>,

<?php endforeach; ?>

];

const joystickY = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["joystick_y"]; ?>,

<?php endforeach; ?>

];

const joystickZ = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["joystick_z"]; ?>,

<?php endforeach; ?>

];

const throttleData = [

<?php foreach ($telemetryData as $row): ?>

    <?= $row["throttle"]; ?>,

<?php endforeach; ?>

];


new Chart(
    document.getElementById("joystickChart"),
    {

        type: "line",

        data: {

            labels: timeLabels,

            datasets: [

                {
                    label: "Joystick X",
                    data: joystickX,
                    tension: 0.3
                },

                {
                    label: "Joystick Y",
                    data: joystickY,
                    tension: 0.3
                },

                {
                    label: "Joystick Z",
                    data: joystickZ,
                    tension: 0.3
                },

                {
                    label: "Throttle",
                    data: throttleData,
                    tension: 0.3
                }

            ]

        },

        options: {

            responsive: true,

            interaction: {
                mode: "index",
                intersect: false
            }

        }

    }
);

</script>

</body>
</html>