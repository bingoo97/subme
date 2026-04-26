<?php 	
	
	$wybrana_aukcja = $db->select("produkty", "*", "WHERE id=$id");	 	
	
	if($wybrana_aukcja){

		$wybrana_aukcja["cena"]  = number_format($wybrana_aukcja["cena"], 2, '.', '');
		
		$smarty->assign("wybrana_aukcja", $wybrana_aukcja);
		
	}	
?>