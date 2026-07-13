<?php
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/telegram.php';

function checkUserChannels($userId) {
    $db = Database::getInstance();
    $channels = $db->fetchAll("SELECT * FROM channels WHERE is_active = 1");
    
    if (empty($channels)) return true;
    
    $notJoined = [];
    foreach ($channels as $channel) {
        $result = getChatMember($channel['channel_id'], $userId);
        // API xatosi bo'lsa (bot admin emas yoki boshqa xato) - o'tkazib yuboramiz
        if (!$result || !$result['ok']) {
            continue;
        }
        $status = $result['result']['status'] ?? 'member';
        if (in_array($status, ['left', 'kicked'])) {
            $notJoined[] = $channel;
        }
    }
    
    if (!empty($notJoined)) {
        $buttons = [];
        foreach ($notJoined as $ch) {
            $buttons[] = [['text' => '📢 ' . $ch['channel_name'], 'url' => $ch['channel_link']]];
        }
        $buttons[] = [['text' => '✅ A\'zo bo\'ldim', 'callback_data' => 'check_subscription']];
        sendMessage($userId, "⚠️ <b>Botdan foydalanish uchun quyidagi kanallarga a'zo bo'ling:</b>", inlineKeyboard($buttons));
        return false;
    }
    return true;
}
