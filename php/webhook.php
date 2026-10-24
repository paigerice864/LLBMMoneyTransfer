<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/bot.php';

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    echo "OK - LLBM Money Bot Webhook Ready";
    exit;
}

$db = getDb();

// ────────────────────────────────────────────────────────────────────────────
// Subscription Prompt
// ────────────────────────────────────────────────────────────────────────────
function getSubPrompt(?string $customNote = null): array {
    $channelHandle = ltrim(NOTIFY_CHANNEL, '@');
    $link = "https://t.me/$channelHandle";
    $text =
        "📢 *Channel Subscription Required*\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
        ($customNote ? "$customNote\n\n" : "") .
        "To use *LLBM Money Bot*, you must subscribe to our official notification channel:\n👉 *" . NOTIFY_CHANNEL . "*\n\n" .
        "🔔 *Why?*\n" .
        "All live transfers, batch alerts, and activity across the network are publicly broadcast there in real-time!\n\n" .
        "👇 *Quick Steps:*\n" .
        "1. Click *📢 Join Channel* below\n" .
        "2. Click *✅ Verify Subscription* to unlock the bot";

    $kb = [
        'inline_keyboard' => [
            [['text' => "📢 Join " . NOTIFY_CHANNEL, 'url' => $link]],
            [['text' => "✅ Verify Subscription", 'callback_data' => 'check_sub']],
        ]
    ];
    return ['text' => $text, 'kb' => $kb];
}

// ────────────────────────────────────────────────────────────────────────────
// Menus: House, Quilox, and VIP Whale Radar
// ────────────────────────────────────────────────────────────────────────────
function renderHouseMenu(string $chatId, ?int $msgId = null): void {
    $text =
        "🏠 *LAGOSLIFE HOUSE BEAUTIFICATION & CLONING*\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
        "Transform your residence into a high-end luxury masterpiece!\n\n" .
        "🌟 *Verified Master Catalog:*\n" .
        "• 🏰 *@wedekaov* — Banana Island Mega Mansion (153 Items)\n" .
        "• 🏝️ *@phinnybabe* — Lekki Water Palace (153 Items)\n" .
        "• 💎 *@kimoflele* — Victoria Island Penthouse (65 Items)\n" .
        "• 🌟 *@ohayo* — Ikoyi Modern Executive Suite (58 Items)\n\n" .
        "✨ *Features:*\n" .
        "✅ Clean slate installation (removes clutter & replaces with pristine layout)\n" .
        "✅ Fully sanitized coordinates with protected spawn & stairways\n" .
        "✅ 1-Click In-Game Scanner bookmarklet support\n" .
        "✅ Clone any user's house layout by username\n\n" .
        "👇 *Choose a luxury design or clone a player's house:*";

    $kb = [
        'inline_keyboard' => [
            [['text' => '🏰 Clone @wedekaov Mansion (153 Items)', 'callback_data' => 'house_clone_tpl_wedekaov']],
            [['text' => '🏝️ Clone @phinnybabe Palace (153 Items)', 'callback_data' => 'house_clone_tpl_phinnybabe']],
            [['text' => '💎 Clone @kimoflele Penthouse (65 Items)', 'callback_data' => 'house_clone_tpl_kimoflele']],
            [['text' => '🌟 Clone @ohayo Suite (58 Items)',        'callback_data' => 'house_clone_tpl_ohayo']],
            [
                ['text' => '✍️ Clone by Username', 'callback_data' => 'house_custom'],
                ['text' => '📸 1-Click Scanner',    'callback_data' => 'house_bookmarklet'],
            ],
        ]
    ];

    if ($msgId) {
        safeEdit($chatId, $msgId, $text, $kb);
    } else {
        safeSend($chatId, $text, $kb);
    }
}

function renderQuiloxMenu(string $chatId, ?int $msgId = null): void {
    global $db;
    $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId'")->fetch();
    $totalPicked = (int)($user['quilox_total_picked'] ?? 0);
    $lastPicked = !empty($user['quilox_last_picked']) ? date('H:i d M', strtotime($user['quilox_last_picked'])) : 'None yet';

    $text =
        "🪩 *QUILOX NIGHTCLUB FLOOR CASH PICKER* 🍸\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
        "• *Venue:* 🍸 *Quilox Nightclub* (`clubEko`)\n" .
        "• *Status:* 🟢 *Active Floor Sweeper*\n" .
        "• *Total Cash Scooped:* *" . fmtFull($totalPicked) . "* _(" . fmtShort($totalPicked) . ")_\n" .
        "• *Last Grabbed:* $lastPicked\n\n" .
        "⚡ *How it works:*\n" .
        "When wealthy VIPs spray cash on the dance floor at Quilox, the bot automatically scoops the banknotes into your character's wallet!\n\n" .
        "👇 *Quick Controls:*";

    $kb = [
        'inline_keyboard' => [
            [['text' => '⚡ Sweep Dance Floor Now', 'callback_data' => 'quilox_sweep']],
            [
                ['text' => '📍 Teleport to Quilox', 'callback_data' => 'quilox_teleport'],
                ['text' => '💰 Claim Cash',          'callback_data' => 'quilox_claim'],
            ],
            [['text' => '🔄 Refresh Status',       'callback_data' => 'quilox_menu']],
        ]
    ];

    if ($msgId) {
        safeEdit($chatId, $msgId, $text, $kb);
    } else {
        safeSend($chatId, $text, $kb);
    }
}

