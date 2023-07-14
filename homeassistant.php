<?php

function ha_init_db() {
  global $db;
  // Database connection

  // Create the ip_table_switch table if it doesn't exist
  $query = "CREATE TABLE IF NOT EXISTS ip_table_homeassistant (
            ip_address TEXT  PRIMARY KEY
          )";
  $db->exec($query);

}

function ha_add_db($ipAddress) {
  global $db;
  global $debug;
  if ($debug) {
    echo "add ha db $ipAddress\n";
  }
  // Insert data into the ip_table_switch table
  $query = "INSERT OR REPLACE INTO ip_table_homeassistant (ip_address) VALUES (:ip)";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
  $statement->execute();

}

function ha_del_db($ipAddress) {
  global $db;
  global $debug;
  if ($debug) {
    echo "del db $ipAddress\n";
  }
  // Delete data from the ip_table_switch table
  $query = "DELETE FROM ip_table_switch WHERE ip_address = :ip";
  $statement = $db->prepare($query);
  $statement->bindValue(':ip', $ipAddress, SQLITE3_TEXT);
  $statement->execute();
  $result = $statement->fetchAll(PDO::FETCH_ASSOC);
  return $result;
}


function ha_get_list() {
    global $db;

    $query = "



    SELECT ip_address, state, ip_table
    FROM ip_table_switch
    WHERE state = 1
    OR (state = 0 AND ip_address NOT IN (SELECT ip_address FROM ip_table_homeassistant))
    UNION ALL
    SELECT ip_address, '1' AS state, '0' AS ip_table
    FROM ip_table_homeassistant
    WHERE ip_address NOT IN (SELECT ip_address FROM ip_table_switch WHERE state = 1);
    ";
    $data = [];
    $result = $db->query($query);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }

    return $data;
}




function ha_ipaddess_add($argument) {
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
            ha_add_db(long2ip($i));
        }
    } elseif (strpos($argument, '-') !== false) {
        // IP address range
        $range = explode('-', $argument);
        $start = ip2long(trim($range[0]));
        $end = ip2long(trim($range[1]));

        for ($ip = $start; $ip <= $end; $ip++) {
            ha_add_db(long2ip($ip));
      }
    } else {
        // Single IP address
        $ipaddress = $argument;
        ha_add_db($ipaddress);
    }
}

function ha_ipaddess_del($argument) {
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
            ha_del_db(long2ip($i));
        }
    } elseif (strpos($argument, '-') !== false) {
        // IP address range
        $range = explode('-', $argument);
        $start = ip2long(trim($range[0]));
        $end = ip2long(trim($range[1]));

        for ($ip = $start; $ip <= $end; $ip++) {
            ha_del_db(long2ip($ip));
      }
    } else {
        // Single IP address
        $ipaddress = $argument;
        ha_del_db($ipaddress);
    }
}



function ha_send($ipAddress, $hostName, $ipTable,$bOnline) {
    global $sHomeApiUrl;
    global $sHomeApiKey;
    if ($bOnline) {
       $state = "on"; 
    } else {
       $state = "off"; 
    }
    // Prepare the data for the device tracker entity
    $entityId = "device_tracker.ip_switch_" . str_replace(".", "_", $ipAddress);
    $attributes = [
        "friendly_name" => $hostName,
        "ip_table" => $ipTable,
        "ip" => $ipAddress
    ];
    
    // Prepare the payload
    $data = [
        "state" => $state,
        "attributes" => $attributes
    ];
    
    // Convert the payload to JSON
    $jsonPayload = json_encode($data);
    
    // Set the headers
    $headers = [
        "Authorization: Bearer $sHomeApiKey",
        "Content-Type: application/json"
    ];
    
    // Send the POST request to create the device tracker entity
    $ch = curl_init($sHomeApiUrl . "/api/states/$entityId");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Check the response status
    if ($response === false) {
        echo "Failed to create the device tracker entity." . PHP_EOL;
    } else {
        echo "Device tracker entity created successfully." . PHP_EOL;
    }
}



function ha_parse() {
  $ipList = ha_get_list();
  $onlineDevices = parseARPTable();

  foreach ($ipList as $ipData) {
    $ipAddress = $ipData['ip_address'];
    $state = $ipData['state'];
    $ipTable = $ipData['ip_table'];

    //Check if IP address is online
    $isOnline = false;
    $hostname = '';

    foreach ($onlineDevices as $onlineDevice) {
        if ($onlineDevice['ip_address'] === $ipAddress) {
            $isOnline = true;
            $hostname = $onlineDevice['hostname'];
            echo " $hostname \n";
            break;
        }
    }

    // Update the hostname if the IP address is online
    if ($isOnline) {
        update_host($hostname, $ipAddress);
    }

    //  Call ha_send() with appropriate parameters
    if ($state == 0) {
        $ipTable = 0; // Treat state 0 as ipTable 0
    }

    if ($isOnline) {
        ha_send($ipAddress, $hostname, $ipTable,true);
    } else {
        $offlineHostname = get_host($ipAddress); //  Retrieve the offline hostname
        ha_send($ipAddress, $offlineHostname, $ipTable,false); // Call ha_send() with offline hostname
    }
  }
}


function ha_parse_ip($ipAddress) {
  
  $onlineDevices = parseARPTable();

    $ipTable = get_table($ipAddress);
    //Check if IP address is online
    $isOnline = false;
    $hostname = '';

    foreach ($onlineDevices as $onlineDevice) {
        if ($onlineDevice['ip_address'] === $ipAddress) {
            $isOnline = true;
            $hostname = $onlineDevice['hostname'];
            echo " $hostname \n";
            break;
        }
    }

    // Update the hostname if the IP address is online
    if ($isOnline) {
        update_host($hostname, $ipAddress);
    }

    if ($isOnline) {
        ha_send($ipAddress, $hostname, $ipTable,true);
    } else {
        $offlineHostname = get_host($ipAddress); //  Retrieve the offline hostname
        ha_send($ipAddress, $offlineHostname, $ipTable,false); // Call ha_send() with offline hostname
    }
}

function ha_parse_range($argument) {
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
            ha_parse_ip(long2ip($i));
        }
    } elseif (strpos($argument, '-') !== false) {
        // IP address range
        $range = explode('-', $argument);
        $start = ip2long(trim($range[0]));
        $end = ip2long(trim($range[1]));

        for ($ip = $start; $ip <= $end; $ip++) {
            ha_parse_ip(long2ip($i));
      }
    } else {
        // Single IP address
        $ipaddress = $argument;
        ha_parse_ip($ipaddress);
    }
}

?>
