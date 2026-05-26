<?php

require __DIR__ . '/config/mysql.php';
require __DIR__ . '/bootstrap/session.php';

app_start_session();

require __DIR__ . '/vendor/autoload.php';

$smarty = new \Smarty\Smarty;

require __DIR__ . '/config/config.php';

if (empty($user['logged'])) {
    echo '<div class="alert alert-danger">Please login again.</div>';
    exit;
}

$providerId = isset($_POST['id_provider']) ? (int)$_POST['id_provider'] : 0;

if ($providerId <= 0) {
    echo '<div class="form-group"><div class="col-lg-6"><p>Please select a provider.</p></div></div>';
    exit;
}

if ((int)($settings['active_sale'] ?? 0) !== 1) {
    $disabledMessage = localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.');
    echo '<div class="form-group"><div class="col-lg-6"><p>' . htmlspecialchars($disabledMessage, ENT_QUOTES, 'UTF-8') . '</p></div></div>';
    exit;
}

if (!app_customer_sales_enabled($user, $settings)) {
    $disabledMessage = app_customer_product_type($user, $settings) === 'credits'
        ? localization_translate($t, 'credits_sales_disabled_notice', 'Credits sales are currently unavailable.')
        : localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.');
    echo '<div class="form-group"><div class="col-lg-6"><p>' . htmlspecialchars($disabledMessage, ENT_QUOTES, 'UTF-8') . '</p></div></div>';
    exit;
}

$trialsEnabled = (int)($settings['active_trials'] ?? 0) === 1;
$productTypeSql = app_customer_order_catalog_product_type_sql($db, $user, $settings);
$productDescriptionSelect = schema_column_exists($db, 'products', 'description')
    ? 'products.description AS description,'
    : "'' AS description,";
$providerLogoSelect = schema_column_exists($db, 'product_providers', 'logo_url')
    ? 'product_providers.logo_url AS provider_logo_url,'
    : "'' AS provider_logo_url,";
$legacyProductDescriptionSelect = schema_column_exists($db, 'products', 'description')
    ? 'description,'
    : "'' AS description,";

if (app_uses_v2_schema($db)) {
    $products = $db->select_full_user(
        "SELECT
            products.id,
            products.name,
            {$productDescriptionSelect}
            {$providerLogoSelect}
            products.duration_hours AS duration,
            products.price_amount AS price,
            products.is_trial AS trial,
            products.product_type,
            currencies.symbol AS currency_symbol
         FROM products
         INNER JOIN product_providers ON product_providers.id = products.provider_id
         LEFT JOIN currencies ON currencies.id = products.currency_id
         WHERE products.provider_id = {$providerId}
           AND products.is_active = 1
           AND product_providers.is_active = 1
           AND products.product_type IN ({$productTypeSql})
           " . app_customer_provider_visibility_sql($db, (int)$user['id'], 'products.provider_id') . "
           " . ($trialsEnabled ? '' : "AND products.is_trial = 0") . "
         ORDER BY products.duration_hours ASC, products.price_amount ASC, products.id ASC"
    );
} else {
    $tenantId = tenant_current_id($user);
    $products = $db->select_full_user(
        "SELECT
            id,
            name,
            {$legacyProductDescriptionSelect}
            '' AS provider_logo_url,
            duration,
            price,
            trial
         FROM products
         WHERE provider_id = {$providerId}
           AND res_id = {$tenantId}
           AND status = 1
           " . ($trialsEnabled ? '' : "AND trial = 0") . "
         ORDER BY duration ASC, price ASC, id ASC"
    );
}

if (!$products) {
    echo '<div class="form-group"><div class="col-lg-6"><p>No products available for this provider.</p></div></div>';
    exit;
}

$catalogAvailableTypes = [];
foreach ((array)$products as $productRow) {
    $catalogAvailableTypes[] = app_normalize_product_type($productRow['product_type'] ?? 'subscription');
}
$catalogAvailableTypes = array_values(array_unique($catalogAvailableTypes));
$catalogProductType = count($catalogAvailableTypes) > 1
    ? 'mixed'
    : ($catalogAvailableTypes[0] ?? app_customer_order_catalog_mode($user, $settings));

