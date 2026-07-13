<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbFile = __DIR__ . '/bot.sqlite';
        $this->pdo = new PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createTables();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            user_id INTEGER UNIQUE NOT NULL,
            username TEXT,
            full_name TEXT,
            gender TEXT,
            is_blocked INTEGER DEFAULT 0,
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            current_state TEXT DEFAULT NULL,
            state_data TEXT DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS channels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel_id TEXT NOT NULL,
            channel_name TEXT NOT NULL,
            channel_link TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id TEXT NOT NULL,
            file_type TEXT DEFAULT 'video',
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS gifs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id TEXT NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS stickers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id TEXT NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS boy_chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id TEXT NOT NULL,
            chat_link TEXT NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS girl_chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id TEXT NOT NULL,
            chat_link TEXT NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS girl_chat_index (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            last_index INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS boy_chat_index (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            last_index INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            payment_type TEXT NOT NULL,
            amount INTEGER NOT NULL,
            check_photo TEXT,
            status TEXT DEFAULT 'pending',
            extra_data TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS cards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            card_number TEXT NOT NULL,
            card_owner TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE IF NOT EXISTS user_video_index (
            user_id INTEGER PRIMARY KEY,
            video_index INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS user_gif_index (
            user_id INTEGER PRIMARY KEY,
            gif_index INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS user_sticker_index (
            user_id INTEGER PRIMARY KEY,
            sticker_index INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS watch_together_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            requester_id INTEGER NOT NULL,
            partner_id INTEGER NOT NULL,
            session_token TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS watch_together_paid (
            user_id INTEGER PRIMARY KEY,
            used INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS upload_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            upload_type TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            count INTEGER DEFAULT 0,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";

        $this->pdo->exec($sql);

        // Default sozlamalar
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('payment_girl', '5000')");
        $this->pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('payment_watch', '2000')");
        $this->pdo->exec("INSERT OR IGNORE INTO girl_chat_index (last_index) SELECT 0 WHERE NOT EXISTS (SELECT 1 FROM girl_chat_index)");
        $this->pdo->exec("INSERT OR IGNORE INTO boy_chat_index (last_index) SELECT 0 WHERE NOT EXISTS (SELECT 1 FROM boy_chat_index)");
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function getSetting($key) {
        $row = $this->fetch("SELECT value FROM settings WHERE key = ?", [$key]);
        return $row ? $row['value'] : null;
    }

    public function setSetting($key, $value) {
        $this->query("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", [$key, $value]);
    }
}
