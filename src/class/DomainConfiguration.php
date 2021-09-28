<?php

class DomainConfiguration
{
    private $domains;
    private $servers = [];
    private $username;
    private $name;
    private $name_short;
    private $id;

    /**
     * Add new Server for Config-ID
     *
     * @param string $type
     * @param string $hostname
     *
     * @return Server
     * @throws Exception
     */
    public function add_server(string $type, string $hostname)
    {
        $server = $this->create_server($type, $hostname);
        $server->username = $this->username;
        array_push($this->servers, $server);
        return $server;
    }

    /**
     * Set Name for Config
     *
     * @param string $name
     * @param string|null $name_short
     */
    public function set_name(string $name, string $name_short = null)
    {
        $this->name = $name;
        if (!is_null($name_short)) {
            $this->name_short = $name_short;
        }
    }

    /**
     * Get Name for Config
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get Shortname for Config
     * @return string
     */
    public function get_shortname(): string
    {
        return $this->name_short;
    }

    /**
     * Set ID for Config
     *
     * @param string $id
     */
    public function set_id(string $id)
    {
        $this->id = $id;
    }

    /**
     * Get Config-ID
     * @return mixed
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Set all used Domains for Config
     *
     * @param array $domains
     */
    public function set_domains(array $domains)
    {
        $this->domains = $domains;
    }

    /**
     * Get list of all Domains for Config
     * @return array
     */
    public function get_domains()
    {
        return $this->domains;
    }

    /**
     * Set Username to use for Config
     *
     * @param string $username
     */
    public function set_username(string $username)
    {
        $this->username = $username;
    }

    /**
     * Get all Servers for Config
     * @return array
     */
    public function get_servers()
    {
        return $this->servers;
    }

    /**
     * @param string $type
     * @param string $hostname
     *
     * @return Server
     * @throws Exception
     */
    private function create_server(string $type, string $hostname)
    {
        switch ($type) {
            case ConnectionType::IMAP:
                return new Server($type, $hostname, 143, 993);
            case ConnectionType::POP3:
                return new Server($type, $hostname, 110, 995);
            case ConnectionType::SMTP:
                return new Server($type, $hostname, 25, 465);
            default:
                throw new Exception(sprintf(Exceptions::UNKNOWN_SOCKET_TYPE, $type));
        }
    }
}