function renderVipWhalesMenu(string $chatId, ?int $msgId = null): void {
    $whales = scanVipWhales($chatId);

    $lines = [];
    foreach (array_slice($whales, 0, 8) as $idx => $w) {
        $medal = $idx === 0 ? '🥇' : ($idx === 1 ? '🥈' : ($idx === 2 ? '🥉' : '💎'));
        $lines[] = "$medal *@" . escMd($w['username']) . "* — _{$w['status']}_\n   📍 {$w['location']}";
    }

    $text =
        "👑 *LAGOSLIFE VIP WHALE RADAR* 💎\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
        "Top Wealthiest Landlords & Mega-Spenders discovered in luxury districts (Banana Island, Ikoyi, Maitama, Quilox VIP):\n\n" .
        implode("\n\n", $lines) . "\n\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
        "_You can inspect and clone their houses, send funds, or track their activities!_";

    $kb = [
        'inline_keyboard' => [
            [['text' => '🔄 Refresh Whales', 'callback_data' => 'vip_whales']],
            [['text' => '🏠 Clone a Mansion', 'callback_data' => 'house_menu']],
        ]
    ];

    if ($msgId) {
        safeEdit($chatId, $msgId, $text, $kb);
    } else {
        safeSend($chatId, $text, $kb);
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Spawn Background Worker
// ────────────────────────────────────────────────────────────────────────────
function startBatchWorker(string $jobId): void {
    $script = __DIR__ . '/worker.php';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        pclose(popen("start /B php \"$script\" $jobId", "r"));
    } else {
        exec("php \"$script\" $jobId > /dev/null 2>&1 &");
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Handle Callback Queries
// ────────────────────────────────────────────────────────────────────────────
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $chatId = (string)$cb['message']['chat']['id'];
    $userId = (string)$cb['from']['id'];
    $data = $cb['data'];
    $msgId = (int)$cb['message']['message_id'];

    tgCall('answerCallbackQuery', ['callback_query_id' => $cb['id']]);

    if ($data === 'check_sub') {
        if (isSubscribed($userId)) {
            wipeMessage($chatId, $msgId);
            safeSend($chatId, "🎉 *Channel Verified!*\n\nThank you for joining " . NOTIFY_CHANNEL . ". The bot is unlocked for your account.");

            $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
            if ($user) {
                $bal = getBalance($chatId);
                $balLine = $bal !== null ? "\n💰 Balance: *" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "";
                safeSend($chatId, "👤 Logged in as *@{$user['ll_username']}*$balLine\n\nUse the menu below to send money:", mainMenu(true));
            } else {
                safeSend($chatId, "👇 Tap *Login* to connect your LagosLife account and start sending money:", mainMenu(false));
            }
        } else {
            $prompt = getSubPrompt("❌ *We could not find your subscription in the channel.*");
            safeSend($chatId, $prompt['text'], $prompt['kb']);
        }
        exit;
    }

    // Check subscription gate for all other actions
    if (!isSubscribed($userId)) {
        $prompt = getSubPrompt();
        safeSend($chatId, $prompt['text'], $prompt['kb']);
        exit;
    }

    if ($data === 'setup_pin') {
        $db->prepare("UPDATE pending_transfers SET step = 'enter_pin' WHERE chat_id = ?")->execute([$chatId]);
        safeEdit($chatId, $msgId, "🔐 *Transfer PIN Setup*\n\nEnter your *transfer PIN*:\n\n_It will be deleted from chat immediately and stored securely for transfers._");
        exit;
    }

    if ($data === 'skip_pin') {
        safeEdit($chatId, $msgId, "✅ No PIN set. You can add one anytime with *🔐 Set/Update PIN* or /setpin.");
        safeSend($chatId, "What would you like to do?", mainMenu(true));
        exit;
    }

    if ($data === 'cancel_batch') {
        $db->prepare("UPDATE batch_jobs SET status = 'cancelled' WHERE chat_id = ? AND status = 'pending'")->execute([$chatId]);
        safeEdit($chatId, $msgId, "🚫 Batch cancel requested.");
        exit;
    }

    if ($data === 'mode_single') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, updated_at) VALUES (?, 'single', 'recipient', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId, "📤 *Single Recipient*\n\nEnter the recipient's *LagosLife username*:");
        exit;
    }

    if ($data === 'mode_multi') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, updated_at) VALUES (?, 'multi', 'multi_paste', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId,
            "👥 *Multiple Recipients*\n\n" .
            "Paste your list — one per line:\n\n" .
            "`@username1  amount\n@username2  amount\n@username3  amount`\n\n" .
            "*Examples:*\n" .
            "`@alice 5M\n@bob 10B\n@charlie 2.5M`\n\n" .
            "Supports `1M`, `500K`, `2.5B` or plain numbers.\n" .
            "Comma or space between name and amount both work."
        );
        exit;
    }

    if ($data === 'mode_support_llbm') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, recipient_username, recipient_player_id, note, updated_at) VALUES (?, 'single', 'amount', ?, ?, 'Support LLBM Movement 💎', NOW())")
           ->execute([$chatId, LLBM_MOVEMENT_USERNAME, LLBM_MOVEMENT_PLAYER_ID]);
        safeEdit($chatId, $msgId,
            "💎 *Support LLBM Movement*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🎯 *Beneficiary:* *" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")\n" .
            "🆔 *Player ID:* `" . LLBM_MOVEMENT_PLAYER_ID . "`\n\n" .
            "How much funds would you like to contribute?\n\n" .
            "💡 _Examples:_ `100K`, `5M`, `10B`, `50B`, `100B`\n" .
            "📦 _Amounts over ₦10B will automatically be sent in batches of ₦10B with the standard 32s cooldown._"
        );
        exit;
    }

    if ($data === 'send_cancel' || $data === 'multi_cancel') {
        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        safeEdit($chatId, $msgId, "❌ Transfer cancelled.");
        safeSend($chatId, "What next?", mainMenu(true));
        exit;
    }

    if ($data === 'send_confirm') {
        $pStmt = $db->prepare("SELECT * FROM pending_transfers WHERE chat_id = ?");
        $pStmt->execute([$chatId]);
        $pending = $pStmt->fetch();

        if (!$pending || empty($pending['recipient_player_id']) || empty($pending['amount'])) {
            safeEdit($chatId, $msgId, "⚠️ Session expired. Please start again with /send.");
            exit;
        }

        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        $isLLBM = strtolower($pending['recipient_username']) === strtolower(LLBM_MOVEMENT_USERNAME);
        $note = $pending['note'] ?: ($isLLBM ? 'Support LLBM Movement 💎' : 'Sent via LLBM Money Bot');
        $amount = (int)$pending['amount'];
        $chunks = chunkAmounts($amount);

        wipeMessage($chatId, $msgId);

        if (count($chunks) === 1) {
            $wait = safeSend($chatId, "⏳ Sending…");
            $waitId = $wait['result']['message_id'] ?? 0;
            $res = sendMoney($chatId, $pending['recipient_player_id'], $amount, $note, $user['pin'] ?? null);
            $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);

            if ($res['ok']) {
                $db->prepare("INSERT INTO transfers (chat_id, sender_ll_username, recipient_username, recipient_player_id, amount, note, status, tx_response, created_at) VALUES (?, ?, ?, ?, ?, ?, 'success', ?, NOW())")
                   ->execute([$chatId, $user['ll_username'], $pending['recipient_username'], $pending['recipient_player_id'], $amount, $note, $res['txId'] ?? '']);

                $successMsg =
                    "✅ *Transfer Successful!*\n\n" .
                    "👤 *From:* @" . escMd($user['ll_username']) . "\n" .
                    "🎯 *To:* " . ($isLLBM ? "*" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")" : "@" . escMd($pending['recipient_username'])) . "\n" .
                    "💵 *Amount:* " . fmtFull($amount) . " _(" . fmtShort($amount) . ")_\n" .
                    "📝 *Note:* " . escMd($note);

                if ($waitId) safeEdit($chatId, $waitId, $successMsg);
                else safeSend($chatId, $successMsg);

                safeSend($chatId, "What would you like to do next?", mainMenu(true));

                if ($isLLBM) {
                    notifyChannel(
                        "💎 *LLBM MOVEMENT CONTRIBUTION!*\n" .
                        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                        "👤 *Supporter:* @" . escMd($user['ll_username']) . "\n" .
                        "🎯 *Beneficiary:* @" . LLBM_MOVEMENT_USERNAME . " (" . LLBM_MOVEMENT_NAME . ")\n" .
                        "💵 *Amount:* " . fmtFull($amount) . " _(" . fmtShort($amount) . ")_\n" .
                        "❤️ *Thank you for empowering the Movement!* 🚀\n" .
                        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                        "🤖 *LLBM Money Bot*"
                    );
                } else {
                    notifyChannel(
                        "💸 *TRANSFER NOTIFICATION*\n" .
                        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                        "👤 *Sender:* @" . escMd($user['ll_username']) . "\n" .
                        "🎯 *Recipient:* @" . escMd($pending['recipient_username']) . "\n" .
                        "💵 *Amount:* " . fmtFull($amount) . " _(" . fmtShort($amount) . ")_\n" .
                        "📝 *Note:* " . escMd($note) . "\n" .
                        "⚡ *Status:* Delivered ✅\n" .
                        "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                        "🤖 *LLBM Money Bot*"
                    );
                }
            } else {
                $err = $res['error'] ?? 'Transfer failed';
                $db->prepare("INSERT INTO transfers (chat_id, sender_ll_username, recipient_username, recipient_player_id, amount, note, status, error_reason, created_at) VALUES (?, ?, ?, ?, ?, ?, 'failed', ?, NOW())")
                   ->execute([$chatId, $user['ll_username'], $pending['recipient_username'], $pending['recipient_player_id'], $amount, $note, $err]);

                if ($waitId) safeEdit($chatId, $waitId, "❌ *Transfer Failed*\n\nReason: " . escMd($err) . "\n\nTry again with /send.");
                else safeSend($chatId, "❌ *Transfer Failed*\n\nReason: " . escMd($err));

                safeSend($chatId, "What would you like to do?", mainMenu(true));
            }
        } else {
            // Multi-chunk batch
            $jobId = bin2hex(random_bytes(12));
            $initProgress = safeSend($chatId, "⏳ Initializing batch…");
            $progId = $initProgress['result']['message_id'] ?? 0;

            $db->prepare("INSERT INTO batch_jobs (id, chat_id, sender_username, mode, total_chunks, total_amount, progress_msg_id, created_at, updated_at) VALUES (?, ?, ?, 'single', ?, ?, ?, NOW(), NOW())")
               ->execute([$jobId, $chatId, $user['ll_username'], count($chunks), $amount, $progId]);

            foreach ($chunks as $idx => $amt) {
                $db->prepare("INSERT INTO batch_chunks (job_id, chunk_index, recipient_username, recipient_player_id, amount, note, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                   ->execute([$jobId, $idx, $pending['recipient_username'], $pending['recipient_player_id'], $amt, $note]);
            }

            $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
            startBatchWorker($jobId);
        }
        exit;
    }

    if ($data === 'multi_confirm') {
        $pStmt = $db->prepare("SELECT * FROM pending_transfers WHERE chat_id = ?");
        $pStmt->execute([$chatId]);
        $pending = $pStmt->fetch();

        if (!$pending || empty($pending['data'])) {
            safeEdit($chatId, $msgId, "⚠️ Session expired. Please start again with /send.");
            exit;
        }

        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        $recipients = json_decode($pending['data'], true);
        wipeMessage($chatId, $msgId);

        $chunks = [];
        $totalAmount = 0;
        foreach ($recipients as $r) {
            $rChunks = chunkAmounts((int)$r['amount']);
            foreach ($rChunks as $cAmt) {
                $chunks[] = [
                    'username' => $r['username'],
                    'playerId' => $r['playerId'],
                    'amount'   => $cAmt,
                ];
                $totalAmount += $cAmt;
            }
        }

        $jobId = bin2hex(random_bytes(12));
        $initProgress = safeSend($chatId, "⏳ Initializing batch for " . count($recipients) . " recipients…");
        $progId = $initProgress['result']['message_id'] ?? 0;

        $db->prepare("INSERT INTO batch_jobs (id, chat_id, sender_username, mode, total_chunks, total_amount, progress_msg_id, created_at, updated_at) VALUES (?, ?, ?, 'multi', ?, ?, ?, NOW(), NOW())")
           ->execute([$jobId, $chatId, $user['ll_username'], count($chunks), $totalAmount, $progId]);

        foreach ($chunks as $idx => $c) {
            $db->prepare("INSERT INTO batch_chunks (job_id, chunk_index, recipient_username, recipient_player_id, amount, note, status) VALUES (?, ?, ?, ?, ?, 'Sent via LLBM Money Bot', 'pending')")
               ->execute([$jobId, $idx, $c['username'], $c['playerId'], $c['amount']]);
        }

        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        startBatchWorker($jobId);
        exit;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // House & Beautification Callbacks
    // ────────────────────────────────────────────────────────────────────────────
    if ($data === 'house_menu') {
        renderHouseMenu($chatId, $msgId);
        exit;
    }

    if (strpos($data, 'house_clone_tpl_') === 0) {
        $tplKey = substr($data, strlen('house_clone_tpl_'));
        $tpls = getHouseTemplates();
        if (!isset($tpls[$tplKey])) {
            safeEdit($chatId, $msgId, "❌ Template not found.");
            exit;
        }
        $t = $tpls[$tplKey];
        $text =
            "📋 *HOUSE CLONING QUOTE & SPECIFICATION*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "• *Source Design:* *{$t['name']}* (@{$t['owner']})\n" .
            "• *Luxury Elements:* *{$t['items']} Total Items*\n" .
            "• *Description:* {$t['description']}\n\n" .
            "💰 *Package Cost:* *" . fmtFull($t['cost']) . "* _(" . fmtShort($t['cost']) . ")_\n\n" .
            "🗑️ *Clean Slate Guarantee:*\n" .
            "⚠️ *All old furniture items will be removed and replaced* with this exact layout so there is zero clashing clutter.\n\n" .
            "_Tap below to confirm and install this luxury design into your home!_";

        $kb = [
            'inline_keyboard' => [
                [['text' => '🚀 Confirm & Install Design (' . fmtShort($t['cost']) . ')', 'callback_data' => "house_apply_$tplKey"]],
                [['text' => '🔙 Back to House Menu', 'callback_data' => 'house_menu']],
            ]
        ];
        safeEdit($chatId, $msgId, $text, $kb);
        exit;
    }

    if (strpos($data, 'house_apply_') === 0) {
        $tplKey = substr($data, strlen('house_apply_'));
        $design = loadHouseTemplate($tplKey);
        $tpls = getHouseTemplates();
        $cost = $tpls[$tplKey]['cost'] ?? 0;

        if (!$design) {
            safeEdit($chatId, $msgId, "❌ Template design file not found.");
            exit;
        }

        safeEdit($chatId, $msgId, "⏳ *Installing luxury furniture, wall finishings, and marble floors into your home…*");
        $res = applyHouseDesign($chatId, $design, $cost);

        if ($res['ok']) {
            $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId'")->fetch();
            $successMsg =
                "🎉 *HOUSE BEAUTIFIED SUCCESSFULLY!*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "• *Design Installed:* *{$tpls[$tplKey]['name']}*\n" .
                "• *Elements Placed:* *{$res['itemsCount']} luxury items*\n" .
                "• *New Balance:* *" . fmtFull($res['newBalance']) . "*\n\n" .
                "🏰 Log into [lagoslife.app](https://lagoslife.app) to walk inside your brand new luxury residence!";

            safeEdit($chatId, $msgId, $successMsg, [
                'inline_keyboard' => [
                    [['text' => '🏠 House Menu', 'callback_data' => 'house_menu']],
                ]
            ]);

            notifyChannel(
                "🏰 *[LUXURY RESIDENCE BEAUTIFIED]*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "• *Owner:* @" . escMd($user['ll_username'] ?? 'Landlord') . "\n" .
                "• *Design:* *" . escMd($tpls[$tplKey]['name']) . "*\n" .
                "• *Installed:* *{$res['itemsCount']} luxury elements*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "🤖 *LLBM Money Bot*"
            );
        } else {
            safeEdit($chatId, $msgId, "❌ *Failed to beautify house:*\n" . ($res['error'] ?? 'Unknown error'), [
                'inline_keyboard' => [
                    [['text' => '🔄 Try Again', 'callback_data' => "house_clone_tpl_$tplKey"]],
                    [['text' => '🏠 House Menu', 'callback_data' => 'house_menu']],
                ]
            ]);
        }
        exit;
    }

    if ($data === 'house_custom') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, updated_at) VALUES (?, 'house', 'inspect_user', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId,
            "✍️ *Clone Player House by Username*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Please send the *LagosLife @username* of the player whose house layout you want to inspect & clone:\n\n" .
            "💡 _Examples: `@wedekaov`, `@phinnybabe`, `@kimoflele`_"
        );
        exit;
    }

    if (strpos($data, 'house_apply_inspect_') === 0) {
        $targetUser = substr($data, strlen('house_apply_inspect_'));
        $insp = inspectPlayerHouse($chatId, $targetUser);

        if (!$insp['ok']) {
            safeEdit($chatId, $msgId, "❌ " . ($insp['error'] ?? 'Could not clone house.'));
            exit;
        }

        safeEdit($chatId, $msgId, "⏳ *Cloning layout from @$targetUser into your home…*");
        $res = applyHouseDesign($chatId, $insp, 0);

        if ($res['ok']) {
            $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId'")->fetch();
            safeEdit($chatId, $msgId,
                "🎉 *HOUSE CLONED SUCCESSFULLY!*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "• *Source:* *@$targetUser*\n" .
                "• *Elements Placed:* *{$res['itemsCount']} luxury items*\n\n" .
                "🏰 Log into [lagoslife.app](https://lagoslife.app) to view your cloned house!",
                [
                    'inline_keyboard' => [
                        [['text' => '🏠 House Menu', 'callback_data' => 'house_menu']],
                    ]
                ]
            );

            notifyChannel(
                "🏰 *[HOUSE CLONED]*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "• *Owner:* @" . escMd($user['ll_username'] ?? 'Landlord') . "\n" .
                "• *Cloned From:* @" . escMd($targetUser) . "\n" .
                "• *Items Installed:* *{$res['itemsCount']} items*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "🤖 *LLBM Money Bot*"
            );
        } else {
            safeEdit($chatId, $msgId, "❌ Failed to clone house: " . ($res['error'] ?? 'Unknown error'));
        }
        exit;
    }

    if ($data === 'house_bookmarklet') {
        $bm = "javascript:(async function(){try{var p=new URLSearchParams(window.location.search);var u=p.get('visit')||prompt('📸 Enter username of house owner:');if(!u)return;u=u.replace(/^@/,'').trim();var mR=await fetch('/api/homes/u/'+encodeURIComponent(u));if(!mR.ok){alert('❌ Could not locate home of @'+u);return;}var m=await mR.json();var rR=await fetch('/api/homes/'+encodeURIComponent(m.id));if(!rR.ok){alert('⚠️ You must be inside the house of @'+u+' to capture their layout!');return;}var r=await rR.json();if((!r.objects||!r.objects.length)&&(!r.up||!r.up.length)){alert('⚠️ No furniture objects found in home of @'+u);return;}var allObjs=[...(r.objects||[]),...((r.up||[]).map(function(o){return Object.assign({},o,{floor:1});}))];alert('🎉 Captured '+allObjs.length+' items from @'+u+'!');}catch(e){alert('Error: '+e.message);}})();";

        $text =
            "📸 *1-CLICK IN-GAME HOUSE SCANNER*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Visiting any player's house in your game browser? You can scan and capture their layout in 1 click!\n\n" .
            "*How to use:*\n" .
            "1. Create a browser bookmark named `📸 Copy House`\n" .
            "2. Paste the snippet below into the URL field\n" .
            "3. Click it while visiting any home on [lagoslife.app](https://lagoslife.app)!\n\n" .
            "👇 *Bookmarklet Code:*";

        safeEdit($chatId, $msgId, $text, [
            'inline_keyboard' => [
                [['text' => '🔙 Back to House Menu', 'callback_data' => 'house_menu']],
            ]
        ]);
        safeSend($chatId, "`$bm`");
        exit;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Quilox Callbacks
    // ────────────────────────────────────────────────────────────────────────────
    if ($data === 'quilox_menu') {
        renderQuiloxMenu($chatId, $msgId);
        exit;
    }

    if ($data === 'quilox_sweep') {
        safeEdit($chatId, $msgId, "⚡ *Sweeping dance floor at Quilox Nightclub for sprayed cash…*");
        $res = sweepQuiloxFloor($chatId);
        if ($res['ok']) {
            if ($res['grabbed'] > 0) {
                $msg = "🎉 *SCOOPED " . fmtFull($res['grabbed']) . " OFF THE FLOOR!* 🪩\n\n" .
                       "• Cash Grabbed: *" . fmtFull($res['grabbed']) . "*\n" .
                       "• Sprayed By: @" . implode(', @', $res['sprayers']) . "\n" .
                       "• Claimed directly into your wallet balance! ✅";
            } else {
                $msg = "🍸 *Floor Swept!*\n\nNo active banknotes were sprayed on the dance floor right now. Stay tuned or sweep again!";
            }
        } else {
            $msg = "⚠️ " . ($res['error'] ?? 'Could not sweep floor.');
        }

        safeEdit($chatId, $msgId, $msg, [
            'inline_keyboard' => [
                [['text' => '⚡ Sweep Again',        'callback_data' => 'quilox_sweep']],
                [['text' => '🪩 Quilox Menu',       'callback_data' => 'quilox_menu']],
            ]
        ]);
        exit;
    }

    if ($data === 'quilox_teleport') {
        $res = teleportToQuilox($chatId);
        safeEdit($chatId, $msgId, "📍 *" . ($res['message'] ?? 'Character at Quilox!') . "*", [
            'inline_keyboard' => [
                [['text' => '⚡ Sweep Dance Floor Now', 'callback_data' => 'quilox_sweep']],
                [['text' => '🪩 Quilox Menu',           'callback_data' => 'quilox_menu']],
            ]
        ]);
        exit;
    }

    if ($data === 'quilox_claim') {
        requestAsUser($chatId, '/api/claim', 'POST', []);
        $bal = getBalance($chatId);
        safeEdit($chatId, $msgId, "💰 *Cash Claimed!*\n\nAll pending banknotes deposited into your wallet balance.\nBalance: *" . fmtFull($bal ?? 0) . "*", [
            'inline_keyboard' => [
                [['text' => '🪩 Quilox Menu', 'callback_data' => 'quilox_menu']],
            ]
        ]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // VIP Whales Callback
    // ────────────────────────────────────────────────────────────────────────────
    if ($data === 'vip_whales') {
        renderVipWhalesMenu($chatId, $msgId);
        exit;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Handle Messages
// ────────────────────────────────────────────────────────────────────────────
if (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = (string)$msg['chat']['id'];
    $userId = (string)$msg['from']['id'];
    $text = trim($msg['text'] ?? '');
    $msgId = (int)$msg['message_id'];

    // Mandatory Subscription Gate
    if (!isSubscribed($userId)) {
        $prompt = getSubPrompt();
        safeSend($chatId, $prompt['text'], $prompt['kb']);
        exit;
    }

    $userStmt = $db->prepare("SELECT * FROM bot_users WHERE chat_id = ? AND is_authenticated = 1");
    $userStmt->execute([$chatId]);
    $user = $userStmt->fetch();
    $loggedIn = !empty($user);

    // /start
    if ($text === '/start') {
        if ($loggedIn) {
            $bal = getBalance($chatId);
            $balLine = $bal !== null ? "\n💰 Balance: *" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "";
            $pinLine = !empty($user['pin']) ? "\n🔐 Transfer PIN: *Set ✅*" : "\n🔐 Transfer PIN: *Not set*";

            safeSend($chatId,
                "🏦 *LLBM Money Bot*\n\n" .
                "Welcome back, *{$user['ll_name']}*! 👋\n" .
                "👤 Account: *@{$user['ll_username']}*\n" .
                "🆔 Player ID: `{$user['ll_user_id']}`" .
                $balLine . $pinLine,
                mainMenu(true)
            );
        } else {
            safeSend($chatId,
                "🏦 *LLBM Money Bot*\n\n" .
                "Send LagosLife money directly from your own account.\n\n" .
                "✅ Auto-batch for amounts over *₦10B*\n" .
                "✅ *32s cooldown* handled automatically between chunks\n" .
                "✅ Multi-recipient — send to *many users at once*\n" .
                "✅ Support the *LLBM Movement* directly\n" .
                "✅ Transfer PIN support\n" .
                "✅ Multiple users, fully isolated\n\n" .
                "👇 Tap *Login* to begin.",
                mainMenu(false)
            );
        }
        exit;
    }

    // /help
    if ($text === '/help') {
        safeSend($chatId,
            "📖 *Help*\n\n" .
            "/login — Connect your LagosLife account\n" .
            "/send — Send money (single or multi-recipient)\n" .
            "/support — Send money directly to support LLBM Movement\n" .
            "/balance — Check your wallet balance\n" .
            "/history — Last 10 transfers\n" .
            "/account — Your account & Player ID details\n" .
            "/setpin — Set or update your transfer PIN\n" .
            "/clearpin — Remove your stored PIN\n" .
            "/logout — Disconnect\n\n" .
            "*Smart batch rules:*\n" .
            "• Max *₦10B* per single transfer\n" .
            "• Over ₦10B → auto-split into chunks\n" .
            "• *32s cooldown* between every chunk\n" .
            "• PIN entered once → applied to all chunks\n" .
            "• Live alerts posted to " . NOTIFY_CHANNEL
        );
        exit;
    }

    // /login or 🔑 Login
    if ($text === '/login' || $text === '🔑 Login') {
        if ($loggedIn) {
            safeSend($chatId, "✅ Already logged in as *@{$user['ll_username']}*.\nUse /logout to switch accounts.", mainMenu(true));
            exit;
        }
        $db->prepare("REPLACE INTO pending_transfers (chat_id, step, updated_at) VALUES (?, 'login_username', NOW())")->execute([$chatId]);
        safeSend($chatId, "🔑 *Login to LagosLife*\n\nEnter your *LagosLife username*:", ['remove_keyboard' => true]);
        exit;
    }

    // /logout or 🔓 Logout
    if ($text === '/logout' || $text === '🔓 Logout') {
        $db->prepare("UPDATE bot_users SET is_authenticated = 0, ll_session_cookie = NULL, pin = NULL, updated_at = NOW() WHERE chat_id = ?")->execute([$chatId]);
        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        safeSend($chatId, "👋 Logged out. Your session and PIN have been cleared.", mainMenu(false));
        exit;
    }

    // /balance or 💰 My Balance
    if ($text === '/balance' || $text === '💰 My Balance') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $wait = safeSend($chatId, "⏳ Fetching your live balance…");
        $waitId = $wait['result']['message_id'] ?? 0;
        $bal = getBalance($chatId);
        if ($bal !== null) {
            $msgText = "💰 *Wallet Balance*\n\n👤 Account: *@{$user['ll_username']}*\n━━━━━━━━━━━━━━━━━━━━━━━━━\n💵 *" . fmtFull($bal) . "*\n    _(" . fmtShort($bal) . ")_";
            if ($waitId) safeEdit($chatId, $waitId, $msgText);
            else safeSend($chatId, $msgText);
        } else {
            safeSend($chatId, "⚠️ Could not fetch balance. Try again shortly.");
        }
        exit;
    }

    // /account or 👤 My Account
    if ($text === '/account' || $text === '👤 My Account') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $bal = getBalance($chatId);
        $balLine = $bal !== null ? "\n💰 Balance: *" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "";
        $pinLine = !empty($user['pin']) ? "\n🔐 Transfer PIN: *Set ✅*" : "\n🔐 Transfer PIN: *Not set* — use /setpin";

        safeSend($chatId,
            "👤 *My Account*\n\n" .
            "🎮 LagosLife: *@{$user['ll_username']}*\n" .
            "📛 Name: " . ($user['ll_name'] ?: $user['ll_username']) . "\n" .
            "🆔 Player ID: `{$user['ll_user_id']}`\n" .
            "✅ Status: *Logged in*\n" .
            "📅 Since: {$user['authenticated_at']}" .
            $balLine . $pinLine,
            mainMenu(true)
        );
        exit;
    }

    // /history or 📋 Transfer History
    if ($text === '/history' || $text === '📋 Transfer History') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $histStmt = $db->prepare("SELECT * FROM transfers WHERE chat_id = ? ORDER BY id DESC LIMIT 10");
        $histStmt->execute([$chatId]);
        $rows = $histStmt->fetchAll();

        if (empty($rows)) {
            safeSend($chatId, "📭 No transfers yet.", mainMenu(true));
            exit;
        }

        $lines = [];
        foreach ($rows as $i => $r) {
            $icon = $r['status'] === 'success' ? '✅' : '❌';
            $d = date('d M', strtotime($r['created_at']));
            $lines[] = "$icon " . ($i + 1) . ". *" . fmtShort((int)$r['amount']) . "* → @" . escMd($r['recipient_username']) . " _($d)_";
        }

        safeSend($chatId, "📋 *Last " . count($rows) . " Transfers*\n\n" . implode("\n", $lines), mainMenu(true));
        exit;
    }

    // /setpin or 🔐 Set/Update PIN
    if ($text === '/setpin' || $text === '🔐 Set/Update PIN') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $db->prepare("REPLACE INTO pending_transfers (chat_id, step, updated_at) VALUES (?, 'enter_pin', NOW())")->execute([$chatId]);
        $curr = !empty($user['pin']) ? "_You have a PIN set — enter new PIN to replace it._" : "_No PIN set yet._";
        safeSend($chatId, "🔐 *Set Transfer PIN*\n\n$curr\n\nEnter your PIN now:\n_(Message will be deleted immediately)_", ['remove_keyboard' => true]);
        exit;
    }

    // /clearpin
    if ($text === '/clearpin') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $db->prepare("UPDATE bot_users SET pin = NULL WHERE chat_id = ?")->execute([$chatId]);
        safeSend($chatId, "🔓 Transfer PIN cleared.", mainMenu(true));
        exit;
    }

    // /support or 💎 Support LLBM
    if ($text === '/support' || $text === '💎 Support LLBM') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, recipient_username, recipient_player_id, note, updated_at) VALUES (?, 'single', 'amount', ?, ?, 'Support LLBM Movement 💎', NOW())")
           ->execute([$chatId, LLBM_MOVEMENT_USERNAME, LLBM_MOVEMENT_PLAYER_ID]);

        safeSend($chatId,
            "💎 *Support LLBM Movement*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🎯 *Beneficiary:* *" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")\n" .
            "🆔 *Player ID:* `" . LLBM_MOVEMENT_PLAYER_ID . "`\n\n" .
            "How much funds would you like to contribute?\n\n" .
            "💡 _Examples:_ `100K`, `5M`, `10B`, `50B`, `100B`\n" .
            "📦 _Amounts over ₦10B will automatically be sent in batches of ₦10B with the standard 32s cooldown._",
            ['remove_keyboard' => true]
        );
        exit;
    }

    // /send or 💸 Send Money
    if ($text === '/send' || $text === '💸 Send Money') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        $pinStatus = !empty($user['pin']) ? "🔐 PIN: *Set ✅*" : "🔐 PIN: *Not set* _(use /setpin)_";

        $kb = [
            'inline_keyboard' => [
                [['text' => '📤 Single Recipient',      'callback_data' => 'mode_single']],
                [['text' => '👥 Multiple Recipients',   'callback_data' => 'mode_multi']],
                [['text' => '💎 Support LLBM Movement', 'callback_data' => 'mode_support_llbm']],
            ]
        ];

        safeSend($chatId, "💸 *Send Money*\n$pinStatus\n\nChoose mode:", $kb);
        exit;
    }

    // /beautify or 🏠 Beautify House or /house or /clone
    if ($text === '/beautify' || $text === '🏠 Beautify House' || $text === '/house' || $text === '/clone') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        renderHouseMenu($chatId);
        exit;
    }

    // /quilox or 🪩 Quilox Money Picker or /picker or /moneypicker
    if ($text === '/quilox' || $text === '🪩 Quilox Money Picker' || $text === '/picker' || $text === '/moneypicker') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        renderQuiloxMenu($chatId);
        exit;
    }

    // /whales or 👑 VIP Whale Radar or /vip or /radar or /elites
    if ($text === '/whales' || $text === '👑 VIP Whale Radar' || $text === '/vip' || $text === '/radar' || $text === '/elites') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); exit; }
        renderVipWhalesMenu($chatId);
        exit;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Wizard text state handlers
    // ────────────────────────────────────────────────────────────────────────────
    $pStmt = $db->prepare("SELECT * FROM pending_transfers WHERE chat_id = ?");
    $pStmt->execute([$chatId]);
    $pending = $pStmt->fetch();

    if ($pending && !empty($pending['step'])) {
        $step = $pending['step'];

        if ($step === 'enter_pin') {
            wipeMessage($chatId, $msgId);
            $cleanPin = preg_replace('/\s+/', '', $text);
            $db->prepare("UPDATE bot_users SET pin = ? WHERE chat_id = ?")->execute([$cleanPin, $chatId]);
            $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
            safeSend($chatId, "🔐 *Transfer PIN saved!*\n\n✅ Your PIN will automatically be included in all future transfers.", mainMenu(true));
            exit;
        }

        if ($step === 'inspect_user') {
            $target = ltrim(trim($text), '@');
            $wait = safeSend($chatId, "🔍 *Inspecting residence of @$target…*");
            $waitId = $wait['result']['message_id'] ?? 0;
            $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);

            $insp = inspectPlayerHouse($chatId, $target);
            if ($insp['ok']) {
                $count = $insp['count'];
                $prop = $insp['home'];
                $quoteText =
                    "📋 *HOUSE CLONING QUOTE & SPECIFICATION*\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "• *Source House:* *@$target*\n" .
                    "• *Property Type:* 🏰 *$prop*\n" .
                    "• *Furniture Items:* *$count Total Elements*\n" .
                    "• *Wall Decor:* `{$insp['decor']['wall']}`\n" .
                    "• *Floor Decor:* `{$insp['decor']['floor']}`\n\n" .
                    "🗑️ *Clean Slate Guarantee:*\n" .
                    "⚠️ *All old furniture items will be removed and replaced* with this exact layout so there is zero clashing clutter.\n\n" .
                    "_Tap below to clone this house design now!_";

                $kb = [
                    'inline_keyboard' => [
                        [['text' => "🚀 Confirm & Clone House ($count Items)", 'callback_data' => "house_apply_inspect_$target"]],
                        [['text' => "✍️ Choose Another User", 'callback_data' => "house_custom"]],
                        [['text' => "🏠 House Menu",            'callback_data' => "house_menu"]],
                    ]
                ];
                if ($waitId) safeEdit($chatId, $waitId, $quoteText, $kb);
                else safeSend($chatId, $quoteText, $kb);
            } else {
                $errText =
                    "🔒 *@$target is not inside their house right now or doors are locked.*\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "LagosLife protects private house coordinates when the owner is away from home.\n\n" .
                    "🌟 *Ready-to-Clone Catalog:* You can install one of our verified master designs immediately!";

                $kb = [
                    'inline_keyboard' => [
                        [['text' => '🏰 Clone @wedekaov Mansion (153 Items)', 'callback_data' => 'house_clone_tpl_wedekaov']],
                        [['text' => '✍️ Try Another Username', 'callback_data' => 'house_custom']],
                        [['text' => '🏠 House Menu',            'callback_data' => 'house_menu']],
                    ]
                ];
                if ($waitId) safeEdit($chatId, $waitId, $errText, $kb);
                else safeSend($chatId, $errText, $kb);
            }
            exit;
        }

        if ($step === 'login_username') {
            $db->prepare("UPDATE pending_transfers SET step = 'login_password', recipient_username = ? WHERE chat_id = ?")->execute([$text, $chatId]);
            safeSend($chatId, "Got it! Now enter your *LagosLife password*:\n_(Message will be deleted from chat immediately)_");
            exit;
        }

        if ($step === 'login_password') {
            wipeMessage($chatId, $msgId);
            $username = $pending['recipient_username'];
            $password = $text;
            $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);

            $wait = safeSend($chatId, "⏳ Logging in to LagosLife…");
            $waitId = $wait['result']['message_id'] ?? 0;
            $loginRes = llLogin($username, $password);

            if (!$loginRes['ok']) {
                $err = $loginRes['error'] ?? 'Login failed';
                if ($waitId) safeEdit($chatId, $waitId, "❌ *Login Failed*\n\nReason: $err\n\nTry /login again.");
                else safeSend($chatId, "❌ *Login Failed*\n\nReason: $err");
                safeSend($chatId, "", mainMenu(false));
                exit;
            }

            $tgUser = $msg['from']['username'] ?? '';
            $tgName = $msg['from']['first_name'] ?? '';

            $db->prepare("REPLACE INTO bot_users (chat_id, tg_username, tg_first_name, ll_username, ll_session_cookie, ll_user_id, ll_name, is_authenticated, authenticated_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())")
               ->execute([$chatId, $tgUser, $tgName, $username, $loginRes['cookie'], $loginRes['userId'], $loginRes['name']]);

            $bal = getBalance($chatId);
            $balLine = $bal !== null ? "\n💰 Balance: *" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "";

            $successText =
                "✅ *Logged In Successfully!*\n\n" .
                "👤 Account: *@$username*\n" .
                "📛 Name: *{$loginRes['name']}*\n" .
                "🆔 Player ID: `{$loginRes['userId']}`" .
                $balLine;

            if ($waitId) safeEdit($chatId, $waitId, $successText);
            else safeSend($chatId, $successText);

            $pinKb = [
                'inline_keyboard' => [
                    [['text' => '🔐 Yes, set my PIN', 'callback_data' => 'setup_pin']],
                    [['text' => '⏩ No PIN / Skip',   'callback_data' => 'skip_pin']],
                ]
            ];
            safeSend($chatId, "🔐 *Does your account have a transfer PIN?*\n\nIf yes, enter it once — it will apply to all transfers automatically.", $pinKb);

            notifyChannel(
                "🔑 *USER SESSION CONNECTED*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *In-Game Account:* @" . escMd($username) . " (" . escMd($loginRes['name']) . ")\n" .
                "⚡ *Status:* Online & Active\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "🤖 *LLBM Money Bot*"
            );
            exit;
        }

        if ($step === 'recipient') {
            $raw = ltrim(trim($text), '@');
            $wait = safeSend($chatId, "🔍 Looking up *@$raw*…");
            $waitId = $wait['result']['message_id'] ?? 0;
            $player = resolvePlayer($chatId, $raw);

            if (!$player) {
                if ($waitId) safeEdit($chatId, $waitId, "❌ Player *@$raw* not found on LagosLife.\nCheck spelling and try again.");
                else safeSend($chatId, "❌ Player not found.");
                exit;
            }

            $db->prepare("UPDATE pending_transfers SET step = 'amount', recipient_username = ?, recipient_player_id = ? WHERE chat_id = ?")
               ->execute([$player['username'], $player['id'], $chatId]);

            $foundText = "✅ Found: *{$player['name']}* (@{$player['username']})\n\nEnter the *amount* to send:\n💡 e.g. `500K`, `5M`, `10B`, `100B`\n_(Over ₦10B auto-splits into chunks with 32s cooldown)_";
            if ($waitId) safeEdit($chatId, $waitId, $foundText);
            else safeSend($chatId, $foundText);
            exit;
        }

        if ($step === 'amount') {
            $amount = parseAmount($text);
            if (!$amount || $amount < 1) {
                safeSend($chatId, "⚠️ Invalid amount. Try `5M`, `10B`, `500000`.");
                exit;
            }

            $chunks = chunkAmounts($amount);
            $batchMsg = count($chunks) > 1 ? "\n📦 *" . count($chunks) . " chunks* of " . fmtShort(MAX_CHUNK) . " · 32s cooldown" : "";

            $db->prepare("UPDATE pending_transfers SET step = 'note', amount = ? WHERE chat_id = ?")->execute([$amount, $chatId]);
            $isLLBM = strtolower($pending['recipient_username'] ?? '') === strtolower(LLBM_MOVEMENT_USERNAME);

            $noteKb = [
                'keyboard' => [
                    [['text' => '⏩ Skip note']]
                ],
                'resize_keyboard' => true,
            ];

            safeSend($chatId,
                "💵 Amount: *" . fmtFull($amount) . "* _(" . fmtShort($amount) . ")_$batchMsg\n\n" .
                "Add a *transfer note* or tap Skip:" .
                ($isLLBM ? "\n_(Default note: \"Support LLBM Movement 💎\")_" : ""),
                $noteKb
            );
            exit;
        }

        if ($step === 'note') {
            $isLLBM = strtolower($pending['recipient_username'] ?? '') === strtolower(LLBM_MOVEMENT_USERNAME);
            $defaultNote = $isLLBM ? 'Support LLBM Movement 💎' : 'Sent via LLBM Money Bot';
            $note = $text === '⏩ Skip note' ? $defaultNote : substr($text, 0, 200);

            $db->prepare("UPDATE pending_transfers SET step = 'confirm', note = ? WHERE chat_id = ?")->execute([$note, $chatId]);

            $chunks = chunkAmounts((int)$pending['amount']);
            $confirmTitle = $isLLBM ? "💎 *Confirm LLBM Movement Contribution*" : "📋 *Confirm Transfer*";
            $recipientLabel = $isLLBM ? "*" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")" : "@" . escMd($pending['recipient_username']);

            $confirmText =
                "$confirmTitle\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *From:* @" . escMd($user['ll_username']) . "\n" .
                "🎯 *To:* $recipientLabel\n" .
                "💵 *Amount:* " . fmtFull((int)$pending['amount']) . " _(" . fmtShort((int)$pending['amount']) . ")_\n" .
                "📝 *Note:* " . escMd($note) . "\n" .
                "🔐 *PIN:* " . (!empty($user['pin']) ? 'Included ✅' : 'Not set') . "\n" .
                (count($chunks) > 1 ? "📦 *Chunks:* " . count($chunks) . " × " . fmtShort(MAX_CHUNK) . " · 32s cooldown\n" : "") .
                "━━━━━━━━━━━━━━━━━━━━━━━━━";

            $confirmKb = [
                'inline_keyboard' => [
                    [['text' => '✅ Confirm & Send', 'callback_data' => 'send_confirm']],
                    [['text' => '❌ Cancel',         'callback_data' => 'send_cancel']],
                ]
            ];

            safeSend($chatId, $confirmText, $confirmKb);
            safeSend($chatId, "👆 Review and confirm above.", ['remove_keyboard' => true]);
            exit;
        }

        if ($step === 'multi_paste') {
            $lines = preg_split('/[\r\n;]+/', $text);
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
                safeSend($chatId, "⚠️ *Could not parse any entries.*\n\nFormat: `@username amount` — one per line.\nExample:\n`@alice 5M\n@bob 10B\n@charlie 2.5M`");
                exit;
            }

            $wait = safeSend($chatId, "🔍 Resolving " . count($parsed) . " username(s)…", ['remove_keyboard' => true]);
            $waitId = $wait['result']['message_id'] ?? 0;

            $resolved = [];
            $failedResolve = [];

            foreach ($parsed as $r) {
                $player = resolvePlayer($chatId, $r['username']);
                if ($player) {
                    $resolved[] = [
                        'username' => $player['username'],
                        'playerId' => $player['id'],
                        'amount'   => $r['amount'],
                    ];
                } else {
                    $failedResolve[] = "❌ @" . escMd($r['username']) . " — not found";
                }
            }

            if (empty($resolved)) {
                if ($waitId) safeEdit($chatId, $waitId, "❌ No valid players found.\n\n" . implode("\n", $failedResolve));
                else safeSend($chatId, "❌ No valid players found.");
                $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
                safeSend($chatId, "Please try again with /send.", mainMenu(true));
                exit;
            }

            $totalAmount = 0;
            $totalChunks = 0;
            $previewLines = [];
            foreach ($resolved as $idx => $r) {
                $totalAmount += $r['amount'];
                $nChunks = count(chunkAmounts($r['amount']));
                $totalChunks += $nChunks;
                $previewLines[] = ($idx + 1) . ". @" . escMd($r['username']) . " — *" . fmtShort($r['amount']) . "*" . ($nChunks > 1 ? " _($nChunks chunks)_" : "");
            }

            $previewText =
                "📋 *Multi-Send Preview*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "👤 *From:* @" . escMd($user['ll_username']) . "\n" .
                "👥 *Recipients:* " . count($resolved) . "\n" .
                "📦 *Total Chunks:* $totalChunks\n" .
                "💵 *Total Amount:* " . fmtFull($totalAmount) . " _(" . fmtShort($totalAmount) . ")_\n" .
                "🔐 *PIN:* " . (!empty($user['pin']) ? 'Included ✅' : 'Not set') . "\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                implode("\n", $previewLines) .
                (!empty($failedResolve) ? "\n\n⚠️ *Skipped:*\n" . implode("\n", $failedResolve) : "") .
                "\n━━━━━━━━━━━━━━━━━━━━━━━━━";

            $db->prepare("UPDATE pending_transfers SET step = 'confirm_multi', data = ? WHERE chat_id = ?")
               ->execute([json_encode($resolved), $chatId]);

            if ($waitId) safeEdit($chatId, $waitId, $previewText);
            else safeSend($chatId, $previewText);

            $multiKb = [
                'inline_keyboard' => [
                    [['text' => '✅ Start Batch', 'callback_data' => 'multi_confirm']],
                    [['text' => '❌ Cancel',      'callback_data' => 'multi_cancel']],
                ]
            ];
            safeSend($chatId, "Proceed with this batch?", $multiKb);
            exit;
        }
    }

    // Default response
    if ($loggedIn) {
        safeSend($chatId, "Use the menu below, or /help.", mainMenu(true));
    } else {
        safeSend($chatId, "👋 Tap *Login* to get started.", mainMenu(false));
    }
}
