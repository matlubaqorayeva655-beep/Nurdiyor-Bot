<?php
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../utils/telegram.php';

function isAdmin($userId) {
    if ($userId == MAIN_ADMIN_ID) return true;
    $db = Database::getInstance();
    $admin = $db->fetch("SELECT id FROM admins WHERE user_id = ?", [$userId]);
    return !empty($admin);
}

function isMainAdmin($userId) {
    return $userId == MAIN_ADMIN_ID;
}

function sendAdminPanel($userId) {
    $db = Database::getInstance();
    $isMain = isMainAdmin($userId);
    
    $buttons = [
        // — Umumiy —
        [['text' => '📊 Statistika',           'callback_data' => 'admin_stats']],
        [
            ['text' => '👥 Foydalanuvchilar',  'callback_data' => 'admin_users'],
            ['text' => '📣 Reklama',            'callback_data' => 'admin_broadcast'],
        ],
        // — Kontent —
        [
            ['text' => '🎬 Video +',           'callback_data' => 'admin_upload_video'],
            ['text' => '🎭 GIF +',             'callback_data' => 'admin_upload_gif'],
            ['text' => '🌟 Stiker +',          'callback_data' => 'admin_upload_sticker'],
        ],
        [['text' => '🗑 Kontentni o\'chirish', 'callback_data' => 'admin_delete_menu']],
        // — Chatlar —
        [
            ['text' => '👨 Erkak chat +',      'callback_data' => 'admin_add_boy_chat'],
            ['text' => '👩 Ayol chat +',       'callback_data' => 'admin_add_girl_chat'],
        ],
        // — To'lov —
        [
            ['text' => '💳 Karta +',           'callback_data' => 'admin_add_card'],
            ['text' => '💳 Kartalar',          'callback_data' => 'admin_list_cards'],
        ],
        [['text' => '💰 To\'lovlar',           'callback_data' => 'admin_payments']],
        // — Kanallar —
        [
            ['text' => '📢 Kanal +',           'callback_data' => 'admin_add_channel'],
            ['text' => '📢 Kanallar',          'callback_data' => 'admin_list_channels'],
        ],
    ];
    
    if ($isMain) {
        $buttons[] = [['text' => '👮 Admin qo\'shish/o\'chirish', 'callback_data' => 'admin_manage_admins']];
    }
    
    $markup = inlineKeyboard($buttons);
    
    $totalUsers      = $db->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'];
    $todayUsers      = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE date(joined_at) = date('now')")['cnt'];
    $pendingPayments = $db->fetch("SELECT COUNT(*) as cnt FROM payments WHERE status = 'pending'")['cnt'];
    $totalVideos     = $db->fetch("SELECT COUNT(*) as cnt FROM videos")['cnt'];
    
    $text = "🛠 <b>Admin Panel</b>\n\n";
    $text .= "👥 Foydalanuvchilar: <b>{$totalUsers}</b> (bugun +{$todayUsers})\n";
    $text .= "🎬 Videolar: <b>{$totalVideos}</b>\n";
    $text .= "⏳ Kutilayotgan to'lovlar: <b>{$pendingPayments}</b>";

    sendMessage($userId, "🏠", replyKeyboard([['🏠 Bosh menyu']]));
    sendMessage($userId, $text, $markup);
}

