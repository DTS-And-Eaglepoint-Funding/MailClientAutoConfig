<?php
include './const.php';
const CONFIG_FILE = './autoconfig.settings.php';

class Configuration {
    private $items = array();

    /**
     * Setup new Configuration for Autoconfig
     * @param $id
     * @return DomainConfiguration
     */
    public function add($id) {
        $result = new DomainConfiguration();
        $result->set_id($id);
        array_push($this->items, $result);
        return $result;
    }

    /**
     * @param string $domain
     * @return DomainConfiguration
     * @throws Exception
     */
    public function get_domain_config(string $domain) {
        foreach ($this->items as $domain_config) {
            if (in_array($domain, $domain_config->domains)) {
                return $domain_config;
            }
        }

        throw new Exception(sprintf(Exceptions::DOMAIN_NOT_CONFIGURED, $domain));
    }
}

class DomainConfiguration {
    private $domains;
    private $servers = array();
    private $username;
    private $name;
    private $name_short;
    private $id;

    /**
     * Add new Server for Config-ID
     * @param string $type
     * @param string $hostname
     * @return Server
     * @throws Exception
     */
    public function add_server(string $type, string $hostname) {
        $server = $this->create_server($type, $hostname);
        $server->username = $this->username;
        array_push($this->servers, $server);
        return $server;
    }

    /**
     * Set Name for Config
     * @param string $name
     * @param string|null $name_short
     */
    public function set_name(string $name, string $name_short = NULL) {
        $this->name = $name;
        if (!is_null($name_short)) {
            $this->name_short = $name_short;
        }
    }

    /**
     * Get Name for Config
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get Shortname for Config
     * @return string
     */
    public function get_shortname(): string {
        return $this->name_short;
    }

    /**
     * Set ID for Config
     * @param string $id
     */
    public function set_id(string $id) {
        $this->id = $id;
    }

    /**
     * Get Config-ID
     * @return mixed
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Set all used Domains for Config
     * @param array $domains
     */
    public function set_domains(array $domains) {
        $this->domains = $domains;
    }

    /**
     * Get list of all Domains for Config
     * @return array
     */
    public function get_domains() {
        return $this->domains;
    }

    /**
     * Set Username to use for Config
     * @param string $username
     */
    public function set_username(string $username) {
        $this->username = $username;
    }

    /**
     * Get all Servers for Config
     * @return array
     */
    public function get_servers() {
        return $this->servers;
    }

