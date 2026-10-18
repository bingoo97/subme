<?php
declare(strict_types=1);

function admin_provider_remote_text(string $key, string $locale = ''): string
{
    $pl = ($locale !== '' ? $locale : (function_exists('admin_current_locale') ? admin_current_locale() : 'pl')) === 'pl';
    $messages = [
        'session' => ['Sesja wygasła. Odśwież stronę i zaloguj się ponownie.', 'Session expired. Refresh the page and sign in again.'],
        'forbidden' => ['Brak uprawnień do tej operacji.', 'You do not have permission for this action.'],
        'captcha_rejected' => ['Kod CAPTCHA jest błędny lub wygasł. Uruchom nowy test.', 'The CAPTCHA code is incorrect or expired. Start a new test.'],
        'expired' => ['Próba wygasła. Uruchom nowy test.', 'The attempt expired. Start a new test.'],
        'rate_limited' => ['Zbyt wiele prób. Odczekaj chwilę i spróbuj ponownie.', 'Too many attempts. Wait a moment and try again.'],
        'captcha_required' => ['Wpisz kod z obrazka, aby dokończyć test.', 'Enter the image code to finish this single login attempt.'],
        'cancelled' => ['Test anulowany.', 'The test ended. The browser session was closed.'],
        'browser_unavailable' => ['Lokalny moduł przeglądarkowy nie jest dostępny. Uruchom usługę provider_browser.', 'The local browser module is unavailable. Start the provider_browser service.'],
        'saved' => ['Konfiguracja zapisana.', 'Configuration saved.'],
        'disabled' => ['Zdalne logowanie jest wyłączone w Settings → Funkcje.', 'Remote login is disabled in Settings → Features.'],
        'missing' => ['Najpierw zapisz konfigurację logowania providera.', 'Save the provider login configuration first.'],
        'changed_url' => ['URL providera zmienił się. Sprawdź i zapisz ponownie konfigurację logowania.', 'The provider URL changed. Review and save the login configuration again.'],
        'config' => ['Sprawdź dane i ustawienia logowania.', 'Check the configuration: fields, credentials and a success condition are required.'],
        'url' => ['Podaj publiczny adres HTTP lub HTTPS z poprawnym portem, bez danych logowania w URL.', 'Enter a public HTTP or HTTPS address with a valid port, without credentials in the URL.'],
        'destination' => ['Adres prowadzi do sieci lokalnej, prywatnej lub niedozwolonej.', 'The address points to a local, private or disallowed network.'],
        'origin' => ['Formularz lub przekierowanie prowadzi poza domenę panelu. Ten wariant wymaga osobnego adaptera.', 'The form or redirect leaves the panel origin. This requires a separate adapter.'],
        'dns' => ['Nie udało się potwierdzić publicznego adresu serwera providera.', 'Unable to verify the public address of the provider server.'],
        'connection' => ['Nie można połączyć się z panelem. Sprawdź adres, port i dostępność strony.', 'Cannot connect. Check the address, port and site availability.'],
        'limit' => ['Test przerwany — przekroczono limit czasu lub zapytań.', 'The time, size or redirect limit for this attempt was exceeded.'],
        'form' => ['Nie rozpoznano formularza. Sprawdź opcje zaawansowane.', 'A unique form with these fields was not found. Check name/id; the panel may require JavaScript.'],
        'post' => ['Obsługiwane są formularze logowania POST. Inny mechanizm wymaga osobnego adaptera.', 'Only POST login forms are supported. Other mechanisms require an adapter.'],
        'manual' => ['Panel wymaga ręcznej CAPTCHA lub 2FA. Zaloguj się w jego oknie.', 'The panel requires CAPTCHA or 2FA. Log in manually; the test did not send credentials.'],
        'success' => ['Logowanie działa. Sesja testowa została zamknięta.', 'Login works. The test session has been closed.'],
        'rejected' => ['Logowanie odrzucone. Sprawdź login i hasło.', 'Login rejected. Check your username and password.'],
        'unconfirmed' => ['Nie potwierdzono logowania. Sprawdź dane lub ustaw ręczny warunek sukcesu.', 'Login could not be confirmed reliably. Check credentials and CAPTCHA/2FA. If the panel has no logout control, configure a manual success condition in advanced settings.'],
        'baseline' => ['Warunek sukcesu pasuje już przed logowaniem. Zmień go w opcjach zaawansowanych.', 'The success condition already matches before login. Choose a marker available only after login.'],
        'busy' => ['Inny test już trwa. Poczekaj chwilę.', 'A test for this provider is already running. Wait for it to finish.'],
        'storage' => ['Nie udało się bezpiecznie zapisać lub odczytać konfiguracji.', 'Unable to securely store or read the configuration.'],
    ];
    return ($messages[$key] ?? $messages['config'])[$pl ? 0 : 1];
}

function admin_provider_remote_ensure_schema(Mysql_ks $db): void
{
    foreach (['provider_remote_login_enabled', 'provider_remote_subscriptions_enabled'] as $column) {
        if (!schema_column_exists($db, 'app_settings', $column)) {
            if (!$db->query("ALTER TABLE app_settings ADD COLUMN {$column} TINYINT(1) NOT NULL DEFAULT 0")) throw new RuntimeException('storage');
            schema_forget_column_cache('app_settings', $column);
        }
    }
    if (!$db->query("CREATE TABLE IF NOT EXISTS provider_remote_login_configs (
        provider_id INT UNSIGNED NOT NULL PRIMARY KEY,
        config_json TEXT NOT NULL,
        credentials_encrypted TEXT NOT NULL,
        CONSTRAINT fk_provider_remote_config FOREIGN KEY (provider_id) REFERENCES product_providers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) throw new RuntimeException('storage');
}

function admin_provider_remote_key(): string
{
    $env = (string)getenv('PROVIDER_REMOTE_SECRET_KEY');
    if ($env !== '') return hash('sha256', $env, true);
    $root = function_exists('app_secrets_root_path') ? app_secrets_root_path() : '';
    $path = ($root !== '' ? $root : dirname(__DIR__) . '/storage/provider-remote-private') . '/provider_remote_master.key';
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('storage');
    // Exclusive creation prevents parallel saves from replacing an existing key.
    $handle = @fopen($path, 'x+b');
    if ($handle) {
        if (!flock($handle, LOCK_EX)) { fclose($handle); throw new RuntimeException('storage'); }
        @chmod($path, 0600);
        $key = random_bytes(32);
        $written = fwrite($handle, bin2hex($key));
        fflush($handle); flock($handle, LOCK_UN); fclose($handle);
        if ($written !== 64) throw new RuntimeException('storage');
        return $key;
    }
    $handle = @fopen($path, 'rb');
    if (!$handle || !flock($handle, LOCK_SH)) { if ($handle) fclose($handle); throw new RuntimeException('storage'); }
    $value = stream_get_contents($handle, 65);
    flock($handle, LOCK_UN); fclose($handle);
    if (!preg_match('/^[a-f0-9]{64}$/D', $value)) throw new RuntimeException('storage');
    return hex2bin($value);
}

function admin_provider_remote_seal(array $credentials, int $providerId): string
{
    $key = admin_provider_remote_key();
    $nonce = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt(json_encode($credentials, JSON_THROW_ON_ERROR), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'provider:' . $providerId);
    if ($cipher === false) throw new RuntimeException('storage');
    return base64_encode($nonce . $tag . $cipher);
}

function admin_provider_remote_unseal(string $cipher, int $providerId): array
{
    $raw = base64_decode($cipher, true);
    if ($raw === false || strlen($raw) < 29) throw new RuntimeException('storage');
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', admin_provider_remote_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'provider:' . $providerId);
    if ($plain === false) throw new RuntimeException('storage');
    $decoded = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('storage');
    return $decoded;
}

function admin_provider_remote_url(string $url): string
{
    $p = parse_url($url);
    $scheme = strtolower((string)($p['scheme'] ?? ''));
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !is_array($p)
        || !in_array($scheme, ['http','https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass'])
        || isset($p['fragment']) || (isset($p['port']) && ($p['port'] < 1 || $p['port'] > 65535))) throw new RuntimeException('url');
    $host = strtolower($p['host']);
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)) throw new RuntimeException('destination');
    $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
    return $scheme . '://' . $host . ($port === ($scheme === 'https' ? 443 : 80) ? '' : ':' . $port) . (($p['path'] ?? '') ?: '/') . (isset($p['query']) ? '?' . $p['query'] : '');
}