// =====================
// STATISTIKA
// =====================
function handleAdminStats($userId) {
    $db = Database::getInstance();

    $total   = $db->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'];
    $males   = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE gender='male'")['cnt'];
    $females = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE gender='female'")['cnt'];
    $today   = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE date(joined_at) = date('now')")['cnt'];
    $week    = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE joined_at >= datetime('now','-7 days')")['cnt'];

    $approved = $db->fetch("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM payments WHERE status='approved'");
    $pending  = $db->fetch("SELECT COUNT(*) as cnt FROM payments WHERE status='pending'")['cnt'];
    $rejected = $db->fetch("SELECT COUNT(*) as cnt FROM payments WHERE status='rejected'")['cnt'];

    $videos   = $db->fetch("SELECT COUNT(*) as cnt FROM videos")['cnt'];
    $gifs     = $db->fetch("SELECT COUNT(*) as cnt FROM gifs")['cnt'];
    $stickers = $db->fetch("SELECT COUNT(*) as cnt FROM stickers")['cnt'];
    $boychats = $db->fetch("SELECT COUNT(*) as cnt FROM boy_chats")['cnt'];
    $girlchats= $db->fetch("SELECT COUNT(*) as cnt FROM girl_chats")['cnt'];
    $channels = $db->fetch("SELECT COUNT(*) as cnt FROM channels WHERE is_active=1")['cnt'];
    $activeSessions = $db->fetch("SELECT COUNT(*) as cnt FROM watch_together_sessions WHERE is_active=1")['cnt'];

    $text  = "📊 <b>Bot Statistikasi</b>\n\n";
    $text .= "👥 <b>Foydalanuvchilar:</b>\n";
    $text .= "  Jami: <b>{$total}</b> | Bugun: <b>+{$today}</b> | Hafta: <b>+{$week}</b>\n";
    $text .= "  👨 Erkak: <b>{$males}</b> | 👩 Ayol: <b>{$females}</b>\n\n";
    $text .= "💰 <b>To'lovlar:</b>\n";
    $text .= "  ✅ Tasdiqlangan: <b>{$approved['cnt']}</b> — {$approved['total']} so'm\n";
    $text .= "  ⏳ Kutilmoqda: <b>{$pending}</b>\n";
    $text .= "  ❌ Rad etilgan: <b>{$rejected}</b>\n\n";
    $text .= "🎬 <b>Kontent:</b>\n";
    $text .= "  Video: <b>{$videos}</b> | GIF: <b>{$gifs}</b> | Stiker: <b>{$stickers}</b>\n\n";
    $text .= "💬 <b>Chatlar:</b>\n";
    $text .= "  👨 Erkak: <b>{$boychats}</b> | 👩 Ayol: <b>{$girlchats}</b>\n\n";
    $text .= "📢 Kanallar: <b>{$channels}</b>\n";
    $text .= "🎥 Faol birga ko'rish: <b>{$activeSessions}</b>";

    $markup = inlineKeyboard([[['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']]]);
    sendMessage($userId, $text, $markup);
}

// =====================
// REKLAMA (BROADCAST)
// =====================
function startBroadcast($userId) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_broadcast_msg', state_data = NULL WHERE user_id = ?", [$userId]);
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE is_blocked = 0")['cnt'];
    sendMessage($userId,
        "📣 <b>Reklama yuborish</b>\n\n" .
        "Jami foydalanuvchilar: <b>{$total}</b>\n\n" .
        "Xabar yuboring — matn, rasm, video, GIF — istalgan format.\n" .
        "⚠️ Yuborishdan oldin yaxshilab tekshiring!",
        replyKeyboard([['❌ Bekor qilish']])
    );
}

function handleBroadcastMessage($adminId, $message) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$adminId]);

    $users = $db->fetchAll("SELECT user_id FROM users WHERE is_blocked = 0 AND user_id != ?", [$adminId]);
    $total = count($users);
    $sent  = 0;
    $failed = 0;

    $progressMsg = sendMessage($adminId,
        "⏳ Yuborilmoqda...\n0 / {$total}",
        removeKeyboard()
    );
    $progressMsgId = $progressMsg['result']['message_id'] ?? null;

    foreach ($users as $i => $user) {
        $res = copyMessage($user['user_id'], $adminId, $message['message_id']);
        if (!empty($res['ok'])) {
            $sent++;
        } else {
            // Bot bloklangan foydalanuvchini belgilash
            if (isset($res['error_code']) && $res['error_code'] === 403) {
                $db->query("UPDATE users SET is_blocked = 1 WHERE user_id = ?", [$user['user_id']]);
            }
            $failed++;
        }
        // Har 50 ta foydalanuvchidan keyin progress yangilash
        if (($i + 1) % 50 === 0 && $progressMsgId) {
            editMessage($adminId, $progressMsgId,
                "⏳ Yuborilmoqda...\n" . ($sent + $failed) . " / {$total}"
            );
        }
        // Telegram rate limit: 30 xabar/sekunddan oshmaslik
        if (($i + 1) % 25 === 0) usleep(1100000);
    }

    if ($progressMsgId) {
        editMessage($adminId, $progressMsgId,
            "✅ <b>Reklama yuborildi!</b>\n\n" .
            "✅ Muvaffaqiyatli: <b>{$sent}</b>\n" .
            "❌ Xato (bloklangan): <b>{$failed}</b>"
        );
    }
    sendAdminPanel($adminId);
}

