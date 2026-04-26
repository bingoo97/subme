<?php

require_once __DIR__ . '/../bootstrap/chat.php';

if (empty($settings['support_chat_enabled']) || !isset($user['logged']) || !$user['logged']) {
    $smarty->assign('chat', []);
    $smarty->assign('chat_nieprzeczytane', 0);
    $smarty->assign('chat_last_message_id', 0);
    $smarty->assign('chat_faq_prompts', []);
    return;
}

chat_purge_expired_messages($db, chat_retention_days($settings));

$chatLocaleCode = 'en';
if (isset($currentLocale)) {
    $chatLocaleCode = (string)$currentLocale;
} elseif (isset($user['lang_code'])) {
    $chatLocaleCode = (string)$user['lang_code'];
} elseif (isset($user['locale_code'])) {
    $chatLocaleCode = (string)$user['locale_code'];
}

$chatFaqPrompts = chat_load_faq_prompts($db, $chatLocaleCode, 5);
$smarty->assign('chat_faq_prompts', $chatFaqPrompts);
$chatSupportLabel = chat_default_support_label($db, $reseller);
$smarty->assign('chat_support_label', $chatSupportLabel);
$requestedConversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : (isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0);

if (app_uses_v2_schema($db)) {
    $chatConversations = chat_customer_conversation_list($db, $user, $reseller, $chatSupportLabel);
    $chatActiveConversation = chat_customer_selected_conversation($db, $user, $requestedConversationId);
    $chatMessages = chat_messages_for_customer_conversation($db, $user, $chatActiveConversation, $reseller, $chatSupportLabel);
    $normalizedChat = chat_normalize_messages($chatMessages ?: [], (int)$user['id'], $reseller, $chatSupportLabel);
    $chatUnreadCount = 0;
    foreach ($chatConversations as $chatConversation) {
        $chatUnreadCount += (int)($chatConversation['unread_count'] ?? 0);
    }
    $lastMessageId = !empty($normalizedChat) ? (int)$normalizedChat[count($normalizedChat) - 1]['id'] : 0;
    $activeConversationId = (int)($chatActiveConversation['id'] ?? 0);
    $activeConversationType = (string)($chatActiveConversation['type'] ?? 'live_chat');
    $activeConversationRow = (array)($chatActiveConversation['row'] ?? []);
    $activeCanSend = true;
    if ($activeConversationType === 'group_chat' && !empty($activeConversationRow['is_group_read_only'])) {
        $activeCanSend = false;
    }

    $smarty->assign('chat', $normalizedChat);
    $smarty->assign('chat_conversations', $chatConversations);
    $smarty->assign('chat_active_conversation_id', $activeConversationId);
    $smarty->assign('chat_active_conversation_type', $activeConversationType);
    $smarty->assign('chat_active_conversation_title', $activeConversationType === 'group_chat' ? chat_group_conversation_title($activeConversationRow) : $chatSupportLabel);
    $smarty->assign('chat_active_conversation_is_group', $activeConversationType === 'group_chat');
    $smarty->assign('chat_active_conversation_is_read_only', $activeConversationType === 'group_chat' && !empty($activeConversationRow['is_group_read_only']));
    $smarty->assign('chat_active_conversation_can_send', $activeCanSend);
    $smarty->assign('chat_active_conversation_can_leave', $activeConversationType === 'group_chat');
    $smarty->assign('chat_active_conversation_is_owned', $activeConversationType === 'group_chat' && (int)($activeConversationRow['group_created_by_customer_id'] ?? 0) === (int)$user['id']);
    $smarty->assign('chat_active_conversation_can_manage', $activeConversationType === 'group_chat' && (int)($activeConversationRow['group_created_by_customer_id'] ?? 0) === (int)$user['id']);
    $chatGroupCreationState = chat_customer_group_creation_state($db, $user, is_array($settings ?? null) ? $settings : []);
    $smarty->assign('chat_customer_can_create_groups', !empty($chatGroupCreationState['allowed']));
    $smarty->assign('chat_customer_group_creation_state', $chatGroupCreationState);
    $smarty->assign('chat_nieprzeczytane', $chatUnreadCount);
    $smarty->assign('chat_last_message_id', $lastMessageId);
    return;
}

$zapytanie = "SELECT * FROM produkty_chat WHERE 
            (produkty_chat.user1 = {$user["id"]} or produkty_chat.user2 = {$user["id"]}) 
              ORDER BY data ASC";
$chat = $db->select_full_user($zapytanie);

if ($chat) {
    $chat = chat_normalize_messages($chat, (int)$user['id'], $reseller, $chatSupportLabel);
}

$chat_nieprzeczytane = 0;
foreach (($chat ?: []) as $chatRow) {
    if (!empty($chatRow['is_unread'])) {
        $chat_nieprzeczytane++;
    }
}

$lastMessageId = !empty($chat) ? (int)$chat[count($chat) - 1]['id'] : 0;
$smarty->assign('chat', $chat ?: []);
$smarty->assign('chat_nieprzeczytane', $chat_nieprzeczytane);
$smarty->assign('chat_last_message_id', $lastMessageId);
?>
