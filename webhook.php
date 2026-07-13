<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/database/database.php';
require_once __DIR__ . '/utils/telegram.php';
require_once __DIR__ . '/utils/channel_check.php';
require_once __DIR__ . '/handlers/user_handler.php';
require_once __DIR__ . '/handlers/admin_handler.php';

// Input olish
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) exit;

// Asosiy ma'lumotlarni olish
$message = $update['message'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;

if ($message) {
    handleMessage($message);
} elseif ($callbackQuery) {
    handleCallbackQuery($callbackQuery);
}

// =====================
// MESSAGE HANDLER
// =====================
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $username = $message['from']['username'] ?? '';
    $fullName = trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? ''));
    $text = $message['text'] ?? '';
    $photo = $message['photo'] ?? null;
    $video = $message['video'] ?? null;
    $animation = $message['animation'] ?? null;
    $sticker = $message['sticker'] ?? null;

    $db = Database::getInstance();

    // Foydalanuvchi ma'lumotlarini yangilash
    $db->query("INSERT OR IGNORE INTO users (user_id, username, full_name) VALUES (?, ?, ?)", [$userId, $username, $fullName]);
    $db->query("UPDATE users SET username = ?, full_name = ? WHERE user_id = ?", [$username, $fullName, $userId]);

    $user = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$userId]);

    // /start buyrug'i
    if ($text === '/start') {
        // Oldingi holatni tozalash
        $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
        handleUserStart($userId, $username, $fullName);
        return;
    }

    // Admin panel tugmasi - FAQAT adminlarga
    if ($text === '🛠 Admin Panel' || $text === '/admin') {
        if (isAdmin($userId)) {
            sendAdminPanel($userId);
        }
        return;
    }

    // Foydalanuvchining joriy holati
    $currentState = $user['current_state'] ?? null;
    $stateData = $user['state_data'] ?? null;

    // =====================
    // ADMIN UPLOAD STATES
    // =====================
    if ($currentState && strpos($currentState, 'uploading_') === 0 && isAdmin($userId)) {
        $uploadType = str_replace('uploading_', '', $currentState);

        if ($text === '⛔ Stop (yuklashni tugatish)') {
            stopUploadSession($userId);
            return;
        }

        $session = $db->fetch("SELECT * FROM upload_sessions WHERE admin_id = ? AND is_active = 1", [$userId]);
        $count = $session ? $session['count'] + 1 : 1;

        if ($uploadType === 'video' && $video) {
            $fileId = $video['file_id'];
            handleUploadedFile($userId, $fileId, 'video', $count);
        } elseif ($uploadType === 'gif' && $animation) {
            $fileId = $animation['file_id'];
            handleUploadedFile($userId, $fileId, 'gif', $count);
        } elseif ($uploadType === 'sticker' && $sticker) {
            $fileId = $sticker['file_id'];
            handleUploadedFile($userId, $fileId, 'sticker', $count);
        } else {
            sendMessage($userId, "⚠️ Noto'g'ri fayl turi. " . strtoupper($uploadType) . " yuboring yoki ⛔ Stop bosing.");
        }
        return;
    }

    // =====================
    // ADMIN STATES
    // =====================
    if ($currentState && isAdmin($userId)) {
        if ($text === '❌ Bekor qilish' || $text === '🏠 Bosh menyu') {
            $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
            sendMessage($userId, "Bekor qilindi.", removeKeyboard());
            if ($text === '🏠 Bosh menyu') {
                sendMainMenu($userId);
            } else {
                sendAdminPanel($userId);
            }
            return;
        }

        switch ($currentState) {
            case 'waiting_boy_chat':
                saveBoyChat($userId, $text);
                return;
            case 'waiting_girl_chat':
                saveGirlChat($userId, $text);
                return;
            case 'waiting_channel_link':
                handleChannelLink($userId, $text);
                return;
            case 'waiting_channel_name':
                handleChannelName($userId, $text, $stateData);
                return;
            case 'waiting_card_number':
                handleCardNumber($userId, $text);
                return;
            case 'waiting_card_owner':
                handleCardOwner($userId, $text, $stateData);
                return;
            case 'waiting_new_admin_id':
                handleSaveAdmin($userId, $text);
                return;
            // Kanal post — istalgan turdagi xabar
            case 'waiting_chpost_content':
                handleChannelPostContent($userId, $message, $stateData);
                return;
            // Broadcast
            case 'waiting_broadcast_msg':
                handleBroadcastMessage($userId, $message);
                return;
        }
    }

    // =====================
    // USER STATES
    // =====================
    if ($currentState) {
        if ($text === '❌ Bekor qilish' || $text === '🏠 Bosh menyu') {
            $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
            sendMessage($userId, "❌ Bekor qilindi.", removeKeyboard());
            sendMainMenu($userId);
            return;
        }

        // To'lov cheki (rasm)
        if (($currentState === 'waiting_girl_check' || $currentState === 'waiting_watch_check') && $photo) {
            $fileId = end($photo)['file_id'];
            handlePaymentCheck($userId, $fileId, $currentState, $stateData);
            return;
        }

        // Birga video ko'rish - partner kiritish
        if ($currentState === 'waiting_watch_partner') {
            handleWatchTogetherPartner($userId, $text);
            return;
        }
    }

    // Jins tanlanmagan bo'lsa
    if (empty($user['gender'])) {
        handleUserStart($userId, $username, $fullName);
        return;
    }

    // Kanal tekshirish (har bir xabar uchun)
    if (!checkUserChannels($userId)) return;

    // =====================
    // ASOSIY MENYULAR
    // =====================
    switch ($text) {
        case '🎬 Video':
            handleVideoWatch($userId);
            break;
        case '🎭 GIF':
            handleGifWatch($userId);
            break;
        case '🌟 Stiker':
            handleStickerWatch($userId);
            break;
        case '👨 Erkak chat':
            handleBoyChatSearch($userId);
            break;
        case '👩 Qiz chat':
            handleGirlChatSearch($userId);
            break;
        case '🎥 Birga video':
            handleWatchTogether($userId);
            break;
        case '❓ Yordam':
            handleHelp($userId);
            break;
        case '🏠 Bosh menyu':
            sendMainMenu($userId);
            break;
        case '🛠 Admin Panel':
            if (isAdmin($userId)) {
                sendAdminPanel($userId);
            }
            break;
        default:
            if (!$currentState) {
                sendMainMenu($userId);
            }
            break;
    }
}

