<?php
// DNS CLI Management Functionality

// Path to DNS mock configuration
$DNS_MOCK_CONFIG_PATH = __DIR__ . '/test-dns.php';

// Function to load DNS mock configuration
function loadDnsMockConfig() {
    global $DNS_MOCK_CONFIG_PATH;
    
    // If file doesn't exist, create with empty configuration
    if (!file_exists($DNS_MOCK_CONFIG_PATH)) {
        file_put_contents($DNS_MOCK_CONFIG_PATH, "<?php\nreturn ['fake_dns_records' => []];");
    }
    
    return require $DNS_MOCK_CONFIG_PATH;
}

// Function to save DNS mock configuration
function saveDnsMockConfig_bak($config) {
    global $DNS_MOCK_CONFIG_PATH;
    
    // Use associative arrays for complex records
    $configString = "<?php\nreturn " . var_export($config, true) . ";";
    
    // Replace numeric array syntax with associative array syntax
    $configString = preg_replace_callback('/(\s+)\[(\d+)\]\s*=>\s*/', function($matches) {
        return $matches[1];
    }, $configString);
    
    // Replace old array syntax with short array syntax
    $configString = str_replace(['array (', ')'], ['[', ']'], $configString);
    
    // Ensure proper indentation
    $configString = preg_replace_callback('/^(  +)/m', function($matches) {
        return str_repeat(' ', strlen($matches[1]) * 2);
    }, $configString);
    
    file_put_contents($DNS_MOCK_CONFIG_PATH, $configString);
}

