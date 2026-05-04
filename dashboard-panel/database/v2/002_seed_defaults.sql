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
  `customer_messenger_enabled`,
  `customer_direct_chat_enabled`,
  `customer_group_chat_enabled`,
  `customer_global_group_enabled`,
  `messenger_voice_enabled`,
  `demo_messenger_showcase_enabled`,
  `demo_messenger_showcase_last_tick_at`,
  `demo_messenger_showcase_last_global_tick_at`,
  `demo_messenger_showcase_last_private_tick_at`,
  `contact_form_enabled`,
  `referrals_enabled`,
  `apps_page_enabled`,
  `apps_url`,
  `application_instructions_enabled`,
  `page_guidance_enabled`,
  `payment_test_mode_notice_enabled`,
  `crypto_daily_backup_enabled`,
  `crypto_daily_backup_email`,
  `crypto_daily_backup_last_processed_date`,
  `manual_database_backup_last_downloaded_at`,
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
  NULL,
  1,
  1,
  NULL,
  0,
  0,
  0,
  0,
  0,
  0,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
  0,
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
  `customer_messenger_enabled` = VALUES(`customer_messenger_enabled`),
  `customer_direct_chat_enabled` = VALUES(`customer_direct_chat_enabled`),
  `customer_group_chat_enabled` = VALUES(`customer_group_chat_enabled`),
  `customer_global_group_enabled` = VALUES(`customer_global_group_enabled`),
  `messenger_voice_enabled` = VALUES(`messenger_voice_enabled`),
  `demo_messenger_showcase_enabled` = VALUES(`demo_messenger_showcase_enabled`),
  `demo_messenger_showcase_last_tick_at` = VALUES(`demo_messenger_showcase_last_tick_at`),
  `demo_messenger_showcase_last_global_tick_at` = VALUES(`demo_messenger_showcase_last_global_tick_at`),
  `demo_messenger_showcase_last_private_tick_at` = VALUES(`demo_messenger_showcase_last_private_tick_at`),
  `credits_sales_enabled` = VALUES(`credits_sales_enabled`),
  `contact_form_enabled` = VALUES(`contact_form_enabled`),
  `referrals_enabled` = VALUES(`referrals_enabled`),
  `apps_page_enabled` = VALUES(`apps_page_enabled`),
  `apps_url` = VALUES(`apps_url`),
  `application_instructions_enabled` = VALUES(`application_instructions_enabled`),
  `page_guidance_enabled` = VALUES(`page_guidance_enabled`),
  `payment_test_mode_notice_enabled` = VALUES(`payment_test_mode_notice_enabled`),
  `crypto_daily_backup_enabled` = VALUES(`crypto_daily_backup_enabled`),
  `crypto_daily_backup_email` = VALUES(`crypto_daily_backup_email`),
  `crypto_daily_backup_last_processed_date` = VALUES(`crypto_daily_backup_last_processed_date`),
  `manual_database_backup_last_downloaded_at` = VALUES(`manual_database_backup_last_downloaded_at`),
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

