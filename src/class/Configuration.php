<?php

class Configuration
{
    private $items = [];
    private $domains = [];

    /**
     * Setup new Configuration for Autoconfig
     *
     * @param $id
     *
     * @return DomainConfiguration
     */
    public function add($id)
    {
        $result = new DomainConfiguration();
        $result->set_id($id);
        array_push($this->items, $result);
        return $result;
    }

    /**
     * @param string $domain
     *
     * @return DomainConfiguration
     * @throws Exception
     */
    public function get_domain_config(string $domain)
    {
        $domain = strtolower($domain);
        $this->log('Looking for domain configuration for: '. $domain, 'DEBUG');
        $this->domain_config_check();
        foreach ($this->items as $domain_config) {
            /** @var $domain_config DomainConfiguration */
            if (in_array($domain, $domain_config->get_domains())) {
                $this->log('Domain Found in: '. $domain_config->get_id(), 'INFO');
                return $domain_config;
            }
        }

        throw new Exception(sprintf(Exceptions::DOMAIN_NOT_CONFIGURED, $domain));
    }
    private function domain_config_check(){
        $this->log('Looking for domain configuration errors', 'DEBUG');
        foreach ($this->items as $result) {
            $domains = $result->get_domains();
            $this->log('Domains: ' . implode(', ', $domains), 'DEBUG');
            if (count($domains) > 0) {
                foreach ($domains as $working_domain) {
                    $working_domain = strtolower($working_domain);
                    if (in_array($working_domain, $this->domains)) {
                        $this->log(sprintf(Exceptions::OVERLAPPING_DOMAINS, $working_domain), 'ERROR');
                        throw new Exception(sprintf(Exceptions::OVERLAPPING_DOMAINS, $working_domain));
                    }
                    $this->domains[] = $working_domain;
                }
            }
        }
        $this->log('No overlapping domains found', 'DEBUG');
    }

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
}
