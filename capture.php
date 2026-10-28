<?php
// capture.php — 1-Click In-Game House Scanner Webhook
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bot.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['targetUser']) || empty($data['objects'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid house payload']);
    exit;
}

$target = strtolower(ltrim(trim($data['targetUser']), '@'));
$objects = $data['objects'];
$decor = $data['decor'] ?? ['wall' => 'sky', 'floor' => 'blackMarble'];
$home = $data['home'] ?? 'bananaIsland';
$chatId = $data['chatId'] ?? '';

$tpl = [
    'home'       => $home,
    'decor'      => $decor,
    'objects'    => $objects,
    'capturedAt' => date('c'),
    'owner'      => $target,
];

$tplDir = __DIR__ . '/templates';
if (!is_dir($tplDir)) {
    mkdir($tplDir, 0755, true);
}
file_put_contents("$tplDir/$target.json", json_encode($tpl, JSON_PRETTY_PRINT));

if ($chatId) {
    $count = count($objects);
    $text =
        "📸 *1-CLICK HOUSE SCANNER CAPTURED!*\n" .
        "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n" .
        "• *Source:* *@$target*\n" .
        "• *Property:* 🏰 *$home*\n" .
        "• *Elements Captured:* *$count luxury furniture items*\n" .
        "• *Wall:* `{$decor['wall']}` | *Floor:* `{$decor['floor']}`\n\n" .
        "🎉 *Ready to Clone:* You can now install this exact design into your own house with 1 tap!";

    $kb = [
        'inline_keyboard' => [
            [['text' => "🚀 Clone @$target's House Now ($count Items)", 'callback_data' => "house_apply_inspect_$target"]],
            [['text' => "🏠 House Menu", 'callback_data' => "house_menu"]],
        ]
    ];

    safeSend($chatId, $text, $kb);
}

echo json_encode(['ok' => true, 'count' => count($objects), 'user' => $target]);
