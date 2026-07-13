<?php
// ====================================================
// MUHIT O'ZGARUVCHILAR (Environment Variables) orqali
// sozlash. .env fayl yoki server panelidan o'rnating:
//
//   BOT_TOKEN=8822942676:AAGZ...
//   ADMIN_ID=8159211308
//   BOT_DOMAIN=https://sizning-domen.com
// ====================================================

// Telegram Bot Token
define('BOT_TOKEN',    getenv('BOT_TOKEN')  ?: 'YOUR_BOT_TOKEN_HERE');

// Bot API URL (o'zgartirmang)
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// Bosh admin Telegram ID
define('MAIN_ADMIN_ID', (int)(getenv('ADMIN_ID') ?: 123456789));

// Server domeni (https:// bilan, oxirida / siz)
$_domain = rtrim(
    getenv('BOT_DOMAIN')
        ?: ('https://' . (getenv('REPLIT_DEV_DOMAIN') ?: 'localhost')),
    '/'
);

// Webhook URL
define('WEBHOOK_URL',        $_domain . '/bot/webhook.php');

// Birga video ko'rish sahifasi
define('WATCH_TOGETHER_URL', $_domain . '/bot/watch/');
