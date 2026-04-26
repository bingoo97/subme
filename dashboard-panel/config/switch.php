<?php

	$page_title = '';
	$appInstructionRoutes = ['instruction-smart-iptv', 'instruction-ott-player', 'instruction-newlook'];
	if ($site === 'apps' && !app_apps_page_enabled($settings)) {
		header('Location: /');
		exit;
	}

	if (in_array($site, $appInstructionRoutes, true) && !app_application_instructions_enabled($settings)) {
		header('Location: /instructions');
		exit;
	}
	
	switch ($site){ 
 
	default: 					$page_title = "Homepage"; break;
	
	case "news":		   		$page_title = "News"; break;
	case "apps":		   		$page_title = "Apps"; break;
	case "settings":		   	$page_title = "Settings"; break;
	case "change-password":	    $page_title = "Change password"; break;
	case "orders":	   			$page_title = "Orders"; break;
	case "cryptocurrency":	   	$page_title = "Cryptocurrency"; break;
	case "payments_crypto":	   	$page_title = "Cryptocurrency"; break;
	case "referrals":			$page_title = "Referrals"; break;	
	case "history":				$page_title = "History"; break;
	case "how-to-pay":			$page_title = "How to pay?"; break;
	case "faq":					$page_title = "FAQ"; break;
	case "instructions":		$page_title = "Instructions"; break;
	case "instruction-trust-wallet":		$page_title = "Trust Wallet"; break;
	case "instruction-revolut":			$page_title = "Revolut"; break;
	case "instruction-crypto-exchange":	$page_title = "Crypto exchange"; break;
	case "instruction-smart-iptv":		$page_title = "Smart IPTV"; break;
	case "instruction-ott-player":		$page_title = "OTT Player"; break;
	case "instruction-newlook":			$page_title = "NewLook"; break;
	
	case "contact":				$page_title = "Contact"; break;
	case "register": 			$page_title = "Sign up"; break;
	case "password":	    	$page_title = "Password"; break;
    case "login":			    $page_title = "Login"; break;
	case "logout":				break;
	}
	
	$smarty->assign("page_title", $page_title);
	$content_inner_class = in_array($site, ['login', 'register', 'password'], true) ? 'content-inner-auth' : '';
	$smarty->assign("content_inner_class", $content_inner_class);
	
	$smarty->display("header.tpl");

	$has_verified_home_email = false;

	if (!empty($_SESSION['home_email'])) {
		$verifiedHomeCustomer = app_find_customer_by_email($db, trim($_SESSION['home_email']));
		$has_verified_home_email = is_array($verifiedHomeCustomer) && app_customer_is_active($verifiedHomeCustomer);

		if (!$has_verified_home_email) {
			unset($_SESSION['home_email'], $_SESSION['home_email_verified_at'], $_SESSION['verified_user_id']);
		}
	}

	////////////////////////////////////////////////////
			
	if($settings["homepage_verify"] == 1 && !$has_verified_home_email){
		
		include("scripts/check_email.php");
		$smarty->display("homepage_verify.tpl"); 
			
	}else{ 
				
		$smarty->display("loader.tpl"); //loader 
				
		if($user["logged"]){
					
			$smarty->display("top_page.tpl");

			if (!empty($settings['support_chat_enabled'])) {
				include("scripts/check_data.php"); 
				include("config/chat_config.php");
				$smarty->display("messanger.tpl");
			}
					
		}	
						
		$smarty->display("content_page.tpl");
				
		switch($site){
			
			default: 					include("pages/homepage.php"); break; 
				
			case "news":				include("pages/profil/news.php"); break;
			case "apps":				include("pages/apps.php"); break;
			case "settings":			include("pages/profil/settings.php"); break;
			case "orders":				include("pages/profil/orders.php"); break;
			case "history":				include("pages/profil/history.php"); break;
			case "how-to-pay":			include("pages/how_to_pay.php"); break;
			case "faq":					include("pages/faq.php"); break;
			case "instructions":		include("pages/instructions.php"); break;
			case "instruction-trust-wallet":	include("pages/instructions.php"); break;
			case "instruction-revolut":		include("pages/instructions.php"); break;
			case "instruction-crypto-exchange":	include("pages/instructions.php"); break;
			case "instruction-smart-iptv":	include("pages/instructions.php"); break;
			case "instruction-ott-player":	include("pages/instructions.php"); break;
			case "instruction-newlook":		include("pages/instructions.php"); break;
			case "change-password":		include("pages/profil/change_password.php"); break;
			case "referrals":			include("pages/profil/referrals.php"); break;
			case "cryptocurrency":		include("pages/profil/payments_crypto.php"); break;
			case "payments_crypto":	include("pages/profil/payments_crypto.php"); break;
				
			case "contact":				include("pages/contact.php"); break;
			case "register": 			include("pages/register.php"); break;
			case "password":			include("pages/password.php"); break;
			case "login":				include("pages/login.php"); break;
			case "logout":				include("pages/logout.php"); break;
		}
				
		$smarty->display("content_footer.tpl");
		
	}	
		
	$smarty->display("footer.tpl");
		

?>
