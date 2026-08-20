<?php

session_start();

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$traineeId = $_SESSION["user_id"];


/*
-----------------------------------------
Dashboard summary
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_sessions,
        ROUND(AVG(overall_score), 1) AS average_score,
        MAX(overall_score) AS best_score,
        SUM(duration_seconds) AS total_training_time
    FROM training_sessions
    WHERE trainee_id = ?
");

$stmt->execute([$traineeId]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);


/*
-----------------------------------------
Recent training sessions
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ts.id,
        ts.session_date,
        ts.overall_score,
        ts.status,
        ts.duration_seconds,
        s.scenario_name
    FROM training_sessions ts

    JOIN scenarios s
        ON ts.scenario_id = s.id

    WHERE ts.trainee_id = ?

    ORDER BY ts.session_date DESC
");

$stmt->execute([$traineeId]);

$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
-----------------------------------------
Latest session
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ts.*,
        s.scenario_name,
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

    WHERE ts.trainee_id = ?

    ORDER BY ts.session_date DESC

    LIMIT 1
");

$stmt->execute([$traineeId]);

$latest = $stmt->fetch(PDO::FETCH_ASSOC);


/*
-----------------------------------------
Latest telemetry position
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM session_telemetry
    WHERE session_id = ?
    ORDER BY timestamp_ms DESC
    LIMIT 1
");

$stmt->execute([$latest["id"]]);

$telemetry = $stmt->fetch(PDO::FETCH_ASSOC);


/*
-----------------------------------------
Performance chart
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        overall_score
    FROM training_sessions

    WHERE trainee_id = ?

    ORDER BY session_date ASC
");

$stmt->execute([$traineeId]);

$performance = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
-----------------------------------------
XYZ telemetry graph
-----------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        timestamp_ms,
        position_x,
        position_y,
        position_z

    FROM session_telemetry

    WHERE session_id = ?

    ORDER BY timestamp_ms ASC
");

$stmt->execute([$latest["id"]]);

$xyzData = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>

<html>

<head>

    <title>AAR Training Dashboard</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js">
    </script>

</head>

<body>

<div class="container-fluid">

    <div class="row">

        <!-- SIDEBAR -->

        <div class="col-md-2 sidebar">

            <div class="p-4">

                <h4>
                    AAR Training
                </h4>

                <hr>

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a href="#">
                    Training Sessions
                </a>

                <a href="#">
                    Performance
                </a>

                <a href="#">
                    Feedback
                </a>

                <a href="../logout.php">
                    Logout
                </a>

            </div>

        </div>


        <!-- MAIN CONTENT -->

        <div class="col-md-10 main-content">

            <div class="p-4">

                <div
                    class="d-flex justify-content-between align-items-center mb-4"
                >

                    <div>

                        <h2>
                            Trainee Dashboard
                        </h2>

                        <p class="text-muted">

                            Welcome back,
                            <?= htmlspecialchars($_SESSION["user_name"]); ?>

                        </p>

                    </div>

                    <div>

                        <span class="badge bg-success">

                            Training Active

                        </span>

                    </div>

                </div>


                <!-- STAT CARDS -->

                <div class="row mb-4">

                    <div class="col-md-3">

                        <div class="dashboard-card">

                            <p>
                                Average Score
                            </p>

                            <h2>

                                <?= $stats["average_score"]; ?>%

                            </h2>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="dashboard-card">

                            <p>
                                Training Sessions
                            </p>

                            <h2>

                                <?= $stats["total_sessions"]; ?>

                            </h2>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="dashboard-card">

                            <p>
                                Best Score
                            </p>

                            <h2>

                                <?= $stats["best_score"]; ?>%

                            </h2>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="dashboard-card">

                            <p>
                                Latest Score
                            </p>

                            <h2>

                                <?= $latest["overall_score"]; ?>%

                            </h2>

                        </div>

                    </div>

                </div>


                <!-- PERFORMANCE GRAPH -->

                <div class="row mb-4">

                    <div class="col-md-8">

                        <div class="dashboard-card">

                            <h5>
                                Performance Progress
                            </h5>

                            <canvas
                                id="performanceChart">
                            </canvas>

                        </div>

                    </div>


                    <!-- LATEST SESSION -->

                    <div class="col-md-4">

                        <div class="dashboard-card">

                            <h5>
                                Latest Session
                            </h5>

                            <hr>

                            <strong>
                                Scenario
                            </strong>

                            <p>
                                <?= htmlspecialchars($latest["scenario_name"]); ?>
                            </p>

                            <strong>
                                Score
                            </strong>

                            <p>
                                <?= $latest["overall_score"]; ?>%
                            </p>

                            <strong>
                                Reaction Time
                            </strong>

                            <p>
                                <?= $latest["reaction_time"]; ?> sec
                            </p>

                        </div>

                    </div>

                </div>


                <!-- PERFORMANCE METRICS -->

                <div class="row mb-4">

                    <div class="col-md-6">

                        <div class="dashboard-card">

                            <h5>
                                Skill Performance
                            </h5>

                            <hr>


                            <p>
                                Alignment Accuracy
                            </p>

                            <div class="progress mb-3">

                                <div
                                    class="progress-bar"
                                    style="width:
                                    <?= $latest["alignment_accuracy"]; ?>%"
                                >

                                    <?= $latest["alignment_accuracy"]; ?>%

                                </div>

                            </div>


                            <p>
                                Position Stability
                            </p>

                            <div class="progress mb-3">

                                <div
                                    class="progress-bar"
                                    style="width:
                                    <?= $latest["position_stability"]; ?>%"
                                >

                                    <?= $latest["position_stability"]; ?>%

                                </div>

                            </div>


                            <p>
                                Approach Accuracy
                            </p>

                            <div class="progress mb-3">

                                <div
                                    class="progress-bar"
                                    style="width:
                                    <?= $latest["approach_accuracy"]; ?>%"
                                >

                                    <?= $latest["approach_accuracy"]; ?>%

                                </div>

                            </div>


                            <p>
                                Safety Score
                            </p>

                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="width:
                                    <?= $latest["safety_score"]; ?>%"
                                >

                                    <?= $latest["safety_score"]; ?>%

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- AIRCRAFT POSITION -->

                    <div class="col-md-6">

                        <div class="dashboard-card">

                            <h5>
                                Aircraft Telemetry
                            </h5>

                            <hr>

                            <div class="row">

                                <div class="col-4">

                                    <small>
                                        X Position
                                    </small>

                                    <h4>
                                        <?= $telemetry["position_x"]; ?>
                                    </h4>

                                </div>


                                <div class="col-4">

                                    <small>
                                        Y Position
                                    </small>

                                    <h4>
                                        <?= $telemetry["position_y"]; ?>
                                    </h4>

                                </div>


                                <div class="col-4">

                                    <small>
                                        Z Position
                                    </small>

                                    <h4>
                                        <?= $telemetry["position_z"]; ?>
                                    </h4>

                                </div>

                            </div>

                            <hr>

                            <div class="row">

                                <div class="col-4">

                                    <small>
                                        Pitch
                                    </small>

                                    <h5>
                                        <?= $telemetry["rotation_pitch"]; ?>°
                                    </h5>

                                </div>


                                <div class="col-4">

                                    <small>
                                        Yaw
                                    </small>

                                    <h5>
                                        <?= $telemetry["rotation_yaw"]; ?>°
                                    </h5>

                                </div>


                                <div class="col-4">

                                    <small>
                                        Roll
                                    </small>

                                    <h5>
                                        <?= $telemetry["rotation_roll"]; ?>°
                                    </h5>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- XYZ GRAPH -->

                <div class="dashboard-card mb-4">

                    <h5>
                        Aircraft Position – X / Y / Z
                    </h5>

                    <canvas
                        id="xyzChart"
                        height="100">
                    </canvas>

                </div>


                <!-- RECENT SESSION TABLE -->

                <div class="dashboard-card">

                    <h5>
                        Recent Training Sessions
                    </h5>

                    <div class="table-responsive">

                        <table
                            class="table table-hover mt-3"
                        >

                            <thead>

                            <?php foreach ($sessions as $session): ?>

                            <tr style="cursor:pointer;"
                                onclick="window.location='session.php?id=<?= $session['id']; ?>'">

                                <td>
                                    <?= date(
                                        "d M Y",
                                        strtotime($session["session_date"])
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $session["scenario_name"]
                                    ); ?>
                                </td>

                                <td>
                                    <?= gmdate(
                                        "i:s",
                                        $session["duration_seconds"]
                                    ); ?>
                                </td>

                                <td>
                                    <?= $session["overall_score"]; ?>%
                                </td>

                                <td>
                                    <span class="badge bg-success">
                                        <?= $session["status"]; ?>
                                    </span>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                            </thead>

                            <tbody>

                            <?php foreach ($sessions as $session): ?>

                                <tr>

                                    <td>

                                        <?= date(
                                            "d M Y",
                                            strtotime($session["session_date"])
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $session["scenario_name"]
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= gmdate(
                                            "i:s",
                                            $session["duration_seconds"]
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= $session["overall_score"]; ?>%

                                    </td>


                                    <td>

                                        <span class="badge bg-success">

                                            <?= $session["status"]; ?>

                                        </span>

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

/*
================================
PERFORMANCE CHART
================================
*/

