<?php
include("/opt/ip-rule-switcher/settings.php");

$db = new SQLite3('/opt/ip-rule-switcher/ipaddress.db');
$debug = true;
function init_db() {
global $db;
// Database connection

// Create the ip_table_switch table if it doesn't exist
$query = "CREATE TABLE IF NOT EXISTS ip_table_switch (
            ip_address TEXT PRIMARY KEY ,
            state INTEGER,
            ip_table INTEGER
          )";
$db->exec($query);
$query = "CREATE TABLE IF NOT EXISTS ip_table_hosts (
            ip_address TEXT PRIMARY KEY ,
            hostname TEXT
          )";
$db->exec($query);


}

function restore_from_db() {
// Get all IP addresses with state 1 and ip_table state from the database
    global $db;
    $query = "SELECT ip_address, ip_table FROM ip_table_switch WHERE state = 1";
    $result = $db->query($query);
    $ipAddresses = [];
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ipAddresses[] = [
                'ip_address' => $row['ip_address'],
                'table' => $row['ip_table']
            ];
        }
    }
    foreach ($ipAddresses as $ipData) {
        $ipAddress = $ipData['ip_address'];
        $nTable = $ipData['ip_table'];
        route_add($ipAddress, $nTable);
    }

}

function init_ip() {
  //this code is to custom for every installation to use global vars or settings

  $IF1="br0";
  $IF2="br1";
  $VPN_GATEWAY="192.168.122.2";
  //table 12 is a vpn route
  shell_exec("ip route add 192.168.122.0/24  dev $IF2 table 12");
  shell_exec("ip route add default via $VPN_GATEWAY dev $IF2 table 12");
  shell_exec("ip route add 192.168.5.0/24  dev $IF1 table 12");

  //table 11 does not go to the internet. use an existing IP that does not forward traffic
  shell_exec("ip route add 192.168.5.0/24 dev $IF1 table 11");
  
  shell_exec("ip route add default via 192.168.5.43 table 11");
}

function add_route($ipAddress,int $nTable) {
  global $debug;
  if ($debug) {
    echo "add route $ipAddress  $nTable \n ip rule list table $nTable | grep $ipAddress\n ";
  }
  if ($table = get_table($ipAddress))
    del_route($ipAddress, $table);


  // Execute the command and capture the output
  $output = shell_exec("ip rule list table $nTable | grep $ipAddress");

  // Check if the output contains any result
  if (empty($output)) {
    shell_exec("ip rule add from $ipAddress table $nTable");
    shell_exec("ip rule add to $ipAddress table $nTable");
  }

}

function add_db($ipAddress,int $nTable) {
  global $db;
  global $debug;
  if ($debug) {
    echo "add db $ipAddress  $nTable \n";
  }
  $state = 1; // 1 for on, 0 for off
  // Insert data into the ip_table_switch table
  $query = "INSERT OR REPLACE INTO ip_table_switch (ip_address, state,ip_table) VALUES (:ip, :state,:ip_table)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
  $statement->bindValue(':state', $state, SQLITE3_INTEGER);
  $statement->bindValue(':ip_table', $nTable, SQLITE3_INTEGER);
  $statement->execute();

}

function del_route($ipAddress,int $nTable) {
  global $db;
  global $debug;
  if ($debug) {
    echo "del route $ipAddress  $nTable \n  ip rule list table $nTable | grep $ipAddress \n";
  }

  $output = shell_exec("ip rule list table $nTable | grep $ipAddress");
  
  // Check if the output contains any result
  if (@!empty($output)) {
    shell_exec("ip rule del from $ipAddress table $nTable");
    shell_exec("ip rule del to $ipAddress table $nTable");
  }
}

function del_db($ipAddress) {
  global $db;
  global $debug;
  if ($debug) {
    echo "del db $ipAddress\n";
  }
  $state = 0; // 1 for on, 0 for off
  // Insert data into the ip_table_switch table
  $query = "INSERT OR REPLACE INTO ip_table_switch (ip_address, state) VALUES (:ip, :state)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
  $statement->bindValue(':state', $state, SQLITE3_INTEGER);
  $statement->execute();
}