function admin_provider_remote_origin(string $url): string
{
    $p = parse_url(admin_provider_remote_url($url));
    return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
}

function admin_provider_remote_resolve_url(string $base, string $relative): string
{
    $base = admin_provider_remote_url($base);
    if ($relative === '') return $base;
    if (str_starts_with($relative, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $relative)) $url = str_starts_with($relative, '//') ? parse_url($base, PHP_URL_SCHEME) . ':' . $relative : $relative;
    else {
        $p = parse_url($base); $prefix = admin_provider_remote_origin($base);
        if ($relative[0] === '?') $url = $prefix . ($p['path'] ?? '/') . $relative;
        else $url = $prefix . ($relative[0] === '/' ? '' : preg_replace('~/[^/]*$~', '/', $p['path'] ?? '/')) . $relative;
    }
    $url = admin_provider_remote_url($url);
    if (admin_provider_remote_origin($url) !== admin_provider_remote_origin($base)) throw new RuntimeException('origin');
    $p = parse_url($url); $parts = [];
    foreach (explode('/', $p['path'] ?? '/') as $part) {
        if ($part === '..') array_pop($parts);
        elseif ($part !== '.' && $part !== '') $parts[] = $part;
    }
    $path = '/' . implode('/', $parts);
    if (str_ends_with($p['path'] ?? '/', '/') && $path !== '/') $path .= '/';
    return admin_provider_remote_origin($url) . $path . (isset($p['query']) ? '?' . $p['query'] : '');
}

function admin_provider_remote_public_ip(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    if (str_contains($ip, ':')) {
        $bytes = inet_pton($ip);
        return $bytes !== false && (ord($bytes[0]) & 0xe0) === 0x20
            && !str_starts_with(strtolower($ip), '2002:') && !str_starts_with(strtolower($ip), '2001:db8:')
            && !(ord($bytes[0]) === 0x20 && ord($bytes[1]) === 0x01 && ord($bytes[2]) < 2);
    }
    $long = ip2long($ip);
    foreach ([['0.0.0.0',8],['100.64.0.0',10],['192.0.0.0',24],['192.0.2.0',24],['198.51.100.0',24],['203.0.113.0',24],['198.18.0.0',15],['224.0.0.0',4],['240.0.0.0',4]] as [$network,$bits]) {
        $mask = -1 << (32 - $bits);
        if (($long & $mask) === (ip2long($network) & $mask)) return false;
    }
    return true;
}

function admin_provider_remote_config(array $input, string $dashboardUrl): array
{
    $url = admin_provider_remote_url($dashboardUrl);
    $config = ['dashboard_hash' => hash('sha256', $dashboardUrl), 'login_path' => trim((string)($input['login_path'] ?? '')),
        'use_username' => !empty($input['use_username']), 'use_password' => !empty($input['use_password']),
        'captcha' => !empty($input['captcha']), 'two_factor' => !empty($input['two_factor']),
        'captcha_ocr' => !empty($input['captcha_ocr']), 'captcha_ocr_digits' => !empty($input['captcha_ocr_digits'])];
    admin_provider_remote_resolve_url($url, $config['login_path']);
    foreach(['subscription_list_path','subscription_renew_path'] as $field){
        $config[$field]=trim((string)($input[$field]??''));
        if(strlen($config[$field])>2048)throw new RuntimeException('config');
        if($config[$field]!=='')admin_provider_remote_resolve_url($url,$config[$field]);
    }
    $config['mode'] = (string)($input['mode'] ?? 'auto');
    if (!in_array($config['mode'], ['auto','html','browser'], true)) throw new RuntimeException('config');
    foreach (['captcha_input'=>'input[placeholder="Enter code"]','captcha_image'=>'img[alt="Captcha"]'] as $field=>$default) {
        $config[$field]=trim((string)($input[$field]??$default));
        if (strlen($config[$field])>256 || $config[$field]==='' || preg_match('/[\x00-\x1f]/',$config[$field])) throw new RuntimeException('config');
    }
    if (!$config['use_username'] && !$config['use_password']) throw new RuntimeException('config');
    foreach (['username','password','submit'] as $field) {
        $config[$field . '_by'] = (string)($input[$field . '_by'] ?? 'name');
        $config[$field . '_field'] = trim((string)($input[$field . '_field'] ?? ($field === 'submit' ? '' : $field)));
        if (!in_array($config[$field . '_by'], ['name','id'], true) || strlen($config[$field . '_field']) > 128 || preg_match('/[\x00-\x1f]/', $config[$field . '_field'])) throw new RuntimeException('config');
        if ($config['mode'] !== 'auto' && !empty($config['use_' . $field]) && $config[$field . '_field'] === '') throw new RuntimeException('config');
    }
    foreach (['success','failure'] as $marker) {
        $config[$marker . '_kind'] = (string)($input[$marker . '_kind'] ?? ($marker === 'success' ? 'auto' : 'id'));
        $config[$marker . '_value'] = trim((string)($input[$marker . '_value'] ?? ''));
        if (!in_array($config[$marker . '_kind'], ($marker === 'success' ? ['auto','id','class','text','url'] : ['id','class','text','url']), true) || strlen($config[$marker . '_value']) > 300
            || ($marker === 'success' && $config[$marker . '_kind'] !== 'auto' && $config[$marker . '_value'] === '') || preg_match('/[\x00-\x1f]/', $config[$marker . '_value'])) throw new RuntimeException('config');
        if ($config[$marker . '_kind'] === 'url' && $config[$marker . '_value'] !== '' && !str_starts_with($config[$marker . '_value'], '/')) throw new RuntimeException('config');
    }
    return $config;
}