// =====================
// CALLBACK HANDLER
// =====================
function handleCallbackQuery($callbackQuery) {
    $callbackId = $callbackQuery['id'];
    $userId = $callbackQuery['from']['id'];
    $data = $callbackQuery['data'];
    $messageId = $callbackQuery['message']['message_id'];
    $chatId = $callbackQuery['message']['chat']['id'];

    $db = Database::getInstance();
    $user = $db->fetch("SELECT * FROM users WHERE user_id = ?", [$userId]);

    answerCallbackQuery($callbackId);

    // Jins tanlash
    if ($data === 'gender_male') {
        handleGenderSelection($userId, 'male');
        return;
    }
    if ($data === 'gender_female') {
        handleGenderSelection($userId, 'female');
        return;
    }

    // Kanal tekshirish
    if ($data === 'check_subscription') {
        if (checkUserChannels($userId)) {
            sendMainMenu($userId);
        }
        return;
    }

    // Video navigatsiya
    if ($data === 'video_next') { handleVideoNav($userId, 'next'); return; }
    if ($data === 'video_prev') { handleVideoNav($userId, 'prev'); return; }
    if ($data === 'gif_next') { handleGifNav($userId, 'next'); return; }
    if ($data === 'gif_prev') { handleGifNav($userId, 'prev'); return; }
    if ($data === 'sticker_next') { handleStickerNav($userId, 'next'); return; }
    if ($data === 'sticker_prev') { handleStickerNav($userId, 'prev'); return; }

    // Hech narsa qilmaydi
    if ($data === 'noop') return;

    // To'lov bekor qilish
    if ($data === 'cancel_payment') {
        $db->query("UPDATE users SET current_state = NULL, state_data = NULL WHERE user_id = ?", [$userId]);
        sendMessage($userId, "❌ Bekor qilindi.");
        return;
    }

    // Chek yuborish - qiz chat
    if ($data === 'send_check_girl') {
        $db2 = Database::getInstance();
        $card = $db2->fetch("SELECT * FROM cards WHERE is_active = 1 LIMIT 1");
        $amount = $db2->getSetting('payment_girl') ?? 5000;
        $db2->query("UPDATE users SET current_state = 'waiting_girl_check', state_data = ? WHERE user_id = ?",
            [json_encode(['type' => 'girl_search', 'amount' => $amount]), $userId]);
        $markup = replyKeyboard([['❌ Bekor qilish']]);
        sendMessage($userId, "📸 <b>Chek rasmini yuboring:</b>", $markup);
        return;
    }

    // Chek yuborish - birga video
    if ($data === 'send_check_watch') {
        $db2 = Database::getInstance();
        $amount = $db2->getSetting('payment_watch') ?? 2000;
        $db2->query("UPDATE users SET current_state = 'waiting_watch_check', state_data = ? WHERE user_id = ?",
            [json_encode(['type' => 'watch_together', 'amount' => $amount]), $userId]);
        $markup = replyKeyboard([['❌ Bekor qilish']]);
        sendMessage($userId, "📸 <b>Chek rasmini yuboring:</b>", $markup);
        return;
    }

    // =====================
    // ADMIN CALLBACKS
    // =====================
    if (!isAdmin($userId)) return;

    // Foydalanuvchilar
    if ($data === 'admin_users') {
        $markup = inlineKeyboard([
            [
                ['text' => '👨 Erkaklar', 'callback_data' => 'admin_users_male_0'],
                ['text' => '👩 Ayollar', 'callback_data' => 'admin_users_female_0'],
            ],
            [['text' => '🔙 Orqaga', 'callback_data' => 'admin_back']],
        ]);
        sendMessage($userId, "👥 <b>Foydalanuvchilar</b>\n\nQaysi jinsdagilarni ko'rmoqchisiz?", $markup);
        return;
    }

    if (preg_match('/^admin_users_(male|female)_(\d+)$/', $data, $m)) {
        handleAdminUsers($userId, $m[1], (int)$m[2]);
        return;
    }

    // Admin panel orqaga
    if ($data === 'admin_back') {
        sendAdminPanel($userId);
        return;
    }

    // Yuklash
    if ($data === 'admin_upload_video') { startUploadSession($userId, 'video'); return; }
    if ($data === 'admin_upload_gif') { startUploadSession($userId, 'gif'); return; }
    if ($data === 'admin_upload_sticker') { startUploadSession($userId, 'sticker'); return; }

    // O'chirish
    if ($data === 'admin_delete_menu') { handleDeleteMenu($userId); return; }
    if ($data === 'admin_delete_video') { handleDeleteList($userId, 'video'); return; }
    if ($data === 'admin_delete_gif') { handleDeleteList($userId, 'gif'); return; }
    if ($data === 'admin_delete_sticker') { handleDeleteList($userId, 'sticker'); return; }
    if ($data === 'admin_delete_boy_chat') { handleDeleteList($userId, 'boy_chat'); return; }
    if ($data === 'admin_delete_girl_chat') { handleDeleteList($userId, 'girl_chat'); return; }
    if ($data === 'admin_delete_channel') { handleDeleteList($userId, 'channel'); return; }

    // O'chirish - item
    if (preg_match('/^delete_(video|gif|sticker|boy_chat|girl_chat|channel|card)_(\d+)$/', $data, $m)) {
        handleDeleteItem($userId, $m[1], (int)$m[2]);
        return;
    }

    // Chat qo'shish
    if ($data === 'admin_add_boy_chat') { handleAddBoyChat($userId); return; }
    if ($data === 'admin_add_girl_chat') { handleAddGirlChat($userId); return; }

    // Kanal
    if ($data === 'admin_add_channel') { handleAddChannel($userId); return; }
    if ($data === 'admin_list_channels') { handleListChannels($userId); return; }

    // Kartalar
    if ($data === 'admin_add_card') { handleAddCard($userId); return; }
    if ($data === 'admin_list_cards') { handleListCards($userId); return; }

    // Statistika
    if ($data === 'admin_stats') { handleAdminStats($userId); return; }

    // Reklama
    if ($data === 'admin_broadcast') { startBroadcast($userId); return; }

    // Kanalga post
    if ($data === 'admin_channel_post') { handleChannelPostStart($userId); return; }
    if (preg_match('/^chpost_(\d+)$/', $data, $m)) {
        handleChannelPostSelectChannel($userId, (int)$m[1]);
        return;
    }

    // To'lovlar
    if ($data === 'admin_payments') { handleAdminPayments($userId); return; }

    if (preg_match('/^pay_approve_(\d+)$/', $data, $m)) {
        handlePaymentApprove($userId, (int)$m[1], $chatId, $messageId);
        return;
    }
    if (preg_match('/^pay_reject_(\d+)$/', $data, $m)) {
        handlePaymentReject($userId, (int)$m[1], $chatId, $messageId);
        return;
    }

    // Adminlar boshqaruvi (faqat bosh admin)
    if ($data === 'admin_manage_admins' && isMainAdmin($userId)) {
        handleManageAdmins($userId);
        return;
    }
    if ($data === 'add_admin' && isMainAdmin($userId)) {
        handleAddAdmin($userId);
        return;
    }
    if (preg_match('/^remove_admin_(\d+)$/', $data, $m) && isMainAdmin($userId)) {
        handleRemoveAdmin($userId, (int)$m[1]);
        return;
    }

    // Birga video ko'rish - qabul/rad
    if (preg_match('/^watch_accept_(.+)$/', $data, $m)) {
        handleWatchAccept($userId, $m[1]);
        return;
    }
    if (preg_match('/^watch_reject_(.+)$/', $data, $m)) {
        handleWatchReject($userId, $m[1]);
        return;
    }
}

