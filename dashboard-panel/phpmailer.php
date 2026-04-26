<?php

	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;
	use PHPMailer\PHPMailer\SMTP;
	
	require 'PHPMailer/PHPMailer/src/Exception.php';
	require 'PHPMailer/PHPMailer/src/PHPMailer.php';
	require 'PHPMailer/PHPMailer/src/SMTP.php';

	$maxil = new PHPMailer(TRUE);

	$mail = new PHPMailer;
	
	$mail_settings = array();
	if (isset($ustawienia) && is_array($ustawienia)) {
		$mail_settings = $ustawienia;
	} elseif (isset($settings) && is_array($settings)) {
		$mail_settings = $settings;
	}
	$smtp_host = isset($mail_settings["smtp_host"]) ? $mail_settings["smtp_host"] : "localhost";
	$smtp_port = isset($mail_settings["smtp_port"]) ? $mail_settings["smtp_port"] : "465";
	$smtp_login = isset($mail_settings["smtp_login"]) ? $mail_settings["smtp_login"] : "no-reply@localhost";
	$smtp_haslo = isset($mail_settings["smtp_haslo"]) ? $mail_settings["smtp_haslo"] : "";
	//Tell PHPMailer to use SMTP
	$mail->isSMTP();
	//Enable SMTP debugging
	// SMTP::DEBUG_OFF = off (for production use)
	// SMTP::DEBUG_CLIENT = client messages
	// SMTP::DEBUG_SERVER = client and server messages
	$mail->SMTPDebug = SMTP::DEBUG_OFF;
	
	//Set the hostname of the mail server
	$mail->Mailer = "smtp";	
	$mail->SMTPAuth = true;
	$mail->SMTPSecure = "ssl";
	$mail->SetLanguage("pl", "phpmailer/language/");				
	$mail->CharSet = "UTF-8";	
	$mail->ContentType = "text/html";
	$mail->Host = $smtp_host;                  //Set the SMTP port number - likely to be 25, 465 or 587
	$mail->Port = $smtp_port;                  //Whether to use SMTP authentication
	$mail->SMTPAuth = true;                       			 //Username to use for SMTP authentication
	$mail->Username = $smtp_login;     		 //Password to use for SMTP authentication
	$mail->Password = $smtp_haslo;             //Set who the message is to be sent from
	
?>