INSERT INTO `admin_help_topics` (`question`, `answer_html`, `audience_code`, `keywords`, `sort_order`, `is_active`)
SELECT seed.question, seed.answer_html, seed.audience_code, seed.keywords, seed.sort_order, seed.is_active
FROM (
  SELECT 'Jak dodać nowego użytkownika?' AS question, '<p>Wejdź w zakładkę Użytkownicy i użyj formularza dodawania nowego konta.</p><p>Wpisz email, a login i handle mogą uzupełnić się automatycznie.</p><p>Po zapisaniu konto od razu pojawi się na liście i w wyszukiwarce panelu.</p>' AS answer_html, 'admin' AS audience_code, 'user klient reseller konto rejestracja dodawanie' AS keywords, 10 AS sort_order, 1 AS is_active
  UNION ALL SELECT 'Jak dodać środki do salda użytkownika?', '<p>Otwórz kartę użytkownika i przejdź do sekcji działań na koncie.</p><p>Możesz tam dodać środki ręcznie albo poprowadzić użytkownika przez standardowe doładowanie.</p><ul><li>Ręczne dodanie działa od razu.</li><li>Doładowanie tworzy ślad płatności i jest lepsze do rozliczeń.</li></ul>', 'admin', 'saldo balance credits doładowanie środki', 20, 1
  UNION ALL SELECT 'Jak utworzyć ticket płatności za zamówienie?', '<p>Wejdź w zamówienie albo otwórz rozmowę z klientem i użyj akcji generowania płatności.</p><p>Wybierz kryptowalutę lub przelew bankowy, a system przypisze adres albo konto.</p><p>Po utworzeniu ticketu klient zobaczy instrukcję, a Ty dostaniesz ślad w płatnościach.</p>', 'admin', 'płatność crypto bank transfer request zamówienie ticket', 30, 1
  UNION ALL SELECT 'Jak utworzyć grupę na chacie?', '<p>W messengerze użyj tworzenia nowej rozmowy i dodaj co najmniej jedną osobę.</p><p>Jedno zaproszenie utworzy rozmowę 1 na 1, a kilka osób stworzy grupę.</p><p>Po utworzeniu możesz dalej dopraszać członków i ustawić auto-usuwanie wiadomości.</p>', 'all', 'grupa group chat messenger rozmowa zaproszenie reseller', 40, 1
  UNION ALL SELECT 'Jak ukryć providera tylko dla jednego użytkownika?', '<p>Na karcie użytkownika znajdziesz sekcję widoczności providerów.</p><p>Odznacz providera, którego nie chcesz pokazywać temu kontu.</p><p>Od tej chwili użytkownik nie zobaczy tego providera ani jego produktów w dodawaniu nowej subskrypcji.</p>', 'admin', 'provider widoczność produkty ukrywanie user dostęp', 50, 1
  UNION ALL SELECT 'Jak klient lub reseller ma zapłacić za zamówienie?', '<p>Po wejściu w zamówienie wybierz metodę płatności i przejdź do kolejnego kroku.</p><p>System pokaże dokładną kwotę, dane portfela albo dane przelewu i czas ważności.</p><p>Po opłaceniu wystarczy wysłać potwierdzenie, jeśli dana metoda tego wymaga.</p>', 'client', 'jak zapłacić krypto bank przelew zamówienie klient reseller', 60, 1
  UNION ALL SELECT 'Jak reseller zmienia avatar i własny nick?', '<p>W ustawieniach profilu możesz kliknąć avatar, wgrać nowy obrazek i od razu zobaczyć podgląd.</p><p>Nick jest edytowalny z poziomu ustawień i potem pokazuje się w messengerze.</p><p>Jeśli zmieniasz zdjęcie, najlepiej używać prostych grafik JPG, PNG albo WEBP.</p>', 'reseller', 'avatar nick handle ustawienia reseller profil', 70, 1
  UNION ALL SELECT 'Jak znaleźć konkretną osobę lub zamówienie w adminie?', '<p>Użyj globalnej wyszukiwarki w górnym pasku panelu.</p><p>Dla użytkowników możesz szukać po emailu albo po nicku zaczynając od znaku @.</p><p>Dla zamówień i portfeli wyniki prowadzą od razu do właściwego widoku szczegółowego.</p>', 'admin', 'wyszukiwarka email handle @nick zamówienie portfel admin', 80, 1
  UNION ALL SELECT 'Ręczne dodanie zamówienia dla klienta', '<p>Wejdź w kartę użytkownika albo w sekcję zamówień i użyj opcji dodania nowego zamówienia.</p><p>Wybierz providera, produkt i okres subskrypcji, a potem przejdź do zapisania formularza.</p><p>Po utworzeniu zamówienia możesz od razu wygenerować ticket płatności albo aktywować je ręcznie.</p>', 'admin', 'zamówienie ręczne create order klient admin', 90, 1
  UNION ALL SELECT 'Potwierdzanie płatności krypto', '<p>Otwórz sekcję Payments i przejdź do oczekującej płatności krypto.</p><p>Sprawdź kwotę, adres portfela i hash transakcji, a następnie porównaj dane z explorerem blockchain.</p><p>Jeśli wszystko się zgadza, zatwierdź płatność i dopiero potem przejdź do realizacji zamówienia.</p>', 'admin', 'krypto płatność potwierdzenie crypto verify', 100, 1
  UNION ALL SELECT 'Potwierdzanie przelewu bankowego', '<p>W Payments otwórz oczekujący przelew bankowy i porównaj dane z potwierdzeniem od klienta.</p><p>Zwróć uwagę na kwotę, nazwę płatnika i czas wpływu środków.</p><p>Po zatwierdzeniu płatności zamówienie będzie można bezpiecznie aktywować.</p>', 'admin', 'bank przelew płatność potwierdzenie payment', 110, 1
  UNION ALL SELECT 'Odrzucanie płatności i czyszczenie anulowanych wpisów', '<p>Jeśli płatność ma błędne dane albo wygasła, oznacz ją jako odrzuconą lub anulowaną.</p><p>W sekcji Payments możesz potem jednym kliknięciem usunąć anulowane wpisy z bieżącego widoku.</p><p>To porządkuje listę i zostawia tylko realne sprawy do obsługi.</p>', 'admin', 'odrzucenie anulowane failed cleanup payments', 120, 1
  UNION ALL SELECT 'Dodawanie nowego portfela krypto do puli', '<p>Przejdź do Crypto Wallets i dodaj nowy adres dla wybranego aktywa.</p><p>Uzupełnij nazwę, właściciela i sam adres portfela, a potem zapisz rekord.</p><p>Nowy portfel od razu trafi do puli dostępnej dla płatności, jeśli ma status aktywny.</p>', 'admin', 'portfel wallet crypto pula adres dodawanie', 130, 1
  UNION ALL SELECT 'Ręczne przypisanie portfela do klienta', '<p>Otwórz profil klienta albo listę portfeli i wybierz akcję ręcznego przypisania.</p><p>Po przypisaniu dany adres będzie widoczny przy generowaniu płatności dla tego użytkownika.</p><p>Jeśli współdzielenie portfeli jest wyłączone, jeden adres powinien pozostać przypisany tylko do jednego konta.</p>', 'admin', 'przypisanie portfela wallet klient ręcznie', 140, 1
  UNION ALL SELECT 'Dodawanie konta bankowego do płatności', '<p>Wejdź w Bank Transfers i utwórz nowe konto z kompletem danych do przelewu.</p><p>Najważniejsze pola to właściciel konta, bank, IBAN lub numer rachunku oraz SWIFT.</p><p>Po zapisaniu konto będzie mogło zostać przypisane do nowych ticketów płatności bankowej.</p>', 'admin', 'konto bankowe iban swift przelew bank add', 150, 1
  UNION ALL SELECT 'Tworzenie produktu trial 6h, 12h lub 24h', '<p>W produktach wybierz krótki okres 6h, 12h albo 24h, a system potraktuje go jako trial.</p><p>Przy tych wariantach pole trial jest pilnowane także po stronie backendu.</p><p>Dzięki temu krótkie pakiety nie będą mylone ze zwykłą sprzedażą miesięczną lub roczną.</p>', 'admin', 'trial produkt 6h 12h 24h pakiet', 160, 1
  UNION ALL SELECT 'Włączanie i wyłączanie sprzedaży w panelu', '<p>W Settings możesz osobno sterować sprzedażą subskrypcji, triali, creditsów i płatności.</p><p>Po wyłączeniu danej funkcji użytkownik nie zobaczy odpowiednich akcji w swoim panelu.</p><p>To wygodne rozwiązanie, gdy chcesz zrobić przerwę techniczną bez blokowania całej strony.</p>', 'admin', 'sprzedaż sales settings on off', 170, 1
  UNION ALL SELECT 'Blokowanie konta użytkownika', '<p>Na karcie użytkownika możesz zmienić status konta, gdy trzeba wstrzymać dostęp.</p><p>Po blokadzie konto nadal pozostaje w bazie, ale użytkownik nie powinien korzystać z panelu jak aktywne konto.</p><p>To lepsze niż kasowanie danych, jeśli sprawa wymaga tylko czasowego zatrzymania dostępu.</p>', 'admin', 'blokada block user status konto', 180, 1
  UNION ALL SELECT 'Reset hasła użytkownika', '<p>Otwórz profil użytkownika i użyj akcji resetu hasła albo wygenerowania nowego dostępu.</p><p>Po zmianie hasła warto od razu poinformować użytkownika, aby zalogował się i ustawił własne dane.</p><p>Jeśli konto jest używane przez resellera, dobrze też sprawdzić czy email powiadomień jest aktualny.</p>', 'admin', 'reset hasła password user klient reseller', 190, 1
  UNION ALL SELECT 'Dodawanie newsa lub komunikatu na stronę', '<p>W sekcji News dodaj nowy wpis z tytułem, treścią i statusem aktywności.</p><p>Możesz zaplanować publikację albo ukryć wpis bez jego usuwania.</p><p>To dobre miejsce na komunikaty o przerwach technicznych, zmianach cen lub nowych funkcjach.</p>', 'admin', 'news komunikat wpis aktualność strona', 200, 1
  UNION ALL SELECT 'Edycja szablonów email', '<p>W Email Templates zmienisz temat i treść wiadomości wysyłanych automatycznie przez system.</p><p>Zachowaj placeholdery typu {site_name} albo {payment_url}, bo są podmieniane dynamicznie.</p><p>Po zapisaniu najlepiej od razu sprawdzić efekt na prostym scenariuszu testowym.</p>', 'admin', 'email templates szablon wiadomość powiadomienie', 210, 1
  UNION ALL SELECT 'Wykonanie ręcznego backupu SQL', '<p>Wejdź do Settings i użyj przycisku pobrania backupu bazy danych.</p><p>Plik zapisze się na Twój dysk jako pełny eksport SQL z tabelami, widokami i danymi.</p><p>Warto robić taki backup regularnie, szczególnie przed większymi zmianami w panelu albo importem danych.</p>', 'admin', 'backup sql baza export settings', 220, 1
  UNION ALL SELECT 'Weryfikacja kończących się subskrypcji', '<p>W powiadomieniach topbara zobaczysz listę subskrypcji, które kończą się w ciągu najbliższych 24 godzin.</p><p>Możesz z niej przejść do użytkownika, zamówienia albo dashboardu providera.</p><p>To pomaga szybko wychwycić konta wymagające kontaktu, odnowienia lub ręcznej kontroli.</p>', 'admin', 'subskrypcja wygasa expires soon renewal', 230, 1
  UNION ALL SELECT 'Korzystanie z powiadomień w górnym pasku', '<p>Ikona powiadomień zbiera najważniejsze rzeczy do obsługi, zaczynając od płatności i zamówień.</p><p>Niżej zobaczysz także nowe konta, wygasające subskrypcje i przypomnienia o backupie.</p><p>Dzięki temu nie musisz ręcznie sprawdzać każdej sekcji panelu po kolei.</p>', 'admin', 'powiadomienia topbar bell payments orders backup', 240, 1
  UNION ALL SELECT 'Zapraszanie resellera do rozmowy 1 na 1', '<p>W messengerze użyj tworzenia nowej rozmowy i wpisz email resellera albo jego @nick.</p><p>Jedna zaakceptowana osoba tworzy od razu prywatną rozmowę 1 na 1.</p><p>Adminów nie da się dodać z tego poziomu, więc lista wyników pokazuje tylko dozwolone konta resellerów.</p>', 'reseller', 'reseller direct chat zaproszenie 1 na 1 messenger', 250, 1
  UNION ALL SELECT 'Zarządzanie powiadomieniami email w rozmowie', '<p>W nagłówku rozmowy otwórz menu ustawień i użyj przełącznika powiadomień email dla tej konkretnej rozmowy.</p><p>Wyłączenie działa dla grup i rozmów reseller-reseller, ale nie zastępuje głównego chatu Support.</p><p>To dobre rozwiązanie, gdy chcesz ograniczyć spam bez wyłączania całych maili konta.</p>', 'reseller', 'email powiadomienia rozmowa mute messenger reseller', 260, 1
  UNION ALL SELECT 'Ustawienie auto-usuwania wiadomości w grupie', '<p>Twórca grupy może w ustawieniach rozmowy wybrać czas auto-usuwania wiadomości.</p><p>Dostępne są krótkie przedziały, dzięki którym rozmowa sama się porządkuje bez ręcznego kasowania.</p><p>Informacja o aktywnym czasie kasowania pokazuje się też nad historią wiadomości.</p>', 'reseller', 'auto-usuwanie retention grupa chat wiadomości', 270, 1
  UNION ALL SELECT 'Wysyłanie potwierdzenia płatności przez klienta', '<p>Po opłaceniu zamówienia klient powinien otworzyć live chat albo instrukcję płatności i wysłać potwierdzenie, jeśli dana metoda tego wymaga.</p><p>Najważniejsze są czytelny zrzut albo PDF z kwotą oraz czasem wykonania płatności.</p><p>To przyspiesza weryfikację i ogranicza potrzebę dodatkowych pytań od supportu.</p>', 'client', 'klient potwierdzenie płatność support chat bank', 280, 1
  UNION ALL SELECT 'Jak uruchomić maintenance runner ręcznie?', '<p>W Settings znajdziesz sekcję maintenance z przyciskiem ręcznego uruchomienia procesów systemowych.</p><p>Ten runner obsługuje kolejkę maili, wygasanie płatności i reguły automatycznego czyszczenia.</p><p>To przydatne po większych zmianach lub gdy chcesz od razu wymusić wykonanie zadań bez czekania na cron.</p>', 'admin', 'maintenance runner cron ręcznie cleanup email queue', 290, 1
  UNION ALL SELECT 'Jak sprawdzić, dlaczego użytkownik nie widzi produktu?', '<p>Najpierw sprawdź, czy produkt i provider są aktywne oraz czy globalna sprzedaż nie jest wyłączona.</p><p>Potem zobacz na karcie użytkownika, czy provider nie został dla niego ukryty indywidualnie.</p><p>Jeśli to trial albo credits, upewnij się też, że odpowiedni switch w Settings jest włączony.</p>', 'admin', 'produkt niewidoczny user provider trial sales', 300, 1
  UNION ALL SELECT 'Jak działa testowy tryb płatności?', '<p>W Settings możesz włączyć ostrzeżenie testowe dla płatności.</p><p>Użytkownik nadal wygeneruje request krypto albo bankowy, ale na ekranie zobaczy wyraźny komunikat, aby nic nie wpłacać.</p><p>To dobre rozwiązanie przy testach, migracji lub sprawdzaniu nowych danych płatniczych.</p>', 'admin', 'test mode płatności warning settings', 310, 1
  UNION ALL SELECT 'Jak zmienić logo, nazwę i adres strony?', '<p>Wejdź do Settings i edytuj pola nazwy strony, tytułu, adresu URL oraz logo.</p><p>Zmiany wpływają na wygląd panelu, wiadomości email i część linków generowanych przez system.</p><p>Po zapisaniu warto odświeżyć stronę i sprawdzić, czy nowe logo oraz adres pokazują się poprawnie.</p>', 'admin', 'logo site name title url settings', 320, 1
  UNION ALL SELECT 'Jak zmienić domyślną walutę serwisu?', '<p>Domyślną walutę ustawisz w Settings w polu waluty głównej serwisu.</p><p>Wpływa ona na salda, podsumowania, kalkulator krypto i część wyliczeń płatności.</p><p>Po zmianie waluty warto sprawdzić kursy aktywów i kilka przykładowych zamówień, aby upewnić się, że wszystko liczy się zgodnie z oczekiwaniem.</p>', 'admin', 'default currency waluta settings usd eur', 330, 1
  UNION ALL SELECT 'Jak korzystać z kalkulatora wymiany krypto?', '<p>W górnym pasku admina otwórz kalkulator wymiany i wpisz kwotę FIAT.</p><p>Po wyborze aktywa system pokaże, ile powinno wyjść w danej kryptowalucie oraz kiedy kurs był ostatnio aktualizowany.</p><p>Kliknięcie w wynik krypto kopiuje wartość do schowka, co przyspiesza obsługę klienta.</p>', 'admin', 'kalkulator wymiany crypto converter kurs admin', 340, 1
  UNION ALL SELECT 'Jak odświeżyć kurs kryptowaluty ręcznie?', '<p>W kalkulatorze wymiany przy dacie aktualizacji kursu pojawi się ikona odświeżenia, jeśli kurs jest starszy niż 15 minut.</p><p>Po kliknięciu system pobierze świeży kurs dla waluty głównej serwisu z używanego źródła notowań.</p><p>To pozwala uniknąć zbyt częstego odpytywania API, a jednocześnie daje ręczną kontrolę, gdy naprawdę jest potrzebna.</p>', 'admin', 'kurs crypto refresh coingecko rate updated', 350, 1
  UNION ALL SELECT 'Jak sprawdzić, czy portfel jest naprawdę wolny?', '<p>Sama lista portfeli to nie wszystko, bo adres może być blokowany przez aktywne przypisanie albo otwarty request płatności.</p><p>Najlepiej sprawdzić status portfela, historię przypisań i czy nie ma oczekującego ticketu krypto.</p><p>Dopiero wtedy wiadomo, czy dany adres można bezpiecznie użyć dla kolejnego użytkownika.</p>', 'admin', 'portfel wolny assigned request pending wallet', 360, 1
  UNION ALL SELECT 'Jak otworzyć rozmowę z użytkownikiem z poziomu płatności?', '<p>W wielu miejscach panelu, szczególnie przy płatnościach i powiadomieniach, masz szybkie akcje otwierające rozmowę z danym użytkownikiem.</p><p>To pozwala od razu poprosić o potwierdzenie lub doprecyzować brakujące dane bez szukania klienta ręcznie.</p><p>Dzięki temu obsługa płatności i zamówień jest dużo szybsza.</p>', 'admin', 'chat user płatność payments quick action support', 370, 1
  UNION ALL SELECT 'Jak usunąć całą rozmowę z chatu?', '<p>W liście rozmów użyj akcji usunięcia tylko wtedy, gdy naprawdę chcesz wyczyścić cały wątek.</p><p>System usuwa wtedy samą rozmowę, wiadomości, członków grupy i załączniki powiązane z tym czatem.</p><p>To jest operacja porządkowa, więc przed kliknięciem warto upewnić się, że nic ważnego nie zostanie utracone.</p>', 'admin', 'usunąć rozmowę chat delete conversation attachments', 380, 1
  UNION ALL SELECT 'Jak dodać lub edytować stronę statyczną?', '<p>W sekcji Pages możesz tworzyć i edytować zwykłe podstrony informacyjne.</p><p>To dobre miejsce na regulamin, kontakt, politykę prywatności albo własne treści pomocnicze.</p><p>Po zapisaniu strony sprawdź jej aktywność i slug, aby link publiczny był poprawny.</p>', 'admin', 'strona statyczna pages edycja content', 390, 1
  UNION ALL SELECT 'Jak edytować FAQ dla klientów?', '<p>W zakładce FAQ możesz zmienić krótkie pytania i odpowiedzi widoczne dla użytkowników.</p><p>To dobre miejsce na podstawowe informacje o płatnościach, aktywacji i najczęstszych problemach.</p><p>Jeśli temat dotyczy tylko admina, lepiej dodaj go do modułu Pomoc zamiast do publicznego FAQ.</p>', 'admin', 'faq klient pytania odpowiedzi edycja', 400, 1
  UNION ALL SELECT 'Jak włączyć lub wyłączyć live chat dla użytkowników?', '<p>W Settings znajdziesz przełącznik odpowiedzialny za widoczność live chatu po stronie użytkownika.</p><p>Po wyłączeniu widget, skróty czatu i część akcji powiązanych z supportem znikną z panelu klienta.</p><p>To przydatne, gdy chcesz na chwilę wstrzymać obsługę wiadomości lub zrobić prace techniczne.</p>', 'admin', 'live chat support toggle settings', 410, 1
  UNION ALL SELECT 'Jak sprawdzić ostatnie logowanie klienta?', '<p>Na karcie użytkownika i w wynikach wyszukiwania zobaczysz informację o ostatnim logowaniu.</p><p>Jeśli logowanie było dzisiaj, panel zwykle pokazuje samą godzinę, co ułatwia szybką ocenę aktywności.</p><p>To dobra wskazówka, gdy chcesz sprawdzić, czy klient faktycznie wraca do panelu po wysłaniu instrukcji.</p>', 'admin', 'last login ostatnie logowanie user customer', 420, 1
  UNION ALL SELECT 'Jak włączyć codzienny backup płatności krypto?', '<p>W Settings włącz dzienny backup płatności krypto i wskaż adres email odbiorcy raportu.</p><p>Po północy system wyśle plik CSV tylko wtedy, gdy poprzedniego dnia były potwierdzone płatności.</p><p>To praktyczne zabezpieczenie na wypadek awarii lub potrzeby późniejszego odtworzenia danych.</p>', 'admin', 'daily backup crypto csv settings email', 430, 1
  UNION ALL SELECT 'Jak odczytać raport CSV z płatności krypto?', '<p>W pliku CSV znajdziesz podstawowe dane potrzebne do odtworzenia i audytu opłaconych transakcji krypto.</p><p>Najważniejsze kolumny to użytkownik, email, aktywo, kwota krypto, adres portfela i powiązanie z zamówieniem lub requestem.</p><p>Dzięki temu możesz wrócić do danych nawet wtedy, gdy później trzeba porównać stan z innym systemem.</p>', 'admin', 'csv raport płatności crypto backup', 440, 1
  UNION ALL SELECT 'Jak kontrolować sprzedaż creditsów dla resellerów?', '<p>W Settings możesz osobno włączyć lub wyłączyć sprzedaż produktów typu credits.</p><p>Jeśli przełącznik jest wyłączony, reseller nie zobaczy tych pozycji w swoim panelu zakupowym.</p><p>To pozwala łatwo sterować modelem sprzedaży bez usuwania samych produktów z bazy.</p>', 'admin', 'credits reseller sprzedaż toggle settings', 450, 1
  UNION ALL SELECT 'Jak działają instrukcje aplikacji i podpowiedzi stron?', '<p>W Settings masz osobne przełączniki dla instrukcji aplikacji oraz dla niebieskich podpowiedzi pod stronami.</p><p>Pierwszy steruje poradnikami typu Smart IPTV i podobnymi, a drugi dodatkowymi opisami interfejsu oraz dużym opisem homepage.</p><p>Dzięki rozdzieleniu tych opcji możesz precyzyjnie decydować, ile treści pomocniczych ma widzieć użytkownik.</p>', 'admin', 'instrukcje aplikacji podpowiedzi stron settings', 460, 1
  UNION ALL SELECT 'Jak używać modułu Pomoc w adminie?', '<p>Zielony przycisk w lewym dolnym rogu otwiera szybki modal Pomoc z wyszukiwaniem tematów.</p><p>To najszybszy sposób, aby znaleźć instrukcję bez wychodzenia z aktualnej podstrony.</p><p>Jeśli czegoś brakuje, możesz potem dopisać własny wpis w zakładce Pomoc w sidebarze.</p>', 'admin', 'pomoc help modal search admin tutorial', 470, 1
  UNION ALL SELECT 'Jak dodać własny temat do modułu Pomoc?', '<p>W sidebarze otwórz zakładkę Pomoc i użyj formularza dodania nowego wątku.</p><p>Możesz wskazać odbiorcę, słowa kluczowe, kolejność oraz krótką odpowiedź w HTML.</p><p>Najlepiej pisać zwięźle, tak aby nowa osoba mogła po jednym wpisie od razu wykonać daną czynność.</p>', 'admin', 'dodaj help topic pomoc admin wpis', 480, 1
  UNION ALL SELECT 'Jak działają dwa typy użytkowników w systemie?', '<p>W systemie podstawowo działają dwa typy kont: klient i reseller.</p><p>Klient skupia się na zakupie i obsłudze własnych subskrypcji, a reseller dodatkowo dostaje funkcje salda, creditsów i własnego messengera.</p><p>Przy dodawaniu lub edycji konta warto od razu sprawdzić, jaki typ najlepiej pasuje do sposobu pracy danej osoby.</p>', 'admin', 'typy użytkowników client reseller role konto', 490, 1
  UNION ALL SELECT 'Jak reseller korzysta z rozmów 1 na 1?', '<p>Reseller może rozpocząć prywatną rozmowę 1 na 1 z innym resellerem z poziomu własnego messengera.</p><p>Wystarczy wpisać email drugiej osoby albo jej @nick i wysłać zaproszenie.</p><p>Po zaakceptowaniu rozmowa działa jak osobny, prywatny kanał do szybkich ustaleń.</p>', 'reseller', 'reseller direct chat 1 na 1 messenger', 500, 1
  UNION ALL SELECT 'Jak działają grupy resellerów?', '<p>Reseller może tworzyć grupy, jeśli limit grup w Settings nie jest ustawiony na zero.</p><p>Do grupy można dopraszać innych resellerów, a członkowie akceptują lub odrzucają zaproszenie.</p><p>To wygodne miejsce do pracy zespołowej, przekazywania ustaleń i utrzymywania historii rozmów wewnętrznych.</p>', 'reseller', 'grupy resellerów group chat messenger invite', 510, 1
  UNION ALL SELECT 'Jak admin rozmawia z innymi adminami?', '<p>Admin może wyszukać innego admina w panelu chatu i rozpocząć z nim prywatną rozmowę.</p><p>Taki czat przydaje się do przekazywania zadań, notatek i ustaleń operacyjnych bez mieszania tego z rozmowami klientów.</p><p>Wyszukiwanie działa także po @nick, jeśli administrator ma ustawiony publiczny handle.</p>', 'admin', 'admin do admina chat rozmowa prywatna', 520, 1
  UNION ALL SELECT 'Jak admin tworzy grupę w panelu chatu?', '<p>W rozwiniętym panelu chatu admin może użyć modala tworzenia grupy i dodać do niej wybrane osoby.</p><p>Grupa może służyć zarówno do współpracy między adminami, jak i do ustaleń z resellerami.</p><p>Po utworzeniu grupy można dopraszać kolejne osoby, a twórca grupy ma szersze uprawnienia do zarządzania rozmową.</p>', 'admin', 'admin group chat create grupa messenger', 530, 1
  UNION ALL SELECT 'Kto może usuwać wiadomości w rozmowach grupowych?', '<p>W grupach zwykły uczestnik usuwa tylko własne wiadomości.</p><p>Twórca grupy może usuwać pojedynczo wszystkie wiadomości w tej konkretnej rozmowie.</p><p>Dzięki temu da się utrzymać porządek bez odbierania podstawowej kontroli zwykłym użytkownikom.</p>', 'all', 'usuwanie wiadomości grupa chat owner creator', 540, 1
  UNION ALL SELECT 'Jak działa wybór rodzaju płatności przez admina?', '<p>Przy tworzeniu ticketu płatności admin wybiera, czy ma to być krypto czy przelew bankowy.</p><p>Od tego wyboru zależy, czy system przypisze portfel, czy konto bankowe oraz jakie instrukcje dostanie użytkownik.</p><p>Warto wybrać metodę zgodną z tym, co klient realnie chce opłacić i jak szybko może przesłać potwierdzenie.</p>', 'admin', 'rodzaj płatności admin crypto bank payment type', 550, 1
  UNION ALL SELECT 'Jak wyłączyć przelewy bankowe albo płatności krypto?', '<p>W Settings są osobne przełączniki dla przelewów bankowych i płatności krypto.</p><p>Po wyłączeniu danej metody użytkownik nie zobaczy jej w wyborze płatności, a odpowiednie sekcje panelu mogą zostać ukryte.</p><p>To wygodne przy zmianie dostawcy, aktualizacji danych lub chwilowym wstrzymaniu konkretnego kanału płatności.</p>', 'admin', 'wyłączenie przelewy bankowe crypto payments settings', 560, 1
  UNION ALL SELECT 'Jak działa współdzielenie kont bankowych i adresów krypto?', '<p>W Settings możesz niezależnie zdecydować, czy konta bankowe i adresy krypto mogą być współdzielone między użytkownikami.</p><p>Gdy współdzielenie jest wyłączone, jeden zasób powinien być przypisany tylko do jednej osoby naraz.</p><p>To zwiększa porządek i bezpieczeństwo, ale wymaga utrzymywania większej puli dostępnych danych płatniczych.</p>', 'admin', 'współdzielenie kont bankowych adresów krypto shared assignments', 570, 1
  UNION ALL SELECT 'Jak odróżnić czat Support od rozmów wewnętrznych?', '<p>Główny czat Support służy do kontaktu użytkownika z obsługą i jest oddzielony od rozmów reseller-reseller oraz grup wewnętrznych.</p><p>Rozmowy prywatne i grupowe między resellerami lub adminami są przeznaczone do ustaleń operacyjnych i nie zastępują oficjalnego kontaktu z supportem.</p><p>Dzięki temu łatwiej utrzymać porządek i nie mieszać zgłoszeń klientów z komunikacją zespołową.</p>', 'all', 'support chat rozmowy wewnętrzne reseller admin różnica', 580, 1
) AS seed
WHERE NOT EXISTS (
  SELECT 1
  FROM `admin_help_topics` existing
  WHERE existing.`question` = seed.question
);

