#!/usr/bin/php
<?php
//script to be run at boot
include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");
init_db();
ha_init_db();
close_db();
?>
