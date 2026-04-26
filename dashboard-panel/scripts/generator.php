<?php

function imageGenerator($length) {
   $lowercase = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'w', 'y', 'z');
   $number = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
   $image = NULL;
   for ($i = 0; $i < $length; $i++) {
	  $image .= $number[rand(0, count($number) - 1)];
   }
	 return substr($image, 0, $length);
}

?>