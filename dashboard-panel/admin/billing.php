<?php

declare(strict_types=1);

function admin_billing_enabled(array $settings): bool
{
    return admin_setting_is_enabled($settings, 'billing_enabled', false);
}

function admin_billing_auto_issue_email_enabled(array $settings): bool
{
    return admin_billing_enabled($settings)
        && admin_setting_is_enabled($settings, 'billing_auto_issue_email_enabled', false);
}

function admin_billing_supported_countries(): array
{
    return [
        'IE' => ['name' => 'Ireland', 'currency' => 'EUR', 'language' => 'en', 'number_prefix' => 'INV-{YYYY}-'],
        'AT' => ['name' => 'Austria', 'currency' => 'EUR', 'language' => 'de', 'number_prefix' => 'RE-{YYYY}-'],
        'PL' => ['name' => 'Polska', 'currency' => 'PLN', 'language' => 'pl', 'number_prefix' => 'R/{YYYY}/'],
    ];
}

function admin_billing_country_language(string $countryCode): string
{
    $country = admin_billing_supported_countries()[strtoupper(trim($countryCode))] ?? null;
    return is_array($country) ? (string)($country['language'] ?? 'en') : 'en';
}

function admin_billing_customer_countries(string $locale = 'pl'): array
{
    $locale = in_array($locale, ['pl', 'en', 'de'], true) ? $locale : 'en';
    $rows = [
        'PL' => ['Polska', 'Poland', 'Polen', '#dc2626'], 'AT' => ['Austria', 'Austria', 'Österreich', '#b91c1c'],
        'DE' => ['Niemcy', 'Germany', 'Deutschland', '#111827'], 'IE' => ['Irlandia', 'Ireland', 'Irland', '#15803d'],
        'GB' => ['Wielka Brytania', 'United Kingdom', 'Vereinigtes Königreich', '#1d4ed8'], 'UA' => ['Ukraina', 'Ukraine', 'Ukraine', '#2563eb'],
        'US' => ['Stany Zjednoczone', 'United States', 'Vereinigte Staaten', '#7c3aed'], 'RU' => ['Rosja', 'Russia', 'Russland', '#64748b'],
        'CZ' => ['Czechy', 'Czechia', 'Tschechien', '#0369a1'], 'SK' => ['Słowacja', 'Slovakia', 'Slowakei', '#0f766e'],
        'HU' => ['Węgry', 'Hungary', 'Ungarn', '#be123c'], 'FR' => ['Francja', 'France', 'Frankreich', '#1e40af'],
        'ES' => ['Hiszpania', 'Spain', 'Spanien', '#c2410c'], 'PT' => ['Portugalia', 'Portugal', 'Portugal', '#047857'],
        'IT' => ['Włochy', 'Italy', 'Italien', '#166534'], 'NL' => ['Holandia', 'Netherlands', 'Niederlande', '#ea580c'],
        'BE' => ['Belgia', 'Belgium', 'Belgien', '#ca8a04'], 'LU' => ['Luksemburg', 'Luxembourg', 'Luxemburg', '#0891b2'],
        'DK' => ['Dania', 'Denmark', 'Dänemark', '#be123c'], 'SE' => ['Szwecja', 'Sweden', 'Schweden', '#1d4ed8'],
        'NO' => ['Norwegia', 'Norway', 'Norwegen', '#b91c1c'], 'FI' => ['Finlandia', 'Finland', 'Finnland', '#2563eb'],
        'IS' => ['Islandia', 'Iceland', 'Island', '#0e7490'], 'CH' => ['Szwajcaria', 'Switzerland', 'Schweiz', '#dc2626'],
        'LI' => ['Liechtenstein', 'Liechtenstein', 'Liechtenstein', '#1e3a8a'], 'EE' => ['Estonia', 'Estonia', 'Estland', '#0f172a'],
        'LV' => ['Łotwa', 'Latvia', 'Lettland', '#991b1b'], 'LT' => ['Litwa', 'Lithuania', 'Litauen', '#a16207'],
        'GR' => ['Grecja', 'Greece', 'Griechenland', '#1d4ed8'], 'CY' => ['Cypr', 'Cyprus', 'Zypern', '#b45309'],
        'MT' => ['Malta', 'Malta', 'Malta', '#dc2626'], 'RO' => ['Rumunia', 'Romania', 'Rumänien', '#2563eb'],
        'BG' => ['Bułgaria', 'Bulgaria', 'Bulgarien', '#15803d'], 'HR' => ['Chorwacja', 'Croatia', 'Kroatien', '#be123c'],
        'SI' => ['Słowenia', 'Slovenia', 'Slowenien', '#0369a1'], 'AL' => ['Albania', 'Albania', 'Albanien', '#b91c1c'],
        'BA' => ['Bośnia i Hercegowina', 'Bosnia and Herzegovina', 'Bosnien und Herzegowina', '#1d4ed8'],
        'RS' => ['Serbia', 'Serbia', 'Serbien', '#7c3aed'], 'ME' => ['Czarnogóra', 'Montenegro', 'Montenegro', '#991b1b'],
        'MK' => ['Macedonia Północna', 'North Macedonia', 'Nordmazedonien', '#b45309'],
        'MD' => ['Mołdawia', 'Moldova', 'Moldau', '#1e40af'], 'CA' => ['Kanada', 'Canada', 'Kanada', '#dc2626'],
        'AU' => ['Australia', 'Australia', 'Australien', '#1d4ed8'],
    ];
    $nameIndex = ['pl' => 0, 'en' => 1, 'de' => 2][$locale];
    $countries = [];
    foreach ($rows as $code => $row) {
        $countries[$code] = ['name' => $row[$nameIndex], 'color' => $row[3]];
    }
    return $countries;
}

function admin_billing_country_presentation(string $countryCode, string $locale = 'pl', string $fallbackName = ''): array
{
    $countryCode = strtoupper(trim($countryCode));
    $country = admin_billing_customer_countries($locale)[$countryCode] ?? null;
    $name = is_array($country) ? trim((string)($country['name'] ?? '')) : '';
    if ($name === '') {
        $name = trim($fallbackName) !== '' ? trim($fallbackName) : $countryCode;
    }
    return [
        'code' => $countryCode,
        'name' => $name,
        'color' => is_array($country) ? (string)($country['color'] ?? '#374151') : '#374151',
        'flag_url' => '/img/flags/' . (preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : 'noflag') . '.gif',
    ];
}

function admin_billing_eu_country_codes(): array
{
    return [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU',
        'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];
}

function admin_billing_eu_standard_tax_rates(): array
{
    return [
        'AT' => 20.0, 'BE' => 21.0, 'BG' => 20.0, 'HR' => 25.0, 'CY' => 19.0,
        'CZ' => 21.0, 'DK' => 25.0, 'EE' => 24.0, 'FI' => 25.5, 'FR' => 20.0,
        'DE' => 19.0, 'GR' => 24.0, 'HU' => 27.0, 'IE' => 23.0, 'IT' => 22.0,
        'LV' => 21.0, 'LT' => 21.0, 'LU' => 17.0, 'MT' => 18.0, 'NL' => 21.0,
        'PL' => 23.0, 'PT' => 23.0, 'RO' => 21.0, 'SK' => 23.0, 'SI' => 22.0,
        'ES' => 21.0, 'SE' => 25.0,
    ];
}

function admin_billing_transaction_region(string $sellerCountryCode, string $destinationCountryCode): string
{
    $sellerCountryCode = strtoupper(trim($sellerCountryCode));
    $destinationCountryCode = strtoupper(trim($destinationCountryCode));
    if ($destinationCountryCode === '' || $destinationCountryCode === $sellerCountryCode) {
        return 'domestic';
    }
    if ($destinationCountryCode === 'GB') {
        return 'uk';
    }
    if (in_array($sellerCountryCode, admin_billing_eu_country_codes(), true)
        && in_array($destinationCountryCode, admin_billing_eu_country_codes(), true)) {
        return 'eu';
    }
    return 'outside_eu';
}

function admin_billing_default_supply_type(string $businessActivity): string
{
    return [
        'digital_services' => 'digital_service',
        'professional_services' => 'consulting_service',
        'education' => 'education_service',
        'installation_services' => 'construction_service',
        'construction' => 'construction_service',
        'cleaning_services' => 'other_service',
        'repair_maintenance' => 'repair_service',
        'healthcare' => 'healthcare_service',
        'psychology' => 'psychological_service',
        'fitness_wellness' => 'consulting_service',
        'retail_goods' => 'goods',
        'second_hand_goods' => 'used_goods',
    ][$businessActivity] ?? 'other_service';
}

function admin_billing_profile_capability_rules(): array
{
    $rules = [];
    foreach (array_keys(admin_billing_supported_countries()) as $countryCode) {
        $rules[$countryCode] = [
            'oss' => [
                'vat_statuses' => ['vat_registered'],
                'business_activities' => ['digital_services', 'retail_goods', 'second_hand_goods', 'general'],
            ],
            'medical' => [
                'vat_statuses' => ['vat_registered', 'small_business_exempt', 'not_vat_registered'],
                'business_activities' => ['healthcare', 'psychology'],
            ],
            'margin' => [
                'vat_statuses' => ['vat_registered'],
                'business_activities' => ['second_hand_goods'],
            ],
        ];
    }
    return $rules;
}

function admin_billing_profile_capabilities(string $countryCode, string $businessActivity, string $vatStatus): array
{
    $countryCode = strtoupper(trim($countryCode));
    $businessActivity = strtolower(trim($businessActivity));
    $vatStatus = strtolower(trim($vatStatus));
    $countryRules = admin_billing_profile_capability_rules()[$countryCode] ?? [];
    $capabilities = ['oss' => false, 'medical' => false, 'margin' => false];

    foreach ($capabilities as $capability => $_available) {
        $rule = $countryRules[$capability] ?? null;
        $capabilities[$capability] = is_array($rule)
            && in_array($vatStatus, (array)($rule['vat_statuses'] ?? []), true)
            && in_array($businessActivity, (array)($rule['business_activities'] ?? []), true);
    }
    return $capabilities;
}

function admin_billing_supported_languages(): array
{
    return ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch'];
}

function admin_billing_units(string $language): array
{
    $units = [
        'pl' => ['piece' => 'szt.', 'service' => 'usł.', 'hour' => 'godz.', 'day' => 'dzień', 'month' => 'mies.', 'session' => 'sesja', 'package' => 'pakiet', 'kilogram' => 'kg', 'kilometre' => 'km'],
        'en' => ['piece' => 'pcs', 'service' => 'service', 'hour' => 'hour', 'day' => 'day', 'month' => 'month', 'session' => 'session', 'package' => 'package', 'kilogram' => 'kg', 'kilometre' => 'km'],
        'de' => ['piece' => 'Stk.', 'service' => 'Leistung', 'hour' => 'Std.', 'day' => 'Tag', 'month' => 'Monat', 'session' => 'Sitzung', 'package' => 'Paket', 'kilogram' => 'kg', 'kilometre' => 'km'],
    ];
    return $units[$language] ?? $units['en'];
}

function admin_billing_unit_key(string $label): string
{
    $needle = mb_strtolower(trim($label));
    foreach (array_keys(admin_billing_supported_languages()) as $language) {
        foreach (admin_billing_units($language) as $key => $translatedLabel) {
            if (mb_strtolower($translatedLabel) === $needle) {
                return $key;
            }
        }
    }
    return '';
}

function admin_billing_supported_currencies(): array
{
    return ['EUR' => 'EUR', 'PLN' => 'PLN', 'GBP' => 'GBP', 'USD' => 'USD'];
}

function admin_billing_business_activities(string $locale = 'pl'): array
{
    $pl = [
        'digital_services' => 'Subskrypcje, dostęp premium i usługi cyfrowe',
        'professional_services' => 'Konsultacje, trenerzy i usługi profesjonalne',
        'education' => 'Szkolenia, kursy i edukacja',
        'installation_services' => 'Instalacje, elektryka i usługi techniczne',
        'construction' => 'Budownictwo i usługi związane z nieruchomościami',
        'cleaning_services' => 'Sprzątanie i obsługa obiektów',
        'repair_maintenance' => 'Naprawy, serwis i konserwacja',
        'healthcare' => 'Usługi medyczne i ochrona zdrowia',
        'psychology' => 'Psychologia, psychoterapia i terapia',
        'fitness_wellness' => 'Trening personalny, fitness i wellness',
        'retail_goods' => 'Handel i sprzedaż towarów',
        'second_hand_goods' => 'Handel towarami używanymi',
        'general' => 'Działalność ogólna / mieszana',
    ];
    if ($locale === 'pl') {
        return $pl;
    }
    return [
        'digital_services' => 'Subscriptions, premium access and digital services',
        'professional_services' => 'Consulting, coaching and professional services',
        'education' => 'Training, courses and education',
        'installation_services' => 'Installation, electrical and technical services',
        'construction' => 'Construction and property-related services',
        'cleaning_services' => 'Cleaning and facility services',
        'repair_maintenance' => 'Repairs, servicing and maintenance',
        'healthcare' => 'Medical and healthcare services',
        'psychology' => 'Psychology, psychotherapy and therapy',
        'fitness_wellness' => 'Personal training, fitness and wellness',
        'retail_goods' => 'Trade and sale of goods',
        'second_hand_goods' => 'Second-hand goods trading',
        'general' => 'General / mixed activity',
    ];
}

function admin_billing_vat_statuses(string $locale = 'pl'): array
{
    $pl = [
        'vat_registered' => 'Czynny / zarejestrowany podatnik VAT',
        'small_business_exempt' => 'Zwolnienie dla małego przedsiębiorcy',
        'not_vat_registered' => 'Niezarejestrowany do VAT',
        'manual' => 'Ustalane indywidualnie z księgowością',
    ];
    if ($locale === 'pl') {
        return $pl;
    }
    return [
        'vat_registered' => 'VAT registered',
        'small_business_exempt' => 'Small-business VAT exemption',
        'not_vat_registered' => 'Not VAT registered',
        'manual' => 'Determined individually with accounting',
    ];
}

function admin_billing_supply_types(string $locale = 'pl'): array
{
    $pl = [
        'digital_service' => 'Subskrypcje, konta premium i dostęp cyfrowy',
        'software_service' => 'Oprogramowanie, licencje i usługi SaaS',
        'consulting_service' => 'Konsultacje, sesje i usługi profesjonalne',
        'education_service' => 'Szkolenia, kursy i edukacja',
        'creative_service' => 'Marketing i usługi kreatywne',
        'it_support_service' => 'IT i wsparcie techniczne',
        'repair_service' => 'Serwis, naprawy i konserwacja',
        'rental_service' => 'Najem, wynajem i dzierżawa',
        'transport_service' => 'Transport, dostawa i logistyka',
        'hospitality_service' => 'Noclegi i zakwaterowanie',
        'catering_service' => 'Gastronomia i catering',
        'financial_service' => 'Finanse i ubezpieczenia',
        'construction_service' => 'Budownictwo i nieruchomości',
        'healthcare_service' => 'Usługi medyczne i zdrowotne',
        'psychological_service' => 'Sesje terapeutyczne i psychologiczne',
        'other_service' => 'Pozostałe usługi',
        'goods' => 'Towary i sprzedaż detaliczna',
        'manufactured_goods' => 'Produkty własne i rękodzieło',
        'automotive_goods' => 'Motoryzacja i części',
        'used_goods' => 'Towary używane',
    ];
    if ($locale === 'pl') {
        return $pl;
    }
    return [
        'digital_service' => 'Subscriptions, premium accounts and digital access',
        'software_service' => 'Software, licences and SaaS services',
        'consulting_service' => 'Consultations, sessions and professional services',
        'education_service' => 'Training, courses and education',
        'creative_service' => 'Marketing and creative services',
        'it_support_service' => 'IT and technical support',
        'repair_service' => 'Servicing, repairs and maintenance',
        'rental_service' => 'Rental, hire and lease',
        'transport_service' => 'Transport, delivery and logistics',
        'hospitality_service' => 'Accommodation and hospitality',
        'catering_service' => 'Food service and catering',
        'financial_service' => 'Finance and insurance',
        'construction_service' => 'Construction and real estate',
        'healthcare_service' => 'Medical and healthcare services',
        'psychological_service' => 'Therapy and psychology sessions',
        'other_service' => 'Other services',
        'goods' => 'Goods and retail sales',
        'manufactured_goods' => 'Own products and handicrafts',
        'automotive_goods' => 'Automotive goods and parts',
        'used_goods' => 'Second-hand goods',
    ];
}

function admin_billing_supply_type_groups(): array
{
    return [
        'digital_professional' => [
            'digital_service', 'software_service', 'consulting_service', 'education_service',
            'creative_service', 'it_support_service',
        ],
        'specialized_services' => [
            'repair_service', 'rental_service',
            'transport_service', 'hospitality_service', 'catering_service', 'financial_service',
            'construction_service', 'healthcare_service', 'psychological_service', 'other_service',
        ],
        'goods' => ['goods', 'manufactured_goods', 'automotive_goods', 'used_goods'],
    ];
}

function admin_billing_goods_supply_types(): array
{
    return admin_billing_supply_type_groups()['goods'];
}

function admin_billing_digital_supply_types(): array
{
    return ['digital_service', 'software_service'];
}

function admin_billing_tax_treatments(string $locale = 'pl'): array
{
    $pl = [
        'domestic_vat' => 'Sprzedaż krajowa - VAT sprzedawcy',
        'small_business_exempt' => 'Sprzedaż krajowa - zwolnienie podmiotowe / bez VAT',
        'eu_b2c_destination_oss' => 'UE B2C - VAT kraju konsumenta / OSS',
        'eu_b2b_reverse_charge' => 'UE B2B - reverse charge',
        'construction_reverse_charge' => 'Usługi budowlane - reverse charge',
        'eu_distance_goods_oss' => 'UE B2C - sprzedaż wysyłkowa / OSS',
        'medical_exempt_entity' => 'Opieka medyczna - zwolnienie podmiotu leczniczego',
        'medical_exempt_professional' => 'Opieka medyczna - zwolnienie uprawnionego specjalisty',
        'margin_scheme_second_hand' => 'Procedura marży - towary używane',
        'export_outside_eu' => 'Poza UE / eksport - ustalenie stawki',
        'uk_manual' => 'Wielka Brytania - ręczna weryfikacja VAT',
        'manual' => 'Ustalenie ręczne z księgową',
    ];
    if ($locale === 'pl') {
        return $pl;
    }
    return [
        'domestic_vat' => 'Domestic sale - issuer VAT',
        'small_business_exempt' => 'Domestic sale - small-business exemption / no VAT',
        'eu_b2c_destination_oss' => 'EU B2C - consumer-country VAT / OSS',
        'eu_b2b_reverse_charge' => 'EU B2B - reverse charge',
        'construction_reverse_charge' => 'Construction services - reverse charge',
        'eu_distance_goods_oss' => 'EU B2C - distance sale of goods / OSS',
        'medical_exempt_entity' => 'Healthcare - qualifying entity exemption',
        'medical_exempt_professional' => 'Healthcare - qualifying professional exemption',
        'margin_scheme_second_hand' => 'Margin scheme - second-hand goods',
        'export_outside_eu' => 'Outside EU / export - determine rate',
        'uk_manual' => 'United Kingdom - manual VAT review',
        'manual' => 'Manual accounting decision',
    ];
}

function admin_billing_default_tax_note(string $treatment, string $language, string $countryCode = ''): string
{
    $language = isset(admin_billing_supported_languages()[$language]) ? $language : 'en';
    $countryCode = strtoupper(trim($countryCode));
    $notes = [
        'pl' => [
            'domestic_vat' => '',
            'small_business_exempt' => $countryCode === 'PL'
                ? 'Sprzedaż zwolniona z VAT na podstawie art. 113 ust. 1 albo 9 ustawy o VAT.'
                : 'Sprzedaż zwolniona z VAT zgodnie ze statusem małego przedsiębiorcy.',
            'eu_b2c_destination_oss' => 'VAT rozliczono w procedurze unijnej OSS.',
            'eu_b2b_reverse_charge' => 'Odwrotne obciążenie.',
            'construction_reverse_charge' => 'Odwrotne obciążenie.',
            'eu_distance_goods_oss' => 'VAT rozliczono w procedurze unijnej OSS.',
            'margin_scheme_second_hand' => 'Procedura marży - towary używane.',
            'export_outside_eu' => 'Stawka VAT 0% - eksport towarów poza Unię Europejską.',
            'uk_manual' => '',
            'manual' => '',
        ],
        'en' => [
            'domestic_vat' => '',
            'small_business_exempt' => $countryCode === 'IE'
                ? 'VAT not charged under the issuer small-business VAT status.'
                : 'VAT exempt under the issuer small-business VAT status.',
            'eu_b2c_destination_oss' => 'VAT accounted for under the Union OSS scheme.',
            'eu_b2b_reverse_charge' => 'Reverse charge.',
            'construction_reverse_charge' => 'Reverse charge.',
            'eu_distance_goods_oss' => 'VAT accounted for under the Union OSS scheme.',
            'margin_scheme_second_hand' => 'Margin Scheme - Second-Hand Goods.',
            'export_outside_eu' => 'VAT 0% - export of goods outside the European Union.',
            'uk_manual' => '',
            'manual' => '',
        ],
        'de' => [
            'domestic_vat' => '',
            'small_business_exempt' => $countryCode === 'AT'
                ? 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung.'
                : 'Umsatzsteuerfrei aufgrund des Kleinunternehmerstatus des Ausstellers.',
            'eu_b2c_destination_oss' => 'Umsatzsteuer im EU-OSS-Verfahren abgerechnet.',
            'eu_b2b_reverse_charge' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).',
            'construction_reverse_charge' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).',
            'eu_distance_goods_oss' => 'Umsatzsteuer im EU-OSS-Verfahren abgerechnet.',
            'margin_scheme_second_hand' => 'Differenzbesteuerung - Gebrauchtgegenstände.',
            'export_outside_eu' => 'Umsatzsteuer 0% - Ausfuhrlieferung in ein Drittland.',
            'uk_manual' => '',
            'manual' => '',
        ],
    ];

    if (in_array($treatment, ['medical_exempt_entity', 'medical_exempt_professional'], true)) {
        $medicalNotes = [
            'PL' => [
                'pl' => [
                    'medical_exempt_entity' => 'Zwolnienie od podatku VAT na podstawie art. 43 ust. 1 pkt 18 ustawy o VAT.',
                    'medical_exempt_professional' => 'Zwolnienie od podatku VAT na podstawie art. 43 ust. 1 pkt 19 ustawy o VAT.',
                ],
                'en' => [
                    'medical_exempt_entity' => 'VAT exempt under Article 43(1)(18) of the Polish VAT Act.',
                    'medical_exempt_professional' => 'VAT exempt under Article 43(1)(19) of the Polish VAT Act.',
                ],
                'de' => [
                    'medical_exempt_entity' => 'Umsatzsteuerbefreit gemäß Art. 43 Abs. 1 Nr. 18 des polnischen UStG.',
                    'medical_exempt_professional' => 'Umsatzsteuerbefreit gemäß Art. 43 Abs. 1 Nr. 19 des polnischen UStG.',
                ],
            ],
            'AT' => [
                'pl' => ['medical_exempt_entity' => 'Zwolnienie z VAT zgodnie z § 6 ust. 1 pkt 19 austriackiej ustawy UStG.', 'medical_exempt_professional' => 'Zwolnienie z VAT zgodnie z § 6 ust. 1 pkt 19 austriackiej ustawy UStG.'],
                'en' => ['medical_exempt_entity' => 'VAT exempt under Section 6(1)(19) of the Austrian UStG.', 'medical_exempt_professional' => 'VAT exempt under Section 6(1)(19) of the Austrian UStG.'],
                'de' => ['medical_exempt_entity' => 'Umsatzsteuerfrei gemäß § 6 Abs. 1 Z 19 UStG.', 'medical_exempt_professional' => 'Umsatzsteuerfrei gemäß § 6 Abs. 1 Z 19 UStG.'],
            ],
            'IE' => [
                'pl' => ['medical_exempt_entity' => 'Zwolnienie z VAT zgodnie z pkt 2(3), załącznik 1 do irlandzkiej ustawy Value-Added Tax Consolidation Act 2010.', 'medical_exempt_professional' => 'Zwolnienie z VAT zgodnie z pkt 2(3), załącznik 1 do irlandzkiej ustawy Value-Added Tax Consolidation Act 2010.'],
                'en' => ['medical_exempt_entity' => 'VAT exempt under paragraph 2(3), Schedule 1, Value-Added Tax Consolidation Act 2010.', 'medical_exempt_professional' => 'VAT exempt under paragraph 2(3), Schedule 1, Value-Added Tax Consolidation Act 2010.'],
                'de' => ['medical_exempt_entity' => 'Umsatzsteuerbefreit gemäß Absatz 2(3), Anhang 1, irisches Value-Added Tax Consolidation Act 2010.', 'medical_exempt_professional' => 'Umsatzsteuerbefreit gemäß Absatz 2(3), Anhang 1, irisches Value-Added Tax Consolidation Act 2010.'],
            ],
        ];
        if (isset($medicalNotes[$countryCode][$language][$treatment])) {
            return $medicalNotes[$countryCode][$language][$treatment];
        }
    }

    return (string)($notes[$language][$treatment] ?? $notes[$language]['manual']);
}