const performanceLabels = [

<?php foreach ($performance as $index => $row): ?>

    "Session <?= $index + 1 ?>",

<?php endforeach; ?>

];


const performanceScores = [

<?php foreach ($performance as $row): ?>

    <?= $row["overall_score"]; ?>,

<?php endforeach; ?>

];


new Chart(
    document.getElementById("performanceChart"),
    {

        type: "line",

        data: {

            labels: performanceLabels,

            datasets: [

                {

                    label: "Overall Score",

                    data: performanceScores,

                    tension: 0.3,

                    fill: false

                }

            ]

        },

        options: {

            responsive: true,

            scales: {

                y: {

                    beginAtZero: true,
                    max: 100

                }

            }

        }

    }
);



/*
================================
XYZ TELEMETRY CHART
================================
*/

const telemetryLabels = [

<?php foreach ($xyzData as $row): ?>

    "<?= $row["timestamp_ms"]; ?> ms",

<?php endforeach; ?>

];


const xPositions = [

<?php foreach ($xyzData as $row): ?>

    <?= $row["position_x"]; ?>,

<?php endforeach; ?>

];


const yPositions = [

<?php foreach ($xyzData as $row): ?>

    <?= $row["position_y"]; ?>,

<?php endforeach; ?>

];


const zPositions = [

<?php foreach ($xyzData as $row): ?>

    <?= $row["position_z"]; ?>,

<?php endforeach; ?>

];


new Chart(
    document.getElementById("xyzChart"),
    {

        type: "line",

        data: {

            labels: telemetryLabels,

            datasets: [

                {

                    label: "X Position",

                    data: xPositions,

                    tension: 0.3

                },

                {

                    label: "Y Position",

                    data: yPositions,

                    tension: 0.3

                },

                {

                    label: "Z Position",

                    data: zPositions,

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