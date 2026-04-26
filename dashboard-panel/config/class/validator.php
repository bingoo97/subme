<?php
Class Validator {
	/**
	 * 
	 * Tryby walidacji
	 */
	const MIN_LENGTH = 1;
	const MAX_LENGTH = 2;
	const EMPTY_FIELD = 3;
	const EMAIL = 4;
	const REG_EXP = 5;

	const AUTO_EMPTY = 100;

	private $auto_empty = false;

	private $is_empty = false;

	private $checked_empty = false;
	
	private $field = '';
	private $tryb = 0;
	private $min_length = 0;
	private $max_length = 0;
	private $pattern;
	private $email_regexp = '/^([a-z0-9]{1})([^\s\t\.@]*)((\.[^\s\t\.@]+)*)@([a-z0-9]{1})((([a-z0-9-]*[-]{2})|([a-z0-9])*|([a-z0-9-]*[-]{1}[a-z0-9]+))*)((\.[a-z0-9](([a-z0-9-]*[-]{2})|([a-z0-9]*)|([a-z0-9-]*[-]{1}[a-z0-9]+))+)*)\.([a-z0-9]{2,6})$/Diu';
	private $errno = array();
	private $komunikat = '';
	
	function __construct($val = null) {
		if ($val == self::AUTO_EMPTY) $this->auto_empty = true;
	}
	
	public function add($field) {
		$this->field = $field;
		if ($this->auto_empty) {
			$this->is_empty = false;
			$this->checked_empty = false;
		}
	}
	
	public function set_min_length($min_length) { $this->min_length = (int)$min_length; }
	public function set_max_length($max_length) { $this->max_length = (int)$max_length; }
	public function set_pattern($pattern) { $this->pattern = $pattern; }
	
	public function min($min = null) { 
		if ($min != null) $this->set_min_length($min);
		$this->tryb = self::MIN_LENGTH;
		return $this;
	}

	public function max($max = null) { 
		if ($max != null) $this->set_max_length($max);
		$this->tryb = self::MAX_LENGTH;
		return $this;
	}
	
	public function reg_exp($pattern = null) { 
		if ($pattern != null) $this->set_pattern($pattern);
		$this->tryb = self::REG_EXP;
		return $this;
	}
	
	public function email() { 
		$this->tryb = self::EMAIL;
		return $this;
	}
	
	public function empty_field() {
		$this->tryb = self::EMPTY_FIELD;
		return $this;
	}
	
	public function exe() {
		return $this->errno;
	}
	
	public function val($komunikat) {
		$this->komunikat = $komunikat;
		if (!$this->auto_empty || ($this->auto_empty && $this->checked_empty && !$this->is_empty)) {
			switch ($this->tryb) {
				case self::MIN_LENGTH: 		$this->min_val(); break;
				case self::MAX_LENGTH: 		$this->max_val(); break;
				case self::EMPTY_FIELD: 	$this->empty_field_val(); break;
				case self::EMAIL:	 		$this->email_val(); break;
				case self::REG_EXP: 		$this->reg_exp_val(); break;
				
				default: break;
			}
		} elseif ($this->auto_empty && !$this->checked_empty) {
			$this->empty_field_val();
		}
	}
	
	private function min_val() {
		if (strlen($this->field) < $this->min_length) {
			$this->errno[] = $this->komunikat;
		}
	}
	
	private function max_val() {
		if (strlen($this->field) > $this->max_length) {
			$this->errno[] = $this->komunikat;
		}
	}
	
	private function empty_field_val() {
		if ($this->auto_empty) {
			if (!$this->checked_empty) {
				$this->checked_empty = true;
				
				if (strlen($this->field) == 0) {
					$this->errno[] = $this->komunikat;
					$this->is_empty = true;
				}
			}
				
		} else {
			if (strlen($this->field) == 0) {
				$this->errno[] = $this->komunikat;
			}
		}
	}
	
	private function reg_exp_val() {
		
		if (!preg_match($this->pattern, $this->field)) {
			$this->errno[] = $this->komunikat;
		}
	}
	
	private function email_val() {
		if (!preg_match($this->email_regexp, $this->field)) {
			$this->errno[] = $this->komunikat;
		}
	}

	public function less_than($field, $number, $komunikat) {
		if ($field >= $number) $this->errno[] = $komunikat;
	}
	
	public function greater_than($field, $number, $komunikat) {
		if ($field <= $number) $this->errno[] = $komunikat;
	}
	
	public function equals($field1, $field2, $komunikat) {
		if ($field1 != $field2) $this->errno[] = $komunikat;
	}
	
	public function user_check($bool, $komunikat) {
		if (!$bool) $this->errno[] = $komunikat;
	}
	
	public function unique($field, $tabela, $pole, $komunikat) {
		$db = Mysql_ks::get_instance();
		$dodatki = "WHERE $pole = '$field'";
		
		if ($db->select($tabela, "*", $dodatki)) $this->errno[] = $komunikat;	
	}
	
}



class RejException extends Exception{
	private $errno;
	function __construct($errno) {
		$this->errno = $errno;
	}
	public function getErrno() {
		return $this->errno;
	}
	
}


?>