<?php
$botToken = '8790094820:AAG0gWoRouQmkwY66fFTRpON8WMYkuys_ms';
$webhookUrl = 'https://closed-boxes-loved-pat.trycloudflare.com/bikinweb/flappybird/api/telegram.php';

$url = "https://api.telegram.org/bot{$botToken}/setWebhook";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['url' => $webhookUrl]),
    CURLOPT_TIMEOUT => 10,
]);
$result = curl_exec($ch);
curl_close($ch);

$data = json_decode($result, true);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($data['ok'] ?? false) {
    echo "Webhook set successfully!\n";
} else {
    echo "Failed to set webhook: " . ($data['description'] ?? 'unknown error') . "\n";
}
