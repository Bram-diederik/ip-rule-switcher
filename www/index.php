<?php

$ipAddress = $_GET['addr'];
$tabel = $_GET['table'];
$action = $_GET['action'];
$device = $_GET['device'];
$sToken = "54956432856432895432698754329698436298632985643298368329657843258329";


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
   if ($action == "del") {
    echo "sudo /opt/ip-rule-switcher/del_www.php $ipAddress";
     exec("sudo /opt/ip-rule-switcher/del_www.php $ipAddress");
     echo "off";
   } else { 
     echo "sudo /opt/ip-rule-switcher/add_www.php $ipAddress $tabel $device";
     exec("sudo /opt/ip-rule-switcher/add_www.php $ipAddress $tabel $device");
     echo "on";
   }
}

?>
