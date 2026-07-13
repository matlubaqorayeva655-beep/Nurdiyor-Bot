<?php
require_once __DIR__ . '/../config/config.php';

function apiRequest($method, $params = []) {
    $url = API_URL . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'HTML') {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    return apiRequest('sendMessage', $params);
}

function sendPhoto($chatId, $photo, $caption = '', $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'photo' => $photo,
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    return apiRequest('sendPhoto', $params);
}

function sendVideo($chatId, $video, $caption = '', $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'video' => $video,
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    return apiRequest('sendVideo', $params);
}

function sendAnimation($chatId, $animation, $caption = '', $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'animation' => $animation,
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    return apiRequest('sendAnimation', $params);
}

function sendSticker($chatId, $sticker, $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'sticker' => $sticker,
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    return apiRequest('sendSticker', $params);
}

function editMessage($chatId, $messageId, $text, $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    return apiRequest('editMessageText', $params);
}

function deleteMessage($chatId, $messageId) {
    return apiRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
    ]);
}

// Inline tugmalarni o'chirish (faqat klaviaturani olib tashla)
function removeInlineKeyboard($chatId, $messageId) {
    return apiRequest('editMessageReplyMarkup', [
        'chat_id'      => $chatId,
        'message_id'   => $messageId,
        'reply_markup' => ['inline_keyboard' => []],
    ]);
}

function answerCallbackQuery($callbackId, $text = '', $showAlert = false) {
    return apiRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $showAlert,
    ]);
}

function getChatMember($chatId, $userId) {
    return apiRequest('getChatMember', [
        'chat_id' => $chatId,
        'user_id' => $userId,
    ]);
}

function inlineKeyboard($buttons) {
    return ['inline_keyboard' => $buttons];
}

function replyKeyboard($buttons, $resize = true, $oneTime = false) {
    return [
        'keyboard' => $buttons,
        'resize_keyboard' => $resize,
        'one_time_keyboard' => $oneTime,
    ];
}

function removeKeyboard() {
    return ['remove_keyboard' => true];
}

function generateToken($length = 16) {
    return bin2hex(random_bytes($length));
}

// Xabarni copy qilish (broadcast uchun - "Forwarded from" ko'rsatmaydi)
function copyMessage($chatId, $fromChatId, $messageId, $replyMarkup = null) {
    $params = [
        'chat_id'      => $chatId,
        'from_chat_id' => $fromChatId,
        'message_id'   => $messageId,
    ];
    if ($replyMarkup) $params['reply_markup'] = $replyMarkup;
    return apiRequest('copyMessage', $params);
}
