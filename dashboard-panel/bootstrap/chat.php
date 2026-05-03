<?php

require_once __DIR__ . '/chat_groups.php';

if (!function_exists('chat_support_name')) {
    function chat_support_name(array $reseller): string
    {
        return 'Support';
    }
}

if (!function_exists('chat_normalize_public_handle')) {
    function chat_normalize_public_handle(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
        return trim($value, '-._');
    }
}

if (!function_exists('chat_ensure_message_interactions_runtime')) {
    function chat_ensure_message_interactions_runtime(Mysql_ks $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (schema_object_exists($db, 'support_messages') && !schema_column_exists($db, 'support_messages', 'reply_to_message_id')) {
            @$db->query(
                "ALTER TABLE support_messages
                 ADD COLUMN reply_to_message_id INT UNSIGNED DEFAULT NULL
                 AFTER attachment_path"
            );
            schema_forget_column_cache('support_messages', 'reply_to_message_id');
        }

        if (!schema_object_exists($db, 'support_message_reactions')) {
            @$db->query(
                "CREATE TABLE IF NOT EXISTS support_message_reactions (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    message_id INT UNSIGNED NOT NULL,
                    actor_key VARCHAR(64) NOT NULL,
                    actor_type VARCHAR(16) NOT NULL,
                    customer_id INT UNSIGNED DEFAULT NULL,
                    admin_user_id INT UNSIGNED DEFAULT NULL,
                    reaction_code VARCHAR(24) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_support_message_reactions_actor (message_id, actor_key),
                    KEY idx_support_message_reactions_message (message_id),
                    KEY idx_support_message_reactions_actor_type (actor_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            unset($GLOBALS['schema_object_exists_cache']['support_message_reactions']);
        }
    }
}

if (!function_exists('chat_message_reaction_catalog')) {
    function chat_message_reaction_catalog(): array
    {
        return [
            'thumbs_up' => '👍',
            'heart' => '❤️',
            'joy' => '😂',
            'wow' => '😮',
            'sad' => '😢',
        ];
    }
}

if (!function_exists('chat_message_reaction_emoji')) {
    function chat_message_reaction_emoji(string $code): string
    {
        $catalog = chat_message_reaction_catalog();
        return (string)($catalog[$code] ?? '');
    }
}

if (!function_exists('chat_message_preview_excerpt')) {
    function chat_message_preview_excerpt(string $messageBody = '', string $attachmentPath = '', int $limit = 90): string
    {
        $text = chat_message_preview_text($messageBody, $attachmentPath, 'Attachment');
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
            return rtrim((string)mb_substr($text, 0, max(1, $limit - 1))) . '…';
        }
        if (strlen($text) > $limit) {
            return rtrim(substr($text, 0, max(1, $limit - 1))) . '…';
        }
        return $text;
    }
}

if (!function_exists('chat_message_is_emoji_only')) {
    function chat_message_is_emoji_only(string $messageBody): bool
    {
        $text = html_entity_decode(strip_tags($messageBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        if (preg_match('/[\p{L}\p{N}]/u', $text)) {
            return false;
        }

        $compacted = preg_replace('/\s+/u', '', $text) ?? $text;
        if ($compacted === '') {
            return false;
        }

        $stripped = preg_replace('/(?:\p{Extended_Pictographic}|\p{Emoji_Modifier}|[\x{FE0E}\x{FE0F}\x{200D}\x{20E3}#*0-9])/u', '', $compacted);
        if ($stripped === null) {
            return false;
        }

        return $stripped === '' && preg_match('/(?:\p{Extended_Pictographic}|[#*0-9]\x{FE0F}?\x{20E3})/u', $compacted) === 1;
    }
}

if (!function_exists('chat_toggle_message_reaction')) {
    function chat_toggle_message_reaction(Mysql_ks $db, int $messageId, array $actor, string $reactionCode): array
    {
        chat_ensure_message_interactions_runtime($db);
        $messageId = max(0, $messageId);
        $reactionCode = trim($reactionCode);
        $actorType = trim((string)($actor['participant_type'] ?? ''));
        $actorKey = trim((string)($actor['participant_key'] ?? ''));
        if ($messageId <= 0 || $actorType === '' || $actorKey === '') {
            return ['ok' => false, 'message' => 'Invalid reaction request.'];
        }

        $catalog = chat_message_reaction_catalog();
        if (!isset($catalog[$reactionCode])) {
            return ['ok' => false, 'message' => 'Unsupported reaction.'];
        }

        $row = $db->select_user(
            "SELECT id, reaction_code
             FROM support_message_reactions
             WHERE message_id = {$messageId}
               AND actor_key = '" . $db->escape($actorKey) . "'
             LIMIT 1"
        );

        if (is_array($row) && !empty($row['id'])) {
            if ((string)($row['reaction_code'] ?? '') === $reactionCode) {
                $db->delete_using_id('support_message_reactions', (int)$row['id']);
                return ['ok' => true, 'removed' => true];
            }

            $updated = $db->update_using_id(
                ['reaction_code'],
                [$reactionCode],
                'support_message_reactions',
                (int)$row['id']
            );
            return ['ok' => (bool)$updated];
        }

        $inserted = $db->insert(
            ['message_id', 'actor_key', 'actor_type', 'customer_id', 'admin_user_id', 'reaction_code'],
            [
                $messageId,
                $actorKey,
                $actorType,
                !empty($actor['customer_id']) ? (int)$actor['customer_id'] : null,
                !empty($actor['admin_user_id']) ? (int)$actor['admin_user_id'] : null,
                $reactionCode
            ],
            'support_message_reactions'
        );

        return ['ok' => (bool)$inserted];
    }
}

if (!function_exists('chat_validate_reply_target')) {
    function chat_validate_reply_target(Mysql_ks $db, int $conversationId, int $replyToMessageId): ?int
    {
        chat_ensure_message_interactions_runtime($db);
        if ($conversationId <= 0 || $replyToMessageId <= 0 || !schema_object_exists($db, 'support_messages')) {
            return null;
        }

        $row = $db->select_user(
            "SELECT id
             FROM support_messages
             WHERE id = {$replyToMessageId}
               AND conversation_id = {$conversationId}
             LIMIT 1"
        );

        return is_array($row) && !empty($row['id']) ? (int)$row['id'] : null;
    }
}

if (!function_exists('chat_enrich_support_message_rows')) {
    function chat_enrich_support_message_rows(Mysql_ks $db, array $rows, array $actor, array $reseller = [], string $defaultSupportLabel = 'Support'): array
    {
        chat_ensure_message_interactions_runtime($db);
        if (!$rows) {
            return [];
        }

        $actorKey = trim((string)($actor['participant_key'] ?? ''));
        $messageIds = [];
        $replyIds = [];

        foreach ($rows as $row) {
            $messageId = (int)($row['id'] ?? 0);
            if ($messageId > 0) {
                $messageIds[$messageId] = $messageId;
            }
            $replyId = (int)($row['reply_to_message_id'] ?? 0);
            if ($replyId > 0) {
                $replyIds[$replyId] = $replyId;
            }
        }

        $replyMap = [];
        if ($replyIds) {
            $replyRows = $db->select_full_user(
                "SELECT
                    support_messages.id,
                    support_messages.sender_type,
                    support_messages.customer_id,
                    support_messages.admin_user_id,
                    support_messages.message_body AS tresc,
                    support_messages.attachment_path,
                    NULLIF(TRIM(admin_users.public_handle), '') AS admin_public_handle,
                    admin_users.login_name AS admin_login_name,
                    customers.email AS customer_email,
                    NULLIF(TRIM(customers.public_handle), '') AS customer_display_name
                 FROM support_messages
                 LEFT JOIN admin_users ON admin_users.id = support_messages.admin_user_id
                 LEFT JOIN customers ON customers.id = support_messages.customer_id
                 WHERE support_messages.id IN (" . implode(',', array_map('intval', $replyIds)) . ")"
            );
            foreach ($replyRows as $replyRow) {
                $replyId = (int)($replyRow['id'] ?? 0);
                if ($replyId <= 0) {
                    continue;
                }
                $replyMap[$replyId] = [
                    'sender_label' => chat_sender_display_name($replyRow, $reseller, $defaultSupportLabel),
                    'preview_text' => chat_message_preview_excerpt(
                        (string)($replyRow['tresc'] ?? ''),
                        chat_extract_attachment_path((string)($replyRow['attachment_path'] ?? ''), (string)($replyRow['tresc'] ?? ''))
                    ),
                ];
            }
        }

        $reactionBuckets = [];
        if ($messageIds && schema_object_exists($db, 'support_message_reactions')) {
            $reactionRows = $db->select_full_user(
                "SELECT message_id, reaction_code, COUNT(*) AS total
                 FROM support_message_reactions
                 WHERE message_id IN (" . implode(',', array_map('intval', $messageIds)) . ")
                 GROUP BY message_id, reaction_code
                 ORDER BY message_id ASC, reaction_code ASC"
            );
            foreach ($reactionRows as $reactionRow) {
                $messageId = (int)($reactionRow['message_id'] ?? 0);
                $reactionCode = trim((string)($reactionRow['reaction_code'] ?? ''));
                $emoji = chat_message_reaction_emoji($reactionCode);
                if ($messageId <= 0 || $reactionCode === '' || $emoji === '') {
                    continue;
                }
                $reactionBuckets[$messageId][$reactionCode] = [
                    'code' => $reactionCode,
                    'emoji' => $emoji,
                    'count' => (int)($reactionRow['total'] ?? 0),
                    'is_selected' => false,
                ];
            }

            if ($actorKey !== '') {
                $selectedRows = $db->select_full_user(
                    "SELECT message_id, reaction_code
                     FROM support_message_reactions
                     WHERE actor_key = '" . $db->escape($actorKey) . "'
                       AND message_id IN (" . implode(',', array_map('intval', $messageIds)) . ")"
                );
                foreach ($selectedRows as $selectedRow) {
                    $messageId = (int)($selectedRow['message_id'] ?? 0);
                    $reactionCode = trim((string)($selectedRow['reaction_code'] ?? ''));
                    if ($messageId <= 0 || $reactionCode === '' || empty($reactionBuckets[$messageId][$reactionCode])) {
                        continue;
                    }
                    $reactionBuckets[$messageId][$reactionCode]['is_selected'] = true;
                }
            }
        }

        foreach ($rows as $index => $row) {
            $messageId = (int)($row['id'] ?? 0);
            $replyId = (int)($row['reply_to_message_id'] ?? 0);
            $rows[$index]['reply_preview_sender'] = '';
            $rows[$index]['reply_preview_text'] = '';
            $rows[$index]['reply_target_exists'] = false;
            if ($replyId > 0 && isset($replyMap[$replyId])) {
                $rows[$index]['reply_preview_sender'] = (string)($replyMap[$replyId]['sender_label'] ?? '');
                $rows[$index]['reply_preview_text'] = (string)($replyMap[$replyId]['preview_text'] ?? '');
                $rows[$index]['reply_target_exists'] = true;
            }
            $rows[$index]['reactions'] = $messageId > 0 && !empty($reactionBuckets[$messageId])
                ? array_values($reactionBuckets[$messageId])
                : [];
        }

        return $rows;
    }
}

if (!function_exists('chat_default_support_label')) {
    function chat_default_support_label(Mysql_ks $db, array $reseller = []): string
    {
        if (schema_object_exists($db, 'admin_users')) {
            if (schema_column_exists($db, 'admin_users', 'public_handle')) {
                $row = $db->select_user(
                    "SELECT id, public_handle
                     FROM admin_users
                     WHERE status = 'active'
                     ORDER BY id ASC
                     LIMIT 1"
                );
                $handle = chat_normalize_public_handle((string)($row['public_handle'] ?? ''));
                if ($handle !== '') {
                    return $handle;
                }
                if (!empty($row['id'])) {
                    return 'support-' . (int)$row['id'];
                }
            } else {
                $row = $db->select_user(
                    "SELECT id
                     FROM admin_users
                     WHERE status = 'active'
                     ORDER BY id ASC
                     LIMIT 1"
                );
                if (!empty($row['id'])) {
                    return 'support-' . (int)$row['id'];
                }
            }
        }

        return chat_support_name($reseller);
    }
}

if (!function_exists('chat_extract_attachment_path')) {
    function chat_extract_attachment_path(?string $attachmentPath, string $messageBody): string
    {
        $attachmentPath = trim((string)$attachmentPath);
        if ($attachmentPath !== '') {
            return $attachmentPath;
        }

        if (preg_match('~(?:src|href)=["\']([^"\']+/uploads/chat/[^"\']+)["\']~i', $messageBody, $matches)) {
            return (string)$matches[1];
        }

        return '';
    }
}

if (!function_exists('chat_emoticon_map')) {
    function chat_emoticon_map(): array
    {
        return [
            ':-)' => '😊',
            ':)' => '😊',
            ':-(' => '😕',
            ':(' => '😕',
            ';-)' => '😉',
            ';)' => '😉',
            ':-D' => '😄',
            ':D' => '😄',
            ':-P' => '😛',
            ':-p' => '😛',
            ':P' => '😛',
            ':p' => '😛',
            ':-O' => '😮',
            ':-o' => '😮',
            ':O' => '😮',
            ':o' => '😮',
            '<3' => '❤️',
        ];
    }
}

if (!function_exists('chat_linkify_text')) {
    function chat_linkify_text(string $text): string
    {
        return preg_replace_callback(
            '~(^|[^\w@])((?:(?:https?://|www\.)[^\s<]+|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?:/[^\s<]*)?))~iu',
            static function (array $matches): string {
                $prefix = htmlspecialchars((string)($matches[1] ?? ''), ENT_QUOTES, 'UTF-8');
                $fullMatch = (string)($matches[2] ?? '');
                $suffix = '';

                while ($fullMatch !== '' && preg_match('/[.,!?);:\]]$/', $fullMatch) === 1) {
                    $suffix = substr($fullMatch, -1) . $suffix;
                    $fullMatch = substr($fullMatch, 0, -1);
                }

                if ($fullMatch === '') {
                    return $prefix . htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8');
                }

                $href = stripos($fullMatch, 'http') === 0 ? $fullMatch : ('https://' . $fullMatch);
                $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                $safeLabel = htmlspecialchars($fullMatch, ENT_QUOTES, 'UTF-8');
                $safeSuffix = htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8');

                return $prefix . '<a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer">' . $safeLabel . '</a>' . $safeSuffix;
            },
            $text
        ) ?? htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('chat_extract_first_url')) {
    function chat_trim_detected_url(string $value): string
    {
        $value = trim($value);

        while ($value !== '' && preg_match('/[.,!?);:\]]$/', $value) === 1) {
            $value = substr($value, 0, -1);
        }

        return trim($value);
    }

    function chat_extract_first_url(string $text): string
    {
        if (!preg_match('~(?:^|[^\w@])((?:(?:https?://|www\.)[^\s<]+|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?:/[^\s<]*)?))~iu', $text, $matches)) {
            return '';
        }

        return chat_trim_detected_url((string)($matches[1] ?? ''));
    }
}

if (!function_exists('chat_normalize_preview_url')) {
    function chat_preview_is_public_hostname_candidate(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || strlen($host) > 253) {
            return false;
        }

        return preg_match('~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$~i', $host) === 1;
    }

    function chat_normalize_preview_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'www.') === 0) {
            $value = 'https://' . $value;
        }

        if (!preg_match('~^https?://~i', $value)) {
            $hostCandidate = $value;
            $slashPosition = strpos($hostCandidate, '/');
            if ($slashPosition !== false) {
                $hostCandidate = substr($hostCandidate, 0, $slashPosition);
            }

            if (chat_preview_is_public_hostname_candidate($hostCandidate)) {
                $value = 'https://' . $value;
            }
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($value);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = trim((string)($parts['host'] ?? ''));
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            return '';
        }

        return $value;
    }
}

