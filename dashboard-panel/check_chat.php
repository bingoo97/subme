<?php

require __DIR__ . '/config/mysql.php';
require __DIR__ . '/bootstrap/session.php';

app_start_session();

require __DIR__ . '/vendor/autoload.php';

$smarty = new \Smarty\Smarty;

require __DIR__ . '/config/config.php';
require_once __DIR__ . '/bootstrap/chat.php';

function chat_json_response(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function chat_require_csrf(array $messages = []): void
{
    $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? null;
    if (app_csrf_is_valid($token)) {
        return;
    }

    $message = localization_translate($messages, 'csrf_invalid', 'The form has expired. Refresh the page and try again.');

    if ((isset($_POST['format']) && $_POST['format'] === 'json') || (isset($_GET['format']) && $_GET['format'] === 'json')) {
        chat_json_response(['ok' => false, 'message' => $message]);
    }

    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

if (!app_support_chat_effective_enabled(is_array($settings ?? null) ? $settings : [])) {
    $disabledMessage = localization_translate($t, 'support_chat_disabled_notice', 'Support chat is currently disabled.');

    if ((isset($_POST['format']) && $_POST['format'] === 'json') || (isset($_GET['format']) && $_GET['format'] === 'json')) {
        chat_json_response(['ok' => false, 'message' => $disabledMessage]);
    }

    echo '<p>' . htmlspecialchars($disabledMessage, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

if (empty($user['logged']) || empty($_SESSION['id'])) {
    if ((isset($_POST['format']) && $_POST['format'] === 'json') || (isset($_GET['format']) && $_GET['format'] === 'json')) {
        chat_json_response(['ok' => false, 'message' => 'Please login again.']);
    }

    echo '<p>Please login again...</p>';
    exit;
}

if (app_uses_v2_schema($db) && function_exists('chat_demo_showcase_sync')) {
    chat_demo_showcase_sync($db, is_array($settings ?? null) ? $settings : [], ['emit_messages' => false, 'source' => 'customer_chat_action']);
}

function chat_first_admin_id(Mysql_ks $db): int
{
    if (schema_object_exists($db, 'admin_users')) {
        $admin = $db->select_user("SELECT id FROM admin_users ORDER BY id ASC LIMIT 1");
        if (is_array($admin) && !empty($admin['id'])) {
            return (int)$admin['id'];
        }
    }

    return 1;
}

function chat_find_or_create_conversation(Mysql_ks $db, int $customerId): int
{
    $conversation = $db->select_user(
        "SELECT id
         FROM support_conversations
         WHERE conversation_type = 'live_chat'
           AND customer_id = {$customerId}
         ORDER BY id ASC
         LIMIT 1"
    );

    if (is_array($conversation) && !empty($conversation['id'])) {
        return (int)$conversation['id'];
    }

    $adminId = chat_first_admin_id($db);
    $subject = 'Customer live chat #' . $customerId;

    $db->insert(
        ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'status', 'priority'],
        ['live_chat', $customerId, $adminId, $subject, 'open', 'normal'],
        'support_conversations'
    );

    return (int)$db->id();
}

function chat_insert_customer_message(Mysql_ks $db, int $customerId, string $messageBody, ?string $attachmentPath = null, ?int $replyToMessageId = null): void
{
    chat_ensure_message_interactions_runtime($db);
    $conversationId = chat_find_or_create_conversation($db, $customerId);
    $currentTime = function_exists('app_current_datetime_string') ? app_current_datetime_string() : date('Y-m-d H:i:s');
    $replyToMessageId = $replyToMessageId !== null && $replyToMessageId > 0 ? $replyToMessageId : null;

    $db->insert(
        ['conversation_id', 'sender_type', 'customer_id', 'message_body', 'attachment_path', 'reply_to_message_id', 'is_read', 'created_at'],
        [$conversationId, 'customer', $customerId, $messageBody, $attachmentPath, $replyToMessageId, 0, $currentTime],
        'support_messages'
    );

    $db->update_using_id(
        ['status', 'last_customer_message_at', 'updated_at'],
        ['open', $currentTime, $currentTime],
        'support_conversations',
        $conversationId
    );

    app_queue_live_chat_admin_notification($db, $conversationId, $customerId, $messageBody, $attachmentPath);
}

function chat_insert_support_message(Mysql_ks $db, int $customerId, string $messageBody, ?string $createdAt = null, ?int $replyToMessageId = null): void
{
    chat_ensure_message_interactions_runtime($db);
    $conversationId = chat_find_or_create_conversation($db, $customerId);
    $currentTime = $createdAt !== null && $createdAt !== '' ? $createdAt : (function_exists('app_current_datetime_string') ? app_current_datetime_string() : date('Y-m-d H:i:s'));
    $adminId = chat_first_admin_id($db);
    $replyToMessageId = $replyToMessageId !== null && $replyToMessageId > 0 ? $replyToMessageId : null;

    $db->insert(
        ['conversation_id', 'sender_type', 'customer_id', 'admin_user_id', 'message_body', 'attachment_path', 'reply_to_message_id', 'is_read', 'created_at'],
        [$conversationId, 'admin', $customerId, $adminId, $messageBody, null, $replyToMessageId, 1, $currentTime],
        'support_messages'
    );

    $db->update_using_id(
        ['status', 'last_admin_message_at', 'updated_at'],
        ['open', $currentTime, $currentTime],
        'support_conversations',
        $conversationId
    );
}

function chat_insert_customer_group_message(Mysql_ks $db, int $conversationId, int $customerId, string $messageBody, ?string $attachmentPath = null, ?int $replyToMessageId = null): bool
{
    chat_ensure_message_interactions_runtime($db);
    $conversation = chat_group_accessible_for_customer($db, $customerId, $conversationId);
    if (
        !$conversation
        || !empty($conversation['is_group_read_only'])
        || (int)($conversation['can_post'] ?? 1) === 0
        || chat_group_conversation_has_pending_invites($db, $conversationId)
    ) {
        return false;
    }

    $currentTime = chat_current_datetime();
    $replyToMessageId = $replyToMessageId !== null && $replyToMessageId > 0 ? $replyToMessageId : null;
    $inserted = $db->insert(
        ['conversation_id', 'sender_type', 'customer_id', 'message_body', 'attachment_path', 'reply_to_message_id', 'is_read', 'created_at'],
        [$conversationId, 'customer', $customerId, $messageBody, $attachmentPath, $replyToMessageId, 0, $currentTime],
        'support_messages'
    );

    if (!$inserted) {
        return false;
    }

    $messageId = (int)$db->id();
    $db->update_using_id(['updated_at', 'status'], [$currentTime, 'open'], 'support_conversations', $conversationId);
    $member = chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($customerId));
    if ($member) {
        $db->update_using_id(['last_read_message_id'], [$messageId], 'support_conversation_members', (int)$member['id']);
    }

    return true;
}

function chat_mark_admin_messages_read(Mysql_ks $db, int $customerId): void
{
    $visibleUntil = date('Y-m-d H:i:s');
    $messageRows = $db->select_full_user(
        "SELECT support_messages.id
         FROM support_messages
         INNER JOIN support_conversations
            ON support_conversations.id = support_messages.conversation_id
         WHERE support_conversations.conversation_type = 'live_chat'
           AND support_conversations.customer_id = {$customerId}
           AND support_messages.sender_type = 'admin'
           AND support_messages.is_read = 0
           AND support_messages.created_at <= '{$visibleUntil}'"
    );

    foreach ($messageRows as $messageRow) {
        if (!empty($messageRow['id'])) {
            $db->update_using_id(['is_read'], [1], 'support_messages', (int)$messageRow['id']);
        }
    }
}

function chat_delete_uploaded_file(?string $attachmentPath): void
{
    $attachmentPath = trim((string)$attachmentPath);
    if ($attachmentPath === '' || strpos($attachmentPath, '/uploads/chat/') !== 0) {
        return;
    }

    foreach (app_chat_attachment_candidate_paths($attachmentPath) as $filePath) {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}

function chat_delete_customer_message_if_allowed(Mysql_ks $db, int $customerId, int $messageId): bool
{
    if ($messageId <= 0) {
        return false;
    }

    if (app_uses_v2_schema($db)) {
        $row = $db->select_user(
            "SELECT support_messages.id, support_messages.attachment_path, support_messages.created_at, support_messages.sender_type, support_messages.customer_id AS message_customer_id, support_conversations.conversation_type, support_conversations.id AS conversation_id, support_conversations.group_created_by_customer_id, support_conversation_members.invite_status
             FROM support_messages
             INNER JOIN support_conversations
                ON support_conversations.id = support_messages.conversation_id
             LEFT JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
               AND support_conversation_members.participant_key = '" . $db->escape(chat_participant_key_for_customer($customerId)) . "'
             WHERE support_messages.id = {$messageId}
               AND (
                    (
                        support_conversations.conversation_type = 'live_chat'
                        AND support_messages.sender_type = 'customer'
                        AND support_messages.customer_id = {$customerId}
                        AND support_conversations.customer_id = {$customerId}
                    )
                    OR (
                        support_conversations.conversation_type IN ('group_chat', 'global_group')
                        AND support_conversation_members.invite_status = 'accepted'
                        AND (
                            support_messages.customer_id = {$customerId}
                            OR support_conversations.group_created_by_customer_id = {$customerId}
                        )
                    )
               )
             LIMIT 1"
        );

        if (!is_array($row) || empty($row['id'])) {
            return false;
        }

        if (chat_is_group_like_conversation_type((string)($row['conversation_type'] ?? ''))) {
            chat_delete_uploaded_file(isset($row['attachment_path']) ? (string)$row['attachment_path'] : '');
            if (schema_object_exists($db, 'support_message_reactions')) {
                $db->query("DELETE FROM support_message_reactions WHERE message_id = {$messageId}");
            }
            $db->query("DELETE FROM support_messages WHERE id = {$messageId} LIMIT 1");
            return true;
        }

        if (trim((string)($row['conversation_type'] ?? '')) !== 'live_chat') {
            $createdAtTimestamp = strtotime((string)$row['created_at']);
            if ($createdAtTimestamp === false || (time() - $createdAtTimestamp) > 10) {
                return false;
            }
        }

        chat_delete_uploaded_file(isset($row['attachment_path']) ? (string)$row['attachment_path'] : '');
        if (schema_object_exists($db, 'support_message_reactions')) {
            $db->query("DELETE FROM support_message_reactions WHERE message_id = {$messageId}");
        }
        $db->query("DELETE FROM support_messages WHERE id = {$messageId} LIMIT 1");
        return true;
    }

    $row = $db->select_user(
        "SELECT id, tresc, data
         FROM produkty_chat
         WHERE id = {$messageId}
           AND user1 = {$customerId}
         LIMIT 1"
    );

    if (!is_array($row) || empty($row['id'])) {
        return false;
    }

    $createdAtTimestamp = strtotime((string)$row['data']);
    if ($createdAtTimestamp === false || (time() - $createdAtTimestamp) > 10) {
        return false;
    }

    $attachmentPath = chat_extract_attachment_path('', (string)($row['tresc'] ?? ''));
    chat_delete_uploaded_file($attachmentPath);
    $db->query("DELETE FROM produkty_chat WHERE id = {$messageId} LIMIT 1");
    return true;
}

function chat_render_payload(
    \Smarty\Smarty $smarty,
    Mysql_ks $db,
    array $user,
    array $reseller,
    array $settings,
    string $currentLocale = 'en',
    ?int $forcedConversationId = null,
    ?string $forcedConversationType = null
): array
{
    $chatRenderConversationId = $forcedConversationId;
    $chatRenderConversationType = $forcedConversationType;
    include __DIR__ . '/config/chat_config.php';

    return [
        'html' => $smarty->fetch('messanger_content.tpl'),
        'unread_count' => (int)$smarty->getTemplateVars('chat_nieprzeczytane'),
        'last_message_id' => (int)$smarty->getTemplateVars('chat_last_message_id'),
        'message_limit' => (int)$smarty->getTemplateVars('chat_message_limit'),
        'loaded_message_count' => (int)$smarty->getTemplateVars('chat_loaded_message_count'),
        'total_message_count' => (int)$smarty->getTemplateVars('chat_total_message_count'),
        'has_more_messages' => (bool)$smarty->getTemplateVars('chat_has_more_messages'),
        'oldest_message_id' => (int)$smarty->getTemplateVars('chat_oldest_message_id'),
    ];
}

function chat_rate_limit_state(): array
{
    if (!isset($_SESSION['chat_rate_limit']) || !is_array($_SESSION['chat_rate_limit'])) {
        $_SESSION['chat_rate_limit'] = [
            'count' => 0,
            'window_started_at' => 0,
            'blocked_until' => 0,
        ];
    }

    $state = $_SESSION['chat_rate_limit'];
    $now = time();
    $blockedUntil = isset($state['blocked_until']) ? (int)$state['blocked_until'] : 0;
    $windowStartedAt = isset($state['window_started_at']) ? (int)$state['window_started_at'] : 0;

    if ($blockedUntil > 0 && $blockedUntil <= $now) {
        $state['count'] = 0;
        $state['window_started_at'] = 0;
        $state['blocked_until'] = 0;
    }

    if ($windowStartedAt > 0 && ($now - $windowStartedAt) >= 30) {
        $state['count'] = 0;
        $state['window_started_at'] = 0;
    }

    $_SESSION['chat_rate_limit'] = $state;

    return [
        'count' => isset($state['count']) ? (int)$state['count'] : 0,
        'window_started_at' => isset($state['window_started_at']) ? (int)$state['window_started_at'] : 0,
        'blocked_until' => isset($state['blocked_until']) ? (int)$state['blocked_until'] : 0,
        'remaining_seconds' => max(0, (isset($state['blocked_until']) ? (int)$state['blocked_until'] : 0) - $now),
        'is_blocked' => (isset($state['blocked_until']) ? (int)$state['blocked_until'] : 0) > $now,
    ];
}

function chat_rate_limit_payload(array $state): array
{
    $remainingSeconds = max(1, (int)($state['remaining_seconds'] ?? 30));

    return [
        'ok' => false,
        'message' => 'Please wait ' . $remainingSeconds . ' seconds.',
        'cooldown_seconds' => $remainingSeconds,
        'cooldown_active' => true,
    ];
}

function chat_require_rate_limit_slot(string $responseFormat): void
{
    $state = chat_rate_limit_state();
    if (!empty($state['is_blocked'])) {
        if ($responseFormat === 'json') {
            chat_json_response(chat_rate_limit_payload($state));
        }

        echo '<p>' . htmlspecialchars(chat_rate_limit_payload($state)['message'], ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
    }
}

function chat_register_customer_message_sent(): void
{
    $state = chat_rate_limit_state();
    $now = time();

    if (!empty($state['is_blocked'])) {
        return;
    }

    $count = (int)($state['count'] ?? 0);
    $windowStartedAt = (int)($state['window_started_at'] ?? 0);

    if ($windowStartedAt <= 0) {
        $windowStartedAt = $now;
        $count = 0;
    }

    $count++;

    $_SESSION['chat_rate_limit'] = [
        'count' => $count >= 3 ? 0 : $count,
        'window_started_at' => $count >= 3 ? 0 : $windowStartedAt,
        'blocked_until' => $count >= 3 ? ($now + 30) : 0,
    ];
}

function chat_resize_image(string $sourcePath, string $destinationPath, string $mimeType): bool
{
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($image);
        return false;
    }

    if ($mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        $orientation = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
        if ($orientation === 3) {
            $rotated = imagerotate($image, 180, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        } elseif ($orientation === 6) {
            $rotated = imagerotate($image, -90, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        } elseif ($orientation === 8) {
            $rotated = imagerotate($image, 90, 0);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $width = imagesx($image);
        $height = imagesy($image);
    }

    $maxDimension = 1280;
    $scale = min($maxDimension / $width, $maxDimension / $height, 1);
    $targetWidth = max(1, (int)round($width * $scale));
    $targetHeight = max(1, (int)round($height * $scale));

    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$target) {
        imagedestroy($image);
        return false;
    }

    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    $saved = false;
    if ($mimeType === 'image/jpeg') {
        $saved = imagejpeg($target, $destinationPath, 80);
    } elseif ($mimeType === 'image/png') {
        $saved = imagepng($target, $destinationPath, 7);
    } elseif ($mimeType === 'image/gif') {
        $saved = imagegif($target, $destinationPath);
    }

    imagedestroy($target);
    imagedestroy($image);

    return (bool)$saved;
}

function chat_store_uploaded_image(array $file, int $customerId): ?string
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
    $originalName = (string)($file['name'] ?? '');
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $mimeType = function_exists('mime_content_type') ? (string)mime_content_type($tmpPath) : '';
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

    if (
        $uploadError !== UPLOAD_ERR_OK
        || !is_uploaded_file($tmpPath)
        || !in_array($extension, $allowedExtensions, true)
        || ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true))
    ) {
        return null;
    }

    $uploadDirectory = app_public_path('uploads/chat');
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        return null;
    }

    $safeExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    $fileName = 'chat_' . $customerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExtension;
    $destinationPath = $uploadDirectory . '/' . $fileName;

    $saved = false;
    if ($mimeType !== '' && function_exists('imagecreatetruecolor')) {
        $saved = chat_resize_image($tmpPath, $destinationPath, $mimeType);
    }

    if (!$saved) {
        $saved = move_uploaded_file($tmpPath, $destinationPath);
    }

    if (!$saved) {
        return null;
    }

    return '/uploads/chat/' . $fileName;
}

$currentCustomerId = (int)$_SESSION['id'];
$requestedConversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : (isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0);
$requestedConversationType = isset($_POST['conversation_type']) ? trim((string)$_POST['conversation_type']) : (isset($_GET['conversation_type']) ? trim((string)$_GET['conversation_type']) : '');
$responseFormat = isset($_POST['format']) ? (string)$_POST['format'] : (isset($_GET['format']) ? (string)$_GET['format'] : 'html');
$action = isset($_POST['action']) ? (string)$_POST['action'] : (isset($_GET['action']) ? (string)$_GET['action'] : 'fetch');
$faqKey = isset($_POST['faq_key']) ? trim((string)$_POST['faq_key']) : (isset($_GET['faq_key']) ? trim((string)$_GET['faq_key']) : '');
$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : (isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0);
$groupActionConversationId = 0;
$groupActionConversationTitle = '';
$groupActionAvatarUrl = '';
$chatLocaleCode = isset($user['locale_code']) && (string)$user['locale_code'] !== ''
    ? (string)$user['locale_code']
    : (isset($currentLocale) ? (string)$currentLocale : (isset($user['lang_code']) ? (string)$user['lang_code'] : 'en'));
$chatLocaleCode = localization_normalize_locale($chatLocaleCode);
$currentCustomerRow = $db->select_user(
    "SELECT id, status
     FROM customers
     WHERE id = {$currentCustomerId}
     LIMIT 1"
);
$customerHasFullMessenger = chat_customer_can_use_groups($user, is_array($settings ?? null) ? $settings : []);
$responseMessage = '';

chat_purge_expired_messages($db, chat_retention_days($settings));

if (
    $action === 'read'
    || $action === 'send'
    || $action === 'link_preview'
    || $action === 'upload'
    || $action === 'toggle_reaction'
    || $action === 'delete_message'
    || $action === 'faq_prompt'
    || $action === 'faq_reply'
    || $action === 'create_group'
    || $action === 'validate_group_email'
    || $action === 'participant_profile'
    || $action === 'start_direct_chat'
    || $action === 'invite_to_group'
    || $action === 'respond_group_invite'
    || $action === 'leave_group'
    || $action === 'remove_group'
    || $action === 'delete_group'
    || $action === 'set_group_email_notifications'
    || $action === 'set_group_retention'
    || isset($_POST['user'])
    || isset($_POST['id_usera'])
    || !empty($_FILES['file']['tmp_name'])
) {
    chat_require_csrf(isset($t) && is_array($t) ? $t : []);
}

if ($action === 'link_preview') {
    $previewUrl = trim((string)($_POST['url'] ?? $_GET['url'] ?? ''));
    $preview = $previewUrl !== '' ? chat_fetch_link_preview($previewUrl) : null;
    chat_json_response([
        'ok' => true,
        'preview' => is_array($preview) ? $preview : null,
    ]);
}

$requestedGroupConversation = null;
if ($requestedConversationId > 0 && function_exists('chat_group_accessible_for_customer')) {
    $requestedGroupConversation = chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId);
}

