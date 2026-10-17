<?php

declare(strict_types=1);

function admin_billing_ui_text(string $locale): array
{
    $pl = [
        'title' => 'Rachunki', 'intro' => 'Wystawiaj dokumenty, zarządzaj klientami rozliczeniowymi i kontroluj sprzedaż.',
        'create' => 'Utwórz', 'stats' => 'Statystyki', 'documents' => 'Rachunki', 'customers' => 'Klienci', 'settings' => 'Ustawienia',
        'company_missing' => 'Najpierw uzupełnij dane firmy w ustawieniach modułu.', 'open_settings' => 'Przejdź do ustawień',
        'source' => 'Źródło dokumentu', 'independent' => 'Dokument niezależny', 'order' => 'Zamówienie', 'crypto' => 'Zatwierdzona płatność krypto', 'bank' => 'Zatwierdzony przelew bankowy',
        'source_help' => 'Dokument niezależny nie musi być przypisany do użytkownika, zamówienia ani płatności.',
        'source_independent_help' => 'Wybierz tę opcję, gdy dokument nie dotyczy zamówienia ani płatności zapisanej w serwisie. Dane klienta i pozycje wpisujesz samodzielnie. Dokument może pozostać niezależny albo zostać połączony z kontem klienta.',
        'source_order_help' => 'Wybierz opłacone zamówienie z serwisu. Moduł uzupełni klienta, produkt, kwotę i walutę na podstawie zamówienia. Przed wystawieniem sprawdź dane nabywcy oraz właściwy sposób rozliczenia VAT.',
        'source_crypto_help' => 'Wybierz zatwierdzoną płatność kryptowalutową z serwisu. Dokument zachowa powiązanie z płatnością i powiązanym zamówieniem, jeśli ono istnieje. Kwotę, klienta i VAT sprawdź przed nadaniem numeru dokumentu.',
        'source_bank_help' => 'Wybierz zatwierdzony przelew bankowy z serwisu. Moduł pobierze kwotę, walutę i dane powiązanego klienta. Przed wystawieniem potwierdź pozycje, dane nabywcy oraz klasyfikację podatkową.',
        'source_only_independent' => 'Brak dostępnych opłaconych zamówień i zatwierdzonych płatności. Możesz wystawić dokument niezależny.',
        'billing_customer' => 'Klient rozliczeniowy', 'site_user' => 'Powiązane konto w serwisie', 'none' => 'Brak powiązania',
        'search_customer' => 'Wyszukaj klienta po nazwie, emailu lub NIP/UID', 'search_user' => 'Wyszukaj usera po ID, emailu lub @handle',
        'search_start' => 'Wpisz co najmniej 2 znaki.', 'search_loading' => 'Wyszukiwanie...', 'search_empty' => 'Nie znaleziono pasujących wyników.', 'search_choose' => 'Kliknij wynik, aby uzupełnić dane.',
        'buyer' => 'Dane nabywcy', 'buyer_name' => 'Imię i nazwisko / nazwa firmy', 'email' => 'Email', 'phone' => 'Telefon', 'tax_id' => 'NIP / UID',
        'address' => 'Adres', 'address2' => 'Adres - linia 2', 'postal' => 'Kod pocztowy', 'city' => 'Miasto', 'country' => 'Kraj', 'country_code' => 'Kod kraju',
        'save_customer' => 'Zapisz lub zaktualizuj kartotekę klienta', 'document' => 'Dane dokumentu', 'language' => 'Język PDF', 'currency' => 'Waluta', 'price_mode' => 'Wprowadzane ceny', 'net_prices' => 'Netto', 'gross_prices' => 'Brutto',
        'issue_date' => 'Data wystawienia', 'service_date' => 'Data sprzedaży', 'due_date' => 'Termin płatności', 'supply' => 'Rodzaj sprzedaży', 'buyer_type' => 'Typ nabywcy',
        'consumer' => 'Konsument', 'business' => 'Firma', 'region' => 'Rynek', 'domestic' => 'Krajowy', 'eu' => 'Unia Europejska', 'uk' => 'Wielka Brytania', 'outside_eu' => 'Poza UE',
        'tax_treatment' => 'Sposób opodatkowania', 'tax_note' => 'Adnotacja podatkowa', 'tax_warning' => 'Wybierz rodzaj sprzedaży i sposób opodatkowania zgodny z profilem firmy oraz transakcją. Moduł uzupełni wymaganą adnotację i skontroluje zgodność stawki z wybranym wariantem.',
        'vat_assistant' => 'Asystent VAT', 'vat_apply' => 'Zastosuj rekomendację', 'vat_rate_apply' => 'Ustaw stawkę w pozycjach',
        'vat_auto' => 'Rekomendacja automatyczna', 'vat_manual_review' => 'Wymaga decyzji', 'vat_context' => 'Kontekst transakcji',
        'vat_manual' => 'Ten wariant wymaga ręcznego wyboru stawki i adnotacji.', 'draft_restored' => 'Przywrócono niezapisane dane rachunku z tej przeglądarki.',
        'profile_ready' => 'Profil gotowy do wystawiania', 'profile_not_ready' => 'Profil wymaga uzupełnienia',
        'region_auto' => 'Rynek ustalony automatycznie', 'source_match' => 'Kontrola kwoty źródłowej',
        'source_match_ok' => 'Kwota i waluta dokumentu są zgodne ze źródłem.',
        'source_match_confirm' => 'Kwota lub waluta różni się od opłaconego źródła. Potwierdzam, że ta różnica jest zamierzona.',
        'vat_id_verified' => 'Sprawdziłem numer VAT ID nabywcy dla reverse charge.',
        'verify_vies' => 'Sprawdź w VIES',
        'foreign_currency_review' => 'Potwierdzam przeliczenie VAT do waluty rozliczeniowej profilu poza tym modułem.',
        'ksef_review' => 'Potwierdzam właściwą obsługę KSeF dla tego dokumentu.',
        'external_reference' => 'Referencja księgowa', 'external_reference_pl' => 'KSeF / referencja księgowa', 'issue_checklist' => 'Kontrola przed wystawieniem',
        'issue_checklist_intro' => 'Moduł sprawdza kompletność danych i pokazuje, co trzeba poprawić przed nadaniem numeru.',
        'tax_review' => 'Sprawdziłem dane nabywcy, rodzaj sprzedaży, stawkę VAT i adnotację podatkową.',
        'items' => 'Pozycje', 'description' => 'Nazwa', 'quantity' => 'Ilość', 'unit' => 'J.m.', 'net_price' => 'Cena sprzedaży', 'purchase_price' => 'Cena nabycia brutto', 'vat' => 'VAT %', 'gross' => 'Brutto', 'add_item' => 'Dodaj pozycję',
        'subtotal' => 'Netto', 'tax_total' => 'VAT', 'total' => 'Razem', 'notes' => 'Uwagi', 'payment_notes' => 'Informacja o płatności', 'save_draft' => 'Zapisz szkic', 'issue' => 'Wystaw dokument',
        'summary' => 'Podsumowanie', 'issued_total' => 'Wystawiono', 'paid_total' => 'Opłacono', 'gross_total' => 'Wartość brutto', 'paid_gross' => 'Wpływy oznaczone jako opłacone',
        'all_companies' => 'Wszystkie firmy', 'all_companies_intro' => 'Wspólny podgląd profili wystawców bez przełączania firmy.', 'active_profiles' => 'Aktywne profile', 'documents_total' => 'Wszystkie dokumenty',
        'outstanding' => 'Do zapłaty', 'overdue' => 'Po terminie', 'tax_value' => 'VAT na dokumentach', 'current_month' => 'Bieżący miesiąc', 'current_year' => 'Bieżący rok', 'previous_month' => 'Poprzedni miesiąc',
        'drafts' => 'Szkice', 'cancelled_total' => 'Anulowane', 'sales_trend' => 'Sprzedaż z ostatnich 12 miesięcy', 'sources_breakdown' => 'Źródła dokumentów', 'tax_breakdown' => 'Rozliczenie podatku', 'top_customers' => 'Najwięksi klienci', 'no_stats' => 'Brak wystawionych dokumentów dla tej firmy.',
        'net_value' => 'Netto', 'documents_short' => 'dok.', 'profile_off' => 'Profil wyłączony', 'profile_on' => 'Profil aktywny',
        'number' => 'Numer', 'status' => 'Status', 'date' => 'Data', 'customer' => 'Klient', 'amount' => 'Kwota', 'actions' => 'Akcje', 'empty' => 'Brak dokumentów.',
        'filter' => 'Szukaj po numerze, nabywcy lub emailu', 'all_statuses' => 'Wszystkie statusy', 'pdf' => 'PDF', 'edit' => 'Edytuj', 'mark_paid' => 'Oznacz jako opłacony', 'cancel' => 'Anuluj', 'delete' => 'Usuń szkic', 'issue_draft' => 'Wystaw szkic',
        'send_email' => 'Wyślij', 'send_email_missing' => 'Dodaj prawidłowy email nabywcy.', 'send_email_status' => 'Najpierw wystaw dokument.',
        'send_email_confirm' => 'Potwierdź, że odbiorca akceptuje elektroniczne doręczenie dokumentu. Wysłać PDF na zapisany email?',
        'email_sent' => 'Wysłano emailem',
        'company' => 'Dane firmy', 'company_intro' => 'Te dane są zapisywane jako snapshot na każdym wystawionym dokumencie.', 'company_name' => 'Nazwa firmy', 'legal_name' => 'Pełna nazwa prawna', 'website' => 'Strona WWW',
        'vat_id' => 'UID / VAT ID', 'tax_number' => 'Numer podatkowy', 'registration' => 'Numer rejestrowy', 'bank_name' => 'Bank', 'iban' => 'IBAN', 'bic' => 'BIC / SWIFT', 'logo' => 'Logo firmy',
        'prefix' => 'Format numeracji', 'terms' => 'Termin płatności (dni)', 'footer' => 'Stopka PDF', 'save_company' => 'Zapisz dane firmy',
        'business_activity' => 'Rodzaj działalności', 'vat_status' => 'Status VAT', 'tax_guidance_note' => 'Wewnętrzna wskazówka podatkowa dla tego profilu',
        'eu_oss_enabled' => 'Rozliczenie OSS dla sprzedaży B2C w UE', 'medical_exemption_enabled' => 'Profil może stosować zwolnienie medyczne', 'margin_scheme_enabled' => 'Profil może stosować procedurę marży',
        'tax_profiles' => 'Profile VAT', 'rate_label' => 'Nazwa stawki', 'rate' => 'Stawka %', 'active' => 'Aktywna', 'default' => 'Domyślna', 'save_rate' => 'Zapisz stawkę',
        'customer_directory' => 'Klienci rozliczeniowi', 'customer_directory_intro' => 'Kartoteka może pozostać niezależna albo zostać połączona z istniejącym kontem w serwisie.', 'save_billing_customer' => 'Zapisz klienta', 'linked' => 'Powiązanie', 'profile' => 'Profil wystawcy', 'profile_label' => 'Nazwa profilu', 'new_profile' => 'Dodaj nowy profil', 'item_catalog' => 'Katalog pozycji', 'item_catalog_intro' => 'Zapisane pozycje są podpowiadane podczas tworzenia kolejnych rachunków dla tego profilu.', 'new_catalog_item' => 'Nowa pozycja', 'catalog_usage' => 'Użycie', 'catalog_never_used' => 'Jeszcze nie użyto', 'save_item' => 'Zapisz pozycję',
    ];
    if ($locale === 'pl') {
        return $pl;
    }
    return array_merge($pl, [
        'title' => 'Billing', 'intro' => 'Issue documents, manage billing customers and review sales.', 'create' => 'Create', 'stats' => 'Statistics', 'documents' => 'Documents', 'customers' => 'Customers', 'settings' => 'Settings',
        'company_missing' => 'Complete the company profile before issuing a document.', 'open_settings' => 'Open settings', 'source' => 'Document source', 'independent' => 'Independent document', 'order' => 'Order', 'crypto' => 'Approved crypto payment', 'bank' => 'Approved bank transfer',
        'source_independent_help' => 'Use this when the document is not based on an order or payment stored in the service. Enter the buyer and items manually. The document may remain standalone or be linked to a customer account.',
        'source_order_help' => 'Select a paid order from the service. The module fills the customer, product, amount and currency from that order. Verify the buyer details and VAT treatment before issuing.',
        'source_crypto_help' => 'Select an approved crypto payment from the service. The document keeps a link to the payment and its order when available. Verify the amount, customer and VAT before assigning a document number.',
        'source_bank_help' => 'Select an approved bank transfer from the service. The module loads its amount, currency and linked customer. Confirm the items, buyer data and tax classification before issuing.',
        'source_only_independent' => 'No paid orders or approved payments are available. You can issue an independent document.',
        'tax_review' => 'I verified the buyer, supply type, VAT rate and tax note.',
        'billing_customer' => 'Billing customer', 'site_user' => 'Linked service account', 'none' => 'No link', 'search_customer' => 'Search by name, email or tax ID', 'search_user' => 'Search by ID, email or @handle', 'search_start' => 'Enter at least 2 characters.', 'search_loading' => 'Searching...', 'search_empty' => 'No matching results found.', 'search_choose' => 'Select a result to fill the buyer details.', 'buyer' => 'Buyer details',
        'save_customer' => 'Save or update billing customer', 'document' => 'Document details', 'language' => 'PDF language', 'price_mode' => 'Entered prices', 'net_prices' => 'Net', 'gross_prices' => 'Gross', 'issue_date' => 'Issue date', 'service_date' => 'Supply date', 'due_date' => 'Due date', 'items' => 'Items', 'add_item' => 'Add item', 'save_draft' => 'Save draft', 'issue' => 'Issue document',
        'company' => 'Company details', 'save_company' => 'Save company profile', 'tax_profiles' => 'VAT profiles', 'customer_directory' => 'Billing customers', 'save_billing_customer' => 'Save customer', 'empty' => 'No documents found.', 'net_price' => 'Sale price', 'purchase_price' => 'Gross purchase price',
        'new_catalog_item' => 'New item', 'catalog_usage' => 'Usage', 'catalog_never_used' => 'Not used yet',
        'send_email' => 'Send', 'send_email_missing' => 'Add a valid buyer email.', 'send_email_status' => 'Issue the document first.',
        'send_email_confirm' => 'Confirm that the recipient accepts electronic delivery. Send the PDF to the saved email address?',
        'email_sent' => 'Emailed',
        'business_activity' => 'Business activity', 'vat_status' => 'VAT status', 'tax_guidance_note' => 'Internal tax guidance for this profile',
        'eu_oss_enabled' => 'EU B2C sales use OSS', 'medical_exemption_enabled' => 'Profile may use the medical exemption', 'margin_scheme_enabled' => 'Profile may use the margin scheme',
        'vat_assistant' => 'VAT assistant', 'vat_apply' => 'Apply recommendation', 'vat_rate_apply' => 'Set rate on items',
        'vat_auto' => 'Automatic recommendation', 'vat_manual_review' => 'Decision required', 'vat_context' => 'Transaction context',
        'vat_manual' => 'This treatment requires a manual rate and tax-note decision.', 'draft_restored' => 'Unsaved billing data was restored from this browser.',
        'profile_ready' => 'Issuer profile ready', 'profile_not_ready' => 'Issuer profile needs attention',
        'region_auto' => 'Market determined automatically', 'source_match' => 'Source amount check',
        'source_match_ok' => 'The document amount and currency match the paid source.',
        'source_match_confirm' => 'The amount or currency differs from the paid source. I confirm this difference is intentional.',
        'vat_id_verified' => 'I verified the buyer VAT ID for reverse charge.',
        'verify_vies' => 'Check in VIES',
        'foreign_currency_review' => 'I confirm local-currency VAT reporting is handled outside this module.',
        'ksef_review' => 'I confirm the applicable KSeF workflow for this document.',
        'external_reference' => 'Accounting reference', 'external_reference_pl' => 'KSeF / accounting reference', 'issue_checklist' => 'Pre-issue check',
        'issue_checklist_intro' => 'The module checks completeness and shows what must be fixed before assigning a number.',
        'all_companies' => 'All companies', 'all_companies_intro' => 'A combined view of every issuer profile without switching companies.', 'active_profiles' => 'Active profiles', 'documents_total' => 'All documents',
        'outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'tax_value' => 'VAT on documents', 'current_month' => 'Current month', 'current_year' => 'Current year', 'previous_month' => 'Previous month',
        'drafts' => 'Drafts', 'cancelled_total' => 'Cancelled', 'sales_trend' => 'Sales over the last 12 months', 'sources_breakdown' => 'Document sources', 'tax_breakdown' => 'Tax breakdown', 'top_customers' => 'Top customers', 'no_stats' => 'No issued documents for this company.',
        'net_value' => 'Net', 'documents_short' => 'docs', 'profile_off' => 'Profile disabled', 'profile_on' => 'Profile active',
    ]);
}