function admin_provider_remote_row(Mysql_ks $db, int $providerId): ?array
{
    $row = $db->select_user('SELECT * FROM provider_remote_login_configs WHERE provider_id = ' . $providerId . ' LIMIT 1');
    return !empty($row['provider_id']) ? $row : null;
}

function admin_provider_remote_save(Mysql_ks $db, int $providerId, array $input, string $dashboardUrl): void
{
    $config = admin_provider_remote_config($input, $dashboardUrl);
    $previous = admin_provider_remote_row($db, $providerId);
    $old = $previous ? admin_provider_remote_unseal($previous['credentials_encrypted'], $providerId) : [];
    $credentials = ['username' => (string)($input['username'] ?? ''), 'password' => (string)($input['password'] ?? '')];
    if ($credentials['password'] === '') $credentials['password'] = (string)($old['password'] ?? '');
    if (strlen($credentials['username']) > 512 || strlen($credentials['password']) > 4096
        || ($config['use_username'] && $credentials['username'] === '') || ($config['use_password'] && $credentials['password'] === '')) throw new RuntimeException('config');
    $cipher = $db->escape(admin_provider_remote_seal($credentials, $providerId));
    $json = $db->escape(json_encode($config, JSON_THROW_ON_ERROR));
    if (!$db->query("INSERT INTO provider_remote_login_configs (provider_id,config_json,credentials_encrypted) VALUES ({$providerId},'{$json}','{$cipher}')
        ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),credentials_encrypted=VALUES(credentials_encrypted)")) throw new RuntimeException('storage');
}

function admin_provider_remote_dom(string $html): DOMDocument
{
    $before = libxml_use_internal_errors(true);
    try { $doc = new DOMDocument(); $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); return $doc; }
    finally { libxml_clear_errors(); libxml_use_internal_errors($before); }
}

/** Ignore explicit hidden/template content; HTML mode cannot execute CSS or JavaScript. */
function admin_provider_remote_element_visible(DOMElement $element): bool
{
    for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
        $classes = preg_split('/\s+/', trim($node->getAttribute('class')));
        if (in_array(strtolower($node->tagName), ['script','style','template','noscript'], true)
            || $node->hasAttribute('hidden') || strtolower($node->getAttribute('aria-hidden')) === 'true'
            || array_intersect($classes, ['d-none','hidden','invisible'])
            || (in_array('modal',$classes,true) && !in_array('show',$classes,true) && !preg_match('/display\s*:\s*(?:block|flex|grid)/i',$node->getAttribute('style')))
            || preg_match('/(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $node->getAttribute('style'))) return false;
    }
    return true;
}

function admin_provider_remote_login_present(array $page, array $config): bool
{
    $doc = admin_provider_remote_dom($page['body']);
    foreach ($doc->getElementsByTagName('input') as $input) {
        if (strtolower($input->getAttribute('type')) === 'hidden' || !admin_provider_remote_element_visible($input)) continue;
        if ($input->getAttribute('autocomplete') === 'one-time-code'
            || preg_match('/^(?:otp|totp|two_factor|two_factor_code|2fa_code|verification_code|captcha|captcha_code)$/i', $input->getAttribute('name'))) return true;
        foreach (['username','password'] as $type) {
            if (!empty($config['use_'.$type]) && $input->getAttribute($config[$type.'_by']) === $config[$type.'_field']) return true;
        }
    }
    return false;
}

function admin_provider_remote_logout_present(array $page): bool
{
    $doc = admin_provider_remote_dom($page['body']);
    // Require an actionable logout control, never arbitrary page prose or a URL change alone.
    $labelPattern = '/^(?:wyloguj(?:\s+się)?|log\s*out|sign\s*out|abmelden|déconnexion|se\s+déconnecter|cerrar\s+sesión|sair|esci|выйти)$/iu';
    $tokenPattern = '/(?:^|[^a-z0-9])(?:logout|log_out|signout|sign_out|wyloguj|abmelden)(?:$|[^a-z0-9])/i';
    foreach (['a','button','input'] as $tag) foreach ($doc->getElementsByTagName($tag) as $control) {
        $logoutUrl = false;
        if (!admin_provider_remote_element_visible($control) || $control->hasAttribute('disabled') || $control->getAttribute('aria-disabled') === 'true') continue;
        if ($tag === 'input' && !in_array(strtolower($control->getAttribute('type')), ['submit','button'], true)) continue;
        if ($tag === 'a') {
            $href = trim($control->getAttribute('href'));
            if ($href === '' || $href === '#') continue;
            try { $target = admin_provider_remote_resolve_url($page['url'], $href); } catch (RuntimeException $e) { continue; }
            $logoutUrl = (bool)preg_match('/^(?:logout|log_out|signout|sign_out|wyloguj|abmelden)(?:\.(?:php|html?))?$/i', rawurldecode(basename(rtrim((string)parse_url($target, PHP_URL_PATH), '/'))));
            parse_str((string)parse_url($target, PHP_URL_QUERY), $query);
            foreach ($query as $key => $value) {
                if (!is_string($value)) continue;
                if ((preg_match('/^(?:logout|signout|sign_out|wyloguj)$/i', $key) && in_array(strtolower($value), ['', '1', 'true'], true))
                    || (in_array(strtolower($key), ['action','do','task'], true) && preg_match('/^(?:logout|signout|sign_out|wyloguj)$/i', $value))) $logoutUrl = true;
            }
        }
        $label = $control->getAttribute('aria-label') ?: ($tag === 'input' ? $control->getAttribute('value') : $control->textContent);
        $label = preg_replace('/\s+/u', ' ', trim($label));
        if ($logoutUrl || preg_match($labelPattern, $label) || preg_match($tokenPattern, $control->getAttribute('name').' '.$control->getAttribute('id'))) return true;
    }
    return false;
}

function admin_provider_remote_match(array $page, string $kind, string $value): bool
{
    if ($kind === 'auto') return admin_provider_remote_logout_present($page);
    if ($value === '') return false;
    if ($kind === 'url') {
        $path = (string)(parse_url($page['url'], PHP_URL_PATH) ?: '/');
        return $path === $value || str_starts_with($path, rtrim($value, '/') . '/');
    }
    $doc = admin_provider_remote_dom($page['body']);
    foreach (iterator_to_array($doc->getElementsByTagName('*')) as $element) {
        if ($kind === 'id' && $element->getAttribute('id') === $value) return true;
        if ($kind === 'class' && in_array($value, preg_split('/\s+/', $element->getAttribute('class')), true)) return true;
    }
    if ($kind !== 'text') return false;
    foreach (['script','style','template'] as $tag) foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) $node->parentNode?->removeChild($node);
    return str_contains((string)$doc->textContent, $value);
}