$currentCustomerIsGlobalChatBlocked = $requestedGroupConversation
    && function_exists('chat_is_global_group_conversation_type')
    && chat_is_global_group_conversation_type((string)($requestedGroupConversation['conversation_type'] ?? ''))
    && function_exists('chat_global_group_customer_blocked')
    && chat_global_group_customer_blocked($db, $currentCustomerId);

if (
    $currentCustomerIsGlobalChatBlocked
    && (
        $action === 'send'
        || $action === 'upload'
        || $action === 'toggle_reaction'
        || !empty($_FILES['file']['tmp_name'])
    )
) {
    $blockedMessage = localization_translate(isset($t) && is_array($t) ? $t : [], 'chat_global_blocked_notice', 'You cannot send messages in Global Chat right now.');
    if ($responseFormat === 'json') {
        chat_json_response(['ok' => false, 'message' => $blockedMessage]);
    }
    echo '<p>' . htmlspecialchars($blockedMessage, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

if ($action === 'validate_group_email') {
    if ($requestedConversationId > 0 && !$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    if ($requestedConversationId > 0) {
        $conversation = chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId);
        if (!$conversation || !chat_group_can_customer_manage($conversation, $currentCustomerId)) {
            chat_json_response(['ok' => false, 'message' => 'Only the group creator can add new members.']);
        }
    } else {
        $groupCreationState = chat_customer_group_creation_state($db, $user, is_array($settings ?? null) ? $settings : []);
        if (empty($groupCreationState['allowed'])) {
            if (!empty($groupCreationState['blocked_by_limit'])) {
                chat_json_response(['ok' => false, 'message' => 'Group chat creation is disabled for reseller accounts.']);
            }
            if (!empty($groupCreationState['reached_limit'])) {
                chat_json_response(['ok' => false, 'message' => 'You reached the maximum number of group chats for your account.']);
            }
            chat_json_response(['ok' => false, 'message' => 'Conversation creation is disabled for this account.']);
        }
    }

    $validation = chat_validate_group_invitee_email(
        $db,
        (string)($_POST['email'] ?? $_GET['email'] ?? ''),
        ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0],
        $requestedConversationId,
        is_array($settings ?? null) ? $settings : [],
        isset($t) && is_array($t) ? $t : []
    );
    chat_json_response($validation);
}

if ($action === 'participant_profile') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }

    $participantType = trim((string)($_POST['participant_type'] ?? $_GET['participant_type'] ?? 'customer'));
    if ($participantType !== 'customer') {
        chat_json_response(['ok' => false, 'message' => 'User not found.']);
    }

    $targetCustomerId = isset($_POST['target_customer_id']) ? (int)$_POST['target_customer_id'] : (int)($_GET['target_customer_id'] ?? 0);
    $contextConversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : (int)($_GET['conversation_id'] ?? 0);
    $result = chat_customer_participant_profile_payload($db, $user, $targetCustomerId, is_array($settings ?? null) ? $settings : [], $contextConversationId);
    chat_json_response($result);
}

