<?php

if (!function_exists('chat_ensure_group_chat_runtime')) {
    function chat_ensure_group_chat_runtime(Mysql_ks $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $done = true;

        if (!schema_object_exists($db, 'support_conversations')) {
            return;
        }

        $conversationColumns = [
            'group_name' => "ALTER TABLE support_conversations ADD COLUMN group_name VARCHAR(191) DEFAULT NULL AFTER subject",
            'is_group_read_only' => "ALTER TABLE support_conversations ADD COLUMN is_group_read_only TINYINT(1) NOT NULL DEFAULT 0 AFTER group_name",
            'group_created_by_customer_id' => "ALTER TABLE support_conversations ADD COLUMN group_created_by_customer_id INT UNSIGNED DEFAULT NULL AFTER is_group_read_only",
            'group_created_by_admin_user_id' => "ALTER TABLE support_conversations ADD COLUMN group_created_by_admin_user_id INT UNSIGNED DEFAULT NULL AFTER group_created_by_customer_id",
        ];

        foreach ($conversationColumns as $columnName => $alterSql) {
            if (!schema_column_exists($db, 'support_conversations', $columnName)) {
                @$db->query($alterSql);
                schema_forget_column_cache('support_conversations', $columnName);
            }
        }

        if (!schema_object_exists($db, 'support_conversation_members')) {
            @$db->query(
                "CREATE TABLE IF NOT EXISTS support_conversation_members (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    conversation_id INT UNSIGNED NOT NULL,
                    participant_key VARCHAR(64) NOT NULL,
                    participant_type VARCHAR(16) NOT NULL,
                    customer_id INT UNSIGNED DEFAULT NULL,
                    admin_user_id INT UNSIGNED DEFAULT NULL,
                    role_code VARCHAR(16) NOT NULL DEFAULT 'member',
                    invite_status VARCHAR(16) NOT NULL DEFAULT 'pending',
                    can_post TINYINT(1) NOT NULL DEFAULT 1,
                    invited_by_customer_id INT UNSIGNED DEFAULT NULL,
                    invited_by_admin_user_id INT UNSIGNED DEFAULT NULL,
                    responded_at DATETIME DEFAULT NULL,
                    joined_at DATETIME DEFAULT NULL,
                    left_at DATETIME DEFAULT NULL,
                    last_read_message_id INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_support_conversation_members_conv_participant (conversation_id, participant_key),
                    KEY idx_support_conversation_members_customer (customer_id),
                    KEY idx_support_conversation_members_admin (admin_user_id),
                    KEY idx_support_conversation_members_status (invite_status),
                    KEY idx_support_conversation_members_conversation (conversation_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            unset($GLOBALS['schema_object_exists_cache']['support_conversation_members']);
        }
    }

    function chat_current_datetime(): string
    {
        return function_exists('app_current_datetime_string')
            ? app_current_datetime_string()
            : date('Y-m-d H:i:s');
    }

    function chat_customer_can_use_groups(array $customer): bool
    {
        return function_exists('app_normalize_customer_type')
            ? app_normalize_customer_type((string)($customer['customer_type'] ?? '')) === 'reseller'
            : false;
    }

    function chat_reseller_group_chat_limit(array $settings = []): int
    {
        $limit = isset($settings['reseller_group_chat_limit']) ? (int)$settings['reseller_group_chat_limit'] : 10;
        if ($limit < 0) {
            return 0;
        }
        if ($limit > 10) {
            return 10;
        }
        return $limit;
    }

    function chat_customer_group_created_count(Mysql_ks $db, int $customerId): int
    {
        chat_ensure_group_chat_runtime($db);
        if ($customerId <= 0 || !schema_object_exists($db, 'support_conversations')) {
            return 0;
        }

        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM support_conversations
             WHERE conversation_type = 'group_chat'
               AND group_created_by_customer_id = {$customerId}"
        );

        return (int)($row['total'] ?? 0);
    }

    function chat_customer_group_creation_state(Mysql_ks $db, array $customer, array $settings = []): array
    {
        $limit = chat_reseller_group_chat_limit($settings);
        $isEligibleRole = chat_customer_can_use_groups($customer);
        $customerId = (int)($customer['id'] ?? 0);
        $createdCount = ($isEligibleRole && $customerId > 0) ? chat_customer_group_created_count($db, $customerId) : 0;
        $allowed = $isEligibleRole && $limit > 0 && $createdCount < $limit;

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'created_count' => $createdCount,
            'remaining_count' => max(0, $limit - $createdCount),
            'blocked_by_limit' => $isEligibleRole && $limit <= 0,
            'reached_limit' => $isEligibleRole && $limit > 0 && $createdCount >= $limit,
        ];
    }

    function chat_participant_key_for_customer(int $customerId): string
    {
        return 'customer:' . max(0, $customerId);
    }

    function chat_participant_key_for_admin(int $adminUserId): string
    {
        return 'admin:' . max(0, $adminUserId);
    }

    function chat_customer_email_short_label(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return 'User';
        }

        $localPart = $email;
        if (strpos($email, '@') !== false) {
            $localPart = (string)substr($email, 0, strpos($email, '@'));
        }

        $localPart = trim($localPart);
        if ($localPart === '') {
            $localPart = $email;
        }

        return function_exists('mb_substr') ? mb_substr($localPart, 0, 32) : substr($localPart, 0, 32);
    }

    function chat_admin_display_label(array $row): string
    {
        $handle = chat_normalize_public_handle((string)($row['public_handle'] ?? ''));
        if ($handle !== '') {
            return $handle;
        }

        $login = trim((string)($row['login_name'] ?? ''));
        if ($login !== '') {
            return $login;
        }

        $email = trim((string)($row['email'] ?? ''));
        if ($email !== '') {
            return chat_customer_email_short_label($email);
        }

        return 'Admin';
    }

    function chat_sender_display_name(array $row, array $reseller = [], string $defaultSupportLabel = 'Support'): string
    {
        $senderType = trim((string)($row['sender_type'] ?? ''));
        if ($senderType === 'admin') {
            $label = trim((string)($row['sender_display_name'] ?? ''));
            if ($label !== '') {
                return $label;
            }

            $label = chat_normalize_public_handle((string)($row['admin_public_handle'] ?? ''));
            if ($label !== '') {
                return $label;
            }

            $label = trim((string)($row['admin_login_name'] ?? ''));
            if ($label !== '') {
                return $label;
            }

            return trim($defaultSupportLabel) !== '' ? trim($defaultSupportLabel) : chat_support_name($reseller);
        }

        $label = trim((string)($row['sender_display_name'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $label = trim((string)($row['customer_display_name'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $email = trim((string)($row['customer_email'] ?? ''));
        if ($email !== '') {
            return chat_customer_email_short_label($email);
        }

        return 'User';
    }

    function chat_log_customer_activity(Mysql_ks $db, int $customerId, string $actionKey, string $description, int $adminUserId = 0): void
    {
        if ($customerId <= 0 || !schema_object_exists($db, 'customer_activity_logs')) {
            return;
        }

        $db->insert(
            ['customer_id', 'admin_user_id', 'actor_type', 'action_key', 'description'],
            [$customerId, $adminUserId > 0 ? $adminUserId : null, $adminUserId > 0 ? 'admin' : 'customer', $actionKey, $description],
            'customer_activity_logs'
        );
    }

    function chat_expire_stale_group_invites(Mysql_ks $db, int $expiryHours = 24): void
    {
        chat_ensure_group_chat_runtime($db);
        if (!schema_object_exists($db, 'support_conversation_members')) {
            return;
        }

        $expiryHours = max(1, $expiryHours);
        @$db->query(
            "DELETE FROM support_conversation_members
             WHERE invite_status = 'pending'
               AND created_at < DATE_SUB(NOW(), INTERVAL {$expiryHours} HOUR)"
        );
    }

    function chat_resolve_group_invitee_by_email(Mysql_ks $db, string $email): ?array
    {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        if (schema_object_exists($db, 'customers')) {
            if (function_exists('app_ensure_customer_runtime_columns')) {
                app_ensure_customer_runtime_columns($db);
            }

            $safeEmail = $db->escape($email);
            $customer = $db->select_user(
                "SELECT id, email, customer_type
                 FROM customers
                 WHERE LOWER(email) = '{$safeEmail}'
                 LIMIT 1"
            );
            if (is_array($customer) && !empty($customer['id']) && chat_customer_can_use_groups($customer)) {
                return [
                    'participant_type' => 'customer',
                    'participant_key' => chat_participant_key_for_customer((int)$customer['id']),
                    'customer_id' => (int)$customer['id'],
                    'admin_user_id' => 0,
                    'email' => (string)$customer['email'],
                    'display_name' => chat_customer_email_short_label((string)$customer['email']),
                ];
            }
        }

        if (schema_object_exists($db, 'admin_users')) {
            $safeEmail = $db->escape($email);
            $admin = $db->select_user(
                "SELECT id, email, login_name, public_handle
                 FROM admin_users
                 WHERE LOWER(email) = '{$safeEmail}'
                   AND status = 'active'
                 LIMIT 1"
            );
            if (is_array($admin) && !empty($admin['id'])) {
                return [
                    'participant_type' => 'admin',
                    'participant_key' => chat_participant_key_for_admin((int)$admin['id']),
                    'customer_id' => 0,
                    'admin_user_id' => (int)$admin['id'],
                    'email' => (string)$admin['email'],
                    'display_name' => chat_admin_display_label($admin),
                ];
            }
        }

        return null;
    }

    function chat_validate_group_invitee_email(Mysql_ks $db, string $email, array $creator = [], int $conversationId = 0): array
    {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);

        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }

        $invitee = chat_resolve_group_invitee_by_email($db, $normalizedEmail);
        if (!$invitee) {
            return ['ok' => false, 'message' => 'No reseller or admin account was found for this email.'];
        }

        $creatorType = trim((string)($creator['participant_type'] ?? ''));
        $creatorCustomerId = (int)($creator['customer_id'] ?? 0);
        $creatorAdminUserId = (int)($creator['admin_user_id'] ?? 0);

        if ($creatorType === 'customer' && ($invitee['participant_type'] ?? '') === 'customer' && (int)($invitee['customer_id'] ?? 0) === $creatorCustomerId) {
            return ['ok' => false, 'message' => 'You cannot invite yourself.'];
        }

        if ($creatorType === 'admin' && ($invitee['participant_type'] ?? '') === 'admin' && (int)($invitee['admin_user_id'] ?? 0) === $creatorAdminUserId) {
            return ['ok' => false, 'message' => 'You cannot invite yourself.'];
        }

        if ($conversationId > 0) {
            $conversation = chat_group_conversation_row($db, $conversationId);
            if (!$conversation) {
                return ['ok' => false, 'message' => 'Group chat not found.'];
            }

            if ($creator && !chat_group_can_actor_manage($conversation, $creator)) {
                return ['ok' => false, 'message' => 'Only the group creator can add new members.'];
            }

            $existingMember = chat_group_member_row($db, $conversationId, (string)($invitee['participant_key'] ?? ''));
            $existingStatus = trim((string)($existingMember['invite_status'] ?? ''));
            if ($existingMember && ($existingStatus === 'accepted' || $existingStatus === 'pending')) {
                return ['ok' => false, 'message' => 'This user is already in the group or has a pending invitation.'];
            }
        }

        return [
            'ok' => true,
            'email' => (string)($invitee['email'] ?? $normalizedEmail),
            'display_name' => (string)($invitee['display_name'] ?? $normalizedEmail),
            'participant_type' => (string)($invitee['participant_type'] ?? ''),
            'participant_key' => (string)($invitee['participant_key'] ?? ''),
        ];
    }

    function chat_group_member_row(Mysql_ks $db, int $conversationId, string $participantKey): ?array
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || $participantKey === '' || !schema_object_exists($db, 'support_conversation_members')) {
            return null;
        }

        $safeKey = $db->escape($participantKey);
        $row = $db->select_user(
            "SELECT *
             FROM support_conversation_members
             WHERE conversation_id = {$conversationId}
               AND participant_key = '{$safeKey}'
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_add_group_member(
        Mysql_ks $db,
        int $conversationId,
        array $participant,
        string $inviteStatus,
        string $roleCode = 'member',
        int $invitedByCustomerId = 0,
        int $invitedByAdminUserId = 0
    ): void {
        chat_ensure_group_chat_runtime($db);

        if ($conversationId <= 0 || empty($participant['participant_key']) || !schema_object_exists($db, 'support_conversation_members')) {
            return;
        }

        $participantKey = (string)$participant['participant_key'];
        $currentTime = chat_current_datetime();
        $joinedAt = $inviteStatus === 'accepted' ? $currentTime : null;
        $respondedAt = $inviteStatus === 'accepted' ? $currentTime : null;
        $existing = chat_group_member_row($db, $conversationId, $participantKey);

        $values = [
            $conversationId,
            $participantKey,
            (string)$participant['participant_type'],
            !empty($participant['customer_id']) ? (int)$participant['customer_id'] : null,
            !empty($participant['admin_user_id']) ? (int)$participant['admin_user_id'] : null,
            $roleCode,
            $inviteStatus,
            1,
            $invitedByCustomerId > 0 ? $invitedByCustomerId : null,
            $invitedByAdminUserId > 0 ? $invitedByAdminUserId : null,
            $respondedAt,
            $joinedAt,
            null,
            0,
        ];

        if ($existing) {
            $db->update_using_id(
                ['participant_type', 'customer_id', 'admin_user_id', 'role_code', 'invite_status', 'can_post', 'invited_by_customer_id', 'invited_by_admin_user_id', 'responded_at', 'joined_at', 'left_at'],
                [
                    (string)$participant['participant_type'],
                    !empty($participant['customer_id']) ? (int)$participant['customer_id'] : null,
                    !empty($participant['admin_user_id']) ? (int)$participant['admin_user_id'] : null,
                    $roleCode,
                    $inviteStatus,
                    1,
                    $invitedByCustomerId > 0 ? $invitedByCustomerId : null,
                    $invitedByAdminUserId > 0 ? $invitedByAdminUserId : null,
                    $respondedAt,
                    $joinedAt,
                    null,
                ],
                'support_conversation_members',
                (int)$existing['id']
            );
            return;
        }

        $db->insert(
            ['conversation_id', 'participant_key', 'participant_type', 'customer_id', 'admin_user_id', 'role_code', 'invite_status', 'can_post', 'invited_by_customer_id', 'invited_by_admin_user_id', 'responded_at', 'joined_at', 'left_at', 'last_read_message_id'],
            $values,
            'support_conversation_members'
        );
    }

    function chat_group_conversation_title(array $row, string $fallback = 'Group chat'): string
    {
        $title = trim((string)($row['group_name'] ?? $row['subject'] ?? ''));
        return $title !== '' ? $title : $fallback;
    }

    function chat_group_conversation_row(Mysql_ks $db, int $conversationId): ?array
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversations')) {
            return null;
        }

        $row = $db->select_user(
            "SELECT *
             FROM support_conversations
             WHERE id = {$conversationId}
               AND conversation_type = 'group_chat'
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_group_can_customer_manage(array $conversation, int $customerId): bool
    {
        return $customerId > 0 && (int)($conversation['group_created_by_customer_id'] ?? 0) === $customerId;
    }

    function chat_group_can_admin_manage(array $conversation, int $adminUserId): bool
    {
        return $adminUserId > 0 && (int)($conversation['group_created_by_admin_user_id'] ?? 0) === $adminUserId;
    }

    function chat_group_can_actor_manage(array $conversation, array $actor): bool
    {
        $participantType = trim((string)($actor['participant_type'] ?? ''));
        if ($participantType === 'customer') {
            return chat_group_can_customer_manage($conversation, (int)($actor['customer_id'] ?? 0));
        }

        if ($participantType === 'admin') {
            return chat_group_can_admin_manage($conversation, (int)($actor['admin_user_id'] ?? 0));
        }

        return false;
    }

    function chat_group_creator_label(Mysql_ks $db, array $conversation, string $fallback = 'Group creator'): string
    {
        $customerId = (int)($conversation['group_created_by_customer_id'] ?? 0);
        if ($customerId > 0 && schema_object_exists($db, 'customers')) {
            $customer = $db->select_user("SELECT email FROM customers WHERE id = {$customerId} LIMIT 1");
            if (is_array($customer) && !empty($customer['email'])) {
                return chat_customer_email_short_label((string)$customer['email']);
            }
        }

        $adminUserId = (int)($conversation['group_created_by_admin_user_id'] ?? 0);
        if ($adminUserId > 0 && schema_object_exists($db, 'admin_users')) {
            $admin = $db->select_user("SELECT login_name, public_handle, email FROM admin_users WHERE id = {$adminUserId} LIMIT 1");
            if (is_array($admin) && !empty($admin)) {
                return chat_admin_display_label($admin);
            }
        }

        return $fallback;
    }

    function chat_delete_group_attachment_file(?string $attachmentPath): void
    {
        $attachmentPath = trim((string)$attachmentPath);
        if ($attachmentPath === '' || strpos($attachmentPath, '/uploads/chat/') !== 0) {
            return;
        }

        $filePath = dirname(__DIR__, 2) . '/public_html' . $attachmentPath;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    function chat_group_participant_label(Mysql_ks $db, array $member): string
    {
        if (!empty($member['customer_id']) && schema_object_exists($db, 'customers')) {
            $customerId = (int)$member['customer_id'];
            $customer = $db->select_user("SELECT email FROM customers WHERE id = {$customerId} LIMIT 1");
            if (is_array($customer) && !empty($customer['email'])) {
                return chat_customer_email_short_label((string)$customer['email']);
            }
        }

        if (!empty($member['admin_user_id']) && schema_object_exists($db, 'admin_users')) {
            $adminUserId = (int)$member['admin_user_id'];
            $admin = $db->select_user("SELECT login_name, public_handle, email FROM admin_users WHERE id = {$adminUserId} LIMIT 1");
            if (is_array($admin) && !empty($admin)) {
                return chat_admin_display_label($admin);
            }
        }

        return !empty($member['admin_user_id']) ? 'Admin' : 'User';
    }

    function chat_group_customer_member_ids(Mysql_ks $db, int $conversationId, array $statuses = ['accepted']): array
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversation_members')) {
            return [];
        }

        $safeStatuses = [];
        foreach ($statuses as $status) {
            $status = trim((string)$status);
            if ($status !== '') {
                $safeStatuses[] = "'" . $db->escape($status) . "'";
            }
        }
        if (!$safeStatuses) {
            $safeStatuses[] = "'accepted'";
        }

        $rows = $db->select_full_user(
            "SELECT customer_id
             FROM support_conversation_members
             WHERE conversation_id = {$conversationId}
               AND customer_id IS NOT NULL
               AND invite_status IN (" . implode(', ', $safeStatuses) . ")"
        );

        $customerIds = [];
        foreach ($rows as $row) {
            $customerId = (int)($row['customer_id'] ?? 0);
            if ($customerId > 0) {
                $customerIds[$customerId] = $customerId;
            }
        }

        return array_values($customerIds);
    }

    function chat_log_group_activity_for_customers(
        Mysql_ks $db,
        int $conversationId,
        string $actionKey,
        string $description,
        int $adminUserId = 0,
        int $excludeCustomerId = 0,
        array $statuses = ['accepted']
    ): void {
        foreach (chat_group_customer_member_ids($db, $conversationId, $statuses) as $customerId) {
            if ($excludeCustomerId > 0 && $customerId === $excludeCustomerId) {
                continue;
            }

            chat_log_customer_activity($db, $customerId, $actionKey, $description, $adminUserId);
        }
    }

    function chat_create_group_conversation(
        Mysql_ks $db,
        array $creator,
        string $groupName,
        array $inviteEmails,
        bool $readOnly = false,
        array $settings = []
    ): array {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);

        $creatorType = trim((string)($creator['participant_type'] ?? ''));
        $creatorCustomerId = (int)($creator['customer_id'] ?? 0);
        $creatorAdminUserId = (int)($creator['admin_user_id'] ?? 0);
        $groupName = trim($groupName);
        $groupName = preg_replace('/\s+/u', ' ', $groupName) ?? $groupName;
        $groupNameLength = function_exists('mb_strlen') ? mb_strlen($groupName) : strlen($groupName);

        if ($groupName === '') {
            return ['ok' => false, 'message' => 'Group name is required.'];
        }
        if ($groupNameLength > 20) {
            return ['ok' => false, 'message' => 'Group name can be up to 20 characters.'];
        }

        if ($creatorType === 'customer' && $creatorCustomerId <= 0) {
            return ['ok' => false, 'message' => 'Creator is invalid.'];
        }
        if ($creatorType === 'admin' && $creatorAdminUserId <= 0) {
            return ['ok' => false, 'message' => 'Creator is invalid.'];
        }
        if ($creatorType !== 'customer' && $creatorType !== 'admin') {
            return ['ok' => false, 'message' => 'Creator is invalid.'];
        }

        if ($creatorType === 'customer') {
            $creatorCustomer = function_exists('app_load_customer_session_record') ? app_load_customer_session_record($db, $creatorCustomerId) : null;
            if (!is_array($creatorCustomer) || !chat_customer_can_use_groups($creatorCustomer)) {
                return ['ok' => false, 'message' => 'Only reseller users can create group chats.'];
            }

            $creationState = chat_customer_group_creation_state($db, $creatorCustomer, $settings);
            if (!$creationState['allowed']) {
                if (!empty($creationState['blocked_by_limit'])) {
                    return ['ok' => false, 'message' => 'Group chat creation is disabled for reseller accounts.'];
                }

                if (!empty($creationState['reached_limit'])) {
                    return ['ok' => false, 'message' => 'You reached the maximum number of group chats for your account.'];
                }
            }
        }

        $invitees = [];
        $seenKeys = [];
        foreach ($inviteEmails as $inviteEmail) {
            $invitee = chat_resolve_group_invitee_by_email($db, (string)$inviteEmail);
            if (!$invitee) {
                continue;
            }
            if (($invitee['participant_type'] ?? '') === 'customer' && (int)($invitee['customer_id'] ?? 0) === $creatorCustomerId) {
                continue;
            }
            if (($invitee['participant_type'] ?? '') === 'admin' && (int)($invitee['admin_user_id'] ?? 0) === $creatorAdminUserId) {
                continue;
            }
            if (isset($seenKeys[$invitee['participant_key']])) {
                continue;
            }
            $seenKeys[$invitee['participant_key']] = true;
            $invitees[] = $invitee;
        }

        if (!$invitees) {
            return ['ok' => false, 'message' => 'Add at least one reseller or admin email to create a group.'];
        }

        $currentTime = chat_current_datetime();
        $inserted = $db->insert(
            ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'group_name', 'is_group_read_only', 'group_created_by_customer_id', 'group_created_by_admin_user_id', 'status', 'priority', 'created_at', 'updated_at'],
            [
                'group_chat',
                $creatorCustomerId > 0 ? $creatorCustomerId : null,
                $creatorAdminUserId > 0 ? $creatorAdminUserId : null,
                $groupName,
                $groupName,
                $readOnly ? 1 : 0,
                $creatorCustomerId > 0 ? $creatorCustomerId : null,
                $creatorAdminUserId > 0 ? $creatorAdminUserId : null,
                'open',
                'normal',
                $currentTime,
                $currentTime,
            ],
            'support_conversations'
        );

        if (!$inserted) {
            return ['ok' => false, 'message' => 'Unable to create the group chat.'];
        }

        $conversationId = (int)$db->id();
        $creatorParticipant = [
            'participant_key' => $creatorType === 'customer' ? chat_participant_key_for_customer($creatorCustomerId) : chat_participant_key_for_admin($creatorAdminUserId),
            'participant_type' => $creatorType,
            'customer_id' => $creatorCustomerId,
            'admin_user_id' => $creatorAdminUserId,
        ];
        chat_add_group_member($db, $conversationId, $creatorParticipant, 'accepted', 'owner', $creatorCustomerId, $creatorAdminUserId);

        $creatorLabel = '';
        if ($creatorType === 'customer' && !empty($creatorCustomer['email'])) {
            $creatorLabel = chat_customer_email_short_label((string)$creatorCustomer['email']);
        } elseif ($creatorType === 'admin' && schema_object_exists($db, 'admin_users')) {
            $creatorAdmin = $db->select_user("SELECT login_name, public_handle, email FROM admin_users WHERE id = {$creatorAdminUserId} LIMIT 1");
            $creatorLabel = is_array($creatorAdmin) ? chat_admin_display_label($creatorAdmin) : 'Admin';
        }
        if ($creatorLabel === '') {
            $creatorLabel = $creatorType === 'admin' ? 'Admin' : 'Reseller';
        }

        $invitedCount = 0;
        foreach ($invitees as $invitee) {
            chat_add_group_member($db, $conversationId, $invitee, 'pending', 'member', $creatorCustomerId, $creatorAdminUserId);
            if (($invitee['participant_type'] ?? '') === 'customer' && !empty($invitee['customer_id'])) {
                chat_log_customer_activity(
                    $db,
                    (int)$invitee['customer_id'],
                    'group_chat_invited',
                    'You were invited to group chat "' . $groupName . '" by ' . $creatorLabel . '.',
                    $creatorAdminUserId
                );
            }
            $invitedCount++;
        }

        if ($creatorCustomerId > 0) {
            chat_log_customer_activity(
                $db,
                $creatorCustomerId,
                'group_chat_created',
                'You created group chat "' . $groupName . '" and invited ' . $invitedCount . ' participant(s).',
                $creatorAdminUserId
            );
        }

        chat_log_group_activity_for_customers(
            $db,
            $conversationId,
            'group_chat_activity',
            $creatorLabel . ' created group chat "' . $groupName . '".',
            $creatorAdminUserId,
            $creatorCustomerId
        );

        return [
            'ok' => true,
            'conversation_id' => $conversationId,
            'title' => $groupName,
        ];
    }

    function chat_customer_group_pending_invites(Mysql_ks $db, int $customerId): array
    {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);
        if ($customerId <= 0 || !schema_object_exists($db, 'support_conversation_members')) {
            return [];
        }

        $participantKey = $db->escape(chat_participant_key_for_customer($customerId));
        return $db->select_full_user(
            "SELECT
                support_conversation_members.conversation_id,
                support_conversations.group_name,
                support_conversations.subject,
                support_conversations.is_group_read_only,
                inviter_customers.email AS invited_by_customer_email,
                inviter_admins.login_name AS invited_by_admin_login,
                inviter_admins.public_handle AS invited_by_admin_handle
             FROM support_conversation_members
             INNER JOIN support_conversations
                ON support_conversations.id = support_conversation_members.conversation_id
             LEFT JOIN customers AS inviter_customers
                ON inviter_customers.id = support_conversation_members.invited_by_customer_id
             LEFT JOIN admin_users AS inviter_admins
                ON inviter_admins.id = support_conversation_members.invited_by_admin_user_id
             WHERE support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'pending'
               AND support_conversations.conversation_type = 'group_chat'
             ORDER BY support_conversation_members.id DESC"
        );
    }

    function chat_admin_group_pending_invites(Mysql_ks $db, int $adminUserId): array
    {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);
        if ($adminUserId <= 0 || !schema_object_exists($db, 'support_conversation_members')) {
            return [];
        }

        $participantKey = $db->escape(chat_participant_key_for_admin($adminUserId));
        return $db->select_full_user(
            "SELECT
                support_conversation_members.conversation_id,
                support_conversations.group_name,
                support_conversations.subject,
                inviter_customers.email AS invited_by_customer_email,
                inviter_admins.login_name AS invited_by_admin_login,
                inviter_admins.public_handle AS invited_by_admin_handle
             FROM support_conversation_members
             INNER JOIN support_conversations
                ON support_conversations.id = support_conversation_members.conversation_id
             LEFT JOIN customers AS inviter_customers
                ON inviter_customers.id = support_conversation_members.invited_by_customer_id
             LEFT JOIN admin_users AS inviter_admins
                ON inviter_admins.id = support_conversation_members.invited_by_admin_user_id
             WHERE support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'pending'
               AND support_conversations.conversation_type = 'group_chat'
             ORDER BY support_conversation_members.id DESC"
        );
    }

    function chat_update_group_invite_status(
        Mysql_ks $db,
        int $conversationId,
        string $participantKey,
        string $status
    ): array {
        chat_ensure_group_chat_runtime($db);
        $member = chat_group_member_row($db, $conversationId, $participantKey);
        if (!$member || trim((string)($member['invite_status'] ?? '')) !== 'pending') {
            return ['ok' => false, 'message' => 'Invitation not found.'];
        }

        $status = $status === 'accepted' ? 'accepted' : 'rejected';
        $currentTime = chat_current_datetime();
        $db->update_using_id(
            ['invite_status', 'responded_at', 'joined_at', 'left_at'],
            [$status, $currentTime, $status === 'accepted' ? $currentTime : null, null],
            'support_conversation_members',
            (int)$member['id']
        );

        $conversation = $db->select_user("SELECT group_name, subject FROM support_conversations WHERE id = {$conversationId} LIMIT 1");
        $title = chat_group_conversation_title((array)$conversation);
        $memberLabel = chat_group_participant_label($db, $member);

        if (!empty($member['customer_id'])) {
            chat_log_customer_activity(
                $db,
                (int)$member['customer_id'],
                $status === 'accepted' ? 'group_chat_joined' : 'group_chat_rejected',
                $status === 'accepted'
                    ? 'You joined group chat "' . $title . '".'
                    : 'You rejected invitation to group chat "' . $title . '".'
            );
        }

        if ($status === 'accepted') {
            chat_log_group_activity_for_customers(
                $db,
                $conversationId,
                'group_chat_activity',
                $memberLabel . ' joined group chat "' . $title . '".',
                (int)($member['invited_by_admin_user_id'] ?? 0),
                (int)($member['customer_id'] ?? 0)
            );
        }

        return ['ok' => true];
    }

    function chat_invite_members_to_group_conversation(
        Mysql_ks $db,
        int $conversationId,
        array $creator,
        array $inviteEmails
    ): array {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);

        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        if (!chat_group_can_actor_manage($conversation, $creator)) {
            return ['ok' => false, 'message' => 'Only the group creator can add new members.'];
        }

        $creatorType = trim((string)($creator['participant_type'] ?? ''));
        $creatorCustomerId = (int)($creator['customer_id'] ?? 0);
        $creatorAdminUserId = (int)($creator['admin_user_id'] ?? 0);

        if ($creatorType === 'customer' && !chat_group_accessible_for_customer($db, $creatorCustomerId, $conversationId)) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        if ($creatorType === 'admin' && !chat_group_accessible_for_admin($db, $creatorAdminUserId, $conversationId)) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        $conversationTitle = chat_group_conversation_title($conversation);
        $creatorLabel = chat_group_creator_label($db, $conversation, $creatorType === 'admin' ? 'Admin' : 'Reseller');
        $invitedCount = 0;
        $duplicateCount = 0;
        $seenKeys = [];

        foreach ($inviteEmails as $inviteEmail) {
            $invitee = chat_resolve_group_invitee_by_email($db, (string)$inviteEmail);
            if (!$invitee || empty($invitee['participant_key'])) {
                continue;
            }

            $participantKey = (string)$invitee['participant_key'];
            if (isset($seenKeys[$participantKey])) {
                continue;
            }
            $seenKeys[$participantKey] = true;

            $validation = chat_validate_group_invitee_email($db, (string)($invitee['email'] ?? ''), $creator, $conversationId);
            if (empty($validation['ok'])) {
                $duplicateCount++;
                continue;
            }

            chat_add_group_member($db, $conversationId, $invitee, 'pending', 'member', $creatorCustomerId, $creatorAdminUserId);
            if (($invitee['participant_type'] ?? '') === 'customer' && !empty($invitee['customer_id'])) {
                chat_log_customer_activity(
                    $db,
                    (int)$invitee['customer_id'],
                    'group_chat_invited',
                    'You were invited to group chat "' . $conversationTitle . '" by ' . $creatorLabel . '.',
                    $creatorAdminUserId
                );
            }
            $invitedCount++;
        }

        if ($invitedCount <= 0) {
            if ($duplicateCount > 0) {
                return ['ok' => false, 'message' => 'Selected users are already in the group or already have pending invitations.'];
            }

            return ['ok' => false, 'message' => 'Add at least one valid reseller or admin email.'];
        }

        if ($creatorCustomerId > 0) {
            chat_log_customer_activity(
                $db,
                $creatorCustomerId,
                'group_chat_members_invited',
                'You invited ' . $invitedCount . ' participant(s) to group chat "' . $conversationTitle . '".',
                $creatorAdminUserId
            );
        }

        chat_log_group_activity_for_customers(
            $db,
            $conversationId,
            'group_chat_activity',
            $creatorLabel . ' invited ' . $invitedCount . ' participant(s) to group chat "' . $conversationTitle . '".',
            $creatorAdminUserId,
            $creatorCustomerId
        );

        return ['ok' => true, 'invited_count' => $invitedCount];
    }

    function chat_leave_group_conversation(
        Mysql_ks $db,
        int $conversationId,
        string $participantKey
    ): array {
        chat_ensure_group_chat_runtime($db);
        $member = chat_group_member_row($db, $conversationId, $participantKey);
        if (!$member || trim((string)($member['invite_status'] ?? '')) !== 'accepted') {
            return ['ok' => false, 'message' => 'Conversation not found.'];
        }

        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Conversation not found.'];
        }

        if (
            (!empty($member['customer_id']) && chat_group_can_customer_manage($conversation, (int)$member['customer_id']))
            || (!empty($member['admin_user_id']) && chat_group_can_admin_manage($conversation, (int)$member['admin_user_id']))
        ) {
            return ['ok' => false, 'message' => 'As the group creator, remove the group instead of leaving it.'];
        }

        $currentTime = chat_current_datetime();
        $db->update_using_id(
            ['invite_status', 'responded_at', 'left_at'],
            ['left', $currentTime, $currentTime],
            'support_conversation_members',
            (int)$member['id']
        );

        $title = chat_group_conversation_title((array)$conversation);
        $memberLabel = chat_group_participant_label($db, $member);
        if (!empty($member['customer_id'])) {
            chat_log_customer_activity($db, (int)$member['customer_id'], 'group_chat_left', 'You left group chat "' . $title . '".');
        }

        chat_log_group_activity_for_customers(
            $db,
            $conversationId,
            'group_chat_activity',
            $memberLabel . ' left group chat "' . $title . '".',
            0,
            (int)($member['customer_id'] ?? 0)
        );

        return ['ok' => true];
    }

    function chat_delete_group_conversation(Mysql_ks $db, int $conversationId, array $actor): array
    {
        chat_ensure_group_chat_runtime($db);
        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        if (!chat_group_can_actor_manage($conversation, $actor)) {
            return ['ok' => false, 'message' => 'Only the group creator can remove this group.'];
        }

        $actorType = trim((string)($actor['participant_type'] ?? ''));
        $actorCustomerId = (int)($actor['customer_id'] ?? 0);
        $actorAdminUserId = (int)($actor['admin_user_id'] ?? 0);

        if ($actorType === 'customer' && !chat_group_accessible_for_customer($db, $actorCustomerId, $conversationId)) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        if ($actorType === 'admin' && !chat_group_accessible_for_admin($db, $actorAdminUserId, $conversationId)) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        $title = chat_group_conversation_title($conversation);
        $actorLabel = chat_group_creator_label($db, $conversation, $actorType === 'admin' ? 'Admin' : 'Reseller');

        if ($actorCustomerId > 0) {
            chat_log_customer_activity(
                $db,
                $actorCustomerId,
                'group_chat_deleted',
                'You removed group chat "' . $title . '".',
                $actorAdminUserId
            );
        }

        chat_log_group_activity_for_customers(
            $db,
            $conversationId,
            'group_chat_deleted',
            $actorLabel . ' removed group chat "' . $title . '".',
            $actorAdminUserId,
            $actorCustomerId,
            ['accepted', 'pending']
        );

        if (schema_object_exists($db, 'support_messages')) {
            $attachments = $db->select_full_user(
                "SELECT attachment_path
                 FROM support_messages
                 WHERE conversation_id = {$conversationId}
                   AND attachment_path IS NOT NULL
                   AND attachment_path != ''"
            );
            foreach ($attachments as $attachmentRow) {
                chat_delete_group_attachment_file((string)($attachmentRow['attachment_path'] ?? ''));
            }

            @$db->query("DELETE FROM support_messages WHERE conversation_id = {$conversationId}");
        }

        if (schema_object_exists($db, 'support_conversation_members')) {
            @$db->query("DELETE FROM support_conversation_members WHERE conversation_id = {$conversationId}");
        }

        @$db->query("DELETE FROM support_conversations WHERE id = {$conversationId} AND conversation_type = 'group_chat' LIMIT 1");

        return ['ok' => true];
    }

    function chat_group_accessible_for_customer(Mysql_ks $db, int $customerId, int $conversationId): ?array
    {
        chat_ensure_group_chat_runtime($db);
        if ($customerId <= 0 || $conversationId <= 0) {
            return null;
        }

        $participantKey = $db->escape(chat_participant_key_for_customer($customerId));
        $row = $db->select_user(
            "SELECT
                support_conversations.*,
                support_conversation_members.invite_status,
                support_conversation_members.last_read_message_id
             FROM support_conversations
             INNER JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
             WHERE support_conversations.id = {$conversationId}
               AND support_conversations.conversation_type = 'group_chat'
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'accepted'
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_group_accessible_for_admin(Mysql_ks $db, int $adminUserId, int $conversationId): ?array
    {
        chat_ensure_group_chat_runtime($db);
        if ($adminUserId <= 0 || $conversationId <= 0) {
            return null;
        }

        $participantKey = $db->escape(chat_participant_key_for_admin($adminUserId));
        $row = $db->select_user(
            "SELECT
                support_conversations.*,
                support_conversation_members.invite_status,
                support_conversation_members.last_read_message_id
             FROM support_conversations
             INNER JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
             WHERE support_conversations.id = {$conversationId}
               AND support_conversations.conversation_type = 'group_chat'
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'accepted'
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_mark_group_read_for_customer(Mysql_ks $db, int $customerId, int $conversationId): void
    {
        chat_ensure_group_chat_runtime($db);
        $conversation = chat_group_accessible_for_customer($db, $customerId, $conversationId);
        if (!$conversation || !schema_object_exists($db, 'support_messages')) {
            return;
        }

        $maxRow = $db->select_user("SELECT MAX(id) AS max_id FROM support_messages WHERE conversation_id = {$conversationId}");
        $maxId = (int)($maxRow['max_id'] ?? 0);
        $member = chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($customerId));
        if ($member) {
            $db->update_using_id(['last_read_message_id'], [$maxId], 'support_conversation_members', (int)$member['id']);
        }
    }

    function chat_mark_group_read_for_admin(Mysql_ks $db, int $adminUserId, int $conversationId): void
    {
        chat_ensure_group_chat_runtime($db);
        $conversation = chat_group_accessible_for_admin($db, $adminUserId, $conversationId);
        if (!$conversation || !schema_object_exists($db, 'support_messages')) {
            return;
        }

        $maxRow = $db->select_user("SELECT MAX(id) AS max_id FROM support_messages WHERE conversation_id = {$conversationId}");
        $maxId = (int)($maxRow['max_id'] ?? 0);
        $member = chat_group_member_row($db, $conversationId, chat_participant_key_for_admin($adminUserId));
        if ($member) {
            $db->update_using_id(['last_read_message_id'], [$maxId], 'support_conversation_members', (int)$member['id']);
        }
    }

    function chat_customer_group_conversation_rows(Mysql_ks $db, int $customerId): array
    {
        chat_ensure_group_chat_runtime($db);
        if ($customerId <= 0 || !schema_object_exists($db, 'support_conversation_members') || !schema_object_exists($db, 'support_messages')) {
            return [];
        }

        $participantKey = $db->escape(chat_participant_key_for_customer($customerId));
        return $db->select_full_user(
            "SELECT
                support_conversations.id,
                support_conversations.group_name,
                support_conversations.subject,
                support_conversations.is_group_read_only,
                support_conversations.group_created_by_customer_id,
                support_conversations.group_created_by_admin_user_id,
                support_conversations.updated_at,
                support_conversations.created_at,
                support_conversation_members.last_read_message_id,
                (
                    SELECT support_messages.message_body
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.id DESC
                    LIMIT 1
                ) AS last_message_body,
                (
                    SELECT support_messages.attachment_path
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.id DESC
                    LIMIT 1
                ) AS last_attachment_path,
                (
                    SELECT COUNT(*)
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                      AND support_messages.id > COALESCE(support_conversation_members.last_read_message_id, 0)
                      AND NOT (
                          support_messages.sender_type = 'customer'
                          AND support_messages.customer_id = {$customerId}
                      )
                ) AS unread_count
             FROM support_conversations
             INNER JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
             WHERE support_conversations.conversation_type = 'group_chat'
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'accepted'
             ORDER BY COALESCE(support_conversations.updated_at, support_conversations.created_at) DESC, support_conversations.id DESC"
        );
    }

    function chat_admin_group_conversation_rows(Mysql_ks $db, int $adminUserId): array
    {
        chat_ensure_group_chat_runtime($db);
        if ($adminUserId <= 0 || !schema_object_exists($db, 'support_conversation_members') || !schema_object_exists($db, 'support_messages')) {
            return [];
        }

        $participantKey = $db->escape(chat_participant_key_for_admin($adminUserId));
        return $db->select_full_user(
            "SELECT
                support_conversations.id,
                support_conversations.customer_id,
                support_conversations.group_name,
                support_conversations.subject,
                support_conversations.status,
                support_conversations.is_group_read_only,
                support_conversations.updated_at,
                support_conversations.created_at,
                support_conversation_members.last_read_message_id,
                (
                    SELECT support_messages.message_body
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.id DESC
                    LIMIT 1
                ) AS last_message_body,
                (
                    SELECT support_messages.attachment_path
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.id DESC
                    LIMIT 1
                ) AS last_attachment_path,
                (
                    SELECT COUNT(*)
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                      AND support_messages.id > COALESCE(support_conversation_members.last_read_message_id, 0)
                      AND NOT (
                          support_messages.sender_type = 'admin'
                          AND support_messages.admin_user_id = {$adminUserId}
                      )
                ) AS unread_count
             FROM support_conversations
             INNER JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
             WHERE support_conversations.conversation_type = 'group_chat'
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'accepted'
             ORDER BY COALESCE(support_conversations.updated_at, support_conversations.created_at) DESC, support_conversations.id DESC"
        );
    }

    function chat_group_messages_query(Mysql_ks $db, int $conversationId): array
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return [];
        }

        return $db->select_full_user(
            "SELECT
                support_messages.id,
                support_messages.sender_type,
                support_messages.customer_id,
                support_messages.admin_user_id,
                support_messages.message_body AS tresc,
                support_messages.attachment_path,
                DATE_FORMAT(support_messages.created_at, '%Y-%m-%d %H:%i:%s') AS data,
                support_messages.is_read AS status,
                NULLIF(TRIM(admin_users.public_handle), '') AS admin_public_handle,
                admin_users.login_name AS admin_login_name,
                customers.email AS customer_email
             FROM support_messages
             LEFT JOIN admin_users ON admin_users.id = support_messages.admin_user_id
             LEFT JOIN customers ON customers.id = support_messages.customer_id
             WHERE support_messages.conversation_id = {$conversationId}
             ORDER BY support_messages.id ASC"
        );
    }

    function chat_customer_conversation_list(Mysql_ks $db, array $customer, array $reseller = [], string $defaultSupportLabel = 'Support'): array
    {
        chat_ensure_group_chat_runtime($db);
        $customerId = (int)($customer['id'] ?? 0);
        if ($customerId <= 0) {
            return [];
        }

        $entries = [];
        $supportConversationId = 0;
        if (schema_object_exists($db, 'support_conversations')) {
            $supportRow = $db->select_user(
                "SELECT id, updated_at, created_at
                 FROM support_conversations
                 WHERE conversation_type = 'live_chat'
                   AND customer_id = {$customerId}
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $supportConversationId = (int)($supportRow['id'] ?? 0);
        }

        $supportUnread = 0;
        $supportPreview = '';
        $supportUpdatedAt = '';
        if ($supportConversationId > 0 && schema_object_exists($db, 'support_messages')) {
            $supportMeta = $db->select_user(
                "SELECT
                    (
                        SELECT support_messages.message_body
                        FROM support_messages
                        WHERE support_messages.conversation_id = {$supportConversationId}
                        ORDER BY support_messages.id DESC
                        LIMIT 1
                    ) AS last_message_body,
                    (
                        SELECT COUNT(*)
                        FROM support_messages
                        WHERE support_messages.conversation_id = {$supportConversationId}
                          AND support_messages.sender_type = 'admin'
                          AND support_messages.is_read = 0
                    ) AS unread_count,
                    (
                        SELECT support_messages.created_at
                        FROM support_messages
                        WHERE support_messages.conversation_id = {$supportConversationId}
                        ORDER BY support_messages.id DESC
                        LIMIT 1
                    ) AS updated_at"
            );
            $supportPreview = trim((string)($supportMeta['last_message_body'] ?? ''));
            $supportUnread = (int)($supportMeta['unread_count'] ?? 0);
            $supportUpdatedAt = (string)($supportMeta['updated_at'] ?? '');
        }

        $entries[] = [
            'id' => $supportConversationId,
            'type' => 'live_chat',
            'title' => trim($defaultSupportLabel) !== '' ? trim($defaultSupportLabel) : 'Support',
            'subtitle' => 'Support',
            'unread_count' => $supportUnread,
            'preview' => $supportPreview,
            'updated_at' => $supportUpdatedAt,
            'is_group' => false,
            'is_read_only' => false,
            'can_leave' => false,
        ];

        if (chat_customer_can_use_groups($customer)) {
            foreach (chat_customer_group_conversation_rows($db, $customerId) as $row) {
                $entries[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'type' => 'group_chat',
                    'title' => chat_group_conversation_title($row),
                    'subtitle' => 'Group',
                    'unread_count' => (int)($row['unread_count'] ?? 0),
                    'preview' => trim((string)($row['last_message_body'] ?? '')),
                    'updated_at' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
                    'is_group' => true,
                    'is_read_only' => !empty($row['is_group_read_only']),
                    'can_leave' => true,
                    'is_owned' => (int)($row['group_created_by_customer_id'] ?? 0) === $customerId,
                ];
            }
        }

        usort($entries, static function (array $left, array $right): int {
            if (($left['type'] ?? '') === 'live_chat' && ($right['type'] ?? '') !== 'live_chat') {
                return -1;
            }
            if (($right['type'] ?? '') === 'live_chat' && ($left['type'] ?? '') !== 'live_chat') {
                return 1;
            }

            $leftTime = strtotime((string)($left['updated_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string)($right['updated_at'] ?? '')) ?: 0;
            if ($leftTime === $rightTime) {
                return (int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0);
            }

            return $rightTime <=> $leftTime;
        });

        return $entries;
    }

    function chat_customer_selected_conversation(Mysql_ks $db, array $customer, int $requestedConversationId): array
    {
        chat_ensure_group_chat_runtime($db);
        $customerId = (int)($customer['id'] ?? 0);
        $conversationId = max(0, $requestedConversationId);
        if ($customerId <= 0) {
            return ['id' => 0, 'type' => 'live_chat', 'is_group' => false];
        }

        if ($conversationId > 0) {
            $groupConversation = chat_group_accessible_for_customer($db, $customerId, $conversationId);
            if ($groupConversation) {
                return [
                    'id' => (int)$groupConversation['id'],
                    'type' => 'group_chat',
                    'is_group' => true,
                    'row' => $groupConversation,
                ];
            }

            $directConversation = $db->select_user(
                "SELECT id, subject, updated_at, created_at
                 FROM support_conversations
                 WHERE id = {$conversationId}
                   AND conversation_type = 'live_chat'
                   AND customer_id = {$customerId}
                 LIMIT 1"
            );
            if (is_array($directConversation) && !empty($directConversation['id'])) {
                return [
                    'id' => (int)$directConversation['id'],
                    'type' => 'live_chat',
                    'is_group' => false,
                    'row' => $directConversation,
                ];
            }
        }

        $directConversation = $db->select_user(
            "SELECT id, subject, updated_at, created_at
             FROM support_conversations
             WHERE conversation_type = 'live_chat'
               AND customer_id = {$customerId}
             ORDER BY id ASC
             LIMIT 1"
        );

        return [
            'id' => (int)($directConversation['id'] ?? 0),
            'type' => 'live_chat',
            'is_group' => false,
            'row' => is_array($directConversation) ? $directConversation : [],
        ];
    }

    function chat_messages_for_customer_conversation(
        Mysql_ks $db,
        array $customer,
        array $conversationState,
        array $reseller = [],
        string $defaultSupportLabel = 'Support'
    ): array {
        chat_ensure_group_chat_runtime($db);
        $customerId = (int)($customer['id'] ?? 0);
        if ($customerId <= 0) {
            return [];
        }

        if (($conversationState['type'] ?? '') === 'group_chat' && !empty($conversationState['id'])) {
            return chat_group_messages_query($db, (int)$conversationState['id']);
        }

        $conversationId = (int)($conversationState['id'] ?? 0);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return [];
        }

        return $db->select_full_user(
            "SELECT
                support_messages.id,
                support_conversations.customer_id AS user1,
                COALESCE(support_messages.admin_user_id, support_conversations.assigned_admin_id, 1) AS user2,
                support_messages.sender_type,
                support_messages.customer_id,
                support_messages.admin_user_id,
                support_messages.message_body AS tresc,
                support_messages.attachment_path,
                DATE_FORMAT(support_messages.created_at, '%Y-%m-%d %H:%i:%s') AS data,
                support_messages.is_read AS status,
                NULLIF(TRIM(admin_users.public_handle), '') AS admin_public_handle,
                admin_users.login_name AS admin_login_name
             FROM support_messages
             INNER JOIN support_conversations
                ON support_conversations.id = support_messages.conversation_id
             LEFT JOIN admin_users
                ON admin_users.id = COALESCE(support_messages.admin_user_id, support_conversations.assigned_admin_id)
             WHERE support_conversations.id = {$conversationId}
               AND support_conversations.customer_id = {$customerId}
               AND support_conversations.conversation_type = 'live_chat'
             ORDER BY support_messages.id ASC"
        );
    }
}
