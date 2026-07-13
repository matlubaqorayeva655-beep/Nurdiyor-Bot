<?php
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../utils/telegram.php';
require_once __DIR__ . '/../utils/channel_check.php';

function handleUserStart($userId, $username, $fullName) {
    $db = Database::getInstance();
    
    // Foydalanuvchini tekshir yoki qo'sh
    $user = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$userId]);
    
    if (!$user) {
        $db->query("INSERT INTO users (user_id, username, full_name) VALUES (?, ?, ?)", 
            [$userId, $username, $fullName]);
        $user = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$userId]);
    }
    
    // Jins tanlanganmi?
    if (empty($user['gender'])) {
        $markup = inlineKeyboard([
            [
                ['text' => '👨 Erkak', 'callback_data' => 'gender_male'],
                ['text' => '👩 Ayol', 'callback_data' => 'gender_female'],
            ]
        ]);
        sendMessage($userId, "👋 <b>Xush kelibsiz!</b>\n\nBotdan foydalanish uchun avval jinsingizni tasdiqlang:", $markup);
        return;
    }
    
    // Kanal tekshirish
    if (!checkUserChannels($userId)) return;
    
    // Asosiy menyu
    sendMainMenu($userId, $user['gender']);
}

function sendMainMenu($userId, $gender = null) {
    $db = Database::getInstance();
    if (!$gender) {
        $user = $db->fetch("SELECT gender FROM users WHERE user_id = ?", [$userId]);
        $gender = $user['gender'] ?? 'male';
    }
    
    $keyboard = [
        ['🎬 Video', '🎭 GIF', '🌟 Stiker'],
        ['👨 Erkak chat', '👩 Qiz chat'],
        ['🎥 Birga video', '❓ Yordam'],
    ];

    // Adminlarga admin panel tugmasini qo'shish
    if (isAdmin($userId)) {
        $keyboard[] = ['🛠 Admin Panel'];
    }
    
    $markup = replyKeyboard($keyboard);
    sendMessage($userId, "🏠 <b>Asosiy menyu</b>", $markup);
}

function handleGenderSelection($userId, $gender) {
    $db = Database::getInstance();
    $db->query("UPDATE users SET gender = ? WHERE user_id = ?", [$gender, $userId]);
    
    $genderText = $gender === 'male' ? '👨 Erkak' : '👩 Ayol';
    sendMessage($userId, "✅ <b>Jinsingiz tasdiqlandi: $genderText</b>\n\nEndi botdan foydalanishingiz mumkin!");
    
    if (!checkUserChannels($userId)) return;
    sendMainMenu($userId, $gender);
}

function handleVideoWatch($userId) {
    $db = Database::getInstance();
    
    $totalVideos = $db->fetch("SELECT COUNT(*) as cnt FROM videos")['cnt'];
    if ($totalVideos == 0) {
        sendMessage($userId, "📭 Hozircha videolar mavjud emas.");
        return;
    }
    
    $indexRow = $db->fetch("SELECT video_index FROM user_video_index WHERE user_id = ?", [$userId]);
    $index = $indexRow ? $indexRow['video_index'] : 0;
    
    if ($index >= $totalVideos) $index = 0;
    
    $video = $db->fetch("SELECT * FROM videos LIMIT 1 OFFSET ?", [$index]);
    if (!$video) {
        $index = 0;
        $video = $db->fetch("SELECT * FROM videos LIMIT 1 OFFSET 0");
    }
    
    $markup = inlineKeyboard([
        [
            ['text' => '⬅️ Ortga', 'callback_data' => 'video_prev'],
            ['text' => ($index + 1) . '/' . $totalVideos, 'callback_data' => 'noop'],
            ['text' => 'Keyingisi ➡️', 'callback_data' => 'video_next'],
        ]
    ]);
    
    $db->query("INSERT OR REPLACE INTO user_video_index (user_id, video_index) VALUES (?, ?)", [$userId, $index]);
    
    sendVideo($userId, $video['file_id'], "🎬 <b>Video " . ($index + 1) . "/" . $totalVideos . "</b>", $markup);
}

