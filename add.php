#!/usr/bin/php
<?php
// add an IP (range) to the vpn gateway

include("/opt/ip-rule-switcher/common.php");

$ipRange = @$argv[1];
$table = @$argv[2];
$device = @$argv[3];

if (!is_numeric($table) || (!$device) ) {
 
  die("usage add.php iprange table device");
}
init_db();
ipaddess_add($ipRange,$table,$device);
close_db();
?>
