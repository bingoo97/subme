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
    $disabledMessage = app_customer_product_type($user) === 'credits'
        ? localization_translate($t, 'credits_sales_disabled_notice', 'Credits sales are currently unavailable.')
        : localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.');
    echo '<div class="form-group"><div class="col-lg-6"><p>' . htmlspecialchars($disabledMessage, ENT_QUOTES, 'UTF-8') . '</p></div></div>';
    exit;
}

$trialsEnabled = (int)($settings['active_trials'] ?? 0) === 1;
$productTypeSql = app_product_type_sql($db, $user);
$catalogProductType = app_customer_product_type($user);
$productDescriptionSelect = schema_column_exists($db, 'products', 'description')
    ? 'products.description AS description,'
    : "'' AS description,";
$legacyProductDescriptionSelect = schema_column_exists($db, 'products', 'description')
    ? 'description,'
    : "'' AS description,";

if (app_uses_v2_schema($db)) {
    $products = $db->select_full_user(
        "SELECT
            products.id,
            products.name,
            {$productDescriptionSelect}
            products.duration_hours AS duration,
            products.price_amount AS price,
            products.is_trial AS trial,
            products.product_type,
            currencies.symbol AS currency_symbol
         FROM products
         LEFT JOIN currencies ON currencies.id = products.currency_id
         WHERE products.provider_id = {$providerId}
           AND products.is_active = 1
           AND products.product_type = {$productTypeSql}
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

echo '<div class="form-group" data-products-found="1">';
echo '<div class="col-lg-8">';
echo '<label class="form-label" for="id_product">' . htmlspecialchars((string)localization_translate($t, $catalogProductType === 'credits' ? 'order_add_credits_label' : 'order_add_subscription_label', $catalogProductType === 'credits' ? 'Select credits package' : 'Select subscription'), ENT_QUOTES, 'UTF-8') . '</label>';
echo '<input type="hidden" name="id_product" id="id_product" value="" required>';
echo '<div class="order-product-picker" id="order_product_picker">';

foreach ($products as $product) {
    $productId = (int)$product['id'];
    $productName = htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8');
    $productDescription = trim(strip_tags((string)($product['description'] ?? '')));
    $productDescriptionAttr = htmlspecialchars($productDescription, ENT_QUOTES, 'UTF-8');
    $price = number_format((float)$product['price'], 2, '.', '');
    $durationHours = (int)$product['duration'];
    $isTrial = !empty($product['trial']);
    $productType = strtolower(trim((string)($product['product_type'] ?? 'subscription')));
    $currencySymbol = htmlspecialchars((string)($product['currency_symbol'] ?? $reseller['currency_symbol'] ?? ''), ENT_QUOTES, 'UTF-8');

    if ($productType === 'credits') {
        $durationLabel = localization_translate($t, 'product_type_credits_short', 'Credits');
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
        . ' data-description="' . $productDescriptionAttr . '"'
        . ' onclick="selectProductOption(this)">';
    echo '<span class="order-product-picker__title">' . $productName . '</span>';
    echo '<span class="order-product-picker__meta">';
    echo '<span class="order-product-picker__badge order-product-picker__badge--muted">' . htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="order-product-picker__badge order-product-picker__badge--dark">' . $price . ' ' . $currencySymbol . '</span>';
    echo '</span>';
    echo '</button>';
}

echo '</div>';
echo '<div id="product_description_wrap" style="display:none; margin-top:12px;">';
echo '<div class="order-product-picker__description-title" id="product_description_title"></div>';
echo '<div class="alert alert-info" id="product_description" style="margin-bottom:0; white-space:pre-line;"></div>';
echo '</div>';
echo '</div>';
echo '</div>';