function admin_billing_status_label(string $status, string $locale): string
{
    $labels = $locale === 'pl'
        ? ['draft' => 'Szkic', 'issued' => 'Wystawiony', 'paid' => 'Opłacony', 'cancelled' => 'Anulowany']
        : ['draft' => 'Draft', 'issued' => 'Issued', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
    return $labels[$status] ?? ucfirst($status);
}

function admin_billing_render_page(array $context, array $messages, string $locale, string $csrfToken): void
{
    $t = admin_billing_ui_text($locale);
    $tab = strtolower(trim((string)($_GET['billing_tab'] ?? 'create')));
    if (!in_array($tab, ['create', 'stats', 'documents', 'customers', 'settings'], true)) {
        $tab = 'create';
    }
    $profile = (array)($context['profile'] ?? []);
    $doc = is_array($context['edit_document'] ?? null) ? $context['edit_document'] : [];
    $items = (array)($doc['items'] ?? []);
    if (!$items) {
        $items = [['description' => '', 'quantity' => 1, 'unit_label' => $locale === 'pl' ? 'szt.' : 'pcs', 'unit_price_net' => 0, 'tax_rate_percent' => 0]];
    }
    $today = date('Y-m-d');
    $terms = max(0, (int)($profile['payment_terms_days'] ?? 14));
    $dueDefault = date('Y-m-d', strtotime('+' . $terms . ' days'));
    $defaultTax = 0.0;
    foreach ((array)($context['tax_rates'] ?? []) as $rateRow) {
        if ((string)($rateRow['country_code'] ?? '') === (string)($profile['country_code'] ?? 'IE') && !empty($rateRow['is_default'])) {
            $defaultTax = (float)($rateRow['rate_percent'] ?? 0);
            break;
        }
    }
    $supportedLanguages = admin_billing_supported_languages();
    $documentLanguage = strtolower(trim((string)($doc['language_code'] ?? '')));
    if (!isset($supportedLanguages[$documentLanguage])) {
        $profileLanguage = admin_billing_country_language((string)($profile['country_code'] ?? ''));
        $documentLanguage = isset($supportedLanguages[$profileLanguage])
            ? $profileLanguage
            : (isset($supportedLanguages[$locale]) ? $locale : 'en');
    }
    $supportedCurrencies = admin_billing_supported_currencies();
    $profileCurrency = strtoupper(trim((string)($profile['default_currency_code'] ?? '')));
    $serviceCurrency = strtoupper(trim((string)($context['site_default_currency_code'] ?? 'EUR')));
    $documentCurrency = strtoupper(trim((string)($doc['currency_code'] ?? '')));
    if (!isset($supportedCurrencies[$documentCurrency])) {
        $documentCurrency = !empty($profile['id']) && isset($supportedCurrencies[$profileCurrency])
            ? $profileCurrency
            : (isset($supportedCurrencies[$serviceCurrency]) ? $serviceCurrency : 'EUR');
    }
    $usedSourceKeys = array_fill_keys(array_map('strval', (array)($context['used_source_keys'] ?? [])), true);
    $availableOrderSources = array_values(array_filter((array)($context['orders'] ?? []), static fn(array $row): bool => empty($usedSourceKeys['order:' . (int)($row['id'] ?? 0)])));
    $availableCryptoSources = array_values(array_filter((array)($context['payments'] ?? []), static fn(array $row): bool => (string)($row['payment_type'] ?? '') === 'crypto' && empty($usedSourceKeys['payment_crypto:' . (int)($row['id'] ?? 0)])));
    $availableBankSources = array_values(array_filter((array)($context['payments'] ?? []), static fn(array $row): bool => (string)($row['payment_type'] ?? '') === 'bank' && empty($usedSourceKeys['payment_bank:' . (int)($row['id'] ?? 0)])));
    $currentSource = (string)($doc['source_type'] ?? 'independent');
    $hasExternalSources = $availableOrderSources || $availableCryptoSources || $availableBankSources;
    $isPolishIssuer = strtoupper((string)($profile['country_code'] ?? '')) === 'PL';
    $unitOptions = admin_billing_units($documentLanguage);
    $customerCountries = admin_billing_customer_countries($locale);
    $profileCountryPresentation = admin_billing_country_presentation(
        (string)($profile['country_code'] ?? ''),
        $locale,
        (string)($profile['country_name'] ?? '')
    );
    $payload = [
        'locale' => $locale,
        'billingCustomers' => array_values((array)($context['billing_customers'] ?? [])),
        'siteCustomers' => array_values((array)($context['site_customers'] ?? [])),
        'orders' => array_values((array)($context['orders'] ?? [])),
        'payments' => array_values((array)($context['payments'] ?? [])),
        'usedSourceKeys' => array_values((array)($context['used_source_keys'] ?? [])),
        'itemCatalog' => array_values((array)($context['item_catalog'] ?? [])),
        'selectedProfileId' => (int)($context['selected_profile_id'] ?? 0),
        'defaultTax' => $defaultTax,
        'taxRates' => array_values((array)($context['tax_rates'] ?? [])),
        'taxTreatments' => admin_billing_tax_treatments($locale),
        'profileCountry' => strtoupper((string)($profile['country_code'] ?? '')),
        'profileVatStatus' => (string)($profile['vat_status'] ?? 'vat_registered'),
        'profileBusinessActivity' => (string)($profile['business_activity'] ?? 'general'),
        'profileOssEnabled' => !empty($profile['eu_oss_enabled']),
        'profileMedicalExemptionEnabled' => !empty($profile['medical_exemption_enabled']),
        'profileMarginSchemeEnabled' => !empty($profile['margin_scheme_enabled']),
        'profileCapabilityRules' => admin_billing_profile_capability_rules(),
        'businessActivityLabels' => admin_billing_business_activities($locale),
        'profileGuidance' => $locale === 'pl' ? [
            'no_special' => 'Dla wybranego kraju, działalności i statusu VAT nie ma dodatkowych procedur do włączenia. Dokumenty będą korzystać ze standardowych stawek profilu albo trybu ręcznego.',
            'status_manual' => 'Status VAT jest ustalany indywidualnie. Dodatkowe procedury pozostają ukryte, a dokumenty wymagają ręcznej decyzji podatkowej.',
            'digital_services' => 'Dla usług cyfrowych w UE włącz OSS tylko wtedy, gdy firma rzeczywiście rozlicza w nim sprzedaż B2C. Sprzedaż B2B nadal wymaga VAT ID nabywcy.',
            'healthcare' => 'Dla usług medycznych lub psychologicznych włącz zwolnienie wyłącznie w profilu, który może je faktycznie stosować.',
            'second_hand_goods' => 'Dla towarów używanych włącz procedurę marży tylko dla kwalifikujących się zakupów. Formularz zażąda ceny nabycia każdej pozycji.',
            'general' => 'Dostępne procedury wynikają z kraju, rodzaju działalności i statusu VAT tego profilu. Stawki VAT pozostają osobne dla każdego kraju i mogą być edytowane przez administratora.',
        ] : [
            'no_special' => 'No additional procedure is available for the selected country, activity and VAT status. Documents will use the profile standard rates or manual treatment.',
            'status_manual' => 'The VAT status is determined individually. Additional procedures stay hidden and documents require a manual tax decision.',
            'digital_services' => 'For EU digital services, enable OSS only when the issuer actually reports B2C sales through OSS. B2B sales still require the buyer VAT ID.',
            'healthcare' => 'Enable the medical exemption only for an issuer that can actually apply it to qualifying healthcare or psychological services.',
            'second_hand_goods' => 'Enable the margin scheme only for qualifying second-hand purchases. The form will require each item purchase cost.',
            'general' => 'Available procedures are derived from this profile country, activity and VAT status. VAT rates remain country-specific and editable by the administrator.',
        ],
        'profileDefaultCurrency' => strtoupper((string)($profile['default_currency_code'] ?? 'EUR')),
        'siteDefaultCurrency' => $serviceCurrency,
        'profileReadiness' => (array)($context['profile_readiness'] ?? []),
        'euCountries' => admin_billing_eu_country_codes(),
        'customerCountries' => $customerCountries,
        'regionLabels' => [
            'domestic' => $t['domestic'], 'eu' => $t['eu'], 'uk' => $t['uk'], 'outside_eu' => $t['outside_eu'],
        ],
        'supplyLabels' => admin_billing_supply_types($locale),
        'goodsSupplyTypes' => admin_billing_goods_supply_types(),
        'digitalSupplyTypes' => admin_billing_digital_supply_types(),
        'buyerLabels' => ['consumer' => $t['consumer'], 'business' => $t['business']],
        'documentId' => (int)($doc['id'] ?? 0),
        'adminUserId' => (int)($context['admin_user_id'] ?? 0),
        'hasServerDraft' => !empty($context['has_server_form_draft']),
        'savedDocumentId' => isset($_GET['billing_saved']) ? max(0, (int)($_GET['document_id'] ?? 0)) : 0,
        'savedProfileId' => isset($_GET['billing_saved']) ? max(0, (int)($_GET['billing_profile_id'] ?? 0)) : 0,
        'siteName' => (string)($context['site_name'] ?? 'Site'),
        'countries' => admin_billing_supported_countries(),
        'units' => [
            'pl' => admin_billing_units('pl'),
            'en' => admin_billing_units('en'),
            'de' => admin_billing_units('de'),
        ],
        'sourceHelp' => [
            'independent' => $t['source_independent_help'],
            'order' => $t['source_order_help'],
            'payment_crypto' => $t['source_crypto_help'],
            'payment_bank' => $t['source_bank_help'],
        ],
        'searchUi' => [
            'start' => $t['search_start'], 'loading' => $t['search_loading'], 'empty' => $t['search_empty'], 'choose' => $t['search_choose'],
        ],
        'defaultPaymentNotes' => array_reduce(array_keys(admin_billing_supported_languages()), static function (array $carry, string $language): array {
            foreach (['order', 'payment_crypto', 'payment_bank'] as $sourceType) {
                $carry[$language][$sourceType] = admin_billing_default_payment_note($sourceType, $language);
            }
            return $carry;
        }, []),
        'defaultTaxNotes' => array_map(static fn(string $key): array => ['key' => $key, 'value' => admin_billing_default_tax_note($key, $documentLanguage, (string)($profile['country_code'] ?? ''))], array_keys(admin_billing_tax_treatments())),
        'defaultTaxNotesByLanguage' => array_reduce(array_keys(admin_billing_supported_languages()), static function (array $carry, string $language) use ($profile): array {
            foreach (array_keys(admin_billing_tax_treatments()) as $treatment) {
                $carry[$language][$treatment] = admin_billing_default_tax_note($treatment, $language, (string)($profile['country_code'] ?? ''));
            }
            return $carry;
        }, []),
        'supplyHelp' => $locale === 'pl' ? [
            'digital_service' => 'Subskrypcje, konta premium, dostęp do treści, streamów i usług online.',
            'software_service' => 'Licencje, oprogramowanie, hosting i pozostałe usługi dostarczane cyfrowo.',
            'consulting_service' => 'Doradztwo, konsultacje i praca ekspercka rozliczana jako usługa.',
            'education_service' => 'Szkolenia, kursy, warsztaty i dostęp do materiałów edukacyjnych.',
            'creative_service' => 'Projektowanie, fotografia, treści i pozostałe usługi kreatywne.',
            'it_support_service' => 'Wsparcie techniczne, administracja systemami i wdrożenia IT.',
            'repair_service' => 'Naprawa, serwis albo konserwacja rzeczy należącej do klienta.',
            'rental_service' => 'Najem lub wypożyczenie sprzętu, lokalu albo innego składnika.',
            'transport_service' => 'Transport, przewóz albo dostawa rozliczana jako osobna usługa.',
            'hospitality_service' => 'Nocleg i usługi zakwaterowania.',
            'catering_service' => 'Gastronomia, catering i przygotowanie posiłków.',
            'financial_service' => 'Usługi finansowe lub pośrednictwo wymagające właściwej kwalifikacji VAT.',
            'construction_service' => 'Usługi związane z nieruchomością. Miejsce położenia nieruchomości ma znaczenie dla VAT.',
            'healthcare_service' => 'Usługi z obszaru zdrowia i medycyny. Zwolnienie wymaga właściwego wariantu opodatkowania.',
            'psychological_service' => 'Sesje terapeutyczne i psychologiczne. Wybierz zwolnienie albo VAT odpowiedni dla usługi.',
            'other_service' => 'Pozostałe usługi, które nie pasują do wyszczególnionych kategorii.',
            'goods' => 'Sprzedaż towarów, elektroniki i produktów wysyłkowych.',
            'manufactured_goods' => 'Towary wytworzone przez sprzedawcę albo sprzedawane pod własną marką.',
            'automotive_goods' => 'Pojazdy, części samochodowe i pozostałe towary motoryzacyjne.',
            'used_goods' => 'Towary używane. Procedura marży jest osobnym wariantem i nie zawsze ma zastosowanie.',
        ] : [
            'digital_service' => 'Subscriptions, premium accounts, content access, streams and online services.',
            'software_service' => 'Software, licences, hosting and other digitally supplied services.',
            'consulting_service' => 'Consulting, advisory and professional expert services.',
            'education_service' => 'Training, courses, workshops and educational content access.',
            'creative_service' => 'Design, photography, content and other creative services.',
            'it_support_service' => 'Technical support, system administration and IT implementation.',
            'repair_service' => 'Repair, servicing or maintenance of customer property.',
            'rental_service' => 'Rental of equipment, premises or other assets.',
            'transport_service' => 'Transport, carriage or delivery billed as a separate service.',
            'hospitality_service' => 'Accommodation and hospitality services.',
            'catering_service' => 'Restaurant, catering and prepared-food services.',
            'financial_service' => 'Financial or intermediary services requiring the correct VAT classification.',
            'construction_service' => 'Property-related services. The property location affects VAT treatment.',
            'healthcare_service' => 'Medical and healthcare services. An exemption requires the matching tax treatment.',
            'psychological_service' => 'Therapy and psychology sessions. Select exemption or VAT for the service.',
            'other_service' => 'Services that do not fit another listed category.',
            'goods' => 'Goods, electronics and shipped products.',
            'manufactured_goods' => 'Goods made by the seller or sold under its own brand.',
            'automotive_goods' => 'Vehicles, automotive parts and other motor-related goods.',
            'used_goods' => 'Second-hand goods. The margin scheme is separate and does not always apply.',
        ],
        'taxAssistant' => [
            'title' => $t['vat_assistant'], 'apply' => $t['vat_apply'], 'applyRate' => $t['vat_rate_apply'], 'manual' => $t['vat_manual'],
            'recommended' => $locale === 'pl' ? 'Rekomendowany wariant' : 'Recommended treatment',
            'rate' => $locale === 'pl' ? 'Stawka VAT' : 'VAT rate',
            'automatic' => $t['vat_auto'], 'manualReview' => $t['vat_manual_review'], 'context' => $t['vat_context'],
            'seller' => $locale === 'pl' ? 'Sprzedawca' : 'Seller', 'buyer' => $locale === 'pl' ? 'Nabywca' : 'Buyer', 'supply' => $locale === 'pl' ? 'Sprzedaż' : 'Supply',
            'destinationMissing' => $locale === 'pl' ? 'Wpisz dwuliterowy kod kraju nabywcy, aby ustalić stawkę kraju docelowego.' : 'Enter the buyer destination country code to determine its VAT rate.',
            'noRate' => $locale === 'pl' ? 'Brak aktywnej stawki dla tego kraju. Ustaw ją ręcznie.' : 'No active rate exists for this country. Set it manually.',
            'zeroReason' => $locale === 'pl' ? 'Ten wariant wykazuje 0% VAT w pozycjach i wymaga właściwej adnotacji.' : 'This treatment uses 0% VAT on items and requires the matching tax note.',
            'filtered' => $locale === 'pl' ? 'Lista uwzględnia profil wystawcy, rodzaj sprzedaży, rynek i typ nabywcy.' : 'The list reflects the issuer profile, supply, market and buyer type.',
            'smallBusiness' => $locale === 'pl' ? 'Profil nie nalicza VAT w sprzedaży krajowej. Moduł ustawi 0% i właściwą adnotację.' : 'This profile does not charge domestic VAT. The module will use 0% and the matching note.',
            'businessVatMissing' => $locale === 'pl' ? 'Dla reverse charge wpisz i sprawdź VAT ID nabywcy.' : 'Enter and verify the buyer VAT ID for reverse charge.',
            'construction' => $locale === 'pl' ? 'Dla usługi budowlanej kraj nabywcy oznacza kraj położenia nieruchomości.' : 'For property-related services, buyer destination means the property country.',
        ],
        'taxTreatmentGuidance' => $locale === 'pl' ? [
            'domestic_vat' => 'Sprzedaż jest krajowa, a wystawca jest czynnym podatnikiem VAT. Ustawiono domyślną stawkę profilu; wybierz stawkę obniżoną tylko wtedy, gdy konkretna pozycja rzeczywiście się do niej kwalifikuje.',
            'small_business_exempt' => 'Sprzedaż jest krajowa, a profil wystawcy nie nalicza VAT. Pozycje otrzymają stawkę 0% oraz adnotację właściwą dla kraju wystawcy.',
            'eu_b2c_destination_oss' => 'To usługa cyfrowa dla konsumenta w innym kraju UE, a profil ma włączony OSS. Moduł proponuje standardową stawkę kraju nabywcy i adnotację OSS.',
            'eu_distance_goods_oss' => 'To sprzedaż towaru konsumentowi w innym kraju UE, a profil ma włączony OSS. Moduł proponuje standardową stawkę kraju nabywcy; stawkę obniżoną wybierz tylko dla kwalifikującego się towaru.',
            'eu_b2b_reverse_charge' => 'Nabywca jest firmą z innego kraju UE. Zastosuj reverse charge dopiero po wpisaniu i sprawdzeniu jego VAT ID.',
            'construction_reverse_charge' => 'To transgraniczna usługa związana z nieruchomością dla firmy. Potwierdź kraj położenia nieruchomości i VAT ID nabywcy przed zastosowaniem reverse charge.',
            'medical_exempt_entity' => 'Profil i rodzaj usługi pozwalają rozważyć zwolnienie medyczne właściwe dla podmiotu leczniczego. Moduł ustawi 0% i właściwą adnotację profilu.',
            'medical_exempt_professional' => 'Profil i rodzaj usługi pozwalają rozważyć zwolnienie medyczne właściwe dla uprawnionego specjalisty. Moduł ustawi 0% i właściwą adnotację profilu.',
            'margin_scheme_second_hand' => 'Profil ma włączoną procedurę marży i sprzedaje towar używany. Wpisz cenę sprzedaży brutto oraz cenę nabycia brutto; VAT zostanie policzony wyłącznie od dodatniej marży.',
            'export_outside_eu' => 'To sprzedaż towaru poza UE. Moduł proponuje wariant eksportowy 0%; zachowaj dokumenty potwierdzające wywóz.',
            'uk_manual' => 'Transakcja dotyczy Wielkiej Brytanii. Moduł nie narzuca stawki, ponieważ wynik zależy od rodzaju sprzedaży, miejsca świadczenia i obowiązków rejestracyjnych.',
            'manual' => 'Brakuje jednoznacznej reguły, którą moduł może bezpiecznie zastosować automatycznie. Wybierz stawkę i adnotację po sprawdzeniu tej konkretnej transakcji.',
            'eu_goods_manual' => 'Sprzedaż towarów konsumentowi w innym kraju UE wymaga właściwej kategorii produktu i zasad sprzedaży na odległość. Profil nie ma aktywnego OSS, dlatego moduł pozostawia wybór ręczny.',
            'oss_disabled' => 'To usługa cyfrowa B2C do innego kraju UE, ale profil nie ma włączonego OSS. Włącz OSS w profilu tylko wtedy, gdy firma faktycznie rozlicza w tej procedurze, albo ustal wariant ręcznie.',
            'outside_service_manual' => 'To usługa dla nabywcy spoza UE. Miejsce opodatkowania zależy od rodzaju usługi i statusu nabywcy, dlatego moduł pozostawia decyzję ręczną.',
        ] : [
            'domestic_vat' => 'This is a domestic sale by a VAT-registered issuer. The profile default rate is proposed; use a reduced rate only when the specific item qualifies.',
            'small_business_exempt' => 'This is a domestic sale by an issuer that does not charge VAT. Items use 0% and the issuer-country exemption note.',
            'eu_b2c_destination_oss' => 'This is a digital service to an EU consumer and the issuer has OSS enabled. The destination-country standard rate and OSS note are proposed.',
            'eu_distance_goods_oss' => 'This is a goods sale to a consumer in another EU country and OSS is enabled. The destination standard rate is proposed; use a reduced rate only for qualifying goods.',
            'eu_b2b_reverse_charge' => 'The buyer is a business in another EU country. Use reverse charge only after entering and verifying its VAT ID.',
            'construction_reverse_charge' => 'This is a cross-border property-related service to a business. Confirm the property country and buyer VAT ID before using reverse charge.',
            'medical_exempt_entity' => 'The issuer profile and supply may use the qualifying healthcare-entity exemption. The module applies 0% and the profile-specific note.',
            'medical_exempt_professional' => 'The issuer profile and supply may use the qualifying healthcare-professional exemption. The module applies 0% and the profile-specific note.',
            'margin_scheme_second_hand' => 'The issuer has enabled the margin scheme for second-hand goods. Enter gross sale and purchase prices; VAT is calculated only from a positive margin.',
            'export_outside_eu' => 'This is a goods sale outside the EU. The module proposes the 0% export treatment; retain evidence of export.',
            'uk_manual' => 'This transaction concerns the United Kingdom. The module does not impose a rate because it depends on supply type, place-of-supply and registration duties.',
            'manual' => 'No rule can be applied safely from the available data. Review this transaction and choose the rate and note manually.',
            'eu_goods_manual' => 'EU distance sales of goods require the correct product category and distance-sales treatment. OSS is not enabled for this issuer, so the decision remains manual.',
            'oss_disabled' => 'This is an EU cross-border B2C digital service, but OSS is disabled for the issuer. Enable OSS only if the issuer actually reports through it, or decide manually.',
            'outside_service_manual' => 'This is a service supplied outside the EU. Tax location depends on the service and buyer status, so the decision remains manual.',
        ],
    ];
    ?>
    <section class="admin-billing" data-admin-billing data-billing-payload="<?php echo admin_e(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
        <nav class="nav nav-pills admin-billing__tabs" aria-label="Billing tabs">
            <?php foreach (['create', 'stats', 'documents', 'customers', 'settings'] as $tabKey): ?>
<a class="nav-link<?php echo $tab === $tabKey ? ' active' : ''; ?>" href="/admin/?page=billing&amp;billing_tab=<?php echo admin_e($tabKey); ?>&amp;billing_profile_id=<?php echo (int)($context['selected_profile_id'] ?? 0); ?>">
<?php echo admin_e($t[$tabKey]); ?>
</a>
<?php endforeach; ?>
        </nav>
        <?php if ($tab !== 'stats'): ?>
<div class="admin-billing-profile-switch">
<span>
<?php echo admin_e($t['profile']); ?>
</span>
<select class="form-select" data-billing-profile-filter data-billing-tab="<?php echo admin_e($tab); ?>">
<?php foreach ((array)($context['profiles'] ?? []) as $profileOption): ?>
<option value="<?php echo (int)$profileOption['id']; ?>"<?php echo (int)($context['selected_profile_id'] ?? 0) === (int)$profileOption['id'] ? ' selected' : ''; ?>>
<?php echo admin_e((string)$profileOption['profile_label'] . (!empty($profileOption['is_active']) ? '' : ' · OFF')); ?>
</option>
<?php endforeach; ?>
</select>
<?php if ($profileCountryPresentation['code'] !== ''): ?>
<span class="admin-billing-country-badge admin-billing-country-badge--profile" style="--billing-country-color:<?php echo admin_e($profileCountryPresentation['color']); ?>"><img src="<?php echo admin_e($profileCountryPresentation['flag_url']); ?>" alt="" aria-hidden="true"><?php echo admin_e($profileCountryPresentation['name']); ?></span>
<?php endif; ?>
</div>
<?php endif; ?>
        <?php if (in_array($tab, ['create', 'settings'], true)): $profileReadiness = (array)($context['profile_readiness'] ?? []); ?>
<div class="admin-billing-readiness<?php echo empty($profileReadiness['ready']) ? ' is-blocked' : ' is-ready'; ?>" data-billing-profile-readiness>
<div>
<i class="bi <?php echo empty($profileReadiness['ready']) ? 'bi-exclamation-circle' : 'bi-check-circle'; ?>" aria-hidden="true"></i>
<span><strong><?php echo admin_e(empty($profileReadiness['ready']) ? $t['profile_not_ready'] : $t['profile_ready']); ?></strong>
<?php foreach ((array)($profileReadiness['blockers'] ?? []) as $message): ?><small><?php echo admin_e($message); ?></small><?php endforeach; ?>
<?php foreach ((array)($profileReadiness['warnings'] ?? []) as $message): ?><small class="is-warning"><?php echo admin_e($message); ?></small><?php endforeach; ?>
</span>
</div>
<?php if (empty($profileReadiness['ready'])): ?><a class="btn btn-dark btn-sm" href="/admin/?page=billing&amp;billing_tab=settings&amp;billing_profile_id=<?php echo (int)($context['selected_profile_id'] ?? 0); ?>"><?php echo admin_e($t['open_settings']); ?></a><?php endif; ?>
</div>
<?php endif; ?>

        <?php if ($tab === 'create'): ?>
        <form method="post" class="admin-billing-form" data-billing-document-form autocomplete="off">
            <input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<?php $submissionToken = (string)($doc['billing_submission_token'] ?? ''); if (!preg_match('/^[a-f0-9]{64}$/D', $submissionToken)) $submissionToken = str_starts_with((string)($doc['source_unique_key'] ?? ''), 'manual:') ? substr((string)$doc['source_unique_key'], 7) : bin2hex(random_bytes(32)); ?>
<input type="hidden" name="billing_submission_token" value="<?php echo admin_e($submissionToken); ?>">
<input type="hidden" name="billing_document_id" value="<?php echo admin_e((string)($doc['id'] ?? 0)); ?>">
<input type="hidden" name="company_profile_id" value="<?php echo (int)($context['selected_profile_id'] ?? 0); ?>">
<input type="hidden" name="source_record_id" data-billing-source-id value="<?php echo admin_e((string)($doc['source_type'] ?? '') === 'order' ? (string)($doc['order_id'] ?? 0) : (string)($doc['payment_id'] ?? 0)); ?>">
            <div class="alert alert-info admin-billing-draft-notice" data-billing-draft-notice hidden><?php echo admin_e($t['draft_restored']); ?></div>
            <section class="admin-billing-section">
<div class="admin-billing-section__head">
<h3>
<?php echo admin_e($t['source']); ?>
</h3>
<p>
<?php echo admin_e($t['source_help']); ?>
</p>
</div>
<div class="row g-3">
                <div class="col-md-6">
<label class="form-label" for="billingSourceType">
<?php echo admin_e($t['source']); ?>
</label>
<select class="form-select" id="billingSourceType" name="source_type" data-billing-source-type>
<?php $siteName = (string)($context['site_name'] ?? 'Site'); ?>
<option value="independent"<?php echo $currentSource === 'independent' ? ' selected' : ''; ?>>
<?php echo admin_e($t['independent']); ?>
</option>
<?php if ($availableOrderSources || $currentSource === 'order'): ?>
<option value="order"<?php echo $currentSource === 'order' ? ' selected' : ''; ?>>
<?php echo admin_e($siteName . ' - ' . $t['order'] . ' (' . count($availableOrderSources) . ')'); ?>
</option>
<?php endif; ?>
<?php if ($availableCryptoSources || $currentSource === 'payment_crypto'): ?>
<option value="payment_crypto"<?php echo $currentSource === 'payment_crypto' ? ' selected' : ''; ?>>
<?php echo admin_e($siteName . ' - ' . $t['crypto'] . ' (' . count($availableCryptoSources) . ')'); ?>
</option>
<?php endif; ?>
<?php if ($availableBankSources || $currentSource === 'payment_bank'): ?>
<option value="payment_bank"<?php echo $currentSource === 'payment_bank' ? ' selected' : ''; ?>>
<?php echo admin_e($siteName . ' - ' . $t['bank'] . ' (' . count($availableBankSources) . ')'); ?>
</option>
<?php endif; ?>
</select>
<?php if (!$hasExternalSources && $currentSource === 'independent'): ?><div class="admin-billing-source-empty"><i class="bi bi-info-circle" aria-hidden="true"></i><span><?php echo admin_e($t['source_only_independent']); ?></span></div><?php endif; ?>
</div>
                <div class="col-md-6" data-billing-source-picker hidden>
<label class="form-label" for="billingSourceSelect">
<?php echo admin_e($t['source']); ?>
</label>
<select class="form-select" id="billingSourceSelect" data-billing-source-select>
<option value="">
<?php echo admin_e($t['none']); ?>
</option>
</select>
</div>
                <div class="col-12">
<div class="alert alert-info mb-0" data-billing-source-help>
<?php echo admin_e($t['source_independent_help']); ?>
</div>
</div>
                <div class="col-12" data-billing-source-audit hidden>
<div class="admin-billing-source-audit">
<div><strong><?php echo admin_e($t['source_match']); ?></strong><span data-billing-source-audit-message><?php echo admin_e($t['source_match_ok']); ?></span></div>
<div class="admin-billing-source-audit__values"><span data-billing-source-expected></span><i class="bi bi-arrow-left-right" aria-hidden="true"></i><span data-billing-source-document></span></div>
<label data-billing-source-override hidden><input class="form-check-input" type="checkbox" name="source_total_override_confirmed" value="1"<?php echo !empty($doc['source_total_override_confirmed']) ? ' checked' : ''; ?>> <span><?php echo admin_e($t['source_match_confirm']); ?></span></label>
</div>
</div>
            </div>
</section>
            <section class="admin-billing-section">
<div class="admin-billing-section__head">
<h3>
<?php echo admin_e($t['buyer']); ?>
</h3>
</div>
<details class="admin-billing-disclosure" data-billing-buyer-lookup-panel>
<summary><span><i class="bi bi-search" aria-hidden="true"></i> <?php echo $locale === 'pl' ? 'Wybierz istniejącego klienta' : 'Choose an existing customer'; ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body"><div class="row g-3">
                <div class="col-md-6">
<div class="admin-billing-buyer-lookup" data-billing-lookup>
<label class="form-label" for="billingCustomerSearch">
<?php echo admin_e($t['billing_customer']); ?>
</label>
<div class="admin-billing-buyer-lookup__input"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" id="billingCustomerSearch" type="search" placeholder="<?php echo admin_e($t['search_customer']); ?>" data-billing-customer-search autocomplete="off"><div class="admin-billing-buyer-lookup__results" data-billing-lookup-results hidden></div></div>
<select class="form-select mt-2" name="billing_customer_id" data-billing-customer-select>
<option value="0">
<?php echo admin_e($t['none']); ?>
</option>
<?php foreach ((array)($context['billing_customers'] ?? []) as $row): ?>
<option value="<?php echo (int)$row['id']; ?>"<?php echo (int)($doc['billing_customer_id'] ?? 0) === (int)$row['id'] ? ' selected' : ''; ?>>
<?php echo admin_e((string)$row['display_name'] . (!empty($row['email']) ? ' · ' . $row['email'] : '')); ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>
                <div class="col-md-6">
<div class="admin-billing-buyer-lookup" data-billing-lookup>
<label class="form-label" for="billingSiteUserSearch">
<?php echo admin_e($t['site_user']); ?>
</label>
<div class="admin-billing-buyer-lookup__input"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" id="billingSiteUserSearch" type="search" placeholder="<?php echo admin_e($t['search_user']); ?>" data-billing-site-user-search autocomplete="off"><div class="admin-billing-buyer-lookup__results" data-billing-lookup-results hidden></div></div>
<select class="form-select mt-2" name="linked_customer_id" data-billing-site-user-select>
<option value="0">
<?php echo admin_e($t['none']); ?>
</option>
<?php foreach ((array)($context['site_customers'] ?? []) as $row): ?>
<option value="<?php echo (int)$row['id']; ?>"<?php echo (int)($doc['customer_id'] ?? 0) === (int)$row['id'] ? ' selected' : ''; ?>>#<?php echo (int)$row['id']; ?> · @<?php echo admin_e((string)($row['public_handle'] ?? '')); ?> · <?php echo admin_e((string)$row['email']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>
</div></div></details>
<details class="admin-billing-disclosure" data-billing-buyer-fields-panel open>
<summary><span><i class="bi bi-person-vcard" aria-hidden="true"></i> <?php echo $locale === 'pl' ? 'Dane nabywcy · dodaj lub edytuj' : 'Buyer details · add or edit'; ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body"><div class="row g-3">
                <div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['buyer_name']); ?>
</label>
<input class="form-control" name="buyer_name" value="<?php echo admin_e($doc['buyer_name'] ?? ''); ?>" required data-billing-buyer="display_name">
</div>
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['company_name']); ?>
</label>
<input class="form-control" name="buyer_company_name" value="<?php echo admin_e($doc['buyer_company_name'] ?? ''); ?>" data-billing-buyer="company_name">
</div>
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['tax_id']); ?>
</label>
<input class="form-control" name="buyer_tax_id" value="<?php echo admin_e($doc['buyer_tax_id'] ?? ''); ?>" data-billing-buyer="tax_id">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['email']); ?>
</label>
<input class="form-control" type="email" name="buyer_email" value="<?php echo admin_e($doc['buyer_email'] ?? ''); ?>" data-billing-buyer="email">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['phone']); ?>
</label>
<input class="form-control" name="buyer_phone" value="<?php echo admin_e($doc['buyer_phone'] ?? ''); ?>" data-billing-buyer="phone">
</div>
                <div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['address']); ?>