// =====================
// KANALGA POST
// =====================
function handleChannelPostStart($userId) {
    $db = Database::getInstance();
    $channels = $db->fetchAll("SELECT * FROM channels WHERE is_active = 1");

    if (empty($channels)) {
        sendMessage($userId, "❌ Kanallar yo'q! Avval <b>📢 Kanal qo'shish</b> orqali kanal qo'shing.");
        return;
    }

    $buttons = [];
    foreach ($channels as $ch) {
        $buttons[] = [['text' => '📢 ' . $ch['channel_name'], 'callback_data' => 'chpost_' . $ch['id']]];
    }
    $buttons[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']];

    sendMessage($userId, "📡 <b>Kanalga post yuborish</b>\n\nQaysi kanalga post yubormoqchisiz?",
        inlineKeyboard($buttons));
}

function handleChannelPostSelectChannel($userId, $channelDbId) {
    $db = Database::getInstance();
    $channel = $db->fetch("SELECT * FROM channels WHERE id = ?", [$channelDbId]);
    if (!$channel) return;

    $db->query(
        "UPDATE users SET current_state = 'waiting_chpost_content', state_data = ? WHERE user_id = ?",
        [json_encode(['channel_id' => $channel['channel_id'], 'channel_name' => $channel['channel_name']]), $userId]
    );

    sendMessage($userId,
        "📡 Kanal: <b>{$channel['channel_name']}</b>\n\n" .
        "📤 Endi kanalga yuboriladigan xabarni yuboring:\n\n" .
        "<i>Rasm, video, GIF, matn — xohlagan narsa bo'lsin.\n" .
        "Xabar pastida avtomatik «🎬 Birga tomosha qilish» tugmasi qo'shiladi.</i>",
        replyKeyboard([['❌ Bekor qilish']])
    );
}

// Istalgan turdagi xabarni kanalga yuborish + "Birga tomosha qilish" tugmasi
function handleChannelPostContent($userId, $message, $stateData) {
    $db = Database::getInstance();
    $data = json_decode($stateData, true);
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);

    // Bo'sh watch room yaratish (foydalanuvchilar sahifada video URL qo'shadi)
    $token  = generateToken(8);
    $dbPath = __DIR__ . '/../database/bot.sqlite';
    $pdo    = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_rooms (
        token TEXT PRIMARY KEY, video_url TEXT DEFAULT '',
        messages TEXT DEFAULT '[]', signals TEXT DEFAULT '[]', updated_at INTEGER DEFAULT 0
    )");
    $cols = array_column($pdo->query("PRAGMA table_info(watch_rooms)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('play_state', $cols)) $pdo->exec("ALTER TABLE watch_rooms ADD COLUMN play_state TEXT DEFAULT 'paused'");
    if (!in_array('seek_time',  $cols)) $pdo->exec("ALTER TABLE watch_rooms ADD COLUMN seek_time REAL DEFAULT 0");
    if (!in_array('seek_ts',    $cols)) $pdo->exec("ALTER TABLE watch_rooms ADD COLUMN seek_ts INTEGER DEFAULT 0");
    $pdo->prepare(
        "INSERT OR REPLACE INTO watch_rooms (token, video_url, play_state, seek_time, seek_ts, messages, signals, updated_at)
         VALUES (?, '', 'paused', 0, ?, '[]', '[]', ?)"
    )->execute([$token, time(), time()]);

    $watchUrl    = WATCH_TOGETHER_URL . '?token=' . $token;
    $channelId   = $data['channel_id'];
    $channelName = $data['channel_name'];
    $caption     = $message['caption'] ?? '';

    $markup = inlineKeyboard([[['text' => '🎬 Birga tomosha qilish', 'url' => $watchUrl]]]);

    // Xabar turini aniqlab kanalga yuborish
    if (!empty($message['photo'])) {
        $fileId = end($message['photo'])['file_id'];
        $result = sendPhoto($channelId, $fileId, $caption, $markup);

    } elseif (!empty($message['video'])) {
        $fileId = $message['video']['file_id'];
        $result = sendVideo($channelId, $fileId, $caption, $markup);

    } elseif (!empty($message['animation'])) {
        $fileId = $message['animation']['file_id'];
        $result = sendAnimation($channelId, $fileId, $caption, $markup);

    } elseif (!empty($message['document'])) {
        $fileId = $message['document']['file_id'];
        $result = apiRequest('sendDocument', [
            'chat_id'      => $channelId,
            'document'     => $fileId,
            'caption'      => $caption,
            'parse_mode'   => 'HTML',
            'reply_markup' => $markup,
        ]);

    } elseif (!empty($message['sticker'])) {
        // Stikerda markup bo'lmaydi — avval stiker, keyin tugma bilan xabar
        apiRequest('sendSticker', ['chat_id' => $channelId, 'sticker' => $message['sticker']['file_id']]);
        $result = sendMessage($channelId, '👆 Birga tomosha qiling 👇', $markup);

    } elseif (!empty($message['text'])) {
        $result = sendMessage($channelId, $message['text'], $markup);

    } else {
        // Boshqa turdagi fayllar (audio, voice, video_note va h.k.)
        $result = copyMessage($channelId, $userId, $message['message_id']);
        if (!empty($result['ok'])) {
            // Tugmani alohida yuborish
            sendMessage($channelId, '👇', $markup);
        }
    }

    if (!empty($result['ok'])) {
        sendMessage($userId,
            "✅ <b>Post yuborildi!</b>\n\n📢 Kanal: <b>{$channelName}</b>\n🔗 " . $watchUrl,
            removeKeyboard()
        );
    } else {
        $err = $result['description'] ?? 'Noma\'lum xato';
        sendMessage($userId,
            "❌ <b>Post yuborishda xato!</b>\n\n<code>{$err}</code>\n\n" .
            "Bot kanalga <b>admin</b> sifatida qo'shilganmi?\n" .
            "Kanalda bot <b>xabar yuborish</b> huquqiga ega bo'lishi kerak.",
            removeKeyboard()
        );
    }
    sendAdminPanel($userId);
}

function handleAdminUsers($userId, $gender, $page = 0) {
    $db = Database::getInstance();
    $perPage = 25;
    $offset = $page * $perPage;
    
    $genderFilter = $gender === 'male' ? 'male' : 'female';
    $genderText = $gender === 'male' ? '👨 Erkaklar' : '👩 Ayollar';
    
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM users WHERE gender = ?", [$genderFilter])['cnt'];
    $users = $db->fetchAll("SELECT * FROM users WHERE gender = ? LIMIT ? OFFSET ?", [$genderFilter, $perPage, $offset]);
    
    if (empty($users)) {
        sendMessage($userId, "📭 {$genderText} ro'yxati bo'sh.");
        return;
    }
    
    $text = "<b>{$genderText} ro'yxati</b> ({$total} ta)\n\n";
    
    $chatList = $gender === 'male' 
        ? $db->fetchAll("SELECT * FROM boy_chats")
        : $db->fetchAll("SELECT * FROM girl_chats");
    
    $buttons = [];
    foreach ($chatList as $chat) {
        $buttons[] = [['text' => '👥 ' . $chat['chat_link'], 'url' => $chat['chat_link']]];
    }
    
    // Navigatsiya
    $navButtons = [];
    if ($page > 0) {
        $navButtons[] = ['text' => '⬅️ Oldingi', 'callback_data' => 'admin_users_' . $gender . '_' . ($page - 1)];
    }
    $navButtons[] = ['text' => ($page + 1) . '/' . ceil($total / $perPage), 'callback_data' => 'noop'];
    if (($page + 1) * $perPage < $total) {
        $navButtons[] = ['text' => 'Keyingisi ➡️', 'callback_data' => 'admin_users_' . $gender . '_' . ($page + 1)];
    }
    if (!empty($navButtons)) $buttons[] = $navButtons;
    
    $text .= "Sahifa: " . ($page + 1) . "/" . ceil($total / $perPage);
    
    sendMessage($userId, $text, inlineKeyboard($buttons));
}

function startUploadSession($userId, $type) {
    $db = Database::getInstance();
    
    // Oldingi sessiyani o'chir
    $db->query("UPDATE upload_sessions SET is_active = 0 WHERE admin_id = ?", [$userId]);
    
    $db->query("INSERT INTO upload_sessions (admin_id, upload_type) VALUES (?, ?)", [$userId, $type]);
    
    $typeNames = ['video' => '🎬 Video', 'gif' => '🎭 GIF', 'sticker' => '🌟 Stiker'];
    $typeName = $typeNames[$type] ?? $type;
    
    $db->query("UPDATE users SET current_state = ?, state_data = NULL WHERE user_id = ?", 
        ['uploading_' . $type, $userId]);
    
    $markup = replyKeyboard([['⛔ Stop (yuklashni tugatish)']]);
    sendMessage($userId, "📤 <b>{$typeName} yuklash rejimi yoqildi!</b>\n\n{$typeName}larni birin-ketin yuboring.\nYuklashni tugatish uchun <b>⛔ Stop</b> tugmasini bosing.", $markup);
}

function handleUploadedFile($userId, $fileId, $type, $count) {
    $db = Database::getInstance();
    
    if ($type === 'video') {
        $db->query("INSERT INTO videos (file_id) VALUES (?)", [$fileId]);
    } elseif ($type === 'gif') {
        $db->query("INSERT INTO gifs (file_id) VALUES (?)", [$fileId]);
    } elseif ($type === 'sticker') {
        $db->query("INSERT INTO stickers (file_id) VALUES (?)", [$fileId]);
    }
    
    $db->query("UPDATE upload_sessions SET count = ? WHERE admin_id = ? AND is_active = 1", [$count, $userId]);
    
    $typeNames = ['video' => 'Video', 'gif' => 'GIF', 'sticker' => 'Stiker'];
    $typeName = $typeNames[$type] ?? $type;
    
    $msg = "✅ {$typeName} #{$count} yuklandi!";
    
    if ($count % 50 === 0) {
        $msg .= "\n\n📌 {$count} ta yuklandi. Yuklashni davom ettiring yoki <b>⛔ Stop</b> tugmasini bosing.";
    }
    
    sendMessage($userId, $msg);
}

function stopUploadSession($userId) {
    $db = Database::getInstance();
    
    $session = $db->fetch("SELECT * FROM upload_sessions WHERE admin_id = ? AND is_active = 1", [$userId]);
    if (!$session) {
        sendAdminPanel($userId);
        return;
    }
    
    $db->query("UPDATE upload_sessions SET is_active = 0 WHERE admin_id = ? AND is_active = 1", [$userId]);
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
    
    $typeNames = ['video' => '🎬 Video', 'gif' => '🎭 GIF', 'sticker' => '🌟 Stiker'];
    $typeName = $typeNames[$session['upload_type']] ?? $session['upload_type'];
    
    sendMessage($userId, "✅ <b>Yuklash tugadi!</b>\n\nJami {$typeName}: <b>{$session['count']}</b> ta yuklandi.", removeKeyboard());
    sendAdminPanel($userId);
}

function handleDeleteMenu($userId) {
    $markup = inlineKeyboard([
        [['text' => '🎬 Video o\'chirish', 'callback_data' => 'admin_delete_video']],
        [['text' => '🎭 GIF o\'chirish', 'callback_data' => 'admin_delete_gif']],
        [['text' => '🌟 Stiker o\'chirish', 'callback_data' => 'admin_delete_sticker']],
        [['text' => '👨 Erkak chat o\'chirish', 'callback_data' => 'admin_delete_boy_chat']],
        [['text' => '👩 Ayol chat o\'chirish', 'callback_data' => 'admin_delete_girl_chat']],
        [['text' => '📢 Kanal o\'chirish', 'callback_data' => 'admin_delete_channel']],
        [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']],
    ]);
    sendMessage($userId, "🗑 <b>Nima o'chirmoqchisiz?</b>", $markup);
}

function handleDeleteList($userId, $type) {
    $db = Database::getInstance();
    
    $tables = [
        'video' => ['videos', '🎬 Video'],
        'gif' => ['gifs', '🎭 GIF'],
        'sticker' => ['stickers', '🌟 Stiker'],
        'boy_chat' => ['boy_chats', '👨 Erkak chat'],
        'girl_chat' => ['girl_chats', '👩 Ayol chat'],
        'channel' => ['channels', '📢 Kanal'],
    ];
    
    if (!isset($tables[$type])) return;
    [$table, $typeName] = $tables[$type];
    
    if (in_array($type, ['boy_chat', 'girl_chat'])) {
        $items = $db->fetchAll("SELECT id, chat_link as name FROM {$table}");
    } elseif ($type === 'channel') {
        $items = $db->fetchAll("SELECT id, channel_name as name FROM {$table}");
    } else {
        $items = $db->fetchAll("SELECT id, ('ID: ' || id) as name FROM {$table}");
    }
    
    if (empty($items)) {
        sendMessage($userId, "📭 {$typeName} ro'yxati bo'sh.");
        return;
    }
    
    $buttons = [];
    foreach ($items as $item) {
        $buttons[] = [['text' => '🗑 ' . $item['name'], 'callback_data' => "delete_{$type}_{$item['id']}"]];
    }
    $buttons[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_delete_menu']];
    
    sendMessage($userId, "<b>{$typeName}larni o'chirish:</b>\n\nO'chirish uchun bosing:", inlineKeyboard($buttons));
}

function handleDeleteItem($userId, $type, $id) {
    $db = Database::getInstance();
    
    $tables = [
        'video' => 'videos',
        'gif' => 'gifs',
        'sticker' => 'stickers',
        'boy_chat' => 'boy_chats',
        'girl_chat' => 'girl_chats',
        'channel' => 'channels',
        'card' => 'cards',
    ];
    
    if (!isset($tables[$type])) return;
    $table = $tables[$type];
    
    $db->query("DELETE FROM {$table} WHERE id = ?", [$id]);
    sendMessage($userId, "✅ O'chirildi!");
    
    if ($type === 'card') {
        handleListCards($userId);
    } else {
        handleDeleteMenu($userId);
    }
}

function handleAddBoyChat($userId) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_boy_chat', state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "👨 <b>Erkak chat qo'shish</b>\n\nChat linkini yuboring (masalan: https://t.me/chatname):", replyKeyboard([['❌ Bekor qilish']]));
}

function handleAddGirlChat($userId) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_girl_chat', state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "👩 <b>Ayol chat qo'shish</b>\n\nChat linkini yuboring (masalan: https://t.me/chatname):", replyKeyboard([['❌ Bekor qilish']]));
}

function normalizeChatLink($raw) {
    $raw = trim($raw);
    // t.me/xxx yoki @xxx → https://t.me/xxx
    if (preg_match('/^@(.+)$/', $raw, $m)) {
        return 'https://t.me/' . $m[1];
    }
    if (preg_match('/^t\.me\/(.+)$/', $raw, $m)) {
        return 'https://t.me/' . $m[1];
    }
    // https://t.me/... yoki https://t.me/+... — qoldirish
    if (preg_match('/^https?:\/\/(www\.)?t\.me\/.+$/', $raw)) {
        return $raw;
    }
    return null; // noto'g'ri format
}

function saveBoyChat($userId, $link) {
    $db = Database::getInstance();
    $normalized = normalizeChatLink($link);
    if (!$normalized) {
        sendMessage($userId, "❌ Noto'g'ri format!\n\nTo'g'ri misol:\n• https://t.me/chatname\n• @chatname\n• t.me/+invitelink\n\nQaytadan yuboring:");
        return;
    }
    $chatId = str_replace('https://t.me/', '@', $normalized);
    $db->query("INSERT INTO boy_chats (chat_id, chat_link) VALUES (?, ?)", [$chatId, $normalized]);
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "✅ Erkak chati qo'shildi!\n🔗 " . $normalized, removeKeyboard());
    sendAdminPanel($userId);
}

function saveGirlChat($userId, $link) {
    $db = Database::getInstance();
    $normalized = normalizeChatLink($link);
    if (!$normalized) {
        sendMessage($userId, "❌ Noto'g'ri format!\n\nTo'g'ri misol:\n• https://t.me/chatname\n• @chatname\n• t.me/+invitelink\n\nQaytadan yuboring:");
        return;
    }
    $chatId = str_replace('https://t.me/', '@', $normalized);
    $db->query("INSERT INTO girl_chats (chat_id, chat_link) VALUES (?, ?)", [$chatId, $normalized]);
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "✅ Ayol chati qo'shildi!\n🔗 " . $normalized, removeKeyboard());
    sendAdminPanel($userId);
}

function handleAddChannel($userId) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_channel_link', state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "📢 <b>Kanal qo'shish</b>\n\nKanal linkini yuboring (masalan: https://t.me/channelname):", replyKeyboard([['❌ Bekor qilish']]));
}

function handleChannelLink($userId, $link) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_channel_name', state_data = ? WHERE user_id = ?",
        [$link, $userId]);
    sendMessage($userId, "📢 Kanal nomini kiriting (masalan: Anime kanal):");
}

