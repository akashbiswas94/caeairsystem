<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? 'trainee')));
if ($role === '' || $role === 'trainee') {
    header('Location: ../trainee/dashboard.php');
    exit;
}

require_once '../config/database.php';

$sessionId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($sessionId <= 0) {
    die('Invalid training session.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim((string) ($_POST['status'] ?? 'Review'));
    $allowed = ['Review', 'Passed', 'Failed'];
    $scoreRaw = trim((string) ($_POST['overall_score'] ?? ''));
    $score = $scoreRaw === '' ? null : (float) $scoreRaw;

    if (!in_array($status, $allowed, true)) {
        $error = 'Invalid status.';
    } elseif ($score !== null && ($score < 0 || $score > 100)) {
        $error = 'Overall score must be between 0 and 100.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE training_sessions
             SET status = ?, overall_score = ?
             WHERE id = ?'
        );
        $stmt->execute([$status, $score, $sessionId]);
        $message = 'AAR result updated.';
    }
}

$stmt = $pdo->prepare(
    "SELECT ts.*, s.scenario_name, s.difficulty,
            u.first_name, u.last_name, u.email,
            sm.alignment_accuracy, sm.position_stability,
            sm.approach_accuracy, sm.safety_score, sm.reaction_time
     FROM training_sessions ts
     JOIN scenarios s ON s.id = ts.scenario_id
     JOIN users u ON u.id = ts.trainee_id
     LEFT JOIN session_metrics sm ON sm.session_id = ts.id
     WHERE ts.id = ?
     LIMIT 1"
);
$stmt->execute([$sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die('Training session not found.');
}

$stmt = $pdo->prepare(
    'SELECT * FROM session_telemetry
     WHERE session_id = ?
     ORDER BY timestamp_ms ASC, id ASC
     LIMIT 1000'
);
$stmt->execute([$sessionId]);
$telemetry = $stmt->fetchAll(PDO::FETCH_ASSOC);
$latestTelemetry = $telemetry ? $telemetry[array_key_last($telemetry)] : null;

$stmt = $pdo->prepare(
    'SELECT event_type, payload, created_at
     FROM session_events
     WHERE session_id = ?
     ORDER BY id ASC'
);
$stmt->execute([$sessionId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

function showValue($value, int $decimals = 2): string
{
    return $value === null || $value === '' ? '—' : number_format((float) $value, $decimals);
}

$statusLabel = $session['status'] ?: 'In Progress';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AAR Session #<?= (int) $session['id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-2 sidebar">
            <div class="p-4">
                <h4>AAR Training</h4>
                <p class="sidebar-subtitle">Administration</p>
                <hr>
                <a href="dashboard.php">Admin Dashboard</a>
                <a class="active" href="#">Session Review</a>
                <a href="../logout.php">Logout</a>
            </div>
        </aside>

        <main class="col-md-10 main-content">
            <div class="p-4 p-lg-5">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mb-3">← Back to Admin Dashboard</a>

                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <div class="eyebrow">AFTER ACTION REVIEW</div>
                        <h2 class="mb-1">Session #<?= (int) $session['id']; ?></h2>
                        <p class="text-muted mb-0">
                            <?= htmlspecialchars(trim($session['first_name'] . ' ' . $session['last_name'])); ?> ·
                            <?= htmlspecialchars($session['scenario_name']); ?>
                        </p>
                    </div>
                    <span class="status-pill"><?= htmlspecialchars($statusLabel); ?></span>
                </div>

                <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error); ?></div><?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="dashboard-card stat-card"><p>Score</p><h2><?= $session['overall_score'] === null ? '—' : number_format((float) $session['overall_score'], 1) . '%'; ?></h2></div></div>
                    <div class="col-md-3"><div class="dashboard-card stat-card"><p>Duration</p><h2><?= gmdate('i:s', max(0, (int) $session['duration_seconds'])); ?></h2></div></div>
                    <div class="col-md-3"><div class="dashboard-card stat-card"><p>Telemetry Samples</p><h2><?= count($telemetry); ?></h2></div></div>
                    <div class="col-md-3"><div class="dashboard-card stat-card"><p>Events</p><h2><?= count($events); ?></h2></div></div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-8">
                        <div class="dashboard-card">
                            <div class="eyebrow">FLIGHT DATA</div>
                            <h5>Position and Altitude</h5>
                            <canvas id="telemetryChart" height="100"></canvas>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div class="dashboard-card">
                            <div class="eyebrow">FINAL STATE</div>
                            <h5>Latest Telemetry</h5>
                            <hr>
                            <p class="mb-2">Aircraft <strong class="float-end"><?= htmlspecialchars($latestTelemetry['aircraft'] ?? '—'); ?></strong></p>
                            <p class="mb-2">X <strong class="float-end"><?= showValue($latestTelemetry['position_x'] ?? null); ?> m</strong></p>
                            <p class="mb-2">Y <strong class="float-end"><?= showValue($latestTelemetry['position_y'] ?? null); ?> m</strong></p>
                            <p class="mb-2">Altitude <strong class="float-end"><?= showValue($latestTelemetry['position_z'] ?? null); ?> m</strong></p>
                            <p class="mb-2">Speed <strong class="float-end"><?= showValue($latestTelemetry['speed_mps'] ?? null); ?> m/s</strong></p>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="dashboard-card">
                            <div class="eyebrow">TIMELINE</div>
                            <h5>Simulator Events</h5>
                            <div class="event-feed mt-3">
                                <?php if (!$events): ?>
                                    <p class="text-muted">No simulator events recorded.</p>
                                <?php endif; ?>
                                <?php foreach ($events as $event): ?>
                                    <div class="event-item">
                                        <span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $event['event_type']))); ?></span>
                                        <time><?= htmlspecialchars((string) $event['created_at']); ?></time>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="dashboard-card">
                            <div class="eyebrow">INSTRUCTOR / ADMIN</div>
                            <h5>Assessment</h5>
                            <form method="post" class="mt-3">
                                <div class="mb-3">
                                    <label class="form-label">Overall Score</label>
                                    <div class="input-group">
                                        <input type="number" min="0" max="100" step="0.1" name="overall_score" class="form-control" value="<?= htmlspecialchars((string) ($session['overall_score'] ?? '')); ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Result</label>
                                    <select name="status" class="form-select">
                                        <?php foreach (['Review', 'Passed', 'Failed'] as $option): ?>
                                            <option value="<?= $option; ?>" <?= $session['status'] === $option ? 'selected' : ''; ?>><?= $option; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-dark w-100" type="submit">Save AAR Result</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
const telemetry = <?= json_encode($telemetry, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES); ?>;

new Chart(document.getElementById('telemetryChart'), {
    type: 'line',
    data: {
        labels: telemetry.map(row => `${row.timestamp_ms} ms`),
        datasets: [
            {label: 'X (m)', data: telemetry.map(row => row.position_x), pointRadius: 0, tension: 0.2},
            {label: 'Y (m)', data: telemetry.map(row => row.position_y), pointRadius: 0, tension: 0.2},
            {label: 'Altitude (m)', data: telemetry.map(row => row.position_z), pointRadius: 0, tension: 0.2}
        ]
    },
    options: {
        responsive: true,
        animation: false,
        interaction: {mode: 'index', intersect: false}
    }
});
</script>
</body>
</html>