</label>
<input class="form-control" name="buyer_address_line1" value="<?php echo admin_e($doc['buyer_address_line1'] ?? ''); ?>" data-billing-buyer="address_line1">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['address2']); ?>
</label>
<input class="form-control" name="buyer_address_line2" value="<?php echo admin_e($doc['buyer_address_line2'] ?? ''); ?>" data-billing-buyer="address_line2">
</div>
                <div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['postal']); ?>
</label>
<input class="form-control" name="buyer_postal_code" value="<?php echo admin_e($doc['buyer_postal_code'] ?? ''); ?>" data-billing-buyer="postal_code">
</div>
<div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['city']); ?>
</label>
<input class="form-control" name="buyer_city" value="<?php echo admin_e($doc['buyer_city'] ?? ''); ?>" data-billing-buyer="city">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['country']); ?>
</label>
<?php $buyerCountryCode = strtoupper((string)($doc['destination_country_code'] ?? '')); ?>
<select class="form-select" name="destination_country_code" data-billing-buyer="country_code" data-billing-destination-country>
<option value=""><?php echo admin_e($t['none']); ?></option>
<?php if ($buyerCountryCode !== '' && !isset($customerCountries[$buyerCountryCode])): ?><option value="<?php echo admin_e($buyerCountryCode); ?>" selected><?php echo admin_e($doc['buyer_country'] ?? $buyerCountryCode); ?></option><?php endif; ?>
<?php foreach ($customerCountries as $countryCode => $countryData): ?>
<option value="<?php echo admin_e($countryCode); ?>" data-country-name="<?php echo admin_e($countryData['name']); ?>"<?php echo $buyerCountryCode === $countryCode ? ' selected' : ''; ?>><?php echo admin_e($countryData['name'] . ' (' . $countryCode . ')'); ?></option>
<?php endforeach; ?>
</select>
<input type="hidden" name="buyer_country" value="<?php echo admin_e($doc['buyer_country'] ?? ''); ?>" data-billing-buyer="country_name" data-billing-buyer-country-name>
</div>
                <div class="col-12">