function handleVideoNav($userId, $direction) {
    $db = Database::getInstance();
    
    $totalVideos = $db->fetch("SELECT COUNT(*) as cnt FROM videos")['cnt'];
    if ($totalVideos == 0) return;
    
    $indexRow = $db->fetch("SELECT video_index FROM user_video_index WHERE user_id = ?", [$userId]);
    $index = $indexRow ? $indexRow['video_index'] : 0;
    
    if ($direction === 'next') {
        $index = ($index + 1) % $totalVideos;
    } else {
        $index = ($index - 1 + $totalVideos) % $totalVideos;
    }
    
    $video = $db->fetch("SELECT * FROM videos LIMIT 1 OFFSET ?", [$index]);
    
    $markup = inlineKeyboard([
        [
            ['text' => '⬅️ Ortga', 'callback_data' => 'video_prev'],
            ['text' => ($index + 1) . '/' . $totalVideos, 'callback_data' => 'noop'],
            ['text' => 'Keyingisi ➡️', 'callback_data' => 'video_next'],
        ]
    ]);
    
    $db->query("INSERT OR REPLACE INTO user_video_index (user_id, video_index) VALUES (?, ?)", [$userId, $index]);
    
    sendVideo($userId, $video['file_id'], "🎬 <b>Video " . ($index + 1) . "/" . $totalVideos . "</b>", $markup);
}

function handleGifWatch($userId) {
    $db = Database::getInstance();
    
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM gifs")['cnt'];
    if ($total == 0) {
        sendMessage($userId, "📭 Hozircha GIFlar mavjud emas.");
        return;
    }
    
    $indexRow = $db->fetch("SELECT gif_index FROM user_gif_index WHERE user_id = ?", [$userId]);
    $index = $indexRow ? $indexRow['gif_index'] : 0;
    if ($index >= $total) $index = 0;
    
    $item = $db->fetch("SELECT * FROM gifs LIMIT 1 OFFSET ?", [$index]);
    
    $markup = inlineKeyboard([
        [
            ['text' => '⬅️ Ortga', 'callback_data' => 'gif_prev'],
            ['text' => ($index + 1) . '/' . $total, 'callback_data' => 'noop'],
            ['text' => 'Keyingisi ➡️', 'callback_data' => 'gif_next'],
        ]
    ]);
    
    $db->query("INSERT OR REPLACE INTO user_gif_index (user_id, gif_index) VALUES (?, ?)", [$userId, $index]);
    sendAnimation($userId, $item['file_id'], "🎭 <b>GIF " . ($index + 1) . "/" . $total . "</b>", $markup);
}

function handleGifNav($userId, $direction) {
    $db = Database::getInstance();
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM gifs")['cnt'];
    if ($total == 0) return;
    
    $indexRow = $db->fetch("SELECT gif_index FROM user_gif_index WHERE user_id = ?", [$userId]);
    $index = $indexRow ? $indexRow['gif_index'] : 0;
    
    $index = $direction === 'next' ? ($index + 1) % $total : ($index - 1 + $total) % $total;
    
    $item = $db->fetch("SELECT * FROM gifs LIMIT 1 OFFSET ?", [$index]);
    $markup = inlineKeyboard([
        [
            ['text' => '⬅️ Ortga', 'callback_data' => 'gif_prev'],
            ['text' => ($index + 1) . '/' . $total, 'callback_data' => 'noop'],
            ['text' => 'Keyingisi ➡️', 'callback_data' => 'gif_next'],
        ]
    ]);
    
    $db->query("INSERT OR REPLACE INTO user_gif_index (user_id, gif_index) VALUES (?, ?)", [$userId, $index]);
    sendAnimation($userId, $item['file_id'], "🎭 <b>GIF " . ($index + 1) . "/" . $total . "</b>", $markup);
}

function handleStickerWatch($userId) {
    $db = Database::getInstance();
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM stickers")['cnt'];
    if ($total == 0) {
        sendMessage($userId, "📭 Hozircha stikerlar mavjud emas.");
        return;
    }
    
    $indexRow = $db->fetch("SELECT sticker_index FROM user_sticker_index WHERE user_id = ?", [$userId]);
    $index = $indexRow ? $indexRow['sticker_index'] : 0;
    if ($index >= $total) $index = 0;
    
    $item = $db->fetch("SELECT * FROM stickers LIMIT 1 OFFSET ?", [$index]);
    
    $markup = inlineKeyboard([
        [
            ['text' => '⬅️ Ortga', 'callback_data' => 'sticker_prev'],
            ['text' => ($index + 1) . '/' . $total, 'callback_data' => 'noop'],
            ['text' => 'Keyingisi ➡️', 'callback_data' => 'sticker_next'],
        ]
    ]);
    
    $db->query("INSERT OR REPLACE INTO user_sticker_index (user_id, sticker_index) VALUES (?, ?)", [$userId, $index]);
    sendSticker($userId, $item['file_id'], $markup);
}

