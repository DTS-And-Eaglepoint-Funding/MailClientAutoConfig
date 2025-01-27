<?php

class Server
{
    public $type;
    public $hostname;
    public $username;
    public $endpoints;
    public $same_password;
    public $default_port;
    public $default_ssl_port;
    public $domainRequired;
    public $client_settings = [];
    public $method = 'config';
    private ?string $mta_sts_mode = null;
    private ?int $mta_sts_max_age = null;
    private $mta_sts_mx_records = [];

    /**
     * Server constructor.
     *
     * @param string $type
     * @param string $hostname
     * @param int $default_port
     * @param int $default_ssl_port
     */
    public function __construct(string $type, string $hostname, int $default_port, int $default_ssl_port, bool $domainRequired)
    {
        $this->type = $type;
        $this->hostname = $hostname;
        $this->default_port = $default_port;
        $this->default_ssl_port = $default_ssl_port;
        $this->endpoints = [];
        $this->same_password = true;
        $this->domainRequired = $domainRequired;
    }

    /**
     * Set specific Username for this Server
     *
     * @param string $username
     *
     * @return $this
     */
    public function with_username(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Call to hint that not the same password is needed for this Server
     * @return $this
     */
    public function with_different_password()
    {
        $this->same_password = false;
        return $this;
    }
    public function add_client_settings(array $settings)
    {
        $this->client_settings = $settings;
        return $this;
    }

    public function get_client_settings()
    {
        return $this->client_settings;
    }

    /**
     * Sets the method used to obtain server configuration.
     *
     * @param string $method The method used (e.g., 'txt', 'srv', 'mx', 'ispdb').
     * @return $this
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Gets the method used to obtain server configuration.
     *
     * @return string The method used.
     */
    public function getMethod(): string
    {
        return $this->method;
    }
    /**
     * @return string|null
     */
    public function get_mta_sts_mode(): ?string
    {
        if ($this->type !== 'mta-sts') {
            return null;
        }
        return $this->mta_sts_mode;
    }

    /**
     * @param string $mode
     * @return void
     */
    public function set_mta_sts_mode(string $mode): self
    {
        if ($this->type !== 'mta-sts') {
            return $this;
        }
        $this->mta_sts_mode = $mode;
        return $this;
    }

        /**
     * @return int|null
     */
    public function get_mta_sts_max_age(): ?int
    {
        if ($this->type !== 'mta-sts') {
            return null;
        }
        return $this->mta_sts_max_age;
    }

    /**
     * @param int $max_age
     * @return void
     */
    public function set_mta_sts_max_age(?int $max_age): self
    {
        if ($this->type !== 'mta-sts') {
            return $this;
        }
        $this->mta_sts_max_age = $max_age;
        return $this;
    }

    /**
     * @return array
     */
    public function get_mta_sts_mx_records(): array
    {
        if ($this->type !== 'mta-sts') {
            return null;
        }
        return $this->mta_sts_mx_records;
    }

    /**
     * @param array $mta_sts_mx_records
     * @return void
     */
    public function set_mta_sts_mx_records(array $mta_sts_mx_records): self
    {
        if ($this->type !== 'mta-sts') {
            return $this;
        }
        $this->mta_sts_mx_records = $mta_sts_mx_records;
        return $this;
    }
    /**
     * Set Endpoint for Server, can be called multiple times to set different endpoints
     *
     * @param string $socket_type
     * @param int $port
     * @param string $authentication
     *
     * @return $this
     */
    public function with_endpoint(string $socket_type, $port = null, $authentication = 'password-cleartext'): self
    {
        if ($port === null) {
            $port = $socket_type === SocketType::SSL ? $this->default_ssl_port : $this->default_port;
        }

        $this->endpoints[] = (object)[
            'socketType' => $socket_type,
            'port' => $port,
            'authentication' => $authentication,
        ];

        return $this;
    }
}
