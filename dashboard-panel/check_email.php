<?php

require __DIR__ . '/config/mysql.php';
require __DIR__ . '/bootstrap/session.php';

app_start_session();

require __DIR__ . '/vendor/autoload.php';

$smarty = new \Smarty\Smarty;

require __DIR__ . '/config/config.php';

if (!isset($_POST['home_email'])) {
    exit;
}

if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
    echo '<div class="alert-box">';
    echo '<p><i class="fa fa-ban red" aria-hidden="true"></i> ' . htmlspecialchars((string)localization_translate($t, 'csrf_invalid'), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</div>';
    exit;
}

$email = trim((string)$_POST['home_email']);

if ($email === '') {
    echo '<div class="alert-box">';
    echo '<p><i class="fa fa-ban red" aria-hidden="true"></i> Enter your email address.</p>';
    echo '</div>';
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<div class="alert-box">';
    echo '<p><i class="fa fa-ban red" aria-hidden="true"></i> Enter a valid email address.</p>';
    echo '</div>';
    exit;
}

$customer = app_find_customer_by_email($db, $email);

if (is_array($customer) && app_customer_is_active($customer)) {
    $_SESSION['home_email'] = $email;
    $_SESSION['home_email_verified_at'] = date('Y-m-d H:i:s');
    $_SESSION['verified_user_id'] = (int)$customer['id'];

    echo '<div data-verify-success="1">';
    echo '<div class="progress"><div class="bar bar-success progress-bar-striped" style="width: 0%; opacity: 1;"></div></div>';
    echo '</div>';
    exit;
}

unset($_SESSION['home_email'], $_SESSION['home_email_verified_at'], $_SESSION['verified_user_id']);

echo '<div class="alert-box">';
echo '<p><i class="fa fa-ban red" aria-hidden="true"></i> Sorry, Email not found.</p>';
echo '<p>Support: <span class="red">' . htmlspecialchars((string)($settings['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></p>';
echo '</div>';