if ($action === 'start_direct_chat') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }

    $targetCustomerId = isset($_POST['target_customer_id']) ? (int)$_POST['target_customer_id'] : (int)($_GET['target_customer_id'] ?? 0);
    $result = chat_start_customer_direct_conversation($db, $user, $targetCustomerId, is_array($settings ?? null) ? $settings : []);
    if (empty($result['ok'])) {
        chat_json_response($result);
    }

    if (!empty($result['conversation_id'])) {
        $requestedConversationId = (int)$result['conversation_id'];
    }
    $responseMessage = (string)($result['message'] ?? '');
}

if ($action === 'create_group') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $groupCreationState = chat_customer_group_creation_state($db, $user, is_array($settings ?? null) ? $settings : []);
    if (empty($groupCreationState['allowed'])) {
        if (!empty($groupCreationState['blocked_by_limit'])) {
            chat_json_response(['ok' => false, 'message' => 'Group chat creation is disabled for reseller accounts.']);
        }
        if (!empty($groupCreationState['reached_limit'])) {
            chat_json_response(['ok' => false, 'message' => 'You reached the maximum number of group chats for your account.']);
        }
        chat_json_response(['ok' => false, 'message' => 'Conversation creation is disabled for this account.']);
    }

    $emailsJson = (string)($_POST['participant_emails_json'] ?? '[]');
    $emails = json_decode($emailsJson, true);
    $emails = is_array($emails) ? $emails : [];
    $requestedRetention = trim((string)($_POST['retention_hours'] ?? ''));
    $groupKind = strtolower(trim((string)($_POST['group_kind'] ?? 'direct')));
    $isNamedGroup = $groupKind === 'group';
    $retentionHours = $requestedRetention === '' || $requestedRetention === '0' ? null : $requestedRetention;
    $groupAvatarUrl = '';
    if ($isNamedGroup && !empty($_FILES['group_avatar_file']['tmp_name'])) {
        $groupAvatarUpload = chat_store_group_avatar_upload($_FILES['group_avatar_file'], $currentCustomerId);
        if (empty($groupAvatarUpload['ok'])) {
            chat_json_response([
                'ok' => false,
                'message' => localization_translate(isset($t) && is_array($t) ? $t : [], 'group_chat_logo_upload_failed', 'Group logo upload failed.')
            ]);
        }
        $groupAvatarUrl = (string)($groupAvatarUpload['url'] ?? '');
    }
    $result = chat_create_group_conversation(
        $db,
        ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0],
        trim((string)($_POST['group_name'] ?? '')),
        $emails,
        false,
        is_array($settings ?? null) ? $settings : [],
        $retentionHours,
        $groupAvatarUrl,
        $isNamedGroup
    );

    if (empty($result['ok'])) {
        if ($groupAvatarUrl !== '') {
            chat_delete_group_avatar_file($groupAvatarUrl);
        }
        chat_json_response($result);
    }

    $requestedConversationId = (int)($result['conversation_id'] ?? 0);
    $groupActionConversationId = $requestedConversationId;
    $groupActionConversationTitle = (string)($result['title'] ?? '');
    $groupActionAvatarUrl = (string)($result['avatar_url'] ?? '');
}