function handleStickerNav($userId, $direction) {
    $db = Database::getInstance();
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM stickers")['cnt'];
    if ($total == 0) return;
    
    $indexRow = $db->fetch("SELECT sticker_index FROM user_sticker_index WHERE user_id = ?", [$userId]);
    $index = $indexRow ? $indexRow['sticker_index'] : 0;
    $index = $direction === 'next' ? ($index + 1) % $total : ($index - 1 + $total) % $total;
    
    $item = $db->fetch("SELECT * FROM stickers LIMIT 1 OFFSET ?", [$index]);
    $markup = inlineKeyboard([
        [
            ['text' => '⬅️ Ortga', 'callback_data' => 'sticker_prev'],
            ['text' => ($index + 1) . '/' . $total, 'callback_data' => 'noop'],
            ['text' => 'Keyingisi ➡️', 'callback_data' => 'sticker_next'],
        ]
    ]);
    
    $db->query("INSERT OR REPLACE INTO user_sticker_index (user_id, sticker_index) VALUES (?, ?)", [$userId, $index]);
    sendSticker($userId, $item['file_id'], $markup);
}

function handleBoyChatSearch($userId) {
    $db = Database::getInstance();
    $total = $db->fetch("SELECT COUNT(*) as cnt FROM boy_chats")['cnt'];
    if ($total == 0) {
        sendMessage($userId, "😔 Hozircha erkak chatlari mavjud emas.\nAdmin tez orada qo'shadi.");
        return;
    }
    
    $indexRow = $db->fetch("SELECT last_index FROM boy_chat_index LIMIT 1");
    $index = $indexRow ? (int)$indexRow['last_index'] : 0;
    if ($index >= $total) $index = 0;
    
    $chat = $db->fetch("SELECT * FROM boy_chats LIMIT 1 OFFSET ?", [$index]);
    if (!$chat) {
        sendMessage($userId, "😔 Chat topilmadi, qayta urinib ko'ring.");
        return;
    }
    
    $newIndex = ($index + 1) % $total;
    $db->query("UPDATE boy_chat_index SET last_index = ? WHERE id = 1", [$newIndex]);
    
    $markup = inlineKeyboard([
        [['text' => '👨 Chatga o\'tish', 'url' => $chat['chat_link']]]
    ]);
    
    sendMessage($userId, "✅ <b>Mana suhbatdosh!</b>", $markup);
}

