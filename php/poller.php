<?php
// HeisDaemon Autonomous Server Poller Engine
// Runs natively on lagoslife.hostedbyfps.com

define('IN_POLLER', true);

ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/webhook.php';

$stateFile  = __DIR__ . '/poller_state.json';
$offsetFile = __DIR__ . '/poller_offset.dat';
$stopFile   = __DIR__ . '/poller.stop';
$historyLog = __DIR__ . '/poller_history.log';

function readState(): array {
    global $stateFile;
    if (file_exists($stateFile)) {
        $raw = @file_get_contents($stateFile);
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return [
        'status'          => 'offline',
        'pid'             => 0,
        'started_at'      => 0,
        'heartbeat'       => 0,
        'offset'          => 0,
        'total_processed' => 0,
        'last_action'     => 'None',
        'logs'            => [],
    ];
}

function writeState(array $state): void {
    global $stateFile;
    $tmp = $stateFile . '.tmp.' . uniqid();
    @file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT));
    @rename($tmp, $stateFile);
}

function appendHistoryLog(string $entry): void {
    global $historyLog;
    $line = "[" . date('Y-m-d H:i:s') . "] " . $entry . PHP_EOL;
    @file_put_contents($historyLog, $line, FILE_APPEND);
}

// ────────────────────────────────────────────────────────────────────────────
// Single-Instance Lock & Conflict Prevention
// ────────────────────────────────────────────────────────────────────────────
$curState = readState();

// If another poller is actively polling right now (heartbeat < 25s)
if ($curState['status'] === 'online' && (time() - ($curState['heartbeat'] ?? 0)) < 25) {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => true,
            'status'  => 'running',
            'pid'     => $curState['pid'] ?? 0,
            'message' => 'Poller is already running and healthy.',
        ]);
    }
    exit;
}

// Clear any stale stop flag
if (file_exists($stopFile)) {
    @unlink($stopFile);
}

// Read saved offset
$offset = 0;
if (file_exists($offsetFile)) {
    $offset = (int)trim(@file_get_contents($offsetFile));
}

$myPid = getmypid();
$startTime = time();

$curState['status']      = 'online';
$curState['pid']         = $myPid;
$curState['started_at']  = $startTime;
$curState['heartbeat']   = time();
$curState['offset']      = $offset;
$curState['last_action'] = "Poller initialized (PID $myPid)";
writeState($curState);
appendHistoryLog("⚡ HeisDaemon Poller Engine Started (PID: $myPid, Offset: $offset)");

// Disconnect HTTP client if triggered via browser so HTTP connection doesn't hang
if (php_sapi_name() !== 'cli') {
    if (ob_get_level() > 0) ob_end_clean();
    header("Connection: close");
    header("Content-Type: application/json");
    $respJson = json_encode([
        'ok'      => true,
        'status'  => 'started',
        'pid'     => $myPid,
        'message' => 'Poller daemon launched successfully in background.',
    ]);
    header("Content-Length: " . strlen($respJson));
    echo $respJson;
    @ob_flush();
    flush();
}

// ────────────────────────────────────────────────────────────────────────────
// Polling Loop (Runs in clean 180-second cycles)
// ────────────────────────────────────────────────────────────────────────────
$maxDuration = 180; // 3 minutes per cycle for optimal memory management
$cycles = 0;
$consecutiveErrors = 0;

