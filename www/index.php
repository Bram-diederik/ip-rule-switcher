<?php

$ipAddress = $_GET['addr'];
$tabel = $_GET['table'];
$action = $_GET['action'];
$sToken = "12345678";


$headers = getallheaders();

// Get the authorization token from the request headers
$authorizationHeader = $headers['Authorization'];

// Extract the token value from the header
$token = null;
if (preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
    $token = $matches[1];
}

// Verify the token and perform further actions
if ($token === $sToken) {
   if ($action == "off") {
     exec("sudo /opt/ip-rule-switcher/del_www.php $ipAddress");
     echo "off";
   } else { 
     exec("sudo /opt/ip-rule-switcher/add_www.php $ipAddress $tabel");
     echo "sudo /opt/ip-rule-switcher/add_www.php $ipAddress $tabel";
     echo "on";
   }
}

?>
