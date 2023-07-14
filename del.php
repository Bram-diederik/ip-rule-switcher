#!/usr/bin/php
<?php
//removes an IP to the vpn gateway

include("/opt/ip-rule-switcher/common.php");

$ipRange = @$argv[1];

if (!$ipRange) {
 
  die("usage del.php iprange");
}

init_db();
ipaddess_del($ipRange);
close_db();
?>
