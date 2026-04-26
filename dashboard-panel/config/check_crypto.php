<?php

		$payment = $db->select("invoices", "*", "WHERE code = '$code'");
		
		if($payment){
			
				$crypto = $db->select_using_id("cryptocurrency", "*", 1); 
			
				$status = $payment["status"];
				$statusval = $status;
				
				$product = $payment["id"];
				$address = $payment["address"];
				
				
				/////////////////////////////////////////////////////////////////////////////////////////////
				
				$payment["btc_price"] = round($payment["price"] / $payment["rate"], 8);
				
				
				$price = $payment["price"];
				
				/////////////////////////////////////////////////////////////////////////////////////////////
			
				$info = "";
				if($status == 0){
					$status = '<span class="btn btn-default orange" id="status">PENDING</span>';
					$info = "<p>You payment has been received.<br/>Invoice will be marked paid on two blockchain confirmations.</p>";
				}else if($status == 1){
					$status = '<span class="btn btn-default orange" id="status">PENDING</span>';
					$info = "<p>You payment has been received.<br/>Invoice will be marked paid on two blockchain confirmations.</p>";
				}else if($status == 2){
					$status = '<span class="btn btn-success" id="status">SUCCESS</span>';
				}else if($status == -1){
					$status = '<span class="btn btn-danger" id="status">UNPAID</span>';  
				}else if($status == -2){
					$status = "<span style='color: red' id='status'>Too little paid, please pay the rest.</span>";
				}else {
					$status = "<span style='color: red' id='status'>Error, expired</span>";
				}
				
				$smarty->assign("status", $status);
				$smarty->assign("info", $info);
				
				////////////////////////////////////////
				
				
				$payment["btc_address"] = $address;
				
				////////////////////////////////////////////////////////////
				
				// QR code generation using google apis 
				$cht = "qr";
				$chs = "220x220";
				$chl = $payment["btc_address"]; 
				$choe = "UTF-8";

				$payment["qr_code"] = 'https://chart.googleapis.com/chart?cht=' . $cht . '&chs=' . $chs . '&chl=' . $chl . '&choe=' . $choe;
				
				////////////////////////////////////////////////////////////
				
				$smarty->assign("payment", $payment);
				$smarty->assign("crypto", $crypto); 
				include("scripts/status.php");
		}

?>