<?php

class ServerConfigResolver
{
    private const SERVICE_TYPE_IMAP = 'imap';
    private const SERVICE_TYPE_POP3 = 'pop3';
    private const SERVICE_TYPE_SMTP = 'smtp';
    private const SERVICE_TYPE_CALDAV = 'caldav';
    private const SERVICE_TYPE_CARDDAV = 'carddav';
    private const SERVICE_TYPE_ACTIVESYNC = 'activesync';
    private const SERVICE_TYPE_MTA_STS = 'mta-sts';
    private const SERVICE_TYPES = [
        self::SERVICE_TYPE_IMAP,
        self::SERVICE_TYPE_POP3,
        self::SERVICE_TYPE_SMTP,
        self::SERVICE_TYPE_CALDAV,
        self::SERVICE_TYPE_CARDDAV,
        self::SERVICE_TYPE_ACTIVESYNC,
        self::SERVICE_TYPE_MTA_STS

    ];

    private const LOG_LEVEL_DEBUG = 'DEBUG';
    private const LOG_LEVEL_INFO = 'INFO';
    private const LOG_LEVEL_WARNING = 'WARNING';
    private const LOG_LEVEL_ERROR = 'ERROR';

    private const SOCKET_TYPE_SSL = 'SSL';
    private const SOCKET_TYPE_STARTTLS = 'STARTTLS';
    private const SOCKET_TYPE_HTTPS = 'https';
    private const SOCKET_TYPE_PLAIN = 'plain';

    private const DEFAULT_PORT_IMAP = 993;
    private const DEFAULT_PORT_POP3 = 995;
    private const DEFAULT_PORT_SMTP = 465;
    private const DEFAULT_PORT_CALDAV = 443;
    private const DEFAULT_PORT_CARDDAV = 443;
    private const DEFAULT_PORT_ACTIVESYNC = 443;

    private const DNS_RECORD_TYPE_MAP = [
        DNS_A => 'A',
        DNS_AAAA => 'AAAA',
        DNS_CNAME => 'CNAME',
        DNS_MX => 'MX',
        DNS_NS => 'NS',
        DNS_PTR => 'PTR',
        DNS_SRV => 'SRV',
        DNS_TXT => 'TXT',
        DNS_SOA => 'SOA',
        DNS_CAA => 'CAA',
        DNS_ANY => 'ANY', // Include DNS_ANY
    ];