<div class="form-check">
<input class="form-check-input" type="checkbox" id="saveBillingCustomer" name="save_billing_customer" value="1"<?php echo !empty($doc['save_billing_customer']) ? ' checked' : ''; ?>>
<label class="form-check-label" for="saveBillingCustomer">
<?php echo admin_e($t['save_customer']); ?>
</label>
</div>
</div>
            </div>
</div></details>
</section>
            <section class="admin-billing-section">
<div class="admin-billing-section__head">
<h3>
<?php echo admin_e($t['document']); ?>
</h3>
</div>
<div class="row g-3">
                <div class="col-md-6 col-xl-4">
<label class="form-label">
<?php echo admin_e($t['language']); ?>
</label>
<select class="form-select w-100" name="language_code" data-billing-language>
<?php foreach ($supportedLanguages as $code => $label): ?>
<option value="<?php echo admin_e($code); ?>"<?php echo $documentLanguage === $code ? ' selected' : ''; ?>>
<?php echo admin_e($label); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 col-xl-4">
<label class="form-label">
<?php echo admin_e($t['currency']); ?>
</label>
<select class="form-select w-100" name="currency_code" data-billing-currency>
<?php foreach ($supportedCurrencies as $code => $label): ?>
<option value="<?php echo admin_e($code); ?>"<?php echo $documentCurrency === $code ? ' selected' : ''; ?>>
<?php echo admin_e($label); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 col-xl-4">
<label class="form-label">
<?php echo admin_e($t['price_mode']); ?>
</label>
<select class="form-select w-100" name="price_entry_mode" data-billing-price-mode>
<option value="net"<?php echo (string)($doc['price_entry_mode'] ?? 'net') === 'net' ? ' selected' : ''; ?>>
<?php echo admin_e($t['net_prices']); ?>
</option>
<option value="gross"<?php echo (string)($doc['price_entry_mode'] ?? '') === 'gross' ? ' selected' : ''; ?>>
<?php echo admin_e($t['gross_prices']); ?>
</option>
</select>
</div>
                <div class="col-md-6 col-xl-4">
<label class="form-label">
<?php echo admin_e($t['issue_date']); ?>
</label>
<input class="form-control w-100" type="date" name="issue_date" value="<?php echo admin_e($doc['issue_date'] ?? $today); ?>" required>
</div>
<div class="col-md-6 col-xl-4">
<label class="form-label">
<?php echo admin_e($t['service_date']); ?>
</label>
<input class="form-control w-100" type="date" name="service_date" value="<?php echo admin_e($doc['service_date'] ?? $today); ?>">
</div>
<div class="col-md-6 col-xl-4">
<label class="form-label">
<?php echo admin_e($t['due_date']); ?>
</label>
<input class="form-control w-100" type="date" name="due_date" value="<?php echo admin_e($doc['due_date'] ?? $dueDefault); ?>">
</div>
                <div class="col-lg-5 col-md-6">
<label class="form-label">
<?php echo admin_e($t['supply']); ?>
</label>
<?php $selectedSupplyType = (string)($doc['supply_type'] ?? admin_billing_default_supply_type((string)($profile['business_activity'] ?? 'general'))); $supplyTypes = admin_billing_supply_types($locale); ?>
<div class="admin-billing-supply-picker">
<div class="admin-billing-supply-picker__control"><i class="bi bi-briefcase" aria-hidden="true"></i><select class="form-select" name="supply_type" data-billing-supply-type>
<?php $supplyGroupLabels = $locale === 'pl'
    ? ['digital_professional' => 'Usługi cyfrowe i profesjonalne', 'specialized_services' => 'Pozostałe branże i usługi', 'goods' => 'Towary i produkty']
    : ['digital_professional' => 'Digital and professional services', 'specialized_services' => 'Other industries and services', 'goods' => 'Goods and products']; ?>
<?php foreach (admin_billing_supply_type_groups() as $groupKey => $groupTypes): ?>
<optgroup label="<?php echo admin_e($supplyGroupLabels[$groupKey]); ?>"><?php foreach ($groupTypes as $key): ?><option value="<?php echo admin_e($key); ?>"<?php echo $selectedSupplyType === $key ? ' selected' : ''; ?>><?php echo admin_e($supplyTypes[$key]); ?></option><?php endforeach; ?></optgroup>
<?php endforeach; ?>
</select></div>
<div class="admin-billing-supply-picker__help"><i class="bi bi-info-circle" aria-hidden="true"></i><small data-billing-supply-help></small></div>
</div>
</div>
<div class="col-lg-3 col-md-6">
<label class="form-label">
<?php echo admin_e($t['buyer_type']); ?>
</label>
<select class="form-select" name="buyer_type" data-billing-buyer-type>
<option value="consumer">
<?php echo admin_e($t['consumer']); ?>
</option>
<option value="business"<?php echo (string)($doc['buyer_type'] ?? '') === 'business' ? ' selected' : ''; ?>>
<?php echo admin_e($t['business']); ?>
</option>
</select>
</div>
<div class="col-lg-4 col-md-12">
<label class="form-label">
<?php echo admin_e($t['region_auto']); ?>
</label>
<select class="form-select" data-billing-transaction-region-display disabled>
<?php foreach (['domestic', 'eu', 'uk', 'outside_eu'] as $key): ?>
<option value="<?php echo admin_e($key); ?>"<?php echo (string)($doc['transaction_region'] ?? 'domestic') === $key ? ' selected' : ''; ?>>
<?php echo admin_e($t[$key]); ?>
</option>
<?php endforeach; ?>
</select>
<input type="hidden" name="transaction_region" value="<?php echo admin_e($doc['transaction_region'] ?? 'domestic'); ?>" data-billing-transaction-region>
</div>
                <div class="col-md-5">