if ($action === 'invite_to_group') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $emailsJson = (string)($_POST['participant_emails_json'] ?? '[]');
    $emails = json_decode($emailsJson, true);
    $emails = is_array($emails) ? $emails : [];

    $result = chat_invite_members_to_group_conversation(
        $db,
        $requestedConversationId,
        ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0],
        $emails
    );

    if (empty($result['ok'])) {
        chat_json_response($result);
    }
    $groupActionConversationId = $requestedConversationId;
    $conversationRow = chat_group_conversation_row($db, $requestedConversationId);
    $groupActionConversationTitle = $conversationRow ? chat_group_conversation_title($conversationRow) : '';
    $groupActionAvatarUrl = $conversationRow ? chat_group_avatar_url((string)($conversationRow['group_avatar_url'] ?? '')) : '';
}

if ($action === 'respond_group_invite') {
    if (!chat_customer_can_use_groups($user, is_array($settings ?? null) ? $settings : [])) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $decision = trim((string)($_POST['decision'] ?? 'reject'));
    $result = chat_update_group_invite_status(
        $db,
        $requestedConversationId,
        chat_participant_key_for_customer($currentCustomerId),
        $decision === 'accept' ? 'accepted' : 'rejected'
    );
    if (empty($result['ok'])) {
        chat_json_response($result);
    }

    if ($decision === 'accept') {
        $requestedConversationId = max(0, $requestedConversationId);
    }
}

