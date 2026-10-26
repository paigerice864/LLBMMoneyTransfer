<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/bot.php';



// ────────────────────────────────────────────────────────────────────────────
// Anti-Distribution Security Gate
// ────────────────────────────────────────────────────────────────────────────
function checkAuthorizationGate(string $chatId, string $tgUser, string $tgName): bool {
    global $db;
    if (!$db) {
        $db = getDb();
    }
    if (isUserAuthorized($db, $chatId)) {
        return true;
    }

    $status = getUserAuthStatus($db, $chatId);
    if ($status === 'unregistered') {
        $db->prepare("INSERT INTO authorized_users (chat_id, tg_username, tg_first_name, status, requested_at) VALUES (?, ?, ?, 'pending', NOW())")
           ->execute([$chatId, $tgUser, $tgName]);

        $userNotice =
            "🛡️ *HEISDAEMON SECURITY GATEWAY*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "⛔ *ACCESS AUTHORIZATION REQUIRED*\n\n" .
            "This bot is private proprietary software belonging exclusively to *HeisDaemon*.\n" .
            "To prevent unauthorized distribution, new devices and accounts must be approved by the Master Admin before access is granted.\n\n" .
            "📋 *Your Access Request Has Been Logged:*\n" .
            "• *Telegram ID:* `$chatId`\n" .
            "• *User:* @" . escMd($tgUser ?: 'none') . " (" . escMd($tgName) . ")\n" .
            "• *Status:* ⏳ *Pending Master Admin Approval*\n\n" .
            "🔔 HeisDaemon (@sinnerzadmin) has received your request. You will receive an alert the moment your account is activated!";
        safeSend($chatId, $userNotice);

        // Notify Master Admin
        if (defined('MASTER_ADMIN_ID') && MASTER_ADMIN_ID) {
            $adminAlert =
                "🚨 *NEW BOT ACCESS REQUEST*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "A new device/user is requesting authorization to run *HeisDaemon Bot*:\n\n" .
                "👤 *User:* @" . escMd($tgUser ?: 'none') . " (" . escMd($tgName) . ")\n" .
                "🆔 *Telegram Chat ID:* `$chatId`\n" .
                "📅 *Time:* " . date('Y-m-d H:i:s') . "\n\n" .
                "Tap an option below to grant or reject access:";
            $adminKb = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Approve Access', 'callback_data' => "adm_approve_$chatId"],
                        ['text' => '🚫 Deny Access',    'callback_data' => "adm_deny_$chatId"],
                    ]
                ]
            ];
            safeSend(MASTER_ADMIN_ID, $adminAlert, $adminKb);
        }
        return false;
    } elseif ($status === 'pending') {
        safeSend($chatId,
            "⏳ *Access Request Pending Approval*\n\n" .
            "Your device request (`$chatId`) is awaiting authorization from *HeisDaemon* (@sinnerzadmin).\n" .
            "You will be unlocked automatically the moment it is approved."
        );
        return false;
    } else { // denied
        safeSend($chatId,
            "🚫 *Access Denied*\n\n" .
            "Your access to *HeisDaemon Bot* has been declined by the Master Admin."
        );
        return false;
    }
}

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
        "To use *" . BOT_NAME . "*, you must subscribe to our official notification channel:\n👉 *" . NOTIFY_CHANNEL . "*\n\n" .
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
// Menus: House & Quilox
// ────────────────────────────────────────────────────────────────────────────
function renderHouseMenu(string $chatId, ?int $msgId = null): void {
    global $db;
    if (!$db) {
        $db = getDb();
    }
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
            [['text' => '🏠 Home Menu', 'callback_data' => 'nav_home']],
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
    if (!$db) {
        $db = getDb();
    }
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
            [
                ['text' => '🔄 Refresh Status',       'callback_data' => 'quilox_menu'],
                ['text' => '🏠 Home Menu',            'callback_data' => 'nav_home'],
            ],
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
if (!function_exists('startBatchWorker')) {
    function startBatchWorker(string $jobId): void {
        $script = __DIR__ . '/worker.php';
        $disabled = explode(',', ini_get('disable_functions') ?: '');
        $disabled = array_map('trim', $disabled);
        $hasExec = function_exists('exec') && !in_array('exec', $disabled);

        if ($hasExec) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                @pclose(popen("start /B php \"$script\" $jobId", "r"));
            } else {
                $phpBin = file_exists('/usr/local/php82/bin/php') ? '/usr/local/php82/bin/php' : 'php';
                @exec("$phpBin \"$script\" $jobId > /dev/null 2>&1 &");
            }
        }

        $url = 'https://lagoslife.hostedbyfps.com/worker.php?job_id=' . urlencode($jobId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        @curl_exec($ch);
        @curl_close($ch);
    }
}


