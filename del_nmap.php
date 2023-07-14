#!/usr/bin/php
<?php
// add an IP to the nmap scan

include("/opt/ip-rule-switcher/common.php");

$ip = $argv[1];
init_db();
del_nmap($ip);
close_db();
?>
