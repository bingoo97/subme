<?php

if(isset($_GET["wybierz"])){
	
		$id = (int)$_GET['wybierz']; 
		include('config/check_product.php');
		$smarty->display($translate."one_product.tpl");
		
}else{

	//////////////////////////////////////
	
	if($user["logged"]){
	
		$balanceTopupEnabled = false;
		$balanceTopupCryptoAssets = [];
		$balanceTopupActionUrl = '/cryptocurrency';

		if ((int)($settings['active_sale'] ?? 0) === 1 && app_uses_v2_schema($db) && !empty($settings['crypto_payments_enabled'])) {
			$balanceTopupEnabled = true;
			$topupCurrencyCode = strtoupper(trim((string)($reseller['currency_short'] ?? 'USD')));
			if ($topupCurrencyCode === '') {
				$topupCurrencyCode = 'USD';
			}

			$balanceTopupCryptoAssets = app_load_customer_crypto_assets($db, (int)($user['id'] ?? 0), $topupCurrencyCode, is_array($settings ?? null) ? $settings : []);
		}

		$smarty->assign('balance_topup_enabled', $balanceTopupEnabled);
		$smarty->assign('balance_topup_crypto_assets', $balanceTopupCryptoAssets);
		$smarty->assign('balance_topup_action_url', $balanceTopupActionUrl);
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
