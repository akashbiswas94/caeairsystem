<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

$traineeId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_sessions,
        ROUND(AVG(overall_score), 1) AS average_score,
        MAX(overall_score) AS best_score,
        COALESCE(SUM(duration_seconds), 0) AS total_training_time
     FROM training_sessions
     WHERE trainee_id = ?'
);
$stmt->execute([$traineeId]);
$stats = $stmt->fetch() ?: [];

$stmt = $pdo->prepare(
    'SELECT ts.id, ts.session_date, ts.overall_score, ts.status, ts.duration_seconds,
            s.scenario_name
     FROM training_sessions ts
     JOIN scenarios s ON s.id = ts.scenario_id
     WHERE ts.trainee_id = ?
     ORDER BY COALESCE(ts.started_at, ts.session_date) DESC, ts.id DESC
     LIMIT 20'
);
$stmt->execute([$traineeId]);
$sessions = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT ts.*, s.scenario_name,
            sm.alignment_accuracy, sm.position_stability,
            sm.approach_accuracy, sm.safety_score, sm.reaction_time
     FROM training_sessions ts
     JOIN scenarios s ON s.id = ts.scenario_id
     LEFT JOIN session_metrics sm ON sm.session_id = ts.id
     WHERE ts.trainee_id = ?
     ORDER BY COALESCE(ts.started_at, ts.session_date) DESC, ts.id DESC
     LIMIT 1'
);
$stmt->execute([$traineeId]);
$latest = $stmt->fetch() ?: null;

$telemetry = null;
$xyzData = [];
$events = [];

if ($latest) {
    $stmt = $pdo->prepare('SELECT * FROM session_telemetry WHERE session_id = ? ORDER BY timestamp_ms DESC LIMIT 1');
    $stmt->execute([$latest['id']]);
    $telemetry = $stmt->fetch() ?: null;

    $stmt = $pdo->prepare(
        'SELECT timestamp_ms, position_x, position_y, position_z, speed_mps
         FROM session_telemetry
         WHERE session_id = ?
         ORDER BY timestamp_ms DESC
         LIMIT 120'
    );
    $stmt->execute([$latest['id']]);
    $xyzData = array_reverse($stmt->fetchAll());

    $stmt = $pdo->prepare(
        'SELECT event_type, created_at
         FROM session_events
         WHERE session_id = ?
         ORDER BY id DESC
         LIMIT 8'
    );
    $stmt->execute([$latest['id']]);
    $events = $stmt->fetchAll();
}

$stmt = $pdo->prepare(
    'SELECT id, overall_score
     FROM training_sessions
     WHERE trainee_id = ? AND overall_score IS NOT NULL
     ORDER BY session_date ASC'
);
$stmt->execute([$traineeId]);
$performance = $stmt->fetchAll();

function displayNumber($value, int $decimals = 2): string
{
    return $value === null || $value === '' ? '—' : number_format((float) $value, $decimals);
}

function scoreText($value): string
{
    return $value === null || $value === '' ? '—' : number_format((float) $value, 1) . '%';
}

function eventLabel(string $event): string
{
    return ucwords(str_replace('_', ' ', $event));
}

function sessionStatusClass($status): string
{
    if ($status === null || $status === '') {
        return 'in-progress';
    }

    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $status));
}

