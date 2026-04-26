SET NAMES utf8mb4;

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`, `is_active`) VALUES
  (1, 'USD', 'US Dollar', '$', 1),
  (2, 'EUR', 'Euro', 'EUR', 1),
  (3, 'PLN', 'Polish Zloty', 'PLN', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `symbol` = VALUES(`symbol`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `app_settings` (
  `id`,
  `site_name`,
  `site_title`,
  `site_url`,
  `site_logo_url`,
  `site_description`,
  `site_keywords`,
  `support_email`,
  `default_currency_id`,
  `default_locale_code`,
  `maintenance_mode`,
  `registration_enabled`,
  `sales_enabled`,
  `credits_sales_enabled`,
  `trials_enabled`,
  `support_chat_enabled`,
  `contact_form_enabled`,
  `referrals_enabled`,
  `apps_page_enabled`,
  `application_instructions_enabled`,
  `page_guidance_enabled`,
  `history_cleanup_enabled`,
  `payments_cleanup_enabled`,
  `expired_orders_cleanup_enabled`,
  `support_chat_retention_days`,
  `support_chat_retention_hours`,
  `crypto_payments_enabled`,
  `bank_transfers_enabled`,
  `crypto_wallet_shared_assignments_enabled`,
  `bank_account_shared_assignments_enabled`,
  `crypto_wallet_assignment_mode`,
  `homepage_verification_enabled`,
  `results_per_page`,
  `news_feed_limit`,
  `email_rate_limit`
) VALUES (
  1,
  'Subscription Panel',
  'Subscription Panel',
  'https://example.com',
  NULL,
  'Self-hosted subscription management application.',
  'subscriptions,iptv,panel',
  'support@example.com',
  1,
  'en',
  0,
  1,
  1,
  1,
  0,
  1,
  1,
  1,
  1,
  1,
  1,
  0,
  0,
  0,
  7,
  168,
  1,
  1,
  0,
  1,
  'manual_only',
  1,
  20,
  10,
  50
)
ON DUPLICATE KEY UPDATE
  `site_name` = VALUES(`site_name`),
  `site_title` = VALUES(`site_title`),
  `site_url` = VALUES(`site_url`),
  `support_email` = VALUES(`support_email`),
  `default_currency_id` = VALUES(`default_currency_id`),
  `default_locale_code` = VALUES(`default_locale_code`),
  `support_chat_enabled` = VALUES(`support_chat_enabled`),
  `credits_sales_enabled` = VALUES(`credits_sales_enabled`),
  `contact_form_enabled` = VALUES(`contact_form_enabled`),
  `referrals_enabled` = VALUES(`referrals_enabled`),
  `apps_page_enabled` = VALUES(`apps_page_enabled`),
  `application_instructions_enabled` = VALUES(`application_instructions_enabled`),
  `page_guidance_enabled` = VALUES(`page_guidance_enabled`),
  `history_cleanup_enabled` = VALUES(`history_cleanup_enabled`),
  `payments_cleanup_enabled` = VALUES(`payments_cleanup_enabled`),
  `expired_orders_cleanup_enabled` = VALUES(`expired_orders_cleanup_enabled`),
  `support_chat_retention_days` = VALUES(`support_chat_retention_days`),
  `support_chat_retention_hours` = VALUES(`support_chat_retention_hours`),
  `crypto_payments_enabled` = VALUES(`crypto_payments_enabled`),
  `bank_transfers_enabled` = VALUES(`bank_transfers_enabled`),
  `crypto_wallet_shared_assignments_enabled` = VALUES(`crypto_wallet_shared_assignments_enabled`),
  `bank_account_shared_assignments_enabled` = VALUES(`bank_account_shared_assignments_enabled`),
  `crypto_wallet_assignment_mode` = VALUES(`crypto_wallet_assignment_mode`);

INSERT INTO `admin_roles` (`id`, `name`, `slug`, `access_level`) VALUES
  (1, 'System Administrator', 'system-administrator', 1000),
  (2, 'Support Agent', 'support-agent', 500)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `access_level` = VALUES(`access_level`);

INSERT INTO `admin_navigation_items` (`group_key`, `sort_order`, `title`, `icon_class`, `route_name`, `minimum_access_level`, `is_active`) VALUES
  ('main', 10, 'Dashboard', 'fa fa-home', 'admin.dashboard', 500, 1),
  ('main', 20, 'Customers', 'fa fa-users', 'admin.customers', 500, 1),
  ('main', 30, 'Orders', 'fa fa-shopping-cart', 'admin.orders', 500, 1),
  ('main', 40, 'Live Chat', 'fa fa-comments-o', 'admin.support', 500, 1),
  ('main', 50, 'Crypto Wallets', 'fa fa-btc', 'admin.crypto-wallets', 500, 1),
  ('main', 60, 'Bank Transfers', 'fa fa-university', 'admin.bank-transfers', 500, 1),
  ('main', 70, 'News', 'fa fa-newspaper-o', 'admin.news', 500, 1),
  ('main', 80, 'Pages', 'fa fa-file-text-o', 'admin.pages', 1000, 1),
  ('main', 90, 'Email Templates', 'fa fa-envelope-o', 'admin.email-templates', 1000, 1),
  ('main', 100, 'Settings', 'fa fa-cog', 'admin.settings', 1000, 1);

INSERT INTO `crypto_assets` (`code`, `name`, `coingecko_id`, `rate_currency_code`, `is_active`) VALUES
  ('BTC',  'Bitcoin',         'bitcoin',        'USD', 1),
  ('BCH',  'Bitcoin Cash',    'bitcoin-cash',   'USD', 0),
  ('LTC',  'Litecoin',        'litecoin',       'USD', 0),
  ('ETH',  'Ethereum',        'ethereum',       'USD', 1),
  ('DOGE', 'Dogecoin',        'dogecoin',       'USD', 1),
  ('BNB',  'Binance Coin',    'binancecoin',    'USD', 1),
  ('USDT', 'Tether',          'tether',         'USD', 1),
  ('CRO',  'Crypto.com Coin', 'crypto-com-chain','USD', 1),
  ('SOL',  'Solana',          'solana',         'USD', 1),
  ('USDC', 'USD Coin',        'usd-coin',       'USD', 1),
  ('MATIC','Polygon',         'matic-network',  'USD', 0),
  ('XRP',  'XRP',             'ripple',         'USD', 0)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `coingecko_id` = VALUES(`coingecko_id`),
  `rate_currency_code` = VALUES(`rate_currency_code`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `static_pages` (`slug`, `title`, `body`, `page_type`, `is_system`, `is_active`) VALUES
  ('contact-help', 'Contact Help', '<p>Use live chat for the fastest response. You can also contact us by email.</p>', 'system', 1, 1),
  ('copyright', 'Copyright', '<p>All rights reserved.</p>', 'system', 1, 1),
  ('faq-1-en', 'How do I pay with crypto?', '<p>1. Open your unpaid order and choose <strong>Pay with crypto</strong>.</p><p>2. Select one of your assigned active wallets.</p><p>3. Send the exact amount shown in the panel.</p><p>4. Wait for manual confirmation from support.</p>', 'system', 1, 1),
  ('faq-2-en', 'How long does activation take?', '<p>Most activations are completed shortly after payment confirmation.</p><p>If the order needs manual verification, support will update you in live chat.</p>', 'system', 1, 1),
  ('faq-3-en', 'How do I extend an active subscription?', '<p>Open the order list and choose <strong>Extend</strong> for the active subscription.</p><p>You can then select another package period from the same provider if more options are available.</p>', 'system', 1, 1),
  ('faq-4-en', 'What should I send after a bank transfer?', '<p>Please send your transfer confirmation to the support email address shown in the payment instructions.</p><p>You can also use live chat if you need faster help.</p>', 'system', 1, 1),
  ('faq-5-en', 'My stream is not working. What should I do?', '<p>Restart the app or device first.</p><p>Then check whether your subscription is active and fully paid.</p><p>If the issue remains, contact support in live chat and include the device or app name.</p>', 'system', 1, 1),
  ('faq-1-pl', 'Jak zapłacić kryptowalutą?', '<p>1. Otwórz nieopłacone zamówienie i wybierz <strong>Pay with crypto</strong>.</p><p>2. Wybierz jeden z przypisanych aktywnych portfeli.</p><p>3. Wyślij dokładnie taką kwotę, jaka jest pokazana w panelu.</p><p>4. Poczekaj na ręczne potwierdzenie płatności przez support.</p>', 'system', 1, 1),
  ('faq-2-pl', 'Jak długo trwa aktywacja?', '<p>Większość aktywacji jest realizowana krótko po potwierdzeniu płatności.</p><p>Jeśli zamówienie wymaga ręcznej weryfikacji, support zaktualizuje status na live chacie.</p>', 'system', 1, 1),
  ('faq-3-pl', 'Jak przedłużyć aktywną subskrypcję?', '<p>Otwórz listę zamówień i wybierz <strong>Extend</strong> przy aktywnej subskrypcji.</p><p>Następnie możesz wybrać inny okres pakietu od tego samego providera, jeśli są dostępne inne opcje.</p>', 'system', 1, 1),
  ('faq-4-pl', 'Co wysłać po przelewie bankowym?', '<p>Wyślij potwierdzenie przelewu na adres supportu pokazany w instrukcjach płatności.</p><p>Jeśli potrzebujesz szybszej pomocy, możesz też użyć live chatu.</p>', 'system', 1, 1),
  ('faq-5-pl', 'Stream nie działa. Co zrobić?', '<p>Najpierw uruchom ponownie aplikację lub urządzenie.</p><p>Następnie sprawdź, czy subskrypcja jest aktywna i opłacona.</p><p>Jeśli problem nadal występuje, napisz do supportu na live chacie i podaj nazwę urządzenia lub aplikacji.</p>', 'system', 1, 1),
  ('faq-1-de', 'Wie bezahle ich mit Krypto?', '<p>1. Öffne deine unbezahlte Bestellung und wähle <strong>Pay with crypto</strong>.</p><p>2. Wähle eine deiner zugewiesenen aktiven Wallets.</p><p>3. Sende genau den Betrag, der im Panel angezeigt wird.</p><p>4. Warte auf die manuelle Bestätigung durch den Support.</p>', 'system', 1, 1),
  ('faq-2-de', 'Wie lange dauert die Aktivierung?', '<p>Die meisten Aktivierungen werden kurz nach der Zahlungsbestätigung abgeschlossen.</p><p>Falls die Bestellung manuell geprüft werden muss, informiert dich der Support im Live-Chat.</p>', 'system', 1, 1),
  ('faq-3-de', 'Wie verlängere ich ein aktives Abonnement?', '<p>Öffne die Bestellliste und wähle <strong>Extend</strong> für das aktive Abonnement.</p><p>Danach kannst du bei demselben Anbieter einen anderen Paketzeitraum auswählen, wenn weitere Optionen verfügbar sind.</p>', 'system', 1, 1),
  ('faq-4-de', 'Was soll ich nach einer Banküberweisung senden?', '<p>Bitte sende deine Überweisungsbestätigung an die Support-E-Mail-Adresse aus den Zahlungsanweisungen.</p><p>Wenn du schnellere Hilfe brauchst, kannst du auch den Live-Chat verwenden.</p>', 'system', 1, 1),
  ('faq-5-de', 'Mein Stream funktioniert nicht. Was soll ich tun?', '<p>Starte zuerst die App oder das Gerät neu.</p><p>Prüfe danach, ob dein Abonnement aktiv und bezahlt ist.</p><p>Wenn das Problem weiterhin besteht, kontaktiere den Support im Live-Chat und nenne den Geräte- oder App-Namen.</p>', 'system', 1, 1)
ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `body` = VALUES(`body`);

INSERT INTO `email_templates` (`template_key`, `name`, `subject`, `body_html`, `is_system`, `is_active`) VALUES
  ('account-activation', 'Account created notification', 'Your account is ready', 'Hello,\n\nYour account in {site_name} is ready.\n\nLog in here:\n{login_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('password-reset', 'Password reset', 'Your new password', 'Hello {customer_email},\n\nYour new password is:\n{password}\n\nLog in and change it as soon as possible.\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('payment-request-created', 'Payment request notification', 'Payment is waiting', 'Hello,\n\nA payment request is waiting in your account.\n\nLog in to the website to complete the payment:\n{payment_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('order-paid', 'Order paid notification', 'Payment confirmed', 'Hello,\n\nYour payment has been confirmed.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('order-activated', 'Order activated notification', 'Your subscription is active', 'Hello,\n\nYour subscription is now active.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('live-chat-admin-notify', 'Live chat admin notification', 'You have a new message [Support]', 'Hello,\n\nA customer sent a new message in {site_name} live chat.\n\nCustomer: {customer_email}\n\nLog in to the admin panel to reply:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('live-chat-customer-notify', 'Live chat customer notification', 'You have a new message [Support]', 'Hello,\n\nYou received a new message in {site_name}.\n\nLog in to your account to check Live Chat:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}\n\nThis message was generated automatically.', 1, 1),
  ('news-broadcast', 'News notification', 'New important information', 'Hello,\n\nA new important message was published in {site_name}.\n\nLog in to your account and open News:\n{news_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('account-blocked', 'Account blocked notification', 'Your account was blocked', 'Hello,\n\nYour account in {site_name} has been blocked.\n\nIf you need help, contact support through the website:\n{site_url}\n\nRegards,\n{site_name}', 1, 1),
  ('support-payment-request-notify', 'Support payment request notification', 'Customer started payment [Support]', 'Hello,\n\nA customer started the payment process in {site_name}.\n\nCustomer: {customer_email}\nPayment type: {payment_type}\n\nOpen the admin panel:\n{admin_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `subject` = VALUES(`subject`),
  `body_html` = VALUES(`body_html`);

INSERT INTO `email_template_translations` (`template_id`, `locale_code`, `subject`, `body_text`)
SELECT et.id, 'en',
  CASE et.template_key
    WHEN 'account-activation' THEN 'Your account is ready'
    WHEN 'password-reset' THEN 'Your new password'
    WHEN 'payment-request-created' THEN 'Payment is waiting'
    WHEN 'order-paid' THEN 'Payment confirmed'
    WHEN 'order-activated' THEN 'Your subscription is active'
    WHEN 'live-chat-admin-notify' THEN 'You have a new message [Support]'
    WHEN 'live-chat-customer-notify' THEN 'You have a new message [Support]'
    WHEN 'news-broadcast' THEN 'New important information'
    WHEN 'account-blocked' THEN 'Your account was blocked'
    WHEN 'support-payment-request-notify' THEN 'Customer started payment [Support]'
  END,
  CASE et.template_key
    WHEN 'account-activation' THEN 'Hello,\n\nYour account in {site_name} is ready.\n\nLog in here:\n{login_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'password-reset' THEN 'Hello {customer_email},\n\nYour new password is:\n{password}\n\nLog in and change it as soon as possible.\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'payment-request-created' THEN 'Hello,\n\nA payment request is waiting in your account.\n\nLog in to the website to complete the payment:\n{payment_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'order-paid' THEN 'Hello,\n\nYour payment has been confirmed.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'order-activated' THEN 'Hello,\n\nYour subscription is now active.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'live-chat-admin-notify' THEN 'Hello,\n\nA customer sent a new message in {site_name} live chat.\n\nCustomer: {customer_email}\n\nLog in to the admin panel to reply:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'live-chat-customer-notify' THEN 'Hello,\n\nYou received a new message in {site_name}.\n\nLog in to your account to check Live Chat:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}\n\nThis message was generated automatically.'
    WHEN 'news-broadcast' THEN 'Hello,\n\nA new important message was published in {site_name}.\n\nLog in to your account and open News:\n{news_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'account-blocked' THEN 'Hello,\n\nYour account in {site_name} has been blocked.\n\nIf you need help, contact support through the website:\n{site_url}\n\nRegards,\n{site_name}'
    WHEN 'support-payment-request-notify' THEN 'Hello,\n\nA customer started the payment process in {site_name}.\n\nCustomer: {customer_email}\nPayment type: {payment_type}\n\nOpen the admin panel:\n{admin_url}\n\nRegards,\n{site_name}\n{site_url}'
  END
FROM `email_templates` et
WHERE et.template_key IN (
  'account-activation',
  'password-reset',
  'payment-request-created',
  'order-paid',
  'order-activated',
  'live-chat-admin-notify',
  'live-chat-customer-notify',
  'news-broadcast',
  'account-blocked',
  'support-payment-request-notify'
)
ON DUPLICATE KEY UPDATE
  `subject` = VALUES(`subject`),
  `body_text` = VALUES(`body_text`);

INSERT INTO `email_template_translations` (`template_id`, `locale_code`, `subject`, `body_text`)
SELECT et.id, 'pl',
  CASE et.template_key
    WHEN 'account-activation' THEN 'Twoje konto jest gotowe'
    WHEN 'password-reset' THEN 'Twoje nowe hasło'
    WHEN 'payment-request-created' THEN 'Płatność oczekuje'
    WHEN 'order-paid' THEN 'Płatność została potwierdzona'
    WHEN 'order-activated' THEN 'Subskrypcja jest aktywna'
    WHEN 'live-chat-admin-notify' THEN 'Masz nową wiadomość [Support]'
    WHEN 'live-chat-customer-notify' THEN 'Masz nową wiadomość [Support]'
    WHEN 'news-broadcast' THEN 'Nowa ważna informacja'
    WHEN 'account-blocked' THEN 'Twoje konto zostało zablokowane'
    WHEN 'support-payment-request-notify' THEN 'Klient rozpoczął płatność [Support]'
  END,
  CASE et.template_key
    WHEN 'account-activation' THEN 'Witaj,\n\nTwoje konto w {site_name} jest gotowe.\n\nZaloguj się tutaj:\n{login_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'password-reset' THEN 'Witaj {customer_email},\n\nTwoje nowe hasło to:\n{password}\n\nZaloguj się i zmień je jak najszybciej.\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'payment-request-created' THEN 'Witaj,\n\nNa Twoim koncie oczekuje płatność.\n\nZaloguj się na stronę, aby ją dokończyć:\n{payment_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'order-paid' THEN 'Witaj,\n\nTwoja płatność została potwierdzona.\n\nZaloguj się na swoje konto, aby sprawdzić szczegóły:\n{order_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'order-activated' THEN 'Witaj,\n\nTwoja subskrypcja jest już aktywna.\n\nZaloguj się na swoje konto, aby sprawdzić szczegóły:\n{order_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'live-chat-admin-notify' THEN 'Witaj,\n\nKlient wysłał nową wiadomość na live chacie w {site_name}.\n\nKlient: {customer_email}\n\nZaloguj się do panelu admina, aby odpowiedzieć:\n{chat_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'live-chat-customer-notify' THEN 'Witaj,\n\nOtrzymałeś nową wiadomość w {site_name}.\n\nZaloguj się na swoje konto, aby sprawdzić Live Chat:\n{chat_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}\n\nTa wiadomość została wygenerowana automatycznie.'
    WHEN 'news-broadcast' THEN 'Witaj,\n\nW {site_name} została opublikowana nowa ważna informacja.\n\nZaloguj się na swoje konto i otwórz News:\n{news_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'account-blocked' THEN 'Witaj,\n\nTwoje konto w {site_name} zostało zablokowane.\n\nJeśli potrzebujesz pomocy, skontaktuj się ze wsparciem przez stronę:\n{site_url}\n\nPozdrawiamy,\n{site_name}'
    WHEN 'support-payment-request-notify' THEN 'Witaj,\n\nKlient rozpoczął proces płatności w {site_name}.\n\nKlient: {customer_email}\nTyp płatności: {payment_type}\n\nOtwórz panel admina:\n{admin_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
  END
FROM `email_templates` et
WHERE et.template_key IN (
  'account-activation',
  'password-reset',
  'payment-request-created',
  'order-paid',
  'order-activated',
  'live-chat-admin-notify',
  'live-chat-customer-notify',
  'news-broadcast',
  'account-blocked',
  'support-payment-request-notify'
)
ON DUPLICATE KEY UPDATE
  `subject` = VALUES(`subject`),
  `body_text` = VALUES(`body_text`);

INSERT INTO `support_quick_replies` (`locale_code`, `title`, `message_body`, `is_active`, `sort_order`) VALUES
  ('en', 'Payment instructions', 'Please open the payment page in your account and follow the instructions shown there.\n\nIf you send a bank transfer or crypto payment from an external wallet or app, make sure the full amount reaches our account or wallet.', 1, 10),
  ('pl', 'Instrukcja płatności', 'Otwórz proszę stronę płatności w swoim koncie i postępuj zgodnie z widoczną instrukcją.\n\nJeśli wysyłasz przelew lub krypto z zewnętrznego portfela albo aplikacji, upewnij się, że pełna kwota dotrze do naszego konta lub portfela.', 1, 10),
  ('en', 'Restart app / device', 'Please restart the app and your device first.\n\nThen check if the subscription is active in your account and try again.', 1, 20),
  ('pl', 'Restart aplikacji / urządzenia', 'Najpierw zrestartuj proszę aplikację i urządzenie.\n\nNastępnie sprawdź, czy subskrypcja jest aktywna na koncie, i spróbuj ponownie.', 1, 20),
  ('en', 'Send payment confirmation', 'Please send the payment confirmation in this chat.\n\nAfter verification we will update the order status in your account.', 1, 30),
  ('pl', 'Wyślij potwierdzenie płatności', 'Wyślij proszę potwierdzenie płatności w tym czacie.\n\nPo weryfikacji zaktualizujemy status zamówienia na Twoim koncie.', 1, 30),
  ('en', 'Need app help', 'If you need help with setup, open the Apps page in your account and choose the app for your device.\n\nIf you still need support, write which app and device you use.', 1, 40),
  ('pl', 'Pomoc z aplikacją', 'Jeśli potrzebujesz pomocy z konfiguracją, otwórz podstronę Apps na swoim koncie i wybierz aplikację dla swojego urządzenia.\n\nJeśli nadal potrzebujesz wsparcia, napisz z jakiej aplikacji i urządzenia korzystasz.', 1, 40)
ON DUPLICATE KEY UPDATE
  `message_body` = VALUES(`message_body`),
  `is_active` = VALUES(`is_active`),
  `sort_order` = VALUES(`sort_order`);