function admin_provider_remote_feedback(array $page): string
{
    $doc = admin_provider_remote_dom($page['body']); $checked = 0;
    foreach ($doc->getElementsByTagName('*') as $node) {
        if (++$checked > 5000) break;
        if (!admin_provider_remote_element_visible($node)) continue;
        if ($node->getAttribute('role') !== 'alert' && !preg_match('/(?:^|[ _-])(?:error|invalid|alert-danger|alert-error)(?:$|[ _-])/i', $node->getAttribute('id').' '.$node->getAttribute('class'))) continue;
        $text = mb_substr(trim($node->textContent), 0, 2000);
        if (preg_match('/(?:captcha|kod(?:u)?\s+(?:z\s+obrazka|weryfikacyjny)).{0,100}(?:invalid|incorrect|wrong|expired|błęd|niepraw|wygas|ungültig)|(?:invalid|incorrect|wrong|expired|błęd|niepraw|wygas|ungültig).{0,100}(?:captcha|kod(?:u)?\s+(?:z\s+obrazka|weryfikacyjny))/iu', $text)) return 'captcha_rejected';
        if (preg_match('/(?:invalid|incorrect|wrong|błęd|niepraw|ungültig|falsch).{0,100}(?:credentials|password|username|login|email|hasł|dane|passwort|benutzer)|(?:credentials|password|username|login|email|hasł|dane|passwort|benutzer).{0,100}(?:invalid|incorrect|wrong|błęd|niepraw|ungültig|falsch)|(?:authentication|login|logowanie)\s+(?:failed|denied|nieudane)|user\s+not\s+found/iu', $text)) return 'rejected';
    }
    return '';
}

/** Discover only one unambiguous POST login form, without submitting credentials. */
function admin_provider_remote_discover(array $page, array $config): array
{
    $doc = admin_provider_remote_dom($page['body']); $candidates = [];
    foreach ($doc->getElementsByTagName('form') as $form) {
        if (!admin_provider_remote_element_visible($form)) continue;
        $inputs = []; $passwords = []; $usernames = []; $preferred = [];
        foreach ($form->getElementsByTagName('input') as $input) {
            $type = strtolower($input->getAttribute('type') ?: 'text');
            if ($input->hasAttribute('disabled') || !admin_provider_remote_element_visible($input)) continue;
            if ($type === 'password') $passwords[] = $input;
            if (in_array($type, ['text','email','tel'], true)
                && !preg_match('/captcha|otp|totp|verification|one.?time|code/i', $input->getAttribute('name').' '.$input->getAttribute('id').' '.$input->getAttribute('autocomplete'))) {
                $usernames[] = $input;
                if ($input->getAttribute('autocomplete') === 'username'
                    || preg_match('/^(?:_?username|_?user|login|identity|email|user_email|login_email)$/i', $input->getAttribute('name'))
                    || $type === 'email') $preferred[] = $input;
            }
        }
        if ((!empty($config['use_password']) && count($passwords) !== 1)
            || (empty($config['use_password']) && count($passwords) > 0)) continue;
        $users = count($preferred) === 1 ? $preferred : $usernames;
        if (!empty($config['use_username']) && count($users) !== 1) continue;
        if (strtolower($form->getAttribute('method')) !== 'post') continue;
        $resolved = $config; $valid = true;
        foreach (['username'=>$users[0] ?? null,'password'=>$passwords[0] ?? null] as $type=>$input) {
            if (empty($config['use_'.$type])) continue;
            // HTTP submission needs a real name, not a guessed selector.
            if (!$input || $input->getAttribute('name') === '') { $valid = false; break; }
            $resolved[$type.'_by'] = 'name'; $resolved[$type.'_field'] = $input->getAttribute('name');
        }
        if (!$valid) continue;
        $buttons = [];
        foreach (['button','input'] as $tag) foreach ($form->getElementsByTagName($tag) as $button) {
            $type = strtolower($button->getAttribute('type'));
            if (($type === 'submit' || ($tag === 'button' && $type === ''))
                && !$button->hasAttribute('disabled') && admin_provider_remote_element_visible($button)) $buttons[] = $button;
        }
        if (count($buttons) !== 1) continue;
        $button = $buttons[0];
        $purpose = $form->getAttribute('action').' '.$form->getAttribute('id').' '.$button->getAttribute('name').' '.$button->textContent.' '.$button->getAttribute('value');
        if (preg_match('/register|registration|signup|sign[ _-]?up|create[ _-]?(?:an?[ _-]?)?account|reset[ _-]?password|password[ _-]?reset|forgot|utwórz[ _-]?konto|zarejestruj/i', $purpose)) continue;
        foreach ($passwords as $password) if (strtolower($password->getAttribute('autocomplete')) === 'new-password') { $valid = false; break; }
        if (!$valid) continue;
        $resolved['submit_by'] = $button->getAttribute('name') !== '' ? 'name' : 'id';
        $resolved['submit_field'] = $button->getAttribute($resolved['submit_by']);
        $candidates[] = $resolved;
    }
    if (count($candidates) !== 1) throw new RuntimeException('browser_required');
    return $candidates[0];
}

