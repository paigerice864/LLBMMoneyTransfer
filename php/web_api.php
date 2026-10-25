<?php
// HeisDaemon Executive Web API Engine
// Powers lagoslife.hostedbyfps.com web interface and syncs results with @HeisdaemonBot

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/bot.php';

$db = getDb();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true) ?: [];
if (empty($action) && !empty($jsonBody['action'])) {
    $action = $jsonBody['action'];
}

function respond(bool $ok, array $data = [], ?string $error = null): void {
    $out = ['ok' => $ok];
    if ($error) $out['error'] = $error;
    echo json_encode(array_merge($out, $data));
    exit;
}

function notifyBotAndChannel(string $text, ?string $targetChatId = null): void {
    // Notify Master Admin
    if (defined('MASTER_ADMIN_ID') && MASTER_ADMIN_ID) {
        safeSend(MASTER_ADMIN_ID, $text);
    }
    // Also notify target user if different
    if ($targetChatId && (string)$targetChatId !== (string)MASTER_ADMIN_ID) {
        safeSend($targetChatId, $text);
    }
    // Broadcast public transfers to channel
    if (strpos($text, '[TRANSFER EXECUTED') !== false || strpos($text, '[HOUSE BEAUTIFIED') !== false || strpos($text, '[PAYROLL BATCH') !== false) {
        notifyChannel($text);
    }
}