while ((time() - $startTime) < $maxDuration) {
    $cycles++;

    // Check for user-requested stop
    if (file_exists($stopFile)) {
        @unlink($stopFile);
        $curState = readState();
        $curState['status']      = 'stopped';
        $curState['last_action'] = 'Poller gracefully stopped by user';
        $curState['heartbeat']   = time();
        writeState($curState);
        appendHistoryLog("🛑 Poller stopped via user request.");
        exit;
    }

    // Update heartbeat
    $curState['heartbeat'] = time();
    $curState['offset']    = $offset;
    writeState($curState);

    // Check for server-side task execution
    $taskFile = __DIR__ . '/exec_task.php';
    if (file_exists($taskFile)) {
        try {
            include $taskFile;
        } catch (Throwable $te) {
            @file_put_contents(__DIR__ . '/exec_task.log', "[" . date('Y-m-d H:i:s') . "] Task Error: " . $te->getMessage() . PHP_EOL, FILE_APPEND);
        }
        @unlink($taskFile);
    }

    // Outbound long-poll to Telegram getUpdates
    $pollUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/getUpdates?timeout=10";
    if ($offset > 0) {
        $pollUrl .= "&offset=" . $offset;
    }

    $ch = curl_init($pollUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "HeisDaemon-Engine/2.0");

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 409 && strpos($raw, 'deleteWebhook') !== false) {
        tgCall('deleteWebhook');
        appendHistoryLog("ℹ️ Webhook cleared on Telegram to enable autonomous polling.");
        sleep(1);
        continue;
    }

    if ($raw === false || $httpCode !== 200) {
        $consecutiveErrors++;
        $errMsg = "HTTP $httpCode " . ($curlErr ?: substr($raw ?: '', 0, 100));
        appendHistoryLog("⚠️ Polling warning ($consecutiveErrors): $errMsg");
        sleep(min(5, $consecutiveErrors));
        continue;
    }

    $consecutiveErrors = 0;
    $res = json_decode($raw, true);

    if (!empty($res['ok']) && !empty($res['result'])) {
        foreach ($res['result'] as $update) {
            $upId = (int)$update['update_id'];
            $offset = $upId + 1;
            @file_put_contents($offsetFile, (string)$offset);

            // Extract summary for log
            $userInfo = 'unknown';
            $textInfo = '[event]';
            if (!empty($update['message'])) {
                $m = $update['message'];
                $u = $m['from'] ?? [];
                $userInfo = '@' . ($u['username'] ?? ($u['first_name'] ?? $u['id']));
                $textInfo = $m['text'] ?? '[media/action]';
            } elseif (!empty($update['callback_query'])) {
                $cb = $update['callback_query'];
                $u = $cb['from'] ?? [];
                $userInfo = '@' . ($u['username'] ?? ($u['first_name'] ?? $u['id']));
                $textInfo = 'cb:' . ($cb['data'] ?? '');
            }

            $t0 = microtime(true);

            // Execute update in-memory
            handleTelegramUpdate($update);

            $durationMs = (int)round((microtime(true) - $t0) * 1000);
            $actionDesc = "Up #$upId from $userInfo: \"$textInfo\" [{$durationMs}ms]";

            // Update state
            $curState = readState();
            $curState['offset'] = $offset;
            $curState['total_processed'] = ($curState['total_processed'] ?? 0) + 1;
            $curState['last_action'] = $actionDesc;
            $curState['heartbeat'] = time();

            // Keep last 25 logs in state
            if (!isset($curState['logs']) || !is_array($curState['logs'])) {
                $curState['logs'] = [];
            }
            array_unshift($curState['logs'], [
                'time' => date('H:i:s'),
                'text' => $actionDesc,
            ]);
            $curState['logs'] = array_slice($curState['logs'], 0, 25);

            writeState($curState);
            appendHistoryLog("✅ $actionDesc");
        }
    }

    // Brief sleep to yield CPU if no updates
    usleep(200000); // 0.2s
}

// ────────────────────────────────────────────────────────────────────────────
// End of Cycle: Auto-Spawn Next Cycle for Seamless 24/7 Continuity
// ────────────────────────────────────────────────────────────────────────────
if (!file_exists($stopFile)) {
    // Non-blocking trigger to reincarnate next cycle
    $reincarnateUrl = "https://lagoslife.hostedbyfps.com/poller.php?reincarnate=" . time();
    $ch = curl_init($reincarnateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    @curl_exec($ch);
    @curl_close($ch);
    appendHistoryLog("🔄 Poller cycle completed. Spawned next cycle.");
}
