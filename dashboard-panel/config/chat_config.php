<?php

require_once __DIR__ . '/../bootstrap/chat.php';

if (!app_support_chat_effective_enabled(is_array($settings ?? null) ? $settings : []) || !isset($user['logged']) || !$user['logged']) {
    $smarty->assign('chat', []);
    $smarty->assign('chat_nieprzeczytane', 0);
    $smarty->assign('chat_last_message_id', 0);
    $smarty->assign('chat_faq_prompts', []);
    return;
}

chat_purge_expired_messages($db, chat_retention_days($settings));

$chatLocaleCode = 'en';
if (isset($user['locale_code']) && (string)$user['locale_code'] !== '') {
    $chatLocaleCode = (string)$user['locale_code'];
} elseif (isset($currentLocale)) {
    $chatLocaleCode = (string)$currentLocale;
} elseif (isset($user['lang_code'])) {
    $chatLocaleCode = (string)$user['lang_code'];
}
$chatLocaleCode = localization_normalize_locale($chatLocaleCode);

$chatFaqPrompts = chat_load_faq_prompts($db, $chatLocaleCode, 5);
$smarty->assign('chat_faq_prompts', $chatFaqPrompts);
$chatSupportLabel = localization_translate(isset($t) && is_array($t) ? $t : [], 'support', 'Support');
$smarty->assign('chat_support_label', $chatSupportLabel);
$chatCustomerIsBlocked = false;
$requestedConversationId = isset($chatRenderConversationId) && $chatRenderConversationId !== null
    ? (int)$chatRenderConversationId
    : (isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : (isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0));
$chatMessageLimit = function_exists('chat_customer_normalize_message_limit')
    ? chat_customer_normalize_message_limit($_POST['message_limit'] ?? $_GET['message_limit'] ?? 0)
    : 10;

