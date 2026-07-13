<?php
// PHP built-in server router
$uri  = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// /bot/webhook.php
if ($path === '/bot/webhook.php') {
    require __DIR__ . '/bot/webhook.php';
    exit;
}

// /bot/watch/sync.php
if ($path === '/bot/watch/sync.php') {
    require __DIR__ . '/bot/watch/sync.php';
    exit;
}

// /bot/watch/ (index)
if (strpos($path, '/bot/watch') === 0) {
    require __DIR__ . '/bot/watch/index.php';
    exit;
}

// /bot/setup_webhook.php
if ($path === '/bot/setup_webhook.php') {
    require __DIR__ . '/bot/setup_webhook.php';
    exit;
}

// Static files in telegram-bot/
$localFile = __DIR__ . $path;
if ($path !== '/' && file_exists($localFile) && is_file($localFile)) {
    return false;
}

// Healthcheck
if ($path === '/health' || $path === '/') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'bot' => 'running']);
    exit;
}

http_response_code(404);
echo 'Not found';