<label class="form-label">
<?php echo admin_e($t['tax_treatment']); ?>
</label>
<select class="form-select" name="tax_treatment" data-billing-tax-treatment>
<?php foreach (admin_billing_tax_treatments($locale) as $key => $label): ?>
<option value="<?php echo admin_e($key); ?>"<?php echo (string)($doc['tax_treatment'] ?? 'domestic_vat') === $key ? ' selected' : ''; ?>>
<?php echo admin_e($label); ?>
</option>
<?php endforeach; ?>
</select>
<div class="alert alert-info admin-billing-context-help" role="note">
<span data-billing-tax-treatment-help><?php echo admin_e($payload['taxTreatmentGuidance'][(string)($doc['tax_treatment'] ?? 'domestic_vat')] ?? $payload['taxTreatmentGuidance']['manual'] ?? ''); ?></span>
</div>
</div>
<div class="col-md-7">
<label class="form-label">
<?php echo admin_e($t['tax_note']); ?>
</label>
<textarea class="form-control" rows="2" name="tax_treatment_note" data-billing-tax-note>
<?php echo admin_e($doc['tax_treatment_note'] ?? admin_billing_default_tax_note('domestic_vat', $documentLanguage, (string)($profile['country_code'] ?? ''))); ?>
</textarea>
</div>
<div class="col-12">
<div class="admin-billing-vat-assistant" data-billing-vat-assistant>
<div class="admin-billing-vat-assistant__content">
<div><strong data-billing-vat-title><?php echo admin_e($t['vat_assistant']); ?></strong><span class="admin-billing-vat-assistant__state" data-billing-vat-state></span></div>
<span data-billing-vat-message><?php echo admin_e($t['tax_warning']); ?></span>
<small data-billing-vat-context></small>
</div>
<div class="admin-billing-vat-assistant__result">
<span data-billing-vat-treatment></span>
<b data-billing-vat-rate></b>
</div>
<div class="admin-billing-vat-assistant__actions">
<button class="btn btn-dark btn-sm" type="button" data-billing-apply-vat><?php echo admin_e($t['vat_apply']); ?></button>
<button class="btn btn-outline-dark btn-sm" type="button" data-billing-apply-vat-rate><?php echo admin_e($t['vat_rate_apply']); ?></button>
</div>
</div>
</div>
<div class="col-12" data-billing-vat-id-review hidden>
<label class="admin-billing-context-confirmation">
<input class="form-check-input" type="checkbox" name="buyer_vat_id_verified" value="1"<?php echo !empty($doc['buyer_vat_id_verified']) ? ' checked' : ''; ?>>
<span><?php echo admin_e($t['vat_id_verified']); ?> <a class="admin-billing-vies-link" href="https://ec.europa.eu/taxation_customs/vies/#/vat-validation" target="_blank" rel="noopener noreferrer"><?php echo admin_e($t['verify_vies']); ?> <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></span>
</label>
</div>
<div class="col-12"><details class="admin-billing-disclosure"<?php echo !empty($doc['external_reference']) ? ' open' : ''; ?>>
<summary><span><i class="bi bi-sliders" aria-hidden="true"></i> <?php echo $locale === 'pl' ? 'Ustawienia zaawansowane' : 'Advanced settings'; ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body"><div>
<label class="form-label" data-billing-external-reference-label><?php echo admin_e($isPolishIssuer ? $t['external_reference_pl'] : $t['external_reference']); ?></label>
<input class="form-control" name="external_reference" value="<?php echo admin_e($doc['external_reference'] ?? ''); ?>" placeholder="<?php echo admin_e($isPolishIssuer ? 'KSeF / ERP / accounting ID' : 'ERP / accounting ID'); ?>" data-billing-external-reference>
<div class="alert alert-info admin-billing-context-help" role="note">
<span><?php echo admin_e($locale === 'pl'
    ? ($isPolishIssuer
        ? 'Opcjonalny identyfikator z KSeF, programu księgowego lub ERP. Ułatwia uzgodnienie dokumentu, ale nie zastępuje jego numeru ani numeru KSeF nadanego po wysyłce.'
        : 'Opcjonalny identyfikator z programu księgowego, ERP lub procesu rozliczeniowego. Ułatwia późniejsze wyszukanie i uzgodnienie dokumentu, ale nie zastępuje numeru rachunku lub faktury.')
    : ($isPolishIssuer
        ? 'Optional identifier from KSeF, accounting software or ERP. It supports reconciliation but does not replace the document number or the KSeF number assigned after submission.'
        : 'Optional identifier from accounting software, ERP or a bookkeeping workflow. It helps reconcile the document but does not replace the invoice or receipt number.')); ?></span>
</div>
</div>
</div></details></div>
<div class="col-md-6" data-billing-ksef-review hidden>
<label class="admin-billing-context-confirmation w-100">
<input class="form-check-input" type="checkbox" name="pl_ksef_review_confirmed" value="1"<?php echo !empty($doc['pl_ksef_review_confirmed']) ? ' checked' : ''; ?>>
<span><?php echo admin_e($t['ksef_review']); ?></span>
</label>
</div>
            </div>
</section>
            <section class="admin-billing-section">
<div class="admin-billing-section__head admin-billing-section__head--actions">
<h3>
<?php echo admin_e($t['items']); ?>
</h3>
<button class="btn btn-outline-dark btn-sm" type="button" data-billing-add-item>
<i class="bi bi-plus-lg" aria-hidden="true">
</i> <?php echo admin_e($t['add_item']); ?>
</button>
</div>
<div class="admin-billing-items" data-billing-items>
                <?php $isMarginTreatment = (string)($doc['tax_treatment'] ?? '') === 'margin_scheme_second_hand'; foreach ($items as $item):
                    $currentUnit = (string)($item['unit_label'] ?? reset($unitOptions));
                    $currentUnitKey = admin_billing_unit_key($currentUnit);
                    $displayUnitPrice = (string)($item['unit_price_net'] ?? 0);
                    if (empty($doc['_form_input']) && (string)($doc['price_entry_mode'] ?? 'net') === 'gross') {
                        $quantityValue = (float)($item['quantity'] ?? 0);
                        if ($quantityValue > 0 && isset($item['line_gross'])) {
                            $displayUnitPrice = (string)round((float)$item['line_gross'] / $quantityValue, 4);
                        }
                    }
                ?>
<div class="admin-billing-item" data-billing-item>
<label class="admin-billing-item__field admin-billing-item__field--description"><span><?php echo admin_e($t['description']); ?></span><input class="form-control" list="billingItemCatalog" name="item_description[]" placeholder="<?php echo admin_e($t['description']); ?>" value="<?php echo admin_e($item['description'] ?? ''); ?>" required data-billing-item-description></label>
<label class="admin-billing-item__field"><span><?php echo admin_e($t['quantity']); ?></span><input class="form-control" type="number" min="0.001" step="0.001" name="item_quantity[]" value="<?php echo admin_e((string)($item['quantity'] ?? 1)); ?>"></label>
<label class="admin-billing-item__field"><span><?php echo admin_e($t['unit']); ?></span><select class="form-select" name="item_unit[]" data-billing-item-unit>
<?php if ($currentUnitKey === ''): ?>
<option value="<?php echo admin_e($currentUnit); ?>" data-unit-key="custom" selected>
<?php echo admin_e($currentUnit); ?>
</option>
<?php endif; ?>
<?php foreach ($unitOptions as $unitKey => $unitLabel): ?>
<option value="<?php echo admin_e($unitLabel); ?>" data-unit-key="<?php echo admin_e($unitKey); ?>"<?php echo $currentUnitKey === $unitKey ? ' selected' : ''; ?>>
<?php echo admin_e($unitLabel); ?>
</option>
<?php endforeach; ?>
</select></label>
<label class="admin-billing-item__field"><span><?php echo admin_e($t['net_price']); ?></span><input class="form-control" type="number" min="0" step="0.0001" name="item_unit_price[]" value="<?php echo admin_e($displayUnitPrice); ?>"></label>
<label class="admin-billing-item__field admin-billing-item__field--purchase"<?php echo $isMarginTreatment ? '' : ' hidden'; ?>><span><?php echo admin_e($t['purchase_price']); ?></span><input class="form-control admin-billing-item__purchase" type="number" min="0" step="0.0001" name="item_purchase_price[]" value="<?php echo admin_e((string)($item['purchase_price_gross'] ?? '')); ?>" placeholder="<?php echo admin_e($t['purchase_price']); ?>" data-billing-item-purchase<?php echo $isMarginTreatment ? '' : ' hidden'; ?>></label>
<label class="admin-billing-item__field"><span><?php echo admin_e($t['vat']); ?></span><input class="form-control" type="number" min="0" max="100" step="0.01" name="item_tax_rate[]" value="<?php echo admin_e((string)($item['tax_rate_percent'] ?? $defaultTax)); ?>" list="billingVatRates" data-billing-item-tax-rate></label>
<div class="admin-billing-item__gross"><span><?php echo admin_e($t['gross']); ?></span><strong data-billing-line-gross>0.00</strong></div>
<button class="btn btn-outline-danger btn-sm admin-billing-item__remove" type="button" data-billing-remove-item title="<?php echo admin_e($locale === 'pl' ? 'Usuń pozycję' : 'Remove item'); ?>" aria-label="<?php echo admin_e($locale === 'pl' ? 'Usuń pozycję' : 'Remove item'); ?>">
<i class="bi bi-trash" aria-hidden="true">
</i>
</button>
</div>
<?php endforeach; ?>
<datalist id="billingItemCatalog" data-billing-item-catalog>
<?php foreach ((array)($context['item_catalog'] ?? []) as $catalogItem): ?>
<option value="<?php echo admin_e($catalogItem['description']); ?>">
</option>
<?php endforeach; ?>
</datalist>
<datalist id="billingVatRates" data-billing-vat-rates></datalist>
            </div>
<div class="admin-billing-totals">
<div>
<span>
<?php echo admin_e($t['subtotal']); ?>
</span>
<strong data-billing-subtotal>0.00</strong>
</div>
<div>
<span>
<?php echo admin_e($t['tax_total']); ?>
</span>
<strong data-billing-tax-total>0.00</strong>
</div>
<div class="is-total">
<span>
<?php echo admin_e($t['total']); ?>
</span>
<strong data-billing-total>0.00</strong>
</div>
</div>
</section>
            <details class="admin-billing-section admin-billing-disclosure"<?php echo !empty($doc['notes']) || !empty($doc['payment_notes']) ? ' open' : ''; ?>>
<summary><span><i class="bi bi-chat-left-text" aria-hidden="true"></i> <?php echo $locale === 'pl' ? 'Uwagi i informacje o płatności' : 'Notes and payment information'; ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body">
<div class="row g-3">
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['notes']); ?>
</label>
<textarea class="form-control" rows="3" name="notes">
<?php echo admin_e($doc['notes'] ?? $profile['default_notes'] ?? ''); ?>
</textarea>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['payment_notes']); ?>
</label>
<textarea class="form-control" rows="3" name="payment_notes">
<?php echo admin_e($doc['payment_notes'] ?? ''); ?>
</textarea>
</div>
</div>
</div></details>
            <section class="admin-billing-issue-checklist" data-billing-issue-checklist>
<div class="admin-billing-section__head"><h3><?php echo admin_e($t['issue_checklist']); ?></h3><p><?php echo admin_e($t['issue_checklist_intro']); ?></p></div>
<div class="admin-billing-issue-checklist__items" data-billing-checklist-items></div>
<label class="admin-billing-context-confirmation" data-billing-foreign-currency-review hidden>
<input class="form-check-input" type="checkbox" name="foreign_currency_review_confirmed" value="1"<?php echo !empty($doc['foreign_currency_review_confirmed']) ? ' checked' : ''; ?>>
<span><?php echo admin_e($t['foreign_currency_review']); ?></span>
</label>
</section>
            <label class="admin-billing-tax-review">
<input class="form-check-input" type="checkbox" name="tax_review_confirmed" value="1"<?php echo !empty($doc['tax_review_confirmed']) ? ' checked' : ''; ?>> <span>
<?php echo admin_e($t['tax_review']); ?>
</span>
</label>
            <div class="admin-billing-form__actions">
<button class="btn btn-outline-dark btn-lg" type="submit" name="admin_billing_save_document">
<?php echo admin_e($t['save_draft']); ?>
</button>
<button class="btn btn-success btn-lg" type="submit" name="admin_billing_issue_document" data-billing-issue-button<?php echo empty($context['company_complete']) ? ' disabled' : ''; ?>>
<?php echo admin_e($t['issue']); ?>
</button>
</div>
        </form>
        <?php elseif ($tab === 'stats'): $stats = (array)($context['stats'] ?? []); $overall = (array)($stats['overall'] ?? []); $sourceLabels = ['independent' => $t['independent'], 'order' => $t['order'], 'payment_crypto' => $t['crypto'], 'payment_bank' => $t['bank']]; ?>
            <section class="admin-billing-stats-overview">
                <div class="admin-billing-section__head">
<h3>
<?php echo admin_e($t['all_companies']); ?>
</h3>
<p>
<?php echo admin_e($t['all_companies_intro']); ?>
</p>
</div>
                <div class="admin-billing-stats-overview__grid">
                    <div>
<span>
<?php echo admin_e($t['active_profiles']); ?>
</span>
<strong>
<?php echo (int)($overall['active_profiles'] ?? 0); ?> / <?php echo (int)($overall['profiles_total'] ?? 0); ?>
</strong>
</div>
                    <div>
<span>
<?php echo admin_e($t['documents_total']); ?>
</span>
<strong>
<?php echo (int)($overall['documents_total'] ?? 0); ?>
</strong>
</div>
                    <?php foreach ((array)($overall['currencies'] ?? []) as $currency => $currencySummary): ?>
