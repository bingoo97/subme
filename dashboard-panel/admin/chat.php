<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

admin_send_security_headers();
admin_start_session();

header('Content-Type: application/json; charset=utf-8');

$db = Mysql_ks::get_instance();
$adminUser = admin_load_session_user($db);

if ($adminUser === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

$currentLocale = isset($_SESSION['admin_locale']) ? admin_normalize_locale((string)$_SESSION['admin_locale']) : 'pl';
$messages = admin_load_messages($currentLocale);
$conversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : (isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0);
$action = isset($_POST['action']) ? (string)$_POST['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : 'fetch');
$messageLimit = admin_chat_normalize_message_limit($_POST['message_limit'] ?? $_GET['message_limit'] ?? 0);
$mutatingActions = ['start_conversation', 'delete_conversation', 'send', 'upload', 'send_quick_reply', 'update_quick_reply_message', 'delete_message', 'edit_message', 'create_crypto_payment_request', 'create_bank_payment_request', 'create_group', 'invite_group_members', 'respond_group_invite', 'leave_group', 'toggle_group_read_only', 'set_group_retention', 'set_group_email_notifications', 'quick_create_customer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, $mutatingActions, true) && !admin_csrf_is_valid($_POST['_csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'CSRF validation failed.']);
    exit;
}

if ($action === 'validate_group_email' && !admin_csrf_is_valid($_POST['_csrf'] ?? $_GET['_csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'CSRF validation failed.']);
    exit;
}

if ($action === 'search_users') {
    $query = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
    $rows = admin_chat_search_customers($db, $query, 8, (int)$adminUser['id']);
    echo json_encode([
        'ok' => true,
        'items' => array_map(static function (array $row): array {
            return [
                'participant_type' => (string)($row['participant_type'] ?? 'customer'),
                'customer_id' => (string)($row['participant_type'] ?? 'customer') === 'customer' ? (int)$row['id'] : 0,
                'admin_user_id' => (string)($row['participant_type'] ?? '') === 'admin' ? (int)$row['id'] : 0,
                'email' => (string)$row['email'],
                'display_name' => (string)($row['display_name'] ?? admin_string_truncate((string)$row['email'], 20)),
                'meta_label' => (string)($row['meta_label'] ?? (string)$row['email']),
                'conversation_id' => !empty($row['conversation_id']) ? (int)$row['conversation_id'] : 0,
                'avatar_url' => (string)($row['avatar_url'] ?? ''),
                'avatar_text' => (string)($row['avatar_text'] ?? 'U'),
                'avatar_theme' => (string)($row['avatar_theme'] ?? 'theme-1'),
            ];
        }, $rows),
    ]);
    exit;
}

if ($action === 'validate_group_email') {
    $validation = chat_validate_group_invitee_email(
        $db,
        (string)($_POST['email'] ?? $_GET['email'] ?? ''),
        ['participant_type' => 'admin', 'customer_id' => 0, 'admin_user_id' => (int)$adminUser['id']],
        $conversationId
    );
    echo json_encode($validation);
    exit;
}

if ($action === 'create_group') {
    $emails = json_decode((string)($_POST['participant_emails_json'] ?? '[]'), true);
    $emails = is_array($emails) ? $emails : [];
    $requestedRetention = trim((string)($_POST['retention_hours'] ?? ''));
    $retentionHours = $requestedRetention === '' || $requestedRetention === '0' ? null : (int)$requestedRetention;
    $result = admin_chat_create_group_conversation(
        $db,
        (int)$adminUser['id'],
        trim((string)($_POST['group_name'] ?? '')),
        $emails,
        !empty($_POST['is_group_read_only']),
        $retentionHours
    );
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to create group chat.')]);
        exit;
    }

    $conversationId = (int)($result['conversation_id'] ?? 0);
}

if ($action === 'invite_group_members') {
    $emails = json_decode((string)($_POST['participant_emails_json'] ?? '[]'), true);
    $emails = is_array($emails) ? $emails : [];
    $result = admin_chat_invite_group_members(
        $db,
        $conversationId,
        (int)$adminUser['id'],
        $emails
    );
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to invite members to the group.')]);
        exit;
    }
}

