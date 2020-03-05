<?php
const CONFIG_FILE = './autoconfig.settings.php';

class ConnectionType {
    const IMAP = 'imap';
    const SMTP = 'smtp';
    const POP3 = 'pop3';
}

class SocketType {
    const SSL = 'SSL';
    const STARTTLS = 'STARTTLS';
}

class Configuration {
    private $items = array();

    public function add($id) {
        $result = new DomainConfiguration();
        $result->id = $id;
        array_push($this->items, $result);
        return $result;
    }

    public function getDomainConfig($domain) {
        foreach ($this->items as $domainConfig) {
            if (in_array($domain, $domainConfig->domains)) {
                return $domainConfig;
            }
        }

        throw new Exception("No configuration found for requested domain '$domain'.");
    }
}

class DomainConfiguration {
    public $domains;
    public $servers = array();
    public $username;
    public $name;
    public $nameShort;
    public $id;

    public function addServer($type, $hostname) {
        $server = $this->createServer($type, $hostname);
        $server->username = $this->username;
        array_push($this->servers, $server);
        return $server;
    }

    private function createServer(string $type, string $hostname) {
        switch ($type) {
            case ConnectionType::IMAP:
                return new Server($type, $hostname, 143, 993);
            case ConnectionType::POP3:
                return new Server($type, $hostname, 110, 995);
            case ConnectionType::SMTP:
                return new Server($type, $hostname, 25, 465);
            default:
                throw new Exception("Unrecognized server type \"$type\"");
        }
    }
}

class Server {
    public $type;
    public $hostname;
    public $username;
    public $endpoints;
    public $samePassword;
    public $defaultPort;
    public $defaultSslPort;

    public function __construct(string $type, string $hostname, int $defaultPort, int $defaultSslPort) {
        $this->type = $type;
        $this->hostname = $hostname;
        $this->defaultPort = $defaultPort;
        $this->defaultSslPort = $defaultSslPort;
        $this->endpoints = array();
        $this->samePassword = true;
    }

    public function withUsername(string $username) {
        $this->username = $username;
        return $this;
    }

    public function withDifferentPassword() {
        $this->samePassword = false;
        return $this;
    }

    public function withEndpoint(string $socketType, $port = NULL, $authentication = 'password-cleartext') {
        if ($port === NULL) {
            $port = $socketType === SocketType::SSL ? $this->defaultSslPort : $this->defaultPort;
        }

        array_push(
            $this->endpoints,
            (object)array(
                'socketType' => $socketType,
                'port' => $port,
                'authentication' => $authentication
            )
        );

        return $this;
    }
}

interface UsernameResolver {
    public function findUsername(stdClass $request);
}

class AliasesFileUsernameResolver implements UsernameResolver {
    private $fileName;

    function __construct(string $fileName = "/etc/mail/aliases") {
        $this->fileName = $fileName;
    }

    public function findUsername(stdClass $request) {
        static $cachedEmail = NULL;
        static $cachedUsername = NULL;

        if ($request->email === $cachedEmail) {
            return $cachedUsername;
        }

        $fp = fopen($this->fileName, 'rb');

        if ($fp === false) {
            throw new Exception("Unable to open aliases file \"$this->fileName\"");
        }

        $username = $this->findLocalPart($fp, $request->localpart);
        if (strpos($username, "@") !== false || strpos($username, ",") !== false) {
            $username = NULL;
        }

        $cachedEmail = $request->email;
        $cachedUsername = $username;
        return $username;
    }

    protected function findLocalPart($fp, string $localPart) {
        while (($line = fgets($fp)) !== false) {
            $matches = array();
            if (!preg_match("/^\s*" . preg_quote($localPart) . "\s*:\s*(\S+)\s*$/", $line, $matches)) continue;
            return $matches[1];
        }
        throw new Exception("Unable to parse \$localPart");
    }
}

abstract class RequestHandler {
    public function handleRequest() {
        $request = $this->parseRequest();
        $this->expandRequest($request);
        $config = $this->getDomainConfig($request);
        $this->writeResponse($config, $request);
    }

    protected abstract function parseRequest();

    protected abstract function mapAuthenticationType(string $authentication);

    public  abstract function writeResponse(DomainConfiguration $config, stdClass $request);

    protected function expandRequest(stdClass $request) {
        list($localpart, $domain) = explode('@', $request->email);

        if (!isset($request->localpart)) {
            $request->localpart = $localpart;
        }

        if (!isset($request->domain)) {
            $request->domain = strtolower($domain);
        }
    }

    protected function getDomainConfig(stdClass $request) {
        static $cachedEmail = NULL;
        static $cachedConfig = NULL;

        if ($cachedEmail === $request->email) {
            return $cachedConfig;
        }

        $cachedConfig = $this->readConfig($request);
        $cachedEmail = $request->email;
        try {
            return $cachedConfig->getDomainConfig($request->domain);
        } catch (Exception $exception) {
            return $exception;
        }
    }

    protected function readConfig(stdClass $vars) {
        foreach ($vars as $var => $value) {
            $$var = $value;
        }

        $config = new Configuration();
        /** @noinspection PhpIncludeInspection */
        include CONFIG_FILE;
        return $config;
    }

    protected function getUsername(Server $server, stdClass $request) {
        if (is_string($server->username)) {
            return $server->username;
        }

        if ($server->username instanceof UsernameResolver) {
            $resolver = $server->username;
            return $resolver->findUsername($request);
        }
        return '';
    }
}