function handleGirlChatSearch($userId) {
    $db = Database::getInstance();
    $card = $db->fetch("SELECT * FROM cards WHERE is_active = 1 LIMIT 1");
    if (!$card) {
        sendMessage($userId, "⚠️ Hozircha to'lov tizimi mavjud emas. Keyinroq urinib ko'ring.");
        return;
    }
    
    $amount = $db->getSetting('payment_girl') ?? 5000;
    $text = "👩 <b>Qiz chat topish</b>\n\n";
    $text .= "💳 <b>Karta:</b> <code>{$card['card_number']}</code>\n";
    $text .= "👤 <b>Egasi:</b> {$card['card_owner']}\n";
    $text .= "💰 <b>Narx:</b> <b>{$amount} so'm</b>\n\n";
    $text .= "To'lovni amalga oshirib, quyidagi tugmani bosing 👇";
    
    $markup = inlineKeyboard([
        [['text' => '📸 Chek yuborish', 'callback_data' => 'send_check_girl']],
        [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_payment']],
    ]);
    sendMessage($userId, $text, $markup);
}

function handleWatchTogether($userId) {
    $db = Database::getInstance();
    $card = $db->fetch("SELECT * FROM cards WHERE is_active = 1 LIMIT 1");
    if (!$card) {
        sendMessage($userId, "⚠️ Hozircha to'lov tizimi mavjud emas.");
        return;
    }
    
    $amount = $db->getSetting('payment_watch') ?? 2000;
    $text = "🎥 <b>Birga video ko'rish</b>\n\n";
    $text .= "Do'stingiz bilan bir vaqtda video ko'ring!\n\n";
    $text .= "💳 <b>Karta:</b> <code>{$card['card_number']}</code>\n";
    $text .= "👤 <b>Egasi:</b> {$card['card_owner']}\n";
    $text .= "💰 <b>Narx:</b> <b>{$amount} so'm</b>\n\n";
    $text .= "To'lovni amalga oshirib, quyidagi tugmani bosing 👇";
    
    $markup = inlineKeyboard([
        [['text' => '📸 Chek yuborish', 'callback_data' => 'send_check_watch']],
        [['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_payment']],
    ]);
    sendMessage($userId, $text, $markup);
}

function handleHelp($userId) {
    $db = Database::getInstance();
    $admin = $db->fetch("SELECT * FROM admins LIMIT 1");
    
    $text = "❓ <b>Yordam</b>\n\nBotdan foydalanishda muammo bo'lsa, admindan so'rang:";
    
    if ($admin) {
        $adminUser = apiRequest('getChat', ['chat_id' => $admin['user_id']]);
        $username = '';
        if ($adminUser && $adminUser['ok']) {
            $username = $adminUser['result']['username'] ?? '';
        }
        $markup = inlineKeyboard([
            [['text' => '👨‍💼 Admin bilan bog\'lanish', 'url' => 'https://t.me/' . $username]]
        ]);
        sendMessage($userId, $text, $markup);
    } else {
        sendMessage($userId, $text . "\n\n@admin_username");
    }
}

function handlePaymentCheck($userId, $photoFileId, $state, $stateData) {
    $db = Database::getInstance();
    $data = json_decode($stateData, true);
    
    $paymentType = $data['type'] ?? 'unknown';
    $amount = $data['amount'] ?? 0;
    $extraData = json_encode($data['extra'] ?? []);
    
    $db->query("INSERT INTO payments (user_id, payment_type, amount, check_photo, extra_data) VALUES (?, ?, ?, ?, ?)",
        [$userId, $paymentType, $amount, $photoFileId, $extraData]);
    $paymentId = $db->getPdo()->lastInsertId();
    
    // Adminlarga xabar yuborish
    $admins = $db->fetchAll("SELECT user_id FROM admins");
    $adminIds = [MAIN_ADMIN_ID];
    foreach ($admins as $a) $adminIds[] = $a['user_id'];
    $adminIds = array_unique($adminIds);
    
    $user = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$userId]);
    $typeText = $paymentType === 'girl_search' ? '👩 Qiz bola qidirish' : '🎥 Birga video ko\'rish';
    
    $adminText = "💳 <b>Yangi to'lov tekshiruvi</b>\n\n";
    $adminText .= "👤 <b>Foydalanuvchi:</b> {$user['full_name']} (ID: {$userId})\n";
    $adminText .= "📌 <b>Xizmat:</b> {$typeText}\n";
    $adminText .= "💰 <b>Miqdor:</b> {$amount} so'm\n";
    $adminText .= "🆔 <b>To'lov ID:</b> #{$paymentId}";
    
    $adminMarkup = inlineKeyboard([
        [
            ['text' => '✅ Tasdiqlash', 'callback_data' => 'pay_approve_' . $paymentId],
            ['text' => '❌ Rad etish', 'callback_data' => 'pay_reject_' . $paymentId],
        ]
    ]);
    
    foreach ($adminIds as $adminId) {
        sendPhoto($adminId, $photoFileId, $adminText, $adminMarkup);
    }
    
    // Holatni tozala
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
    
    sendMessage($userId, "✅ <b>Chekingiz yuborildi!</b>\n\nAdmin tekshirib tez orada javob beradi.", replyKeyboard([['🏠 Bosh menyu']]));
}

function handleWatchTogetherPartner($userId, $partnerInput) {
    $db = Database::getInstance();
    
    // Partner ID yoki username ni aniqlash
    $partnerInput = trim($partnerInput);
    if (strpos($partnerInput, '@') === 0) {
        $partnerInput = substr($partnerInput, 1);
        $partnerData = apiRequest('getChat', ['chat_id' => '@' . $partnerInput]);
        if (!$partnerData || !$partnerData['ok']) {
            sendMessage($userId, "❌ Foydalanuvchi topilmadi. Iltimos to'g'ri username kiriting.");
            return;
        }
        $partnerId = $partnerData['result']['id'];
    } else {
        $partnerId = (int)$partnerInput;
    }
    
    if ($partnerId == $userId) {
        sendMessage($userId, "❌ O'zingizning ID ingizni kirita olmaysiz!");
        return;
    }
    
    // Token yaratish
    $token = generateToken(8);
    
    // Session saqlash
    $db->query("INSERT INTO watch_together_sessions (requester_id, partner_id, session_token) VALUES (?, ?, ?)",
        [$userId, $partnerId, $token]);
    
    // Partnerga qabul/rad tugmasi yuborish
    $markup = inlineKeyboard([
        [
            ['text' => '✅ Qabul qilish', 'callback_data' => 'watch_accept_' . $token],
            ['text' => '❌ Rad etish', 'callback_data' => 'watch_reject_' . $token],
        ]
    ]);
    
    $user = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$userId]);
    sendMessage($partnerId, "🎥 <b>{$user['full_name']} sizni birga video ko'rishga taklif qilmoqda!</b>\n\nQabul qilasizmi?", $markup);
    sendMessage($userId, "✅ <b>Taklif yuborildi!</b>\nDo'stingiz javob kutilmoqda...");
    
    $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
}
