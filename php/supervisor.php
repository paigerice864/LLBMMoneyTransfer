<?php
// HeisDaemon Autonomous Bot Supervisor & Control Center
// lagoslife.hostedbyfps.com/supervisor.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot.php';

$stateFile  = __DIR__ . '/poller_state.json';
$stopFile   = __DIR__ . '/poller.stop';
$offsetFile = __DIR__ . '/poller_offset.dat';
$historyLog = __DIR__ . '/poller_history.log';

$authKey = $_GET['key'] ?? ($_GET['auth'] ?? ($_POST['key'] ?? ''));
$isMaster = ($authKey === (string)MASTER_ADMIN_ID || $authKey === 'Akorede@1' || $authKey === 'heisdaemon');

function spawnPoller(): void {
    $url = "https://lagoslife.hostedbyfps.com/poller.php?spawn=" . time();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "HeisDaemon-Supervisor/2.0");
    @curl_exec($ch);
    @curl_close($ch);
}

function getPollerData(): array {
    global $stateFile, $offsetFile;
    $state = [
        'status'          => 'offline',
        'pid'             => 0,
        'started_at'      => 0,
        'heartbeat'       => 0,
        'offset'          => 0,
        'total_processed' => 0,
        'last_action'     => 'None',
        'logs'            => [],
        'heartbeat_age'   => 999999,
        'is_alive'        => false,
    ];

    if (file_exists($stateFile)) {
        $raw = @file_get_contents($stateFile);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $state = array_merge($state, $data);
        }
    }

    if (file_exists($offsetFile)) {
        $state['offset'] = (int)trim(@file_get_contents($offsetFile));
    }

    $now = time();
    $age = $state['heartbeat'] > 0 ? ($now - $state['heartbeat']) : 999999;
    $state['heartbeat_age'] = $age;
    $state['is_alive'] = ($state['status'] === 'online' && $age < 30);

    return $state;
}

// ────────────────────────────────────────────────────────────────────────────
// API Handler
// ────────────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

