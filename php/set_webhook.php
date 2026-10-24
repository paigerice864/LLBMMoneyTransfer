<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot.php';

$domain = 'https://lagoslife.hostedbyfps.com';
$webhookUrl = $domain . '/webhook.php';

echo "Setting Webhook for " . BOT_USERNAME . " to: $webhookUrl\n<br>";

$res = tgCall('setWebhook', [
    'url' => $webhookUrl,
    'drop_pending_updates' => true,
]);

echo "<b>SetWebhook Result:</b> " . json_encode($res, JSON_PRETTY_PRINT) . "\n<br><br>";

$info = tgCall('getWebhookInfo');
echo "<b>Webhook Info:</b> " . json_encode($info, JSON_PRETTY_PRINT) . "\n<br>";