if (app_uses_v2_schema($db)) {
    if (function_exists('chat_demo_showcase_sync')) {
        chat_demo_showcase_sync($db, is_array($settings ?? null) ? $settings : [], ['emit_messages' => true, 'source' => 'customer_chat']);
    }
    if (chat_customer_global_group_enabled($user, is_array($settings ?? null) ? $settings : [])) {
        chat_sync_global_group_members($db, is_array($settings ?? null) ? $settings : []);
    }
    $chatCustomerFullMessenger = chat_customer_can_use_groups($user, is_array($settings ?? null) ? $settings : []);
    $chatConversations = chat_customer_conversation_list($db, $user, $reseller, $chatSupportLabel, is_array($settings ?? null) ? $settings : []);
    $chatActiveConversation = chat_customer_selected_conversation($db, $user, $requestedConversationId, is_array($settings ?? null) ? $settings : []);
    $chatMessages = chat_messages_for_customer_conversation($db, $user, $chatActiveConversation, $reseller, $chatSupportLabel, $chatMessageLimit);
    $chatTotalMessages = function_exists('chat_messages_total_for_customer_conversation')
        ? chat_messages_total_for_customer_conversation($db, $user, $chatActiveConversation)
        : count($chatMessages ?: []);
    $normalizedChat = chat_normalize_messages($chatMessages ?: [], (int)$user['id'], $reseller, $chatSupportLabel);
    $chatUnreadCount = 0;
    foreach ($chatConversations as $chatConversation) {
        $chatUnreadCount += (int)($chatConversation['unread_count'] ?? 0);
    }
    $lastMessageId = !empty($normalizedChat) ? (int)$normalizedChat[count($normalizedChat) - 1]['id'] : 0;
    $oldestMessageId = !empty($normalizedChat) ? (int)$normalizedChat[0]['id'] : 0;
    $loadedMessageCount = count($normalizedChat);
    $hasMoreMessages = $chatTotalMessages > $loadedMessageCount;
    $activeConversationId = (int)($chatActiveConversation['id'] ?? 0);
    $activeConversationType = (string)($chatActiveConversation['type'] ?? 'live_chat');
    $activeConversationRow = (array)($chatActiveConversation['row'] ?? []);
    $activeCanSend = true;
    $activeConversationMemberCount = 0;
    $activeConversationMemberCountLabel = '';
    $activeConversationMemberEmails = [];
    $activeConversationMembers = [];
    $activeConversationTitle = chat_is_group_like_conversation_type($activeConversationType) ? chat_group_conversation_title($activeConversationRow) : $chatSupportLabel;
    $activeConversationSubtitle = chat_is_group_like_conversation_type($activeConversationType) ? 'Group' : 'Support';
    $activeConversationAvatarUrl = '';
    $activeConversationAvatarText = chat_is_group_like_conversation_type($activeConversationType) ? 'G' : 'S';
    $activeConversationAvatarTheme = 'theme-6';
    $activeConversationPresence = function_exists('chat_support_presence_payload') ? chat_support_presence_payload($db) : ['key' => 'offline', 'label' => 'Offline', 'class_name' => 'admin-chat-presence admin-chat-presence--offline'];
    $activeMemberEmailNotificationsEnabled = true;
    $activeConversationRetentionHours = null;
    $activeConversationRetentionLabel = '';
    $activeConversationRetentionInputValue = '24h';
    $activeConversationIsDirect = false;
    $activeConversationDirectStatus = 'none';
    $activeConversationDirectTargetCustomerId = 0;
    $activeConversationHasPendingInvite = false;
    $activeConversationPendingMemberCount = 0;
    if (chat_is_group_like_conversation_type($activeConversationType) && !empty($activeConversationRow['is_group_read_only'])) {
        $activeCanSend = false;
    }
    if (!chat_is_group_like_conversation_type($activeConversationType)) {
        $activeConversationAvatarUrl = function_exists('chat_support_avatar_url') ? chat_support_avatar_url($db) : '';
    }
    if (chat_is_group_like_conversation_type($activeConversationType) && $activeConversationId > 0) {
        $summary = chat_group_conversation_summary(
            $db,
            $activeConversationId,
            ['participant_type' => 'customer', 'customer_id' => (int)$user['id'], 'admin_user_id' => 0],
            $activeConversationRow
        );
        $activeConversationMemberCount = chat_group_member_count($db, $activeConversationId);
        $activeConversationMemberCountLabel = '';
        $activeConversationMemberEmails = chat_group_member_emails($db, $activeConversationId);
        $activeConversationMembers = isset($summary['members']) && is_array($summary['members']) ? $summary['members'] : [];
        $activeConversationTitle = (string)($summary['title'] ?? $activeConversationTitle);
        $activeConversationSubtitle = (string)($summary['subtitle'] ?? $activeConversationSubtitle);
        $activeConversationAvatarUrl = (string)($summary['avatar_url'] ?? '');
        $activeConversationAvatarText = (string)($summary['avatar_text'] ?? 'G');
        $activeConversationAvatarTheme = (string)($summary['avatar_theme'] ?? 'theme-6');
        $activeConversationIsDirect = !empty($summary['is_direct']);
        $activeConversationDirectStatus = (string)($summary['direct_status'] ?? 'none');
        $activeConversationDirectTargetCustomerId = (int)($summary['direct_target_customer_id'] ?? 0);
        $activeConversationHasPendingInvite = !empty($summary['has_pending_invite']);
        $activeConversationPendingMemberCount = max(0, (int)($summary['pending_member_count'] ?? 0));
        $activeConversationPresence = is_array($summary['presence'] ?? null) ? $summary['presence'] : $activeConversationPresence;
        $activeConversationRetentionHours = chat_group_retention_minutes_from_row($activeConversationRow);
        $activeConversationRetentionLabel = chat_group_retention_label($activeConversationRetentionHours);
        $activeConversationRetentionInputValue = chat_group_retention_input_value($activeConversationRetentionHours);
        $activeMemberRow = chat_group_member_row($db, $activeConversationId, chat_participant_key_for_customer((int)$user['id']));
        if (is_array($activeMemberRow) && array_key_exists('email_notifications_enabled', $activeMemberRow)) {
            $activeMemberEmailNotificationsEnabled = (int)($activeMemberRow['email_notifications_enabled'] ?? 1) !== 0;
        }
        if (chat_is_global_group_conversation_type($activeConversationType)) {
            $activeConversationSubtitle = 'Global';
            $activeConversationMemberCount = 0;
            foreach ($activeConversationMembers as $activeConversationMember) {
                $isCurrentCustomer = (string)($activeConversationMember['participant_type'] ?? '') === 'customer'
                    && (int)($activeConversationMember['customer_id'] ?? 0) === (int)$user['id'];
                $presenceKey = trim((string)($activeConversationMember['presence_key'] ?? 'offline'));
                if ($isCurrentCustomer || $presenceKey === 'online') {
                    $activeConversationMemberCount++;
                }
            }
            if ($activeConversationMemberCount === 1) {
                $activeConversationMemberCountLabel = localization_translate(
                    isset($t) && is_array($t) ? $t : [],
                    'chat_global_group_member_count_single',
                    '1 active user'
                );
                if ($activeConversationMemberCountLabel === 'chat_global_group_member_count_single') {
                    $activeConversationMemberCountLabel = '1 active user';
                }
            } else {
                $activeConversationMemberCountLabel = localization_translate(
                    isset($t) && is_array($t) ? $t : [],
                    'chat_global_group_member_count_many',
                    [
                        'count' => $activeConversationMemberCount,
                    ]
                );
                if ($activeConversationMemberCountLabel === 'chat_global_group_member_count_many') {
                    $activeConversationMemberCountLabel = $activeConversationMemberCount . ' active users';
                }
            }
        } elseif (!empty($summary['subtitle'])) {
            if (!$activeConversationIsDirect) {
                $activeConversationMembers = isset($summary['members_with_pending']) && is_array($summary['members_with_pending'])
                    ? $summary['members_with_pending']
                    : $activeConversationMembers;
                $activeConversationMemberCount = (int)($summary['member_count_with_pending'] ?? count($activeConversationMembers));
            }
            $activeConversationSubtitle = (string)$summary['subtitle'];
            $baseMemberCountLabel = $activeConversationMemberCount === 1
                ? localization_translate(isset($t) && is_array($t) ? $t : [], 'chat_group_member_count_single', '1 member')
                : localization_translate(
                    isset($t) && is_array($t) ? $t : [],
                    'chat_group_member_count_many',
                    ['count' => $activeConversationMemberCount]
                );
            if ($activeConversationMemberCount !== 1 && $baseMemberCountLabel === 'chat_group_member_count_many') {
                $baseMemberCountLabel = $activeConversationMemberCount . ' members';
            }
            $activeConversationMemberCountLabel = $baseMemberCountLabel;
            if ($activeConversationPendingMemberCount > 0) {
                $pendingMemberCountLabel = $activeConversationPendingMemberCount === 1
                    ? localization_translate(isset($t) && is_array($t) ? $t : [], 'chat_group_pending_count_single', '1 pending')
                    : localization_translate(
                        isset($t) && is_array($t) ? $t : [],
                        'chat_group_pending_count_many',
                        ['count' => $activeConversationPendingMemberCount]
                    );
                if ($activeConversationPendingMemberCount !== 1 && $pendingMemberCountLabel === 'chat_group_pending_count_many') {
                    $pendingMemberCountLabel = $activeConversationPendingMemberCount . ' pending';
                }
                $activeConversationMemberCountLabel .= ' · ' . $pendingMemberCountLabel;
            }
        }
        $activeMemberInviteStatus = trim((string)($activeMemberRow['invite_status'] ?? ''));
        if ((int)($activeMemberRow['can_post'] ?? 1) === 0 || $activeMemberInviteStatus !== 'accepted' || !empty($summary['has_pending_invite']) || in_array($activeConversationDirectStatus, ['pending', 'rejected'], true)) {
            $activeCanSend = false;
        }
        $activeConversationOwnedByCustomer = (int)($activeConversationRow['group_created_by_customer_id'] ?? 0) === (int)$user['id'];
        if (
            !$activeConversationIsDirect
            && $activeConversationOwnedByCustomer
            && $activeConversationPendingMemberCount > 0
            && $activeConversationMemberCount <= 1
        ) {
            $activeCanSend = false;
        }
        if (chat_is_global_group_conversation_type($activeConversationType) && (int)($activeMemberRow['global_chat_blocked'] ?? 0) !== 0) {
            $chatCustomerIsBlocked = true;
            $activeCanSend = false;
        }
    }

    if (chat_is_group_like_conversation_type($activeConversationType) && !empty($normalizedChat) && !empty($chatMessages)) {
        $canManageGroupMessages = (int)($activeConversationRow['group_created_by_customer_id'] ?? 0) === (int)$user['id'];
        $rawMessageMap = [];

        foreach ($chatMessages as $chatMessageRow) {
            $rawMessageId = (int)($chatMessageRow['id'] ?? 0);
            if ($rawMessageId > 0) {
                $rawMessageMap[$rawMessageId] = $chatMessageRow;
            }
        }

        foreach ($normalizedChat as $chatIndex => $normalizedMessage) {
            $normalizedMessageId = (int)($normalizedMessage['id'] ?? 0);
            $rawMessageRow = isset($rawMessageMap[$normalizedMessageId]) && is_array($rawMessageMap[$normalizedMessageId]) ? $rawMessageMap[$normalizedMessageId] : [];
            $isOwnGroupMessage = (string)($rawMessageRow['sender_type'] ?? '') === 'customer'
                && (int)($rawMessageRow['customer_id'] ?? 0) === (int)$user['id'];
            $canDeleteMessage = $canManageGroupMessages || $isOwnGroupMessage;

            $normalizedChat[$chatIndex]['can_delete'] = $canDeleteMessage;
            $normalizedChat[$chatIndex]['delete_mode'] = 'icon';
            $normalizedChat[$chatIndex]['delete_forever'] = $canDeleteMessage;
        }
    }

    $smarty->assign('chat', $normalizedChat);
    $smarty->assign('chat_conversations', $chatConversations);
    $smarty->assign('chat_message_limit', $chatMessageLimit);
    $smarty->assign('chat_loaded_message_count', $loadedMessageCount);
    $smarty->assign('chat_total_message_count', $chatTotalMessages);
    $smarty->assign('chat_has_more_messages', $hasMoreMessages);
    $smarty->assign('chat_oldest_message_id', $oldestMessageId);
    $smarty->assign('chat_active_conversation_id', $activeConversationId);
    $smarty->assign('chat_active_conversation_type', $activeConversationType);
    $smarty->assign('chat_active_conversation_title', $activeConversationTitle);
    $smarty->assign('chat_active_conversation_subtitle', $activeConversationSubtitle);
    $smarty->assign('chat_active_conversation_is_group', chat_is_group_like_conversation_type($activeConversationType));
    $smarty->assign('chat_active_conversation_is_direct', $activeConversationIsDirect);
    $smarty->assign('chat_active_conversation_direct_status', $activeConversationDirectStatus);
    $smarty->assign('chat_active_conversation_direct_target_customer_id', $activeConversationDirectTargetCustomerId);
    $smarty->assign('chat_active_conversation_has_pending_invite', $activeConversationHasPendingInvite);
    $smarty->assign('chat_active_conversation_pending_member_count', $activeConversationPendingMemberCount);
    $smarty->assign('chat_active_conversation_is_global_group', chat_is_global_group_conversation_type($activeConversationType));
    $smarty->assign('chat_active_conversation_is_read_only', chat_is_group_like_conversation_type($activeConversationType) && !empty($activeConversationRow['is_group_read_only']));
    $smarty->assign('chat_active_conversation_can_send', $activeCanSend);
    $smarty->assign('chat_customer_is_blocked', $chatCustomerIsBlocked);
    $chatActiveConversationIsOwned = chat_is_group_like_conversation_type($activeConversationType)
        && (int)($activeConversationRow['group_created_by_customer_id'] ?? 0) === (int)$user['id'];
    $smarty->assign(
        'chat_active_conversation_can_leave',
        chat_is_group_like_conversation_type($activeConversationType)
            && !chat_is_global_group_conversation_type($activeConversationType)
            && (!$chatActiveConversationIsOwned || $activeConversationIsDirect)
    );
    $smarty->assign('chat_active_conversation_is_owned', $chatActiveConversationIsOwned);
    $smarty->assign('chat_active_conversation_can_manage', $chatActiveConversationIsOwned);
    $smarty->assign('chat_active_conversation_member_count', $activeConversationMemberCount);
    $smarty->assign('chat_active_conversation_member_count_label', $activeConversationMemberCountLabel);
    $smarty->assign('chat_active_conversation_member_emails', $activeConversationMemberEmails);
    $smarty->assign('chat_active_conversation_members', $activeConversationMembers);
    $smarty->assign('chat_active_conversation_avatar_url', $activeConversationAvatarUrl);
    $smarty->assign('chat_active_conversation_avatar_text', $activeConversationAvatarText);
    $smarty->assign('chat_active_conversation_avatar_theme', $activeConversationAvatarTheme);
    $smarty->assign('chat_active_conversation_presence_key', (string)($activeConversationPresence['key'] ?? 'offline'));
    $smarty->assign('chat_active_conversation_presence_label', (string)($activeConversationPresence['label'] ?? 'Offline'));
    $smarty->assign('chat_active_conversation_presence_class_name', (string)($activeConversationPresence['class_name'] ?? 'admin-chat-presence admin-chat-presence--offline'));
    $smarty->assign('chat_active_member_email_notifications_enabled', $activeMemberEmailNotificationsEnabled);
    $smarty->assign('chat_active_conversation_retention_hours', $activeConversationRetentionHours);
    $smarty->assign('chat_active_conversation_retention_label', $activeConversationRetentionLabel);
    $smarty->assign('chat_active_conversation_retention_input_value', $activeConversationRetentionInputValue);
    $chatGroupCreationState = chat_customer_group_creation_state($db, $user, is_array($settings ?? null) ? $settings : []);
    $smarty->assign('chat_customer_full_messenger_enabled', $chatCustomerFullMessenger);
    $smarty->assign('chat_customer_can_create_groups', !empty($chatGroupCreationState['allowed']));
    $smarty->assign('chat_customer_can_start_direct_chats', !empty($chatGroupCreationState['can_create_direct']));
    $smarty->assign('chat_customer_can_create_named_groups', !empty($chatGroupCreationState['can_create_group']));
    $smarty->assign('chat_customer_global_group_enabled', chat_customer_global_group_enabled($user, is_array($settings ?? null) ? $settings : []));
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
$oldestMessageId = !empty($chat) ? (int)$chat[0]['id'] : 0;
$smarty->assign('chat', $chat ?: []);
$smarty->assign('chat_message_limit', count($chat ?: []));
$smarty->assign('chat_loaded_message_count', count($chat ?: []));
$smarty->assign('chat_total_message_count', count($chat ?: []));
$smarty->assign('chat_has_more_messages', false);
$smarty->assign('chat_oldest_message_id', $oldestMessageId);
$smarty->assign('chat_nieprzeczytane', $chat_nieprzeczytane);
$smarty->assign('chat_last_message_id', $lastMessageId);
?>