function update_host($hostname,$ipAddress) {
  global $db;
  global $debug;
  if ($debug) {
    echo "update $hostname $ipAddress\n";
  }
  $state = 0; // 1 for on, 0 for off
  // Insert data into the ip_table_switch table
  $query = "INSERT OR REPLACE INTO ip_table_hosts (ip_address, hostname) VALUES (:ip, :host)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
  $statement->bindValue(':host', $hostname, SQLITE3_TEXT);
  $statement->execute();
}

function get_host($ipAddress) {
    global $db;
    
    // Retrieve the hostname from the ip_table_hosts table
    $query = "SELECT hostname FROM ip_table_hosts WHERE ip_address = :ip";
    $statement = $db->prepare($query);
    $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
    $result = $statement->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    // Return the hostname if found, otherwise return a default value
    if ($row && isset($row['hostname'])) {
        return $row['hostname'];
    } else {
        return "Unknown Host";
    }
}



function close_db() {
  global $db;
  $db->close();
}

function get_table($ipAddress) {
    // Assuming $db is your SQLite database connection object
    global $db;

    $query = "SELECT `ip_table` FROM ip_table_switch WHERE state = 1 AND ip_address = :ipAddress";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':ipAddress', $ipAddress);
    $result = $stmt->execute();
     $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row) {
        return $row['ip_table'];
    } else {
        return 0; // or any default value you prefer
    }
}




function ipaddess_del($argument) {
    if (strpos($argument, '/') !== false) {
        // IP address with CIDR notation
        $parts = explode('/', $argument);
        $ipaddress = $parts[0];
        $cidr = $parts[1];
        
        $ip = ip2long($ipaddress);
        $mask = -1 << (32 - $cidr);
        $start = $ip & $mask;
        $end = $start | ~$mask;
        
        for ($i = $start; $i <= $end; $i++) {
            if ($table = get_table(long2ip($i)))
              del_route(long2ip($i), $table);
    
            del_db(long2ip($i));
        }
    } elseif (strpos($argument, '-') !== false) {
        // IP address range
        $range = explode('-', $argument);
        $start = ip2long(trim($range[0]));
        $end = ip2long(trim($range[1]));
        
        for ($ip = $start; $ip <= $end; $ip++) {
            if ($table = get_table(long2ip($ip)))
              del_route(long2ip($ip), $table);
            del_db(long2ip($ip));
        }
    } else {
        // Single IP address
        $ipaddress = $argument;
        if ($table = get_table($ipaddress))
           del_route($ipaddress, $table);

        del_db($ipaddress);
    }
}



function ipaddess_add($argument,$table) {
    if (strpos($argument, '/') !== false) {
        // IP address with CIDR notation
        $parts = explode('/', $argument);
        $ipaddress = $parts[0];
        $cidr = $parts[1];
        
        $ip = ip2long($ipaddress);
        $mask = -1 << (32 - $cidr);
        $start = $ip & $mask;
        $end = $start | ~$mask;
        
        for ($i = $start; $i <= $end; $i++) {
            add_route(long2ip($i),$table);
            add_db(long2ip($i), $table);
        }
    } elseif (strpos($argument, '-') !== false) {
        // IP address range
        $range = explode('-', $argument);
        $start = ip2long(trim($range[0]));
        $end = ip2long(trim($range[1]));
        
        for ($ip = $start; $ip <= $end; $ip++) {
            add_route(long2ip($ip),$table);
            add_db(long2ip($ip), $table);
      }
    } else {
        // Single IP address
        $ipaddress = $argument;
        add_route($ipaddress, $table);
        add_db($ipaddress, $table);
    }
}


function parseARPTable() {
    // Execute the arp command to retrieve the ARP table
    $output = shell_exec('arp -a');

    // Split the output into lines
    $lines = explode("\n", $output);

    $ipAddresses = [];

    // Iterate through each line and extract the IP addresses and hostnames
    foreach ($lines as $line) {
        $matches = [];
        // Match IP addresses using regular expression
        preg_match('/\((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\)/', $line, $matches);
        if (isset($matches[1])) {
            $ipAddress = $matches[1];

            // Match hostnames using regular expression
            preg_match('/(.*)\(((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}))\)/', $line, $hostnameMatches);
            $hostname = (trim($hostnameMatches[1]) !="") ? trim($hostnameMatches[1]) : $ipAddress;

            // Store the IP address and hostname in an array
            $ipAddresses[] = [
                'ip_address' => $ipAddress,
                'hostname' => $hostname
            ];
        }
    }

    return $ipAddresses;
}

?>