class MozillaHandler extends RequestHandler {
    public function writeResponse($config, $request) {
        header("Content-Type: text/xml");
        $writer = new XMLWriter();
        $writer->openURI("php://output");

        $this->writeXml($writer, $config, $request);
        $writer->flush();
    }

    protected function parseRequest() {
        return (object)array('email' => $_GET['emailaddress']);
    }

    protected function writeXml(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startDocument("1.0");
        $writer->setIndent(4);
        $writer->startElement("clientConfig");
        $writer->writeAttribute("version", "1.1");

        $this->writeEmailProvider($writer, $config, $request);

        $writer->endElement();
        $writer->endDocument();
    }

    protected function writeEmailProvider(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startElement("emailProvider");
        $writer->writeAttribute("id", $config->id);

        foreach ($config->domains as $domain) {
            $writer->writeElement("domain", $domain);
        }

        $writer->writeElement("displayName", $config->name);
        $writer->writeElement("displayShortName", $config->nameShort);

        foreach ($config->servers as $server) {
            foreach ($server->endpoints as $endpoint) {
                $this->writeServer($writer, $server, $endpoint, $request);
            }
        }

        $writer->endElement();
    }

    protected function writeServer(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        switch ($server->type) {
            case 'imap':
            case 'pop3':
                $this->writeIncomingServer($writer, $server, $endpoint, $request);
                break;
            case 'smtp':
                $this->writeSmtpServer($writer, $server, $endpoint, $request);
                break;
        }
    }

    protected function writeIncomingServer(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        $authentication = $this->mapAuthenticationType($endpoint->authentication);
        if (empty($authentication)) return;

        $writer->startElement("incomingServer");
        $writer->writeAttribute("type", $server->type);
        $writer->writeElement("hostname", $server->hostname);
        $writer->writeElement("port", $endpoint->port);
        $writer->writeElement("socketType", $endpoint->socketType);
        $writer->writeElement("username", $this->getUsername($server, $request));
        $writer->writeElement("authentication", $authentication);
        $writer->endElement();
    }

    protected function writeSmtpServer(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
        $authentication = $this->mapAuthenticationType($endpoint->authentication);
        if ($authentication === NULL) return;

        $writer->startElement("outgoingServer");
        $writer->writeAttribute("type", "smtp");
        $writer->writeElement("hostname", $server->hostname);
        $writer->writeElement("port", $endpoint->port);
        $writer->writeElement("socketType", $endpoint->socketType);

        if ($authentication !== false) {
            $writer->writeElement("username", $this->getUsername($server, $request));
            $writer->writeElement("authentication", $authentication);
        }

        $writer->writeElement("addThisServer", "true");
        $writer->writeElement("useGlobalPreferredServer", "true");
        $writer->endElement();
    }

    protected function mapAuthenticationType(string $authentication) {
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

class OutlookHandler extends RequestHandler {
    public function writeResponse($config, $request) {
        header("Content-Type: application/xml");

        $writer = new XMLWriter();
        $writer->openMemory();

        $this->writeXml($writer, $config, $request);

        $response = $writer->outputMemory(true);
        echo $response;
    }

    protected function parseRequest() {
        $postdata = file_get_contents("php://input");

        if (strlen($postdata) > 0) {
            $xml = simplexml_load_string($postdata);
            /** @noinspection PhpUndefinedFieldInspection */
            return (object)array('email' => $xml->Request->EMailAddress);
        }

        return NULL;
    }

    public function writeXml(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startDocument("1.0", "utf-8");
        $writer->setIndent(4);
        $writer->startElement("Autodiscover");
        $writer->writeAttribute("xmlns", "http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006");
        $writer->startElement("Response");
        $writer->writeAttribute("xmlns", "http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a");

        $writer->startElement("Account");
        $writer->writeElement("AccountType", "email");
        $writer->writeElement("Action", "settings");

        foreach ($config->servers as $server) {
            foreach ($server->endpoints as $endpoint) {
                if ($this->writeProtocol($writer, $server, $endpoint, $request))
                    break;
            }
        }

        $writer->endElement();

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
    }

    protected function writeProtocol(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request) {
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
        $writer->writeElement('LoginName', $this->getUsername($server, $request));
        $writer->writeElement('DomainRequired', 'off');
        $writer->writeElement('SPA', $endpoint->authentication === 'SPA' ? 'on' : 'off');

        switch ($endpoint->socketType) {
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
            $writer->writeElement('UsePOPAuth', $server->samePassword ? 'on' : 'off');
            $writer->writeElement('SMTPLast', 'off');
        }

        $writer->endElement();

        return true;
    }

    protected function mapAuthenticationType(string $authentication) {
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

if (strpos($_SERVER['SERVER_NAME'], "autoconfig.") === 0) {
    // Configuration for Mozilla Thunderbird, Evolution, KMail, Kontact
    $handler = new MozillaHandler();
}
else if (strpos($_SERVER['SERVER_NAME'], "autodiscover.") === 0) {
    // Configuration for Outlook
    $handler = new OutlookHandler();
}
else {
    header("HTTP/1.0 404 Not Found");
}
try {
    $handler->handleRequest();
}
catch (Exception $exception) {
    header("HTTP/1.0 500 	$exception");
}
