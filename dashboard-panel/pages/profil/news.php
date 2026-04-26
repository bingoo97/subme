<?php
switch ($site) {
	case "news":

	if($user["logged"]){
		$visibleNow = $db->escape(app_current_datetime_string());

		if (app_uses_v2_schema($db)) {
			app_ensure_news_runtime_columns($db);
			$visibilityList = app_sql_string_list($db, app_customer_news_visibilities($user));
			$authorSelect = schema_column_exists($db, 'news_posts', 'created_by_admin_user_id') && schema_object_exists($db, 'admin_users')
				? app_admin_display_name_sql($db, 'admin_users') . ' AS author_handle'
				: "'' AS author_handle";
			$authorJoin = schema_column_exists($db, 'news_posts', 'created_by_admin_user_id') && schema_object_exists($db, 'admin_users')
				? ' LEFT JOIN admin_users ON admin_users.id = news_posts.created_by_admin_user_id'
				: '';
			$newsConditions = [
				"is_active = 1",
				"visibility IN ({$visibilityList})",
				"published_at <= '{$visibleNow}'",
			];

			if(isset($_GET["news_id"])){
				$news_id = (int)$_GET["news_id"];
				$newsConditions[] = "news_posts.id = '{$news_id}'";
			}

			$ask = "SELECT news_posts.id, news_posts.title, news_posts.body AS text, news_posts.published_at AS news_created_at, {$authorSelect}
					FROM news_posts{$authorJoin}
					WHERE " . implode(" AND ", $newsConditions) . "
					ORDER BY news_posts.published_at DESC, news_posts.id DESC";
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
				$news[$i]["date"] = date("d.m.Y H:i", $news[$i]["date_s"]);
				$news[$i]["author_label"] = trim((string)($news[$i]["author_handle"] ?? '')) !== ''
					? trim((string)$news[$i]["author_handle"])
					: trim((string)($reseller["name"] ?? ''));
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