function handleChannelName($userId, $name, $link) {
    $db = Database::getInstance();
    $chatId = str_replace('https://t.me/', '@', $link);
    $db->query("INSERT INTO channels (channel_id, channel_name, channel_link) VALUES (?, ?, ?)", [$chatId, $name, $link]);
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "✅ Kanal qo'shildi: <b>{$name}</b>", removeKeyboard());
    sendAdminPanel($userId);
}

function handleListChannels($userId) {
    $db = Database::getInstance();
    $channels = $db->fetchAll("SELECT * FROM channels WHERE is_active = 1");
    
    if (empty($channels)) {
        sendMessage($userId, "📭 Kanallar yo'q.");
        return;
    }
    
    $buttons = [];
    foreach ($channels as $ch) {
        $buttons[] = [
            ['text' => '📢 ' . $ch['channel_name'], 'url' => $ch['channel_link']],
            ['text' => '🗑', 'callback_data' => 'delete_channel_' . $ch['id']],
        ];
    }
    $buttons[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']];
    
    sendMessage($userId, "📢 <b>Kanallar ro'yxati:</b>", inlineKeyboard($buttons));
}

function handleAddCard($userId) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_card_number', state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "💳 <b>Karta qo'shish</b>\n\nKarta raqamini kiriting:", replyKeyboard([['❌ Bekor qilish']]));
}

