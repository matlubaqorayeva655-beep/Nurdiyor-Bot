<?php
// Bu faylni bir marta ishlatib webhook o'rnatish uchun
require_once __DIR__ . '/config/config.php';

$webhookUrl = WEBHOOK_URL;
$url = API_URL . 'setWebhook';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $webhookUrl]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
curl_close($ch);

echo "<pre>";
echo "Webhook o'rnatildi:\n";
echo json_encode(json_decode($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

// Bot ma'lumotlarini tekshirish
$ch2 = curl_init(API_URL . 'getMe');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$me = curl_exec($ch2);
curl_close($ch2);

echo "<pre>Bot: ";
echo json_encode(json_decode($me), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";
