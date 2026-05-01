<?php
switch ($site) {
	case "orders":
		if ($user["logged"]) {
			if (app_uses_v2_schema($db)) {
				app_delete_stale_unpaid_orders($db);
			}

			$tenantId = tenant_current_id($user);
			$customerProductType = app_customer_product_type($user);
			$orderSalesAvailable = app_customer_sales_enabled($user, $settings);
			$orderCatalogHasProducts = false;

			if (app_uses_v2_schema($db)) {
				$productTypeSql = app_product_type_sql($db, $user);
				$productCountRow = $db->select_user(
					"SELECT COUNT(*) AS total
					 FROM products
					 INNER JOIN product_providers
					    ON product_providers.id = products.provider_id
					 WHERE products.is_active = 1
					   AND product_providers.is_active = 1
					   AND products.product_type = {$productTypeSql}
					   " . app_customer_provider_visibility_sql($db, (int)$user['id'], 'products.provider_id') . "
					   " . ((int)($settings["active_trials"] ?? 0) === 1 ? "" : "AND products.is_trial = 0")
				);
				$orderCatalogHasProducts = (int)($productCountRow['total'] ?? 0) > 0;
			}

			$smarty->assign('order_catalog_product_type', $customerProductType);
			$smarty->assign('order_sales_available', $orderSalesAvailable ? 1 : 0);
			$smarty->assign('order_catalog_has_products', $orderCatalogHasProducts ? 1 : 0);

			if (app_uses_v2_schema($db) && isset($_POST["order_note_save"])) {
				$orderId = isset($_POST["order_note_id"]) ? (int)$_POST["order_note_id"] : 0;
				$note = trim((string)($_POST["order_note"] ?? ''));
				$note = mb_substr($note, 0, 1000);

				if ($orderId > 0) {
					$ownedOrder = $db->select_user(
						"SELECT id FROM orders
						 WHERE id = {$orderId}
						   AND customer_id = " . (int)$user["id"] . "
						 LIMIT 1"
					);

					if ($ownedOrder) {
						$db->update(
							['customer_note'],
							[$note],
							'orders',
							"WHERE id = {$orderId} AND customer_id = " . (int)$user["id"]
						);
						$smarty->assign("alert", localization_translate($t, 'orders_note_saved', 'Note saved.'));
						$smarty->display("alert.tpl");
					}
				}
			}

			if (app_uses_v2_schema($db) && (isset($_GET["order_extend"]) || isset($_GET["order_renew"]))) {
				$sourceOrderId = isset($_GET["order_extend"]) ? (int)$_GET["order_extend"] : (int)$_GET["order_renew"];
				$sourceOrder = $db->select_user(
					"SELECT
						orders.*,
						products.name AS product_name,
						products.duration_hours,
						products.price_amount,
						products.currency_id,
						products.is_trial,
						products.product_type
					 FROM orders
					 LEFT JOIN products ON products.id = orders.product_id
					 WHERE orders.id = {$sourceOrderId}
					   AND orders.customer_id = '{$user["id"]}'
					 LIMIT 1"
				);

				if ($settings["active_sale"] == 0) {
					$smarty->assign("alert_error", localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.'));
					$smarty->display("alert.tpl");
				} elseif (!$sourceOrder || empty($sourceOrder["product_id"])) {
					$smarty->assign("alert_error", "Order not found.");
					$smarty->display("alert.tpl");
				} elseif (strtolower(trim((string)($sourceOrder["product_type"] ?? 'subscription'))) === 'credits') {
					$smarty->assign("alert_error", localization_translate($t, 'orders_credits_extend_unavailable', 'Credits orders cannot be renewed or extended.'));
					$smarty->display("alert.tpl");
				} elseif (!empty($sourceOrder["is_trial"]) && (int)($settings["active_trials"] ?? 0) !== 1) {
					$smarty->assign("alert_error", localization_translate($t, 'trials_disabled_notice', 'Trial subscriptions are currently disabled.'));
					$smarty->display("alert.tpl");
				} else {
					$productDurationHours = isset($sourceOrder["duration_hours"]) ? (int)$sourceOrder["duration_hours"] : 0;
					$productPrice = isset($sourceOrder["price_amount"]) ? (float)$sourceOrder["price_amount"] : (float)$sourceOrder["total_amount"];
					$currencyId = isset($sourceOrder["currency_id"]) ? (int)$sourceOrder["currency_id"] : (int)$sourceOrder["currency_id"];
					if ($currencyId <= 0) {
						$currencyId = 2;
					}

					$newExpiry = date("Y-m-d H:i:s", time() + (3600 * max(1, $productDurationHours)));
					$notePrefix = isset($_GET["order_extend"]) ? 'Extended from order #' : 'Renewed from order #';
					$note = trim($notePrefix . $sourceOrderId . '. ' . (string)($sourceOrder["customer_note"] ?? ''));

					$db->insert(
						[
							'customer_id',
							'product_id',
							'total_amount',
							'currency_id',
							'status',
							'payment_status',
							'fulfillment_status',
							'customer_note',
							'expires_at',
						],
						[
							$user['id'],
							(int)$sourceOrder["product_id"],
							$productPrice,
							$currencyId,
							'pending_payment',
							'unpaid',
							'pending',
							$note,
							$newExpiry,
						],
						'orders'
					);

					$smarty->assign("alert", isset($_GET["order_extend"]) ? "Extension order created." : "Renewal order created.");
					$smarty->display("alert.tpl");
				}
			}
			 
			 
			include('pages/profil/orders_add.php');
			if (!empty($ordersPaymentRedirectId)) {
				$ordersPaymentRedirectId = (int)$ordersPaymentRedirectId;
				if ($ordersPaymentRedirectId > 0 && !headers_sent()) {
					header('Location: /order-payment-' . $ordersPaymentRedirectId);
					exit;
				}
				$_GET['payment'] = $ordersPaymentRedirectId;
				unset($_POST['add_product']);
			}
			
			if((isset($_POST["order_add"])) || (isset($_GET["order_extend"]))){
				
				/////// ADD ORDER //////////////////////////////////////////////////////////////////////////////////
				
				
				if(isset($_POST["order_add"])){
					if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
						$smarty->assign("alert_error", localization_translate($t, 'csrf_invalid'));
						$smarty->display("alert.tpl");
						break;
					}
					$_SESSION['order_add_token'] = bin2hex(random_bytes(32));
					$smarty->assign("order_add_token", $_SESSION['order_add_token']);
					
					////////////////// ALL PACKAGES ///////////////////////////////////////////////////////////////
					
					
					if (app_uses_v2_schema($db)) {
						$productTypeSql = app_product_type_sql($db, $user);
						$zapytanie = "SELECT DISTINCT product_providers.*
								  FROM product_providers
								  INNER JOIN products
									ON product_providers.id = products.provider_id
								  WHERE product_providers.is_active = 1
									AND products.is_active = 1
									AND products.product_type = {$productTypeSql}
									" . app_customer_provider_visibility_sql($db, (int)$user['id'], 'products.provider_id') . "
									" . ((int)($settings["active_trials"] ?? 0) === 1 ? "" : "AND products.is_trial = 0") . "
								  ORDER BY product_providers.id";
					} else {
						$zapytanie = "SELECT DISTINCT products_providers.* 
								  FROM products_providers INNER JOIN products
								  ON products_providers.id = products.provider_id
								  WHERE res_id = '{$tenantId}'
								  AND status=1
								  ORDER by id";
					}
					$providers = $orderSalesAvailable ? $db->select_full_user($zapytanie) : [];
					
					 
					if($providers){
						$smarty->assign("providers", $providers);
					}
						
					$smarty->display("profil/orders_add.tpl");
				}
				
				/////// EXTEND ORDER ////////////////////////////////////////////////////////////////////////////////////
				
				if(isset($_GET["order_extend"])){
					
					$order_id = (int)$_GET["order_extend"];
																			
					$ask = "SELECT *,
								(SELECT name FROM products WHERE products.id=products_users.product_id) AS name,
								(SELECT duration FROM products WHERE products.id=products_users.product_id) AS duration
								FROM products_users 
								WHERE products_users.res_id = '{$tenantId}' 
                                AND products_users.user_id = '{$user["id"]}'
								AND id = '$order_id' 
								AND status <> 0
								ORDER BY id ASC";
					$selected = $db->select_user($ask);
					
					/////////// wyłączenie ISATA //////////////////////////////
							
					if($settings["active_sale"] == 0) {
									
						$smarty->assign("alert_error", localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.'));	
						$smarty->display("alert.tpl");
						$smarty->display("no_access.tpl");
						
					}else{
						
						if($selected){
						
							$products = $db->select_full("products", "*", "WHERE res_id = '{$tenantId}' AND id = '{$selected["product_id"]}' AND status=1");
							
							if($products){
								for($i=0; $i < count($products); $i++){
							
									$products[$i]["add_days"] = ($products[$i]["duration"]/24);
							
									$smarty->assign("products", $products);
								}
							}
							
							$_SESSION['id_zamowienia'] = $selected["id"];
								 
							$smarty->assign("selected", $selected);
						
						}
							
						$smarty->display("profil/orders_extend.tpl");
					}
				}
				
			}else{
	
					////////// Usuń pakiet z listy
								
					if(isset($_GET["del_order"])) {
						$id = (int)$_GET["del_order"];

						if (app_uses_v2_schema($db)) {
							$check = $db->select_user(
								"SELECT * FROM orders
								 WHERE id = '{$id}'
								   AND customer_id = '{$user["id"]}'
								   AND status = 'pending_payment'
								   AND payment_status = 'unpaid'
								 LIMIT 1"
							);

							if ($check) {
								$db->delete_using_id("orders", $id);
								$smarty->assign("alert", "Removed.");
								$smarty->display("alert.tpl");
							}
						} else {
							$check = $db->select("products_users", "*", "WHERE id='$id' AND user_id='{$user["id"]}'");
							
							if($check){
							
								$notify = "Package #$id has been canceled.";		
								
								$db->insert(array("user", "date", "desc"), 
											array($user["id"], $time, $notify), "history");
												
								$db->delete_using_id("products_users", $id); 
								
								$smarty->assign("alert", "Removed.");
								$smarty->display("alert.tpl");
							}
						}
					}
					
					if((isset($_GET["payment"])) or (isset($_POST["payment"]))){
						if ((int)($settings["active_sale"] ?? 0) !== 1) {
							$smarty->assign("alert_error", localization_translate($t, 'sales_disabled_notice', 'Sales are currently unavailable.'));
							$smarty->display("alert.tpl");
						} else {
										
							include('pages/profil/orders_payment.php');
						
						/////// payment crypto
										
							if(isset($_POST["payment_crypto"])){
							
								include_once("config/functions.php");
								include_once("config/blockonmics.php");
								include('config/check_crypto.php');
								include('scripts/check_payment.php'); 
								$smarty->display("profil/orders_payment_crypto.tpl"); 
							 
							}else{	
								$smarty->display("profil/orders_payment.tpl"); 
							}
						}
						
					}else{
					
						include("config/class/pagination.php");
						include('pages/profil/orders_check.php');
						$smarty->display("profil/orders.tpl");
						
					}
			}
		}

break;
}
?>
