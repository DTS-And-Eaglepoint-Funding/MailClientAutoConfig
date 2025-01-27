<?php

if (!function_exists('get_dns_record')) {
    function get_dns_record($hostname, $type = DNS_A) {
        return dns_get_record($hostname, $type);
    }
}

class DnsCacheResolver {

    const DNS_CACHE_CONFIG_PATH = __DIR__ . '/dns_cache.php';
    const DEFAULT_TTL = 600; // 10 minutes in seconds

    /**
     * Logs a message to the PHP error log with a timestamp, level, and class name.
     *
     * @param string $message The message to log.
     * @param string $level The log level (e.g., 'INFO', 'WARNING', 'ERROR').
     */
    protected function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] [" . get_class($this) . "] $message";
        error_log($logMessage);
    }

    private function loadDnsCacheConfig()
    {
        if (!file_exists(self::DNS_CACHE_CONFIG_PATH)) {
            try {
                file_put_contents(self::DNS_CACHE_CONFIG_PATH, "<?php\nreturn ['cached_dns_records' => []];");
            } catch (Exception $e) {
                $this->log("Error creating config file: {$e->getMessage()}", 'ERROR');
                return ['cached_dns_records' => []];
            }
        }

        try {
            $config = require self::DNS_CACHE_CONFIG_PATH;
        } catch (Exception $e) {
            $this->log("Error loading config file: {$e->getMessage()}", 'ERROR');
            return ['cached_dns_records' => []];
        }
        return $config;
    }

    private function addDnsRecord(&$dnsCacheConfig, $domain, $recordType, $value, $ttl = self::DEFAULT_TTL)
    {
        if ($ttl < 0) {
            $this->log("Invalid TTL value: $ttl. Using default TTL of " . self::DEFAULT_TTL . " seconds.", 'WARNING');
            $ttl = self::DEFAULT_TTL;
        }
        // Ensure the record type array exists
        if (!isset($dnsCacheConfig['cached_dns_records'][$domain][$recordType])) {
            $dnsCacheConfig['cached_dns_records'][$domain][$recordType] = [];
        }

        $recordData = ['value' => $value, 'expires' => time() + $ttl];
        switch ($recordType) {
            case 'A':
            case 'AAAA':
            case 'TXT':
            case 'NS':
            case 'CAA':
                $exists = false;
                foreach ($dnsCacheConfig['cached_dns_records'][$domain][$recordType] as $record) {
                    if ($record['value'] === $value) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $dnsCacheConfig['cached_dns_records'][$domain][$recordType][] = $recordData;
                }
                return true;

            case 'MX':
                $exists = false;
                foreach ($dnsCacheConfig['cached_dns_records'][$domain]['MX'] ?? [] as &$record) {
                    if ($record['value']['host'] === $value['host']) {
                        $record = $recordData;
                        $record['value'] = $value;
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $dnsCacheConfig['cached_dns_records'][$domain]['MX'][] = $recordData;
                    $dnsCacheConfig['cached_dns_records'][$domain]['MX'][count($dnsCacheConfig['cached_dns_records'][$domain]['MX']) - 1]['value'] = $value;
                }
                return true;
            case 'CNAME':
                $dnsCacheConfig['cached_dns_records'][$domain][$recordType] = [$recordData];
                return true;

            case 'PTR':
                $exists = false;
                foreach ($dnsCacheConfig['cached_dns_records'][$domain]['PTR'] ?? [] as $record) {
                    if ($record['value'] === $value) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $dnsCacheConfig['cached_dns_records'][$domain]['PTR'][] = $recordData;
                }
                return true;

            case 'SRV':
                $exists = false;
                foreach ($dnsCacheConfig['cached_dns_records'][$domain]['SRV'] ?? [] as &$record) {
                    if (
                        $record['value']['service'] === $value['service'] &&
                        $record['value']['protocol'] === $value['protocol']
                    ) {
                        $record = $recordData;
                        $record['value'] = $value;
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $dnsCacheConfig['cached_dns_records'][$domain]['SRV'][] = $recordData;
                    $dnsCacheConfig['cached_dns_records'][$domain]['SRV'][count($dnsCacheConfig['cached_dns_records'][$domain]['SRV']) - 1]['value'] = $value;
                }
                return true;
        }
        return false;
    }

    private function saveDnsCacheConfig($config)
    {
        ksort($config['cached_dns_records']);

        foreach ($config['cached_dns_records'] as $domain => &$records) {
            ksort($records);

            foreach ($records as $type => $values) {
                if (empty($values)) {
                    unset($records[$type]);
                    continue;
                }

                if (in_array($type, ['A', 'AAAA', 'TXT', 'NS', 'CAA', 'PTR'])) {
                    usort($records[$type], fn($a, $b) => $a['value'] <=> $b['value']);
                }

                if ($type === 'MX') {
                    usort($records[$type], fn($a, $b) => $a['value']['pri'] <=> $b['value']['pri']);
                }
                if ($type === 'SRV') {
                    usort(
                        $records[$type],
                        fn($a, $b) =>
                        $a['value']['service'] <=> $b['value']['service'] ?:
                            $a['value']['protocol'] <=> $b['value']['protocol'] ?:
                            $a['value']['pri'] <=> $b['value']['pri']
                    );
                }
            }

            if (empty($records)) {
                unset($config['cached_dns_records'][$domain]);
            }
        }

        $configString = "<?php\nreturn " . var_export($config, true) . ";";
        $configString = preg_replace_callback('/(\s+)\[(\d+)\]\s*=>\s*/', function ($matches) {
            return $matches[1];
        }, $configString);
        $configString = str_replace(['array (', ')'], ['[', ']'], $configString);
        $configString = preg_replace_callback('/^(  +)/m', function ($matches) {
            return str_repeat(' ', strlen($matches[1]) * 2);
        }, $configString);

        try {
            file_put_contents(self::DNS_CACHE_CONFIG_PATH, $configString);
        } catch (Exception $e) {
            $this->log("Error saving config file: {$e->getMessage()}", 'ERROR');
        }
    }

    public function resolve($host, $type)
    {
        $dnsCacheConfig = $this->loadDnsCacheConfig();

        if (isset($dnsCacheConfig['cached_dns_records'][$host][$type])) {
            $formattedRecords = [];
            foreach ($dnsCacheConfig['cached_dns_records'][$host][$type] as $record) {
                if ($record['expires'] > time()) {
                    if ($type === 'SRV') {
                        $formattedRecords[] = [
                            'host' => $host,
                            'type' => 'SRV',
                            'target' => $record['value']['target'],
                            'port' => $record['value']['port'],
                            'pri' => $record['value']['pri'],
                            'weight' => $record['value']['weight'],
                            'service' => $record['value']['service'],
                            'proto' => $record['value']['protocol']
                        ];
                    } else {
                        $formattedRecords[] = $record['value'];
                    }
                }
            }
            if (!empty($formattedRecords)) {
                return $formattedRecords;
            }
        }

        $imported = $this->importDnsRecords($host, $type, $dnsCacheConfig);

        if ($imported) {
            $this->saveDnsCacheConfig($dnsCacheConfig);
            if (isset($dnsCacheConfig['cached_dns_records'][$host][$type])) {
                $formattedRecords = [];
                foreach ($dnsCacheConfig['cached_dns_records'][$host][$type] as $record) {
                    if ($record['expires'] > time()) {
                        if ($type === 'SRV') {
                            $formattedRecords[] = [
                                'host' => $host,
                                'type' => 'SRV',
                                'target' => $record['value']['target'],
                                'port' => $record['value']['port'],
                                'pri' => $record['value']['pri'],
                                'weight' => $record['value']['weight'],
                                'service' => $record['value']['service'],
                                'proto' => $record['value']['protocol']
                            ];
                        } else {
                            $formattedRecords[] = $record['value'];
                        }
                    }
                }
                if (!empty($formattedRecords)) {
                    return $formattedRecords;
                }
            }
        }

        return false;
    }

    private function importDnsRecords($domain, $type = null, &$dnsCacheConfig)
    {
        $recordTypes = [
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

        if ($type !== null) {
            $recordTypes = array_filter($recordTypes, function ($recordType) use ($type) {
                return $recordType === $type;
            });
        }

        $imported = false;
        foreach ($recordTypes as $dnsType => $recordType) {
            try {
                $records = @get_dns_record($domain, $dnsType);
            } catch (Exception $e) {
                $this->log("Error getting DNS record type $recordType for $domain: " . $e->getMessage(), 'ERROR');
                continue;
            }

            if (!empty($records)) {
                foreach ($records as $record) {
                    $ttl = $record['ttl'] ?? self::DEFAULT_TTL;
                    $value = match ($recordType) {
                        'A' => $record['ip'],
                        'AAAA' => $record['ipv6'],
                        'MX' => ['host' => $record['target'], 'pri' => $record['pri']],
                        'TXT' => $record['txt'],
                        'SRV' => [
                            'target' => $record['target'],
                            'port' => $record['port'],
                            'pri' => $record['pri'],
                            'weight' => $record['weight'],
                            'service' => $record['service'],
                            'protocol' => $record['proto']
                        ],
                        'CNAME', 'NS' => $record['target'],
                        'PTR' => $record['target'],
                        'CAA' => $record['tag'] . " " . $record['value']
                    };
                    $this->addDnsRecord($dnsCacheConfig, $domain, $recordType, $value, $ttl);
                    $imported = true;
                }
            }
        }
        return $imported;
    }
}
