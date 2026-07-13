# Tanishuv Bot 🤝

PHP 8+ Telegram Dating Bot

## O'rnatish

### 1. Config sozlang
```bash
cp config/config.example.php config/config.php
```
`config/config.php` ni oching:

```php
define('BOT_TOKEN',    getenv('BOT_TOKEN')  ?: 'TOKEN_NI_BU_YERGA_YOZING');
define('MAIN_ADMIN_ID', (int)(getenv('ADMIN_ID') ?: 123456789));
```

### 2. Webhook o'rnating
Brauzerda oching:
```
https://sizning-domen.com/bot/setup_webhook.php
```

## Ishga tushirish
```bash
php -S 0.0.0.0:3000 -t . router.php
```

## Talablar: PHP 8.0+, PDO SQLite3, cURL