if ($action === 'quick_create_customer') {
    if (!admin_user_can_access_route($adminUser, 'users')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Access denied.']);
        exit;
    }

    $result = admin_create_customer_account(
        $db,
        (string)($_POST['email'] ?? ''),
        (string)($_POST['password'] ?? ''),
        [
            'locale_code' => (string)($_POST['locale_code'] ?? 'pl'),
            'status' => (string)($_POST['status'] ?? 'active'),
            'customer_type' => (string)($_POST['customer_type'] ?? 'client'),
            'send_password_email' => !empty($_POST['send_password_email']),
            'provider_visibility_form_present' => isset($_POST['provider_visibility_form_present']) ? 1 : 0,
            'visible_provider_ids' => array_map('intval', (array)($_POST['visible_provider_ids'] ?? [])),
        ],
        (int)($adminUser['id'] ?? 0),
        admin_request_ip()
    );

    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to create the user.')]);
        exit;
    }

    $customerId = (int)($result['customer_id'] ?? 0);
    $conversationId = $customerId > 0 ? admin_find_or_create_chat_conversation($db, $customerId, (int)($adminUser['id'] ?? 0)) : 0;

    echo json_encode([
        'ok' => true,
        'message' => (string)($result['message'] ?? 'Customer created successfully.'),
        'customer_id' => $customerId,
        'conversation_id' => $conversationId,
        'email' => (string)($result['email'] ?? ''),
        'password' => (string)($result['password'] ?? ''),
        'email_notification' => (array)($result['email_notification'] ?? []),
    ]);
    exit;
}

if ($action === 'respond_group_invite') {
    $result = chat_update_group_invite_status(
        $db,
        $conversationId,
        chat_participant_key_for_admin((int)$adminUser['id']),
        trim((string)($_POST['decision'] ?? 'reject')) === 'accept' ? 'accepted' : 'rejected'
    );
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to update invitation.')]);
        exit;
    }
    if (trim((string)($_POST['decision'] ?? 'reject')) !== 'accept') {
        echo json_encode(['ok' => true, 'conversation_id' => 0, 'dismissed' => true]);
        exit;
    }
}

if ($action === 'leave_group') {
    $result = chat_leave_group_conversation($db, $conversationId, chat_participant_key_for_admin((int)$adminUser['id']));
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to leave group chat.')]);
        exit;
    }
    echo json_encode(['ok' => true, 'conversation_id' => 0, 'left' => true]);
    exit;
}

if ($action === 'start_conversation') {
    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $targetAdminUserId = isset($_POST['admin_user_id']) ? (int)$_POST['admin_user_id'] : 0;
    $participantType = trim((string)($_POST['participant_type'] ?? ($targetAdminUserId > 0 ? 'admin' : 'customer')));
    if ($participantType === 'admin') {
        if ($targetAdminUserId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Admin ID is required.']);
            exit;
        }

        $conversationId = admin_find_or_create_admin_direct_conversation($db, (int)$adminUser['id'], $targetAdminUserId);
    } else {
        if ($customerId <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Customer ID is required.']);
            exit;
        }

        $conversationId = admin_find_or_create_chat_conversation($db, $customerId, (int)$adminUser['id']);
    }

    if ($conversationId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unable to start conversation.']);
        exit;
    }
}

if ($action === 'delete_conversation') {
    if ($conversationId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Conversation ID is required.']);
        exit;
    }

    if (!admin_delete_chat_conversation($db, $conversationId)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to remove this conversation.']);
        exit;
    }

    echo json_encode(['ok' => true, 'deleted' => true, 'conversation_id' => $conversationId]);
    exit;
}

