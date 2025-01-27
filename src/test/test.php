<?php
// router.php with dynamic DNS mocking and favicon serving
error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// Load DNS mock configuration
$DNS_MOCK_CONFIG_PATH = __DIR__ . '/test-dns.php';
$FAVICON_PATH = __DIR__ . '/../favicon.ico'; // Path to your favicon.ico file

// Function to load DNS mock configuration
function loadDnsMockConfig()
{
    global $DNS_MOCK_CONFIG_PATH;

    if (!file_exists($DNS_MOCK_CONFIG_PATH)) {
        file_put_contents($DNS_MOCK_CONFIG_PATH, "<?php\nreturn ['fake_dns_records' => []];");
    }

    return require $DNS_MOCK_CONFIG_PATH;
}

// Custom DNS resolution function
function mock_dns_lookup($hostname, $type = DNS_A)
{
    // Reload configuration each time to catch live changes
    $dnsMockConfig = loadDnsMockConfig();

    // Comprehensive DNS record type mapping
    $typeMap = [
        DNS_A => 'A',
        DNS_MX => 'MX',
        DNS_TXT => 'TXT',
        DNS_SRV => 'SRV',
        DNS_CNAME => 'CNAME',
        DNS_NS => 'NS',
        DNS_PTR => 'PTR',
        DNS_AAAA => 'AAAA',
        DNS_CAA => 'CAA'
    ];
    // echo "mock_dns_lookup called with hostname: $hostname, type: $typeMap[$type]\n";

    // Check if mock configuration exists for hostname

    $recordType = $typeMap[$type] ?? null;

    //handle SRV lookups
    if ($recordType === 'SRV') {
        foreach ($dnsMockConfig['fake_dns_records'] ?? [] as $domain => $records) {
            if (strpos($hostname, $domain) !== false) {
                if (isset($records['SRV'])) {
                    $formattedRecords = [];
                    foreach ($records['SRV'] as $record) {
                        if ($record['service'] === ltrim(explode('.', $hostname)[0], '_') && $record['protocol'] === ltrim(explode('.', $hostname)[1], '_')) {
                            $formattedRecords[] = [
                                'host' => $hostname,
                                'type' => 'SRV',
                                'target' => $record['target'],
                                'port' => $record['port'],
                                'pri' => $record['pri'],
                                'weight' => $record['weight'],
                                'service' => $record['service'],
                                'proto' => $record['protocol']
                            ];
                        }
                    }

                    if (!empty($formattedRecords)) {
                        error_log("[DNS Mock] Using fake {$recordType} record for {$hostname}: " . json_encode($formattedRecords), 4);
                        return $formattedRecords;
                    }
                }
            }
        }
    }

    if (!empty($dnsMockConfig['fake_dns_records'][$hostname])) {
        $mockRecords = $dnsMockConfig['fake_dns_records'][$hostname];


        // Return mock records if available
        if ($recordType && isset($mockRecords[$recordType])) {
            // Transform records to match PHP's dns_get_record() format
            $formattedRecords = [];
            foreach ($mockRecords[$recordType] as $record) {
                switch ($recordType) {
                    case 'A':
                        $formattedRecords[] = ['ip' => $record, 'type' => 'A', 'host' => $hostname];
                        break;
                    case 'AAAA':
                        $formattedRecords[] = ['ipv6' => $record, 'type' => 'AAAA', 'host' => $hostname];
                        break;
                    case 'MX':
                        $formattedRecords[] = [
                            'host' => $hostname,
                            'type' => 'MX',
                            'target' => $record['host'],
                            'pri' => $record['pri']
                        ];
                        break;
                    case 'TXT':
                        $formattedRecords[] = ['txt' => $record, 'type' => 'TXT', 'host' => $hostname];
                        break;

                    case 'CNAME':
                        $formattedRecords[] = ['target' => $record, 'type' => 'CNAME', 'host' => $hostname];
                        break;
                    case 'NS':
                        $formattedRecords[] = ['target' => $record, 'type' => 'NS', 'host' => $hostname];
                        break;
                    case 'PTR':
                        $formattedRecords[] = ['target' => $record, 'type' => 'PTR', 'host' => $hostname];
                        break;
                    case 'CAA':
                        // Split CAA record into tag and value
                        $parts = explode(' ', $record, 2);
                        $formattedRecords[] = [
                            'tag' => $parts[0],
                            'value' => $parts[1] ?? '',
                            'type' => 'CAA',
                            'host' => $hostname
                        ];
                        break;
                }
            }

            // Log the fake DNS record usage
            error_log("[DNS Mock] Using fake {$recordType} record for {$hostname}: " . json_encode($formattedRecords), 4);

            return $formattedRecords;
        }
    }

    // If no mock found, attempt real DNS lookup
    try {
        error_log("[DNS Mock] Using real DNS lookup for {$hostname}");
        return @dns_get_record($hostname, $type);
    } catch (Exception $e) {
        error_log("DNS Lookup failed: " . $e->getMessage());
        return [];
    }
}
// Serve static files directly
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . $_SERVER['REQUEST_URI'])) {
    return false;
}


// Check if favicon is requested
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/favicon.ico') {
    if (file_exists($FAVICON_PATH)) {
        header('Content-Type: image/x-icon');
        readfile($FAVICON_PATH);
        exit;
    } else {
        error_log("[ERROR] favicon.ico not found at path: $FAVICON_PATH", 0);
        http_response_code(404);
        exit; // Or optionally send a default icon
    }
}


// Override DNS resolution with mock version
if (!function_exists('get_dns_record')) {
    function get_dns_record($hostname, $type = DNS_A)
    {
        return mock_dns_lookup($hostname, $type);
    }
}


// Route all other requests to your main script
require __DIR__ . '/../index.php';