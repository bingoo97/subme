<?php

if (!function_exists('app_runtime_timezone_name')) {
    function app_runtime_timezone_name() {
        static $timezone = null;

        if ($timezone !== null) {
            return $timezone;
        }

        $candidate = trim((string)getenv('APP_TIMEZONE'));
        if ($candidate === '') {
            $candidate = 'Europe/Warsaw';
        }

        try {
            new DateTimeZone($candidate);
            $timezone = $candidate;
        } catch (Throwable $exception) {
            $timezone = 'Europe/Warsaw';
        }

        return $timezone;
    }
}

if (!function_exists('app_bootstrap_runtime_timezone')) {
    function app_bootstrap_runtime_timezone() {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }

        $bootstrapped = true;
        date_default_timezone_set(app_runtime_timezone_name());
    }
}

if (!function_exists('app_mysql_session_timezone_offset')) {
    function app_mysql_session_timezone_offset() {
        app_bootstrap_runtime_timezone();
        $now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        return $now->format('P');
    }
}

app_bootstrap_runtime_timezone();

class Mysql_ks {

	private $db;
	private $kodowanie = "utf8mb4";
	private $zapytanie;
	public static $licznik = 0;
	public static $instance;
	
	private function __construct() {
		
		$db_host = getenv("DB_HOST") ?: "localhost";
		$db_name = getenv("DB_NAME") ?: "reseller_v2";
		$db_user = getenv("DB_USER") ?: "marbodz_reseller";
		$db_pass = getenv("DB_PASS") ?: "Reseller12@"; 
		
		if (function_exists('mysqli_report')) {
			mysqli_report(MYSQLI_REPORT_OFF);
		}
		
		@$this->db = new mysqli($db_host, $db_user, $db_pass, $db_name);
		
		if ($this->db->connect_errno) {
			echo 'No database.';
			exit;
		}
		
		$this->set_charset($this->kodowanie);

        $sessionTimezoneOffset = $this->db->real_escape_string(app_mysql_session_timezone_offset());
        @$this->db->query("SET time_zone = '{$sessionTimezoneOffset}'");
	}
	
	public static function get_instance() {
		if (is_null(self::$instance)) {
			self::$instance = new Mysql_ks();
		}
		return self::$instance;
	}
	
	public function __get($parametr) {
		if (isset($this->db->$parametr)) {
			return $this->db->$parametr;
		}
	}
	
	public function __toString() {
		return $this->zapytanie;
	}
	
	public function id() {
		return $this->db->insert_id;
	}
	
	public function set_charset($names) {
		$this->db->set_charset($names);
	}
	
	public function escape($str) {
		if (is_null($str)) return $str;
		if ($this->is_expr($str)) {
			$str["value"] = $this->db->real_escape_string($str["value"]);
			return $str;
		}
		return $this->db->real_escape_string($str);
	}
	
	public function start() {
		$this->query("START TRANSACTION");
	}
	
	public function commit() {
		$this->db->commit();
	}
	
	public function expr($val) {
		return array('value' => $val, 'is_expr' => true);
	}
	
	private function is_expr($val) {
		if (!is_array($val)) return false;
		if (!isset($val["is_expr"])) return false;
		return ($val["is_expr"]);
	}
	
	private function expr_get_value($val) {
		return $val["value"];
	}
	
	public function query($zapytanie) {
		self::$licznik++;
		$this->zapytanie = $zapytanie;
		$tmp = $this->db->query($zapytanie);
		return $tmp;
	}
	
	public function select($tabela, $wartosci = "*", $dodatki = "") {
		$zapytanie = "SELECT ";
	
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		//$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		for ($i = 0; $i < count($wartosci); $i++) {
			$zapytanie .= "$wartosci[$i], ";
		}
		$zapytanie = rtrim($zapytanie, ", ");
		
		$zapytanie .= " FROM $tabela $dodatki";
	
