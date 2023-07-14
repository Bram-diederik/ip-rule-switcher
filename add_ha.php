#!/usr/bin/php
<?php
// add an IP (range) to the scope of home assistant

include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

$ipRange = $argv[1];

init_db();
ha_ipaddess_add($ipRange);
close_db();
?>