function admin_provider_remote_inspect(string $url, callable $request, callable $browser): array
{
    $config = admin_provider_remote_config(['use_username'=>1,'use_password'=>1], $url);
    $before = $request($url, null);
    if ($before['status'] === 429) throw new RuntimeException('rate_limited');
    if ($before['status'] !== 200) throw new RuntimeException('connection');
    if (preg_match('/autocomplete=["\']one-time-code/i', $before['body']) && !preg_match('/captcha/i', $before['body'])) throw new RuntimeException('manual');
    $transport = 'html';
    try {
        $detected = admin_provider_remote_discover($before, $config);
        // Validate action/overrides too, without submitting or using stored credentials.
        admin_provider_remote_form($before, $detected, ['username'=>'','password'=>'']);
        if (preg_match('/captcha|data-sitekey/i', $before['body'])) throw new RuntimeException('browser_required');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'browser_required') throw $error;
        $response = $browser($url, $config);
        if (($response['code'] ?? '') !== 'detected' || !is_array($response['config'] ?? null)) throw new RuntimeException(in_array($response['code'] ?? '', ['form','busy','manual','origin','destination','limit'],true) ? $response['code'] : 'connection');
        $detected = $response['config']; $transport = 'browser';
    }
    // Return configuration suggestions only, never cookies, hidden tokens, page HTML or field values.
    $suggested = ['mode'=>'auto','use_username'=>true,'use_password'=>true,'success_kind'=>'auto','success_value'=>'','captcha'=>!empty($detected['captcha'])];
    foreach (['username','password','submit'] as $type) {
        $by = $detected[$type.'_by'] ?? 'name'; $field = $detected[$type.'_field'] ?? '';
        if (in_array($by, ['name','id'],true) && is_string($field) && strlen($field)<=128 && !preg_match('/[\x00-\x1f]/',$field)) {
            $suggested[$type.'_by']=$by; $suggested[$type.'_field']=$field;
        }
    }
    foreach (['captcha_input','captcha_image'] as $key) if (is_string($detected[$key] ?? null) && strlen($detected[$key])<=256 && !preg_match('/[\x00-\x1f]/',$detected[$key])) $suggested[$key]=$detected[$key];
    if(is_string($detected['login_path']??null)&&$detected['login_path']!==''){
        admin_provider_remote_resolve_url($url,$detected['login_path']);$suggested['login_path']=$detected['login_path'];
    }
    return ['ok'=>true,'code'=>'detected','transport'=>$transport,'config'=>$suggested];
}

function admin_provider_remote_form(array $page, array $config, array $credentials): array
{
    $doc = admin_provider_remote_dom($page['body']); $found = [];
    foreach ($doc->getElementsByTagName('form') as $form) {
        $fields = []; $valid = true;
        foreach (['username','password'] as $type) {
            if (empty($config['use_' . $type])) continue;
            $matches = [];
            foreach ($form->getElementsByTagName('input') as $input) if (!$input->hasAttribute('disabled') && $input->getAttribute($config[$type . '_by']) === $config[$type . '_field']) $matches[] = $input;
            if (count($matches) !== 1 || $matches[0]->getAttribute('name') === '') { $valid = false; break; }
            $fields[$type] = $matches[0]->getAttribute('name');
        }
        if ($valid) $found[] = [$form,$fields];
    }
    if (count($found) !== 1) throw new RuntimeException('form');
    [$form,$fields] = $found[0];
    if (count(array_unique($fields)) !== count($fields)) throw new RuntimeException('form');
    if (strtolower($form->getAttribute('method')) !== 'post') throw new RuntimeException('post');
    $post = [];
    foreach ($form->getElementsByTagName('input') as $input) {
        $name = $input->getAttribute('name');
        if ($name !== '' && !$input->hasAttribute('disabled') && strtolower($input->getAttribute('type')) === 'hidden') $post[$name] = $input->getAttribute('value');
    }
    foreach ($fields as $type=>$name) $post[$name] = $credentials[$type];
    if ($config['submit_field'] !== '') {
        $buttons = [];
        foreach (['button','input'] as $tag) foreach ($form->getElementsByTagName($tag) as $button) {
            $type = strtolower($button->getAttribute('type'));
            if (($type === 'submit' || ($tag === 'button' && $type === '')) && !$button->hasAttribute('disabled') && $button->getAttribute($config['submit_by']) === $config['submit_field']) $buttons[] = $button;
        }
        if (count($buttons) !== 1) throw new RuntimeException('form');
        $button = $buttons[0];
        if ($button->hasAttribute('formaction') || $button->hasAttribute('formmethod')) throw new RuntimeException('post');
        if ($button->getAttribute('name') !== '') {
            $name = $button->getAttribute('name');
            if (in_array($name, $fields, true)) throw new RuntimeException('form');
            $post[$name] = $button->getAttribute('value');
        }
    }
    if (count($post) > 64 || strlen(http_build_query($post)) > 32768) throw new RuntimeException('limit');
    return [admin_provider_remote_resolve_url($page['url'], $form->getAttribute('action')), $post];
}

/** Per-attempt memory-only cookie engine. Never enables verbose, cookiejar or header/body dumps. */
function admin_provider_remote_transport(): Closure
{
    if (!function_exists('curl_init')) throw new RuntimeException('connection');
    $handle = curl_init(); $deadline = microtime(true) + 20; $requests = 0;
    curl_setopt_array($handle, [CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'SubmeProviderLoginTest/1.0']);
    return static function(string $url, ?array $post = null) use ($handle, $deadline, &$requests): array {
        $origin = admin_provider_remote_url($url);
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $remaining = $deadline - microtime(true);
            if (++$requests > 8 || $remaining <= 0) throw new RuntimeException('limit');
            $url = admin_provider_remote_url($url); $host = parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);
            $records = @dns_get_record($host, DNS_A | DNS_AAAA); $ips = [];
            foreach (is_array($records) ? $records : [] as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? '';
                if ($ip === '') continue;
                if (!admin_provider_remote_public_ip($ip)) throw new RuntimeException('destination');
                $ips[] = $ip;
            }
            if (!$ips) throw new RuntimeException('dns');
            $ip = $ips[0]; $body = ''; $location = ''; $headerSize = 0;
            curl_setopt_array($handle, [CURLOPT_URL => $url, CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_CONNECTTIMEOUT_MS => min(4000, (int)($remaining * 1000)), CURLOPT_TIMEOUT_MS => max(1, (int)($remaining * 1000)),
                CURLOPT_WRITEFUNCTION => static function($ch, string $chunk) use (&$body): int { if (strlen($body) + strlen($chunk) > 1048576) return 0; $body .= $chunk; return strlen($chunk); },
                CURLOPT_HEADERFUNCTION => static function($ch, string $line) use (&$location,&$headerSize): int { $headerSize += strlen($line); if ($headerSize > 32768) return 0; if (stripos($line, 'location:') === 0) $location = trim(substr($line, 9)); return strlen($line); }]);
            if ($post === null) curl_setopt($handle, CURLOPT_HTTPGET, true);
            else { curl_setopt($handle, CURLOPT_POST, true); curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post)); }
            if (curl_exec($handle) === false) throw new RuntimeException(curl_errno($handle) === CURLE_WRITE_ERROR || curl_errno($handle) === CURLE_OPERATION_TIMEDOUT ? 'limit' : 'connection');
            $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($status < 300 || $status >= 400) return ['status'=>$status,'body'=>$body,'url'=>$url];
            if ($location === '' || ($post !== null && !in_array($status, [301,302,303], true))) throw new RuntimeException('unconfirmed');
            $url = admin_provider_remote_resolve_url($origin, admin_provider_remote_resolve_url($url, $location));
            $post = null;
        }
        throw new RuntimeException('limit');
    };
}

