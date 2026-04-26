<?php

Class Mail_ks {



	private $email;

	private $naglowek_mail;



	function __construct($email) {

		$this->email = $email;

	}



	public function wyslij($email, $tytul, $tekst) {


		$naglowki = "From: $this->email".PHP_EOL."Content-type: text/plain; charset=iso-8859-2";
		

		 (mail($email, $tytul, $tekst, $naglowki)); 

		 

	}

	

	



	public function zamien($tekst, $tablica, $prefix = "%") {

	

		$znajdz = array_keys($tablica);

		

		foreach ($znajdz as &$value) {

			$value = $prefix.$value.$prefix;

		}

		

		return str_replace($znajdz, array_values($tablica), $tekst);

	}

}

if (!isset($ustawienia) || !is_array($ustawienia)) {
	$ustawienia = array();
}
$mail_ks = new Mail_ks(isset($ustawienia["admin_email"]) ? $ustawienia["admin_email"] : "no-reply@localhost");





?>