if ($action === 'quick_replies') {
    if ($conversationId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Conversation ID is required.']);
        exit;
    }

    $conversationRow = admin_chat_conversation_row($db, $conversationId);
    if (!is_array($conversationRow) || empty($conversationRow['id'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Conversation not found.']);
        exit;
    }

    $quickReplies = admin_chat_quick_reply_rows_for_locale($db, (string)($conversationRow['customer_locale_code'] ?? ''), true);
    echo json_encode([
        'ok' => true,
        'items' => array_map(static function (array $row): array {
            $preview = trim((string)($row['message_body'] ?? ''));
            if (function_exists('mb_strlen') && mb_strlen($preview) > 180) {
                $preview = rtrim(mb_substr($preview, 0, 177)) . '...';
            } elseif (strlen($preview) > 180) {
                $preview = rtrim(substr($preview, 0, 177)) . '...';
            }

            return [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? ''),
                'message_body' => (string)($row['message_body'] ?? ''),
                'preview' => $preview,
                'locale_code' => admin_normalize_locale((string)($row['locale_code'] ?? 'en')),
            ];
        }, $quickReplies),
    ]);
    exit;
}

if ($action === 'update_quick_reply_message') {
    $quickReplyId = isset($_POST['quick_reply_id']) ? (int)$_POST['quick_reply_id'] : 0;
    $result = admin_update_chat_quick_reply_message($db, $quickReplyId, (string)($_POST['message_body'] ?? ''));
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to save quick reply.')]);
        exit;
    }

    $replyRow = is_array($result['reply'] ?? null) ? (array)$result['reply'] : [];
    $preview = trim((string)($replyRow['message_body'] ?? ''));
    if (function_exists('mb_strlen') && mb_strlen($preview) > 180) {
        $preview = rtrim(mb_substr($preview, 0, 177)) . '...';
    } elseif (strlen($preview) > 180) {
        $preview = rtrim(substr($preview, 0, 177)) . '...';
    }

    echo json_encode([
        'ok' => true,
        'message' => (string)($result['message'] ?? 'Quick reply saved successfully.'),
        'item' => [
            'id' => (int)($replyRow['id'] ?? $quickReplyId),
            'title' => (string)($replyRow['title'] ?? ''),
            'message_body' => (string)($replyRow['message_body'] ?? ''),
            'preview' => $preview,
            'locale_code' => admin_normalize_locale((string)($replyRow['locale_code'] ?? 'en')),
        ],
    ]);
    exit;
}

if ($action === 'inbox_state') {
    $rows = admin_chat_inbox_rows($db);
    $pendingCryptoPayment = false;
    if ($conversationId > 0) {
        $conversationRow = admin_chat_conversation_row($db, $conversationId);
        if ($conversationRow && !empty($conversationRow['customer_id'])) {
            $pendingCryptoPayment = admin_customer_has_pending_crypto_payment($db, (int)$conversationRow['customer_id']);
        }
    }
    echo json_encode([
        'ok' => true,
        'badge_count' => admin_chat_inbox_unread_count($rows),
        'pending_crypto_payment' => $pendingCryptoPayment,
        'items' => array_map(static function (array $row) use ($db, $messages): array {
            $presence = admin_chat_customer_presence(
                $db,
                (int)($row['customer_id'] ?? 0),
                (string)($row['customer_last_login_at'] ?? ''),
                $messages
            );

            return [
                'conversation_id' => (int)($row['id'] ?? 0),
                'unread_count' => (int)($row['unread_count'] ?? 0),
                'presence' => $presence,
            ];
        }, $rows),
    ]);
    exit;
}

if ($conversationId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Conversation ID is required.']);
    exit;
}

$safeConversationId = $conversationId;
$conversationRow = $db->select_user(
    "SELECT
        support_conversations.id,
        support_conversations.customer_id,
        support_conversations.conversation_type,
        support_conversations.status,
        support_conversations.subject,
        support_conversations.group_name,
        support_conversations.is_group_read_only,
        support_conversations.group_created_by_customer_id,
        support_conversations.group_created_by_admin_user_id,
        support_conversations.message_retention_hours,
        support_conversations.created_at,
        support_conversations.updated_at,
        customers.email AS customer_email,
        NULLIF(TRIM(customers.public_handle), '') AS customer_public_handle,
        customers.avatar_url AS customer_avatar_url,
        customers.last_login_at AS customer_last_login_at,
        customers.locale_code AS customer_locale_code
     FROM support_conversations
     LEFT JOIN customers ON customers.id = support_conversations.customer_id
     WHERE support_conversations.id = {$safeConversationId}
     LIMIT 1"
);

if (!is_array($conversationRow) || empty($conversationRow['id'])) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Conversation not found.']);
    exit;
}

