<?php
class Stronicowanie {
	
	

	private $strona = 1;
	private $ilosc_danych_na_stronie = 0;
	private $wszystkich_danych;
	private $ostatnia_strona;
	private $zapytanie;

	public function __construct($ilosc) {
		$this->ilosc_danych_na_stronie = $ilosc;
	}

	public function select_user($zapytanie) {
		$db = Mysql_ks::get_instance();
	
		$this->zapytanie = $zapytanie;

		$wynik = $db->query($zapytanie);
		if ($wynik === false) {
			$this->wszystkich_danych = 0;
			$this->ostatnia_strona = 0;
			return;
		}
		
		$this->wszystkich_danych = $wynik->num_rows;
	
		////////////////////// LIMIT Wszystkich wyników z limitem
		
		if(($this->wszystkich_danych) >= 100){ 
			$this->wszystkich_danych = $wynik = 100;
		}else{
			$this->wszystkich_danych = $wynik->num_rows;
		}
		//////////////////////
		
		$this->ostatnia_strona = ceil($this->wszystkich_danych / $this->ilosc_danych_na_stronie);
	}
	
	public function select($tabela, $wartosci = "*", $dodatki = "", $show=false) {
		$db = Mysql_ks::get_instance();

		$zapytanie = "SELECT ";

		if (is_array($wartosci)) {
			for ($i = 0; $i < count($wartosci) - 1; $i++) {
				$zapytanie .= "$wartosci[$i], ";
			}
			$zapytanie .= $wartosci[count($wartosci) - 1];
		} else {
			$zapytanie .= $wartosci;
		}
		$zapytanie .= " FROM $tabela $dodatki";
		if ($show) echo $zapytanie;
		$this->zapytanie = $zapytanie;

		$zapytanie = "SELECT count(id) AS ilosc FROM $tabela $dodatki";
		$wynik = $db->query($zapytanie);
		$wynik = $wynik->fetch_assoc();
	
		$this->wszystkich_danych = $wynik["ilosc"];
		$this->ostatnia_strona = ceil($this->wszystkich_danych / $this->ilosc_danych_na_stronie);
	}

	public function get_wartosci() {
		$db = Mysql_ks::get_instance();
		$offset = ($this->strona - 1) * $this->ilosc_danych_na_stronie;
		$limit = $this->ilosc_danych_na_stronie;

		$this->zapytanie .= " LIMIT $offset, $limit";

		$wynik3 = $db->query($this->zapytanie);
		if ($wynik3 === false) {
			return array();
		}
		$wynik2;
		$wynik = array();
		
		while ($wynik2 = $wynik3->fetch_assoc()) {
			$wynik[] = $wynik2;
		}

		return $wynik;
	}

	public function set_strona($strona) {

		$strona = (int)$strona;
		if ($strona == 0) 
			$strona = 1;
		
		
		if ($strona <= $this->ostatnia_strona)
			$this->strona = $strona;
		else
			$this->strona = 1;
	}

	public function generator($link) {
		if ($this->ostatnia_strona == 1 || $this->ostatnia_strona == 0)
			return "";
		else {
			if ($this->strona == 1)
				$poprzednia_strona = 1;
			else
				$poprzednia_strona = $this->strona-1;
				
			if ($this->strona == $this->ostatnia_strona)
				$nastepna_strona = $this->ostatnia_strona;
			else
				$nastepna_strona = $this->strona+1;

			$tmp  = "<div class=\"crl\"></div>";
			$tmp .= "<div id=\"pagination\">";
			$tmp .= "<ul class=\"pagination pagination-lg\">";
			$tmp .= "<li><a href=\"$link$poprzednia_strona\" aria-label=\"Previous\"><span aria-hidden=\"true\">&laquo;</span></a></li>";
				
			for ($i=1; $i <= $this->ostatnia_strona; $i++) {
				if ($this->strona != $i) {
					$tmp.= "<li><a href=\"$link$i\">$i<span class=\"sr-only\">(current)</span></a></li>";
				} else {
					$tmp.= "<li class=\"active\"><a href=\"$link$i\">$i <span class=\"sr-only\">(current)</span></a></li>";
				}
			}
				
			$tmp .= "<li><a href=\"$link$nastepna_strona\" aria-label=\"Next\"><span aria-hidden=\"true\">&raquo;</span></a></li>";
			$tmp .= "</ul>";
			$tmp .= "</div>";
				
			return $tmp;
			
			
		}
	}

}
?>
