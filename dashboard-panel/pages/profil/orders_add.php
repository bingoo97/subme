<?php

		if (!function_exists('orders_find_pending_unpaid_order')) {
			function orders_find_pending_unpaid_order($db, array $user, int $tenantId = 1)
			{
				if (app_uses_v2_schema($db)) {
					return $db->select_user(
						"SELECT
							orders.id,
							orders.product_id,
							orders.created_at,
							orders.total_amount,
							products.name AS product_name,
							product_providers.name AS provider_name
						 FROM orders
						 LEFT JOIN products ON products.id = orders.product_id
						 LEFT JOIN product_providers ON product_providers.id = products.provider_id
						 WHERE orders.customer_id = '" . (int)$user['id'] . "'
						   AND orders.status = 'pending_payment'
						   AND orders.payment_status = 'unpaid'
						 ORDER BY orders.id DESC
						 LIMIT 1"
					);
				}

				return $db->select_user(
					"SELECT
						products_users.id,
						products_users.product_id,
						products_users.date_add AS created_at,
						products_users.price AS total_amount,
						products.name AS product_name,
						products_providers.name AS provider_name
					 FROM products_users
					 LEFT JOIN products ON products.id = products_users.product_id
					 LEFT JOIN products_providers ON products_providers.id = products.provider_id
					 WHERE products_users.user_id = '" . (int)$user['id'] . "'
					   AND products_users.res_id = '" . (int)$tenantId . "'
					   AND products_users.payment = 0
					   AND products_users.shipment = 0
					   AND products_users.status = 0
					 ORDER BY products_users.id DESC
					 LIMIT 1"
				);
			}
		}

		if (!function_exists('orders_create_pending_order')) {
			function orders_create_pending_order(Mysql_ks $db, int $customerId, array $product, string $note = ''): array
			{
				$productId = (int)($product['id'] ?? 0);
				if ($customerId <= 0 || $productId <= 0) {
					return ['ok' => false, 'message' => 'Invalid order payload.'];
				}

				$price = (float)($product['price'] ?? 0);
				$durationHours = (int)($product['duration'] ?? 0);
				$currencyId = isset($product['currency_id']) ? (int)$product['currency_id'] : 0;
				if ($currencyId <= 0) {
					$currencyId = 1;
				}

				$expiresAt = $durationHours > 0 ? date("Y-m-d H:i:s", time() + (3600 * $durationHours)) : null;
				$orderReference = 'WEB-' . date('YmdHis') . '-' . $customerId;
				$insertFields = [
					'customer_id',
					'product_id',
					'order_reference',
					'source_system',
					'total_amount',
					'currency_id',
					'status',
					'payment_status',
					'fulfillment_status',
					'customer_note',
					'expires_at',
				];
				$insertValues = [
					$customerId,
					$productId,
					$orderReference,
					'native',
					$price,
					$currencyId,
					'pending_payment',
					'unpaid',
					'pending',
					$note !== '' ? $note : null,
					$expiresAt,
				];

				if (schema_column_exists($db, 'orders', 'delivery_link_visible')) {
					$insertFields[] = 'delivery_link_visible';
					$insertValues[] = 0;
				}

				$inserted = $db->insert($insertFields, $insertValues, 'orders');
				if (!$inserted) {
					return ['ok' => false, 'message' => 'Unable to create order.'];
				}

				$orderId = (int)$db->id();
				if ($orderId > 0 && schema_object_exists($db, 'order_status_events')) {
					$db->insert(
						['order_id', 'old_status', 'new_status', 'event_note'],
						[$orderId, null, 'pending_payment', 'Order created from customer panel'],
						'order_status_events'
					);
				}

				if ($orderId > 0) {
					app_queue_order_created_notification($db, $orderId);
				}

				return ['ok' => true, 'order_id' => $orderId];
			}
		}

		if (!isset($ordersPaymentRedirectId)) {
			$ordersPaymentRedirectId = 0;
		}
		
		///////////////////// ADD PACKAGE /////////////////////////////////////////////////////////////
		
		if(isset($_POST['add_product'])){
			$tenantId = tenant_current_id($user);
			$customerProductType = app_customer_product_type($user, $settings);
			$productTypeSql = app_customer_order_catalog_product_type_sql($db, $user, $settings);
			$existingPendingOrder = orders_find_pending_unpaid_order($db, $user, $tenantId);

			if (!app_csrf_is_valid($_POST['_csrf'] ?? null)) {
				$smarty->assign("alert_error", localization_translate($t, 'csrf_invalid'));
				$smarty->display("alert.tpl");
				return;
			}

			$formToken = isset($_POST['order_add_token']) ? (string)$_POST['order_add_token'] : '';
			$sessionToken = isset($_SESSION['order_add_token']) ? (string)$_SESSION['order_add_token'] : '';

			if ($formToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $formToken)) {
				$smarty->assign("alert_error", "This add form has already been used. Open Add subscription again before sending a new order.");
				$smarty->display("alert.tpl");
				return;
			}

			if ($existingPendingOrder) {
				$ordersPaymentRedirectId = (int)$existingPendingOrder['id'];
				return;
			}

			$product_id = (int)$_POST['id_product'];
			$note = trim((string)($_POST['note'] ?? ''));
			$note = mb_substr($note, 0, 1000);
			
			if (app_uses_v2_schema($db)) {
				$select_package = $db->select_user(
					"SELECT
						products.id,
						products.name,
						products.price_amount AS price,
						products.duration_hours AS duration,
						products.is_trial AS trial,
						products.currency_id,
						products.product_type
					 FROM products
					 INNER JOIN product_providers ON product_providers.id = products.provider_id
					 WHERE products.id = {$product_id}
					   AND products.is_active = 1
					   AND product_providers.is_active = 1
					   AND products.product_type IN ({$productTypeSql})
					   " . app_customer_provider_visibility_sql($db, (int)$user['id'], 'products.provider_id') . "
					 LIMIT 1"
				);
			} else {
				$select_package = $db->select("products", "*", "WHERE id='$product_id' AND status = 1");
			}
			
			if($select_package) {
				if(app_customer_sales_enabled($user, $settings)){
					   
					if(($select_package["trial"] == 1) && ($settings["active_trials"] == 0)){
						
						$smarty->assign("alert_error", localization_translate($t, 'trials_disabled_notice', 'Trial subscriptions are currently disabled.'));	
						
					}else{

								if (app_uses_v2_schema($db)) {
									$orderResult = orders_create_pending_order($db, (int)$user['id'], $select_package, $note);
									if (!$orderResult['ok']) {
										$smarty->assign("alert_error", "Unable to create order right now. Please try again.");
									} else {
										unset($_SESSION['order_add_token']);
										$ordersPaymentRedirectId = (int)($orderResult['order_id'] ?? 0);
										return;
									}
								} else {
									$date_end = date("Y-m-d H:i:s", time() + (3600 * (int)$select_package["duration"]));
									$price = (float)$select_package["price"];
									$orderInsertFields = array("res_id", "product_id", "user_id", "price", "note", "link_url", "date_end", "payment", "shipment", "status");
									$orderInsertValues = array($tenantId, $product_id, $user["id"], $price, $note, "", $date_end, 0, 0, 0);

									if (schema_column_exists($db, "products_users", "tenant_id")) {
										$orderInsertFields[] = "tenant_id";
										$orderInsertValues[] = $tenantId;
									}
									if (schema_column_exists($db, "products_users", "created_at")) {
										$orderInsertFields[] = "created_at";
										$orderInsertValues[] = $time;
									}
									if (schema_column_exists($db, "products_users", "end_at")) {
										$orderInsertFields[] = "end_at";
										$orderInsertValues[] = $date_end;
									}
									if (schema_column_exists($db, "products_users", "payment_status")) {
										$orderInsertFields[] = "payment_status";
										$orderInsertValues[] = 0;
									}
									if (schema_column_exists($db, "products_users", "shipment_status")) {
										$orderInsertFields[] = "shipment_status";
										$orderInsertValues[] = 0;
									}
									if (schema_column_exists($db, "products_users", "is_active")) {
										$orderInsertFields[] = "is_active";
										$orderInsertValues[] = 0;
									}
									 
								    $inserted = $db->insert($orderInsertFields, $orderInsertValues, "products_users");
									if ($inserted) {
										unset($_SESSION['order_add_token']);
									}
								}
							
							if (!isset($_GET['payment'])) {
								$smarty->assign("alert", "Product added.");
							}
					}
				}else{
					$smarty->assign("alert_error", $customerProductType === 'credits'
						? localization_translate($t, 'credits_sales_disabled_notice', 'Credits sales are currently unavailable.')
						: "Sale of products is disabled.");	
				}
			}else{
				$smarty->assign("alert_error", "No product.");	
			}
			
			$smarty->display("alert.tpl");
		}
			
?>
