<?php
require_once __DIR__ . '/config.php';

function tgCall(string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/$method";
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $raw = curl_exec($ch);
    $err = curl_error($ch);

    return json_decode($raw, true) ?: [];
}

function escMd(?string $text): string {
    if ($text === null || $text === '') return '';
    return preg_replace('/([_*`\[\]\\\\])/', '\\\\$1', $text);
}

function fmtShort(int $n): string {
    if ($n >= 1000000000000) return '₦' . round($n / 1000000000000, 2) . 'T';
    if ($n >= 1000000000)    return '₦' . round($n / 1000000000, 2) . 'B';
    if ($n >= 1000000)       return '₦' . round($n / 1000000, 1) . 'M';
    if ($n >= 1000)          return '₦' . round($n / 1000, 1) . 'K';
    return '₦' . number_format($n);
}

function fmtFull(int $n): string {
    return '₦' . number_format($n);
}

function progressBar(int $done, int $total, int $width = 12): string {
    if ($total <= 0) return str_repeat('░', $width);
    $filled = min((int)round(($done / $total) * $width), $width);
    return str_repeat('▓', $filled) . str_repeat('░', $width - $filled);
}

function parseAmount(string $raw): ?int {
    $clean = strtolower(str_replace(',', '', trim($raw)));
    if (!preg_match('/^([\d.]+)\s*([kmbt]?)$/', $clean, $m)) {
        return null;
    }
    $val = (float)$m[1];
    if ($val <= 0) return null;
    $mult = [
        'k' => 1000,
        'm' => 1000000,
        'b' => 1000000000,
        't' => 1000000000000, // 1 Trillion
    ];
    $multiplier = isset($m[2]) && isset($mult[$m[2]]) ? $mult[$m[2]] : 1;
    return (int)round($val * $multiplier);
}

function chunkAmounts(int $total): array {
    $chunks = [];
    $rem = $total;
    while ($rem > 0) {
        $chunk = min($rem, MAX_CHUNK);
        $chunks[] = $chunk;
        $rem -= $chunk;
    }
    return $chunks;
}

function safeSend(string $chatId, string $text, ?array $replyMarkup = null): array {
    $params = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($replyMarkup) $params['reply_markup'] = $replyMarkup;

    $res = tgCall('sendMessage', $params);
    if (!empty($res['ok'])) return $res;

    // Retry without Markdown if parsing fails
    $plain = preg_replace('/[*_`\[\]\\\\]/', '', $text);
    $params['text'] = $plain;
    unset($params['parse_mode']);
    return tgCall('sendMessage', $params);
}

function safeEdit(string $chatId, int $messageId, string $text, ?array $replyMarkup = null): array {
    $params = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($replyMarkup) $params['reply_markup'] = $replyMarkup;

    $res = tgCall('editMessageText', $params);
    if (!empty($res['ok'])) return $res;

    $plain = preg_replace('/[*_`\[\]\\\\]/', '', $text);
    $params['text'] = $plain;
    unset($params['parse_mode']);
    return tgCall('editMessageText', $params);
}

function wipeMessage(string $chatId, int $messageId): void {
    tgCall('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
}

function notifyChannel(string $text): void {
    $params = [
        'chat_id'    => NOTIFY_CHANNEL,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    $res = tgCall('sendMessage', $params);
    if (empty($res['ok'])) {
        $plain = preg_replace('/[*_`\[\]\\\\]/', '', $text);
        $params['text'] = $plain;
        unset($params['parse_mode']);
        tgCall('sendMessage', $params);
    }
}

function isSubscribed(string $userId): bool {
    if (defined('MASTER_ADMIN_ID') && (string)$userId === (string)MASTER_ADMIN_ID) {
        return true;
    }
    $res = tgCall('getChatMember', [
        'chat_id' => NOTIFY_CHANNEL,
        'user_id' => (int)$userId,
    ]);
    if (!empty($res['ok']) && !empty($res['result']['status'])) {
        $status = $res['result']['status'];
        return in_array($status, ['creator', 'administrator', 'member', 'restricted']);
    }
    return false;
}

function mainMenu(bool $loggedIn): array {
    if ($loggedIn) {
        return [
            'keyboard' => [
                [['text' => '💸 Send Money'],       ['text' => '💰 My Balance']],
                [['text' => '💼 My Accounts'],     ['text' => '🔐 Set/Update PIN']],
                [['text' => '🏠 Beautify House'],   ['text' => '🪩 Quilox Money Picker']],
                [['text' => '💎 Support LLBM'],     ['text' => '📋 Transfer History']],
                [['text' => '👤 My Account'],       ['text' => '🔑 Add Account']],
                [['text' => '🔒 Lock Bot'],         ['text' => '🔓 Logout']],
                [['text' => '🏠 Home']],
            ],
            'resize_keyboard' => true,
        ];
    }
    return [
        'keyboard' => [
            [['text' => '🔑 Login'],            ['text' => '💼 My Accounts']],
            [['text' => '🔒 Lock Bot'],         ['text' => '🏠 Home']],
        ],
        'resize_keyboard' => true,
    ];
}