function trainingTime($seconds): string
{
    $seconds = max(0, (int) $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Training Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-inner">
            <div class="brand">
                <div class="brand-mark">AAR</div>
                <div>
                    <div class="brand-name">Training Review</div>
                    <div class="brand-subtitle">Trainee workspace</div>
                </div>
            </div>

            <div class="nav-group">
                <div class="nav-label">Workspace</div>
                <nav class="sidebar-nav">
                    <a class="active" href="dashboard.php"><span class="nav-icon"></span>Overview</a>
                    <a href="#live"><span class="nav-icon"></span>Live data</a>
                    <a href="#performance"><span class="nav-icon"></span>Performance</a>
                    <a href="#sessions"><span class="nav-icon"></span>Sessions</a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="user-chip">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Trainee'); ?></strong>
                    <span>Trainee</span>
                </div>
                <nav class="sidebar-nav">
                    <?php if (strtolower((string) ($_SESSION['role'] ?? 'trainee')) !== 'trainee'): ?>
                        <a href="../admin/dashboard.php"><span class="nav-icon"></span>Admin</a>
                    <?php endif; ?>
                    <a href="../logout.php"><span class="nav-icon"></span>Sign out</a>
                </nav>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-wrap">
            <header class="page-header">
                <div>
                    <div class="page-kicker">Trainee</div>
                    <h1 class="page-title"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Training Dashboard'); ?></h1>
                    <p class="page-subtitle">Training dashboard</p>
                </div>
                <span id="liveStatus" class="live-badge <?= $latest && $latest['status'] === null ? 'is-live' : ''; ?>">
                    <span class="live-dot"></span>
                    <?php
                    if ($latest && $latest['status'] === null) {
                        echo 'Live session';
                    } elseif ($latest && $latest['status']) {
                        echo htmlspecialchars((string) $latest['status']);
                    } else {
                        echo 'No active session';
                    }
                    ?>
                </span>
            </header>

            <section class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Average score</div>
                        <div class="stat-value"><?= scoreText($stats['average_score'] ?? null); ?></div>
                        <div class="stat-foot">All scored sessions</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Sessions</div>
                        <div class="stat-value"><?= (int) ($stats['total_sessions'] ?? 0); ?></div>
                        <div class="stat-foot">Total attempts</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Best score</div>
                        <div class="stat-value"><?= scoreText($stats['best_score'] ?? null); ?></div>
                        <div class="stat-foot">Personal best</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-label">Training time</div>
                        <div class="stat-value"><?= htmlspecialchars(trainingTime($stats['total_training_time'] ?? 0)); ?></div>
                        <div class="stat-foot">Recorded time</div>
                    </div>
                </div>
            </section>

            <section id="live" class="row g-4 mb-4">
                <div class="col-xl-8">
                    <div class="dashboard-card live-panel h-100">
                        <div class="panel-header">
                            <div>
                                <div class="eyebrow">Current session</div>
                                <h2 class="panel-title"><?= htmlspecialchars($latest['scenario_name'] ?? 'No session'); ?></h2>
                                <div class="panel-meta" id="sessionLabel">
                                    <?= $latest ? 'Session #' . (int) $latest['id'] : 'Ready'; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                                <span class="aircraft-chip" id="aircraftValue"><?= htmlspecialchars($telemetry['aircraft'] ?? 'F15'); ?></span>
                                <button
                                    type="button"
                                    id="start-game-btn"
                                    class="btn btn-primary btn-sm"
                                    onclick="startGame()"
                                    <?= $latest && $latest['status'] === null ? 'disabled' : ''; ?>
                                >
                                    Start simulation
                                </button>
                                <button
                                    type="button"
                                    id="end-session-btn"
                                    class="btn btn-outline-danger btn-sm"
                                    onclick="endCurrentSession()"
                                    <?= $latest && $latest['status'] === null ? '' : 'disabled'; ?>
                                >
                                    End session
                                </button>
                            </div>
                        </div>

                        <div class="metric-grid">
                            <div class="metric-cell">
                                <span class="metric-label">X position</span>
                                <strong class="metric-value" id="xValue"><?= displayNumber($telemetry['position_x'] ?? null); ?></strong>
                                <span class="metric-unit">m</span>
                            </div>
                            <div class="metric-cell">
                                <span class="metric-label">Y position</span>
                                <strong class="metric-value" id="yValue"><?= displayNumber($telemetry['position_y'] ?? null); ?></strong>
                                <span class="metric-unit">m</span>
                            </div>
                            <div class="metric-cell">
                                <span class="metric-label">Altitude</span>
                                <strong class="metric-value" id="zValue"><?= displayNumber($telemetry['position_z'] ?? null); ?></strong>
                                <span class="metric-unit">m</span>
                            </div>
                            <div class="metric-cell">
                                <span class="metric-label">Speed</span>
                                <strong class="metric-value" id="speedValue"><?= displayNumber($telemetry['speed_mps'] ?? null); ?></strong>
                                <span class="metric-unit">m/s</span>
                            </div>
                        </div>

                        <div class="orientation-strip">
                            <div><span class="metric-label">Pitch</span><strong id="pitchValue"><?= displayNumber($telemetry['rotation_pitch'] ?? null); ?>°</strong></div>
                            <div><span class="metric-label">Yaw</span><strong id="yawValue"><?= displayNumber($telemetry['rotation_yaw'] ?? null); ?>°</strong></div>
                            <div><span class="metric-label">Roll</span><strong id="rollValue"><?= displayNumber($telemetry['rotation_roll'] ?? null); ?>°</strong></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4" id="events">
                    <div class="dashboard-card h-100">
                        <div class="panel-header">
                            <div>
                                <div class="eyebrow">Events</div>
                                <h2 class="panel-title">Recent activity</h2>
                            </div>
                        </div>
                        <div id="eventFeed" class="event-feed">
                            <?php if ($events): ?>
                                <?php foreach ($events as $event): ?>
                                    <div class="event-item">
                                        <span><?= htmlspecialchars(eventLabel($event['event_type'])); ?></span>
                                        <time><?= date('H:i:s', strtotime($event['created_at'])); ?></time>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">No events.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="row g-4 mb-4" id="performance">
                <div class="col-xl-8">
                    <div class="dashboard-card h-100">
                        <div class="panel-header">
                            <div>
                                <div class="eyebrow">Telemetry</div>
                                <h2 class="panel-title">Flight track</h2>
                                <div class="panel-meta">X, Y and altitude</div>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="xyzChart" height="105"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="dashboard-card h-100">
                        <div class="panel-header">
                            <div>
                                <div class="eyebrow">Scores</div>
                                <h2 class="panel-title">Progress</h2>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="performanceChart" height="210"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <section id="sessions" class="dashboard-card">
                <div class="panel-header">
                    <div>
                        <div class="eyebrow">History</div>
                        <h2 class="panel-title">Training sessions</h2>
                    </div>
                </div>

                <div class="table-wrap table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Scenario</th>
                            <th>Duration</th>
                            <th>Score</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$sessions): ?>
                            <tr><td colspan="5"><div class="empty-state">No sessions recorded.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($sessions as $session): ?>
                            <?php
                            $sessionStatus = $session['status'] ?? null;
                            $statusLabel = $sessionStatus ?: 'In Progress';
                            ?>
                            <tr class="clickable-row" onclick="window.location='session.php?id=<?= (int) $session['id']; ?>'">
                                <td class="mono"><?= date('d M Y, H:i', strtotime($session['session_date'])); ?></td>
                                <td class="person-name"><?= htmlspecialchars($session['scenario_name']); ?></td>
                                <td class="mono"><?= gmdate('i:s', max(0, (int) $session['duration_seconds'])); ?></td>
                                <td class="score-value"><?= scoreText($session['overall_score']); ?></td>
                                <td><span class="status-pill status-<?= htmlspecialchars(sessionStatusClass($sessionStatus)); ?>"><?= htmlspecialchars($statusLabel); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
const initialTelemetry = <?= json_encode($xyzData, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES); ?>;
const performance = <?= json_encode($performance, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES); ?>;

Chart.defaults.color = '#64748b';
Chart.defaults.font.family = 'Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

const telemetryChart = new Chart(document.getElementById('xyzChart'), {
    type: 'line',
    data: {
        labels: initialTelemetry.map(row => `${row.timestamp_ms} ms`),
        datasets: [
            { label: 'X', data: initialTelemetry.map(row => row.position_x), borderColor: '#2563eb', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2 },
            { label: 'Y', data: initialTelemetry.map(row => row.position_y), borderColor: '#06b6d4', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2 },
            { label: 'Altitude', data: initialTelemetry.map(row => row.position_z), borderColor: '#64748b', backgroundColor: 'transparent', tension: 0.25, pointRadius: 0, borderWidth: 2 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 9, boxHeight: 9, usePointStyle: true } } },
        scales: {
            x: { grid: { display: false }, ticks: { maxTicksLimit: 7 } },
            y: { grid: { color: '#eef2f7' } }
        }
    }
});