if ($action) {
    header('Content-Type: application/json');

    // Cron Trigger
    if ($action === 'cron') {
        $state = getPollerData();
        if (!$state['is_alive']) {
            @unlink($stopFile);
            tgCall('deleteWebhook');
            spawnPoller();
            echo json_encode(['ok' => true, 'action' => 'revived', 'timestamp' => date('c')]);
        } else {
            echo json_encode(['ok' => true, 'action' => 'already_active', 'pid' => $state['pid'], 'timestamp' => date('c')]);
        }
        exit;
    }

    if ($action === 'status') {
        echo json_encode(['ok' => true, 'data' => getPollerData()]);
        exit;
    }

    if ($action === 'pulse') {
        $auto = !empty($_GET['auto']);
        $state = getPollerData();
        $revived = false;
        if ($auto && !$state['is_alive']) {
            @unlink($stopFile);
            tgCall('deleteWebhook');
            spawnPoller();
            $revived = true;
        }
        echo json_encode(['ok' => true, 'data' => $state, 'revived' => $revived]);
        exit;
    }

    if ($action === 'start') {
        @unlink($stopFile);
        tgCall('deleteWebhook');
        spawnPoller();
        echo json_encode(['ok' => true, 'message' => 'Start signal sent to poller daemon']);
        exit;
    }

    if ($action === 'stop') {
        @file_put_contents($stopFile, '1');
        $state = getPollerData();
        $state['status'] = 'stopped';
        $state['last_action'] = 'Stopped by supervisor user';
        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true, 'message' => 'Stop signal delivered']);
        exit;
    }

    if ($action === 'restart') {
        @file_put_contents($stopFile, '1');
        tgCall('deleteWebhook');
        usleep(300000); // 0.3s
        @unlink($stopFile);
        spawnPoller();
        echo json_encode(['ok' => true, 'message' => 'Bot poller restart triggered']);
        exit;
    }

    if ($action === 'clear_logs') {
        $state = getPollerData();
        $state['logs'] = [];
        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

$state = getPollerData();
$cronUrl = "https://lagoslife.hostedbyfps.com/supervisor.php?action=cron&key=" . MASTER_ADMIN_ID;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ HeisDaemon Bot Supervisor</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07090e;
            --card: #0f141f;
            --card-sub: #161d2d;
            --border: #1e293b;
            --accent: #10b981;
            --accent-glow: rgba(16, 185, 129, 0.25);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.25);
            --warning: #f59e0b;
            --warning-glow: rgba(245, 158, 11, 0.25);
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            max-width: 640px;
            width: 100%;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .subtitle {
            color: var(--text-muted);
            font-size: 14px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
        .status-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 18px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .status-online {
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(16, 185, 129, 0.4);
        }
        .status-offline {
            background: var(--danger-glow);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.4);
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .dot-online {
            background: var(--accent);
            box-shadow: 0 0 12px var(--accent);
            animation: pulse-green 1.8s infinite;
        }
        .dot-offline {
            background: var(--danger);
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .metric-box {
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
        }
        .metric-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .metric-val {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            font-family: 'JetBrains Mono', monospace;
        }
        .btn-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .btn {
            border: none;
            padding: 14px 10px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn:active { transform: scale(0.97); }
        .btn-start {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-restart {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
        }
        .btn-stop {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .toggle-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            margin-top: 14px;
        }
        .toggle-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .toggle-title {
            font-size: 14px;
            font-weight: 700;
        }
        .toggle-desc {
            font-size: 12px;
            color: var(--text-muted);
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 26px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155;
            transition: .3s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--accent); }
        input:checked + .slider:before { transform: translateX(22px); }
        
        .cron-box {
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            margin-top: 14px;
        }
        .cron-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cron-input-row {
            display: flex;
            gap: 8px;
        }
        .cron-input {
            flex: 1;
            background: #0b0f17;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #cbd5e1;
            font-size: 12px;
            padding: 8px 10px;
            font-family: 'JetBrains Mono', monospace;
            outline: none;
        }
        .btn-copy {
            background: #1e293b;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-copy:hover { background: #334155; }
        
        .log-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
        .log-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .log-title {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-clear {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            cursor: pointer;
        }
        .btn-clear:hover { color: #fff; border-color: #475569; }
        .log-box {
            background: #05070b;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            max-height: 240px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            line-height: 1.6;
        }
        .log-entry {
            color: #94a3b8;
            padding: 2px 0;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            word-break: break-all;
        }
        .log-entry.fresh { color: #34d399; }
        .footer-nav {
            margin-top: 20px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 16px;
            font-size: 13px;
        }
        .footer-nav a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-nav a:hover { color: var(--accent); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-badge">⚡ HEISDAEMON CLOUD ENGINE</div>
            <h1>Bot Web Supervisor</h1>
            <p class="subtitle">Autonomous 24/7 Server-Side Poller & Supervision Portal</p>
        </div>

        <div class="card">
            <div class="status-hero">
                <div>
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">CURRENT STATUS</div>
                    <div id="statusBadge" class="status-badge <?= $state['is_alive'] ? 'status-online' : 'status-offline' ?>">
                        <span id="statusDot" class="dot <?= $state['is_alive'] ? 'dot-online' : 'dot-offline' ?>"></span>
                        <span id="statusText"><?= $state['is_alive'] ? 'ONLINE (Polling Active)' : 'OFFLINE (Bot Stopped)' ?></span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 4px;">HEARTBEAT</div>
                    <div id="heartbeatText" style="font-size: 15px; font-weight: 700; font-family: 'JetBrains Mono', monospace; color: <?= $state['is_alive'] ? '#10b981' : '#ef4444' ?>;">
                        <?= $state['is_alive'] ? ($state['heartbeat_age'] . 's ago') : 'Expired' ?>
                    </div>
                </div>
            </div>

            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="metric-label">Server PID</div>
                    <div class="metric-val" id="metricPid">#<?= $state['pid'] ?: 'None' ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Updates Handled</div>
                    <div class="metric-val" id="metricUpdates"><?= number_format($state['total_processed']) ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Server Cron Job</div>
                    <div class="metric-val" style="font-size: 14px; color: #10b981;">🟢 Active (Every 1m)</div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Telegram Offset</div>
                    <div class="metric-val" id="metricOffset"><?= $state['offset'] ?: '0' ?></div>
                </div>
            </div>

            <div class="btn-grid">
                <button onclick="controlBot('start')" class="btn btn-start">🟢 Start Bot</button>
                <button onclick="controlBot('restart')" class="btn btn-restart">🔄 Restart</button>
                <button onclick="controlBot('stop')" class="btn btn-stop">🛑 Stop Bot</button>
            </div>

            <div class="toggle-section">
                <div class="toggle-text">
                    <span class="toggle-title">📱 Mobile / Browser Keep-Alive</span>
                    <span class="toggle-desc">Automatically maintains the poller while this browser tab is open</span>
                </div>
                <label class="switch">
                    <input type="checkbox" id="keepAliveToggle" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="cron-box">
                <div class="cron-label">
                    <span>⚡ Server Crontab Configured on FPS.ms / DirectAdmin</span>
                </div>
                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 8px; line-height: 1.5;">
                    The background daemon is scheduled to run <b>every minute (*/1 * * * *)</b> on the server. Even if your PC is turned off and your phone browser is closed, the server automatically keeps the bot alive 24/7!
                </div>
                <div class="cron-input-row">
                    <input type="text" class="cron-input" readonly value="<?= htmlspecialchars($cronUrl) ?>" id="cronUrlInput">
                    <button class="btn-copy" onclick="copyCronUrl()">Copy URL</button>
                </div>
            </div>
        </div>

        <div class="log-card">
            <div class="log-header">
                <div class="log-title">
                    <span>📋 Live Event Stream</span>
                </div>
                <button class="btn-clear" onclick="clearLogs()">Clear</button>
            </div>
            <div class="log-box" id="logBox">
                <?php if (empty($state['logs'])): ?>
                    <div class="log-entry">Waiting for incoming Telegram activity...</div>
                <?php else: ?>
                    <?php foreach ($state['logs'] as $idx => $l): ?>
                        <div class="log-entry <?= $idx === 0 ? 'fresh' : '' ?>">
                            [<?= htmlspecialchars($l['time']) ?>] <?= htmlspecialchars($l['text']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-nav">
            <a href="https://t.me/HeisdaemonBot" target="_blank">🚀 Open @HeisdaemonBot</a>
            <span>&bull;</span>
            <a href="https://t.me/Heisdaemon" target="_blank">📢 Telegram Channel</a>
            <span>&bull;</span>
            <a href="index.php">🏠 Suite Portal</a>
        </div>
    </div>

    <script>
        let isAlive = <?= $state['is_alive'] ? 'true' : 'false' ?>;

        function updateUI(data) {
            const badge = document.getElementById('statusBadge');
            const dot = document.getElementById('statusDot');
            const text = document.getElementById('statusText');
            const hb = document.getElementById('heartbeatText');
            const pid = document.getElementById('metricPid');
            const up = document.getElementById('metricUpdates');
            const off = document.getElementById('metricOffset');
            const logBox = document.getElementById('logBox');

            isAlive = data.is_alive;

            if (data.is_alive) {
                badge.className = 'status-badge status-online';
                dot.className = 'dot dot-online';
                text.innerText = 'ONLINE (Polling Active)';
                hb.innerText = data.heartbeat_age + 's ago';
                hb.style.color = '#10b981';
            } else {
                badge.className = 'status-badge status-offline';
                dot.className = 'dot dot-offline';
                text.innerText = 'OFFLINE (Bot Stopped)';
                hb.innerText = 'Expired';
                hb.style.color = '#ef4444';
            }

            pid.innerText = '#' + (data.pid || 'None');
            up.innerText = (data.total_processed || 0).toLocaleString();
            off.innerText = data.offset || '0';

            if (data.logs && data.logs.length > 0) {
                let html = '';
                data.logs.forEach((l, idx) => {
                    html += `<div class="log-entry ${idx === 0 ? 'fresh' : ''}">[${l.time}] ${l.text}</div>`;
                });
                logBox.innerHTML = html;
            }
        }

        async function controlBot(action) {
            try {
                const text = document.getElementById('statusText');
                if (action === 'start') {
                    text.innerText = 'STARTING RUNNER...';
                } else if (action === 'restart') {
                    text.innerText = 'RESTARTING RUNNER...';
                } else if (action === 'stop') {
                    text.innerText = 'STOPPING RUNNER...';
                }

                // 1. Tell supervisor to clear flags and prepare
                await fetch(`supervisor.php?action=${action}`);

                // 2. Direct browser trigger (passes Imunify challenge with browser cookies)
                if (action === 'start' || action === 'restart') {
                    fetch(`poller.php?web_init=${Date.now()}`).catch(() => {});
                }

                // 3. Promptly refresh telemetry
                setTimeout(pulse, 600);
                setTimeout(pulse, 1500);
                setTimeout(pulse, 3000);
            } catch (e) {
                console.error('Control error:', e);
            }
        }

        async function pulse() {
            const autoRevive = document.getElementById('keepAliveToggle').checked;
            try {
                const res = await fetch(`supervisor.php?action=pulse&auto=${autoRevive ? 1 : 0}`);
                const json = await res.json();
                if (json.ok && json.data) {
                    updateUI(json.data);
                    // If offline and auto-revive is active, also trigger poller.php directly from browser
                    if (!json.data.is_alive && autoRevive) {
                        fetch(`poller.php?web_init=${Date.now()}`).catch(() => {});
                    }
                }
            } catch (e) {
                console.error('Pulse error:', e);
            }
        }

        async function clearLogs() {
            try {
                await fetch('supervisor.php?action=clear_logs');
                document.getElementById('logBox').innerHTML = '<div class="log-entry">Logs cleared.</div>';
            } catch (e) {}
        }

        function copyCronUrl() {
            const copyText = document.getElementById("cronUrlInput");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            alert("24/7 Cloud Webhook URL copied to clipboard!");
        }

        // Run pulse every 6 seconds
        setInterval(pulse, 6000);
    </script>
</body>
</html>