echo '<div class="form-group" data-products-found="1">';
echo '<div class="col-lg-8">';
if ($catalogProductType === 'mixed') {
    $productPickerLabel = localization_translate($t, 'order_add_mixed_label', 'Select product');
} elseif ($catalogProductType === 'product') {
    $productPickerLabel = localization_translate($t, 'order_add_product_label', 'Select product');
} else {
    $productPickerLabel = localization_translate(
        $t,
        $catalogProductType === 'credits'
            ? 'order_add_credits_label'
            : ($catalogProductType === 'product' ? 'order_add_product_label' : 'order_add_subscription_label'),
        $catalogProductType === 'credits'
            ? 'Select credits package'
            : ($catalogProductType === 'product' ? 'Select product' : 'Select subscription')
    );
}
echo '<label class="form-label" for="id_product">' . htmlspecialchars((string)$productPickerLabel, ENT_QUOTES, 'UTF-8') . '</label>';
echo '<input type="hidden" name="id_product" id="id_product" value="" required>';
echo '<div class="order-product-picker" id="order_product_picker">';
$descriptionTemplates = '';

foreach ($products as $product) {
    $productId = (int)$product['id'];
    $productName = htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8');
    $productDescriptionHtml = trim(chat_sanitize_rich_content_html((string)($product['description'] ?? '')));
    $price = number_format((float)$product['price'], 2, '.', '');
    $durationHours = (int)$product['duration'];
    $isTrial = !empty($product['trial']);
    $productType = app_normalize_product_type($product['product_type'] ?? 'subscription');
    $currencySymbol = htmlspecialchars((string)($product['currency_symbol'] ?? $reseller['currency_symbol'] ?? ''), ENT_QUOTES, 'UTF-8');
    $providerLogoRaw = trim((string)($product['provider_logo_url'] ?? ''));
    $providerLogoPath = $providerLogoRaw !== '' ? app_format_logo_path($providerLogoRaw) : '';
    $providerLogoAlt = htmlspecialchars((string)($reseller['page_name'] ?? 'Provider logo'), ENT_QUOTES, 'UTF-8');

    if ($productType === 'credits') {
        $durationLabel = localization_translate($t, 'product_type_credits_short', 'Credits');
    } elseif ($productType === 'product') {
        $durationLabel = localization_translate($t, 'product_type_product_short', 'Product');
    } elseif ($isTrial) {
        $durationLabel = $durationHours . ' Hours Trial';
    } else {
        $days = $durationHours > 0 ? max(1, (int)round($durationHours / 24)) : 0;
        $durationLabel = $days . ' Day' . ($days === 1 ? '' : 's');
    }

    echo '<button type="button" class="order-product-picker__option"'
        . ' data-product-id="' . $productId . '"'
        . ' data-product-title="' . $productName . '"'
        . ' data-product-price="' . htmlspecialchars($price . ' ' . $currencySymbol, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-description-template="#product_description_template_' . $productId . '"'
        . ' onclick="selectProductOption(this)">';
    echo '<span class="order-product-picker__summary">';
    if ($providerLogoPath !== '') {
        echo '<span class="order-product-picker__logo">';
        echo '<img src="' . htmlspecialchars($providerLogoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . $providerLogoAlt . '" loading="lazy">';
        echo '</span>';
    }
    echo '<span class="order-product-picker__title">' . $productName . '</span>';
    echo '</span>';
    echo '<span class="order-product-picker__meta">';
    echo '<span class="order-product-picker__badge order-product-picker__badge--muted">' . htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="order-product-picker__badge order-product-picker__badge--dark">' . $price . ' ' . $currencySymbol . '</span>';
    echo '</span>';
    echo '</button>';

    $descriptionTemplates .= '<template id="product_description_template_' . $productId . '">'
        . $productDescriptionHtml
        . '</template>';
}

echo '</div>';
if ($descriptionTemplates !== '') {
    echo '<div class="order-product-picker__description-templates" hidden>' . $descriptionTemplates . '</div>';
}
echo '<div id="product_description_wrap" style="display:none; margin-top:12px;">';
echo '<div class="order-product-picker__description-title" id="product_description_title"></div>';
echo '<div class="order-product-picker__description" id="product_description"></div>';
echo '</div>';
echo '</div>';
echo '</div>';