if ($action === 'leave_group') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $result = chat_leave_group_conversation(
        $db,
        $requestedConversationId,
        chat_participant_key_for_customer($currentCustomerId)
    );
    if (empty($result['ok'])) {
        chat_json_response($result);
    }

    $requestedConversationId = 0;
}

if ($action === 'remove_group') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $result = chat_remove_group_conversation_for_participant(
        $db,
        $requestedConversationId,
        chat_participant_key_for_customer($currentCustomerId)
    );
    if (empty($result['ok'])) {
        chat_json_response($result);
    }

    $requestedConversationId = 0;
}

if ($action === 'delete_group') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $result = chat_delete_group_conversation(
        $db,
        $requestedConversationId,
        ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0]
    );
    if (empty($result['ok'])) {
        chat_json_response($result);
    }

    $requestedConversationId = 0;
}

if ($action === 'set_group_email_notifications') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $conversation = chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId);
    if (!$conversation) {
        chat_json_response(['ok' => false, 'message' => 'Conversation not found.']);
    }
    if (chat_is_global_group_conversation_type((string)($conversation['conversation_type'] ?? ''))) {
        chat_json_response(['ok' => false, 'message' => 'Email notifications are not available for the global chat.']);
    }

    $enabled = (string)($_POST['enabled'] ?? $_GET['enabled'] ?? '1') !== '0';
    $result = chat_update_group_member_email_notifications(
        $db,
        $requestedConversationId,
        chat_participant_key_for_customer($currentCustomerId),
        $enabled
    );
    if (empty($result['ok'])) {
        chat_json_response($result);
    }
    $responseMessage = (string)($result['message'] ?? '');
}