function admin_provider_remote_run(array $config, array $credentials, string $dashboardUrl, callable $request): string
{
    $automatic = ($config['mode'] ?? 'html') === 'auto';
    if ($automatic && !empty($config['captcha']) && empty($config['two_factor'])) throw new RuntimeException('browser_required');
    if (!empty($config['captcha']) || !empty($config['two_factor'])) return 'manual';
    $url = admin_provider_remote_resolve_url($dashboardUrl, $config['login_path']);
    $before = $request($url, null);
    if ($before['status'] === 429) return 'rate_limited';
    if ($before['status'] !== 200) throw new RuntimeException('connection');
    if (preg_match('/autocomplete=["\']one-time-code/i', $before['body']) && !preg_match('/captcha/i', $before['body'])) return 'manual';
    // Detect common challenge markup even when its checkbox was not configured.
    if (preg_match('/(?:alt=[\"\'][^\"\']*captcha|name=[\"\'][^\"\']*captcha|data-sitekey|(?:recaptcha|hcaptcha|turnstile)\.(?:com|net)|autocomplete=[\"\']one-time-code)/i', $before['body'])) {
        if ($automatic) throw new RuntimeException('browser_required');
        return 'manual';
    }
    if ($automatic) $config = admin_provider_remote_discover($before, $config);
    if (admin_provider_remote_match($before, $config['success_kind'], $config['success_value'])) throw new RuntimeException('baseline');
    [$action,$post] = admin_provider_remote_form($before,$config,$credentials);
    $after = $request($action,$post);
    if ($after['status'] === 429) return 'rate_limited';
    $feedback = admin_provider_remote_feedback($after);
    if ($feedback !== '') return $feedback;
    if (in_array($after['status'], [401,403], true) || admin_provider_remote_match($after,$config['failure_kind'],$config['failure_value'])) return 'rejected';
    if ($after['status'] !== 200) return 'unconfirmed';
    // A login form still present is not an authenticated result, even if the marker matches.
    $loginStillPresent = admin_provider_remote_login_present($after, $config);
    return !$loginStillPresent && admin_provider_remote_match($after,$config['success_kind'],$config['success_value']) ? 'success' : 'unconfirmed';
}

function admin_provider_remote_render_settings(array $settings, string $locale): void
{
    $pl=$locale==='pl';
    foreach ([
        'provider_remote_login_enabled'=>[$pl?'Zdalne logowanie do panelu providera':'Remote login to provider panels',$pl?'Udostępnia konfigurację i ręczny test logowania w edycji providera. Bez historii prób.':'Enables configuration and manual login tests in the provider editor. No attempt history.'],
        'provider_remote_subscriptions_enabled'=>[$pl?'Automatyczne subskrypcje w panelu providera':'Automatic subscriptions in provider panels',$pl?'Opcja przygotowana na kolejny etap. Obecnie nie tworzy ani nie przedłuża subskrypcji — także po włączeniu.':'Reserved for the next stage. It does not create or renew subscriptions yet, even when enabled.'],
    ] as $key=>[$label,$help]) { ?>
        <div class="col-12"><div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="<?php echo admin_e($key); ?>" name="<?php echo admin_e($key); ?>"<?php echo !empty($settings[$key])?' checked':''; ?>>
            <label class="form-check-label" for="<?php echo admin_e($key); ?>"><?php echo admin_e($label); ?></label>
        </div><small class="text-muted"><?php echo admin_e($help); ?></small></div>
    <?php }
}

function admin_provider_remote_render_section(array $provider, array $settings, string $csrf, string $locale): void
{
    $pl=$locale==='pl'; $enabled=!empty($settings['provider_remote_login_enabled']); ?>
    <section class="admin-provider-remote" data-provider-remote data-provider-id="<?php echo (int)$provider['id']; ?>" data-csrf="<?php echo admin_e($csrf); ?>" data-locale="<?php echo admin_e($locale); ?>">
        <div class="admin-provider-remote__heading"><span class="admin-provider-remote__icon"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></span><div>
            <h4><?php echo $pl?'Zdalne logowanie':'Remote login'; ?></h4><p><?php echo $pl?'Ręczny test połączenia z zapisanym panelem providera.':'Manually test login to the saved provider panel.'; ?></p>
        </div><span class="admin-provider-remote__badge"><?php echo $enabled?'ON':'OFF'; ?></span></div>
        <?php if ($enabled): ?>
        <div class="admin-provider-remote__actions">
            <button class="btn btn-dark btn-sm" type="button" data-provider-remote-test><i class="bi bi-plug" aria-hidden="true"></i> <?php echo $pl?'Testuj logowanie':'Test login'; ?></button>
            <button class="btn btn-outline-dark btn-sm" type="button" data-provider-remote-config><i class="bi bi-sliders" aria-hidden="true"></i> <?php echo $pl?'Konfiguracja':'Configure'; ?></button>
        </div>
        <p class="admin-provider-remote__hint"><?php echo $pl?'Test nie otwiera zalogowanej karty i nie zmienia subskrypcji. Najpierw zapisz URL providera.':'The test does not open an authenticated tab or modify subscriptions. Save the provider URL first.'; ?></p>
        <?php else: ?>
        <p class="admin-provider-remote__hint"><?php echo admin_e(admin_provider_remote_text('disabled',$locale)); ?> <a href="/admin/?page=settings#admin-settings-features-pane"><?php echo $pl?'Ustawienia':'Settings'; ?></a></p>
        <?php endif; ?>
        <div class="admin-provider-remote__result" data-provider-remote-result role="status" aria-live="polite" hidden></div>
        <div class="admin-provider-remote__challenge" data-provider-remote-challenge hidden>
            <img alt="CAPTCHA" data-provider-remote-captcha-image>
            <label for="provider-remote-answer"><?php echo $pl?'Przepisz kod z obrazka':'Enter the image code'; ?></label>
            <input class="form-control" type="text" id="provider-remote-answer" maxlength="128" autocomplete="off" data-provider-remote-answer>
            <button class="btn btn-dark btn-sm" type="button" data-provider-remote-complete><?php echo $pl?'Dokończ logowanie':'Complete login'; ?></button>
            <button class="btn btn-outline-dark btn-sm" type="button" data-provider-remote-cancel><?php echo $pl?'Anuluj test':'Cancel test'; ?></button>
        </div>
    </section>
<?php }