if ((string)($conversationRow['conversation_type'] ?? 'live_chat') === 'group_chat' && !chat_group_accessible_for_admin($db, (int)$adminUser['id'], $conversationId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Access denied.']);
    exit;
}

$conversationType = (string)($conversationRow['conversation_type'] ?? 'live_chat');

if ($conversationType === 'group_chat') {
    chat_mark_group_read_for_admin($db, (int)$adminUser['id'], $conversationId);
} else {
    admin_mark_chat_conversation_read($db, $conversationId);
}

$conversationTitle = admin_chat_display_name($conversationRow, $messages, 20);
$presence = admin_chat_customer_presence(
    $db,
    (int)($conversationRow['customer_id'] ?? 0),
    (string)($conversationRow['customer_last_login_at'] ?? ''),
    $messages
);
$avatarHtml = admin_chat_avatar_html($conversationRow, $messages, 'admin-chat-inbox__avatar--sm');

if ($conversationType === 'group_chat') {
    $summary = chat_group_conversation_summary(
        $db,
        $conversationId,
        ['participant_type' => 'admin', 'customer_id' => 0, 'admin_user_id' => (int)$adminUser['id']],
        $conversationRow
    );
    if ($summary) {
        $conversationRow['summary_title'] = (string)($summary['title'] ?? '');
        $conversationRow['avatar_url'] = (string)($summary['avatar_url'] ?? '');
        $conversationRow['avatar_text'] = (string)($summary['avatar_text'] ?? 'G');
        $conversationRow['avatar_theme'] = (string)($summary['avatar_theme'] ?? 'theme-6');
        $conversationTitle = admin_chat_display_name($conversationRow, $messages, 20);
        $presence = is_array($summary['presence'] ?? null) ? $summary['presence'] : $presence;
        $avatarHtml = admin_chat_avatar_html($conversationRow, $messages, 'admin-chat-inbox__avatar--sm');
    }
}

if ($action === 'payment_modal') {
    if ((string)($conversationRow['conversation_type'] ?? 'live_chat') !== 'live_chat') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment actions are only available in direct live chat.']);
        exit;
    }
    $type = trim((string)($_GET['type'] ?? $_POST['type'] ?? ''));
    $payload = admin_chat_payment_modal_data($db, $type, (int)($conversationRow['customer_id'] ?? 0), $messages);
    if (empty($payload['ok'])) {
        http_response_code(422);
    }
    echo json_encode($payload);
    exit;
}

if ($action === 'payment_preview') {
    if ((string)($conversationRow['conversation_type'] ?? 'live_chat') !== 'live_chat') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment actions are only available in direct live chat.']);
        exit;
    }
    $type = trim((string)($_GET['type'] ?? $_POST['type'] ?? ''));
    if ($type === 'crypto') {
        $assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : (int)($_POST['asset_id'] ?? 0);
        $amount = $_GET['amount'] ?? ($_POST['amount'] ?? 0);
        $payload = admin_chat_crypto_payment_preview($db, (int)($conversationRow['customer_id'] ?? 0), $assetId, $amount, $messages);
    } elseif ($type === 'bank') {
        $bankAccountId = isset($_GET['bank_account_id']) ? (int)$_GET['bank_account_id'] : (int)($_POST['bank_account_id'] ?? 0);
        $amount = $_GET['amount'] ?? ($_POST['amount'] ?? 0);
        $payload = admin_chat_bank_payment_preview($db, (int)($conversationRow['customer_id'] ?? 0), $amount, $bankAccountId, $messages);
    } else {
        $payload = ['ok' => false, 'message' => 'Unsupported payment preview type.'];
    }

    if (empty($payload['ok'])) {
        http_response_code(422);
    }
    echo json_encode($payload);
    exit;
}

if ($action === 'link_preview') {
    $previewUrl = trim((string)($_POST['url'] ?? $_GET['url'] ?? ''));
    echo json_encode([
        'ok' => true,
        'preview' => $previewUrl !== '' ? chat_fetch_link_preview($previewUrl) : null,
    ]);
    exit;
}

if ($action === 'send') {
    $messageBody = trim((string)($_POST['message'] ?? ''));
    if ($messageBody !== '') {
        if ((string)($conversationRow['conversation_type'] ?? 'live_chat') === 'group_chat') {
            chat_mark_group_read_for_admin($db, (int)$adminUser['id'], $conversationId);
        }
        $messageBody = chat_prepare_message_with_link_preview(
            $messageBody,
            admin_normalize_locale((string)($conversationRow['customer_locale_code'] ?? 'en')),
            empty($_POST['link_preview_removed']),
            trim((string)($_POST['link_preview_url'] ?? ''))
        );
        admin_chat_insert_message($db, $conversationId, (int)$adminUser['id'], $messageBody);
    }
}