if ($action === 'set_group_retention') {
    if (!$customerHasFullMessenger) {
        chat_json_response(['ok' => false, 'message' => 'Access denied.']);
    }
    $conversation = chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId);
    if (!$conversation) {
        chat_json_response(['ok' => false, 'message' => 'Conversation not found.']);
    }

    $requestedRetentionToken = trim((string)($_POST['retention_token'] ?? $_GET['retention_token'] ?? ''));
    $requestedRetention = $requestedRetentionToken !== ''
        ? $requestedRetentionToken
        : trim((string)($_POST['retention_hours'] ?? $_GET['retention_hours'] ?? ''));
    $retentionHours = $requestedRetention === '' ? null : $requestedRetention;
    $result = chat_update_group_retention_hours(
        $db,
        $requestedConversationId,
        ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0],
        $retentionHours
    );
    if (empty($result['ok'])) {
        chat_json_response($result);
    }
    $responseMessage = (string)($result['message'] ?? '');
}

if ($faqKey !== '') {
    $faqPrompts = chat_load_faq_prompts($db, $chatLocaleCode, 5);
    $selectedFaqPrompt = chat_find_faq_prompt($faqPrompts, $faqKey);

    if ($selectedFaqPrompt === null) {
        chat_json_response(['ok' => false, 'message' => 'FAQ entry not found.']);
    }
}

