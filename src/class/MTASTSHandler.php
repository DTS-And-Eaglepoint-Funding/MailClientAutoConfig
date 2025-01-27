<?php


class MTASTSHandler extends RequestHandler
{
    private const DEFAULT_MAX_AGE = 604800; // 1 week in seconds
    private const DEFAULT_MODE = 'none';
    private const TXT_RECORD_PREFIX = '_mta-sts.';
    private const ALLOWED_MODES = ['enforce', 'testing', 'none'];

    private $max_age;
    private $mode;
    private $domain;

    /**
     * MTASTSHandler constructor.
     */
    public function __construct() {}

    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request(): object
    {
        $domain = isset($_GET['domain']) ? $_GET['domain'] : $_SERVER['SERVER_NAME'];
        if (strpos($domain, "mta-sts.") === 0) {
            $domain = substr($domain, 8);
        }
        $this->domain = $domain;
        $this->log("Parsed domain: " . $domain, 'DEBUG');
        return (object)['domain' => $domain, 'email' => 'null@' . $domain];
    }

    /**
     * @param string $authentication
     * @return string|null|false
     */
    protected function map_authentication_type(string $authentication)
    {
        return null; // Not applicable for MTA-STS
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
        // This method is not used anymore, but we keep it for compatibility
    }

    /*
      * @param DomainConfiguration $config
      * @param stdClass $request
      *
      * @return string
      */
    protected function write_response(DomainConfiguration $config, stdClass $request)
    {
        $this->log("Starting to write MTA-STS text response.", 'DEBUG');


        header("Content-Type: text/plain");
        
        $server = null;
        $mtasts_mode = self::DEFAULT_MODE;
        $mtasts_max_age = self::DEFAULT_MAX_AGE;
        $mtasts_mx_mode = 'dns';
        $mtasts_mx = [];
        $debug_output = "";


        foreach ($config->get_servers() as $this_server) {
            if ($this_server->type != 'mta-sts') {
                $this->log("Skipping server type: " . $this_server->type, 'DEBUG');
                continue;
            }
            $this->log("Found MTA-STS server", 'DEBUG');
            $server = $this_server;
            break;
        }
        if ($server === null) {
            $this->log("No MTA-STS server found for domain: " . $this->domain, 'DEBUG');
            $this->log("Checking if we can build one from ServerConfigResolver", 'DEBUG');
            $serverConfigResolver = new ServerConfigResolver('null@' . $this->domain);
            if (isset($_GET['debug'])) {
                $this->log("ServerConfigResolver cache disable", 'DEBUG');
                $serverConfigResolver->disableCache();
            }
            $server_mtaStsConfig = $serverConfigResolver->resolveServerConfig('mta-sts');
            $this->log("MTA-STS config from ServerConfigResolver: " . json_encode($server_mtaStsConfig), 'DEBUG');
            if ($server_mtaStsConfig !== null) {
                $mtasts_mode = $server_mtaStsConfig['mode'];
                $debug_output .= "\$server_mtaStsConfig['mode']: " . $server_mtaStsConfig['mode'] . "\n";
                $mtasts_max_age = $server_mtaStsConfig['max_age'];
                if (isset($server_mtaStsConfig['mx']) && is_array($server_mtaStsConfig['mx']) && !empty($server_mtaStsConfig['mx'])) {
                    $mtasts_mx = $server_mtaStsConfig['mx'];
                    $mtasts_mx_mode = 'txt';
                    $this->log("Found MX records for MTA-STS server for domain: " . $this->domain, 'DEBUG');
                }else{
                    $this->log("No MX record found for MTA-STS server for domain in the txt records: " . $this->domain, 'DEBUG');
                }
            }
        } else {
            $mtasts_mode = $server->get_mta_sts_mode();
            $mtasts_max_age = $server->get_mta_sts_max_age();
            $config_mx = $server->get_mta_sts_mx_records();
            if (count($config_mx) > 0) {
                $mtasts_mx = $config_mx;
                $mtasts_mx_mode = 'config';
            } else {
                $this->log("No MX record found for MTA-STS server for domain in the config: " . $this->domain, 'DEBUG');
            }
        }
        if ($mtasts_mx_mode == 'dns') {
            $mtasts_mx = $this->getMXRecords($this->domain);
        }

        if (empty($mtasts_mx)) {
            $mtasts_mode = self::DEFAULT_MODE;
        }
        $output = "version: STSv1\n";
        if (isset($_GET['debug_output'])) {
            $output .= "debug: true\n";
            $output .= "url: " . $this->domain . "\n";
            $output .= "mx_mode: ". $mtasts_mx_mode. "\n";
            $output .= $debug_output;
        }
        $this->log("MTA-STS Mode: " . $mtasts_mode, 'DEBUG');
        if (empty($mtasts_mode) || !in_array($mtasts_mode, self::ALLOWED_MODES)) {
            $this->log("Invalid MTA-STS mode: ". $mtasts_mode . " Using Default", 'DEBUG');
            $mtasts_mode = self::DEFAULT_MODE;
        }

        $output .= "mode: " . $mtasts_mode . "\n";

        $this->log("MTA-STS Max Age: " . $mtasts_max_age, 'DEBUG');
        if ($mtasts_max_age === null||$mtasts_max_age <= 0) {
            $mtasts_max_age = self::DEFAULT_MAX_AGE;
        }
        $output.= "max_age: " . $mtasts_max_age. "\n";

        $this->log("MTA-STS MX Records: ". json_encode($mtasts_mx), 'DEBUG');
        if (!empty($mtasts_mx)) {
            foreach ($mtasts_mx as $mx) {
                $output .= "mx: " . $mx . "\n";
            }
        }

        $this->log("MTA-STS text response created.", 'DEBUG');
        return $output;
    }

