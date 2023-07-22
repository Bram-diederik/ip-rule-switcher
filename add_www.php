#!/usr/bin/php
<?php
// web hook for home assistant to add a ip to the table

include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

$ipRange = @$argv[1];
$table = @$argv[2];
$device = @$argv[3];


if (!is_numeric($table) || (!$device)) {
 
  die("usage add.php iprange table device\n");
}
init_db();
ha_init_db();
ipaddess_add($ipRange,$table,$device);
ha_parse_range($ipRange);
close_db();
?>
