#!/usr/bin/php
<?php
include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

init_db();
ha_parse();
close_db();
?>