if ($action === 'toggle_group_read_only') {
    $result = admin_chat_toggle_group_read_only($db, $conversationId, (int)$adminUser['id'], !empty($_POST['is_group_read_only']));
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to update read only mode.')]);
        exit;
    }
}

if ($action === 'set_group_retention') {
    $requestedRetention = trim((string)($_POST['retention_hours'] ?? $_GET['retention_hours'] ?? ''));
    $retentionHours = $requestedRetention === '' || $requestedRetention === '0' ? null : (int)$requestedRetention;
    $result = admin_chat_set_group_retention($db, $conversationId, (int)$adminUser['id'], $retentionHours);
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to update auto-delete settings.')]);
        exit;
    }
}

if ($action === 'send_quick_reply') {
    $quickReplyId = isset($_POST['quick_reply_id']) ? (int)$_POST['quick_reply_id'] : 0;
    $sendReply = admin_chat_quick_reply_find($db, $quickReplyId);
    if (!is_array($sendReply) || empty($sendReply['id']) || empty($sendReply['is_active'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Quick reply not found.']);
        exit;
    }

    $sent = admin_chat_insert_message($db, $conversationId, (int)$adminUser['id'], (string)($sendReply['message_body'] ?? ''));
    if (!$sent) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unable to send quick reply.']);
        exit;
    }
}

if ($action === 'upload' && !empty($_FILES['file']['tmp_name'])) {
    $attachmentPath = admin_chat_store_uploaded_image($_FILES['file'], (int)$adminUser['id']);
    if ($attachmentPath === null) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Image upload failed.']);
        exit;
    }

    admin_chat_insert_message($db, $conversationId, (int)$adminUser['id'], '', $attachmentPath);
}

if ($action === 'delete_message') {
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $deleted = admin_delete_chat_message($db, $conversationId, $messageId);
    if (!$deleted) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unable to delete message.']);
        exit;
    }
}

if ($action === 'edit_message') {
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $messageBody = trim((string)($_POST['message'] ?? ''));
    $updated = admin_update_chat_message($db, $conversationId, $messageId, (int)$adminUser['id'], $messageBody);
    if (!$updated) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Unable to save message.']);
        exit;
    }
}

if ($action === 'create_crypto_payment_request') {
    if ((string)($conversationRow['conversation_type'] ?? 'live_chat') !== 'live_chat') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment actions are only available in direct live chat.']);
        exit;
    }
    $assetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;
    $amount = $_POST['amount'] ?? 0;
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $result = admin_chat_create_crypto_payment_request(
        $db,
        $conversationId,
        (int)($conversationRow['customer_id'] ?? 0),
        $assetId,
        $amount,
        (int)$adminUser['id'],
        $messages,
        $productId
    );
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to create crypto payment request.')]);
        exit;
    }
}

if ($action === 'create_bank_payment_request') {
    if ((string)($conversationRow['conversation_type'] ?? 'live_chat') !== 'live_chat') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Payment actions are only available in direct live chat.']);
        exit;
    }
    $amount = $_POST['amount'] ?? 0;
    $bankAccountId = isset($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : 0;
    $result = admin_chat_create_bank_payment_request(
        $db,
        $conversationId,
        (int)($conversationRow['customer_id'] ?? 0),
        $amount,
        $bankAccountId,
        (int)$adminUser['id'],
        $messages
    );
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to create bank payment request.')]);
        exit;
    }
}

if ($action === 'set_group_email_notifications') {
    $enabled = (string)($_POST['enabled'] ?? $_GET['enabled'] ?? '1') !== '0';
    $result = admin_chat_set_group_email_notifications($db, $conversationId, (int)$adminUser['id'], $enabled);
    if (empty($result['ok'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string)($result['message'] ?? 'Unable to update email notifications.')]);
        exit;
    }
}

