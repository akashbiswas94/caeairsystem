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

$traineeCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'trainee'")->fetchColumn();
$totalSessions = (int) $pdo->query('SELECT COUNT(*) FROM training_sessions')->fetchColumn();
$liveSessions = (int) $pdo->query('SELECT COUNT(*) FROM training_sessions WHERE status IS NULL')->fetchColumn();
$averageScore = $pdo->query('SELECT ROUND(AVG(overall_score), 1) FROM training_sessions WHERE overall_score IS NOT NULL')->fetchColumn();

$trainees = $pdo->query(
    "SELECT id, first_name, last_name, email
     FROM users
     WHERE LOWER(role) = 'trainee'
     ORDER BY first_name, last_name"
)->fetchAll(PDO::FETCH_ASSOC);

$scenarios = $pdo->query(
    'SELECT id, scenario_name
     FROM scenarios
     ORDER BY id'
)->fetchAll(PDO::FETCH_ASSOC);

$activeSessions = $pdo->query(
    "SELECT ts.id, ts.started_at, ts.session_date,
            u.first_name, u.last_name,
            s.scenario_name,
            (SELECT COUNT(*) FROM session_telemetry st WHERE st.session_id = ts.id) AS telemetry_rows
     FROM training_sessions ts
     JOIN users u ON u.id = ts.trainee_id
     JOIN scenarios s ON s.id = ts.scenario_id
     WHERE ts.status IS NULL
     ORDER BY ts.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$recentSessions = $pdo->query(
    "SELECT ts.id, ts.status, ts.started_at, ts.session_date, ts.duration_seconds, ts.overall_score,
            u.first_name, u.last_name, u.email,
            s.scenario_name,
            sm.alignment_accuracy, sm.position_stability,
            sm.approach_accuracy, sm.safety_score
     FROM training_sessions ts
     JOIN users u ON u.id = ts.trainee_id
     JOIN scenarios s ON s.id = ts.scenario_id
     LEFT JOIN session_metrics sm ON sm.session_id = ts.id
     ORDER BY COALESCE(ts.started_at, ts.session_date) DESC, ts.id DESC
     LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

function scoreText($value): string
{
    return $value === null || $value === '' ? '—' : number_format((float) $value, 1) . '%';
}

function statusLabel($status): string
{
    return $status === null || $status === '' ? 'In Progress' : (string) $status;
}

function statusClass($status): string
{
    if ($status === null || $status === '') {
        return 'in-progress';
    }

    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $status));
}

