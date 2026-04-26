<?php

declare(strict_types=1);

function localization_supported_locales(): array
{
    return [
        'en' => [
            'code' => 'en',
            'label' => 'English',
            'native_label' => 'English',
            'is_ready' => true,
        ],
        'pl' => [
            'code' => 'pl',
            'label' => 'Polish',
            'native_label' => 'Polski',
            'is_ready' => true,
        ],
    ];
}

function localization_normalize_locale($locale): string
{
    $supportedLocales = localization_supported_locales();
    $locale = strtolower(trim((string)$locale));

    if (isset($supportedLocales[$locale])) {
        return $locale;
    }

    return 'en';
}

function localization_from_legacy_value($value): string
{
    $legacyMap = [
        0 => 'en',
        1 => 'pl',
        2 => 'de',
    ];

    $value = (int)$value;

    return $legacyMap[$value] ?? 'en';
}

function localization_to_legacy_value(string $locale): int
{
    $legacyMap = [
        'en' => 0,
        'pl' => 1,
        'de' => 2,
    ];

    $locale = localization_normalize_locale($locale);

    return $legacyMap[$locale] ?? 0;
}

function localization_is_ready(string $locale): bool
{
    $supportedLocales = localization_supported_locales();
    $locale = localization_normalize_locale($locale);

    return !empty($supportedLocales[$locale]['is_ready']);
}

function localization_load(string $locale, string $applicationRoot): array
{
    $locale = localization_normalize_locale($locale);
    $fallbackFile = rtrim($applicationRoot, DIRECTORY_SEPARATOR) . '/locales/en.php';
    $localeFile = rtrim($applicationRoot, DIRECTORY_SEPARATOR) . '/locales/' . $locale . '.php';

    $fallbackConfig = file_exists($fallbackFile) ? require $fallbackFile : ['messages' => []];
    $localeConfig = file_exists($localeFile) ? require $localeFile : ['messages' => []];

    $fallbackMessages = isset($fallbackConfig['messages']) && is_array($fallbackConfig['messages'])
        ? $fallbackConfig['messages']
        : [];
    $localeMessages = isset($localeConfig['messages']) && is_array($localeConfig['messages'])
        ? $localeConfig['messages']
        : [];

    return [
        'locale' => $locale,
        'messages' => array_replace($fallbackMessages, $localeMessages),
        'supported_locales' => localization_supported_locales(),
    ];
}

function localization_translate(array $messages, string $key, array|string $replacements = []): string
{
    $fallback = $key;
    if (is_string($replacements)) {
        $fallback = $replacements;
        $replacements = [];
    }

    $text = isset($messages[$key]) ? (string)$messages[$key] : $fallback;

    foreach ($replacements as $placeholder => $value) {
        $text = str_replace('{' . $placeholder . '}', (string)$value, $text);
    }

    return $text;
}