// CLI DNS Management Function
function addDnsRecord(&$dnsMockConfig, $domain, $recordType, $value) {
    // Ensure the record type array exists
    if (!isset($dnsMockConfig['fake_dns_records'][$domain][$recordType])) {
        $dnsMockConfig['fake_dns_records'][$domain][$recordType] = [];
    }

    switch ($recordType) {
        case 'A':
        case 'AAAA':
        case 'TXT':
        case 'NS':
            // Use array_unique to prevent duplicates
            $dnsMockConfig['fake_dns_records'][$domain][$recordType] = 
                array_values(array_unique(
                    array_merge(
                        $dnsMockConfig['fake_dns_records'][$domain][$recordType], 
                        [$value]
                    )
                ));
            return true;

        case 'MX':
            // Prevent duplicate MX records based on host
            $exists = false;
            foreach ($dnsMockConfig['fake_dns_records'][$domain]['MX'] ?? [] as &$record) {
                if ($record['host'] === $value['host']) {
                    $record = $value; // Update existing record
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $dnsMockConfig['fake_dns_records'][$domain]['MX'][] = $value;
            }
            return true;

        case 'CNAME':
            // Only one CNAME per domain
            $dnsMockConfig['fake_dns_records'][$domain]['CNAME'] = [$value];
            return true;

        case 'PTR':
            // Add PTR record
            $dnsMockConfig['fake_dns_records'][$domain]['PTR'] = 
                array_values(array_unique(
                    array_merge(
                        $dnsMockConfig['fake_dns_records'][$domain]['PTR'] ?? [], 
                        [$value]
                    )
                ));
            return true;

        case 'SRV':
            // Prevent duplicate SRV records based on service and protocol
            $exists = false;
            foreach ($dnsMockConfig['fake_dns_records'][$domain]['SRV'] ?? [] as &$record) {
                if ($record['service'] === $value['service'] && 
                    $record['protocol'] === $value['protocol']) {
                    $record = $value;
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $dnsMockConfig['fake_dns_records'][$domain]['SRV'][] = $value;
            }
            return true;
    }
    return false;
}

function saveDnsMockConfig($config) {
    global $DNS_MOCK_CONFIG_PATH;
    
    // Consolidate duplicate records
    foreach ($config['fake_dns_records'] as $domain => &$records) {
        foreach (['A', 'AAAA', 'TXT', 'NS', 'CAA', 'PTR'] as $simpleType) {
            if (isset($records[$simpleType])) {
                $records[$simpleType] = array_values(array_unique($records[$simpleType]));
            }
        }

        // Consolidate complex records
        if (isset($records['MX'])) {
            $uniqueMX = [];
            foreach ($records['MX'] as $mxRecord) {
                $exists = false;
                foreach ($uniqueMX as &$existingRecord) {
                    if ($existingRecord['host'] === $mxRecord['host']) {
                        $existingRecord = $mxRecord;
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $uniqueMX[] = $mxRecord;
                }
            }
            $records['MX'] = $uniqueMX;
        }

        // Similar consolidation for SRV records
    }
    
    // Rest of the existing saveDnsMockConfig logic...
    $configString = "<?php\nreturn " . var_export($config, true) . ";";
    $configString = preg_replace_callback('/(\s+)\[(\d+)\]\s*=>\s*/', function($matches) {
        return $matches[1];
    }, $configString);
    $configString = str_replace(['array (', ')'], ['[', ']'], $configString);
    $configString = preg_replace_callback('/^(  +)/m', function($matches) {
        return str_repeat(' ', strlen($matches[1]) * 2);
    }, $configString);
    
    file_put_contents($DNS_MOCK_CONFIG_PATH, $configString);
}

function manageDnsMocks($argv) {
    $dnsMockConfig = loadDnsMockConfig();
    
    switch ($argv[1] ?? null) {
        case 'add-a':
            if (count($argv) >= 4) {
                $domain = $argv[2];
                $value = $argv[3];
                addDnsRecord($dnsMockConfig, $domain, 'A', $value);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated A record for $domain\n";
            }
            break;
        
        case 'add-mx':
            if (count($argv) >= 5) {
                $domain = $argv[2];
                $host = $argv[3];
                $priority = intval($argv[4]);
                $value = [
                    'host' => $host, 
                    'pri' => $priority
                ];
                addDnsRecord($dnsMockConfig, $domain, 'MX', $value);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated MX record for $domain\n";
            }
            break;
        
        case 'add-txt':
            if (count($argv) >= 4) {
                $domain = $argv[2];
                $value = $argv[3];
                addDnsRecord($dnsMockConfig, $domain, 'TXT', $value);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated TXT record for $domain\n";
            }
            break;
        
        case 'add-srv':
            if (count($argv) >= 7) {
                $fullDomain = $argv[2]; // e.g. _imaps._tcp.example.test
                $parts = explode('.', $fullDomain);
                $service = ltrim($parts[0], '_');
                $protocol = ltrim($parts[1], '_');
                // Remove service and protocol from domain
                array_shift($parts); // remove service
                array_shift($parts); // remove protocol
                $domain = implode('.', $parts); // example.test
                $target = $argv[3];
                $port = intval($argv[4]);
                $priority = intval($argv[5]);
                $weight = intval($argv[6]);
                $value = [
                    'service' => $service,
                    'protocol' => $protocol,
                    'target' => $target,
                    'port' => $port,
                    'pri' => $priority,
                    'weight' => $weight
                ];
                addDnsRecord($dnsMockConfig, $domain, 'SRV', $value);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated SRV record for $domain\n";
            }
            break;
        
        case 'list':
            print_r($dnsMockConfig['fake_dns_records']);
            break;
        
        case 'import':
            if (count($argv) >= 3) {
                $domain = $argv[2];
                importDnsRecords($domain);
            }
            break;

        case 'add-cname':
            if (count($argv) >= 4) {
                $domain = $argv[2];
                $target = $argv[3];
                addDnsRecord($dnsMockConfig, $domain, 'CNAME', $target);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated CNAME record for $domain\n";
            }
            break;
        
        case 'add-ns':
            if (count($argv) >= 4) {
                $domain = $argv[2];
                $nameserver = $argv[3];
                addDnsRecord($dnsMockConfig, $domain, 'NS', $nameserver);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated NS record for $domain\n";
            }
            break;
        
        case 'add-ptr':
            if (count($argv) >= 4) {
                $ip = $argv[2];
                $domain = $argv[3];
                addDnsRecord($dnsMockConfig, $ip, 'PTR', $domain);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated PTR record for $ip\n";
            }
            break;
        
        case 'add-aaaa':
            if (count($argv) >= 4) {
                $domain = $argv[2];
                $ipv6 = $argv[3];
                addDnsRecord($dnsMockConfig, $domain, 'AAAA', $ipv6);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated AAAA record for $domain\n";
            }
            break;
        
        case 'add-caa':
            if (count($argv) >= 4) {
                $domain = $argv[2];
                $caaRecord = $argv[3];
                addDnsRecord($dnsMockConfig, $domain, 'CAA', $caaRecord);
                saveDnsMockConfig($dnsMockConfig);
                echo "Updated CAA record for $domain\n";
            }
            break;

        default:
            echo "Usage:\n";
            echo "  php dns_cli.php add-a <domain> <ip>\n";
            echo "  php dns_cli.php add-mx <domain> <mail_host> <priority>\n";
            echo "  php dns_cli.php add-txt <domain> <txt_record>\n";
            echo "  php dns_cli.php add-srv <domain> <target> <port> <priority> <weight>\n";
            echo "  php dns_cli.php add-cname <domain> <target>\n";
            echo "  php dns_cli.php add-ns <domain> <nameserver>\n";
            echo "  php dns_cli.php add-ptr <ip> <domain>\n";
            echo "  php dns_cli.php add-aaaa <domain> <ipv6>\n";
            echo "  php dns_cli.php add-caa <domain> <caa_record>\n";
            echo "  php dns_cli.php list\n";
            echo "  php dns_cli.php import <domain>\n";
            break;
    }
}function importDnsRecords($domain) {
    // A records
    $aRecords = dns_get_record($domain, DNS_A);
    // MX records
    $mxRecords = dns_get_record($domain, DNS_MX);
    // TXT records
    $txtRecords = dns_get_record($domain, DNS_TXT);
    // SRV records
    $srvRecords = dns_get_record($domain, DNS_SRV);

    // CNAME records
    $cnameRecords = dns_get_record($domain, DNS_CNAME);
    // NS records
    $nsRecords = dns_get_record($domain, DNS_NS);
    // PTR records
    $ptrRecords = dns_get_record($domain, DNS_PTR);
    // AAAA records
    $aaaaRecords = dns_get_record($domain, DNS_AAAA);
    // CAA records
    $caaRecords = dns_get_record($domain, DNS_CAA);

    $dnsMockConfig = loadDnsMockConfig();

    // Import A records
    if (!empty($aRecords)) {
        foreach ($aRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['A'][] = $record['ip'];
        }
    }

    // Import MX records
    if (!empty($mxRecords)) {
        foreach ($mxRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['MX'][] = [
                'host' => $record['target'], 
                'pri' => $record['pri']
            ];
        }
    }

    // Import TXT records
    if (!empty($txtRecords)) {
        foreach ($txtRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['TXT'][] = $record['txt'];
        }
    }

    // Import SRV records
    if (!empty($srvRecords)) {
        foreach ($srvRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['SRV'][] = [
                'target' => $record['target'],
                'port' => $record['port'],
                'pri' => $record['pri'],
                'weight' => $record['weight'],
                'service' => $record['service'],
                'protocol' => $record['proto']
            ];
        }
    }

    // Import CNAME records
    if (!empty($cnameRecords)) {
        foreach ($cnameRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['CNAME'][] = $record['target'];
        }
    }

    // Import NS records
    if (!empty($nsRecords)) {
        foreach ($nsRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['NS'][] = $record['target'];
        }
    }

    // Import PTR records
    if (!empty($ptrRecords)) {
        foreach ($ptrRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$record['host']]['PTR'][] = $record['target'];
        }
    }

    // Import AAAA records
    if (!empty($aaaaRecords)) {
        foreach ($aaaaRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['AAAA'][] = $record['ipv6'];
        }
    }

    // Import CAA records
    if (!empty($caaRecords)) {
        foreach ($caaRecords as $record) {
            $dnsMockConfig['fake_dns_records'][$domain]['CAA'][] = $record['tag'] . " " . $record['value'];
        }
    }

    saveDnsMockConfig($dnsMockConfig);
    echo "Imported DNS records for $domain\n";
}
// Check if script is run directly from CLI
if (php_sapi_name() === 'cli' && $argc > 1) {
    manageDnsMocks($argv);
    exit;
}
