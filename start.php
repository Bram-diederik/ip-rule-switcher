#!/usr/bin/php
<?php
//script to be started at boot

include("/opt/ip-rule-switcher/common.php");

init_db();
init_ip();
close_db();
?>
