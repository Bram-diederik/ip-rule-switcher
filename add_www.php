#!/usr/bin/php
<?php
// web hook for home assistant to add a ip to the table

include("/opt/ip-rule-switcher/common.php");
include("/opt/ip-rule-switcher/homeassistant.php");

$ipRange = $argv[1];
$table = $argv[2];


if (!is_numeric($table)) {
 
  die("usage add.php iprange table");
}
init_db();
ipaddess_add($ipRange,$table);
ha_parse_range($ipRange);
close_db();
?>