function handleCardNumber($userId, $number) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_card_owner', state_data = ? WHERE user_id = ?",
        [$number, $userId]);
    sendMessage($userId, "💳 Karta egasining ismini kiriting:");
}

function handleCardOwner($userId, $owner, $number) {
    $db = Database::getInstance();
    $db->query("INSERT INTO cards (card_number, card_owner) VALUES (?, ?)", [$number, $owner]);
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "✅ Karta qo'shildi!\n\n💳 {$number}\n👤 {$owner}", removeKeyboard());
    sendAdminPanel($userId);
}

function handleListCards($userId) {
    $db = Database::getInstance();
    $cards = $db->fetchAll("SELECT * FROM cards");
    
    if (empty($cards)) {
        sendMessage($userId, "📭 Kartalar yo'q.");
        return;
    }
    
    $buttons = [];
    foreach ($cards as $card) {
        $status = $card['is_active'] ? '✅' : '❌';
        $buttons[] = [
            ['text' => $status . ' ' . $card['card_number'] . ' (' . $card['card_owner'] . ')', 'callback_data' => 'noop'],
            ['text' => '🗑', 'callback_data' => 'delete_card_' . $card['id']],
        ];
    }
    $buttons[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']];
    
    sendMessage($userId, "💳 <b>Kartalar ro'yxati:</b>", inlineKeyboard($buttons));
}

function handleAdminPayments($userId) {
    $db = Database::getInstance();
    $payments = $db->fetchAll("SELECT p.*, u.full_name, u.username FROM payments p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.status = 'pending' ORDER BY p.created_at DESC LIMIT 20");
    
    if (empty($payments)) {
        sendMessage($userId, "✅ Kutilayotgan to'lovlar yo'q.");
        return;
    }
    
    foreach ($payments as $pay) {
        $typeText = $pay['payment_type'] === 'girl_search' ? '👩 Qiz qidirish' : '🎥 Birga video ko\'rish';
        $text = "💳 <b>To'lov #{$pay['id']}</b>\n";
        $text .= "👤 {$pay['full_name']} (ID: {$pay['user_id']})\n";
        $text .= "📌 {$typeText}\n";
        $text .= "💰 {$pay['amount']} so'm";
        
        $markup = inlineKeyboard([
            [
                ['text' => '✅ Tasdiqlash', 'callback_data' => 'pay_approve_' . $pay['id']],
                ['text' => '❌ Rad etish', 'callback_data' => 'pay_reject_' . $pay['id']],
            ]
        ]);
        
        sendPhoto($userId, $pay['check_photo'], $text, $markup);
    }
}

function handlePaymentApprove($adminId, $paymentId, $adminChatId, $adminMsgId) {
    $db = Database::getInstance();
    $payment = $db->fetch("SELECT * FROM payments WHERE id = ?", [$paymentId]);
    if (!$payment || $payment['status'] !== 'pending') {
        sendMessage($adminId, "⚠️ Bu to'lov allaqachon ko'rib chiqilgan.");
        return;
    }

    // Admin xabaridagi tugmalarni darhol olib tashla
    removeInlineKeyboard($adminChatId, $adminMsgId);

    $db->query("UPDATE payments SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP WHERE id = ?", [$paymentId]);

    $userId = $payment['user_id'];

    if ($payment['payment_type'] === 'girl_search') {
        $total = $db->fetch("SELECT COUNT(*) as cnt FROM girl_chats")['cnt'];

        if ($total == 0) {
            sendMessage($userId, "✅ To'lovingiz tasdiqlandi! Tez orada chat linki yuboriladi.");
            sendMessage($adminId, "✅ Tasdiqlandi, lekin hozir ayol chat yo'q — qo'shing!");
            return;
        }

        $indexRow = $db->fetch("SELECT last_index FROM girl_chat_index LIMIT 1");
        $index    = $indexRow ? (int)$indexRow['last_index'] : 0;
        if ($index >= $total) $index = 0;

        $chat     = $db->fetch("SELECT * FROM girl_chats LIMIT 1 OFFSET ?", [$index]);
        $newIndex = ($index + 1) % $total;
        $db->query("UPDATE girl_chat_index SET last_index = ?", [$newIndex]);

        $markup = inlineKeyboard([
            [['text' => '👩 Chatga o\'tish', 'url' => $chat['chat_link']]]
        ]);
        sendMessage($userId,
            "✅ <b>To'lovingiz tasdiqlandi!</b>\n\nMana, marhamat — gaplashing 👇",
            $markup);

    } elseif ($payment['payment_type'] === 'watch_together') {
        $db->query(
            "UPDATE users SET current_state = 'waiting_watch_partner', state_data = ? WHERE user_id = ?",
            [json_encode(['payment_id' => $paymentId]), $userId]
        );
        sendMessage($userId,
            "✅ <b>To'lovingiz tasdiqlandi!</b>\n\n🎥 Do'stingizning Telegram ID si yoki @username ini kiriting:");
    }

    sendMessage($adminId, "✅ To'lov #{$paymentId} tasdiqlandi!");
}

function handlePaymentReject($adminId, $paymentId, $adminChatId, $adminMsgId) {
    $db = Database::getInstance();
    $payment = $db->fetch("SELECT * FROM payments WHERE id = ?", [$paymentId]);
    if (!$payment || $payment['status'] !== 'pending') {
        sendMessage($adminId, "⚠️ Bu to'lov allaqachon ko'rib chiqilgan.");
        return;
    }

    // Admin xabaridagi tugmalarni darhol olib tashla
    removeInlineKeyboard($adminChatId, $adminMsgId);

    $db->query("UPDATE payments SET status = 'rejected', reviewed_at = CURRENT_TIMESTAMP WHERE id = ?", [$paymentId]);
    sendMessage($payment['user_id'],
        "❌ <b>To'lovingiz rad etildi.</b>\n\nTo'g'ri chek rasmini yuboring yoki admin bilan bog'laning.");
    sendMessage($adminId, "❌ To'lov #{$paymentId} rad etildi.");
}

function handleManageAdmins($userId) {
    if (!isMainAdmin($userId)) return;
    $db = Database::getInstance();
    
    $admins = $db->fetchAll("SELECT * FROM admins");
    $text = "👮 <b>Adminlar boshqaruvi</b>\n\n";
    
    $buttons = [];
    if (!empty($admins)) {
        $text .= "Hozirgi adminlar:\n";
        foreach ($admins as $admin) {
            $text .= "• ID: {$admin['user_id']}\n";
            $buttons[] = [['text' => '🗑 Admin ' . $admin['user_id'] . ' ni o\'chirish', 'callback_data' => 'remove_admin_' . $admin['user_id']]];
        }
    } else {
        $text .= "Hozircha qo'shimcha adminlar yo'q.\n";
    }
    
    $buttons[] = [['text' => '➕ Admin qo\'shish', 'callback_data' => 'add_admin']];
    $buttons[] = [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']];
    
    sendMessage($userId, $text, inlineKeyboard($buttons));
}

function handleAddAdmin($userId) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET current_state = 'waiting_new_admin_id' WHERE user_id = ?", [$userId]);
    sendMessage($userId, "👮 Yangi admin ID sini kiriting:", replyKeyboard([['❌ Bekor qilish']]));
}

function handleSaveAdmin($userId, $newAdminId) {
    $db = Database::getInstance();
    $newAdminId = (int)$newAdminId;
    
    if ($newAdminId == MAIN_ADMIN_ID) {
        sendMessage($userId, "❌ Bu siz (bosh admin)!");
        return;
    }
    
    $db->query("INSERT OR IGNORE INTO admins (user_id) VALUES (?)", [$newAdminId]);
    $db->query("UPDATE users SET current_state = NULL WHERE user_id = ?", [$userId]);
    sendMessage($userId, "✅ Admin qo'shildi: {$newAdminId}", removeKeyboard());
    handleManageAdmins($userId);
}

function handleRemoveAdmin($userId, $adminId) {
    $db = Database::getInstance();
    $db->query("DELETE FROM admins WHERE user_id = ?", [$adminId]);
    sendMessage($userId, "✅ Admin o'chirildi: {$adminId}");
    handleManageAdmins($userId);
}
