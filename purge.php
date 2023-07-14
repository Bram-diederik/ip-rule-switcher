#!/usr/bin/php
<?php
// purge a ip (range) from the system

include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

$ip = $argv[1];
init_db();
purge($ip);
purge_ha($ip);
close_db();
?>