new Chart(document.getElementById('performanceChart'), {
    type: 'line',
    data: {
        labels: performance.map((row, index) => `S${index + 1}`),
        datasets: [{
            label: 'Score',
            data: performance.map(row => row.overall_score),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, .08)',
            fill: true,
            tension: 0.3,
            pointRadius: 3,
            pointHoverRadius: 4,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, max: 100, grid: { color: '#eef2f7' } }
        }
    }
});

let lastTelemetryId = <?= (int) ($telemetry['id'] ?? 0); ?>;
let currentLiveSessionId = <?= $latest && $latest['status'] === null ? (int) $latest['id'] : 'null'; ?>;
let currentChartSessionId = <?= $latest ? (int) $latest['id'] : 'null'; ?>;

function numberValue(value, decimals = 2) {
    if (value === null || value === undefined || value === '') return '—';
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(decimals) : '—';
}

function eventTitle(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
}

function updateEventFeed(events) {
    const feed = document.getElementById('eventFeed');

    if (!events || !events.length) {
        feed.innerHTML = '<div class="empty-state">No events.</div>';
        return;
    }

    feed.innerHTML = events.map(event => {
        const date = new Date(String(event.created_at).replace(' ', 'T'));
        const time = Number.isNaN(date.getTime())
            ? ''
            : date.toLocaleTimeString([], { hour12: false });

        return `
            <div class="event-item">
                <span>${eventTitle(event.event_type)}</span>
                <time>${time}</time>
            </div>
        `;
    }).join('');
}

function resetTelemetryChart(sessionId) {
    currentChartSessionId = Number(sessionId);
    lastTelemetryId = 0;
    telemetryChart.data.labels = [];
    telemetryChart.data.datasets.forEach(dataset => {
        dataset.data = [];
    });
    telemetryChart.update('none');
}

