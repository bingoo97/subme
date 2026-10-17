<?php

declare(strict_types=1);

function admin_billing_pdf_labels(string $language): array
{
    $labels = [
        'pl' => ['document' => 'Faktura', 'draft' => 'WERSJA ROBOCZA', 'reference' => 'Referencja', 'seller' => 'Sprzedawca', 'buyer' => 'Nabywca', 'issue_date' => 'Data wystawienia', 'service_date' => 'Data sprzedaży', 'due_date' => 'Termin płatności', 'description' => 'Nazwa pozycji', 'quantity' => 'Ilość', 'unit' => 'J.m.', 'net_price' => 'Cena netto', 'net' => 'Netto', 'vat' => 'VAT', 'gross' => 'Brutto', 'total' => 'Razem', 'notes' => 'Uwagi', 'payment' => 'Płatność', 'tax_note' => 'Informacja podatkowa', 'tax_id' => 'NIP / UID'],
        'en' => ['document' => 'Invoice', 'draft' => 'DRAFT', 'reference' => 'Reference', 'seller' => 'Seller', 'buyer' => 'Buyer', 'issue_date' => 'Issue date', 'service_date' => 'Supply date', 'due_date' => 'Due date', 'description' => 'Description', 'quantity' => 'Quantity', 'unit' => 'Unit', 'net_price' => 'Net unit price', 'net' => 'Net', 'vat' => 'VAT', 'gross' => 'Gross', 'total' => 'Total', 'notes' => 'Notes', 'payment' => 'Payment', 'tax_note' => 'Tax information', 'tax_id' => 'Tax ID / VAT ID'],
        'de' => ['document' => 'Rechnung', 'draft' => 'ENTWURF', 'reference' => 'Referenz', 'seller' => 'Verkäufer', 'buyer' => 'Kunde', 'issue_date' => 'Rechnungsdatum', 'service_date' => 'Leistungsdatum', 'due_date' => 'Fällig am', 'description' => 'Beschreibung', 'quantity' => 'Menge', 'unit' => 'Einheit', 'net_price' => 'Nettopreis', 'net' => 'Netto', 'vat' => 'USt.', 'gross' => 'Brutto', 'total' => 'Gesamt', 'notes' => 'Hinweise', 'payment' => 'Zahlung', 'tax_note' => 'Steuerhinweis', 'tax_id' => 'UID / Steuernummer'],
    ];
    return $labels[$language] ?? $labels['en'];
}

function admin_billing_pdf_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_billing_pdf_money($value, string $currency): string
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . $currency;
}