    /**
     * @param string $type
     * @param string $hostname
     * @return Server
     * @throws Exception
     */
    private function create_server(string $type, string $hostname) {
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

class Server {
    public $type;
    public $hostname;
    public $username;
    public $endpoints;
    public $same_password;
    public $default_port;
    public $default_ssl_port;

    /**
     * Server constructor.
     * @param string $type
     * @param string $hostname
     * @param int $default_port
     * @param int $default_ssl_port
     */
    public function __construct(string $type, string $hostname, int $default_port, int $default_ssl_port) {
        $this->type = $type;
        $this->hostname = $hostname;
        $this->default_port = $default_port;
        $this->default_ssl_port = $default_ssl_port;
        $this->endpoints = array();
        $this->same_password = true;
    }

    /**
     * Set specific Username for this Server
     * @param string $username
     * @return $this
     */
    public function with_username(string $username) {
        $this->username = $username;
        return $this;
    }

    /**
     * Call to hint that not the same password is needed for this Server
     * @return $this
     */
    public function with_different_password() {
        $this->same_password = false;
        return $this;
    }

    /**
     * Set Endpoint for Server, can be called multiple times to set different endpoints
     * @param string $socket_type
     * @param int $port
     * @param string $authentication
     * @return $this
     */
    public function with_endpoint(string $socket_type, $port = NULL, $authentication = 'password-cleartext') {
        if ($port === NULL) {
            $port = $socket_type === SocketType::SSL ? $this->default_ssl_port : $this->default_port;
        }

        array_push(
            $this->endpoints,
            (object)array(
                'socketType' => $socket_type,
                'port' => $port,
                'authentication' => $authentication
            )
        );

        return $this;
    }
}

interface UsernameResolver {
    /**
     * @param stdClass $request
     * @return string|null
     */
    public function find_username(stdClass $request);
}

class AliasesFileUsernameResolver implements UsernameResolver {
    private $file_name;

    /**
     * AliasesFileUsernameResolver constructor.
     * @param string $fileName
     */
    function __construct(string $fileName = "/etc/mail/aliases") {
        $this->file_name = $fileName;
    }

    /**
     * @param stdClass $request
     * @return string|null
     * @throws Exception
     */
    public function find_username(stdClass $request) {
        static $cached_email = NULL;
        static $cached_username = NULL;

        if ($request->email === $cached_email) {
            return $cached_username;
        }

        $fp = fopen($this->file_name, 'rb');

        if ($fp === false) {
            throw new Exception(sprintf(Exceptions::ALIAS_NOT_OPENABLE, $this->file_name));
        }

        $username = $this->find_localpart($fp, $request->localpart);
        if (strpos($username, "@") !== false || strpos($username, ",") !== false) {
            $username = NULL;
        }

        $cached_email = $request->email;
        $cached_username = $username;
        return $username;
    }

    /**
     * @param resource $fp
     * @param string $localpart
     * @return string
     * @throws Exception
     */
    protected function find_localpart($fp, string $localpart) {
        while (($line = fgets($fp)) !== false) {
            $matches = array();
            if (!preg_match("/^\s*" . preg_quote($localpart) . "\s*:\s*(\S+)\s*$/", $line, $matches)) continue;
            return $matches[1];
        }
        throw new Exception(Exceptions::LOCALPART_NOT_PARSEABLE);
    }
}

abstract class RequestHandler {
    /**
     * @return object
     * @throws Exception
     */
    protected abstract function parse_request();

    /**
     * @param string $authentication
     * @return string|null|false
     */
    protected abstract function map_authentication_type(string $authentication);

    /**
     * Returns complete XML structure for the Request
     * @return string|null
     * @throws Exception
     */
    public function get_response(): string {
        $request = $this->parse_request();
        $this->expand_request($request);
        $config = $this->get_domain_config($request);
        return $this->write_response($config, $request);
    }

    /**
     * @param DomainConfiguration $config
     * @param stdClass $request
     * @return string
     */
    protected function write_response(DomainConfiguration $config, stdClass $request) {
        header("Content-Type: application/xml");

        $writer = new XMLWriter();
        $writer->openMemory();

        $this->write_xml($writer, $config, $request);

        return $writer->outputMemory(true);
    }

    /**
     * @param stdClass $request
     */
    protected function expand_request(stdClass $request) {
        list($localpart, $domain) = explode('@', $request->email);

        if (!isset($request->localpart)) {
            $request->localpart = $localpart;
        }

        if (!isset($request->domain)) {
            $request->domain = strtolower($domain);
        }
    }

    /**
     * @param stdClass $request
     * @return DomainConfiguration|null
     * @throws Exception
     */
    protected function get_domain_config(stdClass $request) {
        static $cachedEmail = NULL;
        static $cached_config = NULL;

        if ($cachedEmail === $request->email) {
            return $cached_config;
        }

        $cached_config = $this->read_config($request);
        $cachedEmail = $request->email;
        return $cached_config->get_domain_config($request->domain);
    }

    /**
     * @param stdClass $vars
     * @return Configuration
     */
    protected function read_config(stdClass $vars) {
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
     * @return string
     */
    protected function get_username(Server $server, stdClass $request) {
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

class MozillaHandler extends RequestHandler {
    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request() {
        $emailaddress = array_key_exists('emailaddress', $_GET) ? $_GET['emailaddress'] : null;
        if (is_null($emailaddress)) {
            throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
        }
        return (object)array('email' => $emailaddress);
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startDocument("1.0");
        $writer->setIndent(4);
        $writer->startElement("clientConfig");
        $writer->writeAttribute("version", "1.1");

        $this->write_email_provider($writer, $config, $request);

        $writer->endElement();
        $writer->endDocument();
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_email_provider(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startElement("emailProvider");
        $writer->writeAttribute("id", $config->get_id());

        foreach ($config->get_domains() as $domain) {
            $writer->writeElement("domain", $domain);
        }

        $writer->writeElement("displayName", $config->get_name());
        $writer->writeElement("displayShortName", $config->get_shortname());

        foreach ($config->get_servers() as $server) {
            foreach ($server->endpoints as $endpoint) {
                $this->write_server($writer, $server, $endpoint, $request);
            }
        }

        $writer->endElement();
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     */
    protected function write_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        switch ($server->type) {
            case 'imap':
            case 'pop3':
                $this->write_incoming_server($writer, $server, $endpoint, $request);
                break;
            case 'smtp':
                $this->write_smtp_server($writer, $server, $endpoint, $request);
                break;
        }
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     */
    protected function write_incoming_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        $authentication = $this->map_authentication_type($endpoint->authentication);
        if (empty($authentication)) return;

        $writer->startElement("incomingServer");
        $writer->writeAttribute("type", $server->type);
        $writer->writeElement("hostname", $server->hostname);
        $writer->writeElement("port", $endpoint->port);
        $writer->writeElement("socketType", $endpoint->socketType);
        $writer->writeElement("username", $this->get_username($server, $request));
        $writer->writeElement("authentication", $authentication);
        $writer->endElement();
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     */
    protected function write_smtp_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        $authentication = $this->map_authentication_type($endpoint->authentication);
        if ($authentication === NULL) return;

        $writer->startElement("outgoingServer");
        $writer->writeAttribute("type", "smtp");
        $writer->writeElement("hostname", $server->hostname);
        $writer->writeElement("port", $endpoint->port);
        $writer->writeElement("socketType", $endpoint->socketType);

        if ($authentication !== false) {
            $writer->writeElement("username", $this->get_username($server, $request));
            $writer->writeElement("authentication", $authentication);
        }

        $writer->writeElement("addThisServer", "true");
        $writer->writeElement("useGlobalPreferredServer", "true");
        $writer->endElement();
    }

    /**
     * @param string $authentication
     * @return false|string|null
     */
    protected function map_authentication_type(string $authentication) {
        switch ($authentication) {
            case 'password-cleartext':
                return 'password-cleartext';
            case 'CRAM-MD5':
                return 'password-encrypted';
            case 'none':
                return '';
            default:
                return NULL;
        }
    }
}

class OutlookHandler extends RequestHandler {

    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request(): object {
        $postdata = file_get_contents("php://input");

        if (strlen($postdata) > 0) {
            $xml = simplexml_load_string($postdata);
            /** @noinspection PhpUndefinedFieldInspection */
            $emailaddress = $xml->Request->EMailAddress;
            if (is_null($emailaddress)) {
                throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
            }
            return (object)array('email' => $emailaddress);
        }

        throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    public function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startDocument("1.0", "utf-8");
        $writer->setIndent(4);
        $writer->startElement("Autodiscover");
        $writer->writeAttribute("xmlns", "http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006");
        $writer->startElement("Response");
        $writer->writeAttribute("xmlns", "http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a");

        $writer->startElement("Account");
        $writer->writeElement("AccountType", "email");
        $writer->writeElement("Action", "settings");

        foreach ($config->get_servers() as $server) {
            foreach ($server->endpoints as $endpoint) {
                if ($this->write_protocol($writer, $server, $endpoint, $request))
                    break;
            }
        }

        $writer->endElement();

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     * @return bool
     */
    protected function write_protocol(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        switch ($endpoint->authentication) {
            case 'password-cleartext':
            case 'SPA':
                break;
            case 'none':
                if ($server->type !== 'smtp') return false;
                break;
            default:
                return false;
        }

        $writer->startElement('Protocol');
        $writer->writeElement('Type', strtoupper($server->type));
        $writer->writeElement('Server', $server->hostname);
        $writer->writeElement('Port', $endpoint->port);
        $writer->writeElement('LoginName', $this->get_username($server, $request));
        $writer->writeElement('DomainRequired', 'off');
        $writer->writeElement('SPA', $endpoint->authentication === 'SPA' ? 'on' : 'off');

        switch ($endpoint->socket_type) {
            case 'plain':
                $writer->writeElement("SSL", "off");
                break;
            case 'SSL':
                $writer->writeElement("SSL", "on");
                $writer->writeElement("Encryption", "SSL");
                break;
            case 'STARTTLS':
                $writer->writeElement("SSL", "on");
                $writer->writeElement("Encryption", "TLS");
                break;
        }

        $writer->writeElement("AuthRequired", $endpoint->authentication !== 'none' ? 'on' : 'off');

        if ($server->type == 'smtp') {
            $writer->writeElement('UsePOPAuth', $server->same_password ? 'on' : 'off');
            $writer->writeElement('SMTPLast', 'off');
        }

        $writer->endElement();

        return true;
    }

    /**
     * @param string $authentication
     * @return bool|false|string|null
     */
    protected function map_authentication_type(string $authentication) {
        switch ($authentication) {
            case 'password-cleartext':
                return 'password-cleartext';
            case 'CRAM-MD5':
                return 'password-encrypted';
            case 'none':
                return false;
            default:
                return NULL;
        }
    }
}