if (app_uses_v2_schema($db)) {
    if (isset($_POST['user']) || $action === 'read') {
        if ($customerHasFullMessenger && $requestedConversationId > 0 && chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId)) {
            chat_mark_group_read_for_customer($db, $currentCustomerId, $requestedConversationId);
        } else {
            chat_mark_admin_messages_read($db, $currentCustomerId);
        }
    }

    if ($action === 'delete_message') {
        if (!$messageId || !chat_delete_customer_message_if_allowed($db, $currentCustomerId, $messageId)) {
            if ($responseFormat === 'json') {
                chat_json_response(['ok' => false, 'message' => 'This message can no longer be deleted.']);
            }
            echo '<p>This message can no longer be deleted.</p>';
            exit;
        }
    }

    if ($action === 'toggle_reaction') {
        $reactionCode = trim((string)($_POST['reaction_code'] ?? ''));
        $targetMessageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
        $targetConversationId = $requestedConversationId;
        if ($targetConversationId <= 0) {
            $targetConversationId = chat_find_or_create_conversation($db, $currentCustomerId);
        }
        $validMessageId = chat_validate_reply_target($db, $targetConversationId, $targetMessageId);
        if ($validMessageId === null) {
            chat_json_response(['ok' => false, 'message' => 'Message not found.']);
        }
        $reactionResult = chat_toggle_message_reaction(
            $db,
            $validMessageId,
            [
                'participant_type' => 'customer',
                'customer_id' => $currentCustomerId,
                'admin_user_id' => 0,
                'participant_key' => chat_participant_key_for_customer($currentCustomerId),
            ],
            $reactionCode
        );
        if (empty($reactionResult['ok'])) {
            chat_json_response(['ok' => false, 'message' => (string)($reactionResult['message'] ?? 'Unable to update reaction.')]);
        }
    }

    if ((isset($_POST['id_usera'], $_POST['tresc']) && (int)$_POST['id_usera'] === $currentCustomerId) || $action === 'send') {
        $messageBody = trim((string)($_POST['tresc'] ?? ''));
        if ($messageBody !== '') {
            chat_require_rate_limit_slot($responseFormat);
            $replyToMessageId = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : 0;
            $messageBody = chat_prepare_message_with_link_preview(
                $messageBody,
                $chatLocaleCode,
                empty($_POST['link_preview_removed']),
                trim((string)($_POST['link_preview_url'] ?? ''))
            );
            if ($customerHasFullMessenger && $requestedConversationId > 0 && chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId)) {
                $replyToMessageId = chat_validate_reply_target($db, $requestedConversationId, $replyToMessageId) ?? 0;
                if (!chat_insert_customer_group_message($db, $requestedConversationId, $currentCustomerId, $messageBody, null, $replyToMessageId > 0 ? $replyToMessageId : null)) {
                    if ($responseFormat === 'json') {
                        chat_json_response(['ok' => false, 'message' => 'You cannot send messages in this conversation right now.']);
                    }
                    echo '<p>You cannot send messages in this conversation right now.</p>';
                    exit;
                }
                chat_queue_group_customer_notifications_if_offline(
                    $db,
                    $requestedConversationId,
                    ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0],
                    $messageBody
                );
                chat_register_customer_message_sent();
            } else {
                $supportConversationId = chat_find_or_create_conversation($db, $currentCustomerId);
                $replyToMessageId = chat_validate_reply_target($db, $supportConversationId, $replyToMessageId) ?? 0;
                chat_insert_customer_message($db, $currentCustomerId, $messageBody, null, $replyToMessageId > 0 ? $replyToMessageId : null);
                chat_register_customer_message_sent();
            }
        }
    }

    if (($action === 'upload' || !empty($_FILES['file']['tmp_name'])) && !empty($_FILES['file']['tmp_name'])) {
        chat_require_rate_limit_slot($responseFormat);
        $publicPath = chat_store_uploaded_image($_FILES['file'], $currentCustomerId);
        if ($publicPath !== null) {
            $replyToMessageId = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : 0;
            if ($customerHasFullMessenger && $requestedConversationId > 0 && chat_group_accessible_for_customer($db, $currentCustomerId, $requestedConversationId)) {
                $replyToMessageId = chat_validate_reply_target($db, $requestedConversationId, $replyToMessageId) ?? 0;
                if (!chat_insert_customer_group_message($db, $requestedConversationId, $currentCustomerId, '', $publicPath, $replyToMessageId > 0 ? $replyToMessageId : null)) {
                    if ($responseFormat === 'json') {
                        chat_json_response(['ok' => false, 'message' => 'You cannot send messages in this conversation right now.']);
                    }
                    echo '<p>You cannot send messages in this conversation right now.</p>';
                    exit;
                }
                chat_queue_group_customer_notifications_if_offline(
                    $db,
                    $requestedConversationId,
                    ['participant_type' => 'customer', 'customer_id' => $currentCustomerId, 'admin_user_id' => 0],
                    '',
                    $publicPath
                );
            } else {
                $supportConversationId = chat_find_or_create_conversation($db, $currentCustomerId);
                $replyToMessageId = chat_validate_reply_target($db, $supportConversationId, $replyToMessageId) ?? 0;
                chat_insert_customer_message($db, $currentCustomerId, '', $publicPath, $replyToMessageId > 0 ? $replyToMessageId : null);
            }
            chat_register_customer_message_sent();
        } elseif ($responseFormat === 'json') {
            chat_json_response(['ok' => false, 'message' => 'Image upload failed.']);
        }
    }

    if ($action === 'faq_prompt' && isset($selectedFaqPrompt)) {
        chat_require_rate_limit_slot($responseFormat);
        chat_insert_customer_message($db, $currentCustomerId, (string)$selectedFaqPrompt['title']);
        chat_insert_support_message($db, $currentCustomerId, (string)$selectedFaqPrompt['answer'], date('Y-m-d H:i:s', time() + 3));
        chat_register_customer_message_sent();
    }

    if ($action === 'faq_reply' && isset($selectedFaqPrompt)) {
        chat_mark_admin_messages_read($db, $currentCustomerId);
    }
} else {
    if (isset($_POST['user']) || $action === 'read') {
        $db->query(
            "UPDATE produkty_chat
             SET status = 1
             WHERE user1 <> {$currentCustomerId}
               AND user2 = {$currentCustomerId}
               AND status = 0"
        );
    }

    if ($action === 'delete_message') {
        if (!$messageId || !chat_delete_customer_message_if_allowed($db, $currentCustomerId, $messageId)) {
            if ($responseFormat === 'json') {
                chat_json_response(['ok' => false, 'message' => 'This message can no longer be deleted.']);
            }
            echo '<p>This message can no longer be deleted.</p>';
            exit;
        }
    }

    if ((isset($_POST['id_usera'], $_POST['tresc']) && (int)$_POST['id_usera'] === $currentCustomerId) || $action === 'send') {
        $messageBody = trim((string)($_POST['tresc'] ?? ''));
        if ($messageBody !== '') {
            chat_require_rate_limit_slot($responseFormat);
            $messageBody = chat_prepare_message_with_link_preview(
                $messageBody,
                $chatLocaleCode,
                empty($_POST['link_preview_removed']),
                trim((string)($_POST['link_preview_url'] ?? ''))
            );
            $db->insert(
                ['user1', 'user2', 'tresc', 'data', 'status'],
                [$currentCustomerId, chat_first_admin_id($db), $messageBody, date('Y-m-d H:i:s'), 0],
                'produkty_chat'
            );
            chat_register_customer_message_sent();
        }
    }

    if ($action === 'faq_prompt' && isset($selectedFaqPrompt)) {
        chat_require_rate_limit_slot($responseFormat);
        $db->insert(
            ['user1', 'user2', 'tresc', 'data', 'status'],
            [$currentCustomerId, chat_first_admin_id($db), (string)$selectedFaqPrompt['title'], date('Y-m-d H:i:s'), 0],
            'produkty_chat'
        );
        $db->insert(
            ['user1', 'user2', 'tresc', 'data', 'status'],
            [chat_first_admin_id($db), $currentCustomerId, (string)$selectedFaqPrompt['answer'], date('Y-m-d H:i:s', time() + 3), 1],
            'produkty_chat'
        );
        chat_register_customer_message_sent();
    }

    if ($action === 'faq_reply' && isset($selectedFaqPrompt)) {
    }
}

