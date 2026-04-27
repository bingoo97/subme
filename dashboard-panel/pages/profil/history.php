<?php
function history_format_ui_date(string $value): string
{
	$value = trim($value);
	if ($value === '') {
		return '';
	}

	$timestamp = strtotime($value);
	if ($timestamp === false) {
		return $value;
	}

	$today = date('Y-m-d');
	$rowDay = date('Y-m-d', $timestamp);
	if ($rowDay === $today) {
		return date('H:i', $timestamp);
	}

	if (date('Y', $timestamp) === date('Y')) {
		return date('d.m', $timestamp);
	}

	return date('d.m.Y', $timestamp);
}

switch ($site) {
	case "history":
		if ($user["logged"]) {
			$history = [];
			$generator = '';

			if (app_uses_v2_schema($db) && isset($user['id'])) {
				$history = app_customer_history_rows($db, (int)$user['id'], $t, isset($settings) && is_array($settings) ? $settings : []);
			} else {
				include("config/class/pagination.php");
				$zapytanie = "SELECT * FROM history WHERE user={$user["id"]} ORDER BY date DESC";
				if (isset($_GET["strona"])) $strona = $_GET["strona"]; else $strona = 1;
				$str = new Stronicowanie(50);
				$str->select_user($zapytanie);
				$str->set_strona($strona);
				$generator = $str->generator("history-");
				$history = $str->get_wartosci();
				if (is_array($history)) {
					foreach ($history as $index => $entry) {
						$history[$index]['is_html'] = false;
					}
				}
			}

			if (is_array($history)) {
				foreach ($history as $index => $entry) {
					$history[$index]['date'] = history_format_ui_date((string)($entry['date'] ?? ''));
				}
			}

			$smarty->assign("generator", $generator);
			$smarty->assign("history", $history);

			$smarty->display("profil/history.tpl");
			
		} else {
			$smarty->display("no_access.tpl");
		}
break;
}
?>
