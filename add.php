#!/usr/bin/php
<?php
#add an IP to the vpn gateway

include("/opt/ip-rule-switcher/common.php");

$ipRange = $argv[1];
$table = $argv[2];


if (!is_numeric($table)) {
 
  die("usage add.php iprange table");
}
init_db();
ipaddess_add($ipRange,$table);
close_db();
?>