$payload = chat_render_payload(
    $smarty,
    $db,
    $user,
    $reseller,
    is_array($settings ?? null) ? $settings : [],
    isset($currentLocale) ? (string)$currentLocale : 'en',
    $requestedConversationId > 0 ? $requestedConversationId : null,
    $requestedConversationType !== '' ? $requestedConversationType : null
);
$rateLimitState = chat_rate_limit_state();

if ($responseFormat === 'json') {
    $groupInvites = chat_customer_can_use_groups($user, is_array($settings ?? null) ? $settings : []) ? chat_customer_group_pending_invites($db, $currentCustomerId) : [];
    $smarty->assign('group_chat_pending_invites', $groupInvites);
    $responsePayload = [
        'ok' => true,
        'html' => $payload['html'],
        'unread_count' => $payload['unread_count'],
        'last_message_id' => $payload['last_message_id'],
        'message_limit' => $payload['message_limit'],
        'loaded_message_count' => $payload['loaded_message_count'],
        'total_message_count' => $payload['total_message_count'],
        'has_more_messages' => $payload['has_more_messages'],
        'oldest_message_id' => $payload['oldest_message_id'],
        'group_invites_html' => $smarty->fetch('profil/group_chat_invites.tpl'),
        'cooldown_active' => !empty($rateLimitState['is_blocked']),
        'cooldown_seconds' => (int)($rateLimitState['remaining_seconds'] ?? 0),
    ];
    if ($responseMessage !== '') {
        $responsePayload['message'] = $responseMessage;
    }

    if ($groupActionConversationId > 0) {
        $responsePayload['conversation_id'] = $groupActionConversationId;
        $responsePayload['conversation_title'] = $groupActionConversationTitle;
        if ($groupActionAvatarUrl !== '') {
            $responsePayload['conversation_avatar_url'] = $groupActionAvatarUrl;
        }
    }

    if ($action === 'faq_prompt' && isset($selectedFaqPrompt)) {
        $responsePayload['faq_answer_text'] = (string)$selectedFaqPrompt['answer'];
    }

    chat_json_response($responsePayload);
}

echo $payload['html'];