		$wynik = $this->query($zapytanie);
		if (!$wynik) {
			return null;
		}
		return $wynik->fetch_assoc();
	}
	
	public function select_using_id($tabela, $wartosci, $id) {
		$id = (int)$id;
		$dodatki = "WHERE id=$id";
		return $this->select($tabela, $wartosci, $dodatki);
	}
	
	public function select_full($tabela, $wartosci = "*", $dodatki = "") {
		$zapytanie = "SELECT ";
	
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		//$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		for ($i = 0; $i < count($wartosci); $i++) {
				$zapytanie .= "$wartosci[$i], ";
		}
		$zapytanie = rtrim($zapytanie, ", ");
		
		$zapytanie .= " FROM $tabela $dodatki";
	
		return $this->select_full_user($zapytanie);
	
		/* $wynik = $this->query($zapytanie);
			$wynik2;
		$wynik3 = array();
		while ($wynik2 = $wynik->fetch_assoc()) {
		$wynik3[] = $wynik2;
		}
		return $wynik3; */
	}
	
	public function select_user($zapytanie) {
		$wynik = $this->query($zapytanie);
		if (!$wynik) {
			return null;
		}
		return $wynik->fetch_assoc();
	}
	
	public function select_full_user($zapytanie) {
		$wynik = $this->query($zapytanie);
		if (!$wynik) {
			return array();
		}
		$wynik2;
		$wynik3 = array();
		while ($wynik2 = $wynik->fetch_assoc()) {
			$wynik3[] = $wynik2;
		}
		return $wynik3;
	}

	public function insert($pola, $wartosci, $tabela) {
		$zapytanie = "INSERT INTO $tabela (";
		if (!is_array($pola)) $pola = array($pola);
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		$wartosci = array_map(array($this, 'escape'), $wartosci);
		//$pola = array_map(array($this, 'escape'), $pola);
		
	
		if (count($pola) != count($wartosci)) {
			echo "Metoda insert<br />";
			echo "Nierówne długości pól i wartości";
			exit;
		} else {
			for ($i = 0; $i < count($pola); $i++) {
				$zapytanie .= "$pola[$i], ";
			}
			$zapytanie = rtrim($zapytanie, ", ");
			$zapytanie .= ") VALUES (";
	
			for ($i = 0; $i < count($wartosci); $i++) {
				if (is_null($wartosci[$i])) $zapytanie .= "NULL, ";
				elseif ($this->is_expr($wartosci[$i])) 
					$zapytanie .= $this->expr_get_value($wartosci[$i]).", ";
				else $zapytanie .= "'$wartosci[$i]', ";
				
			}
			$zapytanie = rtrim($zapytanie, ", ");
			$zapytanie .= ")";
		}
	
		$wynik = $this->query($zapytanie);
		return $wynik;
	}
	
	public function insert_full($wartosci, $tabela) {
		$zapytanie = "INSERT INTO $tabela VALUES (";
		
		if (!is_array($wartosci)) $wartosci = array($wartosci);
		$wartosci = array_map(array($this, 'escape'), $wartosci);
			
		for ($i = 0; $i < count($wartosci); $i++) {
			if (!is_null($wartosci[$i])) $zapytanie .= "'$wartosci[$i]', ";
			else $zapytanie .= "NULL, ";
		}
		$zapytanie = rtrim($zapytanie, ", ");
		$zapytanie .= ")";
	
		$wynik = $this->query($zapytanie);
		return $wynik;
	}
	
	public function update($pola, $wartosci, $tabela, $dodatki = '') {
		$zapytanie = "UPDATE $tabela SET ";
	
		if (!is_array($pola)) {
			$pola = array($pola);
			$wartosci = array($wartosci);
		}
		//$pola = array_map(array($this, 'escape'), $pola);
		$wartosci = array_map(array($this, 'escape'), $wartosci);
		
		for ($i = 0; $i < count($pola); $i++) {
			if (is_null($wartosci[$i])) $zapytanie .= "$pola[$i] = NULL, ";
			elseif ($this->is_expr($wartosci[$i])) $zapytanie .= "$pola[$i] = "
					.$this->expr_get_value($wartosci[$i]).", ";
			else $zapytanie .= "$pola[$i] = '$wartosci[$i]', ";
			
		}
		$zapytanie = rtrim($zapytanie, ", ");
	
		$zapytanie .= " $dodatki" ;
	
		$wynik = $this->query($zapytanie);
		return $wynik;
	}
	
	public function update_using_id($pola, $wartosci, $tabela, $id) {
		$id = (int)$id;
		$dodatki = " WHERE id = ".$id;

		return $this->update($pola, $wartosci, $tabela, $dodatki);
	}
	
	public function delete_using_id($tabela, $id) {
		$id = (int)$id;
		$zapytanie = "DELETE FROM $tabela WHERE id = $id";

		$wynik = $this->query($zapytanie);
		return $wynik;
	}
	
	public function get_polaczenie() {
		$tmp = array();
		$tmp["host"] = $db_host;
		$tmp["baza"] = $db_name;
		$tmp["login"] = $db_user;
		$tmp["haslo"] = $db_pass;
		return $tmp;
	}
}
$db = Mysql_ks::get_instance();
?>