    // Known MX record to server mappings
    private const KNOWN_MX_MAPPINGS = [
        'google.com' => [
            self::SERVICE_TYPE_IMAP => ['hostname' => 'imap.gmail.com', 'port' => self::DEFAULT_PORT_IMAP, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_POP3 => ['hostname' => 'pop.gmail.com', 'port' => self::DEFAULT_PORT_POP3, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_SMTP => ['hostname' => 'smtp.gmail.com', 'port' => self::DEFAULT_PORT_SMTP, 'socket_type' => self::SOCKET_TYPE_SSL]
        ],
        'outlook.com' => [
            self::SERVICE_TYPE_IMAP => ['hostname' => 'outlook.office365.com', 'port' => self::DEFAULT_PORT_IMAP, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_POP3 => ['hostname' => 'outlook.office365.com', 'port' => self::DEFAULT_PORT_POP3, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_SMTP => ['hostname' => 'smtp.office365.com', 'port' => self::DEFAULT_PORT_SMTP, 'socket_type' => self::SOCKET_TYPE_SSL]
        ],
        'hostinger.com' => [
            self::SERVICE_TYPE_IMAP => ['hostname' => 'imap.hostinger.com', 'port' => self::DEFAULT_PORT_IMAP, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_POP3 => ['hostname' => 'pop.hostinger.com', 'port' => self::DEFAULT_PORT_POP3, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_SMTP => ['hostname' => 'smtp.hostinger.com', 'port' => self::DEFAULT_PORT_SMTP, 'socket_type' => self::SOCKET_TYPE_SSL]
        ],
        'zoho.com' => [
            self::SERVICE_TYPE_IMAP => ['hostname' => 'imappro.zoho.com', 'port' => self::DEFAULT_PORT_IMAP, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_POP3 => ['hostname' => 'poppro.zoho.com', 'port' => self::DEFAULT_PORT_POP3, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_SMTP => ['hostname' => 'smtppro.zoho.com', 'port' => self::DEFAULT_PORT_SMTP, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_ACTIVESYNC => ['hostname' => 'msync.zoho.com', 'port' => self::DEFAULT_PORT_ACTIVESYNC, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_CARDDAV => ['hostname' => 'contacts.zoho.com', 'port' => self::DEFAULT_PORT_CARDDAV, 'socket_type' => self::SOCKET_TYPE_SSL],
            self::SERVICE_TYPE_CALDAV => ['hostname' => 'calendar.zoho.com', 'port' => self::DEFAULT_PORT_CALDAV, 'socket_type' => self::SOCKET_TYPE_SSL],
        ],
    ];

    private $email;
    private $domain;
    private $cacheDir;
    private $cacheTTL = 86400; // 24 hours cache duration
    private $dnsCache = [];
    private $dnsCacheTTL = 600; // 10 minutes TTL for DNS cache
    private $skipCache = false;

    public function __construct($email)
    {
        $this->email = $email;
        $this->domain = substr($email, strpos($email, '@') + 1);
        $this->cacheDir = __DIR__ . '/mail_config_cache/';

        // Ensure cache directory exists
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0750, true);
        }
        $this->log("Initialized ServerConfigResolver for domain: {$this->domain}", self::LOG_LEVEL_INFO);
    }

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

        // Replace numeric DNS types with string equivalents in log messages
        $logMessage = preg_replace_callback('/DNS lookup.*? of (.*?):/', function ($matches) {
            $host = $matches[1];
            $lastLog = error_get_last(); // Get the last error message
            if ($lastLog && strpos($lastLog['message'], $host) !== false) { // Check if the last error message contains the host
                $originalMessage = $lastLog['message'];
                foreach (self::DNS_RECORD_TYPE_MAP as $dnsType => $typeString) {
                    $originalMessage = str_replace($dnsType . " record", $typeString . " record", $originalMessage);
                }
                return $originalMessage;
            }
            return $matches[0]; // Return the original match if no replacement is found
        }, $logMessage);

        error_log($logMessage);
    }
    public function resolveServerConfig($type = self::SERVICE_TYPE_IMAP)
    {
        $this->log("Resolving server configuration for type: $type", self::LOG_LEVEL_INFO);

        if (!in_array($type, self::SERVICE_TYPES)) {
            $this->log("Unsupported service type: $type", self::LOG_LEVEL_ERROR);
            throw new \InvalidArgumentException("Unsupported service type: $type");
        }

        if ($type === self::SERVICE_TYPE_MTA_STS) {
            return $this->resolveMtaStsConfig();
        }

        // First check for known MX mapping
        $mxConfig = $this->resolveKnownMXConfig($type);
        if ($mxConfig) {
            $this->log("Configuration resolved via KNOWN MX record mapping for type: $type", self::LOG_LEVEL_INFO);
            $this->log("KNOWN MX record configuration for type $type: " . json_encode($mxConfig), self::LOG_LEVEL_DEBUG);
            return $mxConfig;
        }

        // Then, check TXT record configuration
        $txtConfig = $this->resolveTXTRecordConfig($type);
        if ($txtConfig) {
            $this->log("Configuration resolved via TXT record for type: $type", self::LOG_LEVEL_INFO);
            $this->log("TXT record configuration for type $type: " . json_encode($txtConfig), self::LOG_LEVEL_DEBUG);
            return $txtConfig;
        }

        // Check SRV record configuration
        $srvConfig = $this->resolveSRVRecords($type);
        if ($srvConfig) {
            $this->log("Configuration resolved via SRV record for type: $type", self::LOG_LEVEL_INFO);
            $this->log("SRV record configuration for type $type: " . json_encode($srvConfig), self::LOG_LEVEL_DEBUG);
            return $srvConfig;
        }

        // Check ISPDB record configuration 
        $ispdbConfig = $this->resolveThunderbirdISPDB($type);
        if ($ispdbConfig) {
            $this->log("Configuration resolved via Thunderbird ISPDB for type: $type", self::LOG_LEVEL_INFO);
            $this->log("ISPD record configuration for type $type: " . json_encode($ispdbConfig), self::LOG_LEVEL_DEBUG);
            return $ispdbConfig;
        }

        // Final fallback
        $fallbackConfig = $this->getFallbackConfig($type);
        $this->log("Using fallback configuration for type: $type", self::LOG_LEVEL_WARNING);
        $this->log("Fallback record configuration for type $type: " . json_encode($fallbackConfig), self::LOG_LEVEL_DEBUG);
        return $fallbackConfig;
    }
    public function resolveSRVConfig($type = self::SERVICE_TYPE_IMAP)
    {
        $this->log("Resolving SRV configuration for type: $type", self::LOG_LEVEL_INFO);

        if (!in_array($type, self::SERVICE_TYPES)) {
            $this->log("Unsupported service type: $type", self::LOG_LEVEL_ERROR);
            throw new \InvalidArgumentException("Unsupported service type: $type");
        }

        if ($type === self::SERVICE_TYPE_MTA_STS) {
            return;
        }

        // Check SRV record configuration
        $srv = $this->SRVRecordsCheck($type);
        if (count($srv['foundRecords']) === count($srv['requiredRecords'])) {
            return ['SRVStatus' => $srv];
        }


        // First check for known MX mapping
        $mxConfig = $this->resolveKnownMXConfig($type);
        if ($mxConfig) {
            $mxConfig['SRVStatus'] = $srv;
            $this->log("Configuration resolved via KNOWN MX record mapping for type: $type", self::LOG_LEVEL_INFO);
            $this->log("KNOWN MX record configuration for type $type: " . json_encode($mxConfig), self::LOG_LEVEL_DEBUG);
            return $mxConfig;
        }

        // Check ISPDB record configuration 
        $ispdbConfig = $this->resolveThunderbirdISPDB($type);
        if ($ispdbConfig) {
            $ispdbConfig['SRVStatus'] = $srv;
            $this->log("Configuration resolved via Thunderbird ISPDB for type: $type", self::LOG_LEVEL_INFO);
            $this->log("ISPD record configuration for type $type: " . json_encode($ispdbConfig), self::LOG_LEVEL_DEBUG);
            return $ispdbConfig;
        }

        // Then, check TXT record configuration
        $txtConfig = $this->resolveTXTRecordConfig($type);
        if ($txtConfig) {
            $txtConfig['SRVStatus'] = $srv;
            $this->log("Configuration resolved via TXT record for type: $type", self::LOG_LEVEL_INFO);
            $this->log("TXT record configuration for type $type: " . json_encode($txtConfig), self::LOG_LEVEL_DEBUG);
            return $txtConfig;
        }

        // Final fallback
        $fallbackConfig = $this->getFallbackConfig($type);
        $fallbackConfig['SRVStatus'] = $srv;
        $this->log("Using fallback configuration for type: $type", self::LOG_LEVEL_WARNING);
        $this->log("Fallback record configuration for type $type: " . json_encode($fallbackConfig), self::LOG_LEVEL_DEBUG);
        return $fallbackConfig;
    }

    private function resolveTXTRecordConfig($type)
    {
        $this->log("Resolving TXT record configuration for type: $type", self::LOG_LEVEL_INFO);
        $records = $this->safeGetDnsRecord($this->domain, DNS_TXT);
        if ($records === null) {
            return null;
        }

        foreach ($records as $record) {
            $config = $this->parseTXTServerConfig($record['txt'], $type);
            if ($config) {
                $this->log("TXT record configuration found for type: $type", self::LOG_LEVEL_INFO);
                return $config;
            }
        }

        $this->log("No TXT record configuration found for type: $type", self::LOG_LEVEL_INFO);
        return null;
    }

    private function resolveKnownMXConfig($type)
    {
        $this->log("Resolving KNOWN MX record configuration for type: $type", self::LOG_LEVEL_INFO);
        $mxRecords = $this->safeGetDnsRecord($this->domain, DNS_MX);
        if ($mxRecords === null) {
            return null;
        }

        // Sort MX records by priority
        usort($mxRecords, function ($a, $b) {
            return $a['pri'] - $b['pri'];
        });

        $mxHost = $mxRecords[0]['target'];
        $domainParts = explode('.', $mxHost); // Split into domain parts
        $subdomainIndex = 1; // Start from the second-level domain (e.g., google.com)
        $fullHost = implode('.', array_slice($domainParts, -($subdomainIndex + 1))); // Start with google.com

        do {
            if (isset(self::KNOWN_MX_MAPPINGS[$fullHost][$type])) {
                $this->log("KNOWN MX record configuration found for domain: {$fullHost}, type: $type", self::LOG_LEVEL_INFO);
                $output = self::KNOWN_MX_MAPPINGS[$fullHost][$type];
                $output['method'] = 'known_mx';
                return $output;
            }

            // Increment subdomain index to add more subdomains on the next iteration
            $subdomainIndex++;

            // If we're past the first iteration, build the domain progressively
            if ($subdomainIndex > 1) {
                $fullHost = implode('.', array_slice($domainParts, -($subdomainIndex + 1)));
            }
        } while ($subdomainIndex < count($domainParts));

        $this->log("No KNOWN MX record configuration found for type: $type", self::LOG_LEVEL_INFO);
        return null;
    }

    private function parseTXTServerConfig($txtRecord, $type)
    {
        // Multiple configuration formats support
        $formats = [
            // Basic format: mailconfig-imap=imap.example.com:993:SSL
            '/^mailconfig-' . preg_quote($type) . '=([^:]+):(\d+):([A-Z]+)$/',

            // Extended format with username (without password)
            '/^mailconfig-' . preg_quote($type) . '=([^:]+):(\d+):([A-Z]+):([^:]+)$/'
        ];

        foreach ($formats as $pattern) {
            if (preg_match($pattern, $txtRecord, $matches)) {
                $config = [
                    'hostname' => $matches[1],
                    'port' => (int) $matches[2],
                    'socket_type' => $matches[3],
                    'method' => 'txt'
                ];

                // Optional: Add username if provided
                if (isset($matches[4]) && !empty($matches[4])) {
                    $config['username'] = $matches[4];
                }

                $this->log("Parsed TXT record configuration for type $type: " . json_encode($config), self::LOG_LEVEL_INFO);
                return $config;
            }
        }

        $this->log("No matching TXT record configuration format found", self::LOG_LEVEL_INFO);
        return null;
    }

    private function getCacheKey($type, $method)
    {
        return md5($this->domain . '_' . $type . '_' . $method);
    }

    private function getCachedResult($type, $method)
    {
        if ($this->skipCache) {
            $this->log("Cache is disabled. Skipping cache check for type: $type, method: $method.", self::LOG_LEVEL_DEBUG);
            return null;
        }
        $cacheFile = $this->cacheDir . $this->getCacheKey($type, $method) . '.cache';

        if (file_exists($cacheFile)) {
            $cacheData = @unserialize(file_get_contents($cacheFile));
            if ($cacheData === false) {
                $this->log("Failed to unserialize cache file for type: $type, method: $method. The file is probably corrupted.", self::LOG_LEVEL_WARNING);
                unlink($cacheFile); // Remove corrupted cache file
                return null;
            }
            // Check if cache is still valid
            if ($cacheData['timestamp'] > time() - $this->cacheTTL) {
                $this->log("Cache hit for type: $type, method: $method", self::LOG_LEVEL_INFO);
                return $cacheData['data'];
            }
        }

        $this->log("Cache miss for type: $type, method: $method", self::LOG_LEVEL_INFO);
        return null;
    }

    private function setCachedResult($type, $method, $data)
    {
        $cacheFile = $this->cacheDir . $this->getCacheKey($type, $method) . '.cache';

        $cacheData = [
            'timestamp' => time(),
            'data' => $data
        ];

        file_put_contents($cacheFile, serialize($cacheData));
        $this->log("Cached result for type: $type, method: $method", self::LOG_LEVEL_INFO);
    }
    private function SRVRecordsCheck($type)
    {
        // Check cache first
        $cachedResult = $this->getCachedResult($type, 'srv');
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $servicePrefixes = [
            self::SERVICE_TYPE_IMAP => ['_imaps._tcp.', '_imap._tcp.'],
            self::SERVICE_TYPE_POP3 => ['_pop3s._tcp.', '_pop3._tcp.'],
            self::SERVICE_TYPE_SMTP => ['_smtps._tcp.', '_smtp._tcp.', '_submission._tcp.'],
            self::SERVICE_TYPE_CALDAV => ['_caldavs._tcp.', '_caldav._tcp.'],
            self::SERVICE_TYPE_CARDDAV => ['_carddavs._tcp.', '_carddav._tcp.'],
            self::SERVICE_TYPE_ACTIVESYNC => ['_autodiscover._tcp.']

        ][$type];

        $srvRecords = [
            'requiredRecords' => $servicePrefixes,
            'foundRecords' => []
        ];
        foreach ($servicePrefixes as $servicePrefix) {
            $service = $servicePrefix . $this->domain;
            $records = $this->safeGetDnsRecord($service, DNS_SRV);
            if ($records !== null) {
                $srvRecords['foundRecords'][] = $servicePrefix;
            }
        }
        if (count($srvRecords['foundRecords']) !== count($srvRecords['requiredRecords'])) {
            $this->log("Missing SRV Records", self::LOG_LEVEL_INFO);
        }
        return $srvRecords;
    }

    private function resolveSRVRecords($type)
    {
        // Check cache first
        $cachedResult = $this->getCachedResult($type, 'srv');
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $servicePrefixes = [
            self::SERVICE_TYPE_IMAP => ['_imaps._tcp.', '_imap._tcp.'],
            self::SERVICE_TYPE_POP3 => ['_pop3s._tcp.', '_pop3._tcp.'],
            self::SERVICE_TYPE_SMTP => ['_smtps._tcp.', '_smtp._tcp.', '_submission._tcp.'],
            self::SERVICE_TYPE_CALDAV => ['_caldavs._tcp.', '_caldav._tcp.'],
            self::SERVICE_TYPE_CARDDAV => ['_carddavs._tcp.', '_carddav._tcp.'],
            self::SERVICE_TYPE_ACTIVESYNC => ['_autodiscover._tcp.']

        ][$type];

        $records = null;
        foreach ($servicePrefixes as $servicePrefix) {
            $service = $servicePrefix . $this->domain;
            $records = $this->safeGetDnsRecord($service, DNS_SRV);
            if ($records !== null) {
                break;
            }
        }
        if ($records === null) {
            return null;
        }

        $this->log('DNS Output: ' . json_encode($records), self::LOG_LEVEL_DEBUG);

        $record = $records[0];
        $result = [
            'hostname' => $record['target'],
            'port' => $record['port'],
            'socket_type' => $this->determineSocketType($servicePrefix),
            'method' => 'srv'
        ];

        // Cache the result
        $this->setCachedResult($type, 'srv', $result);
        $this->log("SRV record configuration resolved for type: $type", self::LOG_LEVEL_INFO);
        return $result;
    }


    private function determineSocketType($servicePrefix)
    {
        switch ($servicePrefix) {
            case '_imaps._tcp.':
            case '_pop3s._tcp.':
            case '_smtps._tcp.':
            case '_imap._tcp.':
            case '_pop3._tcp.':
            case '_smtp._tcp.':
                return self::SOCKET_TYPE_SSL;
            case '_submission._tcp.':
                return self::SOCKET_TYPE_STARTTLS;
            case '_autodiscover._tcp.':
            case '_caldavs._tcp.':
            case '_carddavs._tcp.':
            case '_caldav._tcp.':
            case '_carddav._tcp.':
                return self::SOCKET_TYPE_HTTPS;
            default:
                return self::SOCKET_TYPE_PLAIN;
        }
    }

    private function resolveThunderbirdISPDB($type)
    {
        $this->log("Resolving Thunderbird ISPDB configuration for type: $type", self::LOG_LEVEL_INFO);
        if ($type === self::SERVICE_TYPE_ACTIVESYNC) {
            $this->log("No ISPDB configuration available for type: $type", self::LOG_LEVEL_WARNING);
            return null;
        }
        // Retrieve MX records for the current domain
        $mxRecords = $this->safeGetDnsRecord($this->domain, DNS_MX);
        if ($mxRecords === null) {
            return null;
        }

        // Check cache first
        $cachedResult = $this->getCachedResult($type, 'ispdb_' . $this->domain);
        if ($cachedResult !== null) {
            $this->log("ISPDB configuration resolved from cache for type: $type", self::LOG_LEVEL_INFO);
            return $cachedResult;
        }


        // Sort MX records by priority
        usort($mxRecords, function ($a, $b) {
            return $a['pri'] - $b['pri'];
        });

        $mxHost = $mxRecords[0]['target'];
        $domainParts = explode('.', $mxHost); // Split into domain parts
        $subdomainIndex = 1; // Start from the second-level domain (e.g., google.com)
        $fullHost = implode('.', array_slice($domainParts, -($subdomainIndex + 1))); // Start with google.com

        do {
            // Construct the URL using the progressively built full domain
            $url = "https://autoconfig.thunderbird.net/v1.1/{$fullHost}";
            $this->log("Attempting to fetch ISPDB configuration from: $url", self::LOG_LEVEL_INFO);
            $response = @file_get_contents($url);

            // Increment subdomain index to add more subdomains on the next iteration
            $subdomainIndex++;

            // If we're past the first iteration, build the domain progressively
            if ($subdomainIndex > 1) {
                $fullHost = implode('.', array_slice($domainParts, -($subdomainIndex + 1)));
            }
        } while (!$response && $subdomainIndex < count($domainParts));



        if ($response) {
            $xml = @simplexml_load_string($response);
            if ($xml === false) {
                $this->log("Failed to parse XML response from ISPDB", self::LOG_LEVEL_WARNING);
                return null;
            }
            $serverType = [
                self::SERVICE_TYPE_IMAP => 'incomingServer[@type="imap"]',
                self::SERVICE_TYPE_POP3 => 'incomingServer[@type="pop3"]',
                self::SERVICE_TYPE_SMTP => 'outgoingServer[@type="smtp"]',
                self::SERVICE_TYPE_CALDAV => 'calendarServer',
                self::SERVICE_TYPE_CARDDAV => 'addressBookServer'
            ][$type];

            $server = $xml->xpath("//clientConfig/emailProvider/{$serverType}");
            if (!empty($server)) {
                $server = $server[0];
                $result = [
                    'hostname' => (string) $server->hostname,
                    'port' => isset($server->port) ? (int) $server->port : $this->getDefaultPort($type),
                    'socket_type' => isset($server->socketType) ? (string) $server->socketType : self::SOCKET_TYPE_SSL,
                    'method' => 'ispdb'
                ];

                // Cache the result
                $this->setCachedResult($type, 'ispdb_' . $this->domain, $result);
                $this->log("ISPDB configuration resolved for type: $type", self::LOG_LEVEL_INFO);
                return $result;
            }
        }
        // No configuration found
        $this->log("No ISPDB configuration found for type: $type", self::LOG_LEVEL_INFO);
        return null;
    }

    private function getDefaultPort($type)
    {
        switch ($type) {
            case self::SERVICE_TYPE_IMAP:
                return self::DEFAULT_PORT_IMAP;
            case self::SERVICE_TYPE_POP3:
                return self::DEFAULT_PORT_POP3;
            case self::SERVICE_TYPE_SMTP:
                return self::DEFAULT_PORT_SMTP;
            case self::SERVICE_TYPE_CALDAV:
                return self::DEFAULT_PORT_CALDAV;
            case self::SERVICE_TYPE_CARDDAV:
                return self::DEFAULT_PORT_CARDDAV;
            case self::SERVICE_TYPE_ACTIVESYNC:
                return self::DEFAULT_PORT_ACTIVESYNC;
            default:
                return self::DEFAULT_PORT_IMAP;
        }
    }

    private function getFallbackConfig($type)
    {
        $this->log("Using getFallbackConfig for type: $type", self::LOG_LEVEL_INFO);

        // Skip for CalDAV, CardDAV, and Activesync
        if ($type === self::SERVICE_TYPE_CALDAV || $type === self::SERVICE_TYPE_CARDDAV || $type === self::SERVICE_TYPE_ACTIVESYNC) {
            $this->log("No fallback configuration available for type: $type", self::LOG_LEVEL_WARNING);
            return null;
        }
        // Check if a known MX mapping could have provided a config (preventing multiple fallbacks)
        $mxConfig = $this->resolveKnownMXConfig($type);
        if ($mxConfig) {
            $this->log("Fallback bypassed because KNOWN MX configuration could be resolved for type: $type", self::LOG_LEVEL_WARNING);
            return $mxConfig;
        }

        $fallbackConfig = [
            'hostname' => "{$type}." . $this->domain,
            'port' => $this->getDefaultPort($type),
            'socket_type' => self::SOCKET_TYPE_SSL,
            'method' => 'fallback'
        ];

        $this->log("Generic fallback configuration applied for type: $type - " . json_encode($fallbackConfig), self::LOG_LEVEL_INFO);
        return $fallbackConfig;
    }

    // Optional: Method to clear expired cache
    public function clearExpiredCache()
    {
        $files = glob($this->cacheDir . '*.cache');
        $now = time();

        foreach ($files as $file) {
            $cacheData = unserialize(file_get_contents($file));
            if ($cacheData['timestamp'] < $now - $this->cacheTTL) {
                unlink($file);
            }
        }
    }
    private function safeGetDnsRecord($host, $type)
    {
        $typeString = self::DNS_RECORD_TYPE_MAP[$type] ?? $type; // Get string representation or fallback to original value
        if ($this->skipCache) {
            $records = @get_dns_record($host, $type);
            $this->log('Got records for ' . $host . ' ' . $typeString . ': ' . json_encode($records), self::LOG_LEVEL_DEBUG);
            if ($records === false || empty($records) || count($records) === 0) {
                $error = error_get_last();
                $this->log("DNS lookup failed for {$typeString} record of {$host}: " . $error['message'], self::LOG_LEVEL_ERROR);
                return null;
            } else {
                $this->log("DNS lookup successful for {$typeString} record of {$host}", self::LOG_LEVEL_DEBUG);
                return $records;
            }
        }
        $cacheKey = $host . '_' . $type;

        // Check if the result is in the cache
        if (isset($this->dnsCache[$cacheKey]) && $this->dnsCache[$cacheKey]['timestamp'] > time() - $this->dnsCacheTTL) {
            $this->log("DNS cache hit for {$typeString} record of {$host}", self::LOG_LEVEL_DEBUG);
            if ($this->dnsCache[$cacheKey]['data'] === null) {
                $this->log("Cached DNS response indicates no {$typeString} record for domain {$host}.", self::LOG_LEVEL_DEBUG);
                return null;
            }
            return $this->dnsCache[$cacheKey]['data'];
        }

        $records = @get_dns_record($host, $type);

        if ($records === false) {
            $error = error_get_last();
            $this->log("DNS lookup failed for {$typeString} record of {$host}: " . $error['message'], self::LOG_LEVEL_ERROR);

            // Store the failed lookup (null) in the cache
            $this->dnsCache[$cacheKey] = [
                'timestamp' => time(),
                'data' => null,
            ];
            return null;
        }
        if (empty($records) || count($records) === 0) {
            $this->log("No {$typeString} record found for domain {$host}.", self::LOG_LEVEL_INFO);
            // Store the fact that there was no record in the cache
            $this->dnsCache[$cacheKey] = [
                'timestamp' => time(),
                'data' => null,
            ];
            return null;
        }

        // Store the records in the cache
        $this->dnsCache[$cacheKey] = [
            'timestamp' => time(),
            'data' => $records
        ];
        $this->log("DNS cache miss for {$typeString} record of {$host}", self::LOG_LEVEL_DEBUG);
        return $records;
    }
    private function resolveMtaStsConfig()
    {
        $this->log("Resolving MTA-STS configuration", self::LOG_LEVEL_INFO);

        // Check cache first
        $cachedResult = $this->getCachedResult(self::SERVICE_TYPE_MTA_STS, 'txt');
        if ($cachedResult !== null) {
            $this->log("Using cached MTA-STS configuration", self::LOG_LEVEL_INFO);
            return $cachedResult;
        }

        $records = $this->safeGetDnsRecord($this->domain, DNS_TXT); // No _mta-sts prefix here
        if ($records === null) {
            return null;
        }

        $config = [];
        foreach ($records as $record) {
            // mailconfig-mtasts=mx1.example.com,mx2.example.com:enforce:604800
            if (preg_match('/^mailconfig-mtasts=([^:]+):([^:]+):(\d+)$/', $record['txt'], $matches)) {
                $mxHosts = explode(',', $matches[1]);
                $config['mode'] = $matches[2];
                $config['max_age'] = (int) $matches[3];
                if (count($mxHosts) > 1 || !filter_var($mxHosts[0], FILTER_VALIDATE_EMAIL)) {
                    $config['mx'] = $mxHosts;
                }
                $this->log("MTA-STS configuration found (format 1): " . json_encode($config), self::LOG_LEVEL_INFO);
                break; // Found a valid config, no need to continue
            }

            // mailconfig-mtasts=enforce:604800
            if (preg_match('/^mailconfig-mtasts=([^:]+):(\d+)$/', $record['txt'], $matches)) {
                $config['mode'] = $matches[1];
                $config['max_age'] = (int) $matches[2];
                $this->log("MTA-STS configuration found (format 2): " . json_encode($config), self::LOG_LEVEL_INFO);
                break; // Found a valid config, no need to continue
            }
        }

        if (!empty($config)) {
            $config['method'] = 'txt';
            $this->setCachedResult(self::SERVICE_TYPE_MTA_STS, 'txt', $config);
            return $config;
        }

        return null;
    }

    /**
     * Disables the Cache
     *
     * @return void
     */
    public function disableCache(): void
    {
        $this->skipCache = true;
    }

    /**
     * Enables the Cache
     *
     * @return void
     */
    public function enableCache(): void
    {
        $this->skipCache = false;
    }
}
