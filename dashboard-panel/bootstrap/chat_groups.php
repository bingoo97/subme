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
            'is_direct_conversation' => "ALTER TABLE support_conversations ADD COLUMN is_direct_conversation TINYINT(1) NOT NULL DEFAULT 0 AFTER group_created_by_admin_user_id",
            'message_retention_hours' => "ALTER TABLE support_conversations ADD COLUMN message_retention_hours SMALLINT UNSIGNED DEFAULT NULL AFTER group_created_by_admin_user_id",
            'message_retention_minutes' => "ALTER TABLE support_conversations ADD COLUMN message_retention_minutes SMALLINT UNSIGNED DEFAULT NULL AFTER message_retention_hours",
            'group_avatar_url' => "ALTER TABLE support_conversations ADD COLUMN group_avatar_url VARCHAR(255) DEFAULT NULL AFTER group_name",
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

        if (schema_object_exists($db, 'support_conversation_members') && !schema_column_exists($db, 'support_conversation_members', 'email_notifications_enabled')) {
            @$db->query(
                "ALTER TABLE support_conversation_members
                 ADD COLUMN email_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1
                 AFTER can_post"
            );
            schema_forget_column_cache('support_conversation_members', 'email_notifications_enabled');
        }

        if (schema_object_exists($db, 'support_conversation_members') && !schema_column_exists($db, 'support_conversation_members', 'global_chat_blocked')) {
            @$db->query(
                "ALTER TABLE support_conversation_members
                 ADD COLUMN global_chat_blocked TINYINT(1) NOT NULL DEFAULT 0
                 AFTER email_notifications_enabled"
            );
            schema_forget_column_cache('support_conversation_members', 'global_chat_blocked');
        }
    }

    function chat_current_datetime(): string
    {
        return function_exists('app_current_datetime_string')
            ? app_current_datetime_string()
            : date('Y-m-d H:i:s');
    }

    function chat_group_retention_allowed_hours(): array
    {
        return [5, 15, 30, 60, 720, 1440];
    }

    function chat_group_normalize_retention_hours($value): ?int
    {
        if ($value === null) {
            return 60;
        }

        $normalized = trim((string)$value);
        if ($normalized === '' || $normalized === '0' || strtolower($normalized) === 'off' || strtolower($normalized) === 'none') {
            return 60;
        }

        $normalizedLower = strtolower($normalized);
        if (substr($normalizedLower, -1) === 'm') {
            $minutes = (int)substr($normalizedLower, 0, -1);
            if ($minutes === 1) {
                $minutes = 5;
            }
            return in_array($minutes, chat_group_retention_allowed_hours(), true) ? $minutes : null;
        }

        if (substr($normalizedLower, -1) === 'h') {
            $hoursValue = (int)substr($normalizedLower, 0, -1);
            $minutes = $hoursValue * 60;
            return in_array($minutes, chat_group_retention_allowed_hours(), true) ? $minutes : null;
        }

        $numeric = (int)$normalizedLower;
        if (in_array($numeric, [1, 6, 12, 24], true)) {
            $legacyMinutes = $numeric * 60;
            return in_array($legacyMinutes, chat_group_retention_allowed_hours(), true) ? $legacyMinutes : null;
        }

        return in_array($numeric, chat_group_retention_allowed_hours(), true) ? $numeric : null;
    }

    function chat_group_retention_minutes_from_row(array $conversation): int
    {
        $rawMinutes = isset($conversation['message_retention_minutes']) ? (int)$conversation['message_retention_minutes'] : 0;
        if ($rawMinutes > 0) {
            if ($rawMinutes === 1) {
                return 5;
            }

            $minutes = chat_group_normalize_retention_hours($rawMinutes);
            if ($minutes !== null) {
                return $minutes;
            }
        }

        $legacy = trim((string)($conversation['message_retention_hours'] ?? ''));
        if ($legacy !== '') {
            $legacyMinutes = chat_group_normalize_retention_hours($legacy . 'h');
            if ($legacyMinutes !== null) {
                return $legacyMinutes;
            }
        }

        return 60;
    }

    function chat_group_retention_storage_hours(?int $minutes): ?int
    {
        $minutes = chat_group_normalize_retention_hours($minutes);
        if ($minutes === null) {
            return null;
        }

        if ($minutes === 60) {
            return 1;
        }
        if ($minutes === 720) {
            return 12;
        }
        if ($minutes === 1440) {
            return 24;
        }

        return null;
    }

    function chat_group_retention_label(?int $minutes): string
    {
        $minutes = chat_group_normalize_retention_hours($minutes);
        if ($minutes === null) {
            $minutes = 60;
        }

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        if ($minutes % 60 === 0) {
            return (int)($minutes / 60) . 'h';
        }

        return $minutes . ' min';
    }

    function chat_group_retention_input_value(?int $minutes): string
    {
        $minutes = chat_group_normalize_retention_hours($minutes);
        if ($minutes === null) {
            $minutes = 60;
        }

        if ($minutes < 60) {
            return (string)$minutes . 'm';
        }

        return (string)((int)($minutes / 60)) . 'h';
    }

    function chat_group_member_email_notifications_enabled(array $member): bool
    {
        if (!array_key_exists('email_notifications_enabled', $member)) {
            return true;
        }

        return (int)($member['email_notifications_enabled'] ?? 1) !== 0;
    }

    function chat_customer_is_reseller(array $customer): bool
    {
        return function_exists('app_normalize_customer_type')
            ? app_normalize_customer_type((string)($customer['customer_type'] ?? '')) === 'reseller'
            : false;
    }

    function chat_demo_showcase_enabled(array $settings = []): bool
    {
        return function_exists('app_demo_messenger_showcase_enabled')
            ? app_demo_messenger_showcase_enabled($settings)
            : false;
    }

    function chat_customer_messenger_enabled(array $customer, array $settings = []): bool
    {
        if (chat_customer_is_reseller($customer)) {
            return true;
        }

        return function_exists('app_support_chat_effective_enabled')
            && app_support_chat_effective_enabled($settings)
            && (!empty($settings['customer_messenger_enabled']) || chat_demo_showcase_enabled($settings));
    }

    function chat_customer_can_use_groups(array $customer, array $settings = []): bool
    {
        return chat_customer_messenger_enabled($customer, $settings);
    }

    function chat_customer_can_start_direct_conversations(array $customer, array $settings = []): bool
    {
        if (chat_customer_is_reseller($customer)) {
            return true;
        }

        return chat_customer_messenger_enabled($customer, $settings)
            && (!empty($settings['customer_direct_chat_enabled']) || chat_demo_showcase_enabled($settings));
    }

    function chat_customer_can_create_named_groups(array $customer, array $settings = []): bool
    {
        if (chat_customer_is_reseller($customer)) {
            return chat_reseller_group_chat_limit($settings) > 0;
        }

        return chat_customer_messenger_enabled($customer, $settings)
            && (!empty($settings['customer_group_chat_enabled']) || chat_demo_showcase_enabled($settings));
    }

    function chat_customer_global_group_enabled(array $customer, array $settings = []): bool
    {
        return chat_customer_messenger_enabled($customer, $settings)
            && (!empty($settings['customer_global_group_enabled']) || chat_demo_showcase_enabled($settings));
    }

    function chat_customer_can_edit_messenger_identity(array $customer, array $settings = []): bool
    {
        return chat_customer_messenger_enabled($customer, $settings);
    }

    function chat_is_group_like_conversation_type(string $conversationType): bool
    {
        $conversationType = trim($conversationType);
        return $conversationType === 'group_chat' || $conversationType === 'global_group';
    }

    function chat_is_global_group_conversation_type(string $conversationType): bool
    {
        return trim($conversationType) === 'global_group';
    }

    function chat_global_group_title(): string
    {
        return 'Global Chat';
    }

    function chat_global_group_system_subject(): string
    {
        return 'System global group';
    }

    function chat_global_group_retention_hours(): int
    {
        return 1440;
    }

    function chat_demo_showcase_main_email(): string
    {
        return 'demo@demo.demo';
    }

    function chat_demo_showcase_customer_specs(): array
    {
        return [
            ['email' => 'demo@demo.demo', 'handle' => 'demo', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'alex.morgan@demo.demo', 'handle' => 'alexmorgan', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'sophie.carter@demo.demo', 'handle' => 'sophiecarter', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'liam.parker@demo.demo', 'handle' => 'liamparker', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'emma.bennett@demo.demo', 'handle' => 'emmabennett', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'noah.walker@demo.demo', 'handle' => 'noahwalker', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'mia.hughes@demo.demo', 'handle' => 'miahughes', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'oliver.ross@demo.demo', 'handle' => 'oliverross', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'ava.collins@demo.demo', 'handle' => 'avacollins', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'ethan.bailey@demo.demo', 'handle' => 'ethanbailey', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'grace.ward@demo.demo', 'handle' => 'graceward', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'lucas.brooks@demo.demo', 'handle' => 'lucasbrooks', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'chloe.reed@demo.demo', 'handle' => 'chloereed', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'jack.hayes@demo.demo', 'handle' => 'jackhayes', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'ella.foster@demo.demo', 'handle' => 'ellafoster', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'henry.price@demo.demo', 'handle' => 'henryprice', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'zoe.bryant@demo.demo', 'handle' => 'zoebryant', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'mason.cook@demo.demo', 'handle' => 'masoncook', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'lily.long@demo.demo', 'handle' => 'lilylong', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'owen.powell@demo.demo', 'handle' => 'owenpowell', 'password' => '1234', 'locale' => 'en'],
            ['email' => 'ruby.barnes@demo.demo', 'handle' => 'rubybarnes', 'password' => '1234', 'locale' => 'en'],
        ];
    }

    function chat_demo_showcase_group_specs(): array
    {
        return [
            [
                'title' => 'Streaming Crew',
                'retention' => 1440,
                'owner_email' => chat_demo_showcase_main_email(),
                'member_emails' => [
                    chat_demo_showcase_main_email(),
                    'alex.morgan@demo.demo',
                    'sophie.carter@demo.demo',
                    'liam.parker@demo.demo',
                    'emma.bennett@demo.demo',
                    'noah.walker@demo.demo',
                ],
            ],
        ];
    }

    function chat_demo_showcase_direct_spec(): array
    {
        return [
            'owner_email' => chat_demo_showcase_main_email(),
            'partner_email' => 'alex.morgan@demo.demo',
            'retention' => 1440,
        ];
    }

    function chat_demo_showcase_message_pool(): array
    {
        return [
            'Looks good here.',
            'Nice setup.',
            'All set.',
            'Good idea 👍',
            'Sounds good to me.',
            'I like this view.',
            'We can keep it simple.',
            'That works.',
            'Okay 🙂',
            'Thanks, noted.',
            'This looks clean.',
            'Quick check, all good.',
            'Sure, let us keep going.',
            'Small update: still good here.',
            'Great choice.',
            'Looks smooth 👌',
            'I am here.',
            'Nice one.',
            'Two words: very clean.',
            'Works for me.',
        ];
    }

    function chat_demo_showcase_pick_message(): string
    {
        $messages = chat_demo_showcase_message_pool();
        if (!$messages) {
            return 'Looks good here.';
        }

        return (string)$messages[array_rand($messages)];
    }

    function chat_demo_showcase_disable_member_email_notifications(Mysql_ks $db, int $conversationId, int $customerId): void
    {
        $member = chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($customerId));
        if (!$member || !array_key_exists('email_notifications_enabled', $member)) {
            return;
        }

        if ((int)($member['email_notifications_enabled'] ?? 1) !== 0) {
            $db->update_using_id(['email_notifications_enabled'], [0], 'support_conversation_members', (int)$member['id']);
        }
    }

    function chat_demo_showcase_ensure_customer(Mysql_ks $db, array $spec): array
    {
        $email = strtolower(trim((string)($spec['email'] ?? '')));
        $handleInput = trim((string)($spec['handle'] ?? ''));
        $localeCode = trim((string)($spec['locale'] ?? 'en'));
        $password = (string)($spec['password'] ?? '1234');
        if ($email === '') {
            return ['row' => null, 'changed' => false];
        }

        $row = function_exists('app_find_customer_by_email') ? app_find_customer_by_email($db, $email) : null;
        $created = false;
        if (!is_array($row) || empty($row['id'])) {
            $customerId = function_exists('app_insert_customer_registration')
                ? app_insert_customer_registration($db, $email, $password, $localeCode !== '' ? $localeCode : 'en', chat_current_datetime(), '127.0.0.1', 1, 'client')
                : 0;
            if ($customerId > 0) {
                $row = function_exists('app_find_customer_by_id') ? app_find_customer_by_id($db, $customerId) : null;
                $created = true;
            }
        }

        if (!is_array($row) || empty($row['id'])) {
            return ['row' => null, 'changed' => false];
        }

        $customerId = (int)($row['id'] ?? 0);
        $handleResult = function_exists('app_resolve_customer_public_handle')
            ? app_resolve_customer_public_handle($db, $handleInput, $email, $customerId)
            : ['ok' => true, 'handle' => $handleInput];
        $resolvedHandle = !empty($handleResult['ok']) ? trim((string)($handleResult['handle'] ?? '')) : trim((string)($row['public_handle'] ?? ''));
        if ($resolvedHandle === '' && function_exists('app_generate_customer_public_handle')) {
            $resolvedHandle = app_generate_customer_public_handle($db, $email, $customerId);
        }

        $status = 'active';
        $customerType = 'client';
        $emailVerifiedAt = trim((string)($row['email_verified_at'] ?? ''));
        if ($emailVerifiedAt === '') {
            $emailVerifiedAt = chat_current_datetime();
        }

        $fields = ['public_handle', 'status', 'customer_type', 'locale_code', 'email_verified_at'];
        $values = [$resolvedHandle, $status, $customerType, $localeCode !== '' ? $localeCode : 'en', $emailVerifiedAt];
        $db->update_using_id($fields, $values, 'customers', $customerId);

        if ($created && function_exists('app_store_customer_password')) {
            app_store_customer_password($db, $customerId, $password);
        }

        $freshRow = function_exists('app_find_customer_by_id') ? app_find_customer_by_id($db, $customerId) : $row;
        return ['row' => $freshRow, 'changed' => $created];
    }

    function chat_demo_showcase_find_named_group_conversation(Mysql_ks $db, string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $safeTitle = $db->escape($title);
        $row = $db->select_user(
            "SELECT *
             FROM support_conversations
             WHERE conversation_type = 'group_chat'
               AND is_direct_conversation = 0
               AND (
                    group_name = '{$safeTitle}'
                    OR subject = '{$safeTitle}'
               )
             ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_demo_showcase_create_group_shell(
        Mysql_ks $db,
        int $ownerCustomerId,
        string $title,
        int $retentionMinutes,
        bool $isDirectConversation = false
    ): ?array {
        if ($ownerCustomerId <= 0 || $title === '' || !schema_object_exists($db, 'support_conversations')) {
            return null;
        }

        $currentTime = chat_current_datetime();
        $inserted = $db->insert(
            ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'group_name', 'is_group_read_only', 'group_created_by_customer_id', 'group_created_by_admin_user_id', 'is_direct_conversation', 'message_retention_hours', 'message_retention_minutes', 'status', 'priority', 'created_at', 'updated_at'],
            ['group_chat', $ownerCustomerId, null, $title, $title, 0, $ownerCustomerId, null, $isDirectConversation ? 1 : 0, chat_group_retention_storage_hours($retentionMinutes), $retentionMinutes, 'open', 'normal', $currentTime, $currentTime],
            'support_conversations'
        );
        if (!$inserted) {
            return null;
        }

        $conversationId = (int)$db->id();
        return chat_group_conversation_row($db, $conversationId);
    }

    function chat_demo_showcase_sync_customer_members(
        Mysql_ks $db,
        int $conversationId,
        int $ownerCustomerId,
        array $memberCustomerIds,
        bool $isDirectConversation = false
    ): void {
        if ($conversationId <= 0 || $ownerCustomerId <= 0 || !$memberCustomerIds) {
            return;
        }

        $desired = [];
        foreach ($memberCustomerIds as $memberCustomerId) {
            $memberCustomerId = (int)$memberCustomerId;
            if ($memberCustomerId <= 0) {
                continue;
            }
            $desired[chat_participant_key_for_customer($memberCustomerId)] = $memberCustomerId;
            chat_add_group_member(
                $db,
                $conversationId,
                [
                    'participant_key' => chat_participant_key_for_customer($memberCustomerId),
                    'participant_type' => 'customer',
                    'customer_id' => $memberCustomerId,
                    'admin_user_id' => 0,
                ],
                'accepted',
                $memberCustomerId === $ownerCustomerId ? 'owner' : 'member',
                $ownerCustomerId,
                0
            );
            chat_demo_showcase_disable_member_email_notifications($db, $conversationId, $memberCustomerId);
        }

        $rows = $db->select_full_user(
            "SELECT id, participant_key, customer_id
             FROM support_conversation_members
             WHERE conversation_id = {$conversationId}
               AND participant_type = 'customer'"
        );
        foreach ($rows as $row) {
            $participantKey = trim((string)($row['participant_key'] ?? ''));
            if ($participantKey === '' || isset($desired[$participantKey])) {
                continue;
            }
            $db->update_using_id(
                ['invite_status', 'can_post', 'left_at'],
                ['left', 0, chat_current_datetime()],
                'support_conversation_members',
                (int)($row['id'] ?? 0)
            );
        }

        $db->update_using_id(
            ['customer_id', 'group_created_by_customer_id', 'is_direct_conversation', 'status'],
            [$ownerCustomerId, $ownerCustomerId, $isDirectConversation ? 1 : 0, 'open'],
            'support_conversations',
            $conversationId
        );
    }

    function chat_demo_showcase_ensure_named_group(Mysql_ks $db, array $spec, array $customersByEmail): array
    {
        $ownerEmail = strtolower(trim((string)($spec['owner_email'] ?? '')));
        $title = trim((string)($spec['title'] ?? ''));
        $retentionMinutes = chat_group_normalize_retention_hours($spec['retention'] ?? 1440) ?? 1440;
        $owner = $ownerEmail !== '' ? ($customersByEmail[$ownerEmail] ?? null) : null;
        if (!is_array($owner) || empty($owner['id']) || $title === '') {
            return ['row' => null, 'changed' => false];
        }

        $conversation = chat_demo_showcase_find_named_group_conversation($db, $title);
        $created = false;
        if (!$conversation) {
            $conversation = chat_demo_showcase_create_group_shell($db, (int)$owner['id'], $title, $retentionMinutes, false);
            $created = true;
        }
        if (!is_array($conversation) || empty($conversation['id'])) {
            return ['row' => null, 'changed' => false];
        }

        $conversationId = (int)($conversation['id'] ?? 0);
        $memberCustomerIds = [];
        foreach ((array)($spec['member_emails'] ?? []) as $memberEmail) {
            $memberEmail = strtolower(trim((string)$memberEmail));
            if ($memberEmail === '' || !isset($customersByEmail[$memberEmail]['id'])) {
                continue;
            }
            $memberCustomerIds[] = (int)$customersByEmail[$memberEmail]['id'];
        }
        $memberCustomerIds = array_values(array_unique(array_filter($memberCustomerIds)));
        if (!in_array((int)$owner['id'], $memberCustomerIds, true)) {
            array_unshift($memberCustomerIds, (int)$owner['id']);
        }

        $db->update_using_id(
            ['subject', 'group_name', 'message_retention_hours', 'message_retention_minutes', 'status'],
            [$title, $title, chat_group_retention_storage_hours($retentionMinutes), $retentionMinutes, 'open'],
            'support_conversations',
            $conversationId
        );

        chat_demo_showcase_sync_customer_members($db, $conversationId, (int)$owner['id'], $memberCustomerIds, false);
        return ['row' => chat_group_conversation_row($db, $conversationId), 'changed' => $created];
    }

    function chat_demo_showcase_ensure_direct_conversation(Mysql_ks $db, array $spec, array $customersByEmail): array
    {
        $ownerEmail = strtolower(trim((string)($spec['owner_email'] ?? '')));
        $partnerEmail = strtolower(trim((string)($spec['partner_email'] ?? '')));
        $retentionMinutes = chat_group_normalize_retention_hours($spec['retention'] ?? 1440) ?? 1440;
        $owner = $ownerEmail !== '' ? ($customersByEmail[$ownerEmail] ?? null) : null;
        $partner = $partnerEmail !== '' ? ($customersByEmail[$partnerEmail] ?? null) : null;
        if (!is_array($owner) || empty($owner['id']) || !is_array($partner) || empty($partner['id'])) {
            return ['row' => null, 'changed' => false];
        }

        $conversation = chat_find_customer_direct_conversation_between($db, (int)$owner['id'], (int)$partner['id']);
        $created = false;
        if (!$conversation) {
            $conversation = chat_demo_showcase_create_group_shell($db, (int)$owner['id'], 'Direct Chat', $retentionMinutes, true);
            $created = true;
        }
        if (!is_array($conversation) || empty($conversation['id'])) {
            return ['row' => null, 'changed' => false];
        }

        $conversationId = (int)($conversation['id'] ?? 0);
        $db->update_using_id(
            ['subject', 'group_name', 'message_retention_hours', 'message_retention_minutes', 'is_direct_conversation', 'status'],
            ['Direct Chat', 'Direct Chat', chat_group_retention_storage_hours($retentionMinutes), $retentionMinutes, 1, 'open'],
            'support_conversations',
            $conversationId
        );

        chat_demo_showcase_sync_customer_members($db, $conversationId, (int)$owner['id'], [(int)$owner['id'], (int)$partner['id']], true);
        return ['row' => chat_group_conversation_row($db, $conversationId), 'changed' => $created];
    }

    function chat_demo_showcase_insert_customer_message(Mysql_ks $db, int $conversationId, int $customerId, string $messageBody): bool
    {
        $messageBody = trim($messageBody);
        if ($conversationId <= 0 || $customerId <= 0 || $messageBody === '') {
            return false;
        }

        $conversation = chat_group_accessible_for_customer($db, $customerId, $conversationId);
        if (
            !$conversation
            || !empty($conversation['is_group_read_only'])
            || (int)($conversation['can_post'] ?? 1) === 0
            || !chat_group_member_can_post($db, $conversationId, chat_participant_key_for_customer($customerId))
        ) {
            return false;
        }

        $currentTime = chat_current_datetime();
        $inserted = $db->insert(
            ['conversation_id', 'sender_type', 'customer_id', 'message_body', 'attachment_path', 'reply_to_message_id', 'is_read', 'created_at'],
            [$conversationId, 'customer', $customerId, $messageBody, null, null, 0, $currentTime],
            'support_messages'
        );
        if (!$inserted) {
            return false;
        }

        $messageId = (int)$db->id();
        $db->update_using_id(
            ['updated_at', 'last_customer_message_at', 'status'],
            [$currentTime, $currentTime, 'open'],
            'support_conversations',
            $conversationId
        );
        $member = chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($customerId));
        if ($member) {
            $db->update_using_id(['last_read_message_id'], [$messageId], 'support_conversation_members', (int)$member['id']);
        }

        return true;
    }

    function chat_demo_showcase_acquire_tick_lock(Mysql_ks $db, string $columnName, int $intervalSeconds, string $currentTime): bool
    {
        if (
            $intervalSeconds <= 0
            || !schema_object_exists($db, 'app_settings')
            || !preg_match('/^[a-z0-9_]+$/i', $columnName)
            || !schema_column_exists($db, 'app_settings', $columnName)
        ) {
            return false;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime($currentTime) - $intervalSeconds);
        $safeNow = $db->escape($currentTime);
        $safeCutoff = $db->escape($cutoff);
        $result = $db->query(
            "UPDATE app_settings
             SET {$columnName} = '{$safeNow}'
             WHERE id = 1
               AND (
                    {$columnName} IS NULL
                    OR {$columnName} <= '{$safeCutoff}'
               )"
        );

        return (bool)$result && (int)$db->affected_rows > 0;
    }

    function chat_demo_showcase_member_ids_from_emails(array $memberEmails, array $customersByEmail): array
    {
        $memberIds = [];
        foreach ($memberEmails as $memberEmail) {
            $memberEmail = strtolower(trim((string)$memberEmail));
            if ($memberEmail === '' || !isset($customersByEmail[$memberEmail]['id'])) {
                continue;
            }
            $memberIds[] = (int)$customersByEmail[$memberEmail]['id'];
        }

        return array_values(array_unique(array_filter($memberIds)));
    }

    function chat_demo_showcase_emit_single_message(Mysql_ks $db, int $conversationId, array $memberEmails, array $customersByEmail): bool
    {
        $memberIds = chat_demo_showcase_member_ids_from_emails($memberEmails, $customersByEmail);
        if (!$memberIds) {
            return false;
        }

        $speakerId = (int)$memberIds[array_rand($memberIds)];
        return chat_demo_showcase_insert_customer_message($db, $conversationId, $speakerId, chat_demo_showcase_pick_message());
    }

    function chat_demo_showcase_prune_obsolete_named_groups(Mysql_ks $db, int $ownerCustomerId, array $allowedTitles): int
    {
        if ($ownerCustomerId <= 0 || !schema_object_exists($db, 'support_conversations')) {
            return 0;
        }

        $safeTitles = [];
        foreach ($allowedTitles as $title) {
            $title = trim((string)$title);
            if ($title === '') {
                continue;
            }
            $safeTitles[] = "'" . $db->escape($title) . "'";
        }
        $titleCondition = $safeTitles ? 'AND group_name NOT IN (' . implode(', ', $safeTitles) . ')' : '';

        $rows = $db->select_full_user(
            "SELECT id
             FROM support_conversations
             WHERE conversation_type = 'group_chat'
               AND is_direct_conversation = 0
               AND group_created_by_customer_id = {$ownerCustomerId}
               {$titleCondition}"
        );

        $deleted = 0;
        foreach ($rows as $row) {
            $conversationId = (int)($row['id'] ?? 0);
            if ($conversationId <= 0) {
                continue;
            }

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
            $deleted++;
        }

        return $deleted;
    }

    function chat_demo_showcase_sync(Mysql_ks $db, array $settings = [], array $options = []): array
    {
        if (!chat_demo_showcase_enabled($settings)) {
            return ['ok' => true, 'skipped' => true, 'ensured_users' => 0, 'ensured_conversations' => 0, 'generated_messages' => 0, 'message' => 'Demo messenger showcase is disabled.'];
        }

        if (!schema_object_exists($db, 'customers') || !schema_object_exists($db, 'support_conversations')) {
            return ['ok' => false, 'ensured_users' => 0, 'ensured_conversations' => 0, 'generated_messages' => 0, 'message' => 'Required messenger tables are missing.'];
        }

        chat_ensure_group_chat_runtime($db);
        if (function_exists('app_ensure_customer_runtime_columns')) {
            app_ensure_customer_runtime_columns($db);
        }

        $ensuredUsers = 0;
        $ensuredConversations = 0;
        $customersByEmail = [];
        foreach (chat_demo_showcase_customer_specs() as $spec) {
            $ensureResult = chat_demo_showcase_ensure_customer($db, $spec);
            if (!empty($ensureResult['changed'])) {
                $ensuredUsers++;
            }
            if (is_array($ensureResult['row']) && !empty($ensureResult['row']['email'])) {
                $customersByEmail[strtolower((string)$ensureResult['row']['email'])] = $ensureResult['row'];
            }
        }

        $mainCustomer = $customersByEmail[strtolower(chat_demo_showcase_main_email())] ?? null;
        if (is_array($mainCustomer) && !empty($mainCustomer['id'])) {
            $allowedTitles = array_map(static function (array $spec): string {
                return trim((string)($spec['title'] ?? ''));
            }, chat_demo_showcase_group_specs());
            $ensuredConversations += chat_demo_showcase_prune_obsolete_named_groups($db, (int)$mainCustomer['id'], $allowedTitles);
        }

        $globalConversation = chat_sync_global_group_members($db, $settings);
        if (is_array($globalConversation) && !empty($globalConversation['id'])) {
            $ensuredConversations++;
        }

        $groupConversations = [];
        foreach (chat_demo_showcase_group_specs() as $groupSpec) {
            $groupResult = chat_demo_showcase_ensure_named_group($db, $groupSpec, $customersByEmail);
            if (!empty($groupResult['changed'])) {
                $ensuredConversations++;
            }
            if (is_array($groupResult['row']) && !empty($groupResult['row']['id'])) {
                $groupConversations[] = $groupResult['row'];
            }
        }

        $directResult = chat_demo_showcase_ensure_direct_conversation($db, chat_demo_showcase_direct_spec(), $customersByEmail);
        if (!empty($directResult['changed'])) {
            $ensuredConversations++;
        }
        $directConversation = is_array($directResult['row']) ? $directResult['row'] : null;

        $generatedGlobalMessages = 0;
        $generatedPrivateMessages = 0;
        $emitMessages = !array_key_exists('emit_messages', $options) || !empty($options['emit_messages']);
        if ($emitMessages) {
            $currentTime = chat_current_datetime();

            if (
                is_array($globalConversation)
                && !empty($globalConversation['id'])
                && chat_demo_showcase_acquire_tick_lock($db, 'demo_messenger_showcase_last_global_tick_at', 30, $currentTime)
            ) {
                if (chat_demo_showcase_emit_single_message($db, (int)$globalConversation['id'], array_keys($customersByEmail), $customersByEmail)) {
                    $generatedGlobalMessages++;
                }
            }

            if (chat_demo_showcase_acquire_tick_lock($db, 'demo_messenger_showcase_last_private_tick_at', 60, $currentTime)) {
                foreach (chat_demo_showcase_group_specs() as $index => $groupSpec) {
                    if (
                        isset($groupConversations[$index]['id'])
                        && chat_demo_showcase_emit_single_message(
                            $db,
                            (int)$groupConversations[$index]['id'],
                            (array)($groupSpec['member_emails'] ?? []),
                            $customersByEmail
                        )
                    ) {
                        $generatedPrivateMessages++;
                    }
                }

                if (is_array($directConversation) && !empty($directConversation['id'])) {
                    $directSpec = chat_demo_showcase_direct_spec();
                    if (chat_demo_showcase_emit_single_message(
                        $db,
                        (int)$directConversation['id'],
                        [$directSpec['owner_email'] ?? '', $directSpec['partner_email'] ?? ''],
                        $customersByEmail
                    )) {
                        $generatedPrivateMessages++;
                    }
                }
            }
        }

        return [
            'ok' => true,
            'skipped' => false,
            'ensured_users' => $ensuredUsers,
            'ensured_conversations' => $ensuredConversations,
            'generated_messages' => $generatedGlobalMessages + $generatedPrivateMessages,
            'generated_global_messages' => $generatedGlobalMessages,
            'generated_private_messages' => $generatedPrivateMessages,
            'message' => 'Demo messenger showcase synchronized.',
        ];
    }

    function chat_group_avatar_upload_directory(): string
    {
        return app_public_path('uploads/chat/groups');
    }

    function chat_group_avatar_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            return $value;
        }

        return '/' . ltrim($value, '/');
    }

    function chat_store_group_avatar_upload(array $file, int $customerId): array
    {
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $maxBytes = 5 * 1024 * 1024;

        if ($customerId <= 0 || $uploadError !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['ok' => false, 'code' => 'upload_error'];
        }

        $fileSize = @filesize($tmpPath);
        if (!is_int($fileSize) || $fileSize <= 0) {
            $fileSize = isset($file['size']) ? (int)$file['size'] : 0;
        }

        if ($fileSize <= 0 || $fileSize > $maxBytes) {
            return ['ok' => false, 'code' => 'too_large'];
        }

        $imageInfo = @getimagesize($tmpPath);
        $mimeType = strtolower(trim((string)($imageInfo['mime'] ?? '')));
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {
            return ['ok' => false, 'code' => 'invalid_type'];
        }

        $uploadDirectory = chat_group_avatar_upload_directory();
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            return ['ok' => false, 'code' => 'upload_error'];
        }

        $extension = $allowedMimeTypes[$mimeType];
        $fileName = 'group_avatar_' . $customerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destinationPath = $uploadDirectory . '/' . $fileName;
        $saved = false;

        if (
            function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagedestroy')
            && function_exists('app_customer_avatar_create_image_resource')
        ) {
            $sourceImage = app_customer_avatar_create_image_resource($tmpPath, $mimeType);
            if ($sourceImage) {
                $width = imagesx($sourceImage);
                $height = imagesy($sourceImage);

                if ($width > 0 && $height > 0) {
                    $maxDimension = 640;
                    $scale = min($maxDimension / $width, $maxDimension / $height, 1);
                    $targetWidth = max(1, (int)round($width * $scale));
                    $targetHeight = max(1, (int)round($height * $scale));
                    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

                    if ($targetImage) {
                        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                            imagealphablending($targetImage, false);
                            imagesavealpha($targetImage, true);
                            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
                            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                        } else {
                            $background = imagecolorallocate($targetImage, 255, 255, 255);
                            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $background);
                        }

                        imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                        if ($mimeType === 'image/png') {
                            $saved = function_exists('imagepng') ? imagepng($targetImage, $destinationPath, 6) : false;
                        } elseif ($mimeType === 'image/webp') {
                            $saved = function_exists('imagewebp') ? imagewebp($targetImage, $destinationPath, 82) : false;
                        } else {
                            $saved = function_exists('imagejpeg') ? imagejpeg($targetImage, $destinationPath, 82) : false;
                        }

                        imagedestroy($targetImage);
                    }
                }

                imagedestroy($sourceImage);
            }
        }

        if (!$saved) {
            $saved = move_uploaded_file($tmpPath, $destinationPath);
        }

        if (!$saved) {
            return ['ok' => false, 'code' => 'upload_error'];
        }

        return [
            'ok' => true,
            'url' => '/uploads/chat/groups/' . $fileName,
        ];
    }

    function chat_delete_group_avatar_file(string $publicPath): bool
    {
        $publicPath = trim($publicPath);
        if ($publicPath === '' || strpos($publicPath, '/uploads/chat/groups/') !== 0) {
            return false;
        }

        $publicRoot = realpath(app_public_path());
        if ($publicRoot === false) {
            return false;
        }

        $absolutePath = realpath($publicRoot . '/' . ltrim($publicPath, '/'));
        if ($absolutePath === false || strpos($absolutePath, $publicRoot) !== 0 || !is_file($absolutePath)) {
            return false;
        }

        return @unlink($absolutePath);
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
        $isEligibleRole = chat_customer_can_use_groups($customer, $settings);
        $customerId = (int)($customer['id'] ?? 0);
        $createdCount = ($isEligibleRole && $customerId > 0) ? chat_customer_group_created_count($db, $customerId) : 0;
        $canCreateDirect = $isEligibleRole && chat_customer_can_start_direct_conversations($customer, $settings);
        $canCreateGroup = $isEligibleRole && chat_customer_can_create_named_groups($customer, $settings);
        $usesLimit = chat_customer_is_reseller($customer);
        $groupSlotAvailable = !$usesLimit || ($limit > 0 && $createdCount < $limit);
        $allowed = $canCreateDirect || ($canCreateGroup && $groupSlotAvailable);

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'created_count' => $createdCount,
            'remaining_count' => max(0, $limit - $createdCount),
            'blocked_by_limit' => $usesLimit && $canCreateGroup && $limit <= 0,
            'reached_limit' => $usesLimit && $canCreateGroup && $limit > 0 && $createdCount >= $limit,
            'can_create_direct' => $canCreateDirect,
            'can_create_group' => $canCreateGroup && $groupSlotAvailable,
            'full_messenger_enabled' => $isEligibleRole,
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

    function chat_customer_display_label_from_row(array $row): string
    {
        if (function_exists('app_customer_display_label')) {
            $label = trim((string)app_customer_display_label($row));
            if ($label !== '') {
                return $label;
            }
        }

        $email = trim((string)($row['email'] ?? $row['customer_email'] ?? ''));
        if ($email !== '') {
            return chat_customer_email_short_label($email);
        }

        return 'User';
    }

    function chat_customer_avatar_payload_from_row(array $row): array
    {
        $avatarUrl = function_exists('app_customer_avatar_url')
            ? app_customer_avatar_url((string)($row['avatar_url'] ?? ''))
            : trim((string)($row['avatar_url'] ?? ''));

        $avatarText = function_exists('app_customer_avatar_initial')
            ? app_customer_avatar_initial($row)
            : strtoupper(function_exists('mb_substr') ? mb_substr(chat_customer_display_label_from_row($row), 0, 1) : substr(chat_customer_display_label_from_row($row), 0, 1));

        $avatarTheme = function_exists('app_customer_avatar_theme')
            ? app_customer_avatar_theme($row)
            : 'theme-1';

        return [
            'avatar_url' => $avatarUrl,
            'avatar_text' => $avatarText !== '' ? $avatarText : 'U',
            'avatar_theme' => $avatarTheme,
        ];
    }

    function chat_admin_avatar_payload_from_row(array $row): array
    {
        $label = chat_admin_display_label($row);
        $avatarText = strtoupper(function_exists('mb_substr') ? mb_substr($label, 0, 1) : substr($label, 0, 1));
        $avatarUrl = function_exists('app_admin_avatar_url')
            ? app_admin_avatar_url((string)($row['avatar_url'] ?? ''))
            : trim((string)($row['avatar_url'] ?? ''));

        return [
            'avatar_url' => $avatarUrl,
            'avatar_text' => $avatarText !== '' ? $avatarText : 'A',
            'avatar_theme' => 'theme-6',
        ];
    }

    function chat_presence_payload(string $presenceKey = 'offline'): array
    {
        $normalizedKey = strtolower(trim($presenceKey));
        if ($normalizedKey !== 'online' && $normalizedKey !== 'away') {
            $normalizedKey = 'offline';
        }

        $labels = [
            'online' => 'Online',
            'away' => 'Away',
            'offline' => 'Offline',
        ];

        return [
            'key' => $normalizedKey,
            'label' => $labels[$normalizedKey],
            'class_name' => 'admin-chat-presence admin-chat-presence--' . $normalizedKey,
        ];
    }

    function chat_presence_key_from_last_seen(string $lastSeenAt = '', ?int $currentTime = null): string
    {
        $currentTime = $currentTime ?? time();
        $lastSeenAt = trim($lastSeenAt);
        $lastSeenTimestamp = $lastSeenAt !== '' ? strtotime($lastSeenAt) : false;
        if ($lastSeenTimestamp === false) {
            return 'offline';
        }

        $secondsSinceLastSeen = max(0, $currentTime - $lastSeenTimestamp);
        if ($secondsSinceLastSeen <= 180) {
            return 'online';
        }

        if ($secondsSinceLastSeen <= 600) {
            return 'away';
        }

        return 'offline';
    }

    function chat_customer_presence_payload(Mysql_ks $db, int $customerId, string $lastSeenAt = ''): array
    {
        if ($customerId <= 0 || !function_exists('app_customer_presence_key')) {
            return chat_presence_payload('offline');
        }

        return chat_presence_payload(app_customer_presence_key($db, $customerId, $lastSeenAt));
    }

    function chat_admin_presence_payload(string $lastSeenAt = ''): array
    {
        return chat_presence_payload(chat_presence_key_from_last_seen($lastSeenAt));
    }

    function chat_support_presence_payload(Mysql_ks $db): array
    {
        if (!schema_object_exists($db, 'admin_users')) {
            return chat_presence_payload('offline');
        }

        $rows = $db->select_full_user(
            "SELECT last_login_at
             FROM admin_users
             WHERE status = 'active'
             ORDER BY id ASC"
        );

        $hasAway = false;
        foreach ($rows as $row) {
            $key = chat_presence_key_from_last_seen((string)($row['last_login_at'] ?? ''));
            if ($key === 'online') {
                return chat_presence_payload('online');
            }
            if ($key === 'away') {
                $hasAway = true;
            }
        }

        return chat_presence_payload($hasAway ? 'away' : 'offline');
    }

    function chat_support_avatar_url(Mysql_ks $db): string
    {
        if (!function_exists('app_fetch_settings') || !function_exists('app_format_logo_path')) {
            return '';
        }

        $settings = app_fetch_settings($db);
        $logoPath = trim((string)($settings['site_logo_url'] ?? $settings['page_logo'] ?? ''));
        if ($logoPath === '') {
            return '';
        }

        return app_format_logo_path($logoPath);
    }

    function chat_aggregate_presence_payload(array $members): array
    {
        $hasAway = false;
        foreach ($members as $member) {
            $key = strtolower(trim((string)($member['presence_key'] ?? 'offline')));
            if ($key === 'online') {
                return chat_presence_payload('online');
            }
            if ($key === 'away') {
                $hasAway = true;
            }
        }

        return chat_presence_payload($hasAway ? 'away' : 'offline');
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

    function chat_cleanup_direct_conversation_if_lonely(Mysql_ks $db, int $conversationId): bool
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0) {
            return false;
        }

        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation || empty($conversation['is_direct_conversation'])) {
            return false;
        }

        $remainingRow = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM support_conversation_members
             WHERE conversation_id = {$conversationId}
               AND invite_status IN ('accepted', 'pending')"
        );
        $remainingCount = (int)($remainingRow['total'] ?? 0);
        if ($remainingCount > 1) {
            return false;
        }

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

        @$db->query("DELETE FROM support_conversation_members WHERE conversation_id = {$conversationId}");
        $deletedConversation = @$db->query(
            "DELETE FROM support_conversations
             WHERE id = {$conversationId}
               AND conversation_type = 'group_chat'
               AND is_direct_conversation = 1
             LIMIT 1"
        );

        return (bool)$deletedConversation && (int)$db->affected_rows > 0;
    }

    function chat_expire_stale_group_invites(Mysql_ks $db, int $expiryHours = 24): array
    {
        chat_ensure_group_chat_runtime($db);
        if (!schema_object_exists($db, 'support_conversation_members')) {
            return ['ok' => true, 'deleted_invites' => 0, 'deleted_direct_conversations' => 0];
        }

        $expiryHours = max(1, $expiryHours);
        $staleRows = $db->select_full_user(
            "SELECT id, conversation_id
             FROM support_conversation_members
             WHERE invite_status = 'pending'
               AND created_at < DATE_SUB(NOW(), INTERVAL {$expiryHours} HOUR)"
        );

        if (!$staleRows) {
            return ['ok' => true, 'deleted_invites' => 0, 'deleted_direct_conversations' => 0];
        }

        $deletedInvites = 0;
        $affectedConversationIds = [];

        foreach ($staleRows as $staleRow) {
            $memberId = (int)($staleRow['id'] ?? 0);
            $conversationId = (int)($staleRow['conversation_id'] ?? 0);
            if ($memberId <= 0) {
                continue;
            }

            $result = @$db->query("DELETE FROM support_conversation_members WHERE id = {$memberId} LIMIT 1");
            if ($result) {
                $deletedInvites += (int)$db->affected_rows;
                if ($conversationId > 0) {
                    $affectedConversationIds[$conversationId] = $conversationId;
                }
            }
        }

        $deletedDirectConversations = 0;
        foreach (array_values($affectedConversationIds) as $conversationId) {
            if (chat_cleanup_direct_conversation_if_lonely($db, $conversationId)) {
                $deletedDirectConversations++;
            }
        }

        return [
            'ok' => true,
            'deleted_invites' => $deletedInvites,
            'deleted_direct_conversations' => $deletedDirectConversations,
        ];
    }

    function chat_resolve_group_invitee_by_email(Mysql_ks $db, string $email, array $settings = []): ?array
    {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);
        if (!$settings && function_exists('app_fetch_settings')) {
            $settings = app_fetch_settings($db);
        }
        $identifierRaw = trim($email);
        if ($identifierRaw === '') {
            return null;
        }

        $isHandleLookup = strpos($identifierRaw, '@') === 0;
        $email = strtolower($identifierRaw);
        $handle = $isHandleLookup ? chat_normalize_public_handle((string)substr($identifierRaw, 1)) : '';
        if ($isHandleLookup && $handle === '') {
            return null;
        }

        if (schema_object_exists($db, 'customers')) {
            if (function_exists('app_ensure_customer_runtime_columns')) {
                app_ensure_customer_runtime_columns($db);
            }

            if ($isHandleLookup) {
                $safeHandle = $db->escape($handle);
                $customer = $db->select_user(
                    "SELECT id, email, customer_type, public_handle, avatar_url
                     FROM customers
                     WHERE LOWER(public_handle) = '{$safeHandle}'
                     LIMIT 1"
                );
            } else {
                $safeEmail = $db->escape($email);
                $customer = $db->select_user(
                    "SELECT id, email, customer_type, public_handle, avatar_url
                     FROM customers
                     WHERE LOWER(email) = '{$safeEmail}'
                     LIMIT 1"
                );
            }
            if (is_array($customer) && !empty($customer['id']) && chat_customer_can_use_groups($customer, $settings)) {
                return [
                    'participant_type' => 'customer',
                    'participant_key' => chat_participant_key_for_customer((int)$customer['id']),
                    'customer_id' => (int)$customer['id'],
                    'customer_type' => (string)($customer['customer_type'] ?? 'client'),
                    'admin_user_id' => 0,
                    'email' => (string)$customer['email'],
                    'display_name' => chat_customer_display_label_from_row($customer),
                ];
            }
        }

        if (schema_object_exists($db, 'admin_users')) {
            if ($isHandleLookup) {
                $safeHandle = $db->escape($handle);
                $admin = $db->select_user(
                    "SELECT id, email, login_name, public_handle
                     FROM admin_users
                     WHERE LOWER(public_handle) = '{$safeHandle}'
                       AND status = 'active'
                     LIMIT 1"
                );
            } else {
                $safeEmail = $db->escape($email);
                $admin = $db->select_user(
                    "SELECT id, email, login_name, public_handle
                     FROM admin_users
                     WHERE LOWER(email) = '{$safeEmail}'
                       AND status = 'active'
                     LIMIT 1"
                );
            }
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

    function chat_validate_group_invitee_email(Mysql_ks $db, string $email, array $creator = [], int $conversationId = 0, array $settings = [], array $messages = []): array
    {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);
        if (!$settings && function_exists('app_fetch_settings')) {
            $settings = app_fetch_settings($db);
        }

        $translate = static function (string $key, string $fallback) use ($messages): string {
            if (function_exists('localization_translate')) {
                return localization_translate($messages, $key, $fallback);
            }
            return $fallback;
        };

        $normalizedEmail = trim($email);
        $isHandleLookup = strpos($normalizedEmail, '@') === 0;
        if ($normalizedEmail === '') {
            return ['ok' => false, 'message' => $translate('group_chat_email_invalid', 'Enter a valid email address or handle starting with @.')];
        }

        if ($isHandleLookup) {
            $normalizedHandle = chat_normalize_public_handle((string)substr($normalizedEmail, 1));
            if ($normalizedHandle === '') {
                return ['ok' => false, 'message' => $translate('group_chat_email_invalid', 'Enter a valid email address or handle starting with @.')];
            }
            $normalizedEmail = '@' . $normalizedHandle;
        } elseif (!filter_var(strtolower($normalizedEmail), FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => $translate('group_chat_email_invalid', 'Enter a valid email address or handle starting with @.')];
        }

        $invitee = chat_resolve_group_invitee_by_email($db, $normalizedEmail, $settings);
        if (!$invitee) {
            return ['ok' => false, 'message' => $translate('group_chat_email_not_found', 'No user with Messenger access was found for this email or handle.')];
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

        if ($creatorType === 'customer' && ($invitee['participant_type'] ?? '') === 'admin') {
            return ['ok' => false, 'message' => 'Clients cannot invite admins to private conversations.'];
        }

        if (
            $creatorType === 'customer'
            && ($invitee['participant_type'] ?? '') === 'customer'
            && chat_customer_is_reseller($invitee)
        ) {
            return ['ok' => false, 'message' => 'Clients can invite only other client accounts.'];
        }

        if (
            $creatorType === 'admin'
            && ($invitee['participant_type'] ?? '') === 'customer'
            && !chat_customer_is_reseller($invitee)
        ) {
            return ['ok' => false, 'message' => 'Client accounts can join admins only through support chat or the global group.'];
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
        $conversation = chat_group_conversation_row($db, $conversationId);
        $isGlobalGroup = chat_is_global_group_conversation_type((string)($conversation['conversation_type'] ?? ''));
        $globalChatBlocked = $isGlobalGroup ? (int)($existing['global_chat_blocked'] ?? 0) : 0;
        $canPostValue = $isGlobalGroup && $globalChatBlocked !== 0 ? 0 : 1;

        $values = [
            $conversationId,
            $participantKey,
            (string)$participant['participant_type'],
            !empty($participant['customer_id']) ? (int)$participant['customer_id'] : null,
            !empty($participant['admin_user_id']) ? (int)$participant['admin_user_id'] : null,
            $roleCode,
            $inviteStatus,
            $canPostValue,
            $invitedByCustomerId > 0 ? $invitedByCustomerId : null,
            $invitedByAdminUserId > 0 ? $invitedByAdminUserId : null,
            $respondedAt,
            $joinedAt,
            null,
            0,
            $globalChatBlocked,
        ];

        if ($existing) {
            $db->update_using_id(
                ['participant_type', 'customer_id', 'admin_user_id', 'role_code', 'invite_status', 'can_post', 'invited_by_customer_id', 'invited_by_admin_user_id', 'responded_at', 'joined_at', 'left_at', 'global_chat_blocked'],
                [
                    (string)$participant['participant_type'],
                    !empty($participant['customer_id']) ? (int)$participant['customer_id'] : null,
                    !empty($participant['admin_user_id']) ? (int)$participant['admin_user_id'] : null,
                    $roleCode,
                    $inviteStatus,
                    $canPostValue,
                    $invitedByCustomerId > 0 ? $invitedByCustomerId : null,
                    $invitedByAdminUserId > 0 ? $invitedByAdminUserId : null,
                    $respondedAt,
                    $joinedAt,
                    null,
                    $globalChatBlocked,
                ],
                'support_conversation_members',
                (int)$existing['id']
            );
            return;
        }

        $db->insert(
            ['conversation_id', 'participant_key', 'participant_type', 'customer_id', 'admin_user_id', 'role_code', 'invite_status', 'can_post', 'invited_by_customer_id', 'invited_by_admin_user_id', 'responded_at', 'joined_at', 'left_at', 'last_read_message_id', 'global_chat_blocked'],
            $values,
            'support_conversation_members'
        );
    }

    function chat_global_group_customer_blocked(Mysql_ks $db, int $customerId): bool
    {
        chat_ensure_group_chat_runtime($db);
        if ($customerId <= 0 || !schema_object_exists($db, 'support_conversation_members')) {
            return false;
        }

        $conversation = chat_global_group_conversation_row($db);
        if (!$conversation) {
            return false;
        }

        $member = chat_group_member_row($db, (int)($conversation['id'] ?? 0), chat_participant_key_for_customer($customerId));
        return is_array($member) && (int)($member['global_chat_blocked'] ?? 0) !== 0;
    }

    function chat_set_global_group_customer_block_status(
        Mysql_ks $db,
        int $customerId,
        int $adminUserId,
        bool $blocked,
        array $settings = [],
        string $ipAddress = ''
    ): array {
        chat_ensure_group_chat_runtime($db);
        if ($customerId <= 0 || !chat_demo_showcase_enabled($settings) && empty($settings['customer_global_group_enabled'])) {
            return ['ok' => false, 'message' => 'Global chat is disabled.'];
        }

        $customer = schema_object_exists($db, 'customers')
            ? $db->select_user("SELECT id, email, public_handle, avatar_url FROM customers WHERE id = {$customerId} LIMIT 1")
            : null;
        if (!is_array($customer) || empty($customer['id'])) {
            return ['ok' => false, 'message' => 'Customer not found.'];
        }

        chat_sync_global_group_members($db, $settings);
        $conversation = chat_global_group_conversation_row($db);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Global chat not found.'];
        }

        $conversationId = (int)($conversation['id'] ?? 0);
        $participantKey = chat_participant_key_for_customer($customerId);
        $member = chat_group_member_row($db, $conversationId, $participantKey);
        if (!$member) {
            return ['ok' => false, 'message' => 'Customer is not a member of Global Chat.'];
        }

        $currentBlocked = (int)($member['global_chat_blocked'] ?? 0) !== 0;
        if ($currentBlocked === $blocked) {
            return ['ok' => true, 'message' => $blocked ? 'Customer already blocked in Global Chat.' : 'Customer already active in Global Chat.'];
        }

        $updated = $db->update_using_id(
            ['global_chat_blocked', 'can_post'],
            [$blocked ? 1 : 0, $blocked ? 0 : 1],
            'support_conversation_members',
            (int)$member['id']
        );
        if (!$updated) {
            return ['ok' => false, 'message' => $blocked ? 'Unable to block customer in Global Chat.' : 'Unable to unblock customer in Global Chat.'];
        }

        $label = chat_customer_display_label_from_row($customer);
        if ($label === '') {
            $label = '@user';
        }
        chat_insert_group_system_notice(
            $db,
            $conversationId,
            $label . ($blocked ? ' został zablokowany.' : ' został odblokowany.'),
            $adminUserId
        );

        admin_log_customer_and_admin(
            $db,
            $customerId,
            $adminUserId,
            $blocked ? 'customer_global_chat_blocked' : 'customer_global_chat_unblocked',
            $blocked ? 'Customer blocked in Global Chat.' : 'Customer unblocked in Global Chat.',
            $ipAddress
        );

        return ['ok' => true, 'message' => $blocked ? 'Customer blocked in Global Chat.' : 'Customer unblocked in Global Chat.'];
    }

    function chat_group_notice_message(string $text): string
    {
        return '[system_notice]' . trim($text);
    }

    function chat_insert_group_system_notice(Mysql_ks $db, int $conversationId, string $text, int $adminUserId = 0, string $createdAt = ''): void
    {
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return;
        }

        $currentTime = chat_current_datetime();
        $createdAt = trim($createdAt);
        $createdAtTimestamp = $createdAt !== '' ? strtotime($createdAt) : false;
        if ($createdAtTimestamp === false) {
            $createdAt = $currentTime;
            $createdAtTimestamp = strtotime($currentTime);
        } else {
            $createdAt = date('Y-m-d H:i:s', $createdAtTimestamp);
        }

        $db->insert(
            ['conversation_id', 'sender_type', 'customer_id', 'admin_user_id', 'message_body', 'attachment_path', 'is_read', 'created_at'],
            [$conversationId, 'admin', null, $adminUserId > 0 ? $adminUserId : null, chat_group_notice_message($text), null, 1, $createdAt],
            'support_messages'
        );

        if (schema_object_exists($db, 'support_conversations')) {
            $conversation = chat_group_conversation_row($db, $conversationId);
            $conversationUpdatedAt = trim((string)($conversation['updated_at'] ?? ''));
            $conversationUpdatedTimestamp = $conversationUpdatedAt !== '' ? strtotime($conversationUpdatedAt) : false;
            if ($conversationUpdatedTimestamp === false || ($createdAtTimestamp !== false && $createdAtTimestamp >= $conversationUpdatedTimestamp)) {
                $db->update_using_id(
                    ['updated_at', 'last_admin_message_at', 'status'],
                    [$createdAt, $createdAt, 'open'],
                    'support_conversations',
                    $conversationId
                );
            } else {
                $db->update_using_id(
                    ['status'],
                    ['open'],
                    'support_conversations',
                    $conversationId
                );
            }
        }
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
               AND conversation_type IN ('group_chat', 'global_group')
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_global_group_conversation_row(Mysql_ks $db): ?array
    {
        chat_ensure_group_chat_runtime($db);
        if (!schema_object_exists($db, 'support_conversations')) {
            return null;
        }

        $row = $db->select_user(
            "SELECT *
             FROM support_conversations
             WHERE conversation_type = 'global_group'
             ORDER BY id ASC
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_global_group_admin_seed_id(Mysql_ks $db): int
    {
        if (!schema_object_exists($db, 'admin_users')) {
            return 0;
        }

        $row = $db->select_user(
            "SELECT id
             FROM admin_users
             WHERE status = 'active'
             ORDER BY id ASC
             LIMIT 1"
        );

        return (int)($row['id'] ?? 0);
    }

    function chat_eligible_global_group_customers(Mysql_ks $db, array $settings = []): array
    {
        if (!schema_object_exists($db, 'customers')) {
            return [];
        }

        if (function_exists('app_ensure_customer_runtime_columns')) {
            app_ensure_customer_runtime_columns($db);
        }

        $rows = $db->select_full_user(
            "SELECT id, email, public_handle, avatar_url, customer_type, status, last_login_at
             FROM customers
             WHERE status = 'active'
             ORDER BY id ASC"
        );

        $eligible = [];
        foreach ($rows as $row) {
            if (chat_customer_is_reseller($row) || chat_customer_messenger_enabled($row, $settings)) {
                $eligible[] = $row;
            }
        }

        return $eligible;
    }

    function chat_eligible_global_group_admins(Mysql_ks $db): array
    {
        if (!schema_object_exists($db, 'admin_users')) {
            return [];
        }

        return $db->select_full_user(
            "SELECT id, email, login_name, public_handle, avatar_url, status, last_login_at
             FROM admin_users
             WHERE status = 'active'
             ORDER BY id ASC"
        );
    }

    function chat_global_group_recent_join_notice_timestamp(array $customerRow, int $windowHours = 24): string
    {
        $lastLoginAt = trim((string)($customerRow['last_login_at'] ?? ''));
        if ($lastLoginAt === '') {
            return '';
        }

        $lastLoginTimestamp = strtotime($lastLoginAt);
        if ($lastLoginTimestamp === false) {
            return '';
        }

        $now = time();
        $windowSeconds = max(1, $windowHours) * 3600;
        if ($lastLoginTimestamp > $now || ($now - $lastLoginTimestamp) > $windowSeconds) {
            return '';
        }

        return date('Y-m-d H:i:s', $lastLoginTimestamp);
    }

    function chat_global_group_repair_join_notices(Mysql_ks $db, int $conversationId, array $eligibleCustomers = [], int $windowHours = 24): void
    {
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return;
        }

        $rows = $db->select_full_user(
            "SELECT id, message_body, created_at
             FROM support_messages
             WHERE conversation_id = {$conversationId}
               AND sender_type = 'admin'
               AND message_body LIKE '[system_notice]%dołączył do nas!'
             ORDER BY created_at ASC, id ASC"
        );
        if (!$rows) {
            return;
        }

        $customerJoinMap = [];
        foreach ($eligibleCustomers as $customerRow) {
            $label = chat_customer_display_label_from_row($customerRow);
            if ($label === '') {
                continue;
            }
            $customerJoinMap[$label] = chat_global_group_recent_join_notice_timestamp($customerRow, $windowHours);
        }

        $deleteIds = [];
        foreach ($rows as $row) {
            $messageId = (int)($row['id'] ?? 0);
            $body = chat_system_notice_text((string)($row['message_body'] ?? ''));
            if ($messageId <= 0 || $body === '') {
                continue;
            }

            if (!preg_match('/^(.*) dołączył do nas!$/u', $body, $matches)) {
                continue;
            }

            $label = trim((string)($matches[1] ?? ''));
            $expectedTimestamp = $label !== '' ? trim((string)($customerJoinMap[$label] ?? '')) : '';
            if ($expectedTimestamp === '') {
                $deleteIds[] = $messageId;
                continue;
            }

            $existingTimestamp = strtotime((string)($row['created_at'] ?? ''));
            $targetTimestamp = strtotime($expectedTimestamp);
            if ($existingTimestamp === false || $targetTimestamp === false) {
                continue;
            }

            if (abs($existingTimestamp - $targetTimestamp) > 300) {
                $db->update_using_id(
                    ['created_at'],
                    [$expectedTimestamp],
                    'support_messages',
                    $messageId
                );
            }
        }

        if ($deleteIds) {
            $db->query("DELETE FROM support_messages WHERE id IN (" . implode(',', array_map('intval', $deleteIds)) . ")");
        }

        $db->query(
            "UPDATE support_conversations AS conversation
             LEFT JOIN (
                SELECT
                    conversation_id,
                    MAX(created_at) AS latest_message_at,
                    MAX(CASE WHEN sender_type = 'customer' THEN created_at ELSE NULL END) AS latest_customer_message_at,
                    MAX(CASE WHEN sender_type = 'admin' THEN created_at ELSE NULL END) AS latest_admin_message_at
                FROM support_messages
                GROUP BY conversation_id
             ) AS message_state
               ON message_state.conversation_id = conversation.id
             SET conversation.updated_at = COALESCE(message_state.latest_message_at, conversation.updated_at),
                 conversation.last_customer_message_at = message_state.latest_customer_message_at,
                 conversation.last_admin_message_at = message_state.latest_admin_message_at
             WHERE conversation.id = {$conversationId}
               AND conversation.conversation_type = 'global_group'"
        );
    }

    function chat_global_group_has_join_notice(Mysql_ks $db, int $conversationId, string $label, string $joinedAt, int $windowMinutes = 5): bool
    {
        if ($conversationId <= 0 || $label === '' || $joinedAt === '' || !schema_object_exists($db, 'support_messages')) {
            return false;
        }

        $safeLabel = $db->escape($label . ' dołączył do nas!');
        $safeJoinedAt = $db->escape($joinedAt);
        $windowMinutes = max(1, $windowMinutes);
        $row = $db->select_user(
            "SELECT id
             FROM support_messages
             WHERE conversation_id = {$conversationId}
               AND sender_type = 'admin'
               AND message_body = '[system_notice]{$safeLabel}'
               AND ABS(TIMESTAMPDIFF(MINUTE, created_at, '{$safeJoinedAt}')) <= {$windowMinutes}
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']);
    }

    function chat_ensure_global_group_conversation(Mysql_ks $db, array $settings = []): ?array
    {
        if (!chat_demo_showcase_enabled($settings) && empty($settings['customer_global_group_enabled'])) {
            return null;
        }

        $existing = chat_global_group_conversation_row($db);
        if ($existing) {
            if (chat_group_retention_minutes_from_row($existing) !== chat_global_group_retention_hours()) {
                $db->update_using_id(
                    ['message_retention_hours', 'message_retention_minutes'],
                    [chat_group_retention_storage_hours(chat_global_group_retention_hours()), chat_global_group_retention_hours()],
                    'support_conversations',
                    (int)($existing['id'] ?? 0)
                );
                $existing = chat_group_conversation_row($db, (int)($existing['id'] ?? 0)) ?: $existing;
            }
            return $existing;
        }

        $seedAdminUserId = chat_global_group_admin_seed_id($db);
        $currentTime = chat_current_datetime();
        $inserted = $db->insert(
            ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'group_name', 'is_group_read_only', 'group_created_by_customer_id', 'group_created_by_admin_user_id', 'message_retention_hours', 'message_retention_minutes', 'status', 'priority', 'created_at', 'updated_at'],
            ['global_group', null, $seedAdminUserId > 0 ? $seedAdminUserId : null, chat_global_group_system_subject(), chat_global_group_title(), 0, null, $seedAdminUserId > 0 ? $seedAdminUserId : null, chat_group_retention_storage_hours(chat_global_group_retention_hours()), chat_global_group_retention_hours(), 'open', 'normal', $currentTime, $currentTime],
            'support_conversations'
        );
        if (!$inserted) {
            return null;
        }

        return chat_group_conversation_row($db, (int)$db->id());
    }

    function chat_sync_global_group_members(Mysql_ks $db, array $settings = []): ?array
    {
        if (!chat_demo_showcase_enabled($settings) && empty($settings['customer_global_group_enabled'])) {
            return null;
        }

        $conversation = chat_ensure_global_group_conversation($db, $settings);
        if (!$conversation) {
            return null;
        }

        $conversationId = (int)($conversation['id'] ?? 0);
        if ($conversationId <= 0) {
            return null;
        }

        $seedAdminUserId = chat_global_group_admin_seed_id($db);
        $desiredParticipantKeys = [];

        foreach (chat_eligible_global_group_admins($db) as $adminRow) {
            $adminUserId = (int)($adminRow['id'] ?? 0);
            if ($adminUserId <= 0) {
                continue;
            }
            $participant = [
                'participant_key' => chat_participant_key_for_admin($adminUserId),
                'participant_type' => 'admin',
                'customer_id' => 0,
                'admin_user_id' => $adminUserId,
            ];
            chat_add_group_member($db, $conversationId, $participant, 'accepted', 'member', 0, $seedAdminUserId);
            $desiredParticipantKeys[$participant['participant_key']] = true;
        }

        $eligibleCustomers = chat_eligible_global_group_customers($db, $settings);
        chat_global_group_repair_join_notices($db, $conversationId, $eligibleCustomers, 24);

        foreach ($eligibleCustomers as $customerRow) {
            $customerId = (int)($customerRow['id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $participantKey = chat_participant_key_for_customer($customerId);
            $existingMember = chat_group_member_row($db, $conversationId, $participantKey);
            $isNewMember = !$existingMember || trim((string)($existingMember['invite_status'] ?? '')) !== 'accepted';
            $participant = [
                'participant_key' => $participantKey,
                'participant_type' => 'customer',
                'customer_id' => $customerId,
                'admin_user_id' => 0,
            ];
            chat_add_group_member($db, $conversationId, $participant, 'accepted', 'member', 0, $seedAdminUserId);
            $desiredParticipantKeys[$participantKey] = true;

            $label = chat_customer_display_label_from_row($customerRow);
            $joinedAt = chat_global_group_recent_join_notice_timestamp($customerRow, 24);
            if ($label !== '' && $joinedAt !== '') {
                $shouldInsertJoinNotice = $isNewMember
                    || !chat_global_group_has_join_notice($db, $conversationId, $label, $joinedAt, 5);
                if ($shouldInsertJoinNotice) {
                    chat_insert_group_system_notice($db, $conversationId, $label . ' dołączył do nas!', $seedAdminUserId, $joinedAt);
                }
            }
        }

        if (schema_object_exists($db, 'support_conversation_members')) {
            $rows = $db->select_full_user(
                "SELECT id, participant_key
                 FROM support_conversation_members
                 WHERE conversation_id = {$conversationId}"
            );
            foreach ($rows as $row) {
                $participantKey = (string)($row['participant_key'] ?? '');
                if ($participantKey === '' || isset($desiredParticipantKeys[$participantKey])) {
                    continue;
                }
                $db->update_using_id(
                    ['invite_status', 'can_post', 'left_at'],
                    ['left', 0, chat_current_datetime()],
                    'support_conversation_members',
                    (int)($row['id'] ?? 0)
                );
            }
        }

        return chat_group_conversation_row($db, $conversationId);
    }

    function chat_group_actor_can_update_retention(Mysql_ks $db, array $conversation, array $actor): bool
    {
        $conversationType = (string)($conversation['conversation_type'] ?? '');
        if (chat_is_global_group_conversation_type($conversationType)) {
            return trim((string)($actor['participant_type'] ?? '')) === 'admin'
                && chat_group_can_admin_manage($conversation, (int)($actor['admin_user_id'] ?? 0));
        }

        $summary = chat_group_conversation_summary($db, (int)($conversation['id'] ?? 0), $actor, $conversation);
        if (!empty($summary['is_direct'])) {
            if (trim((string)($actor['participant_type'] ?? '')) === 'customer') {
                return chat_group_accessible_for_customer($db, (int)($actor['customer_id'] ?? 0), (int)($conversation['id'] ?? 0)) !== null;
            }
            if (trim((string)($actor['participant_type'] ?? '')) === 'admin') {
                return chat_group_accessible_for_admin($db, (int)($actor['admin_user_id'] ?? 0), (int)($conversation['id'] ?? 0)) !== null;
            }
            return false;
        }

        return chat_group_can_actor_manage($conversation, $actor);
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
            $customer = $db->select_user("SELECT email, public_handle, avatar_url FROM customers WHERE id = {$customerId} LIMIT 1");
            if (is_array($customer) && !empty($customer['email'])) {
                return chat_customer_display_label_from_row($customer);
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

        foreach (app_chat_attachment_candidate_paths($attachmentPath) as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    function chat_group_participant_label(Mysql_ks $db, array $member): string
    {
        if (!empty($member['customer_id']) && schema_object_exists($db, 'customers')) {
            $customerId = (int)$member['customer_id'];
            $customer = $db->select_user("SELECT email, public_handle, avatar_url FROM customers WHERE id = {$customerId} LIMIT 1");
            if (is_array($customer) && !empty($customer)) {
                return chat_customer_display_label_from_row($customer);
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

    function chat_group_conversation_has_pending_invites(Mysql_ks $db, int $conversationId): bool
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversation_members')) {
            return false;
        }

        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM support_conversation_members
             WHERE conversation_id = {$conversationId}
               AND invite_status = 'pending'"
        );

        return (int)($row['total'] ?? 0) > 0;
    }

    function chat_group_member_can_post(Mysql_ks $db, int $conversationId, string $participantKey): bool
    {
        $member = chat_group_member_row($db, $conversationId, $participantKey);
        if (!$member) {
            return false;
        }

        return (int)($member['can_post'] ?? 1) !== 0 && trim((string)($member['invite_status'] ?? '')) === 'accepted';
    }

    function chat_update_group_member_email_notifications(
        Mysql_ks $db,
        int $conversationId,
        string $participantKey,
        bool $enabled
    ): array {
        chat_ensure_group_chat_runtime($db);
        $member = chat_group_member_row($db, $conversationId, $participantKey);
        if (!$member || !in_array(trim((string)($member['invite_status'] ?? '')), ['accepted', 'pending'], true)) {
            return ['ok' => false, 'message' => 'Conversation not found.'];
        }

        $updated = $db->update_using_id(
            ['email_notifications_enabled'],
            [$enabled ? 1 : 0],
            'support_conversation_members',
            (int)$member['id']
        );

        return [
            'ok' => (bool)$updated,
            'message' => $enabled
                ? 'Email notifications enabled for this conversation.'
                : 'Email notifications muted for this conversation.',
        ];
    }

    function chat_update_group_retention_hours(
        Mysql_ks $db,
        int $conversationId,
        array $actor,
        $retentionHours
    ): array {
        chat_ensure_group_chat_runtime($db);
        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Group chat not found.'];
        }

        if (!chat_group_actor_can_update_retention($db, $conversation, $actor)) {
            return ['ok' => false, 'message' => 'You cannot update auto-delete settings for this conversation.'];
        }

        $normalizedHours = null;
        $rawRetentionValue = trim((string)$retentionHours);
        $actorType = trim((string)($actor['participant_type'] ?? ''));

        if ($actorType === 'customer' && in_array($rawRetentionValue, ['1', '5', '15', '30'], true)) {
            $normalizedHours = $rawRetentionValue === '1' ? 5 : (int)$rawRetentionValue;
        } else {
            $normalizedHours = chat_group_normalize_retention_hours($retentionHours);
        }

        if ($retentionHours !== null && trim((string)$retentionHours) !== '' && trim((string)$retentionHours) !== '0' && $normalizedHours === null) {
            return ['ok' => false, 'message' => 'Invalid auto-delete value.'];
        }

        $updated = $db->update_using_id(
            ['message_retention_hours', 'message_retention_minutes'],
            [chat_group_retention_storage_hours($normalizedHours), $normalizedHours],
            'support_conversations',
            $conversationId
        );

        if (!$updated) {
            return ['ok' => false, 'message' => 'Unable to update auto-delete settings.'];
        }

        $actorLabel = trim((string)($actor['participant_type'] ?? '')) === 'admin'
            ? chat_group_participant_label($db, ['admin_user_id' => (int)($actor['admin_user_id'] ?? 0)])
            : chat_group_participant_label($db, ['customer_id' => (int)($actor['customer_id'] ?? 0)]);
        $noticeText = $actorLabel . ' zmienil czas auto-usuwania wiadomosci na ' . chat_group_retention_label($normalizedHours) . '.';
        chat_insert_group_system_notice($db, $conversationId, $noticeText, (int)($actor['admin_user_id'] ?? 0));
        chat_mark_group_read_for_actor($db, $conversationId, $actor);

        return [
            'ok' => true,
            'message' => 'Auto-delete updated for this conversation.',
            'retention_hours' => $normalizedHours,
        ];
    }

    function chat_queue_group_customer_notifications_if_offline(
        Mysql_ks $db,
        int $conversationId,
        array $sender,
        string $messageBody,
        ?string $attachmentPath = null
    ): array {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversation_members') || !schema_object_exists($db, 'customers')) {
            return ['ok' => true, 'queued' => 0, 'skipped' => 0];
        }

        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Group chat not found.', 'queued' => 0, 'skipped' => 0];
        }

        if (chat_is_global_group_conversation_type((string)($conversation['conversation_type'] ?? ''))) {
            return ['ok' => true, 'queued' => 0, 'skipped' => 0];
        }

        $summary = chat_group_conversation_summary($db, $conversationId, $sender, $conversation);
        $conversationTitle = trim((string)($summary['title'] ?? chat_group_conversation_title($conversation)));
        $senderType = trim((string)($sender['participant_type'] ?? ''));
        $senderCustomerId = (int)($sender['customer_id'] ?? 0);
        $senderAdminUserId = (int)($sender['admin_user_id'] ?? 0);
        $senderLabel = $senderType === 'admin'
            ? chat_group_creator_label($db, $conversation, 'Admin')
            : chat_group_participant_label($db, ['customer_id' => $senderCustomerId, 'admin_user_id' => 0]);
        $currentTime = time();
        $settings = function_exists('app_fetch_settings') ? app_fetch_settings($db) : [];
        $chatUrl = rtrim((string)($settings['site_url'] ?? ''), '/') . '/';

        $rows = $db->select_full_user(
            "SELECT
                support_conversation_members.id,
                support_conversation_members.customer_id,
                support_conversation_members.email_notifications_enabled,
                customers.email,
                customers.locale_code,
                customers.last_login_at
             FROM support_conversation_members
             INNER JOIN customers
                ON customers.id = support_conversation_members.customer_id
             WHERE support_conversation_members.conversation_id = {$conversationId}
               AND support_conversation_members.customer_id IS NOT NULL
               AND support_conversation_members.invite_status = 'accepted'"
        );

        $queued = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $recipientCustomerId = (int)($row['customer_id'] ?? 0);
            if ($recipientCustomerId <= 0 || ($senderType === 'customer' && $recipientCustomerId === $senderCustomerId)) {
                $skipped++;
                continue;
            }

            if (!chat_group_member_email_notifications_enabled($row)) {
                $skipped++;
                continue;
            }

            if (function_exists('app_customer_is_currently_online') && app_customer_is_currently_online($db, $recipientCustomerId, (string)($row['last_login_at'] ?? ''))) {
                $skipped++;
                continue;
            }

            $recipientEmail = strtolower(trim((string)($row['email'] ?? '')));
            if ($recipientEmail === '') {
                $skipped++;
                continue;
            }

            $messagePreview = chat_message_preview_text($messageBody, (string)$attachmentPath, 'New image attachment');

            $result = function_exists('app_email_queue_template')
                ? app_email_queue_template(
                    $db,
                    'reseller-chat-customer-notify',
                    $recipientEmail,
                    [
                        'conversation_title' => $conversationTitle,
                        'sender_label' => $senderLabel,
                        'chat_url' => $chatUrl,
                        'message_preview' => $messagePreview,
                    ],
                    $recipientCustomerId,
                    null,
                    function_exists('chat_messenger_email_cooldown_seconds')
                        ? chat_messenger_email_cooldown_seconds()
                        : 3600,
                    true,
                    (string)($row['locale_code'] ?? '')
                )
                : ['ok' => false, 'queued' => false];

            if (!empty($result['queued'])) {
                $queued++;
            } else {
                $skipped++;
            }
        }

        return ['ok' => true, 'queued' => $queued, 'skipped' => $skipped];
    }

    function chat_messenger_email_cooldown_seconds(): int
    {
        return function_exists('app_reseller_chat_email_cooldown_seconds')
            ? max(300, (int)app_reseller_chat_email_cooldown_seconds())
            : 3600;
    }

    function chat_queue_group_invitation_email(
        Mysql_ks $db,
        array $invitee,
        string $conversationTitle,
        string $senderLabel,
        array $settings = []
    ): array {
        $recipientCustomerId = (int)($invitee['customer_id'] ?? 0);
        $recipientEmail = strtolower(trim((string)($invitee['email'] ?? '')));
        if ($recipientCustomerId <= 0 || $recipientEmail === '') {
            return ['ok' => true, 'queued' => false, 'skipped' => true];
        }

        $chatUrl = rtrim((string)($settings['site_url'] ?? ''), '/') . '/';

        return function_exists('app_email_queue_template')
            ? app_email_queue_template(
                $db,
                'messenger-invite-notify',
                $recipientEmail,
                [
                    'conversation_title' => $conversationTitle,
                    'sender_label' => $senderLabel,
                    'chat_url' => $chatUrl,
                ],
                $recipientCustomerId,
                null,
                chat_messenger_email_cooldown_seconds(),
                true,
                (string)($invitee['locale_code'] ?? '')
            )
            : ['ok' => false, 'queued' => false];
    }

    function chat_prune_group_chat_messages(Mysql_ks $db, ?string $now = null): array
    {
        chat_ensure_group_chat_runtime($db);
        if (!schema_object_exists($db, 'support_messages') || !schema_object_exists($db, 'support_conversations')) {
            return ['ok' => false, 'deleted_messages' => 0, 'deleted_files' => 0];
        }

        $safeNow = trim((string)$now);
        if ($safeNow === '') {
            $safeNow = date('Y-m-d H:i:s');
        }

        $allowed = implode(',', chat_group_retention_allowed_hours());
        $rows = $db->select_full_user(
            "SELECT support_messages.id, support_messages.attachment_path
             FROM support_messages
             INNER JOIN support_conversations
                ON support_conversations.id = support_messages.conversation_id
             WHERE support_conversations.conversation_type IN ('group_chat', 'global_group')
               AND CASE
                    WHEN support_conversations.message_retention_minutes = 1
                        THEN 5
                    WHEN support_conversations.message_retention_minutes IS NOT NULL
                        THEN support_conversations.message_retention_minutes
                    WHEN support_conversations.message_retention_hours IN (1, 6, 12, 24)
                        THEN support_conversations.message_retention_hours * 60
                    ELSE NULL
               END IN ({$allowed})
               AND support_messages.created_at < DATE_SUB(
                    '{$db->escape($safeNow)}',
                    INTERVAL CASE
                        WHEN support_conversations.message_retention_minutes = 1
                            THEN 5
                        WHEN support_conversations.message_retention_minutes IS NOT NULL
                            THEN support_conversations.message_retention_minutes
                        WHEN support_conversations.message_retention_hours IN (1, 6, 12, 24)
                            THEN support_conversations.message_retention_hours * 60
                        ELSE 60
                    END MINUTE
               )
             ORDER BY support_messages.id ASC"
        );

        if (!$rows) {
            return ['ok' => true, 'deleted_messages' => 0, 'deleted_files' => 0, 'time' => $safeNow];
        }

        $messageIds = [];
        $deletedFiles = 0;
        foreach ($rows as $row) {
            $messageId = (int)($row['id'] ?? 0);
            if ($messageId > 0) {
                $messageIds[] = $messageId;
            }
            $attachmentPath = trim((string)($row['attachment_path'] ?? ''));
            if ($attachmentPath !== '' && strpos($attachmentPath, '/uploads/chat/') === 0) {
                $absolutePath = app_chat_attachment_absolute_path($attachmentPath);
                if (is_file($absolutePath) && @unlink($absolutePath)) {
                    $deletedFiles++;
                }
            }
        }

        if (!$messageIds) {
            return ['ok' => true, 'deleted_messages' => 0, 'deleted_files' => $deletedFiles, 'time' => $safeNow];
        }

        $idList = implode(',', $messageIds);
        $deletedMessages = 0;
        if ($db->query("DELETE FROM support_messages WHERE id IN ({$idList})")) {
            $deletedMessages = (int)$db->affected_rows;
        }

        $db->query(
            "UPDATE support_conversations AS conversation
             LEFT JOIN (
                SELECT
                    conversation_id,
                    MAX(created_at) AS latest_message_at,
                    MAX(CASE WHEN sender_type = 'customer' THEN created_at ELSE NULL END) AS latest_customer_message_at,
                    MAX(CASE WHEN sender_type = 'admin' THEN created_at ELSE NULL END) AS latest_admin_message_at
                FROM support_messages
                GROUP BY conversation_id
             ) AS message_state
               ON message_state.conversation_id = conversation.id
             SET conversation.updated_at = COALESCE(message_state.latest_message_at, conversation.updated_at),
                 conversation.last_customer_message_at = message_state.latest_customer_message_at,
                 conversation.last_admin_message_at = message_state.latest_admin_message_at
             WHERE conversation.conversation_type = 'group_chat'"
        );

        return [
            'ok' => true,
            'deleted_messages' => $deletedMessages,
            'deleted_files' => $deletedFiles,
            'time' => $safeNow,
        ];
    }

    function chat_group_member_summaries(Mysql_ks $db, int $conversationId, array $statuses = ['accepted']): array
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
            "SELECT
                support_conversation_members.participant_type,
                support_conversation_members.customer_id,
                support_conversation_members.admin_user_id,
                support_conversation_members.invite_status,
                support_conversation_members.email_notifications_enabled,
                customers.email AS customer_email,
                customers.public_handle AS customer_public_handle,
                customers.avatar_url AS customer_avatar_url,
                customers.last_login_at AS customer_last_login_at,
                admin_users.email AS admin_email,
                admin_users.login_name AS admin_login_name,
                admin_users.public_handle AS admin_public_handle,
                admin_users.avatar_url AS admin_avatar_url,
                admin_users.last_login_at AS admin_last_login_at
             FROM support_conversation_members
             LEFT JOIN customers
                ON customers.id = support_conversation_members.customer_id
             LEFT JOIN admin_users
                ON admin_users.id = support_conversation_members.admin_user_id
             WHERE support_conversation_members.conversation_id = {$conversationId}
               AND support_conversation_members.invite_status IN (" . implode(', ', $safeStatuses) . ")
             ORDER BY support_conversation_members.id ASC"
        );

        $members = [];
        foreach ($rows as $row) {
            $participantType = trim((string)($row['participant_type'] ?? ''));
            if ($participantType === 'admin') {
                $adminRow = [
                    'email' => (string)($row['admin_email'] ?? ''),
                    'login_name' => (string)($row['admin_login_name'] ?? ''),
                    'public_handle' => (string)($row['admin_public_handle'] ?? ''),
                    'avatar_url' => (string)($row['admin_avatar_url'] ?? ''),
                ];
                $avatar = chat_admin_avatar_payload_from_row($adminRow);
                $members[] = [
                'participant_type' => 'admin',
                'customer_id' => 0,
                'admin_user_id' => (int)($row['admin_user_id'] ?? 0),
                'invite_status' => (string)($row['invite_status'] ?? ''),
                'label' => chat_admin_display_label($adminRow),
                    'email' => (string)($row['admin_email'] ?? ''),
                    'public_handle' => (string)($row['admin_public_handle'] ?? ''),
                    'avatar_url' => $avatar['avatar_url'],
                    'avatar_text' => $avatar['avatar_text'],
                    'avatar_theme' => $avatar['avatar_theme'],
                    'email_notifications_enabled' => (int)($row['email_notifications_enabled'] ?? 1) !== 0,
                    'presence_key' => (string)(chat_admin_presence_payload((string)($row['admin_last_login_at'] ?? ''))['key'] ?? 'offline'),
                ];
                continue;
            }

            $customerRow = [
                'email' => (string)($row['customer_email'] ?? ''),
                'public_handle' => (string)($row['customer_public_handle'] ?? ''),
                'avatar_url' => (string)($row['customer_avatar_url'] ?? ''),
            ];
            $avatar = chat_customer_avatar_payload_from_row($customerRow);
            $members[] = [
                'participant_type' => 'customer',
                'customer_id' => (int)($row['customer_id'] ?? 0),
                'admin_user_id' => 0,
                'invite_status' => (string)($row['invite_status'] ?? ''),
                'label' => chat_customer_display_label_from_row($customerRow),
                'email' => (string)($row['customer_email'] ?? ''),
                'public_handle' => (string)($row['customer_public_handle'] ?? ''),
                'avatar_url' => $avatar['avatar_url'],
                'avatar_text' => $avatar['avatar_text'],
                'avatar_theme' => $avatar['avatar_theme'],
                'email_notifications_enabled' => (int)($row['email_notifications_enabled'] ?? 1) !== 0,
                'presence_key' => (string)(chat_customer_presence_payload($db, (int)($row['customer_id'] ?? 0), (string)($row['customer_last_login_at'] ?? ''))['key'] ?? 'offline'),
            ];
        }

        return $members;
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

    function chat_group_member_count(Mysql_ks $db, int $conversationId, array $statuses = ['accepted']): int
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_conversation_members')) {
            return 0;
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

        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM support_conversation_members
             WHERE conversation_id = {$conversationId}
               AND invite_status IN (" . implode(', ', $safeStatuses) . ")"
        );

        return (int)($row['total'] ?? 0);
    }

    function chat_group_member_count_label(Mysql_ks $db, int $conversationId, array $statuses = ['accepted']): string
    {
        $count = chat_group_member_count($db, $conversationId, $statuses);
        return $count === 1 ? '1 Member' : $count . ' Members';
    }

    function chat_group_member_emails(Mysql_ks $db, int $conversationId, array $statuses = ['accepted']): array
    {
        return array_values(array_filter(array_map(static function (array $member): string {
            return trim((string)($member['label'] ?? ''));
        }, chat_group_member_summaries($db, $conversationId, $statuses))));
    }

    function chat_group_conversation_summary(Mysql_ks $db, int $conversationId, array $actor, array $conversation = []): array
    {
        $members = chat_group_member_summaries($db, $conversationId, ['accepted']);
        $membersWithPending = chat_group_member_summaries($db, $conversationId, ['accepted', 'pending']);
        $membersForDirectState = chat_group_member_summaries($db, $conversationId, ['accepted', 'pending', 'rejected']);
        $participantType = trim((string)($actor['participant_type'] ?? ''));
        $actorCustomerId = (int)($actor['customer_id'] ?? 0);
        $actorAdminUserId = (int)($actor['admin_user_id'] ?? 0);
        $actorParticipantKey = '';
        $otherMembers = [];
        $otherMembersWithPending = [];
        $otherMembersForDirectState = [];

        if ($participantType === 'customer' && $actorCustomerId > 0) {
            $actorParticipantKey = chat_participant_key_for_customer($actorCustomerId);
        } elseif ($participantType === 'admin' && $actorAdminUserId > 0) {
            $actorParticipantKey = chat_participant_key_for_admin($actorAdminUserId);
        }

        foreach ($members as $member) {
            if ($participantType === 'customer' && (string)($member['participant_type'] ?? '') === 'customer' && (int)($member['customer_id'] ?? 0) === $actorCustomerId) {
                continue;
            }
            if ($participantType === 'admin' && (string)($member['participant_type'] ?? '') === 'admin' && (int)($member['admin_user_id'] ?? 0) === $actorAdminUserId) {
                continue;
            }
            $otherMembers[] = $member;
        }

        foreach ($membersWithPending as $member) {
            if ($participantType === 'customer' && (string)($member['participant_type'] ?? '') === 'customer' && (int)($member['customer_id'] ?? 0) === $actorCustomerId) {
                continue;
            }
            if ($participantType === 'admin' && (string)($member['participant_type'] ?? '') === 'admin' && (int)($member['admin_user_id'] ?? 0) === $actorAdminUserId) {
                continue;
            }
            $otherMembersWithPending[] = $member;
        }

        foreach ($membersForDirectState as $member) {
            if ($participantType === 'customer' && (string)($member['participant_type'] ?? '') === 'customer' && (int)($member['customer_id'] ?? 0) === $actorCustomerId) {
                continue;
            }
            if ($participantType === 'admin' && (string)($member['participant_type'] ?? '') === 'admin' && (int)($member['admin_user_id'] ?? 0) === $actorAdminUserId) {
                continue;
            }
            $otherMembersForDirectState[] = $member;
        }

        $title = chat_group_conversation_title($conversation);
        $subtitle = chat_is_global_group_conversation_type((string)($conversation['conversation_type'] ?? '')) ? 'Global' : 'Group';
        $avatarUrl = chat_group_avatar_url((string)($conversation['group_avatar_url'] ?? ''));
        $avatarText = 'G';
        $avatarTheme = 'theme-6';
        $isDirect = !empty($conversation['is_direct_conversation']);
        $actorMember = $actorParticipantKey !== '' ? chat_group_member_row($db, $conversationId, $actorParticipantKey) : null;
        $actorInviteStatus = trim((string)($actorMember['invite_status'] ?? ''));
        $hasPendingInvite = $isDirect && $actorInviteStatus === 'pending';
        $directStatus = 'none';
        $directTargetCustomerId = 0;
        $presence = chat_aggregate_presence_payload($otherMembersWithPending);

        if ($isDirect) {
            $counterpart = $otherMembersForDirectState ? $otherMembersForDirectState[0] : [];
            $counterpartInviteStatus = trim((string)($counterpart['invite_status'] ?? ''));
            $directTargetCustomerId = (int)($counterpart['customer_id'] ?? 0);

            if ($counterpart) {
                $title = (string)($counterpart['label'] ?? $title);
                $avatarUrl = (string)($counterpart['avatar_url'] ?? '');
                $avatarText = (string)($counterpart['avatar_text'] ?? 'U');
                $avatarTheme = (string)($counterpart['avatar_theme'] ?? 'theme-1');
                $presence = chat_presence_payload((string)($counterpart['presence_key'] ?? 'offline'));
            }

            if ($hasPendingInvite) {
                $directStatus = 'pending_invited';
                $subtitle = 'Invite pending';
            } elseif ($counterpartInviteStatus === 'rejected' && $actorInviteStatus === 'accepted') {
                $directStatus = 'rejected';
                $subtitle = 'Invite rejected';
            } elseif ($counterpartInviteStatus === 'pending') {
                $directStatus = 'pending';
                $subtitle = 'Invite pending';
            } else {
                $directStatus = 'accepted';
                $subtitle = '1:1';
            }
        } elseif (chat_is_global_group_conversation_type((string)($conversation['conversation_type'] ?? ''))) {
            $avatarUrl = '';
            $avatarText = '★';
            $avatarTheme = 'theme-global';
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'is_direct' => $isDirect,
            'direct_status' => $directStatus,
            'direct_target_customer_id' => $directTargetCustomerId,
            'has_pending_invite' => $hasPendingInvite,
            'member_count' => count($members),
            'member_count_with_pending' => count($membersWithPending),
            'pending_member_count' => max(0, count($membersWithPending) - count($members)),
            'members' => $members,
            'members_with_pending' => $membersWithPending,
            'other_members' => $otherMembersWithPending,
            'avatar_url' => $avatarUrl,
            'avatar_text' => $avatarText,
            'avatar_theme' => $avatarTheme,
            'presence' => $presence,
        ];
    }

    function chat_find_customer_direct_conversation_between(Mysql_ks $db, int $customerId, int $targetCustomerId): ?array
    {
        chat_ensure_group_chat_runtime($db);
        if ($customerId <= 0 || $targetCustomerId <= 0 || !schema_object_exists($db, 'support_conversations') || !schema_object_exists($db, 'support_conversation_members')) {
            return null;
        }

        $participantKeyA = $db->escape(chat_participant_key_for_customer($customerId));
        $participantKeyB = $db->escape(chat_participant_key_for_customer($targetCustomerId));
        $row = $db->select_user(
            "SELECT support_conversations.*
             FROM support_conversations
             INNER JOIN support_conversation_members AS member_a
                ON member_a.conversation_id = support_conversations.id
               AND member_a.participant_key = '{$participantKeyA}'
               AND member_a.invite_status IN ('accepted', 'pending', 'rejected', 'left', 'removed')
             INNER JOIN support_conversation_members AS member_b
                ON member_b.conversation_id = support_conversations.id
               AND member_b.participant_key = '{$participantKeyB}'
               AND member_b.invite_status IN ('accepted', 'pending', 'rejected', 'left', 'removed')
             WHERE support_conversations.conversation_type = 'group_chat'
               AND support_conversations.is_direct_conversation = 1
             ORDER BY COALESCE(support_conversations.updated_at, support_conversations.created_at) DESC, support_conversations.id DESC
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? $row : null;
    }

    function chat_participant_last_seen_label(string $lastSeenAt = ''): string
    {
        $lastSeenAt = trim($lastSeenAt);
        if ($lastSeenAt === '') {
            return 'Offline';
        }

        $timestamp = strtotime($lastSeenAt);
        if ($timestamp === false) {
            return $lastSeenAt;
        }

        if (date('Y-m-d', $timestamp) === date('Y-m-d')) {
            return 'Dzisiaj ' . date('H:i', $timestamp);
        }

        return date('d.m.Y H:i', $timestamp);
    }

    function chat_group_invite_status_notice_text(string $participantLabel, bool $isDirectConversation, string $status): string
    {
        $participantLabel = trim($participantLabel);
        if ($participantLabel === '') {
            $participantLabel = 'Użytkownik';
        }

        if ($status === 'accepted') {
            return $isDirectConversation
                ? $participantLabel . ' zaakceptował zaproszenie do rozmowy.'
                : $participantLabel . ' zaakceptował zaproszenie do grupy.';
        }

        return $isDirectConversation
            ? $participantLabel . ' odrzucił zaproszenie do rozmowy.'
            : $participantLabel . ' odrzucił zaproszenie do grupy.';
    }

    function chat_group_leave_notice_text(string $participantLabel, bool $isDirectConversation): string
    {
        $participantLabel = trim($participantLabel);
        if ($participantLabel === '') {
            $participantLabel = 'Użytkownik';
        }

        return $isDirectConversation
            ? $participantLabel . ' opuścił rozmowę.'
            : $participantLabel . ' opuścił grupę.';
    }

    function chat_customer_participant_profile_payload(Mysql_ks $db, array $viewer, int $targetCustomerId, array $settings = [], int $contextConversationId = 0): array
    {
        $viewerCustomerId = (int)($viewer['id'] ?? 0);
        if ($viewerCustomerId <= 0 || $targetCustomerId <= 0 || $viewerCustomerId === $targetCustomerId) {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        $target = $db->select_user(
            "SELECT id, email, public_handle, avatar_url, customer_type, status, last_login_at
             FROM customers
             WHERE id = {$targetCustomerId}
             LIMIT 1"
        );
        if (!is_array($target) || empty($target['id']) || trim((string)($target['status'] ?? '')) !== 'active') {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        if (chat_customer_is_reseller($target)) {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        if (!chat_customer_can_start_direct_conversations($viewer, $settings)) {
            return ['ok' => false, 'message' => 'Direct conversations are disabled for this account.'];
        }

        $avatar = chat_customer_avatar_payload_from_row($target);
        $label = chat_customer_display_label_from_row($target);
        $directConversation = null;
        if ($contextConversationId > 0) {
            $contextConversation = chat_group_accessible_for_customer($db, $viewerCustomerId, $contextConversationId);
            if (
                is_array($contextConversation)
                && !empty($contextConversation['id'])
                && !empty($contextConversation['is_direct_conversation'])
            ) {
                $contextTargetMember = chat_group_member_row($db, (int)$contextConversation['id'], chat_participant_key_for_customer($targetCustomerId));
                if (
                    is_array($contextTargetMember)
                    && (string)($contextTargetMember['participant_type'] ?? 'customer') === 'customer'
                    && (int)($contextTargetMember['customer_id'] ?? 0) === $targetCustomerId
                ) {
                    $directConversation = $contextConversation;
                }
            }
        }

        if (!is_array($directConversation) || empty($directConversation['id'])) {
            $directConversation = chat_find_customer_direct_conversation_between($db, $viewerCustomerId, $targetCustomerId);
        }
        $conversationId = (int)($directConversation['id'] ?? 0);
        $viewerMember = $conversationId > 0 ? chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($viewerCustomerId)) : null;
        $targetMember = $conversationId > 0 ? chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($targetCustomerId)) : null;
        $viewerStatus = trim((string)($viewerMember['invite_status'] ?? ''));
        $targetStatus = trim((string)($targetMember['invite_status'] ?? ''));
        $hasExistingConversation = $conversationId > 0;
        $isAccepted = $targetStatus === 'accepted';
        $isPendingInviteForViewer = $hasExistingConversation && $viewerStatus === 'pending';
        $isRejectedInviteForTarget = $hasExistingConversation && $viewerStatus === 'accepted' && $targetStatus === 'rejected';
        $directStatus = $isPendingInviteForViewer
            ? 'pending_invited'
            : ($isRejectedInviteForTarget
                ? 'rejected'
                : ($isAccepted
                    ? 'accepted'
                    : ($targetStatus === 'pending'
                        ? 'pending'
                        : ($hasExistingConversation ? 'inactive' : 'none'))));
        $actionKind = $isPendingInviteForViewer
            ? 'respond_invite'
            : (($directStatus === 'rejected' || $directStatus === 'inactive')
                ? 'reinvite'
                : ($hasExistingConversation ? 'open' : 'invite'));

        return [
            'ok' => true,
            'participant_type' => 'customer',
            'customer_id' => $targetCustomerId,
            'avatar_url' => (string)($avatar['avatar_url'] ?? ''),
            'avatar_text' => (string)($avatar['avatar_text'] ?? 'U'),
            'avatar_theme' => (string)($avatar['avatar_theme'] ?? 'theme-1'),
            'display_label' => $label,
            'public_handle' => chat_normalize_public_handle((string)($target['public_handle'] ?? '')),
            'last_seen_label' => chat_participant_last_seen_label((string)($target['last_login_at'] ?? '')),
            'conversation_id' => $conversationId,
            'direct_status' => $directStatus,
            'action_kind' => $actionKind,
        ];
    }

    function chat_start_customer_direct_conversation(Mysql_ks $db, array $viewer, int $targetCustomerId, array $settings = []): array
    {
        $profile = chat_customer_participant_profile_payload($db, $viewer, $targetCustomerId, $settings);
        if (empty($profile['ok'])) {
            return $profile;
        }

        if (!empty($profile['conversation_id'])) {
            if (in_array((string)($profile['direct_status'] ?? ''), ['rejected', 'inactive'], true)) {
                $conversationId = (int)$profile['conversation_id'];
                $viewerCustomerId = (int)($viewer['id'] ?? 0);
                $viewerMember = chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($viewerCustomerId));
                $targetMember = chat_group_member_row($db, $conversationId, chat_participant_key_for_customer($targetCustomerId));
                $target = $db->select_user(
                    "SELECT id, email, public_handle, locale_code
                     FROM customers
                     WHERE id = {$targetCustomerId}
                     LIMIT 1"
                );
                if (!$viewerMember || !$targetMember || !is_array($target) || empty($target['id'])) {
                    return ['ok' => false, 'message' => 'User not found.'];
                }

                $db->update_using_id(
                    ['invite_status', 'responded_at', 'joined_at', 'left_at', 'can_post'],
                    ['accepted', chat_current_datetime(), chat_current_datetime(), null, 1],
                    'support_conversation_members',
                    (int)$viewerMember['id']
                );
                $db->update_using_id(
                    ['invite_status', 'responded_at', 'joined_at', 'left_at', 'can_post'],
                    ['pending', null, null, null, 1],
                    'support_conversation_members',
                    (int)$targetMember['id']
                );

                $conversation = chat_group_conversation_row($db, $conversationId);
                $title = chat_group_conversation_title((array)$conversation);
                $identifier = trim((string)($target['public_handle'] ?? '')) !== ''
                    ? '@' . trim((string)$target['public_handle'])
                    : trim((string)($target['email'] ?? ''));

                chat_insert_group_system_notice(
                    $db,
                    $conversationId,
                    'Ponowiono zaproszenie do rozmowy dla ' . $identifier . '.'
                );

                chat_log_customer_activity(
                    $db,
                    $targetCustomerId,
                    'group_chat_invited',
                    'You were invited to group chat "' . $title . '" by ' . chat_customer_display_label_from_row($viewer) . '.'
                );
                chat_queue_group_invitation_email(
                    $db,
                    $target,
                    $title,
                    chat_customer_display_label_from_row($viewer),
                    $settings
                );

                return [
                    'ok' => true,
                    'conversation_id' => $conversationId,
                    'action_kind' => 'reinvite',
                    'message' => 'Invitation sent again.',
                ];
            }

            return [
                'ok' => true,
                'conversation_id' => (int)$profile['conversation_id'],
                'action_kind' => (string)($profile['action_kind'] ?? 'open'),
                'message' => (string)($profile['direct_status'] ?? '') === 'accepted'
                    ? 'Conversation opened.'
                    : 'Invitation already sent.',
            ];
        }

        $target = $db->select_user(
            "SELECT id, email, public_handle, avatar_url, customer_type, status, last_login_at
             FROM customers
             WHERE id = {$targetCustomerId}
             LIMIT 1"
        );
        if (!is_array($target) || empty($target['id'])) {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        $identifier = trim((string)($target['public_handle'] ?? '')) !== ''
            ? '@' . trim((string)$target['public_handle'])
            : trim((string)($target['email'] ?? ''));
        if ($identifier === '') {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        $result = chat_create_group_conversation(
            $db,
            ['participant_type' => 'customer', 'customer_id' => (int)($viewer['id'] ?? 0), 'admin_user_id' => 0],
            '',
            [$identifier],
            false,
            $settings,
            null
        );
        if (empty($result['ok'])) {
            return $result;
        }

        return [
            'ok' => true,
            'conversation_id' => (int)($result['conversation_id'] ?? 0),
            'action_kind' => 'invite',
            'message' => 'Invitation sent.',
        ];
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
        array $settings = [],
        $retentionHours = null,
        string $groupAvatarUrl = '',
        bool $forceNamedGroup = false
    ): array {
        chat_ensure_group_chat_runtime($db);
        chat_expire_stale_group_invites($db);

        $creatorType = trim((string)($creator['participant_type'] ?? ''));
        $creatorCustomerId = (int)($creator['customer_id'] ?? 0);
        $creatorAdminUserId = (int)($creator['admin_user_id'] ?? 0);
        $groupName = trim($groupName);
        $groupName = preg_replace('/\s+/u', ' ', $groupName) ?? $groupName;

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
            if (!is_array($creatorCustomer) || !chat_customer_can_use_groups($creatorCustomer, $settings)) {
                return ['ok' => false, 'message' => 'This account cannot create Messenger conversations.'];
            }

            $creationState = chat_customer_group_creation_state($db, $creatorCustomer, $settings);
            if (!$creationState['allowed']) {
                if (!empty($creationState['blocked_by_limit'])) {
                    return ['ok' => false, 'message' => 'Group chat creation is disabled for reseller accounts.'];
                }

                if (!empty($creationState['reached_limit'])) {
                    return ['ok' => false, 'message' => 'You reached the maximum number of group chats for your account.'];
                }

                return ['ok' => false, 'message' => 'Conversation creation is disabled for this account.'];
            }
        }

        $invitees = [];
        $seenKeys = [];
        foreach ($inviteEmails as $inviteEmail) {
            $invitee = chat_resolve_group_invitee_by_email($db, (string)$inviteEmail, $settings);
            if (!$invitee) {
                continue;
            }
            $inviteeValidation = chat_validate_group_invitee_email(
                $db,
                (string)($invitee['email'] ?? $inviteEmail),
                $creator,
                0,
                $settings
            );
            if (empty($inviteeValidation['ok'])) {
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

        if (!$invitees && !$forceNamedGroup) {
            return ['ok' => false, 'message' => 'Add at least one eligible user email or handle to create a conversation.'];
        }

        $isDirectConversation = !$forceNamedGroup && count($invitees) === 1;
        $directInvitee = $isDirectConversation ? (array)$invitees[0] : [];

        if ($creatorType === 'customer') {
            if ($isDirectConversation && !chat_customer_can_start_direct_conversations($creatorCustomer, $settings)) {
                return ['ok' => false, 'message' => 'Direct conversations are disabled for this account.'];
            }

            if (!$isDirectConversation && !chat_customer_can_create_named_groups($creatorCustomer, $settings)) {
                return ['ok' => false, 'message' => 'Group conversations are disabled for this account.'];
            }
        }

        if ($groupName === '' && !$isDirectConversation) {
            return ['ok' => false, 'message' => 'Group name is required.'];
        }

        if ($isDirectConversation && $groupName === '') {
            $groupName = trim((string)($directInvitee['display_name'] ?? $directInvitee['email'] ?? 'Direct conversation'));
            if ($groupName === '') {
                $groupName = 'Direct conversation';
            }
        }

        $groupNameLength = function_exists('mb_strlen') ? mb_strlen($groupName) : strlen($groupName);
        if (!$isDirectConversation && $groupNameLength > 20) {
            return ['ok' => false, 'message' => 'Group name can be up to 20 characters.'];
        }

        if ($retentionHours === null || trim((string)$retentionHours) === '' || trim((string)$retentionHours) === '0') {
            $normalizedRetentionHours = 1440;
        } else {
            $normalizedRetentionHours = chat_group_normalize_retention_hours($retentionHours);
        }
        if ($normalizedRetentionHours === null) {
            return ['ok' => false, 'message' => 'Invalid auto-delete value.'];
        }
        $groupAvatarUrl = trim($groupAvatarUrl);

        $currentTime = chat_current_datetime();
        $inserted = $db->insert(
            ['conversation_type', 'customer_id', 'assigned_admin_id', 'subject', 'group_name', 'group_avatar_url', 'is_group_read_only', 'group_created_by_customer_id', 'group_created_by_admin_user_id', 'is_direct_conversation', 'message_retention_hours', 'message_retention_minutes', 'status', 'priority', 'created_at', 'updated_at'],
            [
                'group_chat',
                $creatorCustomerId > 0 ? $creatorCustomerId : null,
                $creatorAdminUserId > 0 ? $creatorAdminUserId : null,
                $groupName,
                $groupName,
                $groupAvatarUrl !== '' ? $groupAvatarUrl : null,
                $readOnly ? 1 : 0,
                $creatorCustomerId > 0 ? $creatorCustomerId : null,
                $creatorAdminUserId > 0 ? $creatorAdminUserId : null,
                $isDirectConversation ? 1 : 0,
                chat_group_retention_storage_hours($normalizedRetentionHours),
                $normalizedRetentionHours,
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
        if ($creatorType === 'customer' && is_array($creatorCustomer)) {
            $creatorLabel = chat_customer_display_label_from_row($creatorCustomer);
        } elseif ($creatorType === 'admin' && schema_object_exists($db, 'admin_users')) {
            $creatorAdmin = $db->select_user("SELECT login_name, public_handle, email FROM admin_users WHERE id = {$creatorAdminUserId} LIMIT 1");
            $creatorLabel = is_array($creatorAdmin) ? chat_admin_display_label($creatorAdmin) : 'Admin';
        }
        if ($creatorLabel === '') {
            $creatorLabel = $creatorType === 'admin' ? 'Admin' : 'User';
        }

        $invitedCount = 0;
        $inviteStatus = 'pending';
        foreach ($invitees as $invitee) {
            chat_add_group_member($db, $conversationId, $invitee, $inviteStatus, 'member', $creatorCustomerId, $creatorAdminUserId);
            if (($invitee['participant_type'] ?? '') === 'customer' && !empty($invitee['customer_id'])) {
                chat_log_customer_activity(
                    $db,
                    (int)$invitee['customer_id'],
                    $isDirectConversation ? 'direct_chat_invited' : 'group_chat_invited',
                    $isDirectConversation
                        ? $creatorLabel . ' sent you a direct conversation invite.'
                        : 'You were invited to group chat "' . $groupName . '" by ' . $creatorLabel . '.',
                    $creatorAdminUserId
                );
                chat_queue_group_invitation_email(
                    $db,
                    $invitee,
                    $groupName,
                    $creatorLabel,
                    $settings
                );
            }
            $invitedCount++;
        }

        if ($creatorCustomerId > 0) {
            chat_log_customer_activity(
                $db,
                $creatorCustomerId,
                $isDirectConversation ? 'direct_chat_invited' : 'group_chat_created',
                $isDirectConversation
                    ? 'You sent a direct conversation invite to ' . trim((string)($directInvitee['display_name'] ?? $groupName)) . '.'
                    : 'You created group chat "' . $groupName . '" and invited ' . $invitedCount . ' participant(s).',
                $creatorAdminUserId
            );
        }

        chat_log_group_activity_for_customers(
            $db,
            $conversationId,
            'group_chat_activity',
            $isDirectConversation
                ? $creatorLabel . ' sent a direct conversation invite.'
                : $creatorLabel . ' created group chat "' . $groupName . '".',
            $creatorAdminUserId,
            $creatorCustomerId
        );

        return [
            'ok' => true,
            'conversation_id' => $conversationId,
            'title' => $groupName,
            'avatar_url' => chat_group_avatar_url($groupAvatarUrl),
            'retention_hours' => $normalizedRetentionHours,
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
                support_conversations.group_avatar_url,
                support_conversations.subject,
                support_conversations.is_direct_conversation,
                support_conversations.is_group_read_only,
                inviter_customers.email AS invited_by_customer_email,
                inviter_customers.public_handle AS invited_by_customer_handle,
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
               AND support_conversations.conversation_type IN ('group_chat', 'global_group')
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
                support_conversations.group_avatar_url,
                support_conversations.subject,
                support_conversations.is_direct_conversation,
                inviter_customers.email AS invited_by_customer_email,
                inviter_customers.public_handle AS invited_by_customer_handle,
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
               AND support_conversations.conversation_type IN ('group_chat', 'global_group')
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

        $conversation = $db->select_user("SELECT group_name, subject, is_direct_conversation FROM support_conversations WHERE id = {$conversationId} LIMIT 1");
        $title = chat_group_conversation_title((array)$conversation);
        $memberLabel = chat_group_participant_label($db, $member);
        $isDirectConversation = !empty($conversation['is_direct_conversation']);

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

        chat_insert_group_system_notice(
            $db,
            $conversationId,
            chat_group_invite_status_notice_text($memberLabel, $isDirectConversation, $status)
        );

        if ($status === 'accepted') {
            chat_log_group_activity_for_customers(
                $db,
                $conversationId,
                'group_chat_activity',
                $memberLabel . ' joined group chat "' . $title . '".',
                (int)($member['invited_by_admin_user_id'] ?? 0),
                (int)($member['customer_id'] ?? 0)
            );
        } else {
            chat_log_group_activity_for_customers(
                $db,
                $conversationId,
                'group_chat_activity',
                $memberLabel . ' rejected invitation to group chat "' . $title . '".',
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
        $settings = function_exists('app_fetch_settings') ? app_fetch_settings($db) : [];

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
        $creatorLabel = chat_group_creator_label($db, $conversation, $creatorType === 'admin' ? 'Admin' : 'User');
        $invitedCount = 0;
        $duplicateCount = 0;
        $seenKeys = [];

        foreach ($inviteEmails as $inviteEmail) {
            $invitee = chat_resolve_group_invitee_by_email($db, (string)$inviteEmail, $settings);
            if (!$invitee || empty($invitee['participant_key'])) {
                continue;
            }

            $participantKey = (string)$invitee['participant_key'];
            if (isset($seenKeys[$participantKey])) {
                continue;
            }
            $seenKeys[$participantKey] = true;

            $validation = chat_validate_group_invitee_email($db, (string)($invitee['email'] ?? ''), $creator, $conversationId, $settings);
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
                chat_queue_group_invitation_email(
                    $db,
                    $invitee,
                    $conversationTitle,
                    $creatorLabel,
                    $settings
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

        $isDirectConversation = !empty($conversation['is_direct_conversation']);

        if (
            !$isDirectConversation
            && (
                (!empty($member['customer_id']) && chat_group_can_customer_manage($conversation, (int)$member['customer_id']))
                || (!empty($member['admin_user_id']) && chat_group_can_admin_manage($conversation, (int)$member['admin_user_id']))
            )
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

        chat_insert_group_system_notice(
            $db,
            $conversationId,
            chat_group_leave_notice_text($memberLabel, $isDirectConversation)
        );

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

    function chat_remove_group_conversation_for_participant(
        Mysql_ks $db,
        int $conversationId,
        string $participantKey
    ): array {
        chat_ensure_group_chat_runtime($db);
        $member = chat_group_member_row($db, $conversationId, $participantKey);
        if (!$member || !in_array(trim((string)($member['invite_status'] ?? '')), ['accepted', 'pending'], true)) {
            return ['ok' => false, 'message' => 'Conversation not found.'];
        }

        $conversation = chat_group_conversation_row($db, $conversationId);
        if (!$conversation) {
            return ['ok' => false, 'message' => 'Conversation not found.'];
        }

        if (chat_is_global_group_conversation_type((string)($conversation['conversation_type'] ?? ''))) {
            return ['ok' => false, 'message' => 'Global chat cannot be removed from the inbox.'];
        }

        $currentTime = chat_current_datetime();
        $db->update_using_id(
            ['invite_status', 'responded_at', 'left_at'],
            ['removed', $currentTime, $currentTime],
            'support_conversation_members',
            (int)$member['id']
        );

        $title = chat_group_conversation_title((array)$conversation);
        if (!empty($member['customer_id'])) {
            chat_log_customer_activity(
                $db,
                (int)$member['customer_id'],
                'group_chat_removed_from_inbox',
                !empty($conversation['is_direct_conversation'])
                    ? 'You removed conversation "' . $title . '" from your inbox.'
                    : 'You removed group chat "' . $title . '" from your inbox.'
            );
        }

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
        $actorLabel = chat_group_creator_label($db, $conversation, $actorType === 'admin' ? 'Admin' : 'User');

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
            ['accepted']
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
                support_conversation_members.last_read_message_id,
                support_conversation_members.can_post
             FROM support_conversations
             INNER JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
             WHERE support_conversations.id = {$conversationId}
               AND support_conversations.conversation_type IN ('group_chat', 'global_group')
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status IN ('accepted', 'pending')
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
                support_conversation_members.last_read_message_id,
                support_conversation_members.can_post
             FROM support_conversations
             INNER JOIN support_conversation_members
                ON support_conversation_members.conversation_id = support_conversations.id
             WHERE support_conversations.id = {$conversationId}
               AND support_conversations.conversation_type IN ('group_chat', 'global_group')
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status IN ('accepted', 'pending')
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

    function chat_mark_group_read_for_actor(Mysql_ks $db, int $conversationId, array $actor): void
    {
        $actorType = trim((string)($actor['participant_type'] ?? ''));
        if ($actorType === 'customer') {
            $customerId = (int)($actor['customer_id'] ?? 0);
            if ($customerId > 0) {
                chat_mark_group_read_for_customer($db, $customerId, $conversationId);
            }
            return;
        }

        if ($actorType === 'admin') {
            $adminUserId = (int)($actor['admin_user_id'] ?? 0);
            if ($adminUserId > 0) {
                chat_mark_group_read_for_admin($db, $adminUserId, $conversationId);
            }
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
                support_conversations.conversation_type,
                support_conversations.group_name,
                support_conversations.group_avatar_url,
                support_conversations.subject,
                support_conversations.is_group_read_only,
                support_conversations.is_direct_conversation,
                support_conversations.group_created_by_customer_id,
                support_conversations.group_created_by_admin_user_id,
                support_conversations.updated_at,
                support_conversations.created_at,
                support_conversation_members.last_read_message_id,
                (
                    SELECT support_messages.message_body
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.created_at DESC, support_messages.id DESC
                    LIMIT 1
                ) AS last_message_body,
                (
                    SELECT support_messages.attachment_path
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.created_at DESC, support_messages.id DESC
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
             WHERE support_conversations.conversation_type IN ('group_chat', 'global_group')
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status IN ('accepted', 'pending')
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
                support_conversations.conversation_type,
                support_conversations.customer_id,
                support_conversations.group_name,
                support_conversations.group_avatar_url,
                support_conversations.subject,
                support_conversations.status,
                support_conversations.is_group_read_only,
                support_conversations.is_direct_conversation,
                support_conversations.updated_at,
                support_conversations.created_at,
                support_conversation_members.last_read_message_id,
                (
                    SELECT support_messages.message_body
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.created_at DESC, support_messages.id DESC
                    LIMIT 1
                ) AS last_message_body,
                (
                    SELECT support_messages.attachment_path
                    FROM support_messages
                    WHERE support_messages.conversation_id = support_conversations.id
                    ORDER BY support_messages.created_at DESC, support_messages.id DESC
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
             WHERE support_conversations.conversation_type IN ('group_chat', 'global_group')
               AND support_conversation_members.participant_key = '{$participantKey}'
               AND support_conversation_members.invite_status = 'accepted'
             ORDER BY COALESCE(support_conversations.updated_at, support_conversations.created_at) DESC, support_conversations.id DESC"
        );
    }

    function chat_customer_message_page_size(): int
    {
        return 10;
    }

    function chat_customer_normalize_message_limit($value): int
    {
        $defaultLimit = chat_customer_message_page_size();
        $limit = (int)$value;
        if ($limit <= 0) {
            return $defaultLimit;
        }

        if ($limit < $defaultLimit) {
            return $defaultLimit;
        }

        if ($limit > 200) {
            return 200;
        }

        return $limit;
    }

    function chat_group_message_count(Mysql_ks $db, int $conversationId): int
    {
        chat_ensure_group_chat_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return 0;
        }

        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM support_messages
             WHERE conversation_id = {$conversationId}"
        );

        return (int)($row['total'] ?? 0);
    }

    function chat_group_messages_query(Mysql_ks $db, int $conversationId, int $messageLimit = 0): array
    {
        chat_ensure_group_chat_runtime($db);
        chat_ensure_message_interactions_runtime($db);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return [];
        }

        $safeLimit = chat_customer_normalize_message_limit($messageLimit);
        $baseQuery = "SELECT
                support_messages.id,
                support_messages.sender_type,
                support_messages.customer_id,
                support_messages.admin_user_id,
                support_messages.message_body AS tresc,
                support_messages.attachment_path,
                support_messages.reply_to_message_id,
                DATE_FORMAT(support_messages.created_at, '%Y-%m-%d %H:%i:%s') AS data,
                support_messages.is_read AS status,
                NULLIF(TRIM(admin_users.public_handle), '') AS admin_public_handle,
                admin_users.login_name AS admin_login_name,
                customers.email AS customer_email,
                NULLIF(TRIM(customers.public_handle), '') AS customer_display_name
             FROM support_messages
             LEFT JOIN admin_users ON admin_users.id = support_messages.admin_user_id
             LEFT JOIN customers ON customers.id = support_messages.customer_id
             WHERE support_messages.conversation_id = {$conversationId}";

        $rows = $db->select_full_user(
            "SELECT *
             FROM (
                {$baseQuery}
                ORDER BY support_messages.created_at DESC, support_messages.id DESC
                LIMIT {$safeLimit}
             ) AS recent_messages
             ORDER BY data ASC, id ASC"
        );

        return $rows;
    }

    function chat_customer_conversation_list(Mysql_ks $db, array $customer, array $reseller = [], string $defaultSupportLabel = 'Support', array $settings = []): array
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
        $supportAvatarUrl = chat_support_avatar_url($db);
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
                        SELECT support_messages.attachment_path
                        FROM support_messages
                        WHERE support_messages.conversation_id = {$supportConversationId}
                        ORDER BY support_messages.id DESC
                        LIMIT 1
                    ) AS last_attachment_path,
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
            $supportPreview = chat_message_preview_text(
                (string)($supportMeta['last_message_body'] ?? ''),
                (string)($supportMeta['last_attachment_path'] ?? ''),
                'New image attachment'
            );
            $supportUnread = (int)($supportMeta['unread_count'] ?? 0);
            $supportUpdatedAt = (string)($supportMeta['updated_at'] ?? '');
        }

        $entries[] = [
            'id' => $supportConversationId,
            'type' => 'live_chat',
            'title' => 'Support',
            'subtitle' => 'Support',
            'unread_count' => $supportUnread,
            'preview' => $supportPreview,
            'updated_at' => $supportUpdatedAt,
            'is_group' => false,
            'is_read_only' => false,
            'can_leave' => false,
            'avatar_url' => $supportAvatarUrl,
            'avatar_text' => 'S',
            'avatar_theme' => 'theme-6',
            'presence' => chat_support_presence_payload($db),
        ];

        if (chat_customer_can_use_groups($customer, $settings)) {
            $seenDirectCounterpartIds = [];
            foreach (chat_customer_group_conversation_rows($db, $customerId) as $row) {
                $summary = chat_group_conversation_summary(
                    $db,
                    (int)($row['id'] ?? 0),
                    ['participant_type' => 'customer', 'customer_id' => $customerId, 'admin_user_id' => 0],
                    $row
                );
                $isDirectConversation = !empty($summary['is_direct']);
                $directTargetCustomerId = (int)($summary['direct_target_customer_id'] ?? 0);
                if ($isDirectConversation && $directTargetCustomerId > 0) {
                    if (isset($seenDirectCounterpartIds[$directTargetCustomerId])) {
                        continue;
                    }
                    $seenDirectCounterpartIds[$directTargetCustomerId] = true;
                }
                $entries[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'type' => (string)($row['conversation_type'] ?? 'group_chat'),
                    'title' => (string)($summary['title'] ?? chat_group_conversation_title($row)),
                    'subtitle' => (string)($summary['subtitle'] ?? 'Group'),
                    'unread_count' => (int)($row['unread_count'] ?? 0),
                    'preview' => chat_message_preview_text(
                        (string)($row['last_message_body'] ?? ''),
                        (string)($row['last_attachment_path'] ?? ''),
                        'New image attachment'
                    ),
                    'updated_at' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
                    'is_group' => true,
                    'is_read_only' => !empty($row['is_group_read_only']),
                    'can_leave' => !chat_is_global_group_conversation_type((string)($row['conversation_type'] ?? '')),
                    'is_owned' => (int)($row['group_created_by_customer_id'] ?? 0) === $customerId,
                    'is_direct' => !empty($summary['is_direct']),
                    'has_pending_invite' => !empty($summary['has_pending_invite']),
                    'is_global_group' => chat_is_global_group_conversation_type((string)($row['conversation_type'] ?? '')),
                    'avatar_url' => (string)($summary['avatar_url'] ?? ''),
                    'avatar_text' => (string)($summary['avatar_text'] ?? 'G'),
                    'avatar_theme' => (string)($summary['avatar_theme'] ?? 'theme-6'),
                    'presence' => is_array($summary['presence'] ?? null) ? $summary['presence'] : chat_presence_payload('offline'),
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

    function chat_customer_selected_conversation(Mysql_ks $db, array $customer, int $requestedConversationId, array $settings = []): array
    {
        chat_ensure_group_chat_runtime($db);
        $customerId = (int)($customer['id'] ?? 0);
        $conversationId = max(0, $requestedConversationId);
        $fullMessengerEnabled = chat_customer_can_use_groups($customer, $settings);
        if ($customerId <= 0) {
            return ['id' => 0, 'type' => 'live_chat', 'is_group' => false];
        }

        if ($conversationId > 0 && $fullMessengerEnabled) {
            $groupConversation = chat_group_accessible_for_customer($db, $customerId, $conversationId);
            if ($groupConversation) {
                if (!empty($groupConversation['is_direct_conversation'])) {
                    $summary = chat_group_conversation_summary(
                        $db,
                        (int)$groupConversation['id'],
                        ['participant_type' => 'customer', 'customer_id' => $customerId, 'admin_user_id' => 0],
                        $groupConversation
                    );
                    $directTargetCustomerId = (int)($summary['direct_target_customer_id'] ?? 0);
                    if ($directTargetCustomerId > 0) {
                        $canonicalConversation = chat_find_customer_direct_conversation_between($db, $customerId, $directTargetCustomerId);
                        if (is_array($canonicalConversation) && !empty($canonicalConversation['id'])) {
                            $groupConversation = chat_group_accessible_for_customer($db, $customerId, (int)$canonicalConversation['id']) ?: $groupConversation;
                        }
                    }
                }
                return [
                    'id' => (int)$groupConversation['id'],
                    'type' => (string)($groupConversation['conversation_type'] ?? 'group_chat'),
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

    function chat_messages_total_for_customer_conversation(
        Mysql_ks $db,
        array $customer,
        array $conversationState
    ): int {
        chat_ensure_group_chat_runtime($db);
        $customerId = (int)($customer['id'] ?? 0);
        if ($customerId <= 0) {
            return 0;
        }

        if (chat_is_group_like_conversation_type((string)($conversationState['type'] ?? '')) && !empty($conversationState['id'])) {
            return chat_group_message_count($db, (int)$conversationState['id']);
        }

        $conversationId = (int)($conversationState['id'] ?? 0);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return 0;
        }

        $row = $db->select_user(
            "SELECT COUNT(*) AS total
             FROM support_messages
             INNER JOIN support_conversations
                ON support_conversations.id = support_messages.conversation_id
             WHERE support_conversations.id = {$conversationId}
               AND support_conversations.customer_id = {$customerId}
               AND support_conversations.conversation_type = 'live_chat'"
        );

        return (int)($row['total'] ?? 0);
    }

    function chat_messages_for_customer_conversation(
        Mysql_ks $db,
        array $customer,
        array $conversationState,
        array $reseller = [],
        string $defaultSupportLabel = 'Support',
        int $messageLimit = 0
    ): array {
        chat_ensure_group_chat_runtime($db);
        $customerId = (int)($customer['id'] ?? 0);
        if ($customerId <= 0) {
            return [];
        }

        $safeLimit = chat_customer_normalize_message_limit($messageLimit);

        if (chat_is_group_like_conversation_type((string)($conversationState['type'] ?? '')) && !empty($conversationState['id'])) {
            return chat_enrich_support_message_rows(
                $db,
                chat_group_messages_query($db, (int)$conversationState['id'], $safeLimit),
                [
                    'participant_type' => 'customer',
                    'customer_id' => $customerId,
                    'admin_user_id' => 0,
                    'participant_key' => chat_participant_key_for_customer($customerId),
                ],
                $reseller,
                $defaultSupportLabel
            );
        }

        $conversationId = (int)($conversationState['id'] ?? 0);
        if ($conversationId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return [];
        }

        $baseQuery = "SELECT
                support_messages.id,
                support_conversations.customer_id AS user1,
                COALESCE(support_messages.admin_user_id, support_conversations.assigned_admin_id, 1) AS user2,
                support_conversations.conversation_type,
                support_messages.sender_type,
                support_messages.customer_id,
                support_messages.admin_user_id,
                support_messages.message_body AS tresc,
                support_messages.attachment_path,
                support_messages.reply_to_message_id,
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
               AND support_conversations.conversation_type = 'live_chat'";

        $rows = $db->select_full_user(
            "SELECT *
             FROM (
                {$baseQuery}
                ORDER BY support_messages.id DESC
                LIMIT {$safeLimit}
             ) AS recent_messages
             ORDER BY id ASC"
        );

        return chat_enrich_support_message_rows(
            $db,
            $rows,
            [
                'participant_type' => 'customer',
                'customer_id' => $customerId,
                'admin_user_id' => 0,
                'participant_key' => chat_participant_key_for_customer($customerId),
            ],
            $reseller,
            $defaultSupportLabel
        );
    }
}