if (!function_exists('chat_public_ip_allowed')) {
    function chat_public_ip_allowed(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    function chat_preview_resolve_host_ips(string $host): array
    {
        $ips = [];
        $host = strtolower(trim($host));
        if ($host === '') {
            return $ips;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        if (function_exists('gethostbynamel')) {
            $ipv4 = @gethostbynamel($host);
            if (is_array($ipv4)) {
                $ips = array_merge($ips, $ipv4);
            }
        }

        if (function_exists('dns_get_record')) {
            $ipv4Records = @dns_get_record($host, defined('DNS_A') ? DNS_A : 0);
            if (is_array($ipv4Records)) {
                foreach ($ipv4Records as $record) {
                    if (!empty($record['ip'])) {
                        $ips[] = (string)$record['ip'];
                    }
                }
            }

            if (defined('DNS_AAAA')) {
                $ipv6Records = @dns_get_record($host, DNS_AAAA);
                if (is_array($ipv6Records)) {
                    foreach ($ipv6Records as $record) {
                        if (!empty($record['ipv6'])) {
                            $ips[] = (string)$record['ipv6'];
                        }
                    }
                }
            }
        }

        $ips = array_values(array_unique(array_filter(array_map('trim', $ips))));
        return $ips;
    }

    function chat_preview_host_allowed(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return chat_public_ip_allowed($host);
        }

        $ips = chat_preview_resolve_host_ips($host);
        if (!$ips) {
            return chat_preview_is_public_hostname_candidate($host);
        }

        foreach ($ips as $ip) {
            if (!chat_public_ip_allowed((string)$ip)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('chat_preview_document_fetch')) {
    function chat_preview_looks_like_html(string $html): bool
    {
        return preg_match('~<(?:!doctype|html|head|body|meta|title)\b~i', $html) === 1;
    }

    function chat_preview_document_fetch(string $url): ?array
    {
        $normalizedUrl = chat_normalize_preview_url($url);
        if ($normalizedUrl === '') {
            return null;
        }

        $parts = parse_url($normalizedUrl);
        $host = trim((string)($parts['host'] ?? ''));
        if (!chat_preview_host_allowed($host)) {
            return null;
        }
        $resolvedHostIps = chat_preview_resolve_host_ips($host);

        $html = null;
        $effectiveUrl = $normalizedUrl;
        $contentType = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($normalizedUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_USERAGENT => 'Reseller/1.0',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $normalizedUrl;
            $primaryIp = trim((string)curl_getinfo($ch, CURLINFO_PRIMARY_IP));
            curl_close($ch);

            if ($primaryIp !== '' && !chat_public_ip_allowed($primaryIp)) {
                return null;
            }

            if (is_string($response) && $response !== '' && $httpCode > 0 && $httpCode < 400) {
                $html = $response;
            }
        }

        if ((!is_string($html) || $html === '') && $resolvedHostIps) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 6,
                    'follow_location' => 0,
                    'header' => "Accept: text/html,application/xhtml+xml\r\nUser-Agent: Reseller/1.0\r\n",
                ],
            ]);
            $response = @file_get_contents($normalizedUrl, false, $context);
            if (is_string($response) && $response !== '') {
                $html = $response;
            }
        }

        if (!is_string($html) || $html === '') {
            return null;
        }

        if (
            $contentType !== ''
            && stripos($contentType, 'text/html') === false
            && stripos($contentType, 'application/xhtml+xml') === false
            && !chat_preview_looks_like_html($html)
        ) {
            return null;
        }

        $effectiveUrl = chat_normalize_preview_url($effectiveUrl);
        if ($effectiveUrl === '') {
            return null;
        }

        $effectiveParts = parse_url($effectiveUrl);
        $effectiveHost = trim((string)($effectiveParts['host'] ?? ''));
        if (!chat_preview_host_allowed($effectiveHost)) {
            return null;
        }

        return [
            'url' => $effectiveUrl,
            'html' => substr($html, 0, 262144),
        ];
    }
}

