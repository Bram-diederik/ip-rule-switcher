w<?php
// common files
include("/opt/ip-rule-switcher/settings.php");
include("/opt/ip-rule-switcher/ip_rules.php");

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
$query = "CREATE TABLE IF NOT EXISTS ip_nmap_hosts (
           ip_address TEXT PRIMARY KEY
          )";
$db->exec($query);

$query = "CREATE TABLE IF NOT EXISTS ip_table_dns_route (
           ip_table INTEGER PRIMARY KEY,
           ip_address TEXT
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
                'ip_table' => $row['ip_table']
            ];
        }
    }
    foreach ($ipAddresses as $ipData) {
        $ipAddress = $ipData['ip_address'];
        $nTable = $ipData['ip_table'];
        add_route($ipAddress, $nTable);
    }

}

function add_dns_db($ipAddress,int $nTable)  {
  global $db;
  $query = "INSERT OR REPLACE INTO ip_table_dns_route (ip_address, ip_table) VALUES (:ip, :table)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
  $statement->bindValue(':table', $nTable, SQLITE3_INTEGER);
  $statement->execute();

}

function add_dns($ipAddress,int $nTable) {
  //there is no delete. this rule purges the prevouse rule and sets in.
  // default could have a diffrent ip
  global $db;
  global $my_ip;
  //purge rule
 
  $findCommand = "iptables -t nat -L PREROUTING --line-numbers -n| grep \"$ipAddress\" | grep \"53\"";
  $findResult = shell_exec($findCommand);

  if ($findResult) {
   // Extract the line numbers from the find result
   preg_match_all('/^\s*(\d+).*$/m', $findResult, $matches);
   $lineNumbers = $matches[1];

   // Remove the rules using the line numbers
   foreach ($lineNumbers as $lineNumber) {
    $removeCommand = "iptables -t nat -D PREROUTING $lineNumber";
    echo  $removeCommand . "\n";;
    shell_exec($removeCommand);
   }
  } else {
  echo "No iptables rule found with destination IP $ipAddress.";
  }

  $dns = get_dns($nTable);
  if ($dns == "default")  {

  } else {
    //die("iptables -t nat -A PREROUTING -s $ipAddress -d $my_ip  -p udp --dport 53 -j DNAT --to-destination $dns ");
    exec("iptables -t nat -A PREROUTING -s $ipAddress -d $my_ip  -p udp --dport 53 -j DNAT --to-destination $dns ");

  }

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
  add_dns($ipAddress,$nTable); 
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

function add_nmap($ipAddress) {
  global $db;
  global $debug;
  if ($debug) {
    echo "add nmap $ipAddress \n";
  }
  $state = 1; // 1 for on, 0 for off
  // Insert data into the ip_table_switch table
  $query = "INSERT OR REPLACE INTO ip_nmap_hosts (ip_address) VALUES (:ip)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
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
  //set default dns
  add_dns($ipAddress,0);
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

function del_nmap($ipAddress) {
  global $db;
  global $debug;
  if ($debug) {
    echo "del nmap $ipAddress\n";
  }
  $state = 0; // 1 for on, 0 for off
  // Insert data into the ip_table_switch table
  $query = "DELETE FROM ip_nmap_hosts WHERE ip_address == :ip)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
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

function get_dns($nTable) {
    global $db;
    
    $query = "SELECT ip_address FROM ip_table_dns_route WHERE ip_table = :table";
    $statement = $db->prepare($query);
    $statement->bindValue(':table', $nTable, SQLITE3_INTEGER);
    $result = $statement->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    // Return the hostname if found, otherwise return a default value
    if ($row && isset($row['ip_address'])) {
        return $row['ip_address'];
    } else {
        return "default";
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
    $output = shell_exec('/sbin/arp -a');

    // Split the output into lines
    $lines = explode("\n", $output);

    $ipAddresses = [];

    // Iterate through each line and extract the IP addresses and hostnames
    foreach ($lines as $line) {
        if (strpos($line, '<incomplete>') !== false) {
             continue; // Skip this entry and proceed to the next line
        }
        $matches = [];
        // Match IP addresses using regular expression
        preg_match('/\((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\)/', $line, $matches);
        if (isset($matches[1])) {
            $ipAddress = $matches[1];

            // Match hostnames using regular expression
            preg_match('/(.*)\(((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}))\)/', $line, $hostnameMatches);
            $hostname = (trim($hostnameMatches[1]) !="") ? trim($hostnameMatches[1]) : $ipAddress;
            if ($hostname == "?") {
              $hostname = $ipAddress;
            }
            // Store the IP address and hostname in an array
            $ipAddresses[] = [
                'ip_address' => $ipAddress,
                'hostname' => $hostname
            ];
        }
    }

    return $ipAddresses;
}

function nmapScan($ipAddress = false) {
    global $db;
    if ($ipAddress ) {
      $query = "SELECT ip_address FROM ip_nmap_hosts WHERE ip_address = :ipAddress";
      // Execute the query
      $statement = $db->prepare($query);
      $statement->bindValue(':ipAddress', $ipAddress, SQLITE3_TEXT);
      $result = $statement->execute();
    } else {
      $query = "SELECT ip_address FROM ip_nmap_hosts";
      // Execute the query
      $statement = $db->prepare($query);
      $result = $statement->execute();
    }

    // Array to store the IP addresses
    $ipAddresses = [];
    $found = false;
    // Fetch the IP addresses from the query result
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
print_r($row);
        $ipAddresses[] = $row['ip_address'];
        $found = true;
    }

    if ($found) {
      $command = 'nmap -sn -n ' . implode(' ', $ipAddresses);
      // Execute the command and capture the output
      $output = shell_exec($command);

      // Process the output and extract the IP addresses and hostnames
      $lines = explode("\n", $output);
      $scannedIPs = [];

      foreach ($lines as $line) {
          // Match IP addresses using regular expression
          preg_match('/^Nmap scan report for (\S+)/', $line, $matches);

          if (isset($matches[1])) {
            $ipAddress = $matches[1];
            // Store the IP address and hostname in an array
            $scannedIPs[] = [
                'ip_address' => $ipAddress,
                'hostname' => $ipAddress
            ];
          }
      }
     print_r($scannedIPs);
      return $scannedIPs;
    } else {
      return false;
    }
}

function purge($argument) {
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
            purge_ip(long2ip($ip));
        }
    } elseif (strpos($argument, '-') !== false) {
        // IP address range
        $range = explode('-', $argument);
        $start = ip2long(trim($range[0]));
        $end = ip2long(trim($range[1]));
        
        for ($ip = $start; $ip <= $end; $ip++) {
            purge_ip(long2ip($ip));
      }
    } else {
        // Single IP address
        $ipaddress = $argument;
        purge_ip($ipaddress);
    }
}




function purge_ip($ipAddress) {
    global $db;
    // Prepare the DELETE queries for each table
    $queries = [
        "DELETE FROM ip_table_switch WHERE ip_address = :ipAddress",
        "DELETE FROM ip_table_hosts WHERE ip_address = :ipAddress",
        "DELETE FROM ip_nmap_hosts WHERE ip_address = :ipAddress"
    ];

    // Bind the IP address parameter and execute the DELETE queries
    foreach ($queries as $query) {
        $statement = $db->prepare($query);
        $statement->bindValue(':ipAddress', $ipAddress, SQLITE3_TEXT);
        $statement->execute();
    }
}

?>
