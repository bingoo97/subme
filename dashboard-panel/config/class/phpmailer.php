<?php
	require_once("phpmailer/class.phpmailer.php");
	$mail = new PHPMailer();

	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';

	$mail->PluginDir = "phpmailer/";

	$mail->Mailer = "smtp";	
	$mail->IsSMTP();
	$mail->Host = "$ustawienia[smtp_host]"; 					 
	$mail->SMTPAuth = true;
	$mail->SMTPSecure = "ssl";
	$mail->Port = "$ustawienia[smtp_port]";	
	
	$mail->Username = "$ustawienia[smtp_login]";
	$mail->Password = "$ustawienia[smtp_haslo]";	
					
	$mail->SetLanguage("pl", "phpmailer/language/");				
	$mail->CharSet = "UTF-8";	
	$mail->ContentType = "text/html";
//	$mail->Encoding = 'quoted-printable';

///////////////////////////////////////////////// 

?>

<?php

//	use PHPMailer\PHPMailer\PHPMailer;
//	use PHPMailer\PHPMailer\SMTP;
//	use PHPMailer\PHPMailer\Exception;
//	
//	/* The main PHPMailer class. */
//	require ('PHPMailer4/src/PHPMailer.php');
//	/* Exception class. */
//	require ('PHPMailer4/src/Exception.php');
//	/* SMTP class, needed if you want to use SMTP. */
//	require ('PHPMailer4/src/SMTP.php');
//
//	$mail = new PHPMailer();
//	
//	$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
//    $mail->IsSMTP();
//    $mail->SMTPAuth = true;
//	
//	$mail->Host       = "$ustawienia[smtp_host]";               // Set the SMTP server to send through
//    $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
//    $mail->Username   = "$ustawienia[smtp_login]";              // SMTP username
//    $mail->Password   = "$ustawienia[smtp_haslo]";              // SMTP password
//    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
//    $mail->Port       = "$ustawienia[smtp_port]";               // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
//	
//	$mail->SetLanguage("pl", "phpmailer/language/");				
//	$mail->CharSet = "UTF-8";	
//	$mail->ContentType = "text/html";
	
?>