$messageRows = admin_chat_conversation_messages($db, $conversationId, $messageLimit);
$totalMessageCount = admin_chat_conversation_message_count($db, $conversationId);
$loadedMessageCount = count($messageRows);
$hasMoreMessages = $totalMessageCount > $loadedMessageCount;
$oldestMessageId = $loadedMessageCount > 0 ? (int)($messageRows[0]['id'] ?? 0) : 0;
$quickReplies = admin_chat_quick_reply_rows_for_locale($db, (string)($conversationRow['customer_locale_code'] ?? ''), true);
$pendingCryptoPayment = false;
$groupMemberSettings = null;
$groupMemberSummaries = [];
$groupMemberCountLabel = '';
if (!empty($conversationRow['customer_id'])) {
    $pendingCryptoPayment = admin_customer_has_pending_crypto_payment($db, (int)$conversationRow['customer_id']);
}
if ($conversationType === 'group_chat') {
    $groupMemberSettings = chat_group_member_row($db, $conversationId, chat_participant_key_for_admin((int)$adminUser['id']));
    $groupMemberSummaries = chat_group_member_summaries($db, $conversationId, ['accepted']);
    $groupMemberCountLabel = chat_group_member_count_label($db, $conversationId, ['accepted']);
}

echo json_encode([
    'ok' => true,
    'conversation_id' => $conversationId,
    'customer_id' => (int)($conversationRow['customer_id'] ?? 0),
    'customer_email' => (string)($conversationRow['customer_email'] ?? ''),
    'conversation_type' => $conversationType,
    'customer_public_handle' => (string)($conversationRow['customer_public_handle'] ?? ''),
    'is_group_read_only' => !empty($conversationRow['is_group_read_only']),
    'retention_hours' => chat_group_normalize_retention_hours($conversationRow['message_retention_hours'] ?? null),
    'email_notifications_enabled' => $conversationType === 'group_chat'
        ? chat_group_member_email_notifications_enabled(is_array($groupMemberSettings) ? $groupMemberSettings : [])
        : true,
    'can_manage_group' => $conversationType === 'group_chat'
        ? chat_group_can_admin_manage((array)$conversationRow, (int)($adminUser['id'] ?? 0))
        : false,
    'member_count_label' => $groupMemberCountLabel,
    'members' => array_map(static function (array $member): array {
        $participantType = (string)($member['participant_type'] ?? '');
        $customerId = (int)($member['customer_id'] ?? 0);
        $publicHandle = trim((string)($member['public_handle'] ?? ''));
        $email = trim((string)($member['email'] ?? ''));
        $displayLabel = $publicHandle !== '' ? '@' . $publicHandle : (trim((string)($member['label'] ?? '')) !== '' ? (string)$member['label'] : $email);

        return [
            'participant_type' => $participantType,
            'customer_id' => $customerId,
            'admin_user_id' => (int)($member['admin_user_id'] ?? 0),
            'display_label' => $displayLabel,
            'email' => $email,
            'public_handle' => $publicHandle,
            'profile_url' => $participantType === 'customer' && $customerId > 0 ? '/admin/?page=users&customer_id=' . $customerId : '',
        ];
    }, $groupMemberSummaries),
    'title' => $conversationTitle,
    'presence' => $presence,
    'avatar_html' => $avatarHtml,
    'pending_crypto_payment' => $pendingCryptoPayment,
    'message_limit' => $messageLimit,
    'loaded_message_count' => $loadedMessageCount,
    'total_message_count' => $totalMessageCount,
    'has_more_messages' => $hasMoreMessages,
    'oldest_message_id' => $oldestMessageId,
    'html' => admin_render_chat_conversation_html($conversationRow, $messageRows, $messages),
    'quick_replies' => array_map(static function (array $row): array {
        $preview = trim((string)($row['message_body'] ?? ''));
        if (function_exists('mb_strlen') && mb_strlen($preview) > 180) {
            $preview = rtrim(mb_substr($preview, 0, 177)) . '...';
        } elseif (strlen($preview) > 180) {
            $preview = rtrim(substr($preview, 0, 177)) . '...';
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? ''),
            'message_body' => (string)($row['message_body'] ?? ''),
            'preview' => $preview,
            'locale_code' => admin_normalize_locale((string)($row['locale_code'] ?? 'en')),
        ];
    }, $quickReplies),
]);