function handleTelegramUpdate(array $update): void {
    global $db;
    if (!$db) {
        $db = getDb();
    }
    try {
// ────────────────────────────────────────────────────────────────────────────
// Handle Callback Queries
// ────────────────────────────────────────────────────────────────────────────
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $chatId = (string)$cb['message']['chat']['id'];
    $userId = (string)$cb['from']['id'];
    $tgUser = (string)($cb['from']['username'] ?? '');
    $tgName = (string)($cb['from']['first_name'] ?? '');
    $data = $cb['data'];
    $msgId = (int)$cb['message']['message_id'];

    tgCall('answerCallbackQuery', ['callback_query_id' => $cb['id']]);

    // Admin Access Management Callbacks
    if (strpos($data, 'adm_approve_') === 0) {
        if ((string)$userId !== (string)MASTER_ADMIN_ID) {
            safeEdit($chatId, $msgId, "🚫 Unauthorized: Master Admin only.");
            return;
        }
        $targetId = substr($data, strlen('adm_approve_'));
        $db->prepare("UPDATE authorized_users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE chat_id = ?")
           ->execute([$userId, $targetId]);

        safeEdit($chatId, $msgId, "✅ *ACCESS GRANTED*\n\nUser/device `$targetId` has been authorized by Master Admin.");
        
        safeSend($targetId,
            "🎉 *ACCESS APPROVED BY HEISDAEMON!*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Your account & device have been officially verified by the Master Admin.\n" .
            "You now have full executive access to *HeisDaemon Bot*!\n\n" .
            "👇 Tap *Login* below to connect your LagosLife account and get started:",
            mainMenu(false)
        );
        return;
    }

    if (strpos($data, 'adm_deny_') === 0) {
        if ((string)$userId !== (string)MASTER_ADMIN_ID) {
            safeEdit($chatId, $msgId, "🚫 Unauthorized: Master Admin only.");
            return;
        }
        $targetId = substr($data, strlen('adm_deny_'));
        $db->prepare("UPDATE authorized_users SET status = 'denied', approved_by = ?, approved_at = NOW() WHERE chat_id = ?")
           ->execute([$userId, $targetId]);

        safeEdit($chatId, $msgId, "🚫 *ACCESS DENIED*\n\nUser/device `$targetId` access request was declined.");
        safeSend($targetId, "🚫 *Access Denied*\n\nYour request to access HeisDaemon Bot was declined by Master Admin.");
        return;
    }

    // Security Gate check for callback actions
    if (!checkAuthorizationGate($chatId, $tgUser, $tgName)) {
        return;
    }

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
        return;
    }

    // Check subscription gate for all other actions
    if (!isSubscribed($userId)) {
        $prompt = getSubPrompt();
        safeSend($chatId, $prompt['text'], $prompt['kb']);
        return;
    }

    // Navigation & Home Callbacks
    if ($data === 'nav_home') {
        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        if ($user) {
            $bal = getBalance($chatId);
            $balLine = $bal !== null ? "\n💰 Balance: *" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "";
            $pinLine = !empty($user['pin']) ? "\n🔐 Transfer PIN: *Set ✅*" : "\n🔐 Transfer PIN: *Not set*";
            $homeText =
                "⚡ *HEISDAEMON EXECUTIVE SUITE* ⚡\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "Welcome back, *{$user['ll_name']}*! 👋\n" .
                "👤 *Account:* @" . escMd($user['ll_username']) . "\n" .
                "🆔 *Player ID:* `{$user['ll_user_id']}`" .
                $balLine . $pinLine . "\n\n" .
                "Select a feature or use the buttons below:";
            $homeKb = [
                'inline_keyboard' => [
                    [
                        ['text' => '💸 Send Money',           'callback_data' => 'nav_send_menu'],
                        ['text' => '💰 Wallet Balance',       'callback_data' => 'nav_balance'],
                    ],
                    [
                        ['text' => '🏠 House Beautification', 'callback_data' => 'house_menu'],
                        ['text' => '🪩 Quilox Cash Sweeper',  'callback_data' => 'quilox_menu'],
                    ],
                    [
                        ['text' => '💎 Support LLBM',         'callback_data' => 'mode_support_llbm'],
                        ['text' => '📋 History',              'callback_data' => 'nav_history'],
                    ],
                ]
            ];
            safeEdit($chatId, $msgId, $homeText, $homeKb);
        } else {
            safeEdit($chatId, $msgId,
                "⚡ *HEISDAEMON EXECUTIVE SUITE* ⚡\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "Send LagosLife money directly from your own account with automated multi-chunking up to *Trillions*.\n\n" .
                "👇 Tap *Login* below to connect your account:",
                [
                    'inline_keyboard' => [
                        [['text' => '🔑 Login to LagosLife', 'callback_data' => 'nav_login']],
                    ]
                ]
            );
        }
        return;
    }

    if ($data === 'nav_send_menu') {
        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        if (!$user) {
            safeEdit($chatId, $msgId, "🔒 Please login first.");
            return;
        }
        $pinStatus = !empty($user['pin']) ? "🔐 PIN: *Set ✅*" : "🔐 PIN: *Not set* _(use /setpin)_";
        $kb = [
            'inline_keyboard' => [
                [['text' => '📤 Single Recipient',      'callback_data' => 'mode_single']],
                [['text' => '👥 Multiple Recipients',   'callback_data' => 'mode_multi']],
                [['text' => '💎 Support LLBM Movement', 'callback_data' => 'mode_support_llbm']],
                [['text' => '🏠 Home Menu',              'callback_data' => 'nav_home']],
            ]
        ];
        safeEdit($chatId, $msgId, "💸 *Send Money*\n$pinStatus\n\nChoose transfer mode:", $kb);
        return;
    }

    if ($data === 'nav_balance') {
        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        if (!$user) { safeEdit($chatId, $msgId, "🔒 Please login first."); return; }
        $bal = getBalance($chatId);
        $balText = "💰 *Wallet Balance*\n\n👤 Account: *@{$user['ll_username']}*\n━━━━━━━━━━━━━━━━━━━━━━━━━\n💵 *" . fmtFull($bal ?? 0) . "*\n    _(" . fmtShort($bal ?? 0) . ")_";
        safeEdit($chatId, $msgId, $balText, [
            'inline_keyboard' => [
                [['text' => '🔄 Refresh',    'callback_data' => 'nav_balance']],
                [['text' => '💸 Send Money', 'callback_data' => 'nav_send_menu']],
                [['text' => '🏠 Home Menu',  'callback_data' => 'nav_home']],
            ]
        ]);
        return;
    }

    if ($data === 'nav_history') {
        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        if (!$user) { safeEdit($chatId, $msgId, "🔒 Please login first."); return; }
        $histStmt = $db->prepare("SELECT * FROM transfers WHERE chat_id = ? ORDER BY id DESC LIMIT 10");
        $histStmt->execute([$chatId]);
        $rows = $histStmt->fetchAll();

        if (empty($rows)) {
            safeEdit($chatId, $msgId, "📭 No transfers yet.", [
                'inline_keyboard' => [
                    [['text' => '🏠 Home Menu', 'callback_data' => 'nav_home']]
                ]
            ]);
            return;
        }

        $lines = [];
        foreach ($rows as $i => $r) {
            $icon = $r['status'] === 'success' ? '✅' : '❌';
            $d = date('d M', strtotime($r['created_at']));
            $lines[] = "$icon " . ($i + 1) . ". *" . fmtShort((int)$r['amount']) . "* → @" . escMd($r['recipient_username']) . " _($d)_";
        }

        safeEdit($chatId, $msgId, "📋 *Last " . count($rows) . " Transfers*\n\n" . implode("\n", $lines), [
            'inline_keyboard' => [
                [['text' => '💸 Send Money', 'callback_data' => 'nav_send_menu']],
                [['text' => '🏠 Home Menu',  'callback_data' => 'nav_home']],
            ]
        ]);
        return;
    }

    if ($data === 'nav_login') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, step, updated_at) VALUES (?, 'login_username', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId, "🔑 *Login to LagosLife*\n\nEnter your *LagosLife username*:\n_(Or type /cancel to go back)_");
        return;
    }

    if ($data === 'setup_pin') {
        $db->prepare("UPDATE pending_transfers SET step = 'enter_pin' WHERE chat_id = ?")->execute([$chatId]);
        safeEdit($chatId, $msgId, "🔐 *Transfer PIN Setup*\n\nEnter your *transfer PIN*:\n\n_It will be deleted from chat immediately and stored securely for transfers._");
        return;
    }

    if ($data === 'skip_pin') {
        safeEdit($chatId, $msgId, "✅ No PIN set. You can add one anytime with *🔐 Set/Update PIN* or /setpin.");
        safeSend($chatId, "What would you like to do?", mainMenu(true));
        return;
    }

    if ($data === 'cancel_batch') {
        $db->prepare("UPDATE batch_jobs SET status = 'cancelled' WHERE chat_id = ? AND status = 'pending'")->execute([$chatId]);
        safeEdit($chatId, $msgId, "🚫 Batch cancel requested.");
        return;
    }

    if ($data === 'mode_single') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, updated_at) VALUES (?, 'single', 'recipient', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId,
            "📤 *Single Recipient Transfer*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Enter the recipient's *LagosLife username*:\n" .
            "💡 _Examples: `@wedekaov`, `@phinnybabe`_",
            [
                'inline_keyboard' => [
                    [['text' => '🔙 Back / 🏠 Home', 'callback_data' => 'send_cancel']],
                ]
            ]
        );
        return;
    }

    if ($data === 'mode_multi') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, updated_at) VALUES (?, 'multi', 'multi_paste', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId,
            "👥 *Multiple Recipients Payroll*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Paste your recipient list — one per line:\n\n" .
            "`@username1  amount\n@username2  amount\n@username3  amount`\n\n" .
            "*Examples:*\n" .
            "`@alice 50B\n@bob 100B\n@charlie 1T`\n\n" .
            "Supports `K`, `M`, `B`, `T` or plain numbers.\n" .
            "Amounts over ₦100B will automatically auto-chunk with 32s cooldown.",
            [
                'inline_keyboard' => [
                    [['text' => '🔙 Back / 🏠 Home', 'callback_data' => 'multi_cancel']],
                ]
            ]
        );
        return;
    }

    if ($data === 'mode_support_llbm') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, recipient_username, recipient_player_id, note, updated_at) VALUES (?, 'single', 'amount', ?, ?, 'Support LLBM Movement 💎', NOW())")
           ->execute([$chatId, LLBM_MOVEMENT_USERNAME, LLBM_MOVEMENT_PLAYER_ID]);
        safeEdit($chatId, $msgId,
            "💎 *Support LLBM Movement*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "🎯 *Beneficiary:* *" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")\n" .
            "🆔 *Player ID:* `" . LLBM_MOVEMENT_PLAYER_ID . "`\n\n" .
            "How much funds would you like to contribute?\n\n" .
            "💡 _Examples:_ `100K`, `5M`, `100B`, `50B`, `1T`\n" .
            "📦 _Amounts over ₦100B will automatically be sent in batches of ₦100B with the standard 32s cooldown._",
            [
                'inline_keyboard' => [
                    [['text' => '🔙 Back / 🏠 Home', 'callback_data' => 'send_cancel']],
                ]
            ]
        );
        return;
    }

    if ($data === 'send_cancel' || $data === 'multi_cancel') {
        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        safeEdit($chatId, $msgId, "❌ Transfer cancelled.");
        safeSend($chatId, "What next?", mainMenu(true));
        return;
    }

    if ($data === 'send_confirm') {
        $pStmt = $db->prepare("SELECT * FROM pending_transfers WHERE chat_id = ?");
        $pStmt->execute([$chatId]);
        $pending = $pStmt->fetch();

        if (!$pending || empty($pending['recipient_player_id']) || empty($pending['amount'])) {
            safeEdit($chatId, $msgId, "⚠️ Session expired. Please start again with /send.");
            return;
        }

        $user = $db->query("SELECT * FROM bot_users WHERE chat_id = '$chatId' AND is_authenticated = 1")->fetch();
        $isLLBM = strtolower($pending['recipient_username']) === strtolower(LLBM_MOVEMENT_USERNAME);
        $note = $pending['note'] ?: ($isLLBM ? 'Support LLBM Movement 💎' : ('Sent via ' . BOT_NAME));
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
                        "🤖 *" . BOT_NAME . "*"
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
                        "🤖 *" . BOT_NAME . "*"
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
        return;
    }

    if ($data === 'multi_confirm') {
        $pStmt = $db->prepare("SELECT * FROM pending_transfers WHERE chat_id = ?");
        $pStmt->execute([$chatId]);
        $pending = $pStmt->fetch();

        if (!$pending || empty($pending['data'])) {
            safeEdit($chatId, $msgId, "⚠️ Session expired. Please start again with /send.");
            return;
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
            $db->prepare("INSERT INTO batch_chunks (job_id, chunk_index, recipient_username, recipient_player_id, amount, note, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
               ->execute([$jobId, $idx, $c['username'], $c['playerId'], $c['amount'], ('Sent via ' . BOT_NAME)]);
        }

        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        startBatchWorker($jobId);
        return;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // House & Beautification Callbacks
    // ────────────────────────────────────────────────────────────────────────────
    if ($data === 'house_menu') {
        renderHouseMenu($chatId, $msgId);
        return;
    }

    if (strpos($data, 'house_clone_tpl_') === 0) {
        $tplKey = substr($data, strlen('house_clone_tpl_'));
        $tpls = getHouseTemplates();
        if (!isset($tpls[$tplKey])) {
            safeEdit($chatId, $msgId, "❌ Template not found.");
            return;
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
                [
                    ['text' => '🔙 Back to House Menu', 'callback_data' => 'house_menu'],
                    ['text' => '🏠 Home Menu',          'callback_data' => 'nav_home'],
                ],
            ]
        ];
        safeEdit($chatId, $msgId, $text, $kb);
        return;
    }

    if (strpos($data, 'house_apply_') === 0) {
        $tplKey = substr($data, strlen('house_apply_'));
        $design = loadHouseTemplate($tplKey);
        $tpls = getHouseTemplates();
        $cost = $tpls[$tplKey]['cost'] ?? 0;

        if (!$design) {
            safeEdit($chatId, $msgId, "❌ Template design file not found.");
            return;
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
                    [
                        ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                        ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                    ],
                ]
            ]);

            notifyChannel(
                "🏰 *[LUXURY RESIDENCE BEAUTIFIED]*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "• *Owner:* @" . escMd($user['ll_username'] ?? 'Landlord') . "\n" .
                "• *Design:* *" . escMd($tpls[$tplKey]['name']) . "*\n" .
                "• *Installed:* *{$res['itemsCount']} luxury elements*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
                "🤖 *" . BOT_NAME . "*"
            );
        } else {
            safeEdit($chatId, $msgId, "❌ *Failed to beautify house:*\n" . ($res['error'] ?? 'Unknown error'), [
                'inline_keyboard' => [
                    [['text' => '🔄 Try Again', 'callback_data' => "house_clone_tpl_$tplKey"]],
                    [
                        ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                        ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                    ],
                ]
            ]);
        }
        return;
    }

    if ($data === 'house_custom') {
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, updated_at) VALUES (?, 'house', 'inspect_user', NOW())")->execute([$chatId]);
        safeEdit($chatId, $msgId,
            "✍️ *Clone Player House by Username*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Please send the *LagosLife @username* of the player whose house layout you want to inspect & clone:\n\n" .
            "💡 _Examples: `@wedekaov`, `@phinnybabe`, `@kimoflele`_",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Back to House Menu', 'callback_data' => 'house_menu'],
                        ['text' => '🏠 Home Menu',          'callback_data' => 'nav_home'],
                    ],
                ]
            ]
        );
        return;
    }

    if (strpos($data, 'house_apply_inspect_') === 0) {
        $targetUser = substr($data, strlen('house_apply_inspect_'));
        $insp = inspectPlayerHouse($chatId, $targetUser);

        if (!$insp['ok']) {
            safeEdit($chatId, $msgId, "❌ " . ($insp['error'] ?? 'Could not clone house.'));
            return;
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
                        [
                            ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                            ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                        ],
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
                "🤖 *" . BOT_NAME . "*"
            );
        } else {
            safeEdit($chatId, $msgId, "❌ Failed to clone house: " . ($res['error'] ?? 'Unknown error'), [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                        ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                    ],
                ]
            ]);
        }
        return;
    }

    if (strpos($data, 'house_recheck_') === 0) {
        $parts = explode('_', substr($data, strlen('house_recheck_')), 2);
        $targetUser = $parts[0] ?? '';
        $homeId = $parts[1] ?? '';

        safeEdit($chatId, $msgId, "⏳ *Checking if @$targetUser opened their front door in LagosLife…*");
        $insp = pollPlayerHouseDoor($chatId, $targetUser, $homeId);

        if ($insp['ok']) {
            $count = $insp['count'];
            $prop = $insp['home'];
            $quoteText =
                "🎉 *DOOR OPENED & LAYOUT CAPTURED!*\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "• *Source House:* *@$targetUser*\n" .
                "• *Property Type:* 🏰 *$prop*\n" .
                "• *Furniture Items:* *$count Total Elements*\n" .
                "• *Wall Decor:* `{$insp['decor']['wall']}`\n" .
                "• *Floor Decor:* `{$insp['decor']['floor']}`\n\n" .
                "🗑️ *Clean Slate Guarantee:*\n" .
                "⚠️ *All old furniture items will be removed and replaced* with this exact layout.\n\n" .
                "_Tap below to confirm and clone this house design now!_";

            $kb = [
                'inline_keyboard' => [
                    [['text' => "🚀 Confirm & Clone House ($count Items)", 'callback_data' => "house_apply_inspect_$targetUser"]],
                    [
                        ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                        ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                    ],
                ]
            ];
            safeEdit($chatId, $msgId, $quoteText, $kb);
        } else {
            safeEdit($chatId, $msgId,
                "⏳ *Door is still closed.*\n\n" .
                "The knock alert is on @$targetUser's screen.\n" .
                "Ask @$targetUser to tap *'Let In'*, then tap *[🚪 Check Door Again]* below!",
                [
                    'inline_keyboard' => [
                        [['text' => '🚪 Check Door Again',        'callback_data' => "house_recheck_{$targetUser}_{$homeId}"]],
                        [['text' => '📸 1-Click Browser Scanner', 'callback_data' => 'house_bookmarklet']],
                        [
                            ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                            ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                        ],
                    ]
                ]
            );
        }
        return;
    }

    if ($data === 'house_bookmarklet') {
        $bm = "javascript:(async function(){try{var p=new URLSearchParams(window.location.search);var u=p.get('visit')||prompt('📸 Enter username of house owner you are visiting:');if(!u)return;u=u.replace(/^@/,'').trim();var mR=await fetch('/api/homes/u/'+encodeURIComponent(u));if(!mR.ok){alert('❌ Could not locate home of @'+u);return;}var m=await mR.json();var rR=await fetch('/api/homes/'+encodeURIComponent(m.id));if(!rR.ok){alert('⚠️ You must be inside the house of @'+u+' to capture their layout!');return;}var r=await rR.json();if((!r.objects||!r.objects.length)&&(!r.up||!r.up.length)){alert('⚠️ No furniture objects found in home of @'+u);return;}var allObjs=[...(r.objects||[]),...((r.up||[]).map(function(o){return Object.assign({},o,{floor:1});}))];var res=await fetch('https://lagoslife.hostedbyfps.com/capture.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({targetUser:u,objects:allObjs,decor:r.decor,home:m.home||r.home,chatId:'" . $chatId . "'})});var d=await res.json();alert('🎉 SUCCESS! Captured '+allObjs.length+' items from @'+u+'! Check your Telegram bot to clone this layout with 1 tap!');}catch(e){alert('Error: '+e.message);}})();";

        $text =
            "📸 *1-CLICK IN-GAME HOUSE SCANNER*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "Visiting any player's house in your game browser on [lagoslife.app](https://lagoslife.app)? You can scan and capture their layout in 1 click!\n\n" .
            "*How to use:*\n" .
            "1. Create a browser bookmark named `📸 Copy House`\n" .
            "2. Paste the snippet below into the URL field\n" .
            "3. Click it while visiting any home on LagosLife!\n" .
            "4. Your Telegram bot will beep immediately with the 1-Tap *[🚀 Clone House]* button!\n\n" .
            "👇 *Copy this Bookmarklet Code:*";

        safeEdit($chatId, $msgId, $text, [
            'inline_keyboard' => [
                [
                    ['text' => '🔙 Back to House Menu', 'callback_data' => 'house_menu'],
                    ['text' => '🏠 Home Menu',          'callback_data' => 'nav_home'],
                ],
            ]
        ]);
        safeSend($chatId, "`$bm`");
        return;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Quilox Callbacks
    // ────────────────────────────────────────────────────────────────────────────
    if ($data === 'quilox_menu') {
        renderQuiloxMenu($chatId, $msgId);
        return;
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
                [
                    ['text' => '🪩 Quilox Menu',       'callback_data' => 'quilox_menu'],
                    ['text' => '🏠 Home Menu',         'callback_data' => 'nav_home'],
                ],
            ]
        ]);
        return;
    }

    if ($data === 'quilox_teleport') {
        $res = teleportToQuilox($chatId);
        safeEdit($chatId, $msgId, "📍 *" . ($res['message'] ?? 'Character at Quilox!') . "*", [
            'inline_keyboard' => [
                [['text' => '⚡ Sweep Dance Floor Now', 'callback_data' => 'quilox_sweep']],
                [
                    ['text' => '🪩 Quilox Menu',           'callback_data' => 'quilox_menu'],
                    ['text' => '🏠 Home Menu',             'callback_data' => 'nav_home'],
                ],
            ]
        ]);
        return;
    }

    if ($data === 'quilox_claim') {
        requestAsUser($chatId, '/api/claim', 'POST', []);
        $bal = getBalance($chatId);
        safeEdit($chatId, $msgId, "💰 *Cash Claimed!*\n\nAll pending banknotes deposited into your wallet balance.\nBalance: *" . fmtFull($bal ?? 0) . "*", [
            'inline_keyboard' => [
                [
                    ['text' => '🪩 Quilox Menu', 'callback_data' => 'quilox_menu'],
                    ['text' => '🏠 Home Menu',   'callback_data' => 'nav_home'],
                ],
            ]
        ]);
        return;
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Handle Messages
// ────────────────────────────────────────────────────────────────────────────
if (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = (string)$msg['chat']['id'];
    $userId = (string)$msg['from']['id'];
    $tgUser = (string)($msg['from']['username'] ?? '');
    $tgName = (string)($msg['from']['first_name'] ?? '');
    $text = trim($msg['text'] ?? '');
    $msgId = (int)$msg['message_id'];

    // Anti-Distribution Security Gate check
    if (!checkAuthorizationGate($chatId, $tgUser, $tgName)) {
        return;
    }

    // Mandatory Subscription Gate
    if (!isSubscribed($userId)) {
        $prompt = getSubPrompt();
        safeSend($chatId, $prompt['text'], $prompt['kb']);
        return;
    }

    $userStmt = $db->prepare("SELECT * FROM bot_users WHERE chat_id = ? AND is_authenticated = 1");
    $userStmt->execute([$chatId]);
    $user = $userStmt->fetch();
    $loggedIn = !empty($user);

    // /start
    if ($text === '/start') {
        if ($loggedIn) {
            $bal = getBalance($chatId);
            $balLine = $bal !== null ? "\n💰 *Balance:* *" . fmtFull($bal) . "* _(" . fmtShort($bal) . ")_" : "";
            $pinLine = !empty($user['pin']) ? "\n🔐 *PIN Security:* *Protected ✅*" : "\n🔐 *PIN Security:* *Not set* — _use /setpin_";

            safeSend($chatId,
                "⚡ *HEISDAEMON EXECUTIVE SUITE* ⚡\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "Welcome back, *{$user['ll_name']}*! 👋\n" .
                "👤 *Account:* @" . escMd($user['ll_username']) . "\n" .
                "🆔 *Player ID:* `{$user['ll_user_id']}`" .
                $balLine . $pinLine . "\n\n" .
                "🌟 *ACTIVE CAPABILITIES:*\n" .
                "• 💸 *Mega & Trillion Transfers:* Send up to *Trillions (₦T)* with auto ₦100B chunking & 32s cooldown timing\n" .
                "• 👥 *Multi-Recipient Payroll:* Disperse funds to dozens of players simultaneously\n" .
                "• 🏰 *Luxury House Beautification:* Clone celebrity mansions & custom residences with clean-slate placement\n" .
                "• 📸 *1-Click Browser Scanner:* Instant layout copy bookmarklet for in-game visits\n" .
                "• 🪩 *Quilox Cash Sweeper:* Auto-grab sprayed cash from the nightclub dance floor\n" .
                "• 🛡️ *Proprietary Security Gate:* Master Admin device & account approval protection\n" .
                "• 🔐 *Transfer PIN Protection:* One-time entry with instant chat message destruction\n\n" .
                "👇 *Choose an option from the menu below:*",
                mainMenu(true)
            );
        } else {
            safeSend($chatId,
                "⚡ *HEISDAEMON EXECUTIVE SUITE* ⚡\n" .
                "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                "Welcome to the ultimate high-frequency financial & lifestyle automation system for *LagosLife*.\n\n" .
                "🌟 *ELITE FEATURES:*\n" .
                "• 💸 *Mega & Trillion Transfers:* Send Millions, Billions, or *Trillions (₦T)* seamlessly\n" .
                "• 📦 *Smart ₦100B Auto-Chunking:* Bypasses transfer limits with automatic 32s cooldown timing\n" .
                "• 👥 *Multi-Recipient Blitz:* Disperse payroll to unlimited users in one message\n" .
                "• 🏰 *Celebrity House Cloning:* Instantly apply luxury mansions (Banana Island, Lekki Palaces)\n" .
                "• 📸 *1-Click Browser Scanner:* Copy any player's house layout in real-time\n" .
                "• 🪩 *Quilox Floor Cash Sweeper:* Scoop up sprayed nightclub cash straight to your wallet\n" .
                "• 🛡️ *Device Security Gate:* Master Admin authorization required for all devices\n" .
                "• 🔐 *PIN Protection:* Automatic secure PIN memory with immediate chat wiping\n\n" .
                "👇 *Tap Login below to authenticate your LagosLife account:*",
                mainMenu(false)
            );
        }
        return;
    }

    // /help
    if ($text === '/help') {
        safeSend($chatId,
            "📖 *HEISDAEMON EXECUTIVE MANUAL*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
            "🎮 *Account & Auth:*\n" .
            "/login — Connect your LagosLife account\n" .
            "/account — Your account, Player ID & session info\n" .
            "/balance — Live wallet balance\n" .
            "/setpin — Store/update your transfer PIN\n" .
            "/clearpin — Remove stored PIN\n" .
            "/logout — Disconnect account\n\n" .
            "💸 *Money Operations:*\n" .
            "/send — Mega transfer (Single or Multi-Recipient up to Trillions)\n" .
            "/support — Direct contribution to LLBM Movement\n" .
            "/history — Recent 10 transfers & receipts\n\n" .
            "🏰 *Luxury Lifestyle:*\n" .
            "/beautify or /house — Luxury house cloning & catalog\n" .
            "/quilox — Quilox nightclub floor cash sweeper & teleporter\n" .
            "/home — Return to executive dashboard\n\n" .
            "⚡ *Transfer Engine Rules:*\n" .
            "• Transfers split into *₦100B* max chunks\n" .
            "• Precise *32s cooldown* handled automatically between chunks\n" .
            "• Supports `K`, `M`, `B`, and `T` (Trillions)\n" .
            "• Live receipts broadcast to " . NOTIFY_CHANNEL,
            mainMenu($loggedIn)
        );
        return;
    }

    // /home, /cancel, /back
    if (in_array(strtolower($text), ['/home', '🏠 home', '🏠 home menu', 'home', '/cancel', 'cancel', '❌ cancel', '/back', 'back', '🔙 back', '🔙 back / 🏠 home'])) {
        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        safeSend($chatId, "⚡ *HEISDAEMON EXECUTIVE SUITE*", mainMenu($loggedIn));
        return;
    }

    // /login or 🔑 Login
    if ($text === '/login' || $text === '🔑 Login') {
        if ($loggedIn) {
            safeSend($chatId, "✅ Already logged in as *@{$user['ll_username']}*.\nUse /logout to switch accounts.", mainMenu(true));
            return;
        }
        $db->prepare("REPLACE INTO pending_transfers (chat_id, step, updated_at) VALUES (?, 'login_username', NOW())")->execute([$chatId]);
        safeSend($chatId, "🔑 *Login to LagosLife*\n\nEnter your *LagosLife username*:\n_(Or send /home to return)_", ['remove_keyboard' => true]);
        return;
    }

    // /logout or 🔓 Logout
    if ($text === '/logout' || $text === '🔓 Logout') {
        $db->prepare("UPDATE bot_users SET is_authenticated = 0, ll_session_cookie = NULL, pin = NULL, updated_at = NOW() WHERE chat_id = ?")->execute([$chatId]);
        $db->prepare("DELETE FROM pending_transfers WHERE chat_id = ?")->execute([$chatId]);
        safeSend($chatId, "👋 Logged out. Your session and PIN have been cleared.", mainMenu(false));
        return;
    }

    // /balance or 💰 My Balance
    if ($text === '/balance' || $text === '💰 My Balance') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
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
        return;
    }

    // /account or 👤 My Account
    if ($text === '/account' || $text === '👤 My Account') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
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
        return;
    }

    // /history or 📋 Transfer History
    if ($text === '/history' || $text === '📋 Transfer History') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        $histStmt = $db->prepare("SELECT * FROM transfers WHERE chat_id = ? ORDER BY id DESC LIMIT 10");
        $histStmt->execute([$chatId]);
        $rows = $histStmt->fetchAll();

        if (empty($rows)) {
            safeSend($chatId, "📭 No transfers yet.", mainMenu(true));
            return;
        }

        $lines = [];
        foreach ($rows as $i => $r) {
            $icon = $r['status'] === 'success' ? '✅' : '❌';
            $d = date('d M', strtotime($r['created_at']));
            $lines[] = "$icon " . ($i + 1) . ". *" . fmtShort((int)$r['amount']) . "* → @" . escMd($r['recipient_username']) . " _($d)_";
        }

        safeSend($chatId, "📋 *Last " . count($rows) . " Transfers*\n\n" . implode("\n", $lines), mainMenu(true));
        return;
    }

    // /setpin or 🔐 Set/Update PIN
    if ($text === '/setpin' || $text === '🔐 Set/Update PIN') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        $db->prepare("REPLACE INTO pending_transfers (chat_id, step, updated_at) VALUES (?, 'enter_pin', NOW())")->execute([$chatId]);
        $curr = !empty($user['pin']) ? "_You have a PIN set — enter new PIN to replace it._" : "_No PIN set yet._";
        safeSend($chatId, "🔐 *Set Transfer PIN*\n\n$curr\n\nEnter your PIN now:\n_(Message will be deleted immediately)_", ['remove_keyboard' => true]);
        return;
    }

    // /clearpin
    if ($text === '/clearpin') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        $db->prepare("UPDATE bot_users SET pin = NULL WHERE chat_id = ?")->execute([$chatId]);
        safeSend($chatId, "🔓 Transfer PIN cleared.", mainMenu(true));
        return;
    }

    // /support or 💎 Support LLBM
    if ($text === '/support' || $text === '💎 Support LLBM') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        $db->prepare("REPLACE INTO pending_transfers (chat_id, mode, step, recipient_username, recipient_player_id, note, updated_at) VALUES (?, 'single', 'amount', ?, ?, 'Support LLBM Movement 💎', NOW())")
           ->execute([$chatId, LLBM_MOVEMENT_USERNAME, LLBM_MOVEMENT_PLAYER_ID]);

        safeSend($chatId,
            "💎 *Support LLBM Movement*\n" .
            "━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
            "🎯 *Beneficiary:* *" . LLBM_MOVEMENT_NAME . "* (@" . LLBM_MOVEMENT_USERNAME . ")\n" .
            "🆔 *Player ID:* `" . LLBM_MOVEMENT_PLAYER_ID . "`\n\n" .
            "How much funds would you like to contribute?\n\n" .
            "💡 _Examples:_ `100K`, `5M`, `100B`, `50B`, `1T`\n" .
            "📦 _Amounts over ₦100B will automatically be sent in batches of ₦100B with the standard 32s cooldown._",
            ['remove_keyboard' => true]
        );
        return;
    }

    // /send or 💸 Send Money
    if ($text === '/send' || $text === '💸 Send Money') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        $pinStatus = !empty($user['pin']) ? "🔐 PIN: *Set ✅*" : "🔐 PIN: *Not set* _(use /setpin)_";

        $kb = [
            'inline_keyboard' => [
                [['text' => '📤 Single Recipient',      'callback_data' => 'mode_single']],
                [['text' => '👥 Multiple Recipients',   'callback_data' => 'mode_multi']],
                [['text' => '💎 Support LLBM Movement', 'callback_data' => 'mode_support_llbm']],
                [['text' => '🏠 Home Menu',              'callback_data' => 'nav_home']],
            ]
        ];

        safeSend($chatId, "💸 *Send Money*\n$pinStatus\n\nChoose mode:", $kb);
        return;
    }

    // /beautify or 🏠 Beautify House or /house or /clone
    if ($text === '/beautify' || $text === '🏠 Beautify House' || $text === '/house' || $text === '/clone') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        renderHouseMenu($chatId);
        return;
    }

    // /quilox or 🪩 Quilox Money Picker or /picker or /moneypicker
    if ($text === '/quilox' || $text === '🪩 Quilox Money Picker' || $text === '/picker' || $text === '/moneypicker') {
        if (!$loggedIn) { safeSend($chatId, "🔒 Please /login first.", mainMenu(false)); return; }
        renderQuiloxMenu($chatId);
        return;
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
            return;
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
                        [
                            ['text' => "🔙 House Menu", 'callback_data' => "house_menu"],
                            ['text' => "🏠 Home Menu",  'callback_data' => "nav_home"],
                        ],
                    ]
                ];
                if ($waitId) safeEdit($chatId, $waitId, $quoteText, $kb);
                else safeSend($chatId, $quoteText, $kb);
            } elseif (!empty($insp['knocked'])) {
                $homeId = $insp['homeId'];
                $prop = $insp['home'];
                $knockMsg =
                    "🚪 *KNOCK DELIVERED TO @$target'S FRONT DOOR!*\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "• *Property:* 🏰 *$prop* ({$insp['area']})\n" .
                    "• *Status:* 🔔 *Knock notification sent to @$target's screen in LagosLife!*\n\n" .
                    "👉 *Next Steps:*\n" .
                    "1. @$target has a *Knock at Door* popup on their game screen.\n" .
                    "2. Tell @$target in game: _\"Please tap 'Let In' on your screen!\"_\n" .
                    "3. Once they tap it, click *[🚪 Check Door / Capture Now]* below!\n\n" .
                    "💡 _Or, if you are visiting them right now, tap 📸 1-Click Scanner!_";

                $kb = [
                    'inline_keyboard' => [
                        [['text' => '🚪 Check Door / Capture Now', 'callback_data' => "house_recheck_{$target}_{$homeId}"]],
                        [['text' => '📸 1-Click Browser Scanner',  'callback_data' => 'house_bookmarklet']],
                        [['text' => '🏰 Clone @wedekaov Mansion',   'callback_data' => 'house_clone_tpl_wedekaov']],
                        [
                            ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                            ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                        ],
                    ]
                ];
                if ($waitId) safeEdit($chatId, $waitId, $knockMsg, $kb);
                else safeSend($chatId, $knockMsg, $kb);
            } else {
                $reason = !empty($insp['not_home'])
                    ? "🚶‍♂️ *@$target is currently not inside their house* (roaming in Lagos streets/clubs)."
                    : ($insp['error'] ?? 'Could not access property layout.');

                $errText =
                    "📍 *$reason*\n" .
                    "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
                    "LagosLife protects private house coordinates when the owner is not at home.\n\n" .
                    "💡 *How to clone:* Ask @$target to step inside their house, or use our 1-Click Scanner, or install a verified master mansion below:";

                $kb = [
                    'inline_keyboard' => [
                        [['text' => '🏰 Clone @wedekaov Mansion (153 Items)', 'callback_data' => 'house_clone_tpl_wedekaov']],
                        [['text' => '📸 1-Click Browser Scanner',  'callback_data' => 'house_bookmarklet']],
                        [['text' => '✍️ Try Another Username',      'callback_data' => 'house_custom']],
                        [
                            ['text' => '🔙 House Menu', 'callback_data' => 'house_menu'],
                            ['text' => '🏠 Home Menu',  'callback_data' => 'nav_home'],
                        ],
                    ]
                ];
                if ($waitId) safeEdit($chatId, $waitId, $errText, $kb);
                else safeSend($chatId, $errText, $kb);
            }
            return;
        }

        if ($step === 'login_username') {
            $db->prepare("UPDATE pending_transfers SET step = 'login_password', recipient_username = ? WHERE chat_id = ?")->execute([$text, $chatId]);
            safeSend($chatId, "Got it! Now enter your *LagosLife password*:\n_(Message will be deleted from chat immediately)_");
            return;
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
                return;
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
                "🤖 *" . BOT_NAME . "*"
            );
            return;
        }

        if ($step === 'recipient') {
            $raw = ltrim(trim($text), '@');
            $wait = safeSend($chatId, "🔍 Looking up *@$raw*…");
            $waitId = $wait['result']['message_id'] ?? 0;
            $player = resolvePlayer($chatId, $raw);

            if (!$player) {
                if ($waitId) safeEdit($chatId, $waitId, "❌ Player *@$raw* not found on LagosLife.\nCheck spelling and try again.");
                else safeSend($chatId, "❌ Player not found.");
                return;
            }

            $db->prepare("UPDATE pending_transfers SET step = 'amount', recipient_username = ?, recipient_player_id = ? WHERE chat_id = ?")
               ->execute([$player['username'], $player['id'], $chatId]);

            $foundText = "✅ Found: *{$player['name']}* (@{$player['username']})\n\nEnter the *amount* to send:\n💡 e.g. `500K`, `5M`, `100B`, `100B`, `1T`\n_(Over ₦100B auto-splits into chunks with 32s cooldown)_";
            if ($waitId) safeEdit($chatId, $waitId, $foundText);
            else safeSend($chatId, $foundText);
            return;
        }

        if ($step === 'amount') {
            $amount = parseAmount($text);
            if (!$amount || $amount < 1) {
                safeSend($chatId, "⚠️ Invalid amount. Try `5M`, `100B`, `1T`, `500000`.");
                return;
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
            return;
        }

        if ($step === 'note') {
            $isLLBM = strtolower($pending['recipient_username'] ?? '') === strtolower(LLBM_MOVEMENT_USERNAME);
            $defaultNote = $isLLBM ? 'Support LLBM Movement 💎' : ('Sent via ' . BOT_NAME);
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
            return;
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
                safeSend($chatId, "⚠️ *Could not parse any entries.*\n\nFormat: `@username amount` — one per line.\nExample:\n`@alice 50B\n@bob 100B\n@charlie 1T`");
                return;
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
                return;
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
            return;
        }
    }

    // Default response
    if ($loggedIn) {
        safeSend($chatId, "Use the menu below, or /help.", mainMenu(true));
    } else {
        safeSend($chatId, "👋 Tap *Login* to get started.", mainMenu(false));
    }
}

    } catch (Throwable $e) {
        $err = "[" . date('Y-m-d H:i:s') . "] Handler Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        @file_put_contents(__DIR__ . '/handler_errors.log', $err, FILE_APPEND);
        error_log("HeisDaemon Update Handler Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
}

if (!defined('IN_POLLER')) {
    $input = file_get_contents('php://input');
    if (!empty($input)) { @file_put_contents(__DIR__ . '/webhook_traffic.log', '[' . date('Y-m-d H:i:s') . '] ' . substr($input, 0, 300) . PHP_EOL, FILE_APPEND); }
    if (empty($input)) {
        $input = file_get_contents('php://stdin');
    }
    $update = json_decode($input, true);
    if ($update) {
        handleTelegramUpdate($update);
    } else {
        echo "OK - HeisDaemon Bot Webhook Ready";
    }
}
