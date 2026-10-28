<?php
ignore_user_abort(true);
set_time_limit(0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/bot.php';

$jobId = $argv[1] ?? ($_GET['job_id'] ?? null);
if (!$jobId) {
    exit("Usage: php worker.php <job_id>\n");
}

$db = getDb();

$jobStmt = $db->prepare("SELECT * FROM batch_jobs WHERE id = ?");
$jobStmt->execute([$jobId]);
$job = $jobStmt->fetch();

if (!$job || in_array($job['status'], ['completed', 'cancelled'])) {
    exit("Job not active or already finished.\n");
}

$chatId = $job['chat_id'];
$progressMsgId = (int)$job['progress_msg_id'];

// Get user PIN
$userStmt = $db->prepare("SELECT pin FROM bot_users WHERE (account_id = ? OR chat_id = ? OR ll_username = ?) LIMIT 1");
$userStmt->execute([$chatId, $chatId, $chatId]);
$userRow = $userStmt->fetch();
$pin = $userRow['pin'] ?? null;

$chunksStmt = $db->prepare("SELECT * FROM batch_chunks WHERE job_id = ? ORDER BY chunk_index ASC");
$chunksStmt->execute([$jobId]);
$chunks = $chunksStmt->fetchAll();

$totalChunks = count($chunks);
$doneChunks = (int)$job['completed_chunks'];
$successChunks = 0;
$failedChunks = (int)$job['failed_chunks'];
$sentAmount = (int)$job['sent_amount'];
$failedList = [];

$cancelKb = [
    'inline_keyboard' => [
        [['text' => '🚫 Cancel Batch', 'callback_data' => 'cancel_batch']]
    ]
];

for ($i = $doneChunks; $i < $totalChunks; $i++) {
    // Check if cancelled
    $chkStmt = $db->prepare("SELECT status FROM batch_jobs WHERE id = ?");
    $chkStmt->execute([$jobId]);
    $curStatus = $chkStmt->fetchColumn();
    if ($curStatus === 'cancelled') {
        safeEdit($chatId, $progressMsgId, "🚫 *Batch cancelled by user.*");
        exit("Cancelled.\n");
    }

    $c = $chunks[$i];
    $recipientUsername = $c['recipient_username'];
    $recipientPlayerId = $c['recipient_player_id'];
    $chunkAmount = (int)$c['amount'];
    $note = $c['note'] ?: ('Sent via ' . BOT_NAME);

    // Show sending status
    $bar = progressBar($i, $totalChunks);
    $pct = $totalChunks > 0 ? (int)round(($i / $totalChunks) * 100) : 0;
    $progressText =
        "💸 *Batch Transfer in Progress*\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "👤 *From:* @" . escMd($job['sender_username']) . "\n" .
        "📦 *Total Chunks:* $totalChunks · Max " . fmtShort(MAX_CHUNK) . " each\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "$bar  $pct%  ($i/$totalChunks)\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "✅ *Sent so far:* " . fmtShort($sentAmount) . "\n" .
        "📊 *Remaining:* " . fmtShort(max(0, (int)$job['total_amount'] - $sentAmount)) . "\n" .
        "✔️ OK: $successChunks   ❌ Failed: $failedChunks\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "🔄 *Chunk " . ($i + 1) . "/$totalChunks* → @" . escMd($recipientUsername) . "  " . fmtShort($chunkAmount);

    safeEdit($chatId, $progressMsgId, $progressText, $cancelKb);

    $res = sendMoney($chatId, $recipientPlayerId, $chunkAmount, $note, $pin);

    $status = $res['ok'] ? 'success' : 'failed';
    $errReason = $res['ok'] ? null : ($res['error'] ?? 'Network error');
    $txId = $res['ok'] ? ($res['txId'] ?? '') : null;

    if ($res['ok']) {
        $successChunks++;
        $sentAmount += $chunkAmount;
    } else {
        $failedChunks++;
        $failedList[] = [
            'username' => $recipientUsername,
            'amount'   => $chunkAmount,
            'reason'   => $errReason,
        ];
    }

    // Update DB
    $db->prepare("UPDATE batch_chunks SET status = ?, error_reason = ?, processed_at = NOW() WHERE id = ?")
       ->execute([$status, $errReason, $c['id']]);

    $db->prepare("INSERT INTO transfers (chat_id, sender_ll_username, recipient_username, recipient_player_id, amount, note, status, tx_response, error_reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
       ->execute([$chatId, $job['sender_username'], $recipientUsername, $recipientPlayerId, $chunkAmount, $note, $status, $txId, $errReason]);

    $db->prepare("UPDATE batch_jobs SET completed_chunks = ?, failed_chunks = ?, sent_amount = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$i + 1, $failedChunks, $sentAmount, $jobId]);

    // 32-second cooldown countdown
    if ($i < $totalChunks - 1) {
        $remaining = COOLDOWN_S;
        while ($remaining > 0) {
            $chkStmt->execute([$jobId]);
            if ($chkStmt->fetchColumn() === 'cancelled') {
                safeEdit($chatId, $progressMsgId, "🚫 *Batch cancelled during cooldown.*");
                exit("Cancelled.\n");
            }

            $cooldownText =
                "💸 *Batch Transfer in Progress*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *From:* @" . escMd($job['sender_username']) . "\n" .
                "📦 *Total Chunks:* $totalChunks · Max " . fmtShort(MAX_CHUNK) . " each\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "$bar  $pct%  (" . ($i + 1) . "/$totalChunks)\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "✅ *Sent so far:* " . fmtShort($sentAmount) . "\n" .
                "📊 *Remaining:* " . fmtShort(max(0, (int)$job['total_amount'] - $sentAmount)) . "\n" .
                "✔️ OK: $successChunks   ❌ Failed: $failedChunks\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "⏳ *Cooldown:* {$remaining}s until next chunk…";

            safeEdit($chatId, $progressMsgId, $cooldownText, $cancelKb);
            $step = min(5, $remaining);
            sleep($step);
            $remaining -= $step;
        }
    }
}

// Mark completed
$db->prepare("UPDATE batch_jobs SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$jobId]);

$isLLBM = strtolower($chunks[0]['recipient_username']) === strtolower(LLBM_MOVEMENT_USERNAME);

$failedSection = '';
if (!empty($failedList)) {
    $failedSection = "\n\n❌ *Failed Transfers (" . count($failedList) . "):*\n";
    foreach ($failedList as $idx => $f) {
        $failedSection .= ($idx + 1) . ". @" . escMd($f['username']) . " — *" . fmtShort($f['amount']) . "*\n   ⚠️ _Reason: " . escMd($f['reason']) . "_\n";
    }
}

$summary =
    ($failedChunks === 0 ? "✅" : "⚠️") . " *Batch Complete!*\n" .
    "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
    "👤 *From:* @" . escMd($job['sender_username']) . "\n" .
    ($isLLBM ? "🎯 *Beneficiary:* " . LLBM_MOVEMENT_NAME . " (@" . LLBM_MOVEMENT_USERNAME . ")\n" : "") .
    "📦 *Chunks:* $totalChunks\n" .
    "💵 *Total Sent:* " . fmtFull($sentAmount) . "\n" .
    "✔️ OK: $successChunks   ❌ Failed: $failedChunks" .
    $failedSection;

safeSend($chatId, $summary, mainMenu(true));

// Channel notification
$channelFailedSection = '';
if (!empty($failedList)) {
    $channelFailedSection = "\n⚠️ *Failed:* " . implode(', ', array_map(function($f) {
        return "@" . escMd($f['username']) . " (" . fmtShort($f['amount']) . ")";
    }, $failedList));
}

if ($isLLBM) {
    notifyChannel(
        "💎 *LLBM MOVEMENT CONTRIBUTION!*\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "👤 *Supporter:* @" . escMd($job['sender_username']) . "\n" .
        "🎯 *Beneficiary:* @" . LLBM_MOVEMENT_USERNAME . " (" . LLBM_MOVEMENT_NAME . ")\n" .
        "💵 *Amount:* " . fmtFull($sentAmount) . " _(" . fmtShort($sentAmount) . ")_\n" .
        "📦 *Chunks:* $totalChunks (Delivered: $successChunks)\n" .
        "❤️ *Thank you for empowering the Movement!* 🚀\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "🤖 *" . BOT_NAME . "*"
    );
} else {
    notifyChannel(
        "📦 *BATCH TRANSFER COMPLETED*\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "👤 *Sender:* @" . escMd($job['sender_username']) . "\n" .
        "📦 *Total Chunks:* $totalChunks\n" .
        "💵 *Total Sent:* " . fmtFull($sentAmount) . " _(" . fmtShort($sentAmount) . ")_\n" .
        "✔️ *Successful:* $successChunks   ❌ *Failed:* $failedChunks" .
        $channelFailedSection . "\n" .
        "⚡ *Status:* " . ($failedChunks === 0 ? "All Delivered ✅" : "Completed with issues ⚠️") . "\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "🤖 *" . BOT_NAME . "*"
    );
}