if (!function_exists('chat_preview_normalize_text')) {
    function chat_preview_normalize_text(string $value, int $maxLength = 0): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);
        if ($value === '' || $maxLength <= 0) {
            return $value;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) > $maxLength) {
                $value = rtrim(mb_substr($value, 0, max(1, $maxLength - 1))) . '…';
            }
            return $value;
        }

        if (strlen($value) > $maxLength) {
            $value = rtrim(substr($value, 0, max(1, $maxLength - 3))) . '...';
        }

        return $value;
    }

    function chat_preview_extract_meta_map(string $html): array
    {
        $meta = [];
        $title = '';

        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            $previousState = libxml_use_internal_errors(true);
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            if ($loaded) {
                $titleNodes = $dom->getElementsByTagName('title');
                if ($titleNodes->length > 0) {
                    $title = chat_preview_normalize_text((string)$titleNodes->item(0)->textContent, 160);
                }

                $metaNodes = $dom->getElementsByTagName('meta');
                foreach ($metaNodes as $metaNode) {
                    if (!($metaNode instanceof DOMElement)) {
                        continue;
                    }

                    $name = strtolower(trim((string)$metaNode->getAttribute('name')));
                    $property = strtolower(trim((string)$metaNode->getAttribute('property')));
                    $key = $property !== '' ? $property : $name;
                    $content = trim((string)$metaNode->getAttribute('content'));
                    if ($key !== '' && $content !== '') {
                        $meta[$key] = $content;
                    }
                }
            }
        }

        if ($title === '' && preg_match('~<title[^>]*>(.*?)</title>~is', $html, $matches)) {
            $title = chat_preview_normalize_text((string)($matches[1] ?? ''), 160);
        }

        if (!$meta && preg_match_all('~<meta\s+[^>]*?(?:property|name)\s*=\s*["\']([^"\']+)["\'][^>]*?content\s*=\s*["\']([^"\']*)["\'][^>]*>~is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $metaMatch) {
                $meta[strtolower(trim((string)($metaMatch[1] ?? '')))] = (string)($metaMatch[2] ?? '');
            }
        }

        return [
            'title' => $title,
            'meta' => $meta,
        ];
    }
}