try {
    // ────────────────────────────────────────────────────────────────────────────
    // 1. Get All Accounts & Portfolio Overview
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'get_data') {
        $usersStmt = $db->query("SELECT chat_id, tg_username, tg_first_name, ll_username, ll_user_id, ll_name, pin, is_authenticated, quilox_total_picked, quilox_last_picked, authenticated_at, updated_at FROM bot_users ORDER BY is_authenticated DESC, authenticated_at DESC");
        $rawUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

        $accounts = [];
        $totalWealth = 0;

        foreach ($rawUsers as $u) {
            $chatId = $u['chat_id'];
            $bal = null;
            if (!empty($u['is_authenticated'])) {
                $bal = getBalance($chatId);
            }
            if ($bal !== null) {
                $totalWealth += $bal;
            }

            $accounts[] = [
                'chat_id'          => $chatId,
                'tg_username'      => $u['tg_username'] ?: 'N/A',
                'tg_first_name'    => $u['tg_first_name'] ?: 'User',
                'll_username'      => $u['ll_username'],
                'll_user_id'       => $u['ll_user_id'],
                'll_name'          => $u['ll_name'] ?: $u['ll_username'],
                'has_pin'          => !empty($u['pin']),
                'is_authenticated' => (bool)$u['is_authenticated'],
                'balance'          => $bal,
                'balance_fmt'      => $bal !== null ? fmtFull($bal) : 'Session Expired',
                'balance_short'    => $bal !== null ? fmtShort($bal) : 'N/A',
                'quilox_picked'    => (int)($u['quilox_total_picked'] ?? 0),
                'quilox_last'      => $u['quilox_last_picked'] ?? 'Never',
                'authenticated_at' => $u['authenticated_at'] ?? 'N/A',
            ];
        }

        // Recent Transfers
        $txStmt = $db->query("SELECT * FROM transfers ORDER BY created_at DESC LIMIT 15");
        $recentTransfers = $txStmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Batch Jobs
        $bjStmt = $db->query("SELECT * FROM batch_jobs ORDER BY created_at DESC LIMIT 10");
        $recentBatches = $bjStmt->fetchAll(PDO::FETCH_ASSOC);

        respond(true, [
            'accounts'         => $accounts,
            'total_wealth'     => $totalWealth,
            'total_wealth_fmt' => fmtFull($totalWealth),
            'total_wealth_srt' => fmtShort($totalWealth),
            'mansion_catalog'  => getHouseTemplates(),
            'recent_transfers' => $recentTransfers,
            'recent_batches'   => $recentBatches,
            'server_time'      => date('Y-m-d H:i:s'),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 2. Refresh Single Account Balance
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'refresh_balance') {
        $chatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        if (!$chatId) respond(false, [], 'Missing chat_id');

        $bal = getBalance($chatId);
        if ($bal === null) {
            respond(false, [], 'Could not retrieve live balance. Session may have expired.');
        }

        respond(true, [
            'balance'     => $bal,
            'balance_fmt' => fmtFull($bal),
            'balance_srt' => fmtShort($bal),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 3. Login / Connect LagosLife Account
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'login') {
        $username = trim($jsonBody['username'] ?? ($_POST['username'] ?? ''));
        $password = trim($jsonBody['password'] ?? ($_POST['password'] ?? ''));
        $chatId   = trim($jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? MASTER_ADMIN_ID));

        if (!$username || !$password) {
            respond(false, [], 'Username and password are required.');
        }

        $loginRes = llLogin($username, $password);
        if (!$loginRes['ok']) {
            respond(false, [], $loginRes['error'] ?? 'Login failed. Verify credentials.');
        }

        $stmt = $db->prepare("
            INSERT INTO bot_users (chat_id, ll_username, ll_session_cookie, ll_user_id, ll_name, is_authenticated, authenticated_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                ll_username = VALUES(ll_username),
                ll_session_cookie = VALUES(ll_session_cookie),
                ll_user_id = VALUES(ll_user_id),
                ll_name = VALUES(ll_name),
                is_authenticated = 1,
                authenticated_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([
            $chatId,
            $username,
            $loginRes['cookie'],
            $loginRes['userId'],
            $loginRes['name'],
        ]);

        $bal = getBalance($chatId);

        // Notify Telegram Bot
        $alertText =
            "🔑 *[NEW ACCOUNT CONNECTED VIA WEB]*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "👤 *Account:* @" . escMd($username) . " (" . escMd($loginRes['name']) . ")\n" .
            "🆔 *Player ID:* `{$loginRes['userId']}`\n" .
            "💰 *Live Balance:* " . ($bal !== null ? "*" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "Available in portal") . "\n" .
            "📅 *Time:* " . date('Y-m-d H:i:s') . "\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "⚡ Connected through HeisDaemon Executive Suite";
        notifyBotAndChannel($alertText, $chatId);

        respond(true, [
            'username'    => $username,
            'name'        => $loginRes['name'],
            'userId'      => $loginRes['userId'],
            'balance'     => $bal,
            'balance_fmt' => $bal !== null ? fmtFull($bal) : 'N/A',
            'balance_srt' => $bal !== null ? fmtShort($bal) : 'N/A',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 4. Set / Update PIN
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'set_pin') {
        $chatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        $pin    = trim($jsonBody['pin'] ?? ($_POST['pin'] ?? ''));

        if (!$chatId) respond(false, [], 'Missing chat_id');
        if (!preg_match('/^\d{4,8}$/', $pin)) {
            respond(false, [], 'PIN must be between 4 and 8 digits.');
        }

        $db->prepare("UPDATE bot_users SET pin = ?, updated_at = NOW() WHERE chat_id = ?")->execute([$pin, $chatId]);

        $user = $db->query("SELECT ll_username FROM bot_users WHERE chat_id = '$chatId'")->fetch();
        $uName = $user['ll_username'] ?? 'Account';

        notifyBotAndChannel(
            "🔐 *[TRANSFER PIN UPDATED VIA WEB]*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "👤 *Account:* @" . escMd($uName) . "\n" .
            "✅ *Status:* Secure PIN stored and activated for future mega-transfers!\n" .
            "📅 *Time:* " . date('Y-m-d H:i:s'),
            $chatId
        );

        respond(true, ['message' => 'PIN successfully saved!']);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 5. Send Money (Single / Mega / Trillions)
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'send_money') {
        $senderChatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        $recipientRaw = trim($jsonBody['recipient'] ?? ($_POST['recipient'] ?? ''));
        $rawAmount    = trim($jsonBody['amount'] ?? ($_POST['amount'] ?? ''));
        $note         = trim($jsonBody['note'] ?? ($_POST['note'] ?? ''));
        $pinOverride  = trim($jsonBody['pin'] ?? ($_POST['pin'] ?? ''));

        if (!$senderChatId) respond(false, [], 'Sender account not selected.');
        if (!$recipientRaw) respond(false, [], 'Recipient username is required.');

        $amount = parseAmount($rawAmount);
        if (!$amount || $amount <= 0) {
            respond(false, [], 'Invalid transfer amount. Supports numbers, K, M, B, T (e.g., 5M, 10B, 1T).');
        }

        $senderUser = $db->query("SELECT * FROM bot_users WHERE chat_id = '$senderChatId' AND is_authenticated = 1")->fetch();
        if (!$senderUser) {
            respond(false, [], 'Sender account is not authenticated. Please log in.');
        }

        $pin = $pinOverride ?: ($senderUser['pin'] ?? '');
        $senderUsername = $senderUser['ll_username'];

        // Resolve Recipient
        $resolved = resolvePlayer($senderChatId, $recipientRaw);
        if (!$resolved) {
            respond(false, [], "Recipient '@$recipientRaw' was not found on LagosLife.");
        }

        $recipUsername = $resolved['username'];
        $recipPlayerId = $resolved['id'];
        $actualNote = $note ?: ('Sent via ' . BOT_NAME . ' Web');

        $chunks = chunkAmounts($amount);

        // If <= 10B (Single Chunk)
        if (count($chunks) === 1) {
            $txRes = sendMoney($senderChatId, $recipPlayerId, $recipUsername, $amount, $actualNote, $pin);
            if (!$txRes['ok']) {
                $err = $txRes['error'] ?? 'Transfer failed on LagosLife.';
                $db->prepare("INSERT INTO transfers (chat_id, sender_ll_username, recipient_username, recipient_player_id, amount, note, status, error_reason, created_at) VALUES (?, ?, ?, ?, ?, ?, 'failed', ?, NOW())")
                   ->execute([$senderChatId, $senderUsername, $recipUsername, $recipPlayerId, $amount, $actualNote, $err]);
                respond(false, [], $err);
            }

            // Success
            $db->prepare("INSERT INTO transfers (chat_id, sender_ll_username, recipient_username, recipient_player_id, amount, note, status, tx_response, created_at) VALUES (?, ?, ?, ?, ?, ?, 'success', ?, NOW())")
               ->execute([$senderChatId, $senderUsername, $recipUsername, $recipPlayerId, $amount, $actualNote, json_encode($txRes['data'] ?? [])]);

            $newBal = getBalance($senderChatId);

            // Dispatch notification to Telegram bot
            $alert =
                "💸 *[TRANSFER EXECUTED VIA WEB]*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *From:* @" . escMd($senderUsername) . "\n" .
                "🎯 *To:* @" . escMd($recipUsername) . "\n" .
                "💵 *Amount:* *" . fmtFull($amount) . "* _(" . fmtShort($amount) . ")_\n" .
                "📝 *Note:* " . escMd($actualNote) . "\n" .
                ($newBal !== null ? "💰 *New Balance:* " . fmtFull($newBal) . " _(" . fmtShort($newBal) . ")_\n" : "") .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "⚡ Executed via HeisDaemon Executive Suite";
            notifyBotAndChannel($alert, $senderChatId);

            respond(true, [
                'sender'      => $senderUsername,
                'recipient'   => $recipUsername,
                'amount'      => $amount,
                'amount_fmt'  => fmtFull($amount),
                'amount_srt'  => fmtShort($amount),
                'new_balance' => $newBal,
                'message'     => "Successfully transferred " . fmtFull($amount) . " to @$recipUsername!",
            ]);
        } else {
            // Multi-Chunk Mega/Trillion Batch
            $jobId = bin2hex(random_bytes(12));
            $totalChunks = count($chunks);

            $db->prepare("INSERT INTO batch_jobs (id, chat_id, sender_username, mode, total_chunks, total_amount, created_at, updated_at) VALUES (?, ?, ?, 'single_chunked', ?, ?, NOW(), NOW())")
               ->execute([$jobId, $senderChatId, $senderUsername, $totalChunks, $amount]);

            foreach ($chunks as $idx => $cAmt) {
                $db->prepare("INSERT INTO batch_chunks (job_id, chunk_index, recipient_username, recipient_player_id, amount, note, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                   ->execute([$jobId, $idx, $recipUsername, $recipPlayerId, $cAmt, $actualNote]);
            }

            // Spawn background worker
            startBatchWorker($jobId);

            // Notify Telegram Bot
            $alert =
                "📦 *[MEGA TRANSFER BATCH LAUNCHED VIA WEB]*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *From:* @" . escMd($senderUsername) . "\n" .
                "🎯 *To:* @" . escMd($recipUsername) . "\n" .
                "💵 *Total Amount:* *" . fmtFull($amount) . "* _(" . fmtShort($amount) . ")_\n" .
                "📦 *Total Chunks:* $totalChunks × " . fmtShort(MAX_CHUNK) . " (32s cooldown timing)\n" .
                "🆔 *Job ID:* `$jobId`\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "⚡ Background worker initialized and running natively!";
            notifyBotAndChannel($alert, $senderChatId);

            respond(true, [
                'batch'        => true,
                'job_id'       => $jobId,
                'total_chunks' => $totalChunks,
                'total_amount' => $amount,
                'message'      => "Mega transfer of " . fmtShort($amount) . " queued across $totalChunks chunks with 32s cooldown!",
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 6. Multi-Recipient Payroll Blitz
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'batch_payroll') {
        $senderChatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        $rawList      = $jsonBody['list'] ?? ($_POST['list'] ?? '');

        if (!$senderChatId) respond(false, [], 'Sender account not selected.');
        if (!$rawList) respond(false, [], 'Recipient list cannot be empty.');

        $senderUser = $db->query("SELECT * FROM bot_users WHERE chat_id = '$senderChatId' AND is_authenticated = 1")->fetch();
        if (!$senderUser) respond(false, [], 'Sender account is not authenticated.');

        $lines = preg_split('/[\r\n;]+/', $rawList);
        $parsed = [];
        foreach ($lines as $line) {
            $line = trim(str_replace(',', ' ', $line));
            if (empty($line)) continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                $uName = ltrim($parts[0], '@');
                $amt = parseAmount($parts[1]);
                if ($uName && $amt) {
                    $parsed[] = ['username' => $uName, 'amount' => $amt];
                }
            }
        }

        if (empty($parsed)) {
            respond(false, [], 'Could not parse any entries. Format: @username amount on each line.');
        }

        $resolved = [];
        $failedResolve = [];
        $chunks = [];
        $totalAmount = 0;

        foreach ($parsed as $r) {
            $player = resolvePlayer($senderChatId, $r['username']);
            if ($player) {
                $rChunks = chunkAmounts($r['amount']);
                foreach ($rChunks as $cAmt) {
                    $chunks[] = [
                        'username' => $player['username'],
                        'playerId' => $player['id'],
                        'amount'   => $cAmt,
                    ];
                    $totalAmount += $cAmt;
                }
                $resolved[] = $player['username'];
            } else {
                $failedResolve[] = "@" . $r['username'];
            }
        }

        if (empty($chunks)) {
            respond(false, [], 'None of the provided usernames could be resolved on LagosLife.');
        }

        $jobId = bin2hex(random_bytes(12));
        $totalChunks = count($chunks);

        $db->prepare("INSERT INTO batch_jobs (id, chat_id, sender_username, mode, total_chunks, total_amount, created_at, updated_at) VALUES (?, ?, ?, 'multi', ?, ?, NOW(), NOW())")
           ->execute([$jobId, $senderChatId, $senderUser['ll_username'], $totalChunks, $totalAmount]);

        foreach ($chunks as $idx => $c) {
            $db->prepare("INSERT INTO batch_chunks (job_id, chunk_index, recipient_username, recipient_player_id, amount, note, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
               ->execute([$jobId, $idx, $c['username'], $c['playerId'], $c['amount'], ('Sent via ' . BOT_NAME . ' Web')]);
        }

        startBatchWorker($jobId);

        // Notify Telegram Bot
        $alert =
            "👥 *[PAYROLL BATCH STARTED VIA WEB]*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "👤 *Sender:* @" . escMd($senderUser['ll_username']) . "\n" .
            "👥 *Recipients:* " . count($resolved) . " players\n" .
            "📦 *Total Chunks:* $totalChunks\n" .
            "💵 *Total Funds:* *" . fmtFull($totalAmount) . "* _(" . fmtShort($totalAmount) . ")_\n" .
            (!empty($failedResolve) ? "⚠️ *Skipped Unresolved:* " . implode(', ', $failedResolve) . "\n" : "") .
            "🆔 *Job ID:* `$jobId`\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "⚡ Background worker is dispersing payments with 32s cooldown timing!";
        notifyBotAndChannel($alert, $senderChatId);

        respond(true, [
            'job_id'          => $jobId,
            'resolved_count'  => count($resolved),
            'total_chunks'    => $totalChunks,
            'total_amount'    => $totalAmount,
            'total_amount_fmt'=> fmtFull($totalAmount),
            'total_amount_srt'=> fmtShort($totalAmount),
            'failed'          => $failedResolve,
            'message'         => "Dispersing " . fmtShort($totalAmount) . " across $totalChunks chunks!",
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 7. Luxury House Beautification (Catalog Mansion)
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'beautify_template') {
        $chatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        $tplKey = $jsonBody['template'] ?? ($_POST['template'] ?? '');

        if (!$chatId) respond(false, [], 'Missing chat_id');
        $tpls = getHouseTemplates();
        if (!isset($tpls[$tplKey])) {
            respond(false, [], 'Invalid mansion template selected.');
        }

        $design = loadHouseTemplate($tplKey);
        if (!$design) respond(false, [], 'Template design file could not be loaded.');

        $cost = $tpls[$tplKey]['cost'] ?? 0;
        $res = applyHouseDesign($chatId, $design, $cost);

        if (!$res['ok']) {
            respond(false, [], $res['error'] ?? 'Could not install mansion design.');
        }

        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId'")->fetch();
        $owner = $user['ll_username'] ?? 'Owner';
        $mansionName = $tpls[$tplKey]['name'];

        // Telegram Bot Alert
        $alert =
            "🏰 *[HOUSE BEAUTIFIED VIA WEB]*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "• *Owner:* @" . escMd($owner) . "\n" .
            "• *Design:* *" . escMd($mansionName) . "*\n" .
            "• *Installed:* *{$res['itemsCount']} luxury elements*\n" .
            "• *New Balance:* *" . fmtFull($res['newBalance']) . "*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🤖 *HeisDaemon Executive Suite*";
        notifyBotAndChannel($alert, $chatId);

        respond(true, [
            'mansion'     => $mansionName,
            'items_count' => $res['itemsCount'],
            'new_balance' => $res['newBalance'],
            'message'     => "Successfully installed $mansionName ({$res['itemsCount']} items) into your residence!",
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 8. Clone Player House by Username
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'clone_player_house') {
        $chatId     = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        $targetUser = trim($jsonBody['target_user'] ?? ($_POST['target_user'] ?? ''));

        if (!$chatId) respond(false, [], 'Missing chat_id');
        if (!$targetUser) respond(false, [], 'Target player username required.');

        $insp = inspectPlayerHouse($chatId, $targetUser);
        if (!$insp['ok']) {
            if (!empty($insp['knocked'])) {
                respond(false, [
                    'knocked' => true,
                    'target'  => $targetUser,
                    'home_id' => $insp['homeId'] ?? '',
                ], "Knock delivered to @$targetUser's door! Ask them to tap 'Let In' on their screen, then click Re-check.");
            }
            respond(false, [], $insp['error'] ?? 'Could not access player house coordinates.');
        }

        // Apply Cloned Design
        $res = applyHouseDesign($chatId, $insp, 0);
        if (!$res['ok']) {
            respond(false, [], $res['error'] ?? 'Failed to apply cloned layout.');
        }

        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId'")->fetch();
        $owner = $user['ll_username'] ?? 'Owner';

        $alert =
            "🏰 *[HOUSE CLONED VIA WEB]*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "• *Owner:* @" . escMd($owner) . "\n" .
            "• *Cloned From:* @" . escMd($targetUser) . "\n" .
            "• *Property Type:* 🏰 *" . escMd($insp['home']) . "*\n" .
            "• *Installed:* *{$res['itemsCount']} items*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🤖 *HeisDaemon Executive Suite*";
        notifyBotAndChannel($alert, $chatId);

        respond(true, [
            'cloned_from' => $targetUser,
            'items_count' => $res['itemsCount'],
            'home'        => $insp['home'],
            'message'     => "Successfully cloned @$targetUser's layout ({$res['itemsCount']} items)!",
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 9. Quilox Nightclub Controls
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'quilox_sweep') {
        $chatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        if (!$chatId) respond(false, [], 'Missing chat_id');

        $res = sweepQuiloxFloor($chatId);
        if (!$res['ok']) {
            respond(false, [], $res['error'] ?? 'Could not sweep Quilox floor.');
        }

        $grabbed = $res['grabbed'] ?? 0;
        $user = $db->query("SELECT ll_username FROM bot_users WHERE chat_id = '$chatId'")->fetch();
        $uName = $user['ll_username'] ?? 'User';

        if ($grabbed > 0) {
            $alert =
                "🪩 *[QUILOX FLOOR SWEEP VIA WEB]*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *Account:* @" . escMd($uName) . "\n" .
                "💰 *Cash Scooped:* *" . fmtFull($grabbed) . "* _(" . fmtShort($grabbed) . ")_\n" .
                "🍸 *Venue:* Quilox Nightclub (`clubEko`)\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "✅ Deposited directly into your character wallet!";
            notifyBotAndChannel($alert, $chatId);
        }

        respond(true, [
            'grabbed'     => $grabbed,
            'grabbed_fmt' => fmtFull($grabbed),
            'grabbed_srt' => fmtShort($grabbed),
            'sprayers'    => $res['sprayers'] ?? [],
            'message'     => $grabbed > 0 ? "🎉 Scooped " . fmtFull($grabbed) . " off the dance floor!" : "Floor swept! No sprayed cash was active right now.",
        ]);
    }

    if ($action === 'quilox_teleport') {
        $chatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        if (!$chatId) respond(false, [], 'Missing chat_id');

        $res = teleportToQuilox($chatId);
        respond(true, [
            'message' => $res['message'] ?? 'Character teleported to Quilox Nightclub!',
        ]);
    }

    if ($action === 'quilox_claim') {
        $chatId = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        if (!$chatId) respond(false, [], 'Missing chat_id');

        requestAsUser($chatId, '/api/claim', 'POST', []);
        $bal = getBalance($chatId);

        respond(true, [
            'balance'     => $bal,
            'balance_fmt' => $bal !== null ? fmtFull($bal) : 'N/A',
            'message'     => 'All pending banknotes claimed into your balance!',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // 10. Support LLBM Movement
    // ────────────────────────────────────────────────────────────────────────────
    if ($action === 'support_llbm') {
        $chatId    = $jsonBody['chat_id'] ?? ($_POST['chat_id'] ?? '');
        $rawAmount = trim($jsonBody['amount'] ?? ($_POST['amount'] ?? ''));

        if (!$chatId) respond(false, [], 'Missing chat_id');
        $amount = parseAmount($rawAmount);
        if (!$amount || $amount <= 0) {
            respond(false, [], 'Invalid amount. Try 10M, 100M, 1B, 10B, 1T.');
        }

        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        if (!$user) respond(false, [], 'Account is not authenticated.');

        $pin = $user['pin'] ?? '';
        $txRes = sendMoney($chatId, LLBM_MOVEMENT_PLAYER_ID, LLBM_MOVEMENT_USERNAME, $amount, 'Support LLBM Movement 💎', $pin);

        if (!$txRes['ok']) {
            respond(false, [], $txRes['error'] ?? 'Transfer failed.');
        }

        $newBal = getBalance($chatId);

        $alert =
            "💎 *[SUPPORT LLBM CONTRIBUTION VIA WEB]*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "👤 *Contributor:* @" . escMd($user['ll_username']) . "\n" .
            "🎯 *Beneficiary:* *" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")\n" .
            "💵 *Amount:* *" . fmtFull($amount) . "* _(" . fmtShort($amount) . ")_\n" .
            ($newBal !== null ? "💰 *Remaining Balance:* " . fmtFull($newBal) . "\n" : "") .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "💎 Thank you for supporting the movement!";
        notifyBotAndChannel($alert, $chatId);

        respond(true, [
            'amount_fmt'  => fmtFull($amount),
            'new_balance' => $newBal,
            'message'     => "Successfully contributed " . fmtFull($amount) . " to LLBM Movement 💎!",
        ]);
    }

    respond(false, [], "Unknown action: '$action'");
} catch (Throwable $e) {
    respond(false, [], "Server Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}
