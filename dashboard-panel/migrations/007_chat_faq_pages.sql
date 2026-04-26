SET NAMES utf8mb4;

INSERT INTO `static_pages` (`slug`, `title`, `body`, `page_type`, `is_system`, `is_active`) VALUES
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
  `body` = VALUES(`body`),
  `page_type` = VALUES(`page_type`),
  `is_system` = VALUES(`is_system`),
  `is_active` = VALUES(`is_active`);