if (!function_exists('chat_preview_resolve_url')) {
    function chat_preview_resolve_url(string $baseUrl, string $candidate): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }

        if (stripos($candidate, 'http://') === 0 || stripos($candidate, 'https://') === 0) {
            return $candidate;
        }

        if (strpos($candidate, '//') === 0) {
            $baseParts = parse_url($baseUrl);
            $scheme = strtolower((string)($baseParts['scheme'] ?? 'https'));
            return $scheme . ':' . $candidate;
        }

        $baseParts = parse_url($baseUrl);
        $scheme = strtolower((string)($baseParts['scheme'] ?? 'https'));
        $host = trim((string)($baseParts['host'] ?? ''));
        if ($host === '') {
            return '';
        }

        $port = isset($baseParts['port']) ? ':' . (int)$baseParts['port'] : '';
        if (strpos($candidate, '/') === 0) {
            return $scheme . '://' . $host . $port . $candidate;
        }

        $path = (string)($baseParts['path'] ?? '/');
        $directory = preg_replace('~/[^/]*$~', '/', $path) ?? '/';
        return $scheme . '://' . $host . $port . $directory . $candidate;
    }

    function chat_preview_compact_url(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower(trim((string)($parts['host'] ?? '')));
        $path = trim((string)($parts['path'] ?? ''));
        $display = $host;
        if ($path !== '' && $path !== '/') {
            $display .= $path;
        }

        return chat_preview_normalize_text($display, 90);
    }
}

if (!function_exists('chat_fetch_link_preview')) {
    function chat_fetch_link_preview(string $url): ?array
    {
        $document = chat_preview_document_fetch($url);
        if (!is_array($document) || empty($document['url']) || empty($document['html'])) {
            return null;
        }

        $finalUrl = (string)$document['url'];
        $html = (string)$document['html'];
        $metaData = chat_preview_extract_meta_map($html);
        $meta = isset($metaData['meta']) && is_array($metaData['meta']) ? $metaData['meta'] : [];
        $parts = parse_url($finalUrl);
        $host = strtolower(trim((string)($parts['host'] ?? '')));

        $siteName = chat_preview_normalize_text((string)($meta['og:site_name'] ?? $meta['twitter:site'] ?? $host), 80);
        $title = chat_preview_normalize_text((string)($meta['og:title'] ?? $meta['twitter:title'] ?? $metaData['title'] ?? ''), 160);
        $description = chat_preview_normalize_text((string)($meta['og:description'] ?? $meta['twitter:description'] ?? $meta['description'] ?? ''), 220);
        $imageUrl = chat_preview_resolve_url($finalUrl, (string)($meta['og:image'] ?? $meta['twitter:image'] ?? ''));
        $imageUrl = chat_normalize_preview_url($imageUrl);

        if ($imageUrl !== '') {
            $imageParts = parse_url($imageUrl);
            $imageHost = trim((string)($imageParts['host'] ?? ''));
            if (!chat_preview_host_allowed($imageHost)) {
                $imageUrl = '';
            }
        }

        if ($title === '' && $description === '' && $siteName === '') {
            return null;
        }

        return [
            'url' => $finalUrl,
            'host' => $host,
            'display_url' => chat_preview_compact_url($finalUrl),
            'site_name' => $siteName,
            'title' => $title !== '' ? $title : $siteName,
            'description' => $description,
            'image_url' => $imageUrl,
        ];
    }

    function chat_build_link_preview_message(string $messageBody, array $preview, string $localeCode = 'en'): string
    {
        if (!function_exists('app_chat_card_encode')) {
            return $messageBody;
        }

        $payload = [
            'kind' => 'link_preview',
            'locale_code' => trim($localeCode) !== '' ? trim($localeCode) : 'en',
            'text' => trim($messageBody),
            'url' => trim((string)($preview['url'] ?? '')),
            'display_url' => trim((string)($preview['display_url'] ?? '')),
            'host' => trim((string)($preview['host'] ?? '')),
            'site_name' => trim((string)($preview['site_name'] ?? '')),
            'title' => trim((string)($preview['title'] ?? '')),
            'description' => trim((string)($preview['description'] ?? '')),
            'image_url' => trim((string)($preview['image_url'] ?? '')),
        ];

        return app_chat_card_encode($payload);
    }

    function chat_prepare_message_with_link_preview(string $messageBody, string $localeCode = 'en', bool $allowPreview = true, string $requestedPreviewUrl = ''): string
    {
        $messageBody = trim($messageBody);
        if ($messageBody === '' || !$allowPreview) {
            return $messageBody;
        }

        $detectedUrl = chat_extract_first_url($messageBody);
        if ($detectedUrl === '') {
            return $messageBody;
        }

        $detectedUrl = chat_normalize_preview_url($detectedUrl);
        $requestedPreviewUrl = chat_normalize_preview_url($requestedPreviewUrl);
        if ($detectedUrl === '' || ($requestedPreviewUrl !== '' && $requestedPreviewUrl !== $detectedUrl)) {
            return $messageBody;
        }

        $preview = chat_fetch_link_preview($detectedUrl);
        if (!is_array($preview)) {
            return $messageBody;
        }

        return chat_build_link_preview_message($messageBody, $preview, $localeCode);
    }
}

