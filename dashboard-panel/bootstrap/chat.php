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
            '~(?:(https?://|www\.)[^\s<]+)~iu',
            static function (array $matches): string {
                $fullMatch = (string)$matches[0];
                $suffix = '';

                while ($fullMatch !== '' && preg_match('/[.,!?);:\]]$/', $fullMatch) === 1) {
                    $suffix = substr($fullMatch, -1) . $suffix;
                    $fullMatch = substr($fullMatch, 0, -1);
                }

                if ($fullMatch === '') {
                    return htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8');
                }

                $href = stripos($fullMatch, 'http') === 0 ? $fullMatch : ('https://' . $fullMatch);
                $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                $safeLabel = htmlspecialchars($fullMatch, ENT_QUOTES, 'UTF-8');
                $safeSuffix = htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8');

                return '<a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer">' . $safeLabel . '</a>' . $safeSuffix;
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
        if (!preg_match('~(?:(?:https?://)|(?:www\.))[^\s<]+~iu', $text, $matches)) {
            return '';
        }

        return chat_trim_detected_url((string)($matches[0] ?? ''));
    }
}

if (!function_exists('chat_normalize_preview_url')) {
    function chat_normalize_preview_url(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'www.') === 0) {
            $value = 'https://' . $value;
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

    function chat_preview_host_allowed(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return chat_public_ip_allowed($host);
        }

        $ips = [];
        if (function_exists('gethostbynamel')) {
            $ipv4 = gethostbynamel($host);
            if (is_array($ipv4)) {
                $ips = array_merge($ips, $ipv4);
            }
        }

        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            $ipv6Records = @dns_get_record($host, DNS_AAAA);
            if (is_array($ipv6Records)) {
                foreach ($ipv6Records as $record) {
                    if (!empty($record['ipv6'])) {
                        $ips[] = (string)$record['ipv6'];
                    }
                }
            }
        }

        if (!$ips) {
            return false;
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

        $html = null;
        $effectiveUrl = $normalizedUrl;
        $contentType = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($normalizedUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
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
            curl_close($ch);

            if (is_string($response) && $response !== '' && $httpCode > 0 && $httpCode < 400) {
                $html = $response;
            }
        }

        if (!is_string($html) || $html === '') {
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

        if ($contentType !== '' && stripos($contentType, 'text/html') === false && stripos($contentType, 'application/xhtml+xml') === false) {
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
            if ($createdAtTimestamp !== false && $createdAtTimestamp > $nowTimestamp) {
                continue;
            }

            if (isset($row['sender_type']) && $row['sender_type'] !== '') {
                $isCustomerMessage = (string)$row['sender_type'] === 'customer';
            } else {
                $isCustomerMessage = isset($row['user1']) && (int)$row['user1'] === $currentUserId;
            }
            $attachmentPath = chat_extract_attachment_path(
                isset($row['attachment_path']) ? (string)$row['attachment_path'] : '',
                isset($row['tresc']) ? (string)$row['tresc'] : ''
            );
            $createdAt = $createdAtRaw;
            $anchorLabel = chat_time_anchor_label($createdAt);
            $showAnchor = $anchorLabel !== '' && $anchorLabel !== $previousAnchor;
            $isUnread = !$isCustomerMessage && isset($row['status']) && (int)$row['status'] === 0;
            $deleteWindowRemaining = 0;
            if ($isCustomerMessage && $createdAtTimestamp !== false) {
                $deleteWindowRemaining = max(0, 10 - max(0, $nowTimestamp - $createdAtTimestamp));
            }

            $senderLabel = 'You';
            if (!$isCustomerMessage) {
                $senderLabel = chat_sender_display_name($row, $reseller, $defaultSupportLabel);
            }

            $normalized[] = [
                'id' => isset($row['id']) ? (int)$row['id'] : 0,
                'direction' => $isCustomerMessage ? 'sent' : 'received',
                'sender_label' => $senderLabel,
                'message_html' => chat_format_message_html((string)($row['tresc'] ?? '')),
                'attachment_path' => $attachmentPath,
                'created_label' => chat_format_timestamp($createdAt),
                'time_anchor_label' => $showAnchor ? $anchorLabel : '',
                'is_unread' => $isUnread,
                'can_delete' => $deleteWindowRemaining > 0,
                'delete_remaining_seconds' => $deleteWindowRemaining,
                'delete_until_timestamp' => $createdAtTimestamp !== false ? ($createdAtTimestamp + 10) : 0,
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
