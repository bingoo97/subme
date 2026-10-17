<?php

declare(strict_types=1);

/** The transport policy is shared by all admin chat mutations. */
function admin_chat_mutating_actions(): array
{
    return ['start_conversation', 'delete_conversation', 'send', 'upload', 'voice_upload', 'send_quick_reply', 'update_quick_reply_message', 'delete_message', 'edit_message', 'toggle_reaction', 'set_customer_block_status', 'create_crypto_payment_request', 'create_bank_payment_request', 'create_group', 'invite_group_members', 'respond_group_invite', 'leave_group', 'toggle_group_read_only', 'set_group_retention', 'set_group_email_notifications', 'quick_create_customer'];
}

function admin_chat_conversation_access_allowed(Mysql_ks $db, array $adminUser, int $conversationId, array $settings): bool
{
    if ($conversationId <= 0 || !admin_user_can_access_route($adminUser, 'live-chat')) {
        return false;
    }
    $row = $db->select_user("SELECT conversation_type FROM support_conversations WHERE id = {$conversationId} LIMIT 1");
    if (!is_array($row)) {
        return false;
    }
    $type = (string)($row['conversation_type'] ?? '');
    if ($type === 'live_chat') {
        return true;
    }
    if (!chat_is_group_like_conversation_type($type)) {
        return false;
    }
    if (chat_is_global_group_conversation_type($type) && !admin_customer_global_group_enabled($settings)) {
        return false;
    }
    $member = chat_group_accessible_for_admin($db, (int)($adminUser['id'] ?? 0), $conversationId);
    return is_array($member) && (string)($member['invite_status'] ?? '') === 'accepted';
}

function admin_chat_message_body_valid(string $body): bool
{
    return trim($body) !== '' && strlen($body) <= 40000;
}
