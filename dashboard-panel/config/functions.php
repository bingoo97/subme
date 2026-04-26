<?php
/*
This page defines a number of functions to make the code on other pages more readable
*/

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function generateAddress(){
    global $apikey;
    global $url;
    $options = array( 
        'http' => array(
            'header'  => 'Authorization: Bearer '.$apikey,
            'method'  => 'POST',
            'content' => '',
            'ignore_errors' => true
        )   
    );  
    
    $context = stream_context_create($options);
    $contents = file_get_contents($url."new_address", false, $context);
    $object = json_decode($contents);
    
    // Check if address was generated successfully
	    if (isset($object->address)) {
	      $address = $object->address;
	    } else {
	      // Show any possible errors
	      $responseHeaders = function_exists('http_get_last_response_headers')
	          ? (array)http_get_last_response_headers()
	          : [];
	      $address = ((string)($responseHeaders[0] ?? 'Request failed'))."\n".$contents;
	    }
    return $address;
}

function getBTCPrice($currency){
    $content = file_get_contents("https://www.blockonomics.co/api/price?currency=".$currency);
    $content = json_decode($content);
    $price = $content->price;
    return $price;
}

function USDtoEUR($amount){
	$req_url = 'https://api.exchangerate-api.com/v4/latest/USD';
	$response_json = file_get_contents($req_url);

	if(false !== $response_json) {
		$response_object = json_decode($response_json);
		$price = round(($amount * $response_object->rates->EUR), 2);
		return $price;
	}
}

function EURtoUSD($amount){
	$req_url = 'https://api.exchangerate-api.com/v4/latest/EUR';
	$response_json = file_get_contents($req_url);

	if(false !== $response_json) {
		$response_object = json_decode($response_json);
		$price = round(($amount * $response_object->rates->USD), 2);
		return $price;
	}
}

/// USD to BTC

function BTCtoUSD($amount){
    $price = getBTCPrice("USD");
    return $amount * $price;
}

function USDtoBTC($amount){
    $price = getBTCPrice("USD");
    return $amount/$price;
}

/// EUR to BTC

function BTCtoEUR($amount){
    $price = getBTCPrice("EUR");
    return $amount * $price;
}

function EURtoBTC($amount){
    $price = getBTCPrice("EUR");
    return $amount/$price;
}

//////////////////////////////////////////////////////////////

function getIp(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        //ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        //ip pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else{
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

?>
