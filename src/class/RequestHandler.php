<?php

abstract class RequestHandler
{

    public function __toString()
    {
        return get_class($this);
    }

    /**
     * @return object
     * @throws Exception
     */
    protected abstract function parse_request();

    /**
     * @param string $authentication
     *
     * @return string|null|false
     */
    protected abstract function map_authentication_type(string $authentication);

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected abstract function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request);

    /**
     * Logs a message to the PHP error log with a timestamp, level, and class name.
     *
     * @param string $message The message to log.
     * @param string $level The log level (e.g., 'INFO', 'WARNING', 'ERROR').
     */
    protected function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] [" . get_class($this) . "] $message";
        // Log to PHP error log
        error_log($logMessage);
    }

    /**
     * Returns complete XML structure for the Request
     * @return string|null
     * @throws Exception
     */
    public function get_response(): string
    {
        $this->log("Starting get_response method.");
        $request = $this->parse_request();
        $this->log("Request parsed successfully.", 'DEBUG');
        $this->expand_request($request);
        $this->log("Request expanded successfully.", 'DEBUG');
        $config = $this->get_domain_config($request);

        $this->log("Domain configuration retrieved successfully.", 'DEBUG');
        return $this->write_response($config, $request);
    }

    /**
     * @param DomainConfiguration $config
     * @param stdClass $request
     *
     * @return string
     */
    protected function write_response(DomainConfiguration $config, stdClass $request)
    {
        $this->log("Starting to write XML response.");
        header("Content-Type: application/xml");

        $writer = new XMLWriter();
        $writer->openMemory();

        $this->write_xml($writer, $config, $request);
        $this->log("XML content written successfully.", 'DEBUG');
        $output =  $writer->outputMemory(true);
        $this->log("Response created.","DEBUG");
        return $output;
    }

    /**
     * @param stdClass $request
     */
    protected function expand_request(stdClass $request)
    {
        $this->log("Expanding request with localpart and domain.", 'DEBUG');
        $localpart = '';
        $domain = '';
        if ($request->email) {
            list($localpart, $domain) = explode('@', $request->email);
            $this->log("Email split into localpart: " . $localpart . " and domain: " . $domain, 'DEBUG');
        }

        if (!isset($request->localpart)) {
            $request->localpart = ($localpart) ? $localpart : '';
            $this->log("Setting localpart to: " . $request->localpart, 'DEBUG');
        }

        if (!isset($request->domain)) {
            $request->domain = ($domain) ? strtolower($domain) : '';
            $this->log("Setting domain to: " . $request->domain, 'DEBUG');
        }
        $this->log("Finished expanding request.", 'DEBUG');
    }

    /**
     * @param stdClass $request
     *
     * @return DomainConfiguration|null
     * @throws Exception
     */
    protected function get_domain_config(stdClass $request)
    {
        $this->log("Starting to get domain configuration.", 'DEBUG');
        static $cachedEmail = null;
        static $cached_config = null;


        if ($cachedEmail === $request->email) {
            $this->log("Using cached configuration for email: " . $request->email, 'DEBUG');
            return $cached_config;
        }

        $this->log("Reading configuration file.", 'DEBUG');
        $cached_config = $this->read_config($request);
        $this->log("Configuration file read successfully.", 'DEBUG');


        try {
            $domainConfig = $cached_config->get_domain_config($request->domain);
            $this->log("Domain configuration retrieved successfully for domain: " . $request->domain, 'DEBUG');
        } catch (Exception $e) {
            $this->log("No domain configuration found for domain: " . $request->domain . ". Attempting to create dynamic config", 'WARNING');
            $domainConfig = null;
        }
        
        // If no domain config found, attempt to create a dynamic configuration
        if ($domainConfig === null) {
            $this->log("Creating dynamic configuration for domain: " . $request->domain, 'DEBUG');

            $domainConfig = $cached_config->add($request->domain);
            $domainConfig->set_domains([$request->domain, $_SERVER['SERVER_NAME']]);
            $this->log("Setting domains for dynamic configuration: ". $request->domain . ", " . $_SERVER['SERVER_NAME'], 'DEBUG');
            // Try to resolve server configurations dynamically
            $resolver = new ServerConfigResolver($request->email);
            if (isset($_GET['debug'])) {
                $this->log("ServerConfigResolver cache disable", 'DEBUG');
                $resolver->disableCache();
            }
            $serverTypes = ['imap', 'smtp',  'pop3', 'caldav', 'carddav', 'activesync'];
            foreach ($serverTypes as $type) {
                try {                    
                    $serverConfig = $resolver->resolveServerConfig($type);

                    if ($serverConfig) {
                        $server = $domainConfig->add_server($type, $serverConfig['hostname']);
                        $server->setMethod($serverConfig['method']);
                        $server->with_endpoint($serverConfig['socket_type'], $serverConfig['port']);
                        $this->log("Added dynamic server configuration for type: ". $type . " host: " . $serverConfig['hostname'], 'DEBUG');
                    }
                } catch (Exception $e) {
                    // Log or handle specific server configuration errors if needed
                    $this->log("Failed to resolve {$type} server config: " . $e->getMessage(), 'ERROR');
                }
            }

            $domainConfig->set_username($request->email);
            $this->log("Setting username: ". $request->email . " for dynamic configuration" , 'DEBUG');
            $domainConfig->set_name("Dynamic Configuration for {$request->domain}");
            $this->log("Setting name for dynamic configuration: Dynamic Configuration for ". $request->domain , 'DEBUG');
            // If no servers were added, return null to indicate no configuration found
            if (empty($domainConfig->get_servers())) {
                $this->log("No servers found in dynamic configuration, returning null", 'ERROR');
                return null;
            }
        }

        $cachedEmail = $request->email;
        $this->log("Finished getting domain configuration for email: " . $request->email, 'DEBUG');
        return $domainConfig;
    }
    /**
     * @param stdClass $vars
     *
     * @return Configuration
     */
    protected function read_config(stdClass $vars)
    {
        $this->log("Starting to read config file", 'DEBUG');
        foreach ($vars as $var => $value) {
            $$var = $value;
        }

        $config = new Configuration();
        /** @noinspection PhpIncludeInspection */
        include CONFIG_FILE;
        $this->log("Configuration file read and processed", 'DEBUG');
        return $config;
    }

    /**
     * @param Server $server
     * @param stdClass $request
     *
     * @return string
     */
    protected function get_username(Server $server, stdClass $request)
    {
        $this->log("Getting username for server type: " . $server->type . " host: " . $server->hostname, 'DEBUG');
        if (is_string($server->username)) {
            $this->log("Username found as static string: " . $server->username, 'DEBUG');
            return $server->username;
        }

        if ($server->username instanceof UsernameResolver) {
            $resolver = $server->username;
            $username =  $resolver->find_username($request);
            $this->log("Username resolved dynamically: " . $username, 'DEBUG');
            return $username;
        }
        $this->log("No username found returning empty string", 'DEBUG');
        return '';
    }

    /**
     * Write client-specific settings for a server
     * 
     * @param XMLWriter $writer
     * @param Server $server
     * @param string $client
     * @param string|null $parentElementName Optional parent element name (e.g., 'pop3')
     */
    protected function write_client_specific_settings(
        XMLWriter $writer, 
        Server $server, 
        string $client, 
        string $parentElementName = null
    )
    {
        $this->log("Writing client-specific settings for client: ". $client . " server type: " . $server->type . " host: " . $server->hostname , 'DEBUG');
        $settings = $server->get_client_settings($client);
        if (empty($settings)) {
            $this->log("No client-specific settings found for client: " . $client, 'DEBUG');
            return;
        }

        // Start parent element if specified
        if ($parentElementName) {
            $writer->startElement($parentElementName);
            $this->log("Started parent XML element: " . $parentElementName , 'DEBUG');
        }

        // Write settings with appropriate formatting based on the handler
        foreach ($settings as $key => $value) {
            // Determine how to write the value based on the current handler
            $formattedValue = $this->format_client_setting_value($value);
            $writer->writeElement($key, $formattedValue);
            $this->log("Writing client setting: " . $key . " with value: " . $formattedValue, 'DEBUG');
        }

        // Close parent element if opened
        if ($parentElementName) {
            $writer->endElement();
            $this->log("Closed parent XML element: " . $parentElementName , 'DEBUG');
        }
        $this->log("Finished writing client-specific settings for client: ". $client . " server type: " . $server->type . " host: " . $server->hostname , 'DEBUG');
    }

    /**
     * Format client setting value based on the specific handler
     * 
     * @param mixed $value
     * @return string
     */
    protected function format_client_setting_value($value): string
    {
        // Default implementation
        if (is_bool($value)) {
            // Most common boolean representations
            return $value ? 'true' : 'false';
        }
        
        return (string)$value;
    }
}