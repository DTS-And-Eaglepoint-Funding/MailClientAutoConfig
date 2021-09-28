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
     * Returns complete XML structure for the Request
     * @return string|null
     * @throws Exception
     */
    public function get_response(): string
    {
        $request = $this->parse_request();
        $this->expand_request($request);
        $config = $this->get_domain_config($request);
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
        header("Content-Type: application/xml");

        $writer = new XMLWriter();
        $writer->openMemory();

        $this->write_xml($writer, $config, $request);

        return $writer->outputMemory(true);
    }

    /**
     * @param stdClass $request
     */
    protected function expand_request(stdClass $request)
    {
        $localpart = '';
        $domain = '';
        if ($request->email) {
            list($localpart, $domain) = explode('@', $request->email);
        }

        if (!isset($request->localpart)) {
            $request->localpart = ($localpart) ? $localpart : '';
        }

        if (!isset($request->domain)) {
            $request->domain = ($domain) ? strtolower($domain) : '';
        }
    }

    /**
     * @param stdClass $request
     *
     * @return DomainConfiguration|null
     * @throws Exception
     */
    protected function get_domain_config(stdClass $request)
    {
        static $cachedEmail = null;
        static $cached_config = null;


        if ($cachedEmail === $request->email) {
            return $cached_config;
        }

        $cached_config = $this->read_config($request);
        $cachedEmail = $request->email;
        return $cached_config->get_domain_config($request->domain);
    }

    /**
     * @param stdClass $vars
     *
     * @return Configuration
     */
    protected function read_config(stdClass $vars)
    {
        foreach ($vars as $var => $value) {
            $$var = $value;
        }

        $config = new Configuration();
        /** @noinspection PhpIncludeInspection */
        include CONFIG_FILE;
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
        if (is_string($server->username)) {
            return $server->username;
        }

        if ($server->username instanceof UsernameResolver) {
            $resolver = $server->username;
            return $resolver->find_username($request);
        }
        return '';
    }
}