if (!function_exists('chat_format_message_html')) {
    function chat_system_notice_text(string $messageBody): string
    {
        if (strpos($messageBody, '[system_notice]') !== 0) {
            return '';
        }

        return trim((string)substr($messageBody, 15));
    }

    function chat_payment_card_html(array $payload): string
    {
        if (($payload['kind'] ?? '') !== 'payment_request') {
            return '';
        }

        $title = htmlspecialchars(trim((string)($payload['title'] ?? 'Payment request')), ENT_QUOTES, 'UTF-8');
        $logoUrl = trim((string)($payload['logo_url'] ?? ''));
        $buttonUrl = trim((string)($payload['button_url'] ?? ''));
        $buttonLabel = htmlspecialchars(trim((string)($payload['button_label'] ?? '')), ENT_QUOTES, 'UTF-8');
        $buttonHint = htmlspecialchars(trim((string)($payload['button_hint'] ?? '')), ENT_QUOTES, 'UTF-8');
        $stepText = htmlspecialchars(trim((string)($payload['step_text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $stepArrowText = htmlspecialchars(trim((string)($payload['step_arrow_text'] ?? '')), ENT_QUOTES, 'UTF-8');
        $note = htmlspecialchars(trim((string)($payload['note'] ?? '')), ENT_QUOTES, 'UTF-8');
        $html = '<div class="chat-payment-card">';
        $html .= '<div class="chat-payment-card__title-row">';
        $html .= '<span class="chat-payment-card__title">' . $title . '</span>';
        if ($logoUrl !== '') {
            $safeLogoUrl = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
            $html .= '<img src="' . $safeLogoUrl . '" alt="" class="chat-payment-card__logo">';
        }
        $html .= '</div>';

        if ($buttonUrl !== '' && $buttonLabel !== '') {
            $safeButtonUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');
            $html .= '<div class="chat-payment-card__button-wrap">';
            $html .= '<a href="' . $safeButtonUrl . '" class="btn btn-dark btn-block strong chat-payment-card__button" style="color: #fff !important; text-decoration: none !important;">';
            $html .= $buttonLabel . ' <i class="fa fa-angle-double-right" aria-hidden="true"></i>';
            $html .= '</a>';
            if ($buttonHint !== '') {
                $html .= '<span class="chat-payment-card__hint">' . $buttonHint . '</span>';
            }
            $html .= '</div>';
        }

        if ($stepText !== '' || $stepArrowText !== '') {
            $html .= '<div class="chat-payment-card__steps">';
            if ($stepText !== '') {
                $html .= '<span class="chat-payment-card__steps-label">' . $stepText . '</span>';
            }
            if ($stepArrowText !== '') {
                $html .= ' → ';
                $html .= '<span class="chat-payment-card__steps-label">' . $stepArrowText . '</span>';
            }
            $html .= '</div>';
        }

        $badges = isset($payload['badges']) && is_array($payload['badges']) ? $payload['badges'] : [];
        if ($badges) {
            $html .= '<div class="chat-payment-card__badges">';
            foreach ($badges as $badge) {
                $badgeText = trim((string)$badge);
                if ($badgeText === '') {
                    continue;
                }
                $html .= '<span class="btn btn-sm btn-danger strong chat-payment-card__badge">' . htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $html .= '</div>';
        }

        $fields = isset($payload['fields']) && is_array($payload['fields']) ? $payload['fields'] : [];
        if ($fields) {
            $html .= '<div class="chat-payment-card__fields">';
            foreach ($fields as $field) {
                $label = htmlspecialchars(trim((string)($field['label'] ?? '')), ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars(trim((string)($field['value'] ?? '')), ENT_QUOTES, 'UTF-8');
                if ($label === '' || $value === '') {
                    continue;
                }
                $tone = trim((string)($field['tone'] ?? ''));
                $valueClass = 'chat-payment-card__value';
                if ($tone !== '') {
                    $valueClass .= ' chat-payment-card__value--' . preg_replace('/[^a-z0-9_-]/i', '', $tone);
                }
                $html .= '<div class="chat-payment-card__field">';
                $html .= '<span class="chat-payment-card__label">' . $label . ':</span>';
                $html .= '<br>';
                $html .= '<span class="' . htmlspecialchars($valueClass, ENT_QUOTES, 'UTF-8') . '">' . $value . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        if ($note !== '') {
            $html .= '<div class="chat-payment-card__note">' . $note . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    function chat_link_preview_card_html(array $payload): string
    {
        if (($payload['kind'] ?? '') !== 'link_preview') {
            return '';
        }

        $text = trim((string)($payload['text'] ?? ''));
        $url = chat_normalize_preview_url((string)($payload['url'] ?? ''));
        $displayUrl = htmlspecialchars(trim((string)($payload['display_url'] ?? '')), ENT_QUOTES, 'UTF-8');
        $siteName = htmlspecialchars(trim((string)($payload['site_name'] ?? '')), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars(trim((string)($payload['title'] ?? '')), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim((string)($payload['description'] ?? '')), ENT_QUOTES, 'UTF-8');
        $imageUrl = chat_normalize_preview_url((string)($payload['image_url'] ?? ''));
        $html = '';

        if ($text !== '') {
            $plainText = strtr($text, chat_emoticon_map());
            $html .= '<div class="chat-link-preview__message">' . nl2br(chat_linkify_text($plainText), false) . '</div>';
        }

        if ($url === '') {
            return $html;
        }

        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $html .= '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" class="chat-link-preview">';
        if ($imageUrl !== '') {
            $safeImageUrl = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
            $html .= '<span class="chat-link-preview__media"><img src="' . $safeImageUrl . '" alt="" loading="lazy"></span>';
        }
        $html .= '<span class="chat-link-preview__content">';
        if ($siteName !== '') {
            $html .= '<span class="chat-link-preview__site">' . $siteName . '</span>';
        }
        if ($title !== '') {
            $html .= '<span class="chat-link-preview__title">' . $title . '</span>';
        }
        if ($description !== '') {
            $html .= '<span class="chat-link-preview__description">' . $description . '</span>';
        }
        if ($displayUrl !== '') {
            $html .= '<span class="chat-link-preview__url">' . $displayUrl . '</span>';
        }
        $html .= '</span>';
        $html .= '</a>';

        return $html;
    }

    function chat_format_message_html(string $messageBody): string
    {
        $systemNoticeText = chat_system_notice_text($messageBody);
        if ($systemNoticeText !== '') {
            return '<span class="messenger-system-note__text">' . chat_linkify_text($systemNoticeText) . '</span>';
        }

        $cardPayload = function_exists('app_chat_card_decode') ? app_chat_card_decode($messageBody) : null;
        if (is_array($cardPayload)) {
            $cardHtml = chat_payment_card_html($cardPayload);
            if ($cardHtml !== '') {
                return $cardHtml;
            }

            $cardHtml = chat_link_preview_card_html($cardPayload);
            if ($cardHtml !== '') {
                return $cardHtml;
            }
        }

        $plainText = trim(html_entity_decode(strip_tags($messageBody), ENT_QUOTES, 'UTF-8'));
        if ($plainText === '') {
            return '';
        }

        $plainText = strtr($plainText, chat_emoticon_map());
        return nl2br(chat_linkify_text($plainText), false);
    }
}

if (!function_exists('chat_format_timestamp')) {
    function chat_format_timestamp(string $timestamp): string
    {
        $time = strtotime($timestamp);
        if (!$time) {
            return trim($timestamp);
        }

        return date('Y-m-d H:i', $time);
    }
}

if (!function_exists('chat_time_anchor_label')) {
    function chat_time_anchor_label(string $timestamp): string
    {
        $time = strtotime($timestamp);
        if (!$time) {
            return '';
        }

        $isToday = date('Y-m-d', $time) === date('Y-m-d');
        return $isToday ? date('H:i', $time) : date('d.m', $time);
    }
}

if (!function_exists('chat_retention_days')) {
    function chat_retention_days(array $settings): int
    {
        $days = isset($settings['support_chat_retention_days']) ? (int)$settings['support_chat_retention_days'] : 7;
        return max(1, min(30, $days));
    }
}

if (!function_exists('chat_purge_expired_messages')) {
    function chat_purge_expired_messages(Mysql_ks $db, int $retentionDays): void
    {
        if ($retentionDays < 1) {
            $retentionDays = 1;
        }

        if (function_exists('app_prune_support_chat_messages')) {
            app_prune_support_chat_messages($db);
        }

        if (function_exists('chat_prune_group_chat_messages')) {
            chat_prune_group_chat_messages($db);
        }

        if (schema_object_exists($db, 'produkty_chat')) {
            $db->query(
                "DELETE FROM produkty_chat
                 WHERE data < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)"
            );
        }
    }
}

if (!function_exists('chat_message_preview_text')) {
    function chat_message_preview_text(string $messageBody = '', string $attachmentPath = '', string $defaultAttachmentLabel = 'Image attachment'): string
    {
        $messageBody = trim($messageBody);
        $attachmentPath = trim($attachmentPath);
        $payload = function_exists('app_chat_card_decode') ? app_chat_card_decode($messageBody) : null;

        if (is_array($payload)) {
            $kind = trim((string)($payload['kind'] ?? ''));
            if ($kind === 'payment_request') {
                $title = trim((string)($payload['title'] ?? ''));
                return $title !== '' ? $title : 'Payment request';
            }

            if ($kind === 'link_preview') {
                $text = trim((string)($payload['text'] ?? ''));
                $title = trim((string)($payload['title'] ?? ''));
                if ($text !== '') {
                    return $text;
                }
                if ($title !== '') {
                    return $title;
                }
                return 'Shared link';
            }
        }

        $systemNoticeText = chat_system_notice_text($messageBody);
        if ($systemNoticeText !== '') {
            return chat_preview_normalize_text($systemNoticeText, 140);
        }

        if ($messageBody !== '') {
            return chat_preview_normalize_text($messageBody, 140);
        }

        if ($attachmentPath !== '') {
            return $defaultAttachmentLabel;
        }

        return '';
    }
}

if (!function_exists('chat_normalize_messages')) {
    function chat_normalize_messages(array $messages, int $currentUserId, array $reseller, string $defaultSupportLabel = 'Support'): array
    {
        $normalized = [];
        $previousAnchor = null;
        $nowTimestamp = time();

        foreach ($messages as $row) {
            $createdAtRaw = (string)($row['data'] ?? '');
            $createdAtTimestamp = strtotime($createdAtRaw);

            if (isset($row['sender_type']) && $row['sender_type'] !== '') {
                $isCustomerMessage = (string)$row['sender_type'] === 'customer'
                    && (int)($row['customer_id'] ?? 0) === $currentUserId;
            } else {
                $isCustomerMessage = isset($row['user1']) && (int)$row['user1'] === $currentUserId;
            }
            $attachmentPath = chat_extract_attachment_path(
                isset($row['attachment_path']) ? (string)$row['attachment_path'] : '',
                isset($row['tresc']) ? (string)$row['tresc'] : ''
            );
            $systemNoticeText = chat_system_notice_text((string)($row['tresc'] ?? ''));
            if ($attachmentPath !== '') {
                app_chat_attachment_absolute_path($attachmentPath, true);
            }
            $createdAt = $createdAtRaw;
            $anchorLabel = chat_time_anchor_label($createdAt);
            $showAnchor = $anchorLabel !== '' && $anchorLabel !== $previousAnchor;
            $isUnread = !$isCustomerMessage && isset($row['status']) && (int)$row['status'] === 0;
            $conversationType = trim((string)($row['conversation_type'] ?? ''));
            $deleteWindowRemaining = 0;
            if ($isCustomerMessage && $conversationType === 'live_chat') {
                $deleteWindowRemaining = 1;
            } elseif ($isCustomerMessage && $createdAtTimestamp !== false) {
                $deleteWindowRemaining = max(0, 10 - max(0, $nowTimestamp - $createdAtTimestamp));
            }

            $senderLabel = 'You';
            if ($systemNoticeText !== '') {
                $senderLabel = '';
            } elseif (!$isCustomerMessage) {
                $senderLabel = chat_sender_display_name($row, $reseller, $defaultSupportLabel);
            }

            $messageBody = (string)($row['tresc'] ?? '');

            $normalized[] = [
                'id' => isset($row['id']) ? (int)$row['id'] : 0,
                'direction' => $systemNoticeText !== '' ? 'system' : ($isCustomerMessage ? 'sent' : 'received'),
                'sender_is_admin' => isset($row['sender_type']) && (string)$row['sender_type'] === 'admin',
                'sender_label' => $senderLabel,
                'message_html' => chat_format_message_html($messageBody),
                'is_emoji_only' => $systemNoticeText === '' && chat_message_is_emoji_only($messageBody),
                'attachment_path' => $attachmentPath,
                'created_label' => chat_format_timestamp($createdAt),
                'time_anchor_label' => $showAnchor ? $anchorLabel : '',
                'is_unread' => $isUnread,
                'is_read_receipt' => $isCustomerMessage && isset($row['status']) && (int)$row['status'] !== 0,
                'can_delete' => $deleteWindowRemaining > 0,
                'delete_remaining_seconds' => $deleteWindowRemaining,
                'delete_until_timestamp' => $conversationType === 'live_chat' ? 0 : ($createdAtTimestamp !== false ? ($createdAtTimestamp + 10) : 0),
                'reply_to_message_id' => isset($row['reply_to_message_id']) ? (int)$row['reply_to_message_id'] : 0,
                'reply_preview_sender' => (string)($row['reply_preview_sender'] ?? ''),
                'reply_preview_text' => (string)($row['reply_preview_text'] ?? ''),
                'reply_target_exists' => !empty($row['reply_target_exists']),
                'reactions' => isset($row['reactions']) && is_array($row['reactions']) ? array_values($row['reactions']) : [],
                'can_interact' => $systemNoticeText === '',
            ];

            if ($anchorLabel !== '') {
                $previousAnchor = $anchorLabel;
            }
        }

        return $normalized;
    }
}

if (!function_exists('chat_page_plain_text')) {
    function chat_page_plain_text(string $html): string
    {
        $html = str_replace(["<br>", "<br/>", "<br />", "</p>", "</li>"], "\n", $html);
        $html = preg_replace('~<li[^>]*>~i', "- ", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES);
        $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('chat_faq_slug_candidates')) {
    function chat_faq_slug_candidates(int $faqNumber, string $localeCode): array
    {
        $localeCode = strtolower(trim($localeCode));
        $candidates = [];

        if ($localeCode !== '') {
            $candidates[] = 'faq-' . $faqNumber . '-' . $localeCode;
        }

        if ($localeCode !== 'en') {
            $candidates[] = 'faq-' . $faqNumber . '-en';
        }

        $candidates[] = 'faq-' . $faqNumber;

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('chat_system_faq_seed_rows')) {
    function chat_system_faq_seed_rows(): array
    {
        return [
            ['slug' => 'faq-1-en', 'locale_code' => 'en', 'title' => 'How do I pay with crypto?', 'body' => '<p>1. Open your unpaid order and choose <strong>Pay with crypto</strong>.</p><p>2. Select one of your assigned active wallets.</p><p>3. Send the exact amount shown in the panel.</p><p>4. Wait for manual confirmation from support.</p>'],
            ['slug' => 'faq-2-en', 'locale_code' => 'en', 'title' => 'How long does activation take?', 'body' => '<p>Most activations are completed shortly after payment confirmation.</p><p>If the order needs manual verification, support will update you in live chat.</p>'],
            ['slug' => 'faq-3-en', 'locale_code' => 'en', 'title' => 'How do I extend an active subscription?', 'body' => '<p>Open the order list and choose <strong>Extend</strong> for the active subscription.</p><p>You can then select another package period from the same provider if more options are available.</p>'],
            ['slug' => 'faq-4-en', 'locale_code' => 'en', 'title' => 'What should I send after a bank transfer?', 'body' => '<p>Please send your transfer confirmation to the support email address shown in the payment instructions.</p><p>You can also use live chat if you need faster help.</p>'],
            ['slug' => 'faq-5-en', 'locale_code' => 'en', 'title' => 'My stream is not working. What should I do?', 'body' => '<p>Restart the app or device first.</p><p>Then check whether your subscription is active and fully paid.</p><p>If the issue remains, contact support in live chat and include the device or app name.</p>'],
            ['slug' => 'faq-1-pl', 'locale_code' => 'pl', 'title' => 'Jak zapłacić kryptowalutą?', 'body' => '<p>1. Otwórz nieopłacone zamówienie i wybierz <strong>Pay with crypto</strong>.</p><p>2. Wybierz jeden z przypisanych aktywnych portfeli.</p><p>3. Wyślij dokładnie taką kwotę, jaka jest pokazana w panelu.</p><p>4. Poczekaj na ręczne potwierdzenie płatności przez support.</p>'],
            ['slug' => 'faq-2-pl', 'locale_code' => 'pl', 'title' => 'Jak długo trwa aktywacja?', 'body' => '<p>Większość aktywacji jest realizowana krótko po potwierdzeniu płatności.</p><p>Jeśli zamówienie wymaga ręcznej weryfikacji, support zaktualizuje status na live chacie.</p>'],
            ['slug' => 'faq-3-pl', 'locale_code' => 'pl', 'title' => 'Jak przedłużyć aktywną subskrypcję?', 'body' => '<p>Otwórz listę zamówień i wybierz <strong>Extend</strong> przy aktywnej subskrypcji.</p><p>Następnie możesz wybrać inny okres pakietu od tego samego providera, jeśli są dostępne inne opcje.</p>'],
            ['slug' => 'faq-4-pl', 'locale_code' => 'pl', 'title' => 'Co wysłać po przelewie bankowym?', 'body' => '<p>Wyślij potwierdzenie przelewu na adres supportu pokazany w instrukcjach płatności.</p><p>Jeśli potrzebujesz szybszej pomocy, możesz też użyć live chatu.</p>'],
            ['slug' => 'faq-5-pl', 'locale_code' => 'pl', 'title' => 'Stream nie działa. Co zrobić?', 'body' => '<p>Najpierw uruchom ponownie aplikację lub urządzenie.</p><p>Następnie sprawdź, czy subskrypcja jest aktywna i opłacona.</p><p>Jeśli problem nadal występuje, napisz do supportu na live chacie i podaj nazwę urządzenia lub aplikacji.</p>'],
        ];
    }
}

if (!function_exists('chat_ensure_system_faq_pages_runtime')) {
    function chat_ensure_system_faq_pages_runtime(Mysql_ks $db): void
    {
        static $done = false;
        if ($done || !schema_object_exists($db, 'static_pages')) {
            return;
        }

        $hasLocaleColumn = schema_column_exists($db, 'static_pages', 'locale_code');
        foreach (chat_system_faq_seed_rows() as $seedRow) {
            $safeSlug = $db->escape((string)$seedRow['slug']);
            $existing = $db->select_user(
                "SELECT id
                 FROM static_pages
                 WHERE slug = '{$safeSlug}'
                 LIMIT 1"
            );

            if (is_array($existing) && !empty($existing['id'])) {
                if ($hasLocaleColumn) {
                    $db->update_using_id(
                        ['locale_code'],
                        [(string)$seedRow['locale_code']],
                        'static_pages',
                        (int)$existing['id']
                    );
                }
                continue;
            }

            $columns = ['slug', 'title', 'body', 'page_type', 'is_system', 'is_active'];
            $values = [
                (string)$seedRow['slug'],
                (string)$seedRow['title'],
                (string)$seedRow['body'],
                'system',
                1,
                1,
            ];

            if ($hasLocaleColumn) {
                array_splice($columns, 3, 0, ['locale_code']);
                array_splice($values, 3, 0, [(string)$seedRow['locale_code']]);
            }

            $db->insert($columns, $values, 'static_pages');
        }

        $done = true;
    }
}

if (!function_exists('chat_load_faq_prompts')) {
    function chat_load_faq_prompts(Mysql_ks $db, string $localeCode, int $limit = 5): array
    {
        chat_ensure_system_faq_pages_runtime($db);
        $prompts = [];
        $safeLimit = max(1, min(5, $limit));
        $localeCode = strtolower(trim($localeCode));
        $hasLocaleColumn = schema_column_exists($db, 'static_pages', 'locale_code');

        for ($number = 1; $number <= $safeLimit; $number++) {
            $page = null;
            foreach (chat_faq_slug_candidates($number, $localeCode) as $slug) {
                $safeSlug = $db->escape($slug);
                $genericSlugFilter = '';
                if (
                    $hasLocaleColumn
                    && $localeCode !== ''
                    && preg_match('/^faq-\d+$/', $slug) === 1
                ) {
                    $safeLocaleCode = $db->escape($localeCode);
                    $genericSlugFilter = " AND locale_code = '{$safeLocaleCode}'";
                }
                $page = $db->select_user(
                    "SELECT id, slug, title, body
                     FROM static_pages
                     WHERE slug = '{$safeSlug}'
                       AND is_active = 1{$genericSlugFilter}
                     LIMIT 1"
                );
                if (is_array($page) && !empty($page['id'])) {
                    break;
                }
            }

            if (!is_array($page) || empty($page['id'])) {
                continue;
            }

            $title = trim(html_entity_decode(strip_tags((string)$page['title']), ENT_QUOTES));
            $answer = chat_page_plain_text((string)$page['body']);
            if ($title === '' || $answer === '') {
                continue;
            }

            $prompts[] = [
                'faq_key' => 'faq-' . $number,
                'page_id' => (int)$page['id'],
                'slug' => (string)$page['slug'],
                'title' => $title,
                'answer' => $answer,
            ];
        }

        return $prompts;
    }
}

if (!function_exists('chat_find_faq_prompt')) {
    function chat_find_faq_prompt(array $prompts, string $faqKey): ?array
    {
        foreach ($prompts as $prompt) {
            if ((string)($prompt['faq_key'] ?? '') === $faqKey) {
                return $prompt;
            }
        }

        return null;
    }
}
