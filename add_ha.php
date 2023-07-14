#!/usr/bin/php
<?php
#add an IP to the vpn gateway

include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

$ipRange = $argv[1];

init_db();
ha_ipaddess_add($ipRange);
close_db();
?>
