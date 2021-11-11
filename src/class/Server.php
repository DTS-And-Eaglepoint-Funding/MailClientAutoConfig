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

    /**
     * Server constructor.
     *
     * @param string $type
     * @param string $hostname
     * @param int $default_port
     * @param int $default_ssl_port
     */
    public function __construct(string $type, string $hostname, int $default_port, int $default_ssl_port)
    {
        $this->type = $type;
        $this->hostname = $hostname;
        $this->default_port = $default_port;
        $this->default_ssl_port = $default_ssl_port;
        $this->endpoints = [];
        $this->same_password = true;
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