    /**
     * @param string $domain
     * @return array
     */
    private function getMXRecords(string $domain): array
    {
        $this->log("Getting MX records for domain: " . $domain, 'DEBUG');
        $records = get_dns_record($domain, DNS_MX);
        $mxHosts = [];
        if ($records) {
            foreach ($records as $record) {
                $mxHosts[] = $record['target'];
            }
            $this->log("MX records found: " . implode(', ', $mxHosts), 'DEBUG');
        } else {
            $this->log("No MX records found for domain: " . $domain, 'WARNING');
        }
        return $mxHosts;
    }


    /**
     * @param stdClass $request
     * @return DomainConfiguration|null
     * @throws Exception
     */
    protected function get_domain_config_bak(stdClass $request)
    {
        $this->log("Starting to get domain configuration.", 'DEBUG');
        static $cachedEmail = null;
        static $cached_config = null;


        if ($cachedEmail === $this->domain) {
            $this->log("Using cached configuration for domain: " . $this->domain, 'DEBUG');
            return $cached_config;
        }

        $this->log("Reading configuration file.", 'DEBUG');
        $cached_config = $this->read_config($request);
        $this->log("Configuration file read successfully.", 'DEBUG');


        try {
            $domainConfig = $cached_config->get_domain_config($this->domain);
            $this->log("Domain configuration retrieved successfully for domain: " . $this->domain . " config id: " . $domainConfig->get_id(), 'DEBUG'); // Added log
        } catch (Exception $e) {
            $this->log("No domain configuration found for domain: " . $this->domain . ". Attempting to create dynamic config", 'WARNING');
            $domainConfig = null;
        }

        // If no domain config found, attempt to create a dynamic configuration
        if ($domainConfig === null) {
            $this->log("Creating dynamic configuration for domain: " . $this->domain, 'DEBUG');

            $domainConfig = $cached_config->add($this->domain);
            $domainConfig->set_domains([$this->domain, $_SERVER['SERVER_NAME']]);
            $this->log("Setting domains for dynamic configuration: " . $this->domain . ", " . $_SERVER['SERVER_NAME'], 'DEBUG');
            $domainConfig->set_name("Dynamic Configuration for {$this->domain}");
            $this->log("Setting name for dynamic configuration: Dynamic Configuration for " . $this->domain, 'DEBUG');
        }

        $cachedEmail = $this->domain;
        $this->log("Finished getting domain configuration for domain: " . $this->domain, 'DEBUG');
        return $domainConfig;
    }
}