function sessionAverage(array $session): ?float
{
    $values = [];
    foreach (['alignment_accuracy', 'position_stability', 'approach_accuracy', 'safety_score'] as $key) {
        if (isset($session[$key]) && $session[$key] !== '') {
            $values[] = (float) $session[$key];
        }
    }

    if ($values) {
        return array_sum($values) / count($values);
    }

    return isset($session['overall_score']) && $session['overall_score'] !== ''
        ? (float) $session['overall_score']
        : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AAR Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-inner">
            <div class="brand">
                <div class="brand-mark">AAR</div>
                <div>
                    <div class="brand-name">Training Review</div>
                    <div class="brand-subtitle">Instructor console</div>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-label">Workspace</div>
                <nav class="sidebar-nav">
                    <a class="active" href="dashboard.php"><span class="nav-icon"></span>Overview</a>
                    <a href="#launch"><span class="nav-icon"></span>Launch</a>
                    <a href="#active"><span class="nav-icon"></span>Live sessions</a>
                    <a href="#history"><span class="nav-icon"></span>Session history</a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="user-chip">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?></strong>
                    <span><?= htmlspecialchars(ucfirst($role)); ?></span>
                </div>
                <nav class="sidebar-nav">
                    <a href="../logout.php"><span class="nav-icon"></span>Sign out</a>
                </nav>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-wrap">
            <header class="page-header">
                <div>
                    <div class="page-kicker">Instructor console</div>
                    <h1 class="page-title">AAR Control</h1>
                    <p class="page-subtitle"><?= date('l, d M Y'); ?></p>
                </div>
                <span class="role-badge"><?= htmlspecialchars($role); ?></span>
            </header>

            <section class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Trainees</div>
                        <div class="stat-value"><?= $traineeCount; ?></div>
                        <div class="stat-foot">Registered</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Sessions</div>
                        <div class="stat-value"><?= $totalSessions; ?></div>
                        <div class="stat-foot">All records</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Live</div>
                        <div class="stat-value"><?= $liveSessions; ?></div>
                        <div class="stat-foot">In progress</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Average score</div>
                        <div class="stat-value"><?= scoreText($averageScore); ?></div>
                        <div class="stat-foot">Completed scoring</div>
                    </div>
                </div>
            </section>

            <section id="launch" class="dashboard-card launch-panel mb-4">
                <div class="panel-header">
                    <div>
                        <div class="eyebrow">Simulator</div>
                        <h2 class="panel-title">Launch simulation</h2>
                    </div>
                    <span class="aircraft-chip">Main_World</span>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label" for="launchTrainee">Trainee</label>
                        <select id="launchTrainee" class="form-select">
                            <?php foreach ($trainees as $trainee): ?>
                                <option value="<?= (int) $trainee['id']; ?>">
                                    <?= htmlspecialchars(trim($trainee['first_name'] . ' ' . $trainee['last_name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="launchScenario">Scenario</label>
                        <select id="launchScenario" class="form-select">
                            <?php foreach ($scenarios as $scenario): ?>
                                <option value="<?= (int) $scenario['id']; ?>"><?= htmlspecialchars($scenario['scenario_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <button id="adminLaunchButton" class="btn btn-primary w-100" onclick="launchSimulation()" <?= !$trainees || !$scenarios ? 'disabled' : ''; ?>>
                            Start simulation
                        </button>
                    </div>
                </div>
                <div id="launchMessage" class="form-text mt-2"></div>
            </section>

            <section id="active" class="dashboard-card mb-4">
                <div class="panel-header">
                    <div>
                        <div class="eyebrow">Live</div>
                        <h2 class="panel-title">Active sessions</h2>
                        <div class="panel-meta"><?= count($activeSessions); ?> currently running</div>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">Refresh</button>
                </div>

                <div class="table-wrap table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
                            <th>Session</th>
                            <th>Trainee</th>
                            <th>Scenario</th>
                            <th>Started</th>
                            <th>Samples</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$activeSessions): ?>
                            <tr><td colspan="6"><div class="empty-state">No active sessions.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($activeSessions as $session): ?>
                            <tr>
                                <td class="session-id">#<?= (int) $session['id']; ?></td>
                                <td class="person-name"><?= htmlspecialchars(trim($session['first_name'] . ' ' . $session['last_name'])); ?></td>
                                <td><?= htmlspecialchars($session['scenario_name']); ?></td>
                                <td class="mono"><?= date('d M, H:i', strtotime((string) ($session['started_at'] ?: $session['session_date']))); ?></td>
                                <td class="mono"><?= number_format((int) $session['telemetry_rows']); ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="session.php?id=<?= (int) $session['id']; ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="history" class="dashboard-card">
                <div class="panel-header">
                    <div>
                        <div class="eyebrow">AAR</div>
                        <h2 class="panel-title">Session history</h2>
                        <div class="panel-meta">Latest <?= min(50, count($recentSessions)); ?> sessions</div>
                    </div>
                </div>

                <div class="table-wrap table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Session</th>
                            <th>Trainee</th>
                            <th>Scenario</th>
                            <th>Started</th>
                            <th>Duration</th>
                            <th>Session avg</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentSessions as $session): ?>
                            <?php
                            $status = statusLabel($session['status']);
                            $avg = sessionAverage($session);
                            ?>
                            <tr>
                                <td class="session-id">#<?= (int) $session['id']; ?></td>
                                <td>
                                    <div class="person-name"><?= htmlspecialchars(trim($session['first_name'] . ' ' . $session['last_name'])); ?></div>
                                    <div class="person-meta"><?= htmlspecialchars($session['email']); ?></div>
                                </td>
                                <td><?= htmlspecialchars($session['scenario_name']); ?></td>
                                <td class="mono"><?= date('d M Y, H:i', strtotime((string) ($session['started_at'] ?: $session['session_date']))); ?></td>
                                <td class="mono"><?= gmdate('i:s', max(0, (int) $session['duration_seconds'])); ?></td>
                                <td><span class="score-value"><?= scoreText($avg); ?></span></td>
                                <td><?= scoreText($session['overall_score']); ?></td>
                                <td><span class="status-pill status-<?= htmlspecialchars(statusClass($session['status'])); ?>"><?= htmlspecialchars($status); ?></span></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="session.php?id=<?= (int) $session['id']; ?>">Review</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentSessions): ?>
                            <tr><td colspan="9"><div class="empty-state">No sessions recorded.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
async function launchSimulation() {
    const button = document.getElementById('adminLaunchButton');
    const message = document.getElementById('launchMessage');
    const traineeId = Number(document.getElementById('launchTrainee').value);
    const scenarioId = Number(document.getElementById('launchScenario').value);

    button.disabled = true;
    button.textContent = 'Launching…';
    message.textContent = 'Starting local simulator…';

    try {
        const response = await fetch('../api/start-game.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ trainee_id: traineeId, scenario_id: scenarioId })
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Unable to launch simulator.');
        }

        message.textContent = `Session #${data.session_id} started.`;
        button.textContent = 'Started';
        setTimeout(() => window.location.reload(), 2200);
    } catch (error) {
        message.textContent = error.message;
        button.disabled = false;
        button.textContent = 'Start simulation';
    }
}
</script>
</body>
</html>
