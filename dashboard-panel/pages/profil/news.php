<?php
switch ($site) {
	case "news":

	if($user["logged"]){
		$visibleNow = $db->escape(app_current_datetime_string());

		if (app_uses_v2_schema($db)) {
			$visibilityList = app_sql_string_list($db, app_customer_news_visibilities($user));
			$newsConditions = [
				"is_active = 1",
				"visibility IN ({$visibilityList})",
				"published_at <= '{$visibleNow}'",
			];

			if(isset($_GET["news_id"])){
				$news_id = (int)$_GET["news_id"];
				$newsConditions[] = "id = '{$news_id}'";
			}

			$ask = "SELECT id, title, body AS text, published_at AS news_created_at
					FROM news_posts
					WHERE " . implode(" AND ", $newsConditions) . "
					ORDER BY published_at DESC, id DESC";
			$news = $db->select_full_user($ask);
		} else {
			$tenantId = tenant_current_id($user);
			$tenantNewsTable = schema_read_target($db, 'tenant_news');
			$tenantNewsTenantColumn = schema_read_column($db, 'tenant_news', 'tenant_id', 'res_id');
			$tenantNewsStatusColumn = schema_read_column($db, 'tenant_news', 'is_active', 'status');
			$tenantNewsCreatedAtColumn = schema_read_column($db, 'tenant_news', 'created_at', 'date');
			
			
			if(isset($_GET["news_id"])){
					
			   $news_id = (int)$_GET["news_id"];
			   
			   $ask = "SELECT *, {$tenantNewsCreatedAtColumn} AS news_created_at
					   FROM {$tenantNewsTable}
					   WHERE {$tenantNewsTenantColumn} = '{$tenantId}' 
					   AND {$tenantNewsStatusColumn}=1 
					   AND {$tenantNewsCreatedAtColumn} <= '{$visibleNow}'
					   AND id = '$news_id'";
			   $news = $db->select_full_user($ask);
			   
			}else{

				$ask = "SELECT *, {$tenantNewsCreatedAtColumn} AS news_created_at
						FROM {$tenantNewsTable}
						WHERE {$tenantNewsTenantColumn} = '{$tenantId}' 
						AND {$tenantNewsStatusColumn}=1 
						AND {$tenantNewsCreatedAtColumn} <= '{$visibleNow}'
						ORDER BY {$tenantNewsCreatedAtColumn} DESC";
				$news = $db->select_full_user($ask);
			}
		}		
		
		if($news){
			for($i=0; $i < count($news); $i++){	
				$news[$i]["date_s"] = strtotime($news[$i]["news_created_at"]);
				$news[$i]["date"] = date("d.m.Y", $news[$i]["date_s"]);	
			}		
			$smarty->assign("news", $news);
		}
		
		$smarty->display("profil/news.tpl");
	 			
	}else{
		$smarty->display("no_access.tpl");
	}
break;
}
?>
