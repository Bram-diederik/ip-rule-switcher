#!/usr/bin/php
<?php
//webhook to delete a ip (range) from home assistant

include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

$ipRange = $argv[1];

init_db();
ipaddess_del($ipRange);
ha_parse_range($ipRange);
close_db();
?>
