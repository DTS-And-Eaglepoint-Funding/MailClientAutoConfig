<?php

class DomainConfiguration
{
    private $domains;
    private $servers = [];
    private $username;
    private $name;
    private $name_short = '';
    private $id;
    private $oauth2_config = null;
    private $domainRequired = true;
    private $documentation = [];

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
        $a = new ConnectionType();
        if (!$a->isValidType($type)) {
            throw new Exception(sprintf(Exceptions::UNKNOWN_CONNECTION_TYPE, $type));
        }
        $server = $this->create_server($type, $hostname);
        if ($type !== 'mta-sts') {
            $server->username = $this->username;
        }
        array_push($this->servers, $server);
        return $server;
    }
    /**
     * Add OAuth2 configuration
     *
     * @param array $config OAuth2 configuration
     * @return self
     * @throws InvalidArgumentException
     */
    public function add_oauth2_config(array $config): self
    {
        $defaults = [
            'issuer' => null,
            'client_id' => null,
            'client_secret' => null,
            'auth_url' => null,
            'token_url' => null,
            'scopes' => [],
            'redirect_uri' => null
        ];

        $normalizedConfig = array_merge($defaults, 
            array_change_key_case($config, CASE_LOWER),
            [
                'issuer' => $config['issuer'] ?? $config['provider'] ?? null,
                'auth_url' => $config['authUrl'] ?? $config['authorization_url'] ?? $config['auth_endpoint'] ?? null,
                'token_url' => $config['tokenUrl'] ?? $config['token_url'] ?? $config['token_endpoint'] ?? null,
                'scopes' => is_string($config['scopes'] ?? '') 
                    ? explode(' ', $config['scopes']) 
                    : ($config['scopes'] ?? [])
            ]
        );

        $requiredFields = ['issuer', 'auth_url', 'token_url'];
        foreach ($requiredFields as $field) {
            if (empty($normalizedConfig[$field])) {
                throw new \InvalidArgumentException("OAuth2 configuration must include a valid $field");
            }
        }

        $this->oauth2_config = [
            'issuer' => $normalizedConfig['issuer'],
            'authUrl' => $normalizedConfig['auth_url'],
            'tokenUrl' => $normalizedConfig['token_url'],
            'scopes' => $normalizedConfig['scopes'],
            'clientId' => $normalizedConfig['client_id'],
            'clientSecret' => $normalizedConfig['client_secret'],
            'redirectUri' => $normalizedConfig['redirect_uri']
        ];

        return $this;
    }

    /**
     * Get OAuth2 configuration
     * @return array|null
     */
    public function get_oauth2_config(): ?array
    {
        return $this->oauth2_config;
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
     * Set whether the domain is required for login.
     *
     * @param bool $isRequired
     * @return $this
     */
    public function setDomainRequired(bool $isRequired): self
    {
        $this->domainRequired = $isRequired;
        return $this;
    }

    /**
     * Get whether the domain is required for login.
     *
     * @return bool
     */
    public function isDomainRequired(): bool
    {
        return $this->domainRequired;
    }

    public function add_documentation(string $url, string $description)
    {
        $this->documentation[] = [
            'url' => $url,
            'description' => $description
        ];
        return $this;
    }

    public function get_documentation()
    {
        return $this->documentation;
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
                return new Server($type, $hostname, 143, 993, $this->domainRequired);
            case ConnectionType::POP3:
                return new Server($type, $hostname, 110, 995, $this->domainRequired);
            case ConnectionType::SMTP:
                return new Server($type, $hostname, 25, 465, $this->domainRequired);
            case ConnectionType::CALDAV:
                return new Server($type, $hostname, 80, 443, $this->domainRequired);
            case ConnectionType::CARDDAV:
                return new Server($type, $hostname, 80, 443, $this->domainRequired);
            case ConnectionType::ACTIVESYNC:
                return new Server($type, $hostname, 80, 443, $this->domainRequired);
            case ConnectionType::MTASTS:
                return new Server($type, $hostname, 80, 443, $this->domainRequired);
            default:
                throw new Exception(sprintf(Exceptions::UNKNOWN_SOCKET_TYPE, $type));
        }
    }
}
