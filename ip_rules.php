<?php

function init_ip() {
  //this code is to custom for every installation to use global vars or settings
  //this is my local setup i explain all settings
  //set the default dns for rule 0. 
  //this dns will be used if no rule is used.
  add_dns_db("192.168.5.2",0);
  //IF1 is the normal network IF2 is connected to the VPN gateway
  $IF1="br0";
  $IF2="br1";
  $VPN_GATEWAY="192.168.122.2";
  //table 12 is a vpn route
  shell_exec("ip route add default via $VPN_GATEWAY dev $IF2 table 12");
  shell_exec("ip route add 192.168.5.0/24  dev $IF1 table 12");
  shell_exec("ip route add 192.168.122.0/24  dev $IF2 table 12");
  add_dns_db("192.168.5.42",12);

  //table 11 does not go to the internet. use an existing IP that does not forward traffic
  shell_exec("ip route add 192.168.5.0/24 dev $IF1 table 11");
  shell_exec("ip route add default via 192.168.5.43 table 11");
  //no rule for dns. the my_ip will be used.
  add_dns_db("default",11);

}