function admin_billing_pdf_logo_data_uri(string $logoUrl): string
{
    $logoUrl = trim($logoUrl);
    if ($logoUrl === '' || !str_starts_with($logoUrl, '/uploads/billing/')) {
        return '';
    }
    $root = realpath(app_public_path('uploads/billing'));
    $absolute = realpath(app_public_path(ltrim($logoUrl, '/')));
    if (!$root || !$absolute || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) return '';
    if (!is_file($absolute) || filesize($absolute) > 2 * 1024 * 1024) {
        return '';
    }
    $dimensions = @getimagesize($absolute);
    if (!$dimensions || $dimensions[0] > 4096 || $dimensions[1] > 4096 || $dimensions[0] * $dimensions[1] > 16000000) return '';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($absolute);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        return '';
    }
    $contents = file_get_contents($absolute);
    return $contents === false ? '' : 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function admin_billing_document_pdf_html(array $document): string
{
    $language = strtolower((string)($document['language_code'] ?? 'en'));
    $labels = admin_billing_pdf_labels($language);
    $currency = strtoupper((string)($document['currency_code'] ?? 'EUR'));
    $seller = json_decode((string)($document['seller_snapshot'] ?? '{}'), true);
    $seller = is_array($seller) ? $seller : [];
    $logo = admin_billing_pdf_logo_data_uri((string)($seller['logo_url'] ?? ''));
    $number = trim((string)($document['document_number'] ?? ''));
    $isDraft = (string)($document['status'] ?? '') === 'draft';
    $isMarginScheme = (string)($document['tax_treatment'] ?? '') === 'margin_scheme_second_hand';
    ob_start();
    ?>
<!doctype html><html lang="<?php echo admin_billing_pdf_escape($language); ?>"><head><meta charset="utf-8"><style>
@page{margin:28px 34px 44px}body{margin:0;color:#151515;font-family:DejaVu Sans,sans-serif;font-size:10px;line-height:1.45}.top{width:100%;border-collapse:collapse;margin-bottom:26px}.top td{vertical-align:top}.brand{display:block}.logo{display:block;max-width:145px;max-height:68px;margin-bottom:6px}.brand-name{display:block;font-size:10px;font-weight:700}.title{text-align:right}.title h1{margin:0 0 4px;font-size:22px}.title strong{font-size:13px}.draft{margin-top:7px;color:#b42318;font-weight:700;letter-spacing:.08em}.parties{width:100%;border:0;border-collapse:collapse;margin-bottom:20px}.parties td{width:50%;padding:0 20px 0 0;vertical-align:top;border:0!important;background:transparent}.parties td+td{padding:0 0 0 20px;border:0!important}.eyebrow{display:block;margin-bottom:7px;color:#777;font-size:8px;font-weight:700;text-transform:uppercase}.dates{width:100%;margin-bottom:18px;border-collapse:collapse}.dates td{padding:7px 9px;border:1px solid #e4e4e4}.items{width:100%;border-collapse:collapse}.items th{padding:8px 6px;background:#171717;color:#fff;text-align:right;font-size:8px}.items th:first-child,.items td:first-child{text-align:left}.items tr{page-break-inside:avoid}.items td{padding:8px 6px;border-bottom:1px solid #e5e5e5;text-align:right}.totals{width:45%;margin:15px 0 22px auto;border-collapse:collapse}.totals td{padding:6px 8px;border-bottom:1px solid #e5e5e5}.totals td:last-child{text-align:right;font-weight:700}.totals .grand td{border-top:2px solid #111;border-bottom:0;font-size:13px}.note{margin:12px 0 0;padding:0;border:0;background:transparent}.note strong{display:inline-block;margin-bottom:2px}.footer{position:fixed;bottom:-12px;left:0;right:0;color:#777;font-size:8px;text-align:center}
</style></head><body>
<table class="top"><tr><td><span class="brand"><?php if ($logo !== ''): ?><img class="logo" src="<?php echo admin_billing_pdf_escape($logo); ?>" alt=""><?php endif; ?><strong class="brand-name"><?php echo admin_billing_pdf_escape($seller['company_name'] ?? ''); ?></strong></span></td><td class="title"><h1><?php echo admin_billing_pdf_escape($labels['document']); ?></h1><strong><?php echo admin_billing_pdf_escape($number !== '' ? $number : '#' . (int)($document['id'] ?? 0)); ?></strong><?php if (!empty($document['external_reference'])): ?><div><?php echo admin_billing_pdf_escape($labels['reference'] . ': ' . $document['external_reference']); ?></div><?php endif; ?><?php if ($isDraft): ?><div class="draft"><?php echo admin_billing_pdf_escape($labels['draft']); ?></div><?php endif; ?></td></tr></table>
<table class="parties"><tr><td><span class="eyebrow"><?php echo admin_billing_pdf_escape($labels['seller']); ?></span><strong><?php echo admin_billing_pdf_escape($seller['company_name'] ?? ''); ?></strong><br><?php echo admin_billing_pdf_escape($seller['legal_name'] ?? ''); ?><br><?php echo admin_billing_pdf_escape($seller['address_line1'] ?? ''); ?><br><?php echo admin_billing_pdf_escape(trim((string)($seller['postal_code'] ?? '') . ' ' . (string)($seller['city'] ?? ''))); ?><br><?php echo admin_billing_pdf_escape($seller['country_name'] ?? ''); ?><?php if (!empty($seller['vat_id'])): ?><br><?php echo admin_billing_pdf_escape($labels['tax_id'] . ': ' . $seller['vat_id']); ?><?php endif; ?></td><td><span class="eyebrow"><?php echo admin_billing_pdf_escape($labels['buyer']); ?></span><strong><?php echo admin_billing_pdf_escape($document['buyer_company_name'] ?: ($document['buyer_name'] ?? '')); ?></strong><?php if (!empty($document['buyer_company_name']) && !empty($document['buyer_name'])): ?><br><?php echo admin_billing_pdf_escape($document['buyer_name']); ?><?php endif; ?><br><?php echo admin_billing_pdf_escape($document['buyer_address_line1'] ?? ''); ?><br><?php echo admin_billing_pdf_escape(trim((string)($document['buyer_postal_code'] ?? '') . ' ' . (string)($document['buyer_city'] ?? ''))); ?><br><?php echo admin_billing_pdf_escape($document['buyer_country'] ?? ''); ?><?php if (!empty($document['buyer_tax_id'])): ?><br><?php echo admin_billing_pdf_escape($labels['tax_id'] . ': ' . $document['buyer_tax_id']); ?><?php endif; ?><?php if (!empty($document['buyer_email'])): ?><br><?php echo admin_billing_pdf_escape($document['buyer_email']); ?><?php endif; ?><?php if (!empty($document['buyer_phone'])): ?><br><?php echo admin_billing_pdf_escape($document['buyer_phone']); ?><?php endif; ?></td></tr></table>
<table class="dates"><tr><td><strong><?php echo admin_billing_pdf_escape($labels['issue_date']); ?></strong><br><?php echo admin_billing_pdf_escape($document['issue_date'] ?? ''); ?></td><td><strong><?php echo admin_billing_pdf_escape($labels['service_date']); ?></strong><br><?php echo admin_billing_pdf_escape($document['service_date'] ?? '—'); ?></td><td><strong><?php echo admin_billing_pdf_escape($labels['due_date']); ?></strong><br><?php echo admin_billing_pdf_escape($document['due_date'] ?? '—'); ?></td></tr></table>
<table class="items"><thead><tr><th><?php echo admin_billing_pdf_escape($labels['description']); ?></th><th><?php echo admin_billing_pdf_escape($labels['quantity']); ?></th><th><?php echo admin_billing_pdf_escape($labels['unit']); ?></th><?php if (!$isMarginScheme): ?><th><?php echo admin_billing_pdf_escape($labels['net_price']); ?></th><th><?php echo admin_billing_pdf_escape($labels['vat']); ?></th><?php endif; ?><th><?php echo admin_billing_pdf_escape($isMarginScheme ? $labels['total'] : $labels['gross']); ?></th></tr></thead><tbody><?php foreach ((array)($document['items'] ?? []) as $item): ?><tr><td><?php echo admin_billing_pdf_escape($item['description'] ?? ''); ?></td><td><?php echo admin_billing_pdf_escape(number_format((float)($item['quantity'] ?? 0), 3, ',', ' ')); ?></td><td><?php echo admin_billing_pdf_escape($item['unit_label'] ?? ''); ?></td><?php if (!$isMarginScheme): ?><td><?php echo admin_billing_pdf_escape(admin_billing_pdf_money($item['unit_price_net'] ?? 0, $currency)); ?></td><td><?php echo admin_billing_pdf_escape(number_format((float)($item['tax_rate_percent'] ?? 0), 2, ',', '') . '%'); ?></td><?php endif; ?><td><?php echo admin_billing_pdf_escape(admin_billing_pdf_money($item['line_gross'] ?? 0, $currency)); ?></td></tr><?php endforeach; ?></tbody></table>
<table class="totals"><?php if (!$isMarginScheme): ?><tr><td><?php echo admin_billing_pdf_escape($labels['net']); ?></td><td><?php echo admin_billing_pdf_escape(admin_billing_pdf_money($document['subtotal_net'] ?? 0, $currency)); ?></td></tr><tr><td><?php echo admin_billing_pdf_escape($labels['vat']); ?></td><td><?php echo admin_billing_pdf_escape(admin_billing_pdf_money($document['tax_total'] ?? 0, $currency)); ?></td></tr><?php endif; ?><tr class="grand"><td><?php echo admin_billing_pdf_escape($labels['total']); ?></td><td><?php echo admin_billing_pdf_escape(admin_billing_pdf_money($document['total_gross'] ?? 0, $currency)); ?></td></tr></table>
<?php if (!empty($document['tax_treatment_note'])): ?><div class="note"><strong><?php echo admin_billing_pdf_escape($labels['tax_note']); ?></strong><br><?php echo nl2br(admin_billing_pdf_escape($document['tax_treatment_note'])); ?></div><?php endif; ?><?php if (!empty($document['payment_notes'])): ?><div class="note"><strong><?php echo admin_billing_pdf_escape($labels['payment']); ?></strong><br><?php echo nl2br(admin_billing_pdf_escape($document['payment_notes'])); ?></div><?php endif; ?><?php if (!empty($document['notes'])): ?><div class="note"><strong><?php echo admin_billing_pdf_escape($labels['notes']); ?></strong><br><?php echo nl2br(admin_billing_pdf_escape($document['notes'])); ?></div><?php endif; ?><div class="footer"><?php echo admin_billing_pdf_escape($seller['default_footer'] ?? ''); ?></div>
</body></html><?php
    return (string)ob_get_clean();
}

function admin_billing_document_pdf_filename(array $document): string
{
    $number = trim((string)($document['document_number'] ?? '')) ?: 'draft-' . (int)($document['id'] ?? 0);
    return preg_replace('/[^a-zA-Z0-9._-]+/', '-', $number) . '.pdf';
}

function admin_billing_document_pdf_bytes(array $document): string
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PDF library is not installed.');
    }
    require_once $autoload;
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml(admin_billing_document_pdf_html($document), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
    $pageLabel = match (strtolower((string)($document['language_code'] ?? 'en'))) { 'pl' => 'Strona', 'de' => 'Seite', default => 'Page' };
    $reference = mb_substr(trim((string)($document['document_number'] ?? '')) ?: 'draft-' . (int)($document['id'] ?? 0), 0, 60);
    $canvas->page_text(26, $canvas->get_height() - 16, $reference, $font, 6.5, [.5, .5, .5]);
    $canvas->page_text($canvas->get_width() - 96, $canvas->get_height() - 16, $pageLabel . ' {PAGE_NUM} / {PAGE_COUNT}', $font, 6.5, [.5, .5, .5]);

    $output = $dompdf->output();
    if (!is_string($output) || $output === '') {
        throw new RuntimeException('Unable to generate the PDF document.');
    }

    return $output;
}

function admin_billing_stream_document_pdf(array $document, bool $download = false): void
{
    try {
        $pdf = admin_billing_document_pdf_bytes($document);
    } catch (Throwable $exception) {
        http_response_code(500);
        echo 'Unable to generate the PDF document.';
        exit;
    }

    $filename = admin_billing_document_pdf_filename($document);
    $disposition = $download ? 'attachment' : 'inline';
    header('Cache-Control: private, no-store');
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdf));
    header("Content-Disposition: {$disposition}; filename=\"{$filename}\"; filename*=UTF-8''" . rawurlencode($filename));
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}