function admin_provider_remote_render_modal(string $locale): void
{
    $pl=$locale==='pl'; ?>
    <div class="modal fade admin-provider-remote-modal" id="providerRemoteModal" tabindex="-1" aria-labelledby="providerRemoteModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="providerRemoteModalTitle"><?php echo $pl?'Konfiguracja zdalnego logowania':'Remote login configuration'; ?></h5><p><?php echo $pl?'HTML / JavaScript · ręczny test · bez logów prób':'HTML / JavaScript · manual test · no attempt logs'; ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo $pl?'Zamknij':'Close'; ?>"></button></div>
        <form data-provider-remote-form autocomplete="off"><div class="modal-body">
            <div class="admin-provider-remote-modal__scan">
                <div><strong><?php echo $pl?'Rozpoznaj panel providera':'Detect provider login'; ?></strong><small class="text-muted" data-provider-remote-scan-url></small></div>
                <button class="btn btn-outline-dark btn-sm" type="button" data-provider-remote-inspect><i class="bi bi-check2-circle" aria-hidden="true"></i> <?php echo $pl?'Sprawdź panel':'Check panel'; ?></button>
                <small class="text-muted"><?php echo $pl?'Sprawdzimy formularz i dobierzemy zalecane opcje. Login i hasło nie są wysyłane podczas sprawdzania.':'We inspect the form and suggest settings. Your username and password are not submitted during this check.'; ?></small>
                <div class="admin-provider-remote__result" data-provider-remote-inspect-result role="status" aria-live="polite" hidden></div>
            </div>
            <div class="admin-provider-remote-modal__notice"><?php echo $pl?'Sesja działa tylko podczas testu. W trybie przeglądarkowym CAPTCHA wpiszesz ręcznie. 2FA, SSO i niestandardowe wyzwania mogą wymagać osobnego adaptera.':'The session exists only during the test. In browser mode you enter CAPTCHA manually. 2FA, SSO and custom challenges may require an adapter.'; ?></div>
            <div class="row g-3">
            <div class="col-12"><p class="admin-provider-remote__hint" data-provider-remote-detection-hint><?php echo $pl?'Podaj login i hasło. Pola formularza i przycisk logowania rozpoznamy automatycznie na podstawie zapisanego URL providera.':'Enter your username and password. Login fields and the submit button are detected from the saved provider URL.'; ?></p></div>
            <?php foreach (['username'=>($pl?'Login':'Username'),'password'=>($pl?'Hasło':'Password')] as $field=>$label): ?>
                <div class="col-md-6">
                <label class="form-label" for="remote-<?php echo $field; ?>"><?php echo admin_e($label); ?></label>
                <input class="form-control" id="remote-<?php echo $field; ?>" type="<?php echo $field==='password'?'password':'text'; ?>" name="<?php echo $field; ?>" autocomplete="<?php echo $field==='password'?'new-password':'off'; ?>" maxlength="<?php echo $field==='password'?'4096':'512'; ?>" spellcheck="false">
                <?php if ($field==='password'): ?><small class="text-muted" data-provider-remote-password-hint></small><?php else: ?><small class="text-muted"><?php echo $pl?'Dane dostępowe zapisujemy zaszyfrowane.':'Credentials are stored encrypted.'; ?></small><?php endif; ?>

                </div>
            <?php endforeach; ?>


            <div class="col-12"><p class="admin-provider-remote__hint" data-provider-remote-confirmation-hint><?php echo $pl?'Potwierdzenie logowania wykrywamy automatycznie. Własny warunek możesz ustawić w opcjach zaawansowanych.':'Login is detected automatically. A custom condition is available in advanced settings.'; ?></p></div>
            <div class="col-12"><details class="admin-provider-remote-modal__advanced"><summary><?php echo $pl?'Adresy subskrypcji (opcjonalnie)':'Subscription URLs (optional)'; ?></summary><div class="row g-3">
                <div class="col-12"><label class="form-label" for="remote-subscription-list"><?php echo $pl?'URL listy i wyszukiwarki subskrypcji':'Subscription list and search URL'; ?></label><input class="form-control" id="remote-subscription-list" name="subscription_list_path" maxlength="2048" placeholder="/subscriptions"><small class="text-muted"><?php echo $pl?'Puste: automat poszuka zakładki z listą. Budowę tabeli i sposób wyszukiwania rozpozna sam.':'Leave empty to discover the list. The table and search structure are detected automatically.'; ?></small></div>
                <div class="col-12"><label class="form-label" for="remote-subscription-renew"><?php echo $pl?'URL przedłużania subskrypcji — kolejny etap':'Subscription renewal URL — future stage'; ?></label><input class="form-control" id="remote-subscription-renew" name="subscription_renew_path" maxlength="2048" placeholder="/subscriptions/renew"><small class="text-muted"><?php echo $pl?'Adres wykorzystamy w kolejnym etapie, po wyborze istniejącej subskrypcji. Teraz nie otwieramy ani nie wysyłamy formularza przedłużenia. Oba adresy muszą należeć do domeny panelu.':'Reserved for the next stage, after selecting an existing subscription. Renewal forms are not opened or submitted yet. Both URLs must use the panel origin.'; ?></small></div>
            </div></details></div>
            <div class="col-12"><details class="admin-provider-remote-modal__advanced"><summary><?php echo $pl?'Ustawienia zaawansowane':'Advanced settings'; ?></summary><div class="row g-3">
            <div class="col-12"><div class="admin-provider-remote-modal__checks">
                <label><input class="form-check-input" type="checkbox" name="captcha" value="1"> CAPTCHA</label>
                <label><input class="form-check-input" type="checkbox" name="captcha_ocr" value="1"> <?php echo $pl?'Automatyczny odczyt CAPTCHA (eksperymentalny)':'Automatic CAPTCHA reading (experimental)'; ?></label>
                <label><input class="form-check-input" type="checkbox" name="captcha_ocr_digits" value="1"> <?php echo $pl?'CAPTCHA zawiera tylko cyfry':'CAPTCHA contains digits only'; ?></label>
                <label><input class="form-check-input" type="checkbox" name="two_factor" value="1"> <?php echo $pl?'Kod 2FA / OTP':'2FA / OTP code'; ?></label>
            </div></div>
            <div class="col-12"><small class="text-muted"><?php echo $pl?'OCR działa lokalnie i nie wysyła obrazka do zewnętrznych usług. Przy niepewnym odczycie wpiszesz kod ręcznie. Jedna próba logowania; bez automatycznego ponawiania po błędzie.':'OCR runs locally without sending the image to external services. Uncertain results require manual entry. One login attempt, no automatic retry after failure.'; ?></small></div>
            <div class="col-12"><label class="form-label" for="remote-mode"><?php echo $pl?'Tryb logowania':'Login mode'; ?></label><select class="form-select" id="remote-mode" name="mode"><option value="auto"><?php echo $pl?'Automatycznie rozpoznaj formularz':'Detect login form automatically'; ?></option><option value="html"><?php echo $pl?'Formularz HTML (POST + CSRF)':'HTML form (POST + CSRF)'; ?></option><option value="browser"><?php echo $pl?'Przeglądarka z JavaScriptem (także ręczna CAPTCHA)':'JavaScript browser (with manual CAPTCHA)'; ?></option></select></div>
            <div class="col-12" data-provider-remote-manual-fields><div class="row g-3">
            <?php foreach (['username'=>($pl?'Login':'Username'),'password'=>($pl?'Hasło':'Password')] as $field=>$label): ?>
                <div class="col-md-6"><label class="admin-provider-remote-modal__check"><input type="checkbox" class="form-check-input" name="use_<?php echo $field; ?>" value="1" checked> <?php echo $pl?'Panel wymaga: ':'Required: '; echo admin_e($label); ?></label>
                <div class="admin-provider-remote-modal__selector"><label class="form-label" for="remote-<?php echo $field; ?>-by"><?php echo $pl?'Znajdź pole przez':'Find field by'; ?></label><div class="input-group"><select class="form-select" id="remote-<?php echo $field; ?>-by" name="<?php echo $field; ?>_by"><option value="name">name</option><option value="id">id</option></select><input class="form-control" name="<?php echo $field; ?>_field" aria-label="<?php echo admin_e($label . ($pl?' — identyfikator pola':' — field identifier')); ?>" value="<?php echo $field; ?>" maxlength="128"></div></div>
                </div>
            <?php endforeach; ?>
                <div class="col-12"><label class="form-label" for="remote-submit-field"><?php echo $pl?'Przycisk „Zaloguj się” w panelu providera':'Provider panel login button'; ?></label><div class="input-group"><select class="form-select" name="submit_by" aria-label="<?php echo $pl?'Identyfikacja przycisku':'Button selector'; ?>"><option value="name">name</option><option value="id">id</option></select><input class="form-control" id="remote-submit-field" name="submit_field" maxlength="128"></div><small class="text-muted"><?php echo $pl?'Np. id = _submit lub name = _submit. Puste pole: jeden przycisk type="submit" na stronie.':'E.g. id = _submit or name = _submit. Leave empty for a single type="submit" button.'; ?></small></div>
            </div></div>

            <div class="col-12"><label class="form-label" for="remote-success"><?php echo $pl?'Potwierdzenie logowania':'Login confirmation'; ?></label><div class="input-group"><select class="form-select" name="success_kind" aria-label="<?php echo $pl?'Rodzaj potwierdzenia':'Confirmation type'; ?>"><option value="auto"><?php echo $pl?'Automatycznie (domyślnie)':'Automatic (default)'; ?></option><option value="id">id</option><option value="class">class</option><option value="text"><?php echo $pl?'Tekst na stronie':'Page text'; ?></option><option value="url"><?php echo $pl?'Ścieżka URL':'URL path'; ?></option></select><input class="form-control" id="remote-success" name="success_value" maxlength="300" placeholder="<?php echo $pl?'Np. account-menu lub /dashboard':'E.g. account-menu or /dashboard'; ?>"></div><small class="text-muted"><?php echo $pl?'Automatycznie: formularz logowania znika i pojawia się opcja wylogowania. Własny znacznik ustaw tylko, jeśli panel wymaga innej metody.':'Automatic: the login form disappears and a logout control appears. Choose a custom marker only if this panel needs a different method.'; ?></small></div>
                <div class="col-12"><label class="form-label" for="remote-login-path"><?php echo $pl?'Ścieżka logowania (opcjonalnie)':'Login path (optional)'; ?></label><input class="form-control" id="remote-login-path" name="login_path" maxlength="2048" placeholder="/login"><small class="text-muted"><?php echo $pl?'Puste pole używa zapisanego URL panelu. Ten sam protokół, host i port. HTTP nie szyfruje danych.':'Leave empty to use the saved panel URL. Same protocol, host and port only. HTTP does not encrypt data.'; ?></small></div>
                <div class="col-md-6"><label class="form-label" for="remote-captcha-input"><?php echo $pl?'Selektor CSS pola CAPTCHA':'CAPTCHA input CSS selector'; ?></label><input class="form-control" id="remote-captcha-input" name="captcha_input" maxlength="256" value="input[placeholder=&quot;Enter code&quot;]"></div>
                <div class="col-md-6"><label class="form-label" for="remote-captcha-image"><?php echo $pl?'Selektor CSS obrazka CAPTCHA':'CAPTCHA image CSS selector'; ?></label><input class="form-control" id="remote-captcha-image" name="captcha_image" maxlength="256" value="img[alt=&quot;Captcha&quot;]"></div>

                <div class="col-12"><label class="form-label" for="remote-failure"><?php echo $pl?'Znacznik błędu (opcjonalnie)':'Failure marker (optional)'; ?></label><div class="input-group"><select class="form-select" name="failure_kind" aria-label="<?php echo $pl?'Rodzaj znacznika błędu':'Failure marker type'; ?>"><option value="id">id</option><option value="class">class</option><option value="text"><?php echo $pl?'Tekst':'Text'; ?></option><option value="url"><?php echo $pl?'Ścieżka URL':'URL path'; ?></option></select><input class="form-control" id="remote-failure" name="failure_value" maxlength="300"></div></div>
            </div></details></div>
            </div>
            <div class="admin-provider-remote__result" data-provider-remote-modal-result role="status" aria-live="polite" hidden></div>
        </div><div class="modal-footer"><button class="btn btn-outline-dark btn-sm" type="button" data-bs-dismiss="modal"><?php echo $pl?'Zamknij':'Close'; ?></button><button class="btn btn-dark btn-sm" type="submit" data-provider-remote-save><?php echo $pl?'Zapisz konfigurację':'Save configuration'; ?></button></div></form>
    </div></div></div>
<?php }