function appendTelemetryPoint(row) {
    if (!row) return;

    const rowId = Number(row.id);
    if (rowId === lastTelemetryId) return;

    lastTelemetryId = rowId;

    telemetryChart.data.labels.push(`${row.timestamp_ms} ms`);
    telemetryChart.data.datasets[0].data.push(Number(row.position_x));
    telemetryChart.data.datasets[1].data.push(Number(row.position_y));
    telemetryChart.data.datasets[2].data.push(Number(row.position_z));

    while (telemetryChart.data.labels.length > 120) {
        telemetryChart.data.labels.shift();
        telemetryChart.data.datasets.forEach(dataset => dataset.data.shift());
    }

    telemetryChart.update('none');
}

function setNoSessionState() {
    currentLiveSessionId = null;

    const status = document.getElementById('liveStatus');
    status.classList.remove('is-live');
    status.innerHTML = '<span class="live-dot"></span>No active session';

    document.getElementById('sessionLabel').textContent = 'Ready';
    document.getElementById('end-session-btn').disabled = true;
    document.getElementById('start-game-btn').disabled = false;
}

async function refreshLiveData() {
    try {
        const response = await fetch('../api/latest.php', { cache: 'no-store' });
        const data = await response.json();

        if (!data.success) {
            return;
        }

        if (!data.session) {
            setNoSessionState();
            updateEventFeed([]);
            return;
        }

        const sessionId = Number(data.session.id);
        const isLive = data.session.status === null;

        if (currentChartSessionId !== sessionId) {
            resetTelemetryChart(sessionId);
        }

        currentLiveSessionId = isLive ? sessionId : null;

        const status = document.getElementById('liveStatus');
        status.classList.toggle('is-live', isLive);

        const statusLabel = data.session.status_label || data.session.status || 'In Progress';
        status.innerHTML = `<span class="live-dot"></span>${isLive ? 'Live session' : String(statusLabel)}`;

        document.getElementById('sessionLabel').textContent =
            `Session #${sessionId} · ${data.session.scenario_name || 'Main_World'}`;

        const endButton = document.getElementById('end-session-btn');
        endButton.disabled = !isLive;

        const startButton = document.getElementById('start-game-btn');
        startButton.disabled = isLive;
        startButton.textContent = isLive ? 'Simulation running' : 'Start simulation';

        if (data.telemetry) {
            const telemetry = data.telemetry;
            document.getElementById('aircraftValue').textContent = telemetry.aircraft || 'F15';
            document.getElementById('xValue').textContent = numberValue(telemetry.position_x);
            document.getElementById('yValue').textContent = numberValue(telemetry.position_y);
            document.getElementById('zValue').textContent = numberValue(telemetry.position_z);
            document.getElementById('speedValue').textContent = numberValue(telemetry.speed_mps);
            document.getElementById('pitchValue').textContent = numberValue(telemetry.rotation_pitch) + '°';
            document.getElementById('yawValue').textContent = numberValue(telemetry.rotation_yaw) + '°';
            document.getElementById('rollValue').textContent = numberValue(telemetry.rotation_roll) + '°';
            appendTelemetryPoint(telemetry);
        }

        updateEventFeed(data.events || []);
    } catch (error) {
        // Keep the last known values on screen if the local API is unavailable.
    }
}

async function startGame() {
    const button = document.getElementById('start-game-btn');
    button.disabled = true;
    button.textContent = 'Launching…';

    try {
        const response = await fetch('../api/start-game.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });

        const data = await response.json();

        if (!data.success) {
            button.disabled = false;
            button.textContent = 'Start simulation';
            alert(data.message || 'Unable to start simulator.');
            return;
        }

        currentLiveSessionId = Number(data.session_id);
        button.textContent = 'Simulation running';

        const status = document.getElementById('liveStatus');
        status.classList.add('is-live');
        status.innerHTML = '<span class="live-dot"></span>Launching';

        document.getElementById('sessionLabel').textContent = `Session #${data.session_id} · Main_World`;
        setTimeout(refreshLiveData, 1500);
    } catch (error) {
        button.disabled = false;
        button.textContent = 'Start simulation';
        alert('Unable to contact simulator launcher.');
    }
}

async function endCurrentSession() {
    if (!currentLiveSessionId) {
        alert('There is no active session.');
        return;
    }

    const confirmed = confirm('Stop Unreal first, then end this session.');
    if (!confirmed) return;

    const button = document.getElementById('end-session-btn');
    button.disabled = true;

    try {
        const response = await fetch('../api/end-session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: currentLiveSessionId })
        });

        const data = await response.json();

        if (!data.success) {
            button.disabled = false;
            alert(data.message || 'Unable to end session.');
            return;
        }

        currentLiveSessionId = null;
        window.location.reload();
    } catch (error) {
        button.disabled = false;
        alert('Unable to end session.');
    }
}

refreshLiveData();
setInterval(refreshLiveData, 500);
</script>
</body>
</html>
