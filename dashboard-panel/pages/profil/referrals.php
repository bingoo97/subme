<?php

switch ($site) {
    case 'referrals':
        if (!$user['logged']) {
            $smarty->display('no_access.tpl');
            break;
        }

        if (!app_referrals_enabled($settings)) {
            $smarty->assign('alert_error', localization_translate($t, 'referrals_disabled_notice', 'The referral program is currently disabled.'));
            $smarty->display('alert.tpl');
            $smarty->display('no_access.tpl');
            break;
        }

        $referralRows = app_referral_rows($db, (int)$user['id']);
        $referralLink = app_referral_link($settings, (int)$user['id']);

        foreach ($referralRows as &$referralRow) {
            $paidOrdersCount = (int)($referralRow['paid_orders_count'] ?? 0);
            $customerStatus = strtolower(trim((string)($referralRow['customer_status'] ?? '')));

            if ($customerStatus === 'blocked' || $customerStatus === '2') {
                $referralRow['status_label'] = localization_translate($t, 'referrals_status_blocked', 'Blocked');
                $referralRow['status_class'] = 'danger';
            } elseif ($paidOrdersCount > 0) {
                $referralRow['status_label'] = localization_translate($t, 'referrals_status_active', 'Active customer');
                $referralRow['status_class'] = 'success';
            } else {
                $referralRow['status_label'] = localization_translate($t, 'referrals_status_pending', 'Registered');
                $referralRow['status_class'] = 'warning';
            }
        }
        unset($referralRow);

        $smarty->assign('referral_link', $referralLink);
        $smarty->assign('referrals_total', count($referralRows));
        $smarty->assign('referrals_converted_total', count(array_filter($referralRows, static function (array $row): bool {
            return (int)($row['paid_orders_count'] ?? 0) > 0;
        })));
        $smarty->assign('refy', $referralRows);
        $smarty->display('profil/referrals.tpl');
        break;
}

?>