// Birga video ko'rish - qabul
function handleWatchAccept($partnerId, $token) {
    $db = Database::getInstance();
    $session = $db->fetch("SELECT * FROM watch_together_sessions WHERE session_token = ? AND is_active = 1", [$token]);
    
    if (!$session) {
        sendMessage($partnerId, "❌ Bu taklif muddati o'tgan.");
        return;
    }
    
    $requesterId = $session['requester_id'];
    $watchUrl = WATCH_TOGETHER_URL . '?token=' . $token;
    
    $markup = inlineKeyboard([
        [['text' => '🎥 Birga ko\'rish', 'url' => $watchUrl]]
    ]);
    
    sendMessage($requesterId, "✅ <b>Do'stingiz qabul qildi!</b>\nQuyidagi tugmani bosing:", $markup);
    sendMessage($partnerId, "✅ <b>Qabul qildingiz!</b>\nQuyidagi tugmani bosing:", $markup);
}

// Birga video ko'rish - rad
function handleWatchReject($partnerId, $token) {
    $db = Database::getInstance();
    $session = $db->fetch("SELECT * FROM watch_together_sessions WHERE session_token = ? AND is_active = 1", [$token]);
    
    if (!$session) return;
    
    $db->query("UPDATE watch_together_sessions SET is_active = 0 WHERE session_token = ?", [$token]);
    sendMessage($session['requester_id'], "❌ Do'stingiz taklifni rad etdi.");
    sendMessage($partnerId, "✅ Rad etdingiz.");
}
