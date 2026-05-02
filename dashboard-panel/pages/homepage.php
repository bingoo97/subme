<?php

if(isset($_GET["wybierz"])){
	
		$id = (int)$_GET['wybierz']; 
		include('config/check_product.php');
		$smarty->display($translate."one_product.tpl");
		
}else{

	//////////////////////////////////////
	$homepageServiceOverviewEnabled = app_page_guidance_enabled(is_array($settings ?? null) ? $settings : []);
	$smarty->assign('homepage_service_overview_enabled', $homepageServiceOverviewEnabled);
	
	if($user["logged"]){
	
		$balanceTopupEnabled = false;
		$balanceTopupCryptoAssets = [];
		$balanceTopupActionUrl = '/cryptocurrency';
		$balanceTopupPendingOrderPayment = null;
		$homepageOnboardingEnabled = false;

		if ((int)($settings['active_sale'] ?? 0) === 1 && app_uses_v2_schema($db) && !empty($settings['crypto_payments_enabled'])) {
			$balanceTopupEnabled = true;
			$topupCurrencyCode = strtoupper(trim((string)($reseller['currency_short'] ?? 'USD')));
			if ($topupCurrencyCode === '') {
				$topupCurrencyCode = 'USD';
			}

			$balanceTopupPendingOrderPayment = app_find_customer_pending_order_payment($db, (int)($user['id'] ?? 0));
			if (!$balanceTopupPendingOrderPayment) {
				$balanceTopupCryptoAssets = app_load_customer_crypto_assets($db, (int)($user['id'] ?? 0), $topupCurrencyCode, is_array($settings ?? null) ? $settings : []);
			}
		}

		if ((int)($settings['active_sale'] ?? 0) === 1) {
			if (app_uses_v2_schema($db)) {
				$safeNow = $db->escape(date('Y-m-d H:i:s'));
				$activeSubscriptionRow = $db->select_user(
					"SELECT COUNT(*) AS total
					 FROM orders
					 LEFT JOIN products ON products.id = orders.product_id
					 WHERE orders.customer_id = '" . (int)$user['id'] . "'
					   AND orders.status = 'active'
					   AND COALESCE(products.product_type, 'subscription') <> 'credits'
					   AND (orders.expires_at IS NULL OR orders.expires_at > '{$safeNow}')
					 LIMIT 1"
				);
			} else {
				$activeSubscriptionRow = $db->select_user(
					"SELECT COUNT(*) AS total
					 FROM products_users
					 WHERE user_id = '" . (int)$user['id'] . "'
					   AND status = '1'
					 LIMIT 1"
				);
			}

			$homepageOnboardingEnabled = (int)($activeSubscriptionRow['total'] ?? 0) === 0;
		}

		$smarty->assign('balance_topup_enabled', $balanceTopupEnabled);
		$smarty->assign('balance_topup_crypto_assets', $balanceTopupCryptoAssets);
		$smarty->assign('balance_topup_action_url', $balanceTopupActionUrl);
		$smarty->assign('balance_topup_pending_order_payment', $balanceTopupPendingOrderPayment);
		$smarty->assign('homepage_onboarding_enabled', $homepageOnboardingEnabled);
		$groupChatCreationState = chat_customer_group_creation_state($db, $user, is_array($settings ?? null) ? $settings : []);
		$smarty->assign('group_chat_pending_invites', chat_customer_can_use_groups($user) ? chat_customer_group_pending_invites($db, (int)$user['id']) : []);
		$smarty->assign('group_chat_can_create', !empty($groupChatCreationState['allowed']));
		$smarty->assign('group_chat_creation_state', $groupChatCreationState);
	
		$smarty->display("homepage.tpl");
		
	}else{
		$smarty->display("home_login.tpl");
	}
}
?>