function admin_provider_remote_browser(string $operation, array $data): array
{
    $token = (string)getenv('PROVIDER_BROWSER_TOKEN');
    if ($token === '') {
        $path = dirname(__DIR__) . '/storage/provider-remote-private/browser.token';
        $token = is_readable($path) ? trim((string)file_get_contents($path)) : '';
    }
    if (strlen($token)<32 || !function_exists('curl_init')) throw new RuntimeException('browser_unavailable');
    $base = rtrim((string)(getenv('PROVIDER_BROWSER_URL') ?: 'http://reseller_provider_browser_local:3210'),'/');
    $handle = curl_init($base . '/' . $operation);
    $quick = in_array($operation,['gate','backoff'],true);
    curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$token],CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>$quick?1:3,CURLOPT_TIMEOUT=>$quick?2:40,CURLOPT_RETURNTRANSFER=>false]);
    $body='';
    curl_setopt($handle,CURLOPT_WRITEFUNCTION,static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>524288)return 0;$body.=$chunk;return strlen($chunk);});
    $done=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);curl_close($handle);
    if ($done===false || $status!==200) throw new RuntimeException('browser_unavailable');
    $response=json_decode($body,true,16);
    if(!is_array($response)||!isset($response['code']))throw new RuntimeException('browser_unavailable');
    return $response;
}
