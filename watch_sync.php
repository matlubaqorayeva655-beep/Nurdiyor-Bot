<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$dbPath = __DIR__ . '/../database/bot.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS watch_rooms (
    token TEXT PRIMARY KEY,
    video_url TEXT DEFAULT '',
    messages TEXT DEFAULT '[]',
    signals TEXT DEFAULT '[]',
    updated_at INTEGER DEFAULT 0
)");
// Yangi ustunlarni qo'shish (eski database uchun migration)
$existing = array_column(
    $pdo->query("PRAGMA table_info(watch_rooms)")->fetchAll(PDO::FETCH_ASSOC),
    'name'
);
if (!in_array('play_state', $existing)) $pdo->exec("ALTER TABLE watch_rooms ADD COLUMN play_state TEXT DEFAULT 'paused'");
if (!in_array('seek_time',  $existing)) $pdo->exec("ALTER TABLE watch_rooms ADD COLUMN seek_time REAL DEFAULT 0");
if (!in_array('seek_ts',    $existing)) $pdo->exec("ALTER TABLE watch_rooms ADD COLUMN seek_ts INTEGER DEFAULT 0");

$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!$token || strlen($token) < 4) {
    echo json_encode(['ok' => false, 'error' => 'no token']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'get';

function ensureRoom($pdo, $token) {
    $stmt = $pdo->prepare("SELECT * FROM watch_rooms WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->prepare("INSERT INTO watch_rooms (token, updated_at, seek_ts) VALUES (?, ?, ?)")
            ->execute([$token, time(), time()]);
        return ['token' => $token, 'video_url' => '', 'play_state' => 'paused',
                'seek_time' => 0, 'seek_ts' => time(),
                'messages' => '[]', 'signals' => '[]', 'updated_at' => time()];
    }
    return $row;
}

// ─── GET state ───────────────────────────────────────────────────────────────
if ($action === 'get') {
    $room  = ensureRoom($pdo, $token);
    $since = (int)($_GET['since'] ?? 0);
    $msgs  = json_decode($room['messages'], true) ?? [];
    $new   = array_values(array_filter($msgs, fn($m) => ($m['t'] ?? 0) > $since));

    echo json_encode([
        'ok'         => true,
        'video_url'  => $room['video_url'],
        'play_state' => $room['play_state'],
        'seek_time'  => (float)$room['seek_time'],
        'seek_ts'    => (int)$room['seek_ts'],
        'updated_at' => (int)$room['updated_at'],
        'messages'   => $new,
        'signals'    => json_decode($room['signals'], true) ?? [],
    ]);
    exit;
}

// ─── Set video URL ───────────────────────────────────────────────────────────
if ($action === 'video') {
    $url = trim($_POST['url'] ?? '');
    ensureRoom($pdo, $token);
    $pdo->prepare("UPDATE watch_rooms SET video_url=?, play_state='paused', seek_time=0, seek_ts=?, updated_at=? WHERE token=?")
        ->execute([$url, time(), time(), $token]);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── Play / Pause sync ───────────────────────────────────────────────────────
if ($action === 'play' || $action === 'pause') {
    $seekTime = (float)($_POST['seek_time'] ?? 0);
    ensureRoom($pdo, $token);
    $pdo->prepare("UPDATE watch_rooms SET play_state=?, seek_time=?, seek_ts=?, updated_at=? WHERE token=?")
        ->execute([$action, $seekTime, time(), time(), $token]);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── Seek ────────────────────────────────────────────────────────────────────
if ($action === 'seek') {
    $seekTime = (float)($_POST['seek_time'] ?? 0);
    ensureRoom($pdo, $token);
    $pdo->prepare("UPDATE watch_rooms SET seek_time=?, seek_ts=?, updated_at=? WHERE token=?")
        ->execute([$seekTime, time(), time(), $token]);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── Chat ────────────────────────────────────────────────────────────────────
if ($action === 'chat') {
    $name = htmlspecialchars(trim($_POST['name'] ?? 'Foydalanuvchi'), ENT_QUOTES);
    $text = htmlspecialchars(trim($_POST['text'] ?? ''), ENT_QUOTES);
    if (!$text) { echo json_encode(['ok' => false]); exit; }
    $room = ensureRoom($pdo, $token);
    $msgs = json_decode($room['messages'], true) ?? [];
    $msgs[] = ['name' => $name, 'text' => $text, 't' => time()];
    if (count($msgs) > 100) $msgs = array_slice($msgs, -100);
    $pdo->prepare("UPDATE watch_rooms SET messages=?, updated_at=? WHERE token=?")
        ->execute([json_encode($msgs), time(), $token]);
    echo json_encode(['ok' => true]);
    exit;
}

// ─── WebRTC signal ──────────────────────────────────────────────────────────
if ($action === 'signal') {
    $signal = $_POST['signal'] ?? '';
    $from   = $_POST['from']   ?? '';
    if (!$signal) { echo json_encode(['ok' => false]); exit; }
    $room    = ensureRoom($pdo, $token);
    $signals = json_decode($room['signals'], true) ?? [];
    $signals[] = ['from' => $from, 'signal' => json_decode($signal, true), 't' => time()];
    $signals = array_slice($signals, -30);
    $pdo->prepare("UPDATE watch_rooms SET signals=?, updated_at=? WHERE token=?")
        ->execute([json_encode($signals), time(), $token]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'clear_signals') {
    ensureRoom($pdo, $token);
    $pdo->prepare("UPDATE watch_rooms SET signals='[]' WHERE token=?")
        ->execute([$token]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'unknown action']);