function admin_billing_default_payment_note(string $sourceType, string $language): string
{
    $language = isset(admin_billing_supported_languages()[$language]) ? $language : 'en';
    $notes = [
        'pl' => ['payment_crypto' => 'Opłacono kryptowalutą.', 'payment_bank' => 'Opłacono przelewem bankowym.', 'order' => 'Opłacono w serwisie.'],
        'en' => ['payment_crypto' => 'Paid in cryptocurrency.', 'payment_bank' => 'Paid by bank transfer.', 'order' => 'Paid through the service.'],
        'de' => ['payment_crypto' => 'Mit Kryptowährung bezahlt.', 'payment_bank' => 'Per Banküberweisung bezahlt.', 'order' => 'Über den Dienst bezahlt.'],
    ];
    return (string)($notes[$language][$sourceType] ?? '');
}

function admin_billing_ensure_runtime_schema(Mysql_ks $db): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    if (schema_object_exists($db, 'app_settings') && !schema_column_exists($db, 'app_settings', 'billing_enabled')) {
        $db->query("ALTER TABLE app_settings ADD COLUMN billing_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER bank_account_shared_assignments_enabled");
        schema_forget_column_cache('app_settings', 'billing_enabled');
    }
    if (schema_object_exists($db, 'app_settings') && !schema_column_exists($db, 'app_settings', 'billing_auto_issue_email_enabled')) {
        $db->query("ALTER TABLE app_settings ADD COLUMN billing_auto_issue_email_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_enabled");
        schema_forget_column_cache('app_settings', 'billing_auto_issue_email_enabled');
    }
    if (schema_object_exists($db, 'app_settings') && !schema_column_exists($db, 'app_settings', 'billing_auto_company_profile_id')) {
        $db->query("ALTER TABLE app_settings ADD COLUMN billing_auto_company_profile_id INT UNSIGNED DEFAULT NULL AFTER billing_auto_issue_email_enabled");
        schema_forget_column_cache('app_settings', 'billing_auto_company_profile_id');
    }
    if (schema_object_exists($db, 'app_settings') && !schema_column_exists($db, 'app_settings', 'billing_auto_supply_type')) {
        $db->query("ALTER TABLE app_settings ADD COLUMN billing_auto_supply_type VARCHAR(30) NOT NULL DEFAULT 'digital_service' AFTER billing_auto_company_profile_id");
        schema_forget_column_cache('app_settings', 'billing_auto_supply_type');
    }
    if (schema_object_exists($db, 'crypto_deposit_requests') && !schema_column_exists($db, 'crypto_deposit_requests', 'received_fiat_actual_amount')) {
        $db->query("ALTER TABLE crypto_deposit_requests ADD COLUMN received_fiat_actual_amount DECIMAL(10,2) DEFAULT NULL AFTER received_fiat_amount");
        schema_forget_column_cache('crypto_deposit_requests', 'received_fiat_actual_amount');
    }

    $db->query("CREATE TABLE IF NOT EXISTS billing_company_profiles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        profile_label VARCHAR(120) NOT NULL DEFAULT 'Main',
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        country_code CHAR(2) NOT NULL DEFAULT 'IE',
        business_activity VARCHAR(40) NOT NULL DEFAULT 'general',
        vat_status VARCHAR(30) NOT NULL DEFAULT 'vat_registered',
        tax_guidance_note TEXT DEFAULT NULL,
        eu_oss_enabled TINYINT(1) NOT NULL DEFAULT 0,
        medical_exemption_enabled TINYINT(1) NOT NULL DEFAULT 0,
        margin_scheme_enabled TINYINT(1) NOT NULL DEFAULT 0,
        company_name VARCHAR(191) NOT NULL DEFAULT '',
        legal_name VARCHAR(191) DEFAULT NULL,
        address_line1 VARCHAR(191) NOT NULL DEFAULT '',
        address_line2 VARCHAR(191) DEFAULT NULL,
        postal_code VARCHAR(30) NOT NULL DEFAULT '',
        city VARCHAR(120) NOT NULL DEFAULT '',
        country_name VARCHAR(120) NOT NULL DEFAULT 'Ireland',
        email VARCHAR(191) DEFAULT NULL,
        phone VARCHAR(60) DEFAULT NULL,
        website VARCHAR(191) DEFAULT NULL,
        vat_id VARCHAR(80) DEFAULT NULL,
        tax_number VARCHAR(80) DEFAULT NULL,
        registration_number VARCHAR(120) DEFAULT NULL,
        bank_name VARCHAR(120) DEFAULT NULL,
        iban VARCHAR(80) DEFAULT NULL,
        bic VARCHAR(30) DEFAULT NULL,
        logo_url VARCHAR(255) DEFAULT NULL,
        default_currency_code CHAR(3) NOT NULL DEFAULT 'EUR',
        number_prefix VARCHAR(40) NOT NULL DEFAULT 'INV-{YYYY}-',
        next_number INT UNSIGNED NOT NULL DEFAULT 1,
        payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
        default_notes TEXT DEFAULT NULL,
        default_footer TEXT DEFAULT NULL,
        updated_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        CONSTRAINT fk_billing_company_profile_admin FOREIGN KEY (updated_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!schema_column_exists($db, 'billing_company_profiles', 'profile_label')) {
        $db->query("ALTER TABLE billing_company_profiles MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT, ADD COLUMN profile_label VARCHAR(120) NOT NULL DEFAULT 'Main' AFTER id, ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_label, ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_default");
        schema_forget_column_cache('billing_company_profiles', 'profile_label');
    }
    if (!schema_column_exists($db, 'billing_company_profiles', 'business_activity')) {
        $db->query("ALTER TABLE billing_company_profiles ADD COLUMN business_activity VARCHAR(40) NOT NULL DEFAULT 'general' AFTER country_code, ADD COLUMN vat_status VARCHAR(30) NOT NULL DEFAULT 'vat_registered' AFTER business_activity, ADD COLUMN tax_guidance_note TEXT NULL AFTER vat_status");
        schema_forget_column_cache('billing_company_profiles', 'business_activity');
    }
    $billingProfileGuidanceColumns = [
        'eu_oss_enabled' => "ALTER TABLE billing_company_profiles ADD COLUMN eu_oss_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER tax_guidance_note",
        'medical_exemption_enabled' => "ALTER TABLE billing_company_profiles ADD COLUMN medical_exemption_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER eu_oss_enabled",
        'margin_scheme_enabled' => "ALTER TABLE billing_company_profiles ADD COLUMN margin_scheme_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER medical_exemption_enabled",
    ];
    foreach ($billingProfileGuidanceColumns as $column => $sql) {
        if (!schema_column_exists($db, 'billing_company_profiles', $column)) {
            $db->query($sql);
            schema_forget_column_cache('billing_company_profiles', $column);
        }
    }

    $db->query("CREATE TABLE IF NOT EXISTS billing_tax_rates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        country_code CHAR(2) NOT NULL,
        rate_code VARCHAR(40) NOT NULL,
        label VARCHAR(120) NOT NULL,
        rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_billing_tax_rate_country_code (country_code, rate_code),
        KEY idx_billing_tax_rate_country_active (country_code, is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS billing_customers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id INT UNSIGNED DEFAULT NULL,
        display_name VARCHAR(191) NOT NULL,
        company_name VARCHAR(191) DEFAULT NULL,
        email VARCHAR(191) DEFAULT NULL,
        phone VARCHAR(60) DEFAULT NULL,
        address_line1 VARCHAR(191) DEFAULT NULL,
        address_line2 VARCHAR(191) DEFAULT NULL,
        postal_code VARCHAR(30) DEFAULT NULL,
        city VARCHAR(120) DEFAULT NULL,
        country_code CHAR(2) DEFAULT NULL,
        country_name VARCHAR(120) DEFAULT NULL,
        tax_id VARCHAR(80) DEFAULT NULL,
        electronic_delivery_accepted_at DATETIME DEFAULT NULL,
        electronic_delivery_email VARCHAR(191) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        updated_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_billing_customers_customer (customer_id),
        KEY idx_billing_customers_name_email (display_name, email),
        CONSTRAINT fk_billing_customer_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_customer_created_admin FOREIGN KEY (created_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_customer_updated_admin FOREIGN KEY (updated_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!schema_column_exists($db, 'billing_customers', 'electronic_delivery_accepted_at')) {
        $db->query("ALTER TABLE billing_customers ADD COLUMN electronic_delivery_accepted_at DATETIME NULL AFTER tax_id, ADD COLUMN electronic_delivery_email VARCHAR(191) NULL AFTER electronic_delivery_accepted_at");
        schema_forget_column_cache('billing_customers', 'electronic_delivery_accepted_at');
        schema_forget_column_cache('billing_customers', 'electronic_delivery_email');
    }

    $db->query("CREATE TABLE IF NOT EXISTS billing_documents (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_number VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        source_type VARCHAR(30) NOT NULL DEFAULT 'independent',
        source_unique_key VARCHAR(80) DEFAULT NULL,
        company_profile_id INT UNSIGNED DEFAULT NULL,
        billing_customer_id BIGINT UNSIGNED DEFAULT NULL,
        customer_id INT UNSIGNED DEFAULT NULL,
        order_id BIGINT UNSIGNED DEFAULT NULL,
        payment_type VARCHAR(20) DEFAULT NULL,
        payment_id BIGINT UNSIGNED DEFAULT NULL,
        country_code CHAR(2) NOT NULL,
        currency_code CHAR(3) NOT NULL,
        price_entry_mode VARCHAR(10) NOT NULL DEFAULT 'net',
        language_code VARCHAR(5) NOT NULL DEFAULT 'pl',
        supply_type VARCHAR(30) NOT NULL DEFAULT 'digital_service',
        buyer_type VARCHAR(20) NOT NULL DEFAULT 'consumer',
        destination_country_code CHAR(2) DEFAULT NULL,
        transaction_region VARCHAR(20) NOT NULL DEFAULT 'domestic',
        tax_treatment VARCHAR(40) NOT NULL DEFAULT 'domestic_vat',
        tax_treatment_note VARCHAR(500) DEFAULT NULL,
        issue_date DATE NOT NULL,
        service_date DATE DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        seller_snapshot LONGTEXT NOT NULL,
        buyer_name VARCHAR(191) NOT NULL,
        buyer_company_name VARCHAR(191) DEFAULT NULL,
        buyer_address_line1 VARCHAR(191) DEFAULT NULL,
        buyer_address_line2 VARCHAR(191) DEFAULT NULL,
        buyer_postal_code VARCHAR(30) DEFAULT NULL,
        buyer_city VARCHAR(120) DEFAULT NULL,
        buyer_country VARCHAR(120) DEFAULT NULL,
        buyer_tax_id VARCHAR(80) DEFAULT NULL,
        buyer_email VARCHAR(191) DEFAULT NULL,
        buyer_phone VARCHAR(60) DEFAULT NULL,
        subtotal_net DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes TEXT DEFAULT NULL,
        payment_notes TEXT DEFAULT NULL,
        source_amount DECIMAL(12,2) DEFAULT NULL,
        source_currency_code CHAR(3) DEFAULT NULL,
        source_paid_at DATETIME DEFAULT NULL,
        buyer_vat_id_verified_at DATETIME DEFAULT NULL,
        source_total_override_confirmed_at DATETIME DEFAULT NULL,
        external_reference VARCHAR(191) DEFAULT NULL,
        tax_decision_snapshot LONGTEXT DEFAULT NULL,
        tax_review_confirmed_at DATETIME DEFAULT NULL,
        tax_review_confirmed_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        issued_at DATETIME DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        cancelled_at DATETIME DEFAULT NULL,
        created_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        updated_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_billing_document_source (source_unique_key),
        UNIQUE KEY uniq_billing_document_profile_number (company_profile_id, document_number),
        KEY idx_billing_documents_status_issue_date (status, issue_date, id),
        KEY idx_billing_documents_profile_status_date (company_profile_id, status, issue_date),
        KEY idx_billing_documents_customer (customer_id),
        KEY idx_billing_documents_billing_customer (billing_customer_id),
        KEY idx_billing_documents_order (order_id),
        KEY idx_billing_documents_payment (payment_type, payment_id),
        CONSTRAINT fk_billing_document_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_document_company_profile FOREIGN KEY (company_profile_id) REFERENCES billing_company_profiles (id) ON DELETE RESTRICT,
        CONSTRAINT fk_billing_document_billing_customer FOREIGN KEY (billing_customer_id) REFERENCES billing_customers (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_document_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_document_tax_review_admin FOREIGN KEY (tax_review_confirmed_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_document_created_admin FOREIGN KEY (created_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_document_updated_admin FOREIGN KEY (updated_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!schema_column_exists($db, 'billing_documents', 'company_profile_id')) {
        $db->query("ALTER TABLE billing_documents ADD COLUMN company_profile_id INT UNSIGNED NULL AFTER source_type, ADD COLUMN buyer_company_name VARCHAR(191) NULL AFTER buyer_name, ADD COLUMN buyer_phone VARCHAR(60) NULL AFTER buyer_email, ADD KEY idx_billing_documents_profile_status_date (company_profile_id, status, issue_date), ADD CONSTRAINT fk_billing_document_company_profile FOREIGN KEY (company_profile_id) REFERENCES billing_company_profiles(id) ON DELETE RESTRICT");
        schema_forget_column_cache('billing_documents', 'company_profile_id');
    }
    if (!schema_column_exists($db, 'billing_documents', 'buyer_company_name')) {
        $db->query("ALTER TABLE billing_documents ADD COLUMN buyer_company_name VARCHAR(191) NULL AFTER buyer_name");
        schema_forget_column_cache('billing_documents', 'buyer_company_name');
    }
    if (!schema_column_exists($db, 'billing_documents', 'buyer_phone')) {
        $db->query("ALTER TABLE billing_documents ADD COLUMN buyer_phone VARCHAR(60) NULL AFTER buyer_email");
        schema_forget_column_cache('billing_documents', 'buyer_phone');
    }
    if (!schema_column_exists($db, 'billing_documents', 'tax_review_confirmed_at')) {
        $db->query("ALTER TABLE billing_documents ADD COLUMN tax_review_confirmed_at DATETIME NULL AFTER payment_notes, ADD COLUMN tax_review_confirmed_by_admin_user_id INT UNSIGNED NULL AFTER tax_review_confirmed_at, ADD KEY idx_billing_documents_tax_review_admin (tax_review_confirmed_by_admin_user_id), ADD CONSTRAINT fk_billing_document_tax_review_admin FOREIGN KEY (tax_review_confirmed_by_admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL");
        schema_forget_column_cache('billing_documents', 'tax_review_confirmed_at');
    }
    if (!schema_column_exists($db, 'billing_documents', 'price_entry_mode')) {
        $db->query("ALTER TABLE billing_documents ADD COLUMN price_entry_mode VARCHAR(10) NOT NULL DEFAULT 'net' AFTER currency_code");
        schema_forget_column_cache('billing_documents', 'price_entry_mode');
    }
    $billingDocumentAuditColumns = [
        'source_unique_key' => "ALTER TABLE billing_documents ADD COLUMN source_unique_key VARCHAR(80) NULL AFTER source_type",
        'source_amount' => "ALTER TABLE billing_documents ADD COLUMN source_amount DECIMAL(12,2) NULL AFTER payment_notes",
        'source_currency_code' => "ALTER TABLE billing_documents ADD COLUMN source_currency_code CHAR(3) NULL AFTER source_amount",
        'source_paid_at' => "ALTER TABLE billing_documents ADD COLUMN source_paid_at DATETIME NULL AFTER source_currency_code",
        'buyer_vat_id_verified_at' => "ALTER TABLE billing_documents ADD COLUMN buyer_vat_id_verified_at DATETIME NULL AFTER source_paid_at",
        'source_total_override_confirmed_at' => "ALTER TABLE billing_documents ADD COLUMN source_total_override_confirmed_at DATETIME NULL AFTER buyer_vat_id_verified_at",
        'external_reference' => "ALTER TABLE billing_documents ADD COLUMN external_reference VARCHAR(191) NULL AFTER source_total_override_confirmed_at",
        'tax_decision_snapshot' => "ALTER TABLE billing_documents ADD COLUMN tax_decision_snapshot LONGTEXT NULL AFTER external_reference",
    ];
    foreach ($billingDocumentAuditColumns as $column => $sql) {
        if (!schema_column_exists($db, 'billing_documents', $column)) {
            $db->query($sql);
            schema_forget_column_cache('billing_documents', $column);
        }
    }
    if (function_exists('admin_schema_index_exists') && admin_schema_index_exists($db, 'billing_documents', 'uniq_billing_document_number')) {
        $db->query('ALTER TABLE billing_documents DROP INDEX uniq_billing_document_number, ADD UNIQUE KEY uniq_billing_document_profile_number (company_profile_id, document_number)');
    }
    if (function_exists('admin_schema_index_exists') && !admin_schema_index_exists($db, 'billing_documents', 'uniq_billing_document_source')) {
        $db->query("UPDATE billing_documents d
            INNER JOIN (
                SELECT MIN(id) AS keeper_id FROM billing_documents
                WHERE status <> 'cancelled' AND source_type = 'order' AND order_id IS NOT NULL AND order_id > 0
                GROUP BY order_id
            ) keepers ON keepers.keeper_id = d.id
            SET d.source_unique_key = CONCAT('order:', d.order_id)
            WHERE d.source_unique_key IS NULL");
        $db->query("UPDATE billing_documents d
            INNER JOIN (
                SELECT MIN(id) AS keeper_id FROM billing_documents
                WHERE status <> 'cancelled' AND source_type IN ('payment_crypto','payment_bank') AND payment_id IS NOT NULL AND payment_id > 0
                GROUP BY payment_type, payment_id
            ) keepers ON keepers.keeper_id = d.id
            SET d.source_unique_key = CONCAT(d.source_type, ':', d.payment_id)
            WHERE d.source_unique_key IS NULL");
        $db->query('ALTER TABLE billing_documents ADD UNIQUE KEY uniq_billing_document_source (source_unique_key)');
    }

    $db->query("CREATE TABLE IF NOT EXISTS billing_automation_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_key VARCHAR(100) NOT NULL,
        trigger_type VARCHAR(30) NOT NULL,
        order_id BIGINT UNSIGNED DEFAULT NULL,
        payment_type VARCHAR(20) DEFAULT NULL,
        payment_id BIGINT UNSIGNED DEFAULT NULL,
        customer_id INT UNSIGNED DEFAULT NULL,
        document_id BIGINT UNSIGNED DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
        message VARCHAR(1000) DEFAULT NULL,
        last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_billing_automation_source (source_key),
        KEY idx_billing_automation_status_attempt (status, last_attempt_at),
        KEY idx_billing_automation_order (order_id),
        KEY idx_billing_automation_document (document_id),
        CONSTRAINT fk_billing_automation_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
        CONSTRAINT fk_billing_automation_document FOREIGN KEY (document_id) REFERENCES billing_documents (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS billing_document_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_id BIGINT UNSIGNED NOT NULL,
        description VARCHAR(500) NOT NULL,
        quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
        unit_label VARCHAR(30) NOT NULL DEFAULT 'szt.',
        unit_price_net DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        purchase_price_gross DECIMAL(12,4) DEFAULT NULL,
        tax_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        line_purchase_gross DECIMAL(12,2) DEFAULT NULL,
        line_margin_gross DECIMAL(12,2) DEFAULT NULL,
        line_net DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        line_tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        line_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_billing_document_items_document_sort (document_id, sort_order, id),
        CONSTRAINT fk_billing_document_item_document FOREIGN KEY (document_id) REFERENCES billing_documents (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $billingItemMarginColumns = [
        'purchase_price_gross' => "ALTER TABLE billing_document_items ADD COLUMN purchase_price_gross DECIMAL(12,4) NULL AFTER unit_price_net",
        'line_purchase_gross' => "ALTER TABLE billing_document_items ADD COLUMN line_purchase_gross DECIMAL(12,2) NULL AFTER tax_rate_percent",
        'line_margin_gross' => "ALTER TABLE billing_document_items ADD COLUMN line_margin_gross DECIMAL(12,2) NULL AFTER line_purchase_gross",
    ];
    foreach ($billingItemMarginColumns as $column => $sql) {
        if (!schema_column_exists($db, 'billing_document_items', $column)) {
            $db->query($sql);
            schema_forget_column_cache('billing_document_items', $column);
        }
    }

    $db->query("CREATE TABLE IF NOT EXISTS billing_document_deliveries (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        document_id BIGINT UNSIGNED NOT NULL,
        recipient_email VARCHAR(191) NOT NULL,
        language_code VARCHAR(5) NOT NULL DEFAULT 'en',
        delivery_channel VARCHAR(20) NOT NULL DEFAULT 'email',
        status VARCHAR(20) NOT NULL DEFAULT 'processing',
        recipient_acceptance_confirmed_at DATETIME NOT NULL,
        confirmed_by_admin_user_id INT UNSIGNED DEFAULT NULL,
        attachment_filename VARCHAR(255) DEFAULT NULL,
        attachment_sha256 CHAR(64) DEFAULT NULL,
        attachment_size_bytes INT UNSIGNED DEFAULT NULL,
        smtp_message_id VARCHAR(255) DEFAULT NULL,
        error_message VARCHAR(1000) DEFAULT NULL,
        request_ip VARCHAR(45) DEFAULT NULL,
        sent_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_billing_delivery_document_created (document_id, created_at, id),
        KEY idx_billing_delivery_status_created (status, created_at, id),
        CONSTRAINT fk_billing_delivery_document FOREIGN KEY (document_id) REFERENCES billing_documents (id) ON DELETE CASCADE,
        CONSTRAINT fk_billing_delivery_admin FOREIGN KEY (confirmed_by_admin_user_id) REFERENCES admin_users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->query("CREATE TABLE IF NOT EXISTS billing_item_catalog (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_profile_id INT UNSIGNED NOT NULL,
        description VARCHAR(500) NOT NULL,
        unit_label VARCHAR(30) NOT NULL DEFAULT 'szt.',
        default_unit_price_net DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        default_unit_price_gross DECIMAL(12,4) DEFAULT NULL,
        default_purchase_price_gross DECIMAL(12,4) DEFAULT NULL,
        default_tax_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        supply_type VARCHAR(30) NOT NULL DEFAULT 'digital_service',
        usage_count INT UNSIGNED NOT NULL DEFAULT 0,
        last_used_at DATETIME DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_billing_item_catalog_profile_description (company_profile_id, description(191)),
        KEY idx_billing_item_catalog_profile_active_usage (company_profile_id, is_active, usage_count),
        CONSTRAINT fk_billing_item_catalog_profile FOREIGN KEY (company_profile_id) REFERENCES billing_company_profiles (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $billingCatalogPriceColumns = [
        'default_unit_price_gross' => "ALTER TABLE billing_item_catalog ADD COLUMN default_unit_price_gross DECIMAL(12,4) NULL AFTER default_unit_price_net",
        'default_purchase_price_gross' => "ALTER TABLE billing_item_catalog ADD COLUMN default_purchase_price_gross DECIMAL(12,4) NULL AFTER default_unit_price_gross",
    ];
    foreach ($billingCatalogPriceColumns as $column => $sql) {
        if (!schema_column_exists($db, 'billing_item_catalog', $column)) {
            $db->query($sql);
            schema_forget_column_cache('billing_item_catalog', $column);
        }
    }

    $siteSettings = $db->select_user('SELECT site_name FROM app_settings WHERE id = 1 LIMIT 1');
    $defaultProfileLabel = trim((string)($siteSettings['site_name'] ?? '')) ?: 'Main';
    $db->query("INSERT INTO billing_company_profiles (id, profile_label, is_default, is_active, country_code, country_name, default_currency_code, number_prefix)
        VALUES (1, '" . $db->escape($defaultProfileLabel) . "', 1, 1, 'IE', 'Ireland', 'EUR', 'INV-{YYYY}-')
        ON DUPLICATE KEY UPDATE profile_label = IF(profile_label = 'Main', VALUES(profile_label), profile_label)");
    $db->query("UPDATE billing_company_profiles SET is_default = 1 WHERE id = (SELECT profile_id FROM (SELECT MIN(id) AS profile_id FROM billing_company_profiles) AS first_profile) AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM billing_company_profiles WHERE is_default = 1) AS defaults_found)");
    $db->query("UPDATE billing_documents SET company_profile_id = 1 WHERE company_profile_id IS NULL");
    $db->query("INSERT INTO billing_tax_rates (country_code, rate_code, label, rate_percent, is_default, is_active, sort_order) VALUES
        ('AT','standard','Standard VAT',20.00,1,1,10),
        ('AT','reduced_10','Reduced VAT 10%',10.00,0,1,20),
        ('AT','reduced_13','Reduced VAT 13%',13.00,0,1,30),
        ('AT','reduced_4_9','Reduced VAT 4.9%',4.90,0,1,40),
        ('AT','zero','VAT 0%',0.00,0,1,50),
        ('PL','standard','Podstawowa stawka VAT',23.00,1,1,10),
        ('PL','reduced_8','Obniżona stawka VAT 8%',8.00,0,1,20),
        ('PL','reduced_5','Obniżona stawka VAT 5%',5.00,0,1,30),
        ('PL','zero','VAT 0%',0.00,0,1,40),
        ('IE','standard','Standard VAT',23.00,1,1,10),
        ('IE','reduced_13_5','Reduced VAT 13.5%',13.50,0,1,20),
        ('IE','reduced_9','Second reduced VAT 9%',9.00,0,1,30),
        ('IE','zero','VAT 0%',0.00,0,1,40)
        ON DUPLICATE KEY UPDATE rate_code = VALUES(rate_code)");

    $standardRateValues = [];
    foreach (admin_billing_eu_standard_tax_rates() as $countryCode => $ratePercent) {
        $label = in_array($countryCode, ['PL'], true) ? 'Podstawowa stawka VAT' : 'Standard VAT';
        $standardRateValues[] = "('" . $db->escape($countryCode) . "','standard','" . $db->escape($label) . "'," . (float)$ratePercent . ",1,1,10)";
    }
    if ($standardRateValues) {
        $db->query("INSERT INTO billing_tax_rates (country_code, rate_code, label, rate_percent, is_default, is_active, sort_order) VALUES "
            . implode(',', $standardRateValues)
            . ' ON DUPLICATE KEY UPDATE rate_code = VALUES(rate_code)');
    }
}

function admin_billing_company_profiles(Mysql_ks $db, bool $activeOnly = false): array
{
    $where = $activeOnly ? ' WHERE is_active = 1' : '';
    return $db->select_full_user("SELECT * FROM billing_company_profiles{$where} ORDER BY is_default DESC, profile_label ASC, id ASC");
}

function admin_billing_company_profile(Mysql_ks $db, int $profileId = 0): array
{
    $where = $profileId > 0 ? "id = {$profileId}" : 'is_default = 1';
    $row = $db->select_user("SELECT * FROM billing_company_profiles WHERE {$where} ORDER BY id ASC LIMIT 1");
    if ((!is_array($row) || empty($row['id'])) && $profileId <= 0) {
        $row = $db->select_user('SELECT * FROM billing_company_profiles ORDER BY id ASC LIMIT 1');
    }
    return is_array($row) ? $row : [];
}

function admin_billing_item_catalog_rows(Mysql_ks $db, int $profileId = 0, string $query = '', int $limit = 100): array
{
    $where = ['is_active = 1'];
    if ($profileId > 0) {
        $where[] = 'company_profile_id = ' . $profileId;
    }
    $query = trim($query);
    if ($query !== '') {
        $escaped = $db->escape($query);
        $where[] = "description LIKE '%{$escaped}%'";
    }
    $limit = max(1, min(200, $limit));
    return $db->select_full_user('SELECT * FROM billing_item_catalog WHERE ' . implode(' AND ', $where) . " ORDER BY usage_count DESC, last_used_at DESC, description ASC LIMIT {$limit}");
}

function admin_billing_company_is_complete(array $profile): bool
{
    return trim((string)($profile['company_name'] ?? '')) !== ''
        && trim((string)($profile['address_line1'] ?? '')) !== ''
        && trim((string)($profile['postal_code'] ?? '')) !== ''
        && trim((string)($profile['city'] ?? '')) !== ''
        && isset(admin_billing_supported_countries()[strtoupper((string)($profile['country_code'] ?? ''))]);
}

function admin_billing_profile_readiness(Mysql_ks $db, array $profile): array
{
    $blockers = [];
    $warnings = [];
    if (empty($profile['id'])) {
        $blockers[] = 'Wybierz profil wystawcy.';
        return ['ready' => false, 'blockers' => $blockers, 'warnings' => $warnings];
    }
    if (empty($profile['is_active'])) {
        $blockers[] = 'Profil wystawcy jest wyłączony.';
    }
    $required = [
        'company_name' => 'nazwa firmy',
        'address_line1' => 'adres',
        'postal_code' => 'kod pocztowy',
        'city' => 'miasto',
    ];
    foreach ($required as $field => $label) {
        if (trim((string)($profile[$field] ?? '')) === '') {
            $blockers[] = 'Uzupełnij: ' . $label . '.';
        }
    }
    $countryCode = strtoupper(trim((string)($profile['country_code'] ?? '')));
    if (!isset(admin_billing_supported_countries()[$countryCode])) {
        $blockers[] = 'Wybierz obsługiwany kraj profilu: PL, AT albo IE.';
    }
    $vatStatus = (string)($profile['vat_status'] ?? 'manual');
    if ($vatStatus === 'vat_registered' && trim((string)($profile['vat_id'] ?? '')) === '') {
        $blockers[] = 'Dla czynnego podatnika VAT wpisz VAT ID / UID.';
    }
    if ($vatStatus === 'manual') {
        $warnings[] = 'Status VAT profilu jest ustawiany ręcznie. Każdy dokument wymaga uważnego wyboru wariantu.';
    }
    $activity = (string)($profile['business_activity'] ?? 'general');
    if ($vatStatus === 'vat_registered' && $activity === 'digital_services' && empty($profile['eu_oss_enabled'])) {
        $warnings[] = 'OSS jest wyłączony; sprzedaż B2C do innych krajów UE pozostanie w trybie ręcznym.';
    }
    if (in_array($activity, ['healthcare', 'psychology'], true) && empty($profile['medical_exemption_enabled'])) {
        $warnings[] = 'Zwolnienie medyczne jest wyłączone i nie będzie proponowane.';
    }
    if ($activity === 'second_hand_goods' && empty($profile['margin_scheme_enabled'])) {
        $warnings[] = 'Procedura marży jest wyłączona i nie będzie proponowana.';
    }
    if ($countryCode !== '') {
        $escapedCountry = $db->escape($countryCode);
        $defaultRate = $db->select_user("SELECT id FROM billing_tax_rates WHERE country_code = '{$escapedCountry}' AND is_active = 1 AND is_default = 1 LIMIT 1");
        if ($vatStatus === 'vat_registered' && (!is_array($defaultRate) || empty($defaultRate['id']))) {
            $blockers[] = 'Ustaw jedną aktywną domyślną stawkę VAT dla kraju profilu.';
        }
    }
    if (!str_contains((string)($profile['number_prefix'] ?? ''), '{YYYY}')) {
        $warnings[] = 'Format numeracji nie zawiera {YYYY}; numery nie będą rozdzielone według roku.';
    }
    return ['ready' => !$blockers, 'blockers' => $blockers, 'warnings' => $warnings];
}

function admin_billing_decimal($value, int $precision = 4): float
{
    $normalized = str_replace([' ', ','], ['', '.'], trim((string)$value));
    if ($normalized === '' || !is_numeric($normalized)) {
        return NAN;
    }
    return round((float)$normalized, $precision);
}

function admin_billing_valid_date($value, bool $allowEmpty = false): ?string
{
    if (str_contains((string)$value, chr(0))) return null;
    $value = trim((string)$value);
    if ($value === '' && $allowEmpty) {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function admin_billing_seller_snapshot(array $profile): string
{
    return (string)json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function admin_billing_tax_rows(Mysql_ks $db, string $countryCode = ''): array
{
    $where = '';
    $countryCode = strtoupper(trim($countryCode));
    if ($countryCode !== '') {
        $where = " WHERE country_code = '" . $db->escape($countryCode) . "'";
    }
    return $db->select_full_user("SELECT * FROM billing_tax_rates{$where} ORDER BY country_code ASC, sort_order ASC, rate_percent DESC, id ASC");
}

function admin_billing_customer_rows(Mysql_ks $db, int $limit = 500): array
{
    $limit = max(1, min(1000, $limit));
    return $db->select_full_user("SELECT bc.*, c.email AS linked_email, c.public_handle AS linked_handle,
            c.locale_code AS linked_locale_code
        FROM billing_customers bc
        LEFT JOIN customers c ON c.id = bc.customer_id
        ORDER BY bc.display_name ASC, bc.id DESC LIMIT {$limit}");
}

function admin_billing_site_customer_rows(Mysql_ks $db, int $limit = 500): array
{
    $limit = max(1, min(1000, $limit));
    return $db->select_full_user("SELECT id, email, public_handle, country_code, locale_code, status
        FROM customers ORDER BY id DESC LIMIT {$limit}");
}

function admin_billing_search_rows(Mysql_ks $db, string $type, string $query, int $profileId = 0): array
{
    $query = trim($query);
    if (mb_strlen($query) > 128 || (mb_strlen($query) < 2 && !ctype_digit($query))) {
        return [];
    }
    $escaped = $db->escape($query);
    if ($type === 'billing_customer') {
        return $db->select_full_user("SELECT bc.*, c.email AS linked_email, c.public_handle AS linked_handle,
                c.locale_code AS linked_locale_code
            FROM billing_customers bc LEFT JOIN customers c ON c.id = bc.customer_id
            WHERE bc.display_name LIKE '%{$escaped}%' OR bc.email LIKE '%{$escaped}%' OR bc.tax_id LIKE '%{$escaped}%'
            ORDER BY bc.updated_at DESC LIMIT 20");
    }
    if ($type === 'site_customer') {
        $numericId = ctype_digit($query) ? (int)$query : 0;
        return $db->select_full_user("SELECT id, email, public_handle, country_code, locale_code, status FROM customers
            WHERE " . ($numericId > 0 ? "id = {$numericId} OR " : '') . "email LIKE '%{$escaped}%' OR public_handle LIKE '%{$escaped}%'
            ORDER BY id DESC LIMIT 20");
    }
    if ($type === 'item') {
        return admin_billing_item_catalog_rows($db, $profileId, $query, 20);
    }
    return [];
}

function admin_billing_order_sources(Mysql_ks $db, int $limit = 300, int $sourceId = 0): array
{
    $limit = max(1, min(500, $limit));
    $where = ["o.payment_status IN ('paid','confirmed','approved','completed')"];
    if (schema_object_exists($db, 'crypto_deposit_requests')) {
        $where[] = "NOT EXISTS (SELECT 1 FROM crypto_deposit_requests cdr WHERE cdr.order_id = o.id AND cdr.status IN ('approved','paid','confirmed','completed'))";
    }
    if (schema_object_exists($db, 'bank_transfer_requests')) {
        $where[] = "NOT EXISTS (SELECT 1 FROM bank_transfer_requests btr WHERE btr.order_id = o.id AND btr.status IN ('approved','paid','confirmed','completed'))";
    }
    if ($sourceId > 0) $where[] = 'o.id = ' . $sourceId;
    $whereSql = implode(' AND ', $where);
    return $db->select_full_user("SELECT o.id, o.customer_id, o.product_id, o.total_amount, o.status,
            o.payment_status, o.paid_at, o.created_at, c.email, c.public_handle,
            COALESCE(p.name, CONCAT('Order #', o.id)) AS product_name,
            COALESCE(cur.code, 'EUR') AS currency_code
        FROM orders o
        INNER JOIN customers c ON c.id = o.customer_id
        LEFT JOIN products p ON p.id = o.product_id
        LEFT JOIN currencies cur ON cur.id = o.currency_id
        WHERE {$whereSql}
        ORDER BY o.id DESC LIMIT {$limit}");
}

function admin_billing_payment_sources(Mysql_ks $db, int $limit = 300, int $sourceId = 0, string $paymentType = ''): array
{
    $limit = max(1, min(500, $limit));
    $rows = [];
    $sourceFilter = $sourceId > 0 ? ' AND r.id = ' . $sourceId : '';
    if (($paymentType === '' || $paymentType === 'crypto') && schema_object_exists($db, 'crypto_deposit_requests')) {
        $cryptoRows = $db->select_full_user("SELECT r.id, r.customer_id, r.order_id, r.status,
                COALESCE(r.received_fiat_actual_amount, r.received_fiat_amount, r.requested_fiat_amount) AS amount,
                r.confirmed_at AS paid_at, c.email, c.public_handle,
                COALESCE(cur.code, 'EUR') AS currency_code,
                COALESCE(p.name, CONCAT('Crypto payment #', r.id)) AS description
            FROM crypto_deposit_requests r
            INNER JOIN customers c ON c.id = r.customer_id
            LEFT JOIN currencies cur ON cur.id = r.fiat_currency_id
            LEFT JOIN orders o ON o.id = r.order_id
            LEFT JOIN products p ON p.id = o.product_id
            WHERE r.status IN ('approved','paid','confirmed','completed') {$sourceFilter}
            ORDER BY r.id DESC LIMIT {$limit}");
        foreach ($cryptoRows as $row) {
            $row['payment_type'] = 'crypto';
            $rows[] = $row;
        }
    }
    if (($paymentType === '' || $paymentType === 'bank') && schema_object_exists($db, 'bank_transfer_requests')) {
        $bankRows = $db->select_full_user("SELECT r.id, r.customer_id, r.order_id, r.status,
                r.requested_amount AS amount, r.approved_at AS paid_at,
                c.email, c.public_handle, COALESCE(cur.code, 'EUR') AS currency_code,
                COALESCE(p.name, CONCAT('Bank transfer #', r.id)) AS description
            FROM bank_transfer_requests r
            INNER JOIN customers c ON c.id = r.customer_id
            LEFT JOIN currencies cur ON cur.id = r.currency_id
            LEFT JOIN orders o ON o.id = r.order_id
            LEFT JOIN products p ON p.id = o.product_id
            WHERE r.status IN ('approved','paid','confirmed','completed') {$sourceFilter}
            ORDER BY r.id DESC LIMIT {$limit}");
        foreach ($bankRows as $row) {
            $row['payment_type'] = 'bank';
            $rows[] = $row;
        }
    }
    usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['paid_at'] ?? ''), (string)($a['paid_at'] ?? '')));
    return array_slice($rows, 0, $limit);
}

function admin_billing_document_row(Mysql_ks $db, int $documentId): ?array
{
    if ($documentId <= 0) {
        return null;
    }
    $row = $db->select_user("SELECT d.*, c.email AS linked_customer_email, c.public_handle AS linked_customer_handle
        FROM billing_documents d
        LEFT JOIN customers c ON c.id = d.customer_id
        WHERE d.id = {$documentId} LIMIT 1");
    if (!is_array($row) || empty($row['id'])) {
        return null;
    }
    $row['items'] = $db->select_full_user("SELECT * FROM billing_document_items WHERE document_id = {$documentId} ORDER BY sort_order ASC, id ASC");
    return $row;
}

function admin_billing_document_with_profile_logo(Mysql_ks $db, array $document): array
{
    if ((string)($document['status'] ?? '') !== 'draft') return $document;
    $seller = json_decode((string)($document['seller_snapshot'] ?? '{}'), true);
    $seller = is_array($seller) ? $seller : [];
    if (trim((string)($seller['logo_url'] ?? '')) !== '' || (int)($document['company_profile_id'] ?? 0) <= 0) {
        return $document;
    }

    $profile = admin_billing_company_profile($db, (int)$document['company_profile_id']);
    if (trim((string)($profile['logo_url'] ?? '')) !== '') {
        $seller['logo_url'] = (string)$profile['logo_url'];
        $document['seller_snapshot'] = json_encode($seller, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $document;
}

function admin_billing_document_rows(Mysql_ks $db, string $status = '', string $query = '', int $limit = 100, int $profileId = 0): array
{
    $where = [];
    $status = strtolower(trim($status));
    if (in_array($status, ['draft', 'issued', 'paid', 'cancelled'], true)) {
        $where[] = "d.status = '" . $db->escape($status) . "'";
    }
    if ($profileId > 0) {
        $where[] = 'd.company_profile_id = ' . $profileId;
    }
    $query = trim($query);
    if ($query !== '') {
        $querySql = $db->escape($query);
        $where[] = "(d.document_number LIKE '%{$querySql}%' OR d.buyer_name LIKE '%{$querySql}%' OR d.buyer_email LIKE '%{$querySql}%')";
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $limit = max(1, min(500, $limit));
    return $db->select_full_user("SELECT d.*, c.email AS linked_customer_email, c.public_handle AS linked_customer_handle,
            cp.profile_label,
            (SELECT COUNT(*) FROM billing_document_deliveries bd_count WHERE bd_count.document_id = d.id AND bd_count.status = 'sent') AS email_delivery_count,
            (SELECT MAX(bd_last.sent_at) FROM billing_document_deliveries bd_last WHERE bd_last.document_id = d.id AND bd_last.status = 'sent') AS last_emailed_at
        FROM billing_documents d LEFT JOIN customers c ON c.id = d.customer_id
        LEFT JOIN billing_company_profiles cp ON cp.id = d.company_profile_id
        {$whereSql} ORDER BY d.issue_date DESC, d.id DESC LIMIT {$limit}");
}

function admin_billing_source_record(Mysql_ks $db, string $sourceType, int $sourceId): ?array
{
    if ($sourceId <= 0) {
        return null;
    }
    if ($sourceType === 'order') {
        foreach (admin_billing_order_sources($db, 1, $sourceId) as $row) {
            if ((int)($row['id'] ?? 0) === $sourceId) {
                return $row;
            }
        }
    }
    if (in_array($sourceType, ['payment_crypto', 'payment_bank'], true)) {
        $expectedType = $sourceType === 'payment_crypto' ? 'crypto' : 'bank';
        foreach (admin_billing_payment_sources($db, 1, $sourceId, $expectedType) as $row) {
            if ((string)($row['payment_type'] ?? '') === $expectedType && (int)($row['id'] ?? 0) === $sourceId) {
                return $row;
            }
        }
    }
    return null;
}

function admin_billing_used_source_keys(Mysql_ks $db, int $profileId, int $excludeDocumentId = 0): array
{
    if ($profileId <= 0) {
        return [];
    }
    $rows = $db->select_full_user("SELECT id, source_type, order_id, payment_id FROM billing_documents
        WHERE status <> 'cancelled' AND id <> " . max(0, $excludeDocumentId));
    $keys = [];
    foreach ($rows as $row) {
        $sourceType = (string)($row['source_type'] ?? '');
        $orderId = max(0, (int)($row['order_id'] ?? 0));
        if ($sourceType === 'order' && $orderId > 0) {
            $keys['order:' . $orderId] = true;
        }
        $paymentId = max(0, (int)($row['payment_id'] ?? 0));
        if (str_starts_with($sourceType, 'payment_') && $paymentId > 0) {
            $keys[$sourceType . ':' . $paymentId] = true;
        }
    }
    return array_keys($keys);
}

function admin_billing_save_logo(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'url' => ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Logo must be a JPG, PNG or WEBP file up to 2 MB.'];
    }
    $tmpName = (string)($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $tmpName !== '' ? (string)$finfo->file($tmpName) : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dimensions = $tmpName !== '' ? @getimagesize($tmpName) : false;
    if (!$dimensions || $dimensions[0] > 4096 || $dimensions[1] > 4096 || $dimensions[0] * $dimensions[1] > 16000000) return ['ok'=>false,'message'=>'Unsupported logo format.'];
    if (!isset($extensions[$mime])) {
        return ['ok' => false, 'message' => 'Unsupported logo format.'];
    }
    $relativeDir = 'uploads/billing';
    $absoluteDir = app_public_path($relativeDir);
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        return ['ok' => false, 'message' => 'Unable to create the billing upload directory.'];
    }
    $filename = 'company-logo-' . bin2hex(random_bytes(10)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmpName, $absoluteDir . '/' . $filename)) {
        return ['ok' => false, 'message' => 'Unable to save the company logo.'];
    }
    return ['ok' => true, 'url' => '/' . $relativeDir . '/' . $filename];
}

function admin_billing_save_company(Mysql_ks $db, array $input, array $files, int $adminUserId): array
{
    $countries = admin_billing_supported_countries();
    $countryCode = strtoupper(trim((string)($input['country_code'] ?? 'IE')));
    if (!isset($countries[$countryCode])) {
        return ['ok' => false, 'message' => 'Choose Austria, Poland or Ireland.'];
    }
    $profileId = max(0, (int)($input['company_profile_id'] ?? 0));
    $profileLabel = trim((string)($input['profile_label'] ?? ''));
    $companyName = trim((string)($input['company_name'] ?? ''));
    $address = trim((string)($input['address_line1'] ?? ''));
    $postalCode = trim((string)($input['postal_code'] ?? ''));
    $city = trim((string)($input['city'] ?? ''));
    if ($profileLabel === '' || $companyName === '' || $address === '' || $postalCode === '' || $city === '') {
        return ['ok' => false, 'message' => 'Profile label, company name, address, postal code and city are required.'];
    }
    $email = strtolower(trim((string)($input['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'message' => 'Company email is invalid.'];
    }
    $profile = $profileId > 0 ? admin_billing_company_profile($db, $profileId) : [];
    if ($profileId > 0 && empty($profile['id'])) return ['ok'=>false,'message'=>'Unable to save company billing data.'];
    $logoUrl = trim((string)($profile['logo_url'] ?? ''));
    if (isset($files['company_logo']) && is_array($files['company_logo'])) {
        $logoResult = admin_billing_save_logo($files['company_logo']);
        if (empty($logoResult['ok'])) {
            return $logoResult;
        }
        if ((string)($logoResult['url'] ?? '') !== '') {
            $logoUrl = (string)$logoResult['url'];
        }
    }
    if (!empty($input['remove_logo'])) {
        $logoUrl = '';
    }
    $currency = strtoupper(trim((string)($input['default_currency_code'] ?? $countries[$countryCode]['currency'])));
    if (!isset(admin_billing_supported_currencies()[$currency])) {
        $currency = (string)$countries[$countryCode]['currency'];
    }
    $numberPrefix = trim((string)($input['number_prefix'] ?? $countries[$countryCode]['number_prefix']));
    if ($numberPrefix === '' || strlen($numberPrefix) > 40) {
        return ['ok' => false, 'message' => 'Document number prefix is invalid.'];
    }
    $termsDays = max(0, min(365, (int)($input['payment_terms_days'] ?? 14)));
    $businessActivity = strtolower(trim((string)($input['business_activity'] ?? 'general')));
    if (!isset(admin_billing_business_activities()[$businessActivity])) {
        $businessActivity = 'general';
    }
    $vatStatus = strtolower(trim((string)($input['vat_status'] ?? 'vat_registered')));
    if (!isset(admin_billing_vat_statuses()[$vatStatus])) {
        $vatStatus = 'manual';
    }
    $isDefault = !empty($input['is_default']) ? 1 : 0;
    $isActive = !empty($input['is_active']) ? 1 : 0;
    if ($isDefault && !$isActive) {
        return ['ok' => false, 'message' => 'The default issuer profile must remain active.'];
    }
    $capabilities = admin_billing_profile_capabilities($countryCode, $businessActivity, $vatStatus);
    $ossEnabled = !empty($capabilities['oss']) && !empty($input['eu_oss_enabled']) ? 1 : 0;
    $medicalExemptionEnabled = !empty($capabilities['medical']) && !empty($input['medical_exemption_enabled']) ? 1 : 0;
    $marginSchemeEnabled = !empty($capabilities['margin']) && !empty($input['margin_scheme_enabled']) ? 1 : 0;
    $fields = [
        'profile_label', 'is_default', 'is_active', 'country_code', 'business_activity', 'vat_status', 'tax_guidance_note', 'eu_oss_enabled', 'medical_exemption_enabled', 'margin_scheme_enabled', 'company_name', 'legal_name', 'address_line1', 'address_line2', 'postal_code', 'city', 'country_name',
        'email', 'phone', 'website', 'vat_id', 'tax_number', 'registration_number', 'bank_name', 'iban', 'bic', 'logo_url',
        'default_currency_code', 'number_prefix', 'payment_terms_days', 'default_notes', 'default_footer', 'updated_by_admin_user_id',
    ];
    $values = [
        $profileLabel, $isDefault, $isActive,
        $countryCode, $businessActivity, $vatStatus, trim((string)($input['tax_guidance_note'] ?? '')) ?: null,
        $ossEnabled, $medicalExemptionEnabled, $marginSchemeEnabled,
        $companyName, trim((string)($input['legal_name'] ?? '')) ?: null,
        $address, trim((string)($input['address_line2'] ?? '')) ?: null, $postalCode, $city,
        (string)$countries[$countryCode]['name'],
        $email ?: null, trim((string)($input['phone'] ?? '')) ?: null, trim((string)($input['website'] ?? '')) ?: null,
        trim((string)($input['vat_id'] ?? '')) ?: null, trim((string)($input['tax_number'] ?? '')) ?: null,
        trim((string)($input['registration_number'] ?? '')) ?: null, trim((string)($input['bank_name'] ?? '')) ?: null,
        trim((string)($input['iban'] ?? '')) ?: null, trim((string)($input['bic'] ?? '')) ?: null, $logoUrl ?: null,
        $currency, $numberPrefix, $termsDays, trim((string)($input['default_notes'] ?? '')) ?: null,
        trim((string)($input['default_footer'] ?? '')) ?: null, $adminUserId > 0 ? $adminUserId : null,
    ];
    $db->start();
    if ($isDefault) {
        $db->query('UPDATE billing_company_profiles SET is_default = 0');
    }
    if ($profileId > 0) {
        $ok = $db->update_using_id($fields, $values, 'billing_company_profiles', $profileId);
    } else {
        $fields[] = 'created_at';
        $values[] = date('Y-m-d H:i:s');
        $ok = $db->insert($fields, $values, 'billing_company_profiles');
        $profileId = (int)$db->id();
    }
    if (!$ok || $profileId <= 0) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to save company billing data.'];
    }
    $db->commit();
    return ['ok' => true, 'message' => 'Company billing data has been saved.', 'company_profile_id' => $profileId];
}

function admin_billing_save_tax_rate(Mysql_ks $db, array $input): array
{
    $lock = admin_billing_operation_lock($db, 'tax-default:' . strtoupper((string)($input['tax_country_code'] ?? '')));
    if ($lock === null) return ['ok'=>false,'message'=>'Unable to save tax rate.'];
    $db->start();
    try {
        $result = admin_billing_save_tax_rate_locked($db, $input);
        if (empty($result['ok']) || !$db->query('COMMIT')) { $db->query('ROLLBACK'); return ['ok'=>false,'message'=>(string)($result['message'] ?? 'Unable to save tax rate.')]; }
        return $result;
    } finally { admin_billing_operation_unlock($db, $lock); }
}

function admin_billing_save_tax_rate_locked(Mysql_ks $db, array $input): array
{
    $allowedCountries = array_fill_keys(array_merge(array_keys(admin_billing_supported_countries()), admin_billing_eu_country_codes()), true);
    $id = max(0, (int)($input['tax_rate_id'] ?? 0));
    $countryCode = strtoupper(trim((string)($input['tax_country_code'] ?? '')));
    $label = trim((string)($input['tax_label'] ?? ''));
    $rate = admin_billing_decimal($input['tax_rate_percent'] ?? 0, 2);
    if (!isset($allowedCountries[$countryCode]) || $label === '' || !is_finite($rate) || $rate < 0 || $rate > 100) {
        return ['ok' => false, 'message' => 'Tax profile data is invalid.'];
    }
    if ($id > 0 && !$db->select_user("SELECT id FROM billing_tax_rates WHERE id = {$id} LIMIT 1")) return ['ok'=>false,'message'=>'Tax profile data is invalid.'];
    $isDefault = !empty($input['tax_is_default']) ? 1 : 0;
    $isActive = !empty($input['tax_is_active']) ? 1 : 0;
    if ($isDefault && !$isActive) {
        return ['ok' => false, 'message' => 'The default VAT rate must remain active.'];
    }
    if ($isDefault) {
        $db->query("UPDATE billing_tax_rates SET is_default = 0 WHERE country_code = '" . $db->escape($countryCode) . "'");
    }
    if ($id > 0) {
        $ok = $db->update_using_id(
            ['country_code', 'label', 'rate_percent', 'is_default', 'is_active', 'sort_order'],
            [$countryCode, $label, $rate, $isDefault, $isActive, max(0, (int)($input['tax_sort_order'] ?? 100))],
            'billing_tax_rates',
            $id
        );
    } else {
        $rateCode = 'custom_' . bin2hex(random_bytes(5));
        $ok = $db->insert(
            ['country_code', 'rate_code', 'label', 'rate_percent', 'is_default', 'is_active', 'sort_order'],
            [$countryCode, $rateCode, $label, $rate, $isDefault, $isActive, max(0, (int)($input['tax_sort_order'] ?? 100))],
            'billing_tax_rates'
        );
    }
    return ['ok' => (bool)$ok, 'message' => $ok ? 'Tax rate has been saved.' : 'Unable to save tax rate.'];
}

function admin_billing_save_catalog_item(Mysql_ks $db, array $input): array
{
    $id = max(0, (int)($input['catalog_item_id'] ?? 0));
    $profileId = max(0, (int)($input['company_profile_id'] ?? 0));
    $description = trim((string)($input['catalog_description'] ?? ''));
    $unit = trim((string)($input['catalog_unit_label'] ?? 'szt.')) ?: 'szt.';
    $price = admin_billing_decimal($input['catalog_unit_price_net'] ?? 0, 4);
    $taxRate = admin_billing_decimal($input['catalog_tax_rate_percent'] ?? 0, 2);
    $supplyType = strtolower(trim((string)($input['catalog_supply_type'] ?? 'digital_service')));
    if ($profileId <= 0 || !admin_billing_company_profile($db, $profileId) || $description === '' || !is_finite($price) || !is_finite($taxRate) || $price >= 100000000 || $price < 0 || $taxRate < 0 || $taxRate > 100 || !isset(admin_billing_supply_types()[$supplyType])) {
        return ['ok' => false, 'message' => 'Catalog item data is invalid.'];
    }
    $fields = ['company_profile_id', 'description', 'unit_label', 'default_unit_price_net', 'default_tax_rate_percent', 'supply_type', 'is_active'];
    $values = [$profileId, mb_substr($description, 0, 500), mb_substr($unit, 0, 30), $price, $taxRate, $supplyType, !empty($input['catalog_is_active']) ? 1 : 0];
    if ($id > 0) {
        $owned = $db->select_user("SELECT id FROM billing_item_catalog WHERE id = {$id} AND company_profile_id = {$profileId} LIMIT 1");
        $ok = is_array($owned) && $db->update_using_id($fields, $values, 'billing_item_catalog', $id);
    } else {
        $ok = $db->insert($fields, $values, 'billing_item_catalog');
    }
    return ['ok' => (bool)$ok, 'message' => $ok ? 'Catalog item has been saved.' : 'Unable to save catalog item.'];
}

function admin_billing_save_customer(Mysql_ks $db, array $input, int $adminUserId, int $existingId = 0): array
{
    $displayName = trim((string)($input['buyer_name'] ?? $input['billing_customer_display_name'] ?? ''));
    if ($displayName === '') {
        return ['ok' => false, 'message' => 'Customer name is required.'];
    }
    $email = strtolower(trim((string)($input['buyer_email'] ?? $input['billing_customer_email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'message' => 'Customer email is invalid.'];
    }
    $linkedCustomerId = max(0, (int)($input['linked_customer_id'] ?? $input['customer_id'] ?? 0));
    if ($linkedCustomerId > 0) {
        $linked = $db->select_user("SELECT id FROM customers WHERE id = {$linkedCustomerId} LIMIT 1");
        if (!is_array($linked) || empty($linked['id'])) {
            return ['ok' => false, 'message' => 'Selected service user does not exist.'];
        }
    }
    $countryCode = strtoupper(trim((string)($input['destination_country_code'] ?? $input['billing_customer_country_code'] ?? '')));
    if ($countryCode !== '' && !isset(admin_billing_customer_countries('en')[$countryCode])) {
        return ['ok' => false, 'message' => 'Selected customer country is invalid.'];
    }
    $countryName = $countryCode !== ''
        ? (string)(admin_billing_customer_countries('en')[$countryCode]['name'] ?? $countryCode)
        : trim((string)($input['buyer_country'] ?? $input['billing_customer_country_name'] ?? ''));
    $existingDelivery = $existingId > 0
        ? $db->select_user("SELECT electronic_delivery_accepted_at, electronic_delivery_email FROM billing_customers WHERE id = {$existingId} LIMIT 1")
        : null;
    if ($existingId > 0 && !$existingDelivery) return ['ok'=>false,'message'=>'Selected billing customer does not exist.'];
    if (array_key_exists('electronic_delivery_accepted', $input)) {
        $deliveryAccepted = !empty($input['electronic_delivery_accepted']);
        $deliveryAcceptedAt = $deliveryAccepted ? date('Y-m-d H:i:s') : null;
        $deliveryEmail = $deliveryAccepted && $email !== '' ? $email : null;
    } else {
        $acceptedEmail = strtolower(trim((string)($existingDelivery['electronic_delivery_email'] ?? '')));
        $deliveryAcceptedAt = $acceptedEmail !== '' && $acceptedEmail === $email
            ? ((string)($existingDelivery['electronic_delivery_accepted_at'] ?? '') ?: null)
            : null;
        $deliveryEmail = $deliveryAcceptedAt !== null ? $acceptedEmail : null;
    }
    $fields = [
        'customer_id', 'display_name', 'company_name', 'email', 'phone', 'address_line1', 'address_line2',
        'postal_code', 'city', 'country_code', 'country_name', 'tax_id', 'electronic_delivery_accepted_at',
        'electronic_delivery_email', 'notes', 'updated_by_admin_user_id',
    ];
    $values = [
        $linkedCustomerId ?: null, $displayName, trim((string)($input['buyer_company_name'] ?? $input['billing_customer_company_name'] ?? '')) ?: null,
        $email ?: null, trim((string)($input['buyer_phone'] ?? $input['billing_customer_phone'] ?? '')) ?: null,
        trim((string)($input['buyer_address_line1'] ?? $input['billing_customer_address_line1'] ?? '')) ?: null,
        trim((string)($input['buyer_address_line2'] ?? $input['billing_customer_address_line2'] ?? '')) ?: null,
        trim((string)($input['buyer_postal_code'] ?? $input['billing_customer_postal_code'] ?? '')) ?: null,
        trim((string)($input['buyer_city'] ?? $input['billing_customer_city'] ?? '')) ?: null,
        $countryCode ?: null,
        $countryName ?: null,
        trim((string)($input['buyer_tax_id'] ?? $input['billing_customer_tax_id'] ?? '')) ?: null,
        $deliveryAcceptedAt, $deliveryEmail,
        trim((string)($input['billing_customer_notes'] ?? '')) ?: null, $adminUserId ?: null,
    ];
    if ($existingId > 0) {
        $ok = $db->update_using_id($fields, $values, 'billing_customers', $existingId);
        $id = $existingId;
    } else {
        $fields[] = 'created_by_admin_user_id';
        $values[] = $adminUserId ?: null;
        $ok = $db->insert($fields, $values, 'billing_customers');
        $id = (int)$db->id();
    }
    return ['ok' => (bool)$ok, 'message' => $ok ? 'Billing customer has been saved.' : 'Unable to save billing customer.', 'billing_customer_id' => $id];
}

function admin_billing_save_automation_settings(Mysql_ks $db, array $input, array $adminUser): array
{
    if (strtolower(trim((string)($adminUser['login_name'] ?? ''))) !== 'admin') {
        return ['ok' => false, 'message' => 'Only the main admin account can change automatic billing settings.'];
    }

    $enabled = !empty($input['billing_auto_issue_email_enabled']) ? 1 : 0;
    $profileId = max(0, (int)($input['billing_auto_company_profile_id'] ?? 0));
    $supplyType = strtolower(trim((string)($input['billing_auto_supply_type'] ?? 'digital_service')));
    if (!isset(admin_billing_supply_types()[$supplyType])) {
        return ['ok' => false, 'message' => 'Select a valid default sale type for automatic documents.'];
    }
    if ($enabled) {
        $profile = admin_billing_company_profile($db, $profileId);
        if (empty($profile['id']) || empty($profile['is_active'])) {
            return ['ok' => false, 'message' => 'Select an active issuer profile before enabling automatic billing.'];
        }
        $readiness = admin_billing_profile_readiness($db, $profile);
        if (empty($readiness['ready'])) {
            return ['ok' => false, 'message' => 'The selected issuer profile is not ready: ' . implode(' ', (array)$readiness['blockers'])];
        }
    }

    $ok = $db->update_using_id(
        ['billing_auto_issue_email_enabled', 'billing_auto_company_profile_id', 'billing_auto_supply_type'],
        [$enabled, $profileId ?: null, $supplyType],
        'app_settings',
        1
    );
    return [
        'ok' => (bool)$ok,
        'message' => $ok ? 'Automatic billing settings have been saved.' : 'Unable to save automatic billing settings.',
    ];
}

function admin_billing_customer_auto_delivery_context(Mysql_ks $db, int $customerId): array
{
    static $cache = [];
    if ($customerId <= 0) {
        return ['ready' => false, 'message' => 'Customer account is unavailable.', 'billing_customer' => null];
    }
    if (isset($cache[$customerId])) {
        return $cache[$customerId];
    }

    $row = $db->select_user("SELECT * FROM billing_customers WHERE customer_id = {$customerId} ORDER BY updated_at DESC, id DESC LIMIT 1");
    if (!is_array($row) || empty($row['id'])) {
        return $cache[$customerId] = [
            'ready' => false,
            'message' => 'Add billing details for this customer before issuing a document.',
            'billing_customer' => null,
        ];
    }

    $missing = [];
    foreach (['display_name' => 'name', 'email' => 'email', 'address_line1' => 'address', 'postal_code' => 'postal code', 'city' => 'city', 'country_code' => 'country'] as $field => $label) {
        if (trim((string)($row[$field] ?? '')) === '') {
            $missing[] = $label;
        }
    }
    $email = strtolower(trim((string)($row['email'] ?? '')));
    $acceptedEmail = strtolower(trim((string)($row['electronic_delivery_email'] ?? '')));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $missing[] = 'valid email';
    }
    if (empty($row['electronic_delivery_accepted_at']) || $acceptedEmail === '' || $acceptedEmail !== $email) {
        $missing[] = 'electronic delivery consent';
    }

    return $cache[$customerId] = [
        'ready' => !$missing,
        'message' => $missing ? 'Complete: ' . implode(', ', array_values(array_unique($missing))) . '.' : 'Billing details are ready.',
        'billing_customer' => $row,
    ];
}

function admin_billing_normalize_items(array $input): array
{
    $descriptions = isset($input['item_description']) && is_array($input['item_description']) ? $input['item_description'] : [];
    $quantities = isset($input['item_quantity']) && is_array($input['item_quantity']) ? $input['item_quantity'] : [];
    $units = isset($input['item_unit']) && is_array($input['item_unit']) ? $input['item_unit'] : [];
    $prices = isset($input['item_unit_price']) && is_array($input['item_unit_price']) ? $input['item_unit_price'] : [];
    $purchasePrices = isset($input['item_purchase_price']) && is_array($input['item_purchase_price']) ? $input['item_purchase_price'] : [];
    $rates = isset($input['item_tax_rate']) && is_array($input['item_tax_rate']) ? $input['item_tax_rate'] : [];
    $priceEntryMode = strtolower(trim((string)($input['price_entry_mode'] ?? 'net')));
    $priceEntryMode = $priceEntryMode === 'gross' ? 'gross' : 'net';
    $isMarginScheme = strtolower(trim((string)($input['tax_treatment'] ?? ''))) === 'margin_scheme_second_hand';
    if (count($descriptions) > 200) return ['ok'=>false,'message'=>'Use no more than 200 billing items.','items'=>[]];
    $items = [];
    foreach ($descriptions as $index => $descriptionValue) {
        $description = trim((string)$descriptionValue);
        if ($description === '') {
            continue;
        }
        foreach ([$quantities[$index] ?? 1, $prices[$index] ?? 0, $rates[$index] ?? 0] as $rawNumber) {
            $normalized = str_replace([' ', ','], ['', '.'], trim((string)$rawNumber));
            if ($normalized === '' || !is_numeric($normalized) || !is_finite((float)$normalized)) return ['ok'=>false,'message'=>'Check quantity, price and VAT rate for every item.','items'=>[]];
        }
        $quantity = admin_billing_decimal($quantities[$index] ?? 1, 3);
        $unitPrice = admin_billing_decimal($prices[$index] ?? 0, 4);
        $purchasePriceRaw = $purchasePrices[$index] ?? '';
        $hasPurchasePrice = trim((string)$purchasePriceRaw) !== '';
        $purchasePrice = $isMarginScheme ? admin_billing_decimal($purchasePriceRaw, 4) : null;
        $taxRate = admin_billing_decimal($rates[$index] ?? 0, 2);
        if ($quantity <= 0 || $quantity >= 1000000000 || $unitPrice >= 100000000 || $unitPrice < 0 || $taxRate < 0 || $taxRate > 100) {
            return ['ok' => false, 'message' => 'Check quantity, price and VAT rate for every item.', 'items' => []];
        }
        if ($isMarginScheme) {
            if ($priceEntryMode !== 'gross' || !$hasPurchasePrice || $purchasePrice === null || !is_finite($purchasePrice) || $purchasePrice >= 100000000 || $purchasePrice < 0 || $purchasePrice > $unitPrice || $taxRate <= 0) {
                return ['ok' => false, 'message' => 'For the margin scheme, use gross prices, a positive VAT rate and a purchase price no higher than the sale price for every item.', 'items' => []];
            }
            $lineGross = round($quantity * $unitPrice, 2);
            $linePurchaseGross = round($quantity * $purchasePrice, 2);
            $lineMarginGross = round(max(0, $lineGross - $linePurchaseGross), 2);
            $lineTax = $taxRate > 0 ? round($lineMarginGross * $taxRate / (100 + $taxRate), 2) : 0.0;
            $lineNet = round($lineGross - $lineTax, 2);
            $unitPriceNet = $quantity > 0 ? round($lineNet / $quantity, 4) : 0.0;
        } elseif ($priceEntryMode === 'gross') {
            $lineGross = round($quantity * $unitPrice, 2);
            $lineNet = $taxRate > 0 ? round($lineGross / (1 + $taxRate / 100), 2) : $lineGross;
            $lineTax = round($lineGross - $lineNet, 2);
            $unitPriceNet = $quantity > 0 ? round($lineNet / $quantity, 4) : 0.0;
        } else {
            $unitPriceNet = $unitPrice;
            $lineNet = round($quantity * $unitPriceNet, 2);
            $lineTax = round($lineNet * $taxRate / 100, 2);
            $lineGross = round($lineNet + $lineTax, 2);
        }
        if (!is_finite($lineGross) || $lineGross > 9999999999.99 || $lineNet > 9999999999.99 || $lineTax > 9999999999.99) return ['ok'=>false,'message'=>'Billing item amount exceeds the supported limit.','items'=>[]];
        $items[] = [
            'description' => mb_substr($description, 0, 500),
            'quantity' => $quantity,
            'unit_label' => mb_substr(trim((string)($units[$index] ?? 'szt.')) ?: 'szt.', 0, 30),
            'unit_price_net' => $unitPriceNet,
            'purchase_price_gross' => $isMarginScheme ? $purchasePrice : null,
            'tax_rate_percent' => $taxRate,
            'line_purchase_gross' => $isMarginScheme ? $linePurchaseGross : null,
            'line_margin_gross' => $isMarginScheme ? $lineMarginGross : null,
            'line_net' => $lineNet,
            'line_tax' => $lineTax,
            'line_gross' => $lineGross,
        ];
    }
    if (!$items) {
        return ['ok' => false, 'message' => 'Add at least one billing item.', 'items' => []];
    }
    return ['ok' => true, 'message' => '', 'items' => $items];
}

function admin_billing_form_document_from_input(array $input): array
{
    $sourceType = strtolower(trim((string)($input['source_type'] ?? 'independent')));
    if (!in_array($sourceType, ['independent', 'order', 'payment_crypto', 'payment_bank'], true)) {
        $sourceType = 'independent';
    }
    $sourceId = max(0, (int)($input['source_record_id'] ?? 0));
    $descriptions = is_array($input['item_description'] ?? null) ? $input['item_description'] : [];
    $quantities = is_array($input['item_quantity'] ?? null) ? $input['item_quantity'] : [];
    $units = is_array($input['item_unit'] ?? null) ? $input['item_unit'] : [];
    $prices = is_array($input['item_unit_price'] ?? null) ? $input['item_unit_price'] : [];
    $purchasePrices = is_array($input['item_purchase_price'] ?? null) ? $input['item_purchase_price'] : [];
    $rates = is_array($input['item_tax_rate'] ?? null) ? $input['item_tax_rate'] : [];
    $items = [];
    foreach ($descriptions as $index => $description) {
        $items[] = [
            'description' => (string)$description,
            'quantity' => (string)($quantities[$index] ?? '1'),
            'unit_label' => (string)($units[$index] ?? 'szt.'),
            // A failed gross-price form must keep the exact value entered by the admin.
            'unit_price_net' => (string)($prices[$index] ?? '0'),
            'purchase_price_gross' => (string)($purchasePrices[$index] ?? ''),
            'tax_rate_percent' => (string)($rates[$index] ?? '0'),
        ];
    }

    $document = [
        '_form_input' => true,
        'billing_submission_token' => (string)($input['billing_submission_token'] ?? ''),
        'id' => max(0, (int)($input['billing_document_id'] ?? 0)),
        'status' => 'draft',
        'company_profile_id' => max(0, (int)($input['company_profile_id'] ?? 0)),
        'source_type' => $sourceType,
        'order_id' => $sourceType === 'order' ? $sourceId : null,
        'payment_id' => str_starts_with($sourceType, 'payment_') ? $sourceId : null,
        'billing_customer_id' => max(0, (int)($input['billing_customer_id'] ?? 0)),
        'customer_id' => max(0, (int)($input['linked_customer_id'] ?? 0)),
        'tax_review_confirmed' => !empty($input['tax_review_confirmed']),
        'buyer_vat_id_verified' => !empty($input['buyer_vat_id_verified']),
        'source_total_override_confirmed' => !empty($input['source_total_override_confirmed']),
        'foreign_currency_review_confirmed' => !empty($input['foreign_currency_review_confirmed']),
        'pl_ksef_review_confirmed' => !empty($input['pl_ksef_review_confirmed']),
        'save_billing_customer' => !empty($input['save_billing_customer']),
        'items' => $items,
    ];
    foreach ([
        'buyer_name', 'buyer_company_name', 'buyer_tax_id', 'buyer_email', 'buyer_phone',
        'buyer_address_line1', 'buyer_address_line2', 'buyer_postal_code', 'buyer_city', 'buyer_country',
        'destination_country_code', 'language_code', 'currency_code', 'price_entry_mode', 'issue_date',
        'service_date', 'due_date', 'supply_type', 'buyer_type', 'transaction_region', 'tax_treatment',
        'tax_treatment_note', 'notes', 'payment_notes', 'external_reference',
    ] as $field) {
        $document[$field] = (string)($input[$field] ?? '');
    }
    return $document;
}

function admin_billing_allowed_tax_treatments(
    string $supplyType,
    string $buyerType,
    string $region,
    string $vatStatus,
    string $businessActivity = 'general',
    bool $ossEnabled = false,
    bool $medicalExemptionEnabled = false,
    bool $marginSchemeEnabled = false,
    string $issuerCountryCode = ''
): array
{
    $profileCapabilities = admin_billing_profile_capabilities($issuerCountryCode, $businessActivity, $vatStatus);
    $ossEnabled = $ossEnabled && !empty($profileCapabilities['oss']);
    $medicalExemptionEnabled = $medicalExemptionEnabled && !empty($profileCapabilities['medical']);
    $marginSchemeEnabled = $marginSchemeEnabled && !empty($profileCapabilities['margin']);
    $allowed = ['manual'];
    if ($region === 'domestic') {
        if ($vatStatus === 'vat_registered') {
            array_unshift($allowed, 'domestic_vat');
        } elseif (in_array($vatStatus, ['small_business_exempt', 'not_vat_registered'], true)) {
            array_unshift($allowed, 'small_business_exempt');
        }
        if ($medicalExemptionEnabled
            && in_array($supplyType, ['healthcare_service', 'psychological_service'], true)
            && in_array($businessActivity, ['healthcare', 'psychology'], true)) {
            array_unshift($allowed, 'medical_exempt_professional');
            $allowed[] = 'medical_exempt_entity';
        }
        if ($vatStatus === 'vat_registered' && $marginSchemeEnabled && $supplyType === 'used_goods' && $businessActivity === 'second_hand_goods') {
            array_unshift($allowed, 'margin_scheme_second_hand');
        }
    } elseif ($region === 'eu') {
        if ($buyerType === 'business' && $vatStatus === 'vat_registered') {
            array_unshift($allowed, $supplyType === 'construction_service' ? 'construction_reverse_charge' : 'eu_b2b_reverse_charge');
        } elseif ($vatStatus === 'vat_registered' && $ossEnabled && in_array($supplyType, admin_billing_digital_supply_types(), true)) {
            array_unshift($allowed, 'eu_b2c_destination_oss');
        } elseif ($vatStatus === 'vat_registered' && $ossEnabled && in_array($supplyType, admin_billing_goods_supply_types(), true)) {
            array_unshift($allowed, 'eu_distance_goods_oss');
        }
    } elseif ($region === 'uk') {
        array_unshift($allowed, 'uk_manual');
    } elseif ($region === 'outside_eu' && in_array($supplyType, admin_billing_goods_supply_types(), true)) {
        array_unshift($allowed, 'export_outside_eu');
    }
    return array_values(array_unique($allowed));
}

function admin_billing_validate_tax_context(
    string $supplyType,
    string $buyerType,
    string $region,
    string $treatment,
    string $destinationCountry,
    string $vatStatus,
    string $businessActivity = 'general',
    bool $ossEnabled = false,
    bool $medicalExemptionEnabled = false,
    bool $marginSchemeEnabled = false,
    string $issuerCountryCode = ''
): string
{
    if (!in_array($treatment, admin_billing_allowed_tax_treatments(
        $supplyType,
        $buyerType,
        $region,
        $vatStatus,
        $businessActivity,
        $ossEnabled,
        $medicalExemptionEnabled,
        $marginSchemeEnabled,
        $issuerCountryCode
    ), true)) {
        return 'The selected tax treatment does not match the issuer VAT profile, supply type, market and buyer type.';
    }
    if ($treatment === 'eu_b2c_destination_oss' && !($region === 'eu' && $buyerType === 'consumer' && in_array($supplyType, admin_billing_digital_supply_types(), true))) {
        return 'EU B2C / OSS for digital services requires an EU consumer and a digital-service sale.';
    }
    if ($treatment === 'eu_distance_goods_oss' && !($region === 'eu' && $buyerType === 'consumer' && in_array($supplyType, admin_billing_goods_supply_types(), true))) {
        return 'EU distance-sales OSS requires an EU consumer and a goods sale.';
    }
    if ($treatment === 'eu_b2b_reverse_charge' && !($region === 'eu' && $buyerType === 'business')) {
        return 'EU B2B reverse charge requires an EU business buyer.';
    }
    if ($treatment === 'small_business_exempt' && !($region === 'domestic' && in_array($vatStatus, ['small_business_exempt', 'not_vat_registered'], true))) {
        return 'The small-business exemption requires a domestic sale and a matching issuer VAT status.';
    }
    if ($treatment === 'construction_reverse_charge' && $supplyType !== 'construction_service') {
        return 'Construction reverse charge can only be used for a construction-service item.';
    }
    if (in_array($treatment, ['medical_exempt_entity', 'medical_exempt_professional'], true)
        && !in_array($supplyType, ['healthcare_service', 'psychological_service'], true)) {
        return 'The selected medical exemption requires a healthcare or psychological service.';
    }
    if ($treatment === 'margin_scheme_second_hand' && $supplyType !== 'used_goods') {
        return 'The second-hand margin scheme requires a used-goods sale.';
    }
    if ($treatment === 'export_outside_eu' && !($region === 'outside_eu' && in_array($supplyType, admin_billing_goods_supply_types(), true))) {
        return 'Export outside the EU requires an outside-EU goods sale.';
    }
    if (in_array($treatment, ['eu_b2c_destination_oss', 'eu_distance_goods_oss', 'eu_b2b_reverse_charge'], true)
        && !preg_match('/^[A-Z]{2}$/', strtoupper(trim($destinationCountry)))) {
        return 'Enter the two-letter destination country code for this EU transaction.';
    }
    return '';
}

function admin_billing_allocate_number(Mysql_ks $db, array $profile, string $issueDate): ?string
{
    $profileId = max(0, (int)($profile['id'] ?? 0));
    $locked = $profileId > 0 ? $db->select_user("SELECT number_prefix, next_number FROM billing_company_profiles WHERE id = {$profileId} FOR UPDATE") : null;
    if (!is_array($locked)) {
        return null;
    }
    $sequence = max(1, (int)($locked['next_number'] ?? 1));
    $year = substr($issueDate, 0, 4);
    $prefix = str_replace('{YYYY}', $year, trim((string)($locked['number_prefix'] ?? $profile['number_prefix'] ?? 'RE-{YYYY}-')));
    $number = $prefix . str_pad((string)$sequence, 5, '0', STR_PAD_LEFT);
    if (!$db->update_using_id(['next_number'], [$sequence + 1], 'billing_company_profiles', $profileId)) {
        return null;
    }
    return $number;
}

function admin_billing_source_unique_key(string $sourceType, int $sourceId, ?array $sourceRecord): ?string
{
    if ($sourceType === 'independent' || $sourceId <= 0 || !$sourceRecord) {
        return null;
    }
    if ($sourceType === 'order') {
        return 'order:' . $sourceId;
    }
    return $sourceType . ':' . $sourceId;
}

/** Nonblocking document/source locks also cover the SMTP interval, without holding SQL row locks. */
function admin_billing_operation_lock(Mysql_ks $db, string $identity): ?string
{
    $database = $db->select_user('SELECT DATABASE() AS name');
    $key = 'billing:' . substr(hash('sha256', (string)($database['name'] ?? '') . '|' . $identity), 0, 48);
    $result = $db->select_user("SELECT GET_LOCK('{$key}', 0) AS acquired");
    return (int)($result['acquired'] ?? 0) === 1 ? $key : null;
}

function admin_billing_operation_unlock(Mysql_ks $db, string $key): void
{
    $safeKey = $db->escape($key);
    $db->select_user("SELECT RELEASE_LOCK('{$safeKey}')");
}

function admin_billing_save_document(Mysql_ks $db, array $input, int $adminUserId, array $profile, bool $issueNow): array
{
    $id = max(0, (int)($input['billing_document_id'] ?? 0));
    $token = (string)($input['billing_submission_token'] ?? '');
    if ($token !== '' && !preg_match('/^[a-f0-9]{64}$/D', $token)) return ['ok'=>false, 'message'=>'Invalid document submission. Reload the form.'];
    $source = (string)($input['source_type'] ?? 'independent');
    $identity = $id > 0 ? 'document:' . $id : ($source === 'independent' && $token !== '' ? 'submission:' . $token : $source . ':' . (int)($input['source_record_id'] ?? 0));
    $lock = admin_billing_operation_lock($db, $identity);
    if ($lock === null) return ['ok'=>false, 'message'=>'This document is being processed. Wait a moment and try again.'];
    try {
        if ($id === 0 && $source === 'independent' && $token !== '') {
            $existing = $db->select_user("SELECT id, status, company_profile_id FROM billing_documents WHERE source_unique_key = 'manual:{$token}' LIMIT 1");
            if ($existing) return ['ok'=>true, 'document_id'=>(int)$existing['id'], 'company_profile_id'=>(int)$existing['company_profile_id'], 'status'=>$existing['status'], 'message'=>'Billing document has been saved.'];
        }
        return admin_billing_save_document_locked($db, $input, $adminUserId, $profile, $issueNow);
    } finally { admin_billing_operation_unlock($db, $lock); }
}

function admin_billing_save_document_locked(Mysql_ks $db, array $input, int $adminUserId, array $profile, bool $issueNow): array
{
    $profileId = max(0, (int)($input['company_profile_id'] ?? $profile['id'] ?? 0));
    $profile = admin_billing_company_profile($db, $profileId);
    if (empty($profile['id'])) {
        return ['ok' => false, 'message' => 'Select an issuer profile before creating a billing document.'];
    }
    $profileReadiness = admin_billing_profile_readiness($db, $profile);
    if ($issueNow && empty($profileReadiness['ready'])) {
        return ['ok' => false, 'message' => 'Issuer profile is not ready: ' . implode(' ', (array)$profileReadiness['blockers'])];
    }
    $documentId = max(0, (int)($input['billing_document_id'] ?? 0));
    $existing = $documentId > 0 ? admin_billing_document_row($db, $documentId) : null;
    if ($documentId > 0 && (!$existing || (string)($existing['status'] ?? '') !== 'draft')) {
        return ['ok' => false, 'message' => 'Only draft documents can be edited.'];
    }
    $sourceType = strtolower(trim((string)($input['source_type'] ?? 'independent')));
    if (!in_array($sourceType, ['independent', 'order', 'payment_crypto', 'payment_bank'], true)) {
        $sourceType = 'independent';
    }
    $sourceId = max(0, (int)($input['source_record_id'] ?? 0));
    $sourceRecord = $sourceType === 'independent' ? null : admin_billing_source_record($db, $sourceType, $sourceId);
    if ($sourceType !== 'independent' && !$sourceRecord) {
        return ['ok' => false, 'message' => 'Selected order or approved payment is no longer available.'];
    }
    if ($sourceType !== 'independent') {
        $exactSourceCondition = $sourceType === 'order'
            ? "source_type = 'order' AND order_id = {$sourceId}"
            : "source_type = '" . $db->escape($sourceType) . "' AND payment_id = {$sourceId}";
        $sourceCondition = "({$exactSourceCondition})";
        $duplicate = $db->select_user("SELECT id FROM billing_documents WHERE {$sourceCondition} AND status <> 'cancelled' AND id <> {$documentId} LIMIT 1");
        if (is_array($duplicate) && !empty($duplicate['id'])) {
            return ['ok' => false, 'message' => 'A billing document already exists for this source.'];
        }
    }
    $sourceUniqueKey = admin_billing_source_unique_key($sourceType, $sourceId, $sourceRecord);
    if ($sourceType === 'independent') {
        $token = (string)($input['billing_submission_token'] ?? '');
        $sourceUniqueKey = !empty($existing['source_unique_key']) && str_starts_with((string)$existing['source_unique_key'], 'manual:')
            ? (string)$existing['source_unique_key'] : ($token !== '' ? 'manual:' . $token : null);
    }
    $billingCustomerId = max(0, (int)($input['billing_customer_id'] ?? 0));
    $customerId = max(0, (int)($input['linked_customer_id'] ?? 0));
    $orderId = null;
    $paymentType = null;
    $paymentId = null;
    if ($sourceRecord) {
        $customerId = (int)($sourceRecord['customer_id'] ?? 0);
        $orderId = !empty($sourceRecord['order_id']) ? (int)$sourceRecord['order_id'] : ($sourceType === 'order' ? $sourceId : null);
        if (str_starts_with($sourceType, 'payment_')) {
            $paymentType = (string)($sourceRecord['payment_type'] ?? '');
            $paymentId = $sourceId;
        }
    }
    if ($customerId > 0) {
        $linked = $db->select_user("SELECT id FROM customers WHERE id = {$customerId} LIMIT 1");
        if (!is_array($linked)) {
            return ['ok' => false, 'message' => 'Selected service user does not exist.'];
        }
    }
    if ($billingCustomerId > 0) {
        $billingCustomer = $db->select_user("SELECT id, customer_id FROM billing_customers WHERE id = {$billingCustomerId} LIMIT 1");
        if (!is_array($billingCustomer)) {
            return ['ok' => false, 'message' => 'Selected billing customer does not exist.'];
        }
        $billingLinkedCustomerId = (int)($billingCustomer['customer_id'] ?? 0);
        if ($customerId <= 0 && $billingLinkedCustomerId > 0) $customerId = $billingLinkedCustomerId;
        if ($customerId > 0 && $billingLinkedCustomerId > 0 && $billingLinkedCustomerId !== $customerId) {
            return ['ok' => false, 'message' => 'The billing customer is linked to a different service account than the selected source.'];
        }
    }
    $buyerName = trim((string)($input['buyer_name'] ?? ''));
    $buyerEmail = strtolower(trim((string)($input['buyer_email'] ?? '')));
    if ($buyerName === '') {
        return ['ok' => false, 'message' => 'Buyer name is required.'];
    }
    if ($buyerEmail !== '' && filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'message' => 'Buyer email is invalid.'];
    }
    $destinationCountry = strtoupper(trim((string)($input['destination_country_code'] ?? '')));
    $customerCountries = admin_billing_customer_countries('en');
    if ($destinationCountry !== '' && !isset($customerCountries[$destinationCountry])) {
        return ['ok' => false, 'message' => 'Choose a supported buyer destination country.'];
    }
    $buyerAddress = trim((string)($input['buyer_address_line1'] ?? ''));
    $buyerPostalCode = trim((string)($input['buyer_postal_code'] ?? ''));
    $buyerCity = trim((string)($input['buyer_city'] ?? ''));
    if ($issueNow && ($buyerAddress === '' || $buyerPostalCode === '' || $buyerCity === '' || $destinationCountry === '')) {
        return ['ok' => false, 'message' => 'Complete the buyer address, postal code, city and country before issuing.'];
    }
    $issueDate = admin_billing_valid_date($input['issue_date'] ?? '');
    $serviceDate = admin_billing_valid_date($input['service_date'] ?? '', true);
    $dueDate = admin_billing_valid_date($input['due_date'] ?? '', true);
    if ($issueDate === null || (($input['service_date'] ?? '') !== '' && $serviceDate === null) || (($input['due_date'] ?? '') !== '' && $dueDate === null)) {
        return ['ok' => false, 'message' => 'Check issue, service and due dates.'];
    }
    if ($issueNow && $serviceDate === null) {
        return ['ok' => false, 'message' => 'Enter the supply/service date before issuing.'];
    }
    $language = strtolower(trim((string)($input['language_code'] ?? 'pl')));
    if (!isset(admin_billing_supported_languages()[$language])) {
        $language = 'en';
    }
    if ($issueNow && !$sourceRecord && ($dueDate === null || $dueDate < $issueDate)) {
        return ['ok' => false, 'message' => 'An unpaid independent document needs a due date on or after its issue date.'];
    }
    $currency = strtoupper(trim((string)($input['currency_code'] ?? $profile['default_currency_code'] ?? 'EUR')));
    if (!isset(admin_billing_supported_currencies()[$currency])) {
        return ['ok' => false, 'message' => 'Unsupported document currency.'];
    }
    $priceEntryMode = strtolower(trim((string)($input['price_entry_mode'] ?? 'net')));
    $priceEntryMode = $priceEntryMode === 'gross' ? 'gross' : 'net';
    $supplyType = strtolower(trim((string)($input['supply_type'] ?? 'digital_service')));
    if (!isset(admin_billing_supply_types()[$supplyType])) {
        $supplyType = 'digital_service';
    }
    $buyerType = strtolower(trim((string)($input['buyer_type'] ?? 'consumer')));
    if (!in_array($buyerType, ['consumer', 'business'], true)) {
        $buyerType = 'consumer';
    }
    $region = admin_billing_transaction_region((string)($profile['country_code'] ?? ''), $destinationCountry);
    $treatment = strtolower(trim((string)($input['tax_treatment'] ?? 'domestic_vat')));
    if (!isset(admin_billing_tax_treatments()[$treatment])) {
        $treatment = 'manual';
    }
    $taxNote = trim((string)($input['tax_treatment_note'] ?? ''));
    if ($taxNote === '') {
        $taxNote = admin_billing_default_tax_note($treatment, $language, (string)($profile['country_code'] ?? ''));
    }
    if ($issueNow) {
        $taxContextError = admin_billing_validate_tax_context(
            $supplyType,
            $buyerType,
            $region,
            $treatment,
            $destinationCountry,
            (string)($profile['vat_status'] ?? 'vat_registered'),
            (string)($profile['business_activity'] ?? 'general'),
            !empty($profile['eu_oss_enabled']),
            !empty($profile['medical_exemption_enabled']),
            !empty($profile['margin_scheme_enabled']),
            (string)($profile['country_code'] ?? '')
        );
        if ($taxContextError !== '') {
            return ['ok' => false, 'message' => $taxContextError];
        }
    }
    $itemsResult = admin_billing_normalize_items($input);
    if (empty($itemsResult['ok'])) {
        return $itemsResult;
    }
    $items = (array)$itemsResult['items'];
    $zeroVatTreatments = [
        'small_business_exempt', 'eu_b2b_reverse_charge', 'construction_reverse_charge', 'medical_exempt_entity',
        'medical_exempt_professional', 'export_outside_eu',
    ];
    if (in_array($treatment, $zeroVatTreatments, true)) {
        foreach ($items as $item) {
            if ((float)($item['tax_rate_percent'] ?? 0) !== 0.0) {
                return ['ok' => false, 'message' => 'The selected tax treatment requires a 0% VAT rate on every item.'];
            }
        }
    }
    if ($issueNow && $treatment !== 'domestic_vat' && trim($taxNote) === '') {
        return ['ok' => false, 'message' => 'Add the tax note required for the selected treatment before issuing.'];
    }
    if ($issueNow && in_array($treatment, ['domestic_vat', 'eu_b2c_destination_oss', 'eu_distance_goods_oss', 'margin_scheme_second_hand'], true)) {
        $rateCountry = in_array($treatment, ['eu_b2c_destination_oss', 'eu_distance_goods_oss'], true)
            ? $destinationCountry
            : strtoupper((string)($profile['country_code'] ?? ''));
        $activeRates = [];
        foreach (admin_billing_tax_rows($db, $rateCountry) as $rateRow) {
            if (!empty($rateRow['is_active'])) {
                $activeRates[] = round((float)($rateRow['rate_percent'] ?? 0), 2);
            }
        }
        foreach ($items as $item) {
            $itemRate = round((float)($item['tax_rate_percent'] ?? 0), 2);
            if (!in_array($itemRate, $activeRates, true)) {
                return ['ok' => false, 'message' => 'Every item VAT rate must match an active rate for the applicable country.'];
            }
        }
    }
    $subtotal = round(array_sum(array_column($items, 'line_net')), 2);
    $taxTotal = round(array_sum(array_column($items, 'line_tax')), 2);
    $gross = round($subtotal + $taxTotal, 2);
    if (!is_finite($gross) || $gross > 9999999999.99 || $subtotal > 9999999999.99 || $taxTotal > 9999999999.99) return ['ok'=>false,'message'=>'Document total exceeds the supported limit.'];

    $buyerTaxId = trim((string)($input['buyer_tax_id'] ?? ''));
    $buyerVatIdVerified = !empty($input['buyer_vat_id_verified']);
    if ($issueNow && in_array($treatment, ['eu_b2b_reverse_charge', 'construction_reverse_charge'], true)
        && ($buyerTaxId === '' || !$buyerVatIdVerified)) {
        return ['ok' => false, 'message' => 'EU B2B reverse charge requires the buyer VAT ID and confirmation that it was verified.'];
    }

    $sourceAmount = null;
    $sourceCurrency = null;
    $sourcePaidAt = null;
    $sourceMismatch = false;
    if ($sourceRecord) {
        $sourceAmount = round((float)($sourceRecord[$sourceType === 'order' ? 'total_amount' : 'amount'] ?? 0), 2);
        $sourceCurrency = strtoupper(trim((string)($sourceRecord['currency_code'] ?? '')));
        $sourcePaidAt = trim((string)($sourceRecord['paid_at'] ?? '')) ?: null;
        $sourceMismatch = $sourceCurrency !== $currency || abs($sourceAmount - $gross) > 0.02;
        if ($issueNow && $sourceMismatch && empty($input['source_total_override_confirmed'])) {
            return ['ok' => false, 'message' => 'The document total differs from the selected paid source. Confirm the deliberate difference before issuing.'];
        }
    }

    $foreignCurrencyReviewRequired = $taxTotal > 0.0
        && $currency !== strtoupper((string)($profile['default_currency_code'] ?? $currency));
    if ($issueNow && $foreignCurrencyReviewRequired && empty($input['foreign_currency_review_confirmed'])) {
        return ['ok' => false, 'message' => 'Confirm the local-currency VAT reporting for this foreign-currency document before issuing.'];
    }
    $plKsefReviewRequired = strtoupper((string)($profile['country_code'] ?? '')) === 'PL' && $buyerType === 'business';
    $plKsefReviewConfirmed = $plKsefReviewRequired && !empty($input['pl_ksef_review_confirmed']);
    if ($issueNow && $plKsefReviewRequired
        && trim((string)($input['external_reference'] ?? '')) === ''
        && !$plKsefReviewConfirmed) {
        return ['ok' => false, 'message' => 'For a Polish business buyer, enter the KSeF/accounting reference or confirm the applicable KSeF workflow before issuing.'];
    }

    if ($issueNow && empty($input['tax_review_confirmed'])) {
        return ['ok' => false, 'message' => 'Confirm the VAT treatment and document data before issuing.'];
    }
    $paymentNotes = trim((string)($input['payment_notes'] ?? ''));
    if ($paymentNotes === '' && $sourceRecord) {
        $paymentNotes = admin_billing_default_payment_note($sourceType, $language);
    }

    $db->start();
    $lockedProfile = $db->select_user("SELECT * FROM billing_company_profiles WHERE id = {$profileId} FOR UPDATE");
    $profileFields = ['is_active','country_code','business_activity','vat_status','eu_oss_enabled','medical_exemption_enabled','margin_scheme_enabled','company_name','legal_name','address_line1','address_line2','postal_code','city','vat_id','default_currency_code','number_prefix'];
    foreach ($profileFields as $field) {
        if (!$lockedProfile || (string)($lockedProfile[$field] ?? '') !== (string)($profile[$field] ?? '')) {
            $db->query('ROLLBACK');
            return ['ok'=>false,'message'=>'Issuer profile changed. Reload the form and review its details.'];
        }
    }
    if ($documentId > 0) {
        $lockedDocument = $db->select_user("SELECT status FROM billing_documents WHERE id = {$documentId} FOR UPDATE");
        if (!$lockedDocument || $lockedDocument['status'] !== 'draft') {
            $db->query('ROLLBACK');
            return ['ok'=>false, 'message'=>'Only draft documents can be edited.'];
        }
    }
    if (!empty($input['save_billing_customer'])) {
        $customerInput = $input;
        $customerInput['linked_customer_id'] = $customerId;
        $saveCustomerResult = admin_billing_save_customer($db, $customerInput, $adminUserId, $billingCustomerId);
        if (empty($saveCustomerResult['ok'])) {
            $db->query('ROLLBACK');
            return $saveCustomerResult;
        }
        $billingCustomerId = (int)($saveCustomerResult['billing_customer_id'] ?? 0);
    }

    $number = null;
    $status = 'draft';
    $issuedAt = null;
    $paidAt = null;
    if ($issueNow) {
        $number = admin_billing_allocate_number($db, $profile, $issueDate);
        if ($number === null) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to allocate a document number.'];
        }
        $status = $sourceRecord ? 'paid' : 'issued';
        $issuedAt = date('Y-m-d H:i:s');
        $paidAt = $sourceRecord ? ($sourcePaidAt ?: $issuedAt) : null;
    }
    $decisionSnapshot = json_encode([
        'seller_country' => strtoupper((string)($profile['country_code'] ?? '')),
        'destination_country' => $destinationCountry,
        'derived_region' => $region,
        'supply_type' => $supplyType,
        'buyer_type' => $buyerType,
        'tax_treatment' => $treatment,
        'vat_status' => (string)($profile['vat_status'] ?? ''),
        'business_activity' => (string)($profile['business_activity'] ?? ''),
        'eu_oss_enabled' => !empty($profile['eu_oss_enabled']),
        'medical_exemption_enabled' => !empty($profile['medical_exemption_enabled']),
        'margin_scheme_enabled' => !empty($profile['margin_scheme_enabled']),
        'buyer_vat_id_verified' => $buyerVatIdVerified,
        'source_total_mismatch' => $sourceMismatch,
        'foreign_currency_review_confirmed' => !empty($input['foreign_currency_review_confirmed']),
        'pl_ksef_review_confirmed' => $plKsefReviewConfirmed,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $fields = [
        'document_number', 'status', 'source_type', 'source_unique_key', 'company_profile_id', 'billing_customer_id', 'customer_id', 'order_id', 'payment_type', 'payment_id',
        'country_code', 'currency_code', 'price_entry_mode', 'language_code', 'supply_type', 'buyer_type', 'destination_country_code',
        'transaction_region', 'tax_treatment', 'tax_treatment_note', 'issue_date', 'service_date', 'due_date', 'seller_snapshot',
        'buyer_name', 'buyer_company_name', 'buyer_address_line1', 'buyer_address_line2', 'buyer_postal_code', 'buyer_city', 'buyer_country',
        'buyer_tax_id', 'buyer_email', 'buyer_phone', 'subtotal_net', 'tax_total', 'total_gross', 'notes', 'payment_notes',
        'source_amount', 'source_currency_code', 'source_paid_at', 'buyer_vat_id_verified_at', 'source_total_override_confirmed_at',
        'external_reference', 'tax_decision_snapshot',
        'tax_review_confirmed_at', 'tax_review_confirmed_by_admin_user_id', 'issued_at',
        'paid_at', 'updated_by_admin_user_id',
    ];
    $values = [
        $number, $status, $sourceType, $sourceUniqueKey, $profileId, $billingCustomerId ?: null, $customerId ?: null, $orderId, $paymentType, $paymentId,
        strtoupper((string)($profile['country_code'] ?? 'IE')), $currency, $priceEntryMode, $language, $supplyType, $buyerType,
        $destinationCountry ?: null, $region, $treatment, $taxNote,
        $issueDate, $serviceDate, $dueDate, admin_billing_seller_snapshot($profile), $buyerName, trim((string)($input['buyer_company_name'] ?? '')) ?: null,
        $buyerAddress ?: null, trim((string)($input['buyer_address_line2'] ?? '')) ?: null,
        $buyerPostalCode ?: null, $buyerCity ?: null,
        (string)(admin_billing_customer_countries($language)[$destinationCountry]['name'] ?? $destinationCountry) ?: null, $buyerTaxId ?: null,
        $buyerEmail ?: null, trim((string)($input['buyer_phone'] ?? '')) ?: null, $subtotal, $taxTotal, $gross, trim((string)($input['notes'] ?? '')) ?: null,
        $paymentNotes ?: null,
        $sourceAmount, $sourceCurrency ?: null, $sourcePaidAt, $issueNow && $buyerVatIdVerified ? date('Y-m-d H:i:s') : null,
        $issueNow && $sourceMismatch && !empty($input['source_total_override_confirmed']) ? date('Y-m-d H:i:s') : null,
        trim((string)($input['external_reference'] ?? '')) ?: null, $decisionSnapshot ?: null,
        $issueNow ? date('Y-m-d H:i:s') : null, $issueNow ? ($adminUserId ?: null) : null, $issuedAt, $paidAt, $adminUserId ?: null,
    ];
    if ($documentId > 0) {
        $ok = $db->update_using_id($fields, $values, 'billing_documents', $documentId);
        $deletedItems = $db->query("DELETE FROM billing_document_items WHERE document_id = {$documentId}");
        $ok = $ok && $deletedItems;
    } else {
        $fields[] = 'created_by_admin_user_id';
        $values[] = $adminUserId ?: null;
        $ok = $db->insert($fields, $values, 'billing_documents');
        $documentId = (int)$db->id();
    }
    if (!$ok || $documentId <= 0) {
        $db->query('ROLLBACK');
        return ['ok' => false, 'message' => 'Unable to save billing document.'];
    }
    foreach ($items as $index => $item) {
        $itemOk = $db->insert(
            ['document_id', 'description', 'quantity', 'unit_label', 'unit_price_net', 'purchase_price_gross', 'tax_rate_percent', 'line_purchase_gross', 'line_margin_gross', 'line_net', 'line_tax', 'line_gross', 'sort_order'],
            [$documentId, $item['description'], $item['quantity'], $item['unit_label'], $item['unit_price_net'], $item['purchase_price_gross'], $item['tax_rate_percent'], $item['line_purchase_gross'], $item['line_margin_gross'], $item['line_net'], $item['line_tax'], $item['line_gross'], ($index + 1) * 10],
            'billing_document_items'
        );
        if (!$itemOk) {
            $db->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Unable to save billing document items.'];
        }
        $catalogDescription = $db->escape((string)$item['description']);
        $catalogUnitGross = (float)$item['quantity'] > 0 ? round((float)$item['line_gross'] / (float)$item['quantity'], 4) : 0.0;
        $catalogPurchase = $item['purchase_price_gross'] === null ? 'NULL' : (string)(float)$item['purchase_price_gross'];
        $db->query("INSERT INTO billing_item_catalog (company_profile_id, description, unit_label, default_unit_price_net, default_unit_price_gross, default_purchase_price_gross, default_tax_rate_percent, supply_type, usage_count, last_used_at, is_active)
            VALUES ({$profileId}, '{$catalogDescription}', '" . $db->escape((string)$item['unit_label']) . "', " . (float)$item['unit_price_net'] . ", {$catalogUnitGross}, {$catalogPurchase}, " . (float)$item['tax_rate_percent'] . ", '" . $db->escape($supplyType) . "', 1, NOW(), 1)
            ON DUPLICATE KEY UPDATE unit_label = VALUES(unit_label), default_unit_price_net = VALUES(default_unit_price_net), default_unit_price_gross = VALUES(default_unit_price_gross), default_purchase_price_gross = VALUES(default_purchase_price_gross), default_tax_rate_percent = VALUES(default_tax_rate_percent), supply_type = VALUES(supply_type), usage_count = usage_count + 1, last_used_at = NOW(), is_active = 1");
    }
    if (!$db->query('COMMIT')) { $db->query('ROLLBACK'); return ['ok'=>false,'message'=>'Unable to save billing document.']; }
    return [
        'ok' => true,
        'message' => $issueNow ? ($status === 'paid' ? 'Billing document has been issued and marked as paid from its source.' : 'Billing document has been issued.') : 'Draft has been saved.',
        'document_id' => $documentId,
        'company_profile_id' => $profileId,
        'status' => $status,
    ];
}

function admin_billing_record_automation_event(
    Mysql_ks $db,
    string $sourceKey,
    string $triggerType,
    int $orderId,
    int $customerId,
    string $status,
    string $message,
    int $documentId = 0,
    string $paymentType = '',
    int $paymentId = 0
): void {
    if (!schema_object_exists($db, 'billing_automation_events')) {
        return;
    }
    $completedAt = in_array($status, ['sent', 'already_sent'], true) ? date('Y-m-d H:i:s') : null;
    $db->query("INSERT INTO billing_automation_events
        (source_key, trigger_type, order_id, payment_type, payment_id, customer_id, document_id, status, attempt_count, message, last_attempt_at, completed_at)
        VALUES ('" . $db->escape($sourceKey) . "', '" . $db->escape($triggerType) . "', " . ($orderId > 0 ? $orderId : 'NULL') . ", "
            . ($paymentType !== '' ? "'" . $db->escape($paymentType) . "'" : 'NULL') . ", " . ($paymentId > 0 ? $paymentId : 'NULL') . ", "
            . ($customerId > 0 ? $customerId : 'NULL') . ", " . ($documentId > 0 ? $documentId : 'NULL') . ", '" . $db->escape($status) . "', 1, "
            . ($message !== '' ? "'" . $db->escape(mb_substr($message, 0, 1000)) . "'" : 'NULL') . ", NOW(), " . ($completedAt !== null ? "'{$completedAt}'" : 'NULL') . ")
        ON DUPLICATE KEY UPDATE trigger_type = VALUES(trigger_type), order_id = VALUES(order_id), payment_type = VALUES(payment_type),
            payment_id = VALUES(payment_id), customer_id = VALUES(customer_id), document_id = VALUES(document_id), status = VALUES(status),
            attempt_count = attempt_count + 1, message = VALUES(message), last_attempt_at = NOW(), completed_at = VALUES(completed_at)");
}

function admin_billing_default_active_tax_rate(Mysql_ks $db, string $countryCode): ?float
{
    $countryCode = strtoupper(trim($countryCode));
    foreach (admin_billing_tax_rows($db, $countryCode) as $row) {
        if (!empty($row['is_active']) && !empty($row['is_default'])) {
            return round((float)($row['rate_percent'] ?? 0), 2);
        }
    }
    return null;
}

function admin_billing_latest_unbilled_order_source(Mysql_ks $db, int $orderId): ?array
{
    foreach (admin_billing_payment_sources($db, 500) as $payment) {
        if ((int)($payment['order_id'] ?? 0) !== $orderId) {
            continue;
        }
        $sourceType = (string)($payment['payment_type'] ?? '') === 'crypto' ? 'payment_crypto' : 'payment_bank';
        $paymentId = (int)($payment['id'] ?? 0);
        $key = admin_billing_source_unique_key($sourceType, $paymentId, $payment);
        $duplicate = $key !== null
            ? $db->select_user("SELECT id FROM billing_documents WHERE source_unique_key = '" . $db->escape($key) . "' AND status <> 'cancelled' LIMIT 1")
            : null;
        if (!is_array($duplicate)) {
            return ['type' => $sourceType, 'id' => $paymentId, 'record' => $payment, 'key' => (string)$key];
        }
    }

    $order = admin_billing_source_record($db, 'order', $orderId);
    if (!$order) {
        return null;
    }
    $key = 'order:' . $orderId;
    $duplicate = $db->select_user("SELECT id FROM billing_documents WHERE source_unique_key = '" . $db->escape($key) . "' AND status <> 'cancelled' LIMIT 1");
    return is_array($duplicate) ? null : ['type' => 'order', 'id' => $orderId, 'record' => $order, 'key' => $key];
}

function admin_billing_issue_and_email_for_order(Mysql_ks $db, int $orderId, int $adminUserId = 0): array
{
    admin_billing_ensure_runtime_schema($db);
    $settings = admin_app_settings($db);
    if (!admin_billing_auto_issue_email_enabled($settings)) {
        return ['ok' => false, 'skipped' => true, 'message' => 'Automatic billing is disabled.'];
    }

    $order = admin_order_find($db, $orderId);
    if (!is_array($order) || empty($order['id'])) {
        return ['ok' => false, 'skipped' => true, 'message' => 'Order not found.'];
    }
    $customerId = (int)($order['customer_id'] ?? 0);
    $source = admin_billing_latest_unbilled_order_source($db, $orderId);
    if (!$source) {
        return ['ok' => true, 'skipped' => true, 'message' => 'No new paid source is available for this order.'];
    }
    $sourceKey = (string)$source['key'];
    $sourceRecord = (array)$source['record'];
    $paymentType = str_starts_with((string)$source['type'], 'payment_') ? (string)($sourceRecord['payment_type'] ?? '') : '';
    $paymentId = $paymentType !== '' ? (int)$source['id'] : 0;

    $customerContext = admin_billing_customer_auto_delivery_context($db, $customerId);
    if (empty($customerContext['ready'])) {
        $message = (string)($customerContext['message'] ?? 'Billing customer data is incomplete.');
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'needs_customer_data', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'skipped' => true, 'message' => $message];
    }
    $billingCustomer = (array)$customerContext['billing_customer'];
    if (trim((string)($billingCustomer['tax_id'] ?? '')) !== '' || trim((string)($billingCustomer['company_name'] ?? '')) !== '') {
        $message = 'Business buyers require manual invoice review, including VAT ID and KSeF/VAT workflow where applicable.';
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'needs_tax_review', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'skipped' => true, 'message' => $message];
    }

    $profileId = max(0, (int)($settings['billing_auto_company_profile_id'] ?? 0));
    $profile = admin_billing_company_profile($db, $profileId);
    $profileReadiness = admin_billing_profile_readiness($db, $profile);
    if (empty($profileReadiness['ready'])) {
        $message = 'Automatic issuer profile is not ready: ' . implode(' ', (array)$profileReadiness['blockers']);
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'needs_profile_data', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'skipped' => true, 'message' => $message];
    }

    $currency = strtoupper(trim((string)($sourceRecord['currency_code'] ?? '')));
    if ($currency === '' || $currency !== strtoupper(trim((string)($profile['default_currency_code'] ?? '')))) {
        $message = 'Automatic issuing is blocked because the paid-source currency differs from the issuer profile currency.';
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'needs_tax_review', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'skipped' => true, 'message' => $message];
    }

    $destinationCountry = strtoupper(trim((string)($billingCustomer['country_code'] ?? '')));
    $region = admin_billing_transaction_region((string)($profile['country_code'] ?? ''), $destinationCountry);
    $supplyType = strtolower(trim((string)($settings['billing_auto_supply_type'] ?? 'digital_service')));
    if (!isset(admin_billing_supply_types()[$supplyType])) {
        $supplyType = 'digital_service';
    }
    $allowedTreatments = admin_billing_allowed_tax_treatments(
        $supplyType,
        'consumer',
        $region,
        (string)($profile['vat_status'] ?? 'vat_registered'),
        (string)($profile['business_activity'] ?? 'general'),
        !empty($profile['eu_oss_enabled']),
        false,
        false,
        (string)($profile['country_code'] ?? '')
    );
    $treatment = (string)(array_values(array_filter($allowedTreatments, static fn(string $value): bool => $value !== 'manual'))[0] ?? '');
    if ($treatment === '') {
        $message = 'The VAT treatment is not deterministic for this customer and sale type. Issue the document manually.';
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'needs_tax_review', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'skipped' => true, 'message' => $message];
    }

    $zeroRateTreatments = ['small_business_exempt', 'eu_b2b_reverse_charge', 'construction_reverse_charge', 'medical_exempt_entity', 'medical_exempt_professional', 'export_outside_eu'];
    $rateCountry = in_array($treatment, ['eu_b2c_destination_oss', 'eu_distance_goods_oss'], true)
        ? $destinationCountry
        : strtoupper((string)($profile['country_code'] ?? ''));
    $taxRate = in_array($treatment, $zeroRateTreatments, true) ? 0.0 : admin_billing_default_active_tax_rate($db, $rateCountry);
    if ($taxRate === null) {
        $message = 'No active default VAT rate is configured for the applicable country.';
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'needs_tax_review', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'skipped' => true, 'message' => $message];
    }

    $amount = round((float)($sourceRecord[(string)$source['type'] === 'order' ? 'total_amount' : 'amount'] ?? 0), 2);
    if ($amount <= 0) {
        return ['ok' => false, 'skipped' => true, 'message' => 'The paid amount is invalid.'];
    }
    $customer = $db->select_user("SELECT locale_code FROM customers WHERE id = {$customerId} LIMIT 1");
    $language = admin_billing_email_language((string)($customer['locale_code'] ?? admin_billing_country_language((string)($profile['country_code'] ?? ''))));
    $paidAt = trim((string)($sourceRecord['paid_at'] ?? ''));
    $documentDate = $paidAt !== '' && strtotime($paidAt) !== false ? date('Y-m-d', strtotime($paidAt)) : date('Y-m-d');
    $description = trim((string)($sourceRecord['description'] ?? $sourceRecord['product_name'] ?? ''));
    if ($description === '') {
        $description = 'Order #' . $orderId;
    }

    $input = [
        'company_profile_id' => (int)$profile['id'],
        'source_type' => (string)$source['type'],
        'source_record_id' => (int)$source['id'],
        'billing_customer_id' => (int)$billingCustomer['id'],
        'linked_customer_id' => $customerId,
        'buyer_name' => (string)$billingCustomer['display_name'],
        'buyer_company_name' => (string)($billingCustomer['company_name'] ?? ''),
        'buyer_email' => (string)$billingCustomer['email'],
        'buyer_phone' => (string)($billingCustomer['phone'] ?? ''),
        'buyer_address_line1' => (string)$billingCustomer['address_line1'],
        'buyer_address_line2' => (string)($billingCustomer['address_line2'] ?? ''),
        'buyer_postal_code' => (string)$billingCustomer['postal_code'],
        'buyer_city' => (string)$billingCustomer['city'],
        'destination_country_code' => $destinationCountry,
        'buyer_tax_id' => '',
        'buyer_type' => 'consumer',
        'issue_date' => $documentDate,
        'service_date' => $documentDate,
        'due_date' => $documentDate,
        'language_code' => $language,
        'currency_code' => $currency,
        'price_entry_mode' => 'gross',
        'supply_type' => $supplyType,
        'tax_treatment' => $treatment,
        'tax_treatment_note' => admin_billing_default_tax_note($treatment, $language, (string)($profile['country_code'] ?? '')),
        'tax_review_confirmed' => 1,
        'item_description' => [$description],
        'item_quantity' => ['1'],
        'item_unit' => [(string)(admin_billing_units($language)['service'] ?? 'service')],
        'item_unit_price' => [number_format($amount, 2, '.', '')],
        'item_purchase_price' => [''],
        'item_tax_rate' => [number_format((float)$taxRate, 2, '.', '')],
        'payment_notes' => admin_billing_default_payment_note((string)$source['type'], $language),
        'external_reference' => 'AUTO-ORDER-' . $orderId,
    ];

    $save = admin_billing_save_document($db, $input, $adminUserId, $profile, true);
    if (empty($save['ok']) || empty($save['document_id'])) {
        $message = (string)($save['message'] ?? 'Unable to issue the billing document.');
        admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, 'issue_failed', $message, 0, $paymentType, $paymentId);
        return ['ok' => false, 'message' => $message];
    }

    $documentId = (int)$save['document_id'];
    $document = admin_billing_document_row($db, $documentId);
    $send = $document ? admin_billing_send_document_email($db, $document, $adminUserId, true) : ['ok' => false, 'message' => 'Issued document could not be loaded.'];
    $status = !empty($send['ok']) ? 'sent' : 'email_failed';
    $message = (string)($send['message'] ?? 'Unable to email the billing document.');
    admin_billing_record_automation_event($db, $sourceKey, 'order_approved', $orderId, $customerId, $status, $message, $documentId, $paymentType, $paymentId);
    return ['ok' => !empty($send['ok']), 'message' => $message, 'document_id' => $documentId];
}

function admin_billing_email_language(string $language): string
{
    $language = strtolower(trim($language));
    return in_array($language, ['pl', 'en', 'de'], true) ? $language : 'en';
}

function admin_billing_email_text(string $language): array
{
    $language = admin_billing_email_language($language);
    $copy = [
        'pl' => [
            'subject' => 'Faktura %s od %s',
            'heading' => 'Faktura %s',
            'intro' => 'W załączniku przesyłamy dokument PDF wystawiony przez %s.',
            'number' => 'Numer dokumentu',
            'issue_date' => 'Data wystawienia',
            'amount' => 'Kwota brutto',
            'payment' => 'Status płatności',
            'paid' => 'Opłacono',
            'issued' => 'Do zapłaty',
            'attachment' => 'Załączony PDF jest kopią dokumentu zapisanego w systemie. Dane płatności, w tym przelew bankowy lub płatność kryptowalutowa, znajdują się na dokumencie.',
            'regards' => 'Pozdrawiamy',
        ],
        'de' => [
            'subject' => 'Rechnung %s von %s',
            'heading' => 'Rechnung %s',
            'intro' => 'Im Anhang senden wir Ihnen das von %s ausgestellte PDF-Dokument.',
            'number' => 'Rechnungsnummer',
            'issue_date' => 'Rechnungsdatum',
            'amount' => 'Bruttobetrag',
            'payment' => 'Zahlungsstatus',
            'paid' => 'Bezahlt',
            'issued' => 'Offen',
            'attachment' => 'Die angehängte PDF-Datei ist eine Kopie des im System gespeicherten Dokuments. Zahlungsangaben, einschließlich Banküberweisung oder Kryptowährungszahlung, sind im Dokument enthalten.',
            'regards' => 'Freundliche Grüße',
        ],
        'en' => [
            'subject' => 'Invoice %s from %s',
            'heading' => 'Invoice %s',
            'intro' => 'Please find attached the PDF document issued by %s.',
            'number' => 'Document number',
            'issue_date' => 'Issue date',
            'amount' => 'Gross amount',
            'payment' => 'Payment status',
            'paid' => 'Paid',
            'issued' => 'Outstanding',
            'attachment' => 'The attached PDF is a copy of the document stored in the system. Payment information, including a bank transfer or cryptocurrency payment, is recorded on the document.',
            'regards' => 'Regards',
        ],
    ];
    return $copy[$language];
}

function admin_billing_document_email_body(array $document, array $settings): array
{
    $language = admin_billing_email_language((string)($document['language_code'] ?? 'en'));
    $text = admin_billing_email_text($language);
    $seller = json_decode((string)($document['seller_snapshot'] ?? '{}'), true);
    $seller = is_array($seller) ? $seller : [];
    $sellerName = trim((string)($seller['company_name'] ?? $seller['legal_name'] ?? ''));
    if ($sellerName === '') {
        $sellerName = trim((string)($settings['site_name'] ?? $settings['page_name'] ?? '')) ?: 'Company';
    }
    $number = trim((string)($document['document_number'] ?? '')) ?: '#' . (int)($document['id'] ?? 0);
    $currency = strtoupper(trim((string)($document['currency_code'] ?? 'EUR')));
    $decimal = $language === 'en' ? '.' : ',';
    $thousands = $language === 'en' ? ',' : ' ';
    $amount = number_format((float)($document['total_gross'] ?? 0), 2, $decimal, $thousands) . ' ' . $currency;
    $statusText = (string)($document['status'] ?? '') === 'paid' ? $text['paid'] : $text['issued'];
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $body = '<h3 style="margin:0 0 18px;font-size:21px;line-height:1.3;color:#111827;">'
        . $escape(sprintf($text['heading'], $number)) . '</h3>'
        . '<p style="margin:0 0 20px;">' . $escape(sprintf($text['intro'], $sellerName)) . '</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;margin:0 0 20px;">'
        . '<tr><td style="padding:8px 0;color:#6b7280;border-bottom:1px solid #eef2f7;">' . $escape($text['number']) . '</td><td align="right" style="padding:8px 0;font-weight:700;border-bottom:1px solid #eef2f7;">' . $escape($number) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6b7280;border-bottom:1px solid #eef2f7;">' . $escape($text['issue_date']) . '</td><td align="right" style="padding:8px 0;font-weight:700;border-bottom:1px solid #eef2f7;">' . $escape($document['issue_date'] ?? '') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6b7280;border-bottom:1px solid #eef2f7;">' . $escape($text['amount']) . '</td><td align="right" style="padding:8px 0;font-weight:700;border-bottom:1px solid #eef2f7;">' . $escape($amount) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6b7280;">' . $escape($text['payment']) . '</td><td align="right" style="padding:8px 0;font-weight:700;">' . $escape($statusText) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 22px;color:#4b5563;">' . $escape($text['attachment']) . '</p>'
        . '<p style="margin:0;">' . $escape($text['regards']) . '<br><strong>' . $escape($sellerName) . '</strong></p>';

    return [
        'language' => $language,
        'subject' => sprintf($text['subject'], $number, $sellerName),
        'body' => $body,
    ];
}

function admin_billing_send_document_email(Mysql_ks $db, array $document, int $adminUserId, bool $recipientAcceptanceConfirmed): array
{
    $lock = admin_billing_operation_lock($db, 'document:' . (int)($document['id'] ?? 0));
    if ($lock === null) return ['ok'=>false, 'message'=>'This document is being processed. Wait a moment and try again.'];
    try {
        $fresh = admin_billing_document_row($db, (int)($document['id'] ?? 0));
        if (!$fresh) return ['ok'=>false, 'message'=>'Billing document not found.'];
        $document = $fresh;
        return admin_billing_send_document_email_locked($db, $document, $adminUserId, $recipientAcceptanceConfirmed);
    } finally { admin_billing_operation_unlock($db, $lock); }
}

function admin_billing_send_document_email_locked(
    Mysql_ks $db,
    array $document,
    int $adminUserId,
    bool $recipientAcceptanceConfirmed
): array {
    $documentId = (int)($document['id'] ?? 0);
    $status = strtolower(trim((string)($document['status'] ?? '')));
    $recipient = strtolower(trim((string)($document['buyer_email'] ?? '')));
    if (!in_array($status, ['issued', 'paid'], true)) {
        return ['ok' => false, 'message' => 'Only issued or paid documents can be emailed.'];
    }
    if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'message' => 'Add a valid buyer email before sending the document.'];
    }
    if (!$recipientAcceptanceConfirmed) {
        return ['ok' => false, 'message' => 'Confirm that the recipient accepts electronic delivery.'];
    }
    if (!schema_object_exists($db, 'billing_document_deliveries')) {
        return ['ok' => false, 'message' => 'The billing email delivery log is unavailable.'];
    }

    $safeRecipient = $db->escape($recipient);
    $recent = $db->select_user("SELECT id FROM billing_document_deliveries
        WHERE document_id = {$documentId}
          AND recipient_email = '{$safeRecipient}'
          AND status IN ('processing','sent')
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ORDER BY id DESC LIMIT 1");
    if (is_array($recent) && !empty($recent['id'])) {
        return ['ok' => false, 'message' => 'This document was just sent. Wait a moment before sending it again.'];
    }

    $language = admin_billing_email_language((string)($document['language_code'] ?? 'en'));
    $requestIp = function_exists('admin_request_ip') ? admin_request_ip() : trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $inserted = $db->insert(
        ['document_id', 'recipient_email', 'language_code', 'delivery_channel', 'status', 'recipient_acceptance_confirmed_at', 'confirmed_by_admin_user_id', 'request_ip'],
        [$documentId, $recipient, $language, 'email', 'processing', date('Y-m-d H:i:s'), $adminUserId ?: null, $requestIp !== '' ? $requestIp : null],
        'billing_document_deliveries'
    );
    $deliveryId = $inserted ? (int)$db->id() : 0;
    if ($deliveryId <= 0) {
        return ['ok' => false, 'message' => 'Unable to start document delivery.'];
    }

    try {
        $document = admin_billing_document_with_profile_logo($db, $document);
        $pdf = admin_billing_document_pdf_bytes($document);
        $filename = admin_billing_document_pdf_filename($document);
        $hash = hash('sha256', $pdf);
        $settings = app_fetch_settings($db);
        $email = admin_billing_document_email_body($document, $settings);
        $db->update_using_id(
            ['attachment_filename', 'attachment_sha256', 'attachment_size_bytes'],
            [$filename, $hash, strlen($pdf)],
            'billing_document_deliveries',
            $deliveryId
        );
        $send = app_email_send_with_attachment(
            $settings,
            $recipient,
            (string)$email['subject'],
            (string)$email['body'],
            $pdf,
            $filename,
            'application/pdf',
            (string)$email['language']
        );
        if (empty($send['ok'])) {
            throw new RuntimeException(trim((string)($send['message'] ?? 'Email delivery failed.')));
        }
        $db->update_using_id(
            ['status', 'smtp_message_id', 'sent_at', 'error_message'],
            ['sent', trim((string)($send['message_id'] ?? '')) ?: null, date('Y-m-d H:i:s'), null],
            'billing_document_deliveries',
            $deliveryId
        );
        return ['ok' => true, 'message' => 'Billing document has been emailed.', 'delivery_id' => $deliveryId];
    } catch (Throwable $exception) {
        $error = mb_substr(trim($exception->getMessage()), 0, 1000);
        $db->update_using_id(
            ['status', 'error_message'],
            ['failed', $error !== '' ? $error : 'Unknown delivery error.'],
            'billing_document_deliveries',
            $deliveryId
        );
        return ['ok' => false, 'message' => 'Unable to email the billing document: ' . ($error !== '' ? $error : 'Unknown error.')];
    }
}

function admin_billing_apply_document_action(Mysql_ks $db, int $documentId, string $action, int $adminUserId, bool $recipientAcceptanceConfirmed = false): array
{
    $lock = admin_billing_operation_lock($db, 'document:' . $documentId);
    if ($lock === null) return ['ok'=>false, 'message'=>'This document is being processed. Wait a moment and try again.'];
    try {
        return admin_billing_apply_document_action_locked($db, $documentId, $action, $adminUserId, $recipientAcceptanceConfirmed);
    } finally { admin_billing_operation_unlock($db, $lock); }
}

function admin_billing_apply_document_action_locked(
    Mysql_ks $db,
    int $documentId,
    string $action,
    int $adminUserId,
    bool $recipientAcceptanceConfirmed = false
): array
{
    $document = admin_billing_document_row($db, $documentId);
    if (!$document) {
        return ['ok' => false, 'message' => 'Billing document not found.'];
    }
    if ($action === 'send_email') {
        return admin_billing_send_document_email_locked(
            $db,
            $document,
            $adminUserId,
            $recipientAcceptanceConfirmed
        );
    }
    $status = (string)($document['status'] ?? '');
    if ($action === 'issue' && $status === 'draft') {
        return ['ok' => false, 'message' => 'Open the draft, review VAT and issue it from the document form.'];
    }
    if (in_array($action, ['mark_paid', 'paid'], true) && $status === 'issued') {
        $ok = $db->update_using_id(['status', 'paid_at', 'updated_by_admin_user_id'], ['paid', date('Y-m-d H:i:s'), $adminUserId ?: null], 'billing_documents', $documentId);
        return ['ok' => (bool)$ok, 'message' => $ok ? 'Document marked as paid.' : 'Unable to update the document.'];
    }
    if ($action === 'cancel' && $status === 'issued') {
        $submissionKey = str_starts_with((string)($document['source_unique_key'] ?? ''), 'manual:') ? (string)$document['source_unique_key'] : null;
        $ok = $db->update_using_id(['status', 'source_unique_key', 'cancelled_at', 'updated_by_admin_user_id'], ['cancelled', $submissionKey, date('Y-m-d H:i:s'), $adminUserId ?: null], 'billing_documents', $documentId);
        return ['ok' => (bool)$ok, 'message' => $ok ? 'Document has been cancelled.' : 'Unable to cancel the document.'];
    }
    if ($action === 'delete' && $status === 'draft') {
        $ok = $db->delete_using_id('billing_documents', $documentId);
        return ['ok' => (bool)$ok, 'message' => $ok ? 'Draft has been deleted.' : 'Unable to delete draft.'];
    }
    return ['ok' => false, 'message' => 'This action is not available for the current document status.'];
}

function admin_billing_stats(Mysql_ks $db): array
{
    $profiles = [];
    foreach (admin_billing_company_profiles($db, false) as $profile) {
        $profileId = (int)($profile['id'] ?? 0);
        if ($profileId <= 0) {
            continue;
        }
        $currency = strtoupper(trim((string)($profile['default_currency_code'] ?? 'EUR'))) ?: 'EUR';
        $profiles[$profileId] = [
            'profile' => $profile,
            'currencies' => [
                $currency => [
                    'summary' => [],
                    'monthly' => [],
                    'sources' => [],
                    'tax_treatments' => [],
                    'customers' => [],
                ],
            ],
        ];
    }

    $summaryRows = $db->select_full_user("SELECT company_profile_id, currency_code,
        COUNT(*) AS documents_total,
        SUM(status = 'draft') AS drafts_total,
        SUM(status = 'issued') AS issued_total,
        SUM(status = 'paid') AS paid_total,
        SUM(status = 'cancelled') AS cancelled_total,
        SUM(status IN ('issued','paid')) AS issued_count,
        COALESCE(SUM(CASE WHEN status IN ('issued','paid') THEN subtotal_net ELSE 0 END),0) AS net_total,
        COALESCE(SUM(CASE WHEN status IN ('issued','paid') THEN tax_total ELSE 0 END),0) AS tax_total,
        COALESCE(SUM(CASE WHEN status IN ('issued','paid') THEN total_gross ELSE 0 END),0) AS gross_total,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_gross ELSE 0 END),0) AS paid_gross,
        COALESCE(SUM(CASE WHEN status = 'issued' THEN total_gross ELSE 0 END),0) AS outstanding_total,
        SUM(status = 'issued' AND due_date IS NOT NULL AND due_date < CURDATE()) AS overdue_count,
        COALESCE(SUM(CASE WHEN status = 'issued' AND due_date IS NOT NULL AND due_date < CURDATE() THEN total_gross ELSE 0 END),0) AS overdue_total,
        COALESCE(SUM(CASE WHEN status IN ('issued','paid') AND YEAR(issue_date) = YEAR(CURDATE()) THEN total_gross ELSE 0 END),0) AS year_total,
        COALESCE(SUM(CASE WHEN status IN ('issued','paid') AND DATE_FORMAT(issue_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN total_gross ELSE 0 END),0) AS month_total,
        COALESCE(SUM(CASE WHEN status IN ('issued','paid') AND DATE_FORMAT(issue_date,'%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m') THEN total_gross ELSE 0 END),0) AS previous_month_total
        FROM billing_documents
        WHERE company_profile_id IS NOT NULL
        GROUP BY company_profile_id, currency_code
        ORDER BY company_profile_id, currency_code");

    $monthlyRows = $db->select_full_user("SELECT company_profile_id, currency_code,
        DATE_FORMAT(issue_date, '%Y-%m') AS month_key,
        COUNT(*) AS documents_total,
        SUM(status = 'paid') AS paid_total,
        COALESCE(SUM(total_gross),0) AS gross_total,
        COALESCE(SUM(tax_total),0) AS tax_total,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_gross ELSE 0 END),0) AS paid_gross
        FROM billing_documents
        WHERE company_profile_id IS NOT NULL
          AND status IN ('issued','paid')
          AND issue_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
        GROUP BY company_profile_id, currency_code, DATE_FORMAT(issue_date, '%Y-%m')
        ORDER BY company_profile_id, currency_code, month_key ASC");

    $sourceRows = $db->select_full_user("SELECT company_profile_id, currency_code, source_type,
        COUNT(*) AS documents_total,
        COALESCE(SUM(total_gross),0) AS gross_total
        FROM billing_documents
        WHERE company_profile_id IS NOT NULL AND status IN ('issued','paid')
        GROUP BY company_profile_id, currency_code, source_type
        ORDER BY company_profile_id, currency_code, gross_total DESC");

    $taxRows = $db->select_full_user("SELECT company_profile_id, currency_code, tax_treatment,
        COUNT(*) AS documents_total,
        COALESCE(SUM(subtotal_net),0) AS net_total,
        COALESCE(SUM(tax_total),0) AS tax_total,
        COALESCE(SUM(total_gross),0) AS gross_total
        FROM billing_documents
        WHERE company_profile_id IS NOT NULL AND status IN ('issued','paid')
        GROUP BY company_profile_id, currency_code, tax_treatment
        ORDER BY company_profile_id, currency_code, gross_total DESC");

    $customerRows = $db->select_full_user("SELECT company_profile_id, currency_code,
        COALESCE(NULLIF(buyer_company_name,''), buyer_name) AS customer_name,
        buyer_email,
        COUNT(*) AS documents_total,
        COALESCE(SUM(total_gross),0) AS gross_total
        FROM billing_documents
        WHERE company_profile_id IS NOT NULL AND status IN ('issued','paid')
        GROUP BY company_profile_id, currency_code, COALESCE(NULLIF(buyer_company_name,''), buyer_name), buyer_email
        ORDER BY company_profile_id, currency_code, gross_total DESC");

    $ensureBucket = static function (array &$allProfiles, int $profileId, string $currency): bool {
        if (!isset($allProfiles[$profileId])) {
            return false;
        }
        if (!isset($allProfiles[$profileId]['currencies'][$currency])) {
            $allProfiles[$profileId]['currencies'][$currency] = [
                'summary' => [], 'monthly' => [], 'sources' => [], 'tax_treatments' => [], 'customers' => [],
            ];
        }
        return true;
    };

    foreach ($summaryRows as $row) {
        $profileId = (int)($row['company_profile_id'] ?? 0);
        $currency = strtoupper((string)($row['currency_code'] ?? 'EUR'));
        if ($ensureBucket($profiles, $profileId, $currency)) {
            $profiles[$profileId]['currencies'][$currency]['summary'] = $row;
        }
    }
    foreach ($monthlyRows as $row) {
        $profileId = (int)($row['company_profile_id'] ?? 0);
        $currency = strtoupper((string)($row['currency_code'] ?? 'EUR'));
        if ($ensureBucket($profiles, $profileId, $currency)) {
            $profiles[$profileId]['currencies'][$currency]['monthly'][] = $row;
        }
    }
    foreach ($sourceRows as $row) {
        $profileId = (int)($row['company_profile_id'] ?? 0);
        $currency = strtoupper((string)($row['currency_code'] ?? 'EUR'));
        if ($ensureBucket($profiles, $profileId, $currency)) {
            $profiles[$profileId]['currencies'][$currency]['sources'][] = $row;
        }
    }
    foreach ($taxRows as $row) {
        $profileId = (int)($row['company_profile_id'] ?? 0);
        $currency = strtoupper((string)($row['currency_code'] ?? 'EUR'));
        if ($ensureBucket($profiles, $profileId, $currency)) {
            $profiles[$profileId]['currencies'][$currency]['tax_treatments'][] = $row;
        }
    }
    foreach ($customerRows as $row) {
        $profileId = (int)($row['company_profile_id'] ?? 0);
        $currency = strtoupper((string)($row['currency_code'] ?? 'EUR'));
        if ($ensureBucket($profiles, $profileId, $currency) && count($profiles[$profileId]['currencies'][$currency]['customers']) < 5) {
            $profiles[$profileId]['currencies'][$currency]['customers'][] = $row;
        }
    }

    $overall = ['profiles_total' => count($profiles), 'active_profiles' => 0, 'documents_total' => 0, 'currencies' => []];
    foreach ($profiles as $profileStats) {
        if (!empty($profileStats['profile']['is_active'])) {
            $overall['active_profiles']++;
        }
        foreach ($profileStats['currencies'] as $currency => $currencyStats) {
            $summary = (array)($currencyStats['summary'] ?? []);
            $overall['documents_total'] += (int)($summary['documents_total'] ?? 0);
            if (!isset($overall['currencies'][$currency])) {
                $overall['currencies'][$currency] = ['gross_total' => 0.0, 'paid_gross' => 0.0, 'outstanding_total' => 0.0, 'tax_total' => 0.0];
            }
            foreach (['gross_total', 'paid_gross', 'outstanding_total', 'tax_total'] as $field) {
                $overall['currencies'][$currency][$field] += (float)($summary[$field] ?? 0);
            }
        }
    }

    return ['overall' => $overall, 'profiles' => array_values($profiles)];
}

function admin_billing_localize_message(string $message, string $locale): string
{
    if ($message === '') {
        return $message;
    }
    if ($locale !== 'pl') {
        if (str_starts_with($message, 'Issuer profile is not ready: ')) {
            return strtr($message, [
                'Uzupełnij: nazwa firmy.' => 'Complete: company name.',
                'Uzupełnij: adres.' => 'Complete: address.',
                'Uzupełnij: kod pocztowy.' => 'Complete: postal code.',
                'Uzupełnij: miasto.' => 'Complete: city.',
                'Wybierz obsługiwany kraj profilu: PL, AT albo IE.' => 'Select a supported issuer country: PL, AT or IE.',
                'Dla czynnego podatnika VAT wpisz VAT ID / UID.' => 'Enter a VAT ID / UID for a VAT-registered issuer.',
                'Ustaw jedną aktywną domyślną stawkę VAT dla kraju profilu.' => 'Set one active default VAT rate for the issuer country.',
            ]);
        }
        return $message;
    }
    $translations = [
        'Logo must be a JPG, PNG or WEBP file up to 2 MB.' => 'Logo musi być plikiem JPG, PNG albo WEBP o rozmiarze do 2 MB.',
        'Unsupported logo format.' => 'Nieobsługiwany format logo.',
        'Unable to create the billing upload directory.' => 'Nie udało się utworzyć katalogu na logo rachunków.',
        'Unable to save the company logo.' => 'Nie udało się zapisać logo firmy.',
        'Choose Austria, Poland or Ireland.' => 'Wybierz Austrię, Polskę albo Irlandię.',
        'Profile label, company name, address, postal code and city are required.' => 'Uzupełnij nazwę profilu, nazwę firmy, adres, kod pocztowy i miasto.',
        'Company email is invalid.' => 'Adres email firmy jest nieprawidłowy.',
        'Document number prefix is invalid.' => 'Format numeracji dokumentów jest nieprawidłowy.',
        'The default issuer profile must remain active.' => 'Domyślny profil wystawcy musi pozostać aktywny.',
        'Unable to save company billing data.' => 'Nie udało się zapisać danych wystawcy.',
        'Company billing data has been saved.' => 'Dane wystawcy zostały zapisane.',
        'Tax profile data is invalid.' => 'Dane stawki VAT są nieprawidłowe.',
        'The default VAT rate must remain active.' => 'Domyślna stawka VAT musi pozostać aktywna.',
        'Tax rate has been saved.' => 'Stawka VAT została zapisana.',
        'Unable to save tax rate.' => 'Nie udało się zapisać stawki VAT.',
        'Catalog item data is invalid.' => 'Dane pozycji katalogowej są nieprawidłowe.',
        'Catalog item has been saved.' => 'Pozycja katalogowa została zapisana.',
        'Unable to save catalog item.' => 'Nie udało się zapisać pozycji katalogowej.',
        'Customer name is required.' => 'Nazwa klienta jest wymagana.',
        'Customer email is invalid.' => 'Adres email klienta jest nieprawidłowy.',
        'Selected service user does not exist.' => 'Wybrany użytkownik serwisu nie istnieje.',
        'Selected customer country is invalid.' => 'Wybrany kraj klienta jest nieprawidłowy.',
        'Billing customer has been saved.' => 'Klient rozliczeniowy został zapisany.',
        'Unable to save billing customer.' => 'Nie udało się zapisać klienta rozliczeniowego.',
        'Check quantity, price and VAT rate for every item.' => 'Sprawdź ilość, cenę i stawkę VAT każdej pozycji.',
        'For the margin scheme, use gross prices, a positive VAT rate and a purchase price no higher than the sale price for every item.' => 'Dla procedury marży wybierz ceny brutto, dodatnią stawkę VAT i wpisz dla każdej pozycji cenę nabycia nie wyższą od ceny sprzedaży.',
        'Add at least one billing item.' => 'Dodaj co najmniej jedną pozycję dokumentu.',
        'Select an issuer profile before creating a billing document.' => 'Wybierz profil wystawcy przed utworzeniem dokumentu.',
        'Issuer profile changed. Reload the form and review its details.' => 'Dane wystawcy zmieniły się. Odśwież formularz i sprawdź je ponownie.',
        'This document is being processed. Wait a moment and try again.' => 'Dokument jest właśnie przetwarzany. Poczekaj chwilę i spróbuj ponownie.',
        'Invalid document submission. Reload the form.' => 'Sesja formularza jest nieprawidłowa. Odśwież formularz.',
        'Use no more than 200 billing items.' => 'Dokument może zawierać najwyżej 200 pozycji.',
        'Billing item amount exceeds the supported limit.' => 'Kwota pozycji przekracza obsługiwany limit.',
        'Document total exceeds the supported limit.' => 'Suma dokumentu przekracza obsługiwany limit.',
        'Only draft documents can be edited.' => 'Edytować można wyłącznie dokumenty robocze.',
        'Selected order or approved payment is no longer available.' => 'Wybrane zamówienie lub zatwierdzona płatność nie są już dostępne.',
        'A billing document already exists for this source.' => 'Dla tej płatności lub zamówienia istnieje już dokument rozliczeniowy.',
        'Selected billing customer does not exist.' => 'Wybrany klient rozliczeniowy nie istnieje.',
        'The billing customer is linked to a different service account than the selected source.' => 'Klient rozliczeniowy jest powiązany z innym kontem niż wybrana płatność lub zamówienie.',
        'Buyer name is required.' => 'Nazwa nabywcy jest wymagana.',
        'Buyer email is invalid.' => 'Adres email nabywcy jest nieprawidłowy.',
        'Choose a supported buyer destination country.' => 'Wybierz kraj nabywcy z listy.',
        'Complete the buyer address, postal code, city and country before issuing.' => 'Przed wystawieniem uzupełnij adres, kod pocztowy, miasto i kraj nabywcy.',
        'Check issue, service and due dates.' => 'Sprawdź datę wystawienia, sprzedaży i termin płatności.',
        'Enter the supply/service date before issuing.' => 'Przed wystawieniem wpisz datę sprzedaży lub wykonania usługi.',
        'An unpaid independent document needs a due date on or after its issue date.' => 'Nieopłacony dokument niezależny wymaga terminu płatności nie wcześniejszego niż data wystawienia.',
        'Unsupported document currency.' => 'Waluta dokumentu nie jest obsługiwana.',
        'The selected tax treatment does not match the issuer VAT profile, supply type, market and buyer type.' => 'Wybrany sposób opodatkowania nie pasuje do profilu VAT wystawcy, rodzaju sprzedaży, rynku i typu nabywcy.',
        'The selected tax treatment requires a 0% VAT rate on every item.' => 'Wybrany sposób opodatkowania wymaga stawki 0% w każdej pozycji.',
        'Add the tax note required for the selected treatment before issuing.' => 'Przed wystawieniem dodaj adnotację wymaganą dla wybranego sposobu opodatkowania.',
        'Every item VAT rate must match an active rate for the applicable country.' => 'Stawka VAT każdej pozycji musi odpowiadać aktywnej stawce dla właściwego kraju.',
        'EU B2B reverse charge requires the buyer VAT ID and confirmation that it was verified.' => 'Odwrotne obciążenie B2B w UE wymaga VAT ID nabywcy oraz potwierdzenia jego weryfikacji.',
        'The document total differs from the selected paid source. Confirm the deliberate difference before issuing.' => 'Suma dokumentu różni się od opłaconego źródła. Potwierdź świadomą różnicę przed wystawieniem.',
        'Confirm the local-currency VAT reporting for this foreign-currency document before issuing.' => 'Potwierdź rozliczenie VAT w walucie krajowej dla dokumentu w obcej walucie.',
        'For a Polish business buyer, enter the KSeF/accounting reference or confirm the applicable KSeF workflow before issuing.' => 'Dla polskiego nabywcy firmowego wpisz referencję KSeF/księgową albo potwierdź obsługę właściwego procesu KSeF.',
        'Confirm the VAT treatment and document data before issuing.' => 'Potwierdź sposób opodatkowania i dane dokumentu przed wystawieniem.',
        'Unable to allocate a document number.' => 'Nie udało się nadać numeru dokumentu.',
        'Unable to save billing document.' => 'Nie udało się zapisać dokumentu.',
        'Unable to save billing document items.' => 'Nie udało się zapisać pozycji dokumentu.',
        'Billing document has been issued and marked as paid from its source.' => 'Dokument został wystawiony i oznaczony jako opłacony na podstawie źródła.',
        'Billing document has been issued.' => 'Dokument został wystawiony.',
        'Draft has been saved.' => 'Wersja robocza została zapisana.',
        'Billing document not found.' => 'Nie znaleziono dokumentu.',
        'Open the draft, review VAT and issue it from the document form.' => 'Otwórz wersję roboczą, sprawdź VAT i wystaw dokument z formularza.',
        'Document marked as paid.' => 'Dokument został oznaczony jako opłacony.',
        'Unable to update the document.' => 'Nie udało się zaktualizować dokumentu.',
        'Document has been cancelled.' => 'Dokument został anulowany.',
        'Unable to cancel the document.' => 'Nie udało się anulować dokumentu.',
        'Draft has been deleted.' => 'Wersja robocza została usunięta.',
        'Unable to delete draft.' => 'Nie udało się usunąć wersji roboczej.',
        'This action is not available for the current document status.' => 'Ta akcja nie jest dostępna dla bieżącego statusu dokumentu.',
        'Billing document has been saved.' => 'Dokument został zapisany.',
        'Billing document has been emailed.' => 'Dokument został wysłany emailem.',
        'Only issued or paid documents can be emailed.' => 'Emailem można wysłać wyłącznie wystawiony albo opłacony dokument.',
        'Add a valid buyer email before sending the document.' => 'Przed wysłaniem dodaj prawidłowy email nabywcy.',
        'Confirm that the recipient accepts electronic delivery.' => 'Potwierdź, że odbiorca akceptuje elektroniczne doręczenie dokumentu.',
        'The billing email delivery log is unavailable.' => 'Dziennik wysyłki dokumentów jest niedostępny.',
        'This document was just sent. Wait a moment before sending it again.' => 'Ten dokument został przed chwilą wysłany. Odczekaj moment przed ponowną wysyłką.',
        'Unable to start document delivery.' => 'Nie udało się rozpocząć wysyłki dokumentu.',
    ];
    if (isset($translations[$message])) {
        return $translations[$message];
    }
    $emailErrorPrefix = 'Unable to email the billing document: ';
    if (str_starts_with($message, $emailErrorPrefix)) {
        return 'Nie udało się wysłać dokumentu emailem: ' . substr($message, strlen($emailErrorPrefix));
    }
    $prefix = 'Issuer profile is not ready: ';
    if (str_starts_with($message, $prefix)) {
        return 'Profil wystawcy nie jest gotowy: ' . substr($message, strlen($prefix));
    }
    return $message;
}

function admin_billing_localize_readiness(array $readiness, string $locale): array
{
    if ($locale === 'pl') {
        return $readiness;
    }
    $translations = [
        'Wybierz profil wystawcy.' => 'Select an issuer profile.',
        'Profil wystawcy jest wyłączony.' => 'The issuer profile is disabled.',
        'Wybierz obsługiwany kraj profilu: PL, AT albo IE.' => 'Select a supported issuer country: PL, AT or IE.',
        'Dla czynnego podatnika VAT wpisz VAT ID / UID.' => 'Enter a VAT ID / UID for a VAT-registered issuer.',
        'Status VAT profilu jest ustawiany ręcznie. Każdy dokument wymaga uważnego wyboru wariantu.' => 'The profile VAT status is manual. Review the treatment carefully on every document.',
        'OSS jest wyłączony; sprzedaż B2C do innych krajów UE pozostanie w trybie ręcznym.' => 'OSS is disabled; EU cross-border B2C sales will remain in manual mode.',
        'Zwolnienie medyczne jest wyłączone i nie będzie proponowane.' => 'The medical exemption is disabled and will not be suggested.',
        'Procedura marży jest wyłączona i nie będzie proponowana.' => 'The margin scheme is disabled and will not be suggested.',
        'Ustaw jedną aktywną domyślną stawkę VAT dla kraju profilu.' => 'Set one active default VAT rate for the issuer country.',
        'Format numeracji nie zawiera {YYYY}; numery nie będą rozdzielone według roku.' => 'The numbering format does not contain {YYYY}; numbers will not be separated by year.',
    ];
    $fieldLabels = ['nazwa firmy' => 'company name', 'adres' => 'address', 'kod pocztowy' => 'postal code', 'miasto' => 'city'];
    foreach (['blockers', 'warnings'] as $group) {
        $localized = [];
        foreach ((array)($readiness[$group] ?? []) as $message) {
            $message = (string)$message;
            if (isset($translations[$message])) {
                $localized[] = $translations[$message];
                continue;
            }
            if (preg_match('/^Uzupełnij: (.+)\.$/u', $message, $match)) {
                $localized[] = 'Complete: ' . ($fieldLabels[$match[1]] ?? $match[1]) . '.';
                continue;
            }
            $localized[] = $message;
        }
        $readiness[$group] = $localized;
    }
    return $readiness;
}

function admin_billing_context(Mysql_ks $db, array $adminUser, array $messages, string $locale, array $post, array $files): array
{
    $tab = strtolower(trim((string)($_GET['billing_tab'] ?? 'create')));
    if (!in_array($tab, ['create','documents','customers','settings','stats'], true)) $tab = 'create';
    $alert = '';
    $alertType = 'success';
    $isDocumentPost = isset($post['admin_billing_save_document']) || isset($post['admin_billing_issue_document']);
    $formDocument = $isDocumentPost ? admin_billing_form_document_from_input($post) : null;
    $adminUserId = (int)($adminUser['id'] ?? 0);
    $creatingProfile = !empty($_GET['new_profile']) && strtolower((string)($_GET['billing_tab'] ?? '')) === 'settings';
    $selectedProfileId = max(0, (int)($post['company_profile_id'] ?? $_GET['billing_profile_id'] ?? 0));
    $profile = $creatingProfile ? [] : admin_billing_company_profile($db, $selectedProfileId);
    $selectedProfileId = (int)($profile['id'] ?? 0);

    if ($post) {
        if (!admin_csrf_is_valid($post['_csrf'] ?? '')) {
            $alert = admin_t($messages, 'login_error', 'Invalid request.');
            $alertType = 'danger';
        } elseif (isset($post['admin_billing_save_automation'])) {
            $result = admin_billing_save_automation_settings($db, $post, $adminUser);
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
        } elseif (isset($post['admin_billing_save_company'])) {
            $result = admin_billing_save_company($db, $post, $files, $adminUserId);
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
            if (!empty($result['company_profile_id'])) {
                $selectedProfileId = (int)$result['company_profile_id'];
            }
        } elseif (isset($post['admin_billing_save_tax_rate'])) {
            $result = admin_billing_save_tax_rate($db, $post);
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
        } elseif (isset($post['admin_billing_save_catalog_item'])) {
            $result = admin_billing_save_catalog_item($db, $post);
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
        } elseif (isset($post['admin_billing_save_customer'])) {
            $result = admin_billing_save_customer($db, $post, $adminUserId, max(0, (int)($post['billing_customer_record_id'] ?? 0)));
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
        } elseif (isset($post['admin_billing_save_document']) || isset($post['admin_billing_issue_document'])) {
            $result = admin_billing_save_document($db, $post, $adminUserId, $profile, isset($post['admin_billing_issue_document']));
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
            if (!empty($result['ok']) && !empty($result['document_id'])) {
                header('Location: /admin/?page=billing&billing_tab=documents&billing_saved=1&document_id=' . (int)$result['document_id'] . '&billing_profile_id=' . (int)($result['company_profile_id'] ?? $selectedProfileId));
                exit;
            }
        } elseif (isset($post['admin_billing_document_action'])) {
            $documentAction = (string)($post['billing_action'] ?? '');
            $result = admin_billing_apply_document_action(
                $db,
                max(0, (int)($post['billing_document_id'] ?? 0)),
                $documentAction,
                $adminUserId,
                !empty($post['recipient_acceptance_confirmed'])
            );
            $alert = (string)$result['message'];
            $alertType = !empty($result['ok']) ? 'success' : 'danger';
            if (!empty($result['ok']) && $documentAction === 'send_email') {
                header('Location: /admin/?page=billing&billing_tab=documents&billing_sent=1&document_id=' . max(0, (int)($post['billing_document_id'] ?? 0)) . '&billing_profile_id=' . $selectedProfileId);
                exit;
            }
        }
        if (!empty($result['ok']) && !headers_sent()) {
            $_SESSION['admin_billing_flash'] = (string)($result['message'] ?? '');
            header('Location: /admin/?page=billing&billing_tab=' . rawurlencode($tab) . '&billing_profile_id=' . $selectedProfileId);
            exit;
        }
        $alert = admin_billing_localize_message($alert, $locale);
        $profile = admin_billing_company_profile($db, $selectedProfileId);
    }

    if (!$post && isset($_SESSION['admin_billing_flash'])) {
        $alert = admin_billing_localize_message((string)$_SESSION['admin_billing_flash'], $locale);
        unset($_SESSION['admin_billing_flash']);
    }
    if (isset($_GET['billing_saved'])) {
        $alert = admin_billing_localize_message('Billing document has been saved.', $locale);
        $alertType = 'success';
    }
    if (isset($_GET['billing_sent'])) {
        $alert = admin_billing_localize_message('Billing document has been emailed.', $locale);
        $alertType = 'success';
    }
    $editDocumentId = max(0, (int)($_GET['edit_document'] ?? 0));
    $editDocument = $editDocumentId > 0 ? admin_billing_document_row($db, $editDocumentId) : null;
    if ($editDocument) {
        $selectedProfileId = (int)($editDocument['company_profile_id'] ?? $selectedProfileId);
        $profile = admin_billing_company_profile($db, $selectedProfileId);
    }
    if ($editDocument && (string)($editDocument['status'] ?? '') !== 'draft') {
        $editDocument = null;
        $alert = admin_billing_localize_message('Only draft documents can be edited.', $locale);
        $alertType = 'danger';
    }
    if (!$editDocument && is_array($formDocument)) {
        $editDocument = $formDocument;
        $selectedProfileId = max(0, (int)($formDocument['company_profile_id'] ?? $selectedProfileId));
        $profile = admin_billing_company_profile($db, $selectedProfileId);
    }
    $editCustomerId = max(0, (int)($_GET['edit_billing_customer'] ?? 0));
    $editCustomer = $editCustomerId > 0 ? $db->select_user("SELECT * FROM billing_customers WHERE id = {$editCustomerId} LIMIT 1") : null;
    $linkCustomerId = max(0, (int)($_GET['billing_link_customer_id'] ?? 0));
    if (!is_array($editCustomer) && $linkCustomerId > 0) {
        $linkedCustomer = $db->select_user("SELECT id, email, public_handle, country_code FROM customers WHERE id = {$linkCustomerId} LIMIT 1");
        if (is_array($linkedCustomer) && !empty($linkedCustomer['id'])) {
            $linkedCountryCode = strtoupper(trim((string)($linkedCustomer['country_code'] ?? '')));
            $editCustomer = [
                'customer_id' => (int)$linkedCustomer['id'],
                'display_name' => '',
                'email' => (string)($linkedCustomer['email'] ?? ''),
                'country_code' => $linkedCountryCode,
                'country_name' => (string)(admin_billing_customer_countries('en')[$linkedCountryCode]['name'] ?? ''),
            ];
            $alert = $locale === 'pl'
                ? 'Uzupełnij dane rozliczeniowe klienta przed zapisaniem.'
                : 'Complete the customer billing details before saving.';
            $alertType = 'info';
        }
    }
    $statusFilter = strtolower(trim((string)($_GET['billing_status'] ?? '')));
    $query = trim((string)($_GET['billing_q'] ?? ''));
    $siteSettings = $db->select_user('SELECT site_name FROM app_settings WHERE id = 1 LIMIT 1');
    $serviceCurrencyRow = function_exists('admin_default_currency_row') ? admin_default_currency_row($db) : null;
    $serviceCurrencyCode = strtoupper(trim((string)($serviceCurrencyRow['code'] ?? 'EUR')));
    if (!isset(admin_billing_supported_currencies()[$serviceCurrencyCode])) {
        $serviceCurrencyCode = 'EUR';
    }
    $profileReadiness = admin_billing_localize_readiness(admin_billing_profile_readiness($db, $profile), $locale);
    $automationSettings = admin_app_settings($db);
    $automationEvents = [];

    $orderSources = $tab === 'create' ? admin_billing_order_sources($db) : [];
    $paymentSources = $tab === 'create' ? admin_billing_payment_sources($db) : [];
    if ($tab === 'create' && is_array($editDocument)) {
        $editSourceType = (string)($editDocument['source_type'] ?? 'independent');
        $editSourceId = (int)($editSourceType === 'order' ? ($editDocument['order_id'] ?? 0) : ($editDocument['payment_id'] ?? 0));
        $selectedSource = admin_billing_source_record($db, $editSourceType, $editSourceId);
        if ($selectedSource) {
            if ($editSourceType === 'order') {
                if (!in_array($editSourceId, array_map('intval', array_column($orderSources, 'id')), true)) array_unshift($orderSources, $selectedSource);
            } else {
                $exists = false;
                foreach ($paymentSources as $payment) if ((int)$payment['id'] === $editSourceId && $payment['payment_type'] === $selectedSource['payment_type']) $exists = true;
                if (!$exists) array_unshift($paymentSources, $selectedSource);
            }
        }
    }

    $billingCustomers = in_array($tab, ['create','customers'], true) ? admin_billing_customer_rows($db) : [];
    $siteCustomers = in_array($tab, ['create','customers'], true) ? admin_billing_site_customer_rows($db) : [];
    if ($tab === 'create' && is_array($editDocument)) {
        $selectedCustomerId = (int)($editDocument['customer_id'] ?? 0);
        $selectedBillingCustomerId = (int)($editDocument['billing_customer_id'] ?? 0);
        if ($selectedCustomerId > 0 && !in_array($selectedCustomerId, array_map('intval', array_column($siteCustomers, 'id')), true)) {
            $selectedCustomer = $db->select_user("SELECT id, email, public_handle, country_code, locale_code, status FROM customers WHERE id = {$selectedCustomerId} LIMIT 1");
            if ($selectedCustomer) array_unshift($siteCustomers, $selectedCustomer);
        }
        if ($selectedBillingCustomerId > 0 && !in_array($selectedBillingCustomerId, array_map('intval', array_column($billingCustomers, 'id')), true)) {
            $selectedBillingCustomer = $db->select_user("SELECT bc.*, c.email AS linked_email, c.public_handle AS linked_handle, c.locale_code AS linked_locale_code FROM billing_customers bc LEFT JOIN customers c ON c.id = bc.customer_id WHERE bc.id = {$selectedBillingCustomerId} LIMIT 1");
            if ($selectedBillingCustomer) array_unshift($billingCustomers, $selectedBillingCustomer);
        }
    }

    return [
        'alert' => $alert,
        'alert_type' => $alertType,
        'profile' => $profile,
        'profiles' => admin_billing_company_profiles($db),
        'selected_profile_id' => $selectedProfileId,
        'company_complete' => !empty($profileReadiness['ready']),
        'profile_readiness' => $profileReadiness,
        'tax_rates' => in_array($tab, ['create','settings'], true) ? admin_billing_tax_rows($db) : [],
        'billing_customers' => $billingCustomers,
        'site_customers' => $siteCustomers,
        'orders' => $orderSources,
        'payments' => $paymentSources,
        'used_source_keys' => $tab === 'create' ? admin_billing_used_source_keys($db, $selectedProfileId, (int)($editDocument['id'] ?? 0)) : [],
        'documents' => $tab === 'documents' ? admin_billing_document_rows($db, $statusFilter, $query, 100, $selectedProfileId) : [],
        'stats' => $tab === 'stats' ? admin_billing_stats($db) : [],
        'item_catalog' => in_array($tab, ['create','settings'], true) ? admin_billing_item_catalog_rows($db, $selectedProfileId, '', 100) : [],
        'edit_document' => $editDocument,
        'has_server_form_draft' => is_array($formDocument),
        'edit_customer' => is_array($editCustomer) ? $editCustomer : null,
        'status_filter' => $statusFilter,
        'query' => $query,
        'locale' => $locale,
        'admin_user_id' => $adminUserId,
        'site_name' => trim((string)($siteSettings['site_name'] ?? '')) ?: 'Site',
        'site_default_currency_code' => $serviceCurrencyCode,
        'billing_auto_issue_email_enabled' => admin_billing_auto_issue_email_enabled($automationSettings),
        'billing_auto_company_profile_id' => max(0, (int)($automationSettings['billing_auto_company_profile_id'] ?? 0)),
        'billing_auto_supply_type' => (string)($automationSettings['billing_auto_supply_type'] ?? 'digital_service'),
        'billing_auto_can_manage' => strtolower(trim((string)($adminUser['login_name'] ?? ''))) === 'admin',
        'billing_automation_events' => $automationEvents,
    ];
}

require_once __DIR__ . '/billing_pdf.php';
require_once __DIR__ . '/billing_view.php';