INSERT INTO `email_templates` (`template_key`, `name`, `subject`, `body_html`, `is_system`, `is_active`) VALUES
  ('account-activation', 'Account created notification', 'Your account is ready', 'Hello,\n\nYour account in {site_name} is ready.\n\nLog in here:\n{login_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('password-reset', 'Password reset', 'Your new password', 'Hello {customer_email},\n\nYour new password is:\n{password}\n\nLog in and change it as soon as possible.\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('payment-request-created', 'Payment request notification', 'Payment is waiting', 'Hello,\n\nA payment request is waiting in your account.\n\nLog in to the website to complete the payment:\n{payment_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('order-paid', 'Order paid notification', 'Payment confirmed', 'Hello,\n\nYour payment has been confirmed.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('order-activated', 'Order activated notification', 'Your subscription is active', 'Hello,\n\nYour subscription is now active.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('order-expiring-soon', 'Order expiry reminder notification', 'Your subscription expires in 5 days', 'Hello,\n\nThis is a reminder that your subscription will expire soon.\n\nLog in to your account to review the details and renew it in advance:\n{order_url}\n\nExpiry date: {expires_at_local}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('order-expired', 'Order expired notification', 'Your order expired', 'Hello,\n\nYour subscription has expired.\n\nLog in to your account to renew it:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('live-chat-admin-notify', 'Live chat admin notification', 'You have a new message [Support]', 'Hello,\n\nA customer sent a new message in {site_name} live chat.\n\nCustomer: {customer_email}\n\nLog in to the admin panel to reply:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('live-chat-customer-notify', 'Live chat customer notification', 'You have a new message [Support]', 'Hello,\n\nYou received a new message in {site_name}.\n\nLog in to your account to check Live Chat:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}\n\nThis message was generated automatically.', 1, 1),
  ('news-broadcast', 'News notification', 'New important information', 'Hello,\n\nA new important message was published in {site_name}.\n\nLog in to your account and open News:\n{news_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('account-blocked', 'Account blocked notification', 'Your account was blocked', 'Hello,\n\nYour account in {site_name} has been blocked.\n\nIf you need help, contact support through the website:\n{site_url}\n\nRegards,\n{site_name}', 1, 1),
  ('support-payment-request-notify', 'Support payment request notification', 'Customer started payment [Support]', 'Hello,\n\nA customer started the payment process in {site_name}.\n\nCustomer: {customer_email}\nPayment type: {payment_type}\n\nOpen the admin panel:\n{admin_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1),
  ('messenger-invite-notify', 'Messenger invitation notification', 'You received a new messenger invitation', 'Hello,\n\n{sender_label} sent you a messenger invitation in {site_name}.\n\nConversation: {conversation_title}\n\nLog in to your account to accept or reject the invitation:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}', 1, 1)
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
    WHEN 'order-expiring-soon' THEN 'Your subscription expires in 5 days'
    WHEN 'order-expired' THEN 'Your order expired'
    WHEN 'live-chat-admin-notify' THEN 'You have a new message [Support]'
    WHEN 'live-chat-customer-notify' THEN 'You have a new message [Support]'
    WHEN 'news-broadcast' THEN 'New important information'
    WHEN 'account-blocked' THEN 'Your account was blocked'
    WHEN 'support-payment-request-notify' THEN 'Customer started payment [Support]'
    WHEN 'messenger-invite-notify' THEN 'You received a new messenger invitation'
  END,
  CASE et.template_key
    WHEN 'account-activation' THEN 'Hello,\n\nYour account in {site_name} is ready.\n\nLog in here:\n{login_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'password-reset' THEN 'Hello {customer_email},\n\nYour new password is:\n{password}\n\nLog in and change it as soon as possible.\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'payment-request-created' THEN 'Hello,\n\nA payment request is waiting in your account.\n\nLog in to the website to complete the payment:\n{payment_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'order-paid' THEN 'Hello,\n\nYour payment has been confirmed.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'order-activated' THEN 'Hello,\n\nYour subscription is now active.\n\nLog in to your account to review the details:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'order-expiring-soon' THEN 'Hello,\n\nThis is a reminder that your subscription will expire soon.\n\nLog in to your account to review the details and renew it in advance:\n{order_url}\n\nExpiry date: {expires_at_local}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'order-expired' THEN 'Hello,\n\nYour subscription has expired.\n\nLog in to your account to renew it:\n{order_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'live-chat-admin-notify' THEN 'Hello,\n\nA customer sent a new message in {site_name} live chat.\n\nCustomer: {customer_email}\n\nLog in to the admin panel to reply:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'live-chat-customer-notify' THEN 'Hello,\n\nYou received a new message in {site_name}.\n\nLog in to your account to check Live Chat:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}\n\nThis message was generated automatically.'
    WHEN 'news-broadcast' THEN 'Hello,\n\nA new important message was published in {site_name}.\n\nLog in to your account and open News:\n{news_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'account-blocked' THEN 'Hello,\n\nYour account in {site_name} has been blocked.\n\nIf you need help, contact support through the website:\n{site_url}\n\nRegards,\n{site_name}'
    WHEN 'support-payment-request-notify' THEN 'Hello,\n\nA customer started the payment process in {site_name}.\n\nCustomer: {customer_email}\nPayment type: {payment_type}\n\nOpen the admin panel:\n{admin_url}\n\nRegards,\n{site_name}\n{site_url}'
    WHEN 'messenger-invite-notify' THEN 'Hello,\n\n{sender_label} sent you a messenger invitation in {site_name}.\n\nConversation: {conversation_title}\n\nLog in to your account to accept or reject the invitation:\n{chat_url}\n\nRegards,\n{site_name}\n{site_url}'
  END
FROM `email_templates` et
WHERE et.template_key IN (
  'account-activation',
  'password-reset',
  'payment-request-created',
  'order-paid',
  'order-activated',
  'order-expiring-soon',
  'order-expired',
  'live-chat-admin-notify',
  'live-chat-customer-notify',
  'news-broadcast',
  'account-blocked',
  'support-payment-request-notify',
  'messenger-invite-notify'
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
    WHEN 'order-expiring-soon' THEN 'Twoja subskrypcja wygasa za 5 dni'
    WHEN 'order-expired' THEN 'Twoje zamówienie wygasło'
    WHEN 'live-chat-admin-notify' THEN 'Masz nową wiadomość [Support]'
    WHEN 'live-chat-customer-notify' THEN 'Masz nową wiadomość [Support]'
    WHEN 'news-broadcast' THEN 'Nowa ważna informacja'
    WHEN 'account-blocked' THEN 'Twoje konto zostało zablokowane'
    WHEN 'support-payment-request-notify' THEN 'Klient rozpoczął płatność [Support]'
    WHEN 'messenger-invite-notify' THEN 'Masz nowe zaproszenie do rozmowy'
  END,
  CASE et.template_key
    WHEN 'account-activation' THEN 'Witaj,\n\nTwoje konto w {site_name} jest gotowe.\n\nZaloguj się tutaj:\n{login_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'password-reset' THEN 'Witaj {customer_email},\n\nTwoje nowe hasło to:\n{password}\n\nZaloguj się i zmień je jak najszybciej.\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'payment-request-created' THEN 'Witaj,\n\nNa Twoim koncie oczekuje płatność.\n\nZaloguj się na stronę, aby ją dokończyć:\n{payment_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'order-paid' THEN 'Witaj,\n\nTwoja płatność została potwierdzona.\n\nZaloguj się na swoje konto, aby sprawdzić szczegóły:\n{order_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'order-activated' THEN 'Witaj,\n\nTwoja subskrypcja jest już aktywna.\n\nZaloguj się na swoje konto, aby sprawdzić szczegóły:\n{order_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'order-expiring-soon' THEN 'Witaj,\n\nPrzypominamy, że Twoja subskrypcja wkrótce wygaśnie.\n\nZaloguj się na swoje konto, aby sprawdzić szczegóły i odnowić ją wcześniej:\n{order_url}\n\nData wygaśnięcia: {expires_at_local}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'order-expired' THEN 'Witaj,\n\nTwoja subskrypcja wygasła.\n\nZaloguj się na swoje konto, aby ją odnowić:\n{order_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'live-chat-admin-notify' THEN 'Witaj,\n\nKlient wysłał nową wiadomość na live chacie w {site_name}.\n\nKlient: {customer_email}\n\nZaloguj się do panelu admina, aby odpowiedzieć:\n{chat_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'live-chat-customer-notify' THEN 'Witaj,\n\nOtrzymałeś nową wiadomość w {site_name}.\n\nZaloguj się na swoje konto, aby sprawdzić Live Chat:\n{chat_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}\n\nTa wiadomość została wygenerowana automatycznie.'
    WHEN 'news-broadcast' THEN 'Witaj,\n\nW {site_name} została opublikowana nowa ważna informacja.\n\nZaloguj się na swoje konto i otwórz News:\n{news_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'account-blocked' THEN 'Witaj,\n\nTwoje konto w {site_name} zostało zablokowane.\n\nJeśli potrzebujesz pomocy, skontaktuj się ze wsparciem przez stronę:\n{site_url}\n\nPozdrawiamy,\n{site_name}'
    WHEN 'support-payment-request-notify' THEN 'Witaj,\n\nKlient rozpoczął proces płatności w {site_name}.\n\nKlient: {customer_email}\nTyp płatności: {payment_type}\n\nOtwórz panel admina:\n{admin_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
    WHEN 'messenger-invite-notify' THEN 'Witaj,\n\n{sender_label} wysłał Ci zaproszenie do messengera w {site_name}.\n\nRozmowa: {conversation_title}\n\nZaloguj się na swoje konto, aby zaakceptować albo odrzucić zaproszenie:\n{chat_url}\n\nPozdrawiamy,\n{site_name}\n{site_url}'
  END
FROM `email_templates` et
WHERE et.template_key IN (
  'account-activation',
  'password-reset',
  'payment-request-created',
  'order-paid',
  'order-activated',
  'order-expiring-soon',
  'order-expired',
  'live-chat-admin-notify',
  'live-chat-customer-notify',
  'news-broadcast',
  'account-blocked',
  'support-payment-request-notify',
  'messenger-invite-notify'
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