<div class="is-financial">
<span>
<?php echo admin_e($t['gross_total'] . ' · ' . $currency); ?>
</span>
<strong>
<?php echo admin_e(number_format((float)($currencySummary['gross_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</strong>
<small>
<?php echo admin_e($t['paid_total']); ?>: <?php echo admin_e(number_format((float)($currencySummary['paid_gross'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?> · <?php echo admin_e($t['outstanding']); ?>: <?php echo admin_e(number_format((float)($currencySummary['outstanding_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</small>
</div>
<?php endforeach; ?>
                </div>
            </section>

            <?php foreach ((array)($stats['profiles'] ?? []) as $profileStats): $statsProfile = (array)($profileStats['profile'] ?? []); $statsCountry = admin_billing_country_presentation((string)($statsProfile['country_code'] ?? ''), $locale, (string)($statsProfile['country_name'] ?? '')); ?>
                <section class="admin-billing-company-stats">
                    <header class="admin-billing-company-stats__header">
                        <div>
<?php if (!empty($statsProfile['logo_url'])): ?>
<img src="<?php echo admin_e($statsProfile['logo_url']); ?>" alt="">
<?php endif; ?>
<span>
<strong>
<?php echo admin_e($statsProfile['profile_label'] ?? $statsProfile['company_name'] ?? ''); ?>
</strong>
<small>
<?php echo admin_e($statsProfile['company_name'] ?? ''); ?>
<?php if ($statsCountry['code'] !== ''): ?><span class="admin-billing-country-badge admin-billing-country-badge--compact" style="--billing-country-color:<?php echo admin_e($statsCountry['color']); ?>"><img src="<?php echo admin_e($statsCountry['flag_url']); ?>" alt="" aria-hidden="true"><?php echo admin_e($statsCountry['name']); ?></span><?php endif; ?>
</small>
</span>
</div>
                        <span class="admin-billing-company-stats__state<?php echo empty($statsProfile['is_active']) ? ' is-off' : ''; ?>">
<?php echo admin_e(empty($statsProfile['is_active']) ? $t['profile_off'] : $t['profile_on']); ?>
</span>
                    </header>
                    <?php foreach ((array)($profileStats['currencies'] ?? []) as $currency => $currencyStats): $summary = (array)($currencyStats['summary'] ?? []); $currentMonth = (float)($summary['month_total'] ?? 0); $previousMonth = (float)($summary['previous_month_total'] ?? 0); $monthChange = $previousMonth > 0 ? (($currentMonth - $previousMonth) / $previousMonth) * 100 : ($currentMonth > 0 ? 100 : 0); ?>
                        <div class="admin-billing-currency-stats">
                            <div class="admin-billing-currency-stats__title">
<strong>
<?php echo admin_e($currency); ?>
</strong>
<span class="<?php echo $monthChange >= 0 ? 'is-positive' : 'is-negative'; ?>">
<?php echo $monthChange >= 0 ? '+' : ''; ?>
<?php echo admin_e(number_format($monthChange, 1, ',', ' ')); ?>% <?php echo admin_e($locale === 'pl' ? 'miesiąc do miesiąca' : 'month over month'); ?>
</span>
</div>
                            <div class="admin-billing-metrics admin-billing-metrics--extended">
                                <div>
<span>
<?php echo admin_e($t['issued_total']); ?>
</span>
<strong>
<?php echo (int)($summary['issued_count'] ?? 0); ?>
</strong>
<small>
<?php echo admin_e($t['drafts']); ?>: <?php echo (int)($summary['drafts_total'] ?? 0); ?> · <?php echo admin_e($t['cancelled_total']); ?>: <?php echo (int)($summary['cancelled_total'] ?? 0); ?>
</small>
</div>
                                <div>
<span>
<?php echo admin_e($t['paid_total']); ?>
</span>
<strong>
<?php echo (int)($summary['paid_total'] ?? 0); ?>
</strong>
<small>
<?php echo admin_e(number_format((float)($summary['paid_gross'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</small>
</div>
                                <div>
<span>
<?php echo admin_e($t['outstanding']); ?>
</span>
<strong>
<?php echo admin_e(number_format((float)($summary['outstanding_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</strong>
<small>
<?php echo (int)($summary['issued_total'] ?? 0); ?> <?php echo admin_e($t['documents_short']); ?>
</small>
</div>
                                <div class="<?php echo (float)($summary['overdue_total'] ?? 0) > 0 ? 'is-danger' : ''; ?>">
<span>
<?php echo admin_e($t['overdue']); ?>
</span>
<strong>
<?php echo admin_e(number_format((float)($summary['overdue_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</strong>
<small>
<?php echo (int)($summary['overdue_count'] ?? 0); ?> <?php echo admin_e($t['documents_short']); ?>
</small>
</div>
                                <div>
<span>
<?php echo admin_e($t['net_value']); ?>
</span>
<strong>
<?php echo admin_e(number_format((float)($summary['net_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</strong>
</div>
                                <div>
<span>
<?php echo admin_e($t['tax_value']); ?>
</span>
<strong>
<?php echo admin_e(number_format((float)($summary['tax_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</strong>
</div>
                                <div>
<span>
<?php echo admin_e($t['current_month']); ?>
</span>
<strong>
<?php echo admin_e(number_format($currentMonth, 2, ',', ' ') . ' ' . $currency); ?>
</strong>
<small>
<?php echo admin_e($t['previous_month']); ?>: <?php echo admin_e(number_format($previousMonth, 2, ',', ' ') . ' ' . $currency); ?>
</small>
</div>
                                <div>
<span>
<?php echo admin_e($t['current_year']); ?>
</span>
<strong>
<?php echo admin_e(number_format((float)($summary['year_total'] ?? 0), 2, ',', ' ') . ' ' . $currency); ?>
</strong>
</div>
                            </div>

                            <?php $monthly = (array)($currencyStats['monthly'] ?? []); if ($monthly): $maxGross = max(array_map(static fn(array $row): float => (float)($row['gross_total'] ?? 0), $monthly) ?: [1]); ?>
                                <div class="admin-billing-stat-panel">
<div class="admin-billing-stat-panel__head">
<strong>
<?php echo admin_e($t['sales_trend']); ?>
</strong>
</div>
<div class="admin-billing-monthly">
<?php foreach ($monthly as $row): $pct = $maxGross > 0 ? min(100, (float)$row['gross_total'] / $maxGross * 100) : 0; ?>
<div class="admin-billing-monthly__row">
<span>
<?php echo admin_e((string)$row['month_key']); ?>
</span>
<div>
<i style="width:<?php echo admin_e(number_format($pct, 2, '.', '')); ?>%">
</i>
</div>
<strong>
<?php echo admin_e(number_format((float)$row['gross_total'], 2, ',', ' ') . ' ' . $currency); ?>
<small>
<?php echo (int)$row['documents_total']; ?> <?php echo admin_e($t['documents_short']); ?> · VAT <?php echo admin_e(number_format((float)$row['tax_total'], 2, ',', ' ')); ?>
</small>
</strong>
</div>
<?php endforeach; ?>
</div>
</div>
                            <?php endif; ?>

                            <div class="admin-billing-stat-columns">
                                <div class="admin-billing-stat-panel">
<div class="admin-billing-stat-panel__head">
<strong>
<?php echo admin_e($t['sources_breakdown']); ?>
</strong>
</div>
<div class="admin-billing-stat-list">
<?php foreach ((array)($currencyStats['sources'] ?? []) as $row): ?>
<div>
<span>
<?php echo admin_e($sourceLabels[$row['source_type']] ?? (string)$row['source_type']); ?>
<small>
<?php echo (int)$row['documents_total']; ?> <?php echo admin_e($t['documents_short']); ?>
</small>
</span>
<strong>
<?php echo admin_e(number_format((float)$row['gross_total'], 2, ',', ' ') . ' ' . $currency); ?>
</strong>
</div>
<?php endforeach; ?>
</div>
</div>
                                <div class="admin-billing-stat-panel">
<div class="admin-billing-stat-panel__head">
<strong>
<?php echo admin_e($t['tax_breakdown']); ?>
</strong>
</div>
<div class="admin-billing-stat-list">
<?php foreach ((array)($currencyStats['tax_treatments'] ?? []) as $row): ?>
<div>
<span>
<?php echo admin_e(admin_billing_tax_treatments($locale)[$row['tax_treatment']] ?? (string)$row['tax_treatment']); ?>
<small>
<?php echo (int)$row['documents_total']; ?> <?php echo admin_e($t['documents_short']); ?>
</small>
</span>
<strong>
<?php echo admin_e(number_format((float)$row['tax_total'], 2, ',', ' ') . ' ' . $currency); ?>
<small>
<?php echo admin_e($locale === 'pl' ? 'brutto ' : 'gross '); ?>
<?php echo admin_e(number_format((float)$row['gross_total'], 2, ',', ' ')); ?>
</small>
</strong>
</div>
<?php endforeach; ?>
</div>
</div>
                            </div>
                            <?php if (!empty($currencyStats['customers'])): ?>
<div class="admin-billing-stat-panel">
<div class="admin-billing-stat-panel__head">
<strong>
<?php echo admin_e($t['top_customers']); ?>
</strong>
</div>
<div class="admin-billing-stat-list admin-billing-stat-list--customers">
<?php foreach ((array)$currencyStats['customers'] as $row): ?>
<div>
<span>
<strong>
<?php echo admin_e($row['customer_name'] ?? ''); ?>
</strong>
<small>
<?php echo admin_e($row['buyer_email'] ?? ''); ?>
</small>
</span>
<strong>
<?php echo admin_e(number_format((float)$row['gross_total'], 2, ',', ' ') . ' ' . $currency); ?>
<small>
<?php echo (int)$row['documents_total']; ?> <?php echo admin_e($t['documents_short']); ?>
</small>
</strong>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
                            <?php if (empty($summary['documents_total'])): ?>
<div class="admin-empty-state">
<?php echo admin_e($t['no_stats']); ?>
</div>
<?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        <?php elseif ($tab === 'documents'): ?>
            <form method="get" class="admin-billing-filter">
<input type="hidden" name="page" value="billing">
<input type="hidden" name="billing_tab" value="documents">
<input type="hidden" name="billing_profile_id" value="<?php echo (int)($context['selected_profile_id'] ?? 0); ?>">
<input class="form-control" name="billing_q" value="<?php echo admin_e($context['query'] ?? ''); ?>" placeholder="<?php echo admin_e($t['filter']); ?>" data-billing-list-search="documents">
<select class="form-select" name="billing_status" onchange="this.form.submit()">
<option value="">
<?php echo admin_e($t['all_statuses']); ?>
</option>
<?php foreach (['draft','issued','paid','cancelled'] as $status): ?>
<option value="<?php echo admin_e($status); ?>"<?php echo (string)($context['status_filter'] ?? '') === $status ? ' selected' : ''; ?>>
<?php echo admin_e(admin_billing_status_label($status, $locale)); ?>
</option>
<?php endforeach; ?>
</select>
<button class="btn btn-dark" type="submit">
<i class="bi bi-search" aria-hidden="true">
</i>
</button>
</form>
            <?php if (!empty($context['documents'])): ?>
<div class="table-responsive">
<table class="table admin-billing-table">
<thead>
<tr>
<th>
<?php echo admin_e($t['number']); ?>
</th>
<th>
<?php echo admin_e($t['status']); ?>
</th>
<th>
<?php echo admin_e($t['date']); ?>
</th>
<th>
<?php echo admin_e($t['customer']); ?>
</th>
<th>
<?php echo admin_e($t['amount']); ?>
</th>
<th>
<?php echo admin_e($t['actions']); ?>
</th>
</tr>
</thead>
<tbody>
<?php foreach ((array)$context['documents'] as $row):
    $status = (string)$row['status'];
    $billingEmail = strtolower(trim((string)($row['buyer_email'] ?? '')));
    $billingEmailValid = $billingEmail !== '' && filter_var($billingEmail, FILTER_VALIDATE_EMAIL) !== false;
    $billingEmailStatusValid = in_array($status, ['issued', 'paid'], true);
    $billingEmailEnabled = $billingEmailValid && $billingEmailStatusValid;
?>
<tr data-billing-filter-row="documents">
<td>
<strong>
<?php echo admin_e($row['document_number'] ?: 'Draft #' . $row['id']); ?>
</strong>
<small>
<?php echo admin_e(($row['profile_label'] ?? '') . ' · ' . ($row['source_type'] ?? '')); ?>
</small>
</td>
<td>
<span class="admin-billing-status is-<?php echo admin_e($status); ?>">
<?php echo admin_e(admin_billing_status_label($status, $locale)); ?>
</span>
</td>
<td>
<?php echo admin_e($row['issue_date']); ?>
</td>
<td>
<strong>
<?php echo admin_e($row['buyer_name']); ?>
</strong>
<small>
<?php echo admin_e($row['buyer_email'] ?? ''); ?>
</small>
<?php if (!empty($row['last_emailed_at'])): ?>
<small class="admin-billing-email-delivery"><i class="bi bi-envelope-check" aria-hidden="true"></i> <?php echo admin_e($t['email_sent']); ?>: <?php echo admin_e(date('d.m.Y, H:i', strtotime((string)$row['last_emailed_at']))); ?><?php if ((int)($row['email_delivery_count'] ?? 0) > 1): ?> · <?php echo (int)$row['email_delivery_count']; ?>×<?php endif; ?></small>
<?php endif; ?>
</td>
<td>
<strong>
<?php echo admin_e(number_format((float)$row['total_gross'], 2, ',', ' ') . ' ' . $row['currency_code']); ?>
</strong>
</td>
<td>
<div class="admin-billing-table__actions">
<a class="btn btn-success btn-sm" href="/admin/?page=billing&amp;billing_pdf=<?php echo (int)$row['id']; ?>" target="_blank">
<?php echo admin_e($t['pdf']); ?>
</a>
<form method="post" class="admin-billing-email-form"<?php if ($billingEmailEnabled): ?> onsubmit="return confirm(<?php echo admin_e(json_encode($t['send_email_confirm'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)"<?php endif; ?>>
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="billing_document_id" value="<?php echo (int)$row['id']; ?>">
<input type="hidden" name="admin_billing_document_action" value="1">
<input type="hidden" name="billing_action" value="send_email">
<input type="hidden" name="recipient_acceptance_confirmed" value="1">
<button class="btn btn-primary btn-sm" type="submit"<?php echo $billingEmailEnabled ? '' : ' disabled'; ?> title="<?php echo admin_e(!$billingEmailValid ? $t['send_email_missing'] : (!$billingEmailStatusValid ? $t['send_email_status'] : $t['send_email'])); ?>">
<?php echo admin_e($t['send_email']); ?>
</button>
</form>
<?php if ($status === 'draft'): ?>
<a class="btn btn-dark btn-sm" href="/admin/?page=billing&amp;billing_tab=create&amp;edit_document=<?php echo (int)$row['id']; ?>">
<?php echo admin_e($t['edit']); ?>
</a>
<?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="billing_document_id" value="<?php echo (int)$row['id']; ?>">
<input type="hidden" name="admin_billing_document_action" value="1">
<?php if ($status === 'draft'): ?>
<button class="btn btn-danger btn-sm" name="billing_action" value="delete" onclick="return confirm('Delete draft?')">
<i class="bi bi-trash" aria-hidden="true">
</i>
</button>
<?php elseif ($status === 'issued'): ?>
<button class="btn btn-success btn-sm" name="billing_action" value="paid">
<?php echo admin_e($t['mark_paid']); ?>
</button>
<button class="btn btn-outline-danger btn-sm" name="billing_action" value="cancel">
<?php echo admin_e($t['cancel']); ?>
</button>
<?php endif; ?>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="admin-empty-state" data-billing-filter-empty="documents" hidden>
<?php echo admin_e($t['empty']); ?>
</div>
<?php else: ?>
<div class="admin-empty-state">
<?php echo admin_e($t['empty']); ?>
</div>
<?php endif; ?>
        <?php elseif ($tab === 'settings'): ?>
            <details class="admin-billing-section admin-billing-disclosure"<?php echo ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ' open' : ''; ?>>
<summary><span><?php echo admin_e($locale === 'pl' ? 'Automatyczne dokumenty' : 'Automatic documents'); ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body">
                <?php if (!empty($context['billing_auto_can_manage'])): ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="billingAutoIssueEmailEnabled" name="billing_auto_issue_email_enabled" value="1"<?php echo !empty($context['billing_auto_issue_email_enabled']) ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="billingAutoIssueEmailEnabled"><strong><?php echo admin_e($locale === 'pl' ? 'Rachunek przy zatwierdzaniu zamówienia' : 'Billing document on order approval'); ?></strong></label>
                        </div>
                        <small class="text-muted"><?php echo admin_e($locale === 'pl' ? 'Switch udostępnia checkbox w zamówieniu, ale każde wysłanie admin wybiera osobno. Checkbox w zamówieniu jest zawsze domyślnie wyłączony.' : 'This switch enables an order checkbox, but the admin chooses every delivery separately. The order checkbox is always OFF by default.'); ?></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="billingAutoCompanyProfile"><?php echo admin_e($locale === 'pl' ? 'Profil wystawcy' : 'Issuer profile'); ?></label>
                        <select class="form-select" id="billingAutoCompanyProfile" name="billing_auto_company_profile_id">
                            <option value="0"><?php echo admin_e($locale === 'pl' ? 'Wybierz profil' : 'Select profile'); ?></option>
                            <?php foreach ((array)($context['profiles'] ?? []) as $autoProfile): ?>
                            <option value="<?php echo (int)$autoProfile['id']; ?>"<?php echo (int)($context['billing_auto_company_profile_id'] ?? 0) === (int)$autoProfile['id'] ? ' selected' : ''; ?><?php echo empty($autoProfile['is_active']) ? ' disabled' : ''; ?>><?php echo admin_e(($autoProfile['profile_label'] ?? $autoProfile['company_name'] ?? '') . ' · ' . ($autoProfile['country_code'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="billingAutoSupplyType"><?php echo admin_e($locale === 'pl' ? 'Domyślny rodzaj sprzedaży' : 'Default sale type'); ?></label>
                        <select class="form-select" id="billingAutoSupplyType" name="billing_auto_supply_type">
                            <?php foreach (admin_billing_supply_types($locale) as $autoSupplyKey => $autoSupplyLabel): ?>
                            <option value="<?php echo admin_e($autoSupplyKey); ?>"<?php echo (string)($context['billing_auto_supply_type'] ?? 'digital_service') === $autoSupplyKey ? ' selected' : ''; ?>><?php echo admin_e($autoSupplyLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info mb-0"><?php echo admin_e($locale === 'pl' ? 'Dokument użyje faktycznie zaakceptowanej kwoty płatności. Wysyłka jest dostępna tylko dla kompletnej kartoteki klienta ze zgodą na e-doręczenie; niejednoznaczne przypadki B2B lub VAT pozostają do wystawienia ręcznego.' : 'The document uses the actually accepted payment amount. Delivery requires complete customer billing details and electronic-delivery consent; ambiguous B2B or VAT cases remain manual.'); ?></div>
                    </div>
                    <div class="col-12"><button class="btn btn-success" name="admin_billing_save_automation"><?php echo admin_e($locale === 'pl' ? 'Zapisz automatyzację' : 'Save automation'); ?></button></div>
                </form>
                <?php else: ?>
                <div class="alert alert-info mb-0"><?php echo admin_e($locale === 'pl' ? 'Te ustawienia może zmienić tylko główne konto admin.' : 'Only the main admin account can change these settings.'); ?></div>
                <?php endif; ?>
            </div></details>
            <section class="admin-billing-section">
<div class="admin-billing-section__head admin-billing-section__head--actions">
<div>
<h3>
<?php echo admin_e($t['company']); ?>
</h3>
<p>
<?php echo admin_e($t['company_intro']); ?>
</p>
</div>
<a class="btn btn-outline-dark btn-sm" href="/admin/?page=billing&amp;billing_tab=settings&amp;new_profile=1">
<i class="bi bi-plus-lg" aria-hidden="true">
</i> <?php echo admin_e($t['new_profile']); ?>
</a>
</div>
<form method="post" enctype="multipart/form-data" class="row g-3" data-billing-company-form>
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="company_profile_id" value="<?php echo (int)($profile['id'] ?? 0); ?>">
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['profile_label']); ?>
</label>
<input class="form-control" name="profile_label" value="<?php echo admin_e($profile['profile_label'] ?? ''); ?>" required>
</div>
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['country']); ?>
</label>
<select class="form-select" name="country_code" data-billing-profile-country>
<?php foreach (admin_billing_supported_countries() as $code => $country): ?>
<option value="<?php echo admin_e($code); ?>" data-country-name="<?php echo admin_e($country['name']); ?>"<?php echo (string)($profile['country_code'] ?? 'IE') === $code ? ' selected' : ''; ?>>
<?php echo admin_e(($customerCountries[$code]['name'] ?? $country['name']) . ' (' . $code . ')'); ?>
</option>
<?php endforeach; ?>
</select>
<span class="admin-billing-country-preview" data-billing-profile-country-preview><img src="<?php echo admin_e($profileCountryPresentation['flag_url']); ?>" alt="" aria-hidden="true"><span><?php echo admin_e($profileCountryPresentation['name']); ?></span></span>
<input type="hidden" name="country_name" value="<?php echo admin_e((string)(admin_billing_supported_countries()[(string)($profile['country_code'] ?? 'IE')]['name'] ?? 'Ireland')); ?>">
</div>
<div class="col-md-4 d-flex align-items-end gap-4 pb-2">
<label>
<input type="checkbox" name="is_active" value="1"<?php echo !array_key_exists('is_active', $profile) || !empty($profile['is_active']) ? ' checked' : ''; ?>> <?php echo admin_e($t['active']); ?>
</label>
<label>
<input type="checkbox" name="is_default" value="1"<?php echo !empty($profile['is_default']) ? ' checked' : ''; ?>> <?php echo admin_e($t['default']); ?>
</label>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['company_name']); ?>
</label>
<input class="form-control" name="company_name" value="<?php echo admin_e($profile['company_name'] ?? ''); ?>" required>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['legal_name']); ?>
</label>
<input class="form-control" name="legal_name" value="<?php echo admin_e($profile['legal_name'] ?? ''); ?>">
</div>
            <?php foreach ([['address_line1',$t['address'],'6'],['address_line2',$t['address2'],'6'],['postal_code',$t['postal'],'3'],['city',$t['city'],'3'],['email',$t['email'],'3'],['phone',$t['phone'],'3'],['website',$t['website'],'3'],['vat_id',$t['vat_id'],'3'],['tax_number',$t['tax_number'],'3'],['registration_number',$t['registration'],'3'],['bank_name',$t['bank_name'],'3'],['iban',$t['iban'],'6'],['bic',$t['bic'],'3']] as [$name,$label,$width]): ?>
<div class="col-md-<?php echo $width; ?>">
<label class="form-label">
<?php echo admin_e($label); ?>
</label>
<input class="form-control" name="<?php echo admin_e($name); ?>" value="<?php echo admin_e($profile[$name] ?? ''); ?>"<?php echo in_array($name, ['address_line1','postal_code','city'], true) ? ' required' : ''; ?>>
</div>
<?php endforeach; ?>
            <div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['business_activity']); ?>
</label>
<select class="form-select" name="business_activity" data-billing-profile-activity>
<?php foreach (admin_billing_business_activities($locale) as $code => $label): ?>
<option value="<?php echo admin_e($code); ?>"<?php echo (string)($profile['business_activity'] ?? 'general') === $code ? ' selected' : ''; ?>>
<?php echo admin_e($label); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['vat_status']); ?>
</label>
<select class="form-select" name="vat_status" data-billing-profile-vat-status>
<?php foreach (admin_billing_vat_statuses($locale) as $code => $label): ?>
<option value="<?php echo admin_e($code); ?>"<?php echo (string)($profile['vat_status'] ?? 'vat_registered') === $code ? ' selected' : ''; ?>>
<?php echo admin_e($label); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12">
<label class="form-label">
<?php echo admin_e($t['tax_guidance_note']); ?>
</label>
<textarea class="form-control" name="tax_guidance_note" rows="2">
<?php echo admin_e($profile['tax_guidance_note'] ?? ''); ?>
</textarea>
</div>
<?php $profileCapabilities = admin_billing_profile_capabilities((string)($profile['country_code'] ?? 'IE'), (string)($profile['business_activity'] ?? 'general'), (string)($profile['vat_status'] ?? 'vat_registered')); ?>
<div class="col-12"><div class="admin-billing-profile-capabilities">
<label data-billing-profile-capability="oss"<?php echo empty($profileCapabilities['oss']) ? ' hidden' : ''; ?>><input class="form-check-input" type="checkbox" name="eu_oss_enabled" value="1"<?php echo !empty($profile['eu_oss_enabled']) && !empty($profileCapabilities['oss']) ? ' checked' : ''; ?>><span><strong><?php echo admin_e($t['eu_oss_enabled']); ?></strong><small><?php echo admin_e($locale === 'pl' ? 'Dostępne dla zarejestrowanego podatnika VAT z profilem usług cyfrowych albo działalności mieszanej w UE.' : 'Available to an EU VAT-registered issuer configured for digital or mixed activity.'); ?></small></span></label>
<label data-billing-profile-capability="medical"<?php echo empty($profileCapabilities['medical']) ? ' hidden' : ''; ?>><input class="form-check-input" type="checkbox" name="medical_exemption_enabled" value="1"<?php echo !empty($profile['medical_exemption_enabled']) && !empty($profileCapabilities['medical']) ? ' checked' : ''; ?>><span><strong><?php echo admin_e($t['medical_exemption_enabled']); ?></strong><small><?php echo admin_e($locale === 'pl' ? 'Dostępne wyłącznie dla profilu medycznego, psychologicznego lub terapeutycznego oraz właściwego statusu VAT.' : 'Available only to healthcare, psychology or therapy profiles with the applicable VAT status.'); ?></small></span></label>
<label data-billing-profile-capability="margin"<?php echo empty($profileCapabilities['margin']) ? ' hidden' : ''; ?>><input class="form-check-input" type="checkbox" name="margin_scheme_enabled" value="1"<?php echo !empty($profile['margin_scheme_enabled']) && !empty($profileCapabilities['margin']) ? ' checked' : ''; ?>><span><strong><?php echo admin_e($t['margin_scheme_enabled']); ?></strong><small><?php echo admin_e($locale === 'pl' ? 'Dostępne wyłącznie dla zarejestrowanego podatnika VAT prowadzącego handel towarami używanymi.' : 'Available only to a VAT-registered issuer trading second-hand goods.'); ?></small></span></label>
<p class="admin-billing-profile-capabilities__empty" data-billing-profile-capabilities-empty<?php echo array_filter($profileCapabilities) ? ' hidden' : ''; ?>><?php echo admin_e($locale === 'pl' ? 'Brak dodatkowych procedur dla tej konfiguracji profilu.' : 'No additional procedures are available for this profile configuration.'); ?></p>
</div></div>
<div class="col-12"><div class="alert alert-info mb-0" data-billing-profile-guidance></div></div>
            <div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['currency']); ?>
</label>
<select class="form-select" name="default_currency_code" data-billing-profile-currency>
<?php foreach (admin_billing_supported_currencies() as $code => $label): ?>
<option value="<?php echo admin_e($code); ?>"<?php echo (string)($profile['default_currency_code'] ?? 'EUR') === $code ? ' selected' : ''; ?>>
<?php echo admin_e($label); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['prefix']); ?>
</label>
<input class="form-control" name="number_prefix" value="<?php echo admin_e($profile['number_prefix'] ?? 'INV-{YYYY}-'); ?>" data-billing-profile-prefix>
</div>
<div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['terms']); ?>
</label>
<input class="form-control" type="number" min="0" max="365" name="payment_terms_days" value="<?php echo (int)($profile['payment_terms_days'] ?? 14); ?>">
</div>
<div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['logo']); ?>
</label>
<input class="form-control" type="file" accept="image/png,image/jpeg,image/webp" name="company_logo" data-billing-logo-input>
</div>
<div class="col-12 admin-billing-logo-preview<?php echo empty($profile['logo_url']) ? ' is-empty' : ''; ?>">
<img src="<?php echo admin_e($profile['logo_url'] ?? ''); ?>" alt="" data-billing-logo-preview>
<label<?php echo empty($profile['logo_url']) ? ' hidden' : ''; ?>>
<input type="checkbox" name="remove_logo" value="1"> <?php echo admin_e($locale === 'pl' ? 'Usuń logo' : 'Remove logo'); ?>
</label>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['notes']); ?>
</label>
<textarea class="form-control" name="default_notes" rows="2">
<?php echo admin_e($profile['default_notes'] ?? ''); ?>
</textarea>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['footer']); ?>
</label>
<textarea class="form-control" name="default_footer" rows="2">
<?php echo admin_e($profile['default_footer'] ?? ''); ?>
</textarea>
</div>
<div class="col-12">
<button class="btn btn-success" name="admin_billing_save_company">
<?php echo admin_e($t['save_company']); ?>
</button>
</div>
</form>
</section>
            <details class="admin-billing-section admin-billing-disclosure"<?php echo ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ' open' : ''; ?>>
<summary><span><?php echo admin_e($t['tax_profiles']); ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body">
<div class="alert alert-info mb-3"><?php echo admin_e($locale === 'pl' ? 'Tutaj edytujesz stawki kraju wybranego profilu. Standardowe stawki krajów UE używane przez asystenta OSS są utrzymywane osobno przez moduł.' : 'Edit the selected issuer-country rates here. EU destination standard rates used by the OSS assistant are maintained separately by the module.'); ?></div>
<div class="admin-billing-tax-grid">
<?php foreach ((array)($context['tax_rates'] ?? []) as $rateRow): if (strtoupper((string)($rateRow['country_code'] ?? '')) !== strtoupper((string)($profile['country_code'] ?? ''))) continue; ?>
<form method="post" class="admin-billing-tax-row">
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="tax_rate_id" value="<?php echo (int)$rateRow['id']; ?>">
<input type="hidden" name="tax_country_code" value="<?php echo admin_e($rateRow['country_code']); ?>">
<strong>
<?php echo admin_e($rateRow['country_code']); ?>
</strong>
<input class="form-control" name="tax_label" value="<?php echo admin_e($rateRow['label']); ?>">
<input class="form-control" type="number" min="0" max="100" step="0.01" name="tax_rate_percent" value="<?php echo admin_e($rateRow['rate_percent']); ?>">
<label>
<input type="checkbox" name="tax_is_active" value="1"<?php echo !empty($rateRow['is_active']) ? ' checked' : ''; ?>> <?php echo admin_e($t['active']); ?>
</label>
<label>
<input type="checkbox" name="tax_is_default" value="1"<?php echo !empty($rateRow['is_default']) ? ' checked' : ''; ?>> <?php echo admin_e($t['default']); ?>
</label>
<button class="btn btn-outline-dark btn-sm" name="admin_billing_save_tax_rate">
<?php echo admin_e($t['save_rate']); ?>
</button>
</form>
<?php endforeach; ?>
</div>
</div></details>
            <?php $ossRateCountries = array_values(array_filter(admin_billing_eu_country_codes(), static fn(string $code): bool => $code !== strtoupper((string)($profile['country_code'] ?? '')))); ?>
            <details class="admin-billing-oss-rates">
<summary><span><strong><?php echo admin_e($locale === 'pl' ? 'Stawki docelowe OSS' : 'OSS destination rates'); ?></strong><small><?php echo admin_e($locale === 'pl' ? 'Standardowe stawki pozostałych państw UE' : 'Standard rates for other EU countries'); ?></small></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-oss-rates__body">
<div class="alert alert-warning mb-3"><?php echo admin_e($locale === 'pl' ? 'Te stawki są używane przez asystenta tylko przy sprzedaży B2C objętej OSS. Zmień stawkę po potwierdzeniu aktualnej wartości; wybór obniżonej stawki dla konkretnego produktu nadal należy wykonać w pozycji dokumentu.' : 'These rates are used only for B2C sales handled through OSS. Update a rate after confirming the current value; any reduced product-specific rate must still be selected on the document item.'); ?></div>
<label class="form-label" for="billingOssRateCountry"><?php echo admin_e($locale === 'pl' ? 'Kraj docelowy' : 'Destination country'); ?></label>
<select class="form-select mb-3" id="billingOssRateCountry" data-billing-oss-rate-country>
<?php foreach ($ossRateCountries as $index => $countryCode): $countryName = (string)($customerCountries[$countryCode]['name'] ?? $countryCode); ?>
<option value="<?php echo admin_e($countryCode); ?>"<?php echo $index === 0 ? ' selected' : ''; ?>><?php echo admin_e($countryName . ' (' . $countryCode . ')'); ?></option>
<?php endforeach; ?>
</select>
<div class="admin-billing-tax-grid">
<?php foreach ((array)($context['tax_rates'] ?? []) as $rateRow): $rateCountry = strtoupper((string)($rateRow['country_code'] ?? '')); if (!in_array($rateCountry, $ossRateCountries, true)) continue; ?>
<form method="post" class="admin-billing-tax-row" data-billing-oss-rate-row="<?php echo admin_e($rateCountry); ?>" hidden>
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="tax_rate_id" value="<?php echo (int)$rateRow['id']; ?>">
<input type="hidden" name="tax_country_code" value="<?php echo admin_e($rateCountry); ?>">
<strong><?php echo admin_e($rateCountry); ?></strong>
<input class="form-control" name="tax_label" value="<?php echo admin_e($rateRow['label']); ?>">
<input class="form-control" type="number" min="0" max="100" step="0.01" name="tax_rate_percent" value="<?php echo admin_e($rateRow['rate_percent']); ?>">
<label><input type="checkbox" name="tax_is_active" value="1"<?php echo !empty($rateRow['is_active']) ? ' checked' : ''; ?>> <?php echo admin_e($t['active']); ?></label>
<label><input type="checkbox" name="tax_is_default" value="1"<?php echo !empty($rateRow['is_default']) ? ' checked' : ''; ?>> <?php echo admin_e($t['default']); ?></label>
<button class="btn btn-outline-dark btn-sm" name="admin_billing_save_tax_rate"><?php echo admin_e($t['save_rate']); ?></button>
</form>
<?php endforeach; ?>
</div>
</div>
</details>
            <details class="admin-billing-section admin-billing-disclosure"<?php echo ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ' open' : ''; ?>>
<summary><span><?php echo admin_e($t['item_catalog']); ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body">
<?php
$catalogLanguage = admin_billing_country_language((string)($profile['country_code'] ?? 'IE'));
$catalogUnits = admin_billing_units($catalogLanguage);
$catalogDefaultUnit = (string)(reset($catalogUnits) ?: 'pcs');
$catalogDefaultSupply = admin_billing_default_supply_type((string)($profile['business_activity'] ?? 'general'));
?>
<div class="admin-billing-catalog">
<form method="post" class="admin-billing-catalog__row admin-billing-catalog__row--new">
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="company_profile_id" value="<?php echo (int)($context['selected_profile_id'] ?? 0); ?>">
<input type="hidden" name="catalog_item_id" value="0">
<label class="admin-billing-catalog__field admin-billing-catalog__field--description"><span><?php echo admin_e($t['new_catalog_item']); ?></span><input class="form-control" name="catalog_description" placeholder="<?php echo admin_e($t['description']); ?>" required></label>
<label class="admin-billing-catalog__field"><span><?php echo admin_e($t['unit']); ?></span><select class="form-select" name="catalog_unit_label"><?php foreach ($catalogUnits as $unitLabel): ?><option value="<?php echo admin_e($unitLabel); ?>"<?php echo $unitLabel === $catalogDefaultUnit ? ' selected' : ''; ?>><?php echo admin_e($unitLabel); ?></option><?php endforeach; ?></select></label>
<label class="admin-billing-catalog__field"><span><?php echo admin_e($t['net_price']); ?></span><input class="form-control" type="number" min="0" step="0.0001" name="catalog_unit_price_net" value="0.0000"></label>
<label class="admin-billing-catalog__field"><span><?php echo admin_e($t['vat']); ?></span><input class="form-control" type="number" min="0" max="100" step="0.01" name="catalog_tax_rate_percent" value="<?php echo admin_e((string)$defaultTax); ?>"></label>
<label class="admin-billing-catalog__field admin-billing-catalog__field--supply"><span><?php echo admin_e($t['supply']); ?></span><select class="form-select" name="catalog_supply_type"><?php foreach (admin_billing_supply_types($locale) as $supplyKey => $supplyLabel): ?><option value="<?php echo admin_e($supplyKey); ?>"<?php echo $catalogDefaultSupply === $supplyKey ? ' selected' : ''; ?>><?php echo admin_e($supplyLabel); ?></option><?php endforeach; ?></select></label>
<div class="admin-billing-catalog__usage"><span><?php echo admin_e($t['catalog_usage']); ?></span><strong>0</strong><small><?php echo admin_e($t['catalog_never_used']); ?></small></div>
<div class="admin-billing-catalog__actions"><label class="admin-billing-catalog__active"><input class="form-check-input" type="checkbox" name="catalog_is_active" value="1" checked><span><?php echo admin_e($t['active']); ?></span></label><button class="btn btn-dark btn-sm" name="admin_billing_save_catalog_item"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?php echo admin_e($t['save_item']); ?></button></div>
</form>
<?php foreach ((array)($context['item_catalog'] ?? []) as $catalogRow): ?>
<form method="post" class="admin-billing-catalog__row">
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="company_profile_id" value="<?php echo (int)($context['selected_profile_id'] ?? 0); ?>">
<input type="hidden" name="catalog_item_id" value="<?php echo (int)$catalogRow['id']; ?>">
<label class="admin-billing-catalog__field admin-billing-catalog__field--description"><span><?php echo admin_e($t['description']); ?></span><input class="form-control" name="catalog_description" value="<?php echo admin_e($catalogRow['description']); ?>" required></label>
<label class="admin-billing-catalog__field"><span><?php echo admin_e($t['unit']); ?></span><input class="form-control" name="catalog_unit_label" value="<?php echo admin_e($catalogRow['unit_label']); ?>"></label>
<label class="admin-billing-catalog__field"><span><?php echo admin_e($t['net_price']); ?></span><input class="form-control" type="number" min="0" step="0.0001" name="catalog_unit_price_net" value="<?php echo admin_e($catalogRow['default_unit_price_net']); ?>"></label>
<label class="admin-billing-catalog__field"><span><?php echo admin_e($t['vat']); ?></span><input class="form-control" type="number" min="0" max="100" step="0.01" name="catalog_tax_rate_percent" value="<?php echo admin_e($catalogRow['default_tax_rate_percent']); ?>"></label>
<label class="admin-billing-catalog__field admin-billing-catalog__field--supply"><span><?php echo admin_e($t['supply']); ?></span><select class="form-select" name="catalog_supply_type">
<?php foreach (admin_billing_supply_types($locale) as $supplyKey => $supplyLabel): ?>
<option value="<?php echo admin_e($supplyKey); ?>"<?php echo (string)$catalogRow['supply_type'] === $supplyKey ? ' selected' : ''; ?>>
<?php echo admin_e($supplyLabel); ?>
</option>
<?php endforeach; ?>
</select></label>
<div class="admin-billing-catalog__usage"><span><?php echo admin_e($t['catalog_usage']); ?></span><strong><?php echo (int)($catalogRow['usage_count'] ?? 0); ?></strong><small><?php echo !empty($catalogRow['last_used_at']) ? admin_e(date('d.m.Y', strtotime((string)$catalogRow['last_used_at']))) : admin_e($t['catalog_never_used']); ?></small></div>
<div class="admin-billing-catalog__actions"><label class="admin-billing-catalog__active"><input class="form-check-input" type="checkbox" name="catalog_is_active" value="1"<?php echo !array_key_exists('is_active', $catalogRow) || !empty($catalogRow['is_active']) ? ' checked' : ''; ?>><span><?php echo admin_e($t['active']); ?></span></label><button class="btn btn-success btn-sm" name="admin_billing_save_catalog_item"><i class="bi bi-check-lg" aria-hidden="true"></i> <?php echo admin_e($t['save_item']); ?></button></div>
</form>
<?php endforeach; ?>
</div>
</div></details>
        <?php else: $editCustomer = (array)($context['edit_customer'] ?? []); ?>
            <section class="admin-billing-section">
<div class="admin-billing-section__head">
<h3>
<?php echo admin_e($t['customer_directory']); ?>
</h3>
<p>
<?php echo admin_e($t['customer_directory_intro']); ?>
</p>
</div>
<details class="admin-billing-disclosure"<?php echo !empty($editCustomer['id']) || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ' open' : ''; ?>>
<summary><span><i class="bi bi-person-plus" aria-hidden="true"></i> <?php echo admin_e($locale === 'pl' ? (!empty($editCustomer['id']) ? 'Edytuj klienta' : 'Dodaj nowego klienta') : (!empty($editCustomer['id']) ? 'Edit customer' : 'Add a customer')); ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
<div class="admin-billing-disclosure__body"><form method="post" class="row g-3" data-billing-customer-form>
<input type="hidden" name="_csrf" value="<?php echo admin_e($csrfToken); ?>">
<input type="hidden" name="billing_customer_record_id" value="<?php echo (int)($editCustomer['id'] ?? 0); ?>">
<div class="col-md-6 admin-billing-buyer-lookup" data-billing-lookup>
<label class="form-label">
<?php echo admin_e($t['site_user']); ?>
</label>
<div class="admin-billing-buyer-lookup__input"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" type="search" placeholder="<?php echo admin_e($t['search_user']); ?>" data-billing-directory-user-search autocomplete="off"><div class="admin-billing-buyer-lookup__results" data-billing-lookup-results hidden></div></div>
<select class="form-select" name="linked_customer_id" data-billing-directory-user-select>
<option value="0">
<?php echo admin_e($t['none']); ?>
</option>
<?php foreach ((array)($context['site_customers'] ?? []) as $row): ?>
<option value="<?php echo (int)$row['id']; ?>"<?php echo (int)($editCustomer['customer_id'] ?? 0) === (int)$row['id'] ? ' selected' : ''; ?>>#<?php echo (int)$row['id']; ?> · @<?php echo admin_e($row['public_handle'] ?? ''); ?> · <?php echo admin_e($row['email']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['buyer_name']); ?>
</label>
<input class="form-control" name="billing_customer_display_name" value="<?php echo admin_e($editCustomer['display_name'] ?? ''); ?>" required>
</div>
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['company_name']); ?>
</label>
<input class="form-control" name="billing_customer_company_name" value="<?php echo admin_e($editCustomer['company_name'] ?? ''); ?>">
</div>
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['email']); ?>
</label>
<input class="form-control" type="email" name="billing_customer_email" value="<?php echo admin_e($editCustomer['email'] ?? ''); ?>">
</div>
<div class="col-md-4">
<label class="form-label">
<?php echo admin_e($t['phone']); ?>
</label>
<input class="form-control" name="billing_customer_phone" value="<?php echo admin_e($editCustomer['phone'] ?? ''); ?>">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['address']); ?>
</label>
<input class="form-control" name="billing_customer_address_line1" value="<?php echo admin_e($editCustomer['address_line1'] ?? ''); ?>">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['address2']); ?>
</label>
<input class="form-control" name="billing_customer_address_line2" value="<?php echo admin_e($editCustomer['address_line2'] ?? ''); ?>">
</div>
<div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['postal']); ?>
</label>
<input class="form-control" name="billing_customer_postal_code" value="<?php echo admin_e($editCustomer['postal_code'] ?? ''); ?>">
</div>
<div class="col-md-3">
<label class="form-label">
<?php echo admin_e($t['city']); ?>
</label>
<input class="form-control" name="billing_customer_city" value="<?php echo admin_e($editCustomer['city'] ?? ''); ?>">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['country']); ?>
</label>
<select class="form-select" name="billing_customer_country_code" data-billing-customer-country>
<option value=""><?php echo admin_e($t['none']); ?></option>
<?php $editCountryCode = strtoupper((string)($editCustomer['country_code'] ?? '')); ?>
<?php if ($editCountryCode !== '' && !isset($customerCountries[$editCountryCode])): ?>
<option value="<?php echo admin_e($editCountryCode); ?>" selected><?php echo admin_e($editCustomer['country_name'] ?? $editCountryCode); ?></option>
<?php endif; ?>
<?php foreach ($customerCountries as $countryCode => $countryData): ?>
<option value="<?php echo admin_e($countryCode); ?>" data-country-name="<?php echo admin_e($countryData['name']); ?>"<?php echo $editCountryCode === $countryCode ? ' selected' : ''; ?>><?php echo admin_e($countryData['name'] . ' (' . $countryCode . ')'); ?></option>
<?php endforeach; ?>
</select>
<input type="hidden" name="billing_customer_country_name" value="<?php echo admin_e($editCustomer['country_name'] ?? ''); ?>" data-billing-customer-country-name>
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['tax_id']); ?>
</label>
<input class="form-control" name="billing_customer_tax_id" value="<?php echo admin_e($editCustomer['tax_id'] ?? ''); ?>">
</div>
<div class="col-md-6">
<label class="form-label">
<?php echo admin_e($t['notes']); ?>
</label>
<input class="form-control" name="billing_customer_notes" value="<?php echo admin_e($editCustomer['notes'] ?? ''); ?>">
</div>
<div class="col-12">
    <label class="admin-billing-electronic-consent">
        <input class="form-check-input" type="checkbox" name="electronic_delivery_accepted" value="1"<?php echo !empty($editCustomer['electronic_delivery_accepted_at']) && strtolower((string)($editCustomer['electronic_delivery_email'] ?? '')) === strtolower((string)($editCustomer['email'] ?? '')) ? ' checked' : ''; ?>>
        <span><strong><?php echo admin_e($locale === 'pl' ? 'Klient zgodził się na elektroniczne doręczanie dokumentów' : 'Customer agreed to electronic document delivery'); ?></strong><small><?php echo admin_e($locale === 'pl' ? 'Zaznacz po uzgodnieniu wysyłki PDF na podany adres email. Zmiana adresu wymaga ponownego potwierdzenia.' : 'Select after agreeing PDF delivery to this email address. Changing the address requires renewed confirmation.'); ?></small></span>
    </label>
</div>
<div class="col-12">
<button class="btn btn-success" name="admin_billing_save_customer">
<?php echo admin_e($t['save_billing_customer']); ?>
</button>
</div>
</form></div></details>
<div class="admin-billing-customer-search">
<i class="bi bi-search" aria-hidden="true">
</i>
<input class="form-control" type="search" placeholder="<?php echo admin_e($t['search_customer']); ?>" data-billing-list-search="customers">
</div>
<div class="table-responsive">
<table class="table admin-billing-table">
<thead>
<tr>
<th>
<?php echo admin_e($t['customer']); ?>
</th>
<th>
<?php echo admin_e($t['country']); ?>
</th>
<th>
<?php echo admin_e($t['tax_id']); ?>
</th>
<th>
<?php echo admin_e($t['linked']); ?>
</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach ((array)($context['billing_customers'] ?? []) as $row): $rowCountryCode = strtoupper((string)($row['country_code'] ?? '')); $rowCountry = admin_billing_country_presentation($rowCountryCode, $locale, (string)($row['country_name'] ?? '')); ?>
<tr data-billing-filter-row="customers">
<td>
<strong>
<?php echo admin_e($row['display_name']); ?>
</strong>
<small>
<?php echo admin_e($row['email'] ?? ''); ?>
</small>
</td>
<td>
<?php if ($rowCountryCode !== ''): ?>
<span class="admin-billing-country-badge admin-billing-country-badge--customer"><img src="<?php echo admin_e($rowCountry['flag_url']); ?>" alt="" aria-hidden="true"><?php echo admin_e($rowCountry['name']); ?></span>
<?php else: ?>—<?php endif; ?>
</td>
<td>
<?php echo admin_e($row['tax_id'] ?? '—'); ?>
</td>
<td>
<?php if (!empty($row['customer_id'])): ?>
<a class="admin-billing-customer-link" href="/admin/?page=users&amp;customer_id=<?php echo (int)$row['customer_id']; ?>">
<strong>#<?php echo (int)$row['customer_id']; ?>
</strong>
<span>@<?php echo admin_e($row['linked_handle'] ?? ''); ?>
</span>
</a>
<?php else: ?>
<span class="admin-billing-link-missing">
<?php echo admin_e($t['none']); ?>
</span>
<?php endif; ?>
</td>
<td>
<a class="btn btn-outline-dark btn-sm" href="/admin/?page=billing&amp;billing_tab=customers&amp;edit_billing_customer=<?php echo (int)$row['id']; ?>">
<i class="bi bi-pencil" aria-hidden="true">
</i>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="admin-empty-state" data-billing-filter-empty="customers" hidden>
<?php echo admin_e($t['empty']); ?>
</div>
</section>
        <?php endif; ?>
    </section>
    <?php
}
