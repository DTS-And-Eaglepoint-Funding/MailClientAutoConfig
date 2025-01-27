<?php

class OutlookHandler extends RequestHandler
{
    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request(): object
    {
        $this->log("Starting to parse request for Outlook configuration");

        // Check if email is provided in the query parameters FOR TESTING
        if (isset($_GET['emailaddress'])) {
            $emailaddress = filter_var($_GET['emailaddress'], FILTER_VALIDATE_EMAIL);
            if ($emailaddress) {
                $this->log("Email address found in query parameters: " . $emailaddress, "DEBUG");
                return (object) ['email' => $emailaddress];
            }
            $this->log("Email address in query params is invalid: " . $_GET['emailaddress'], 'ERROR');
            throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
        }
        $this->log("Request Method: " . $_SERVER['REQUEST_METHOD']);
        // Check if it's a POST request (V2) or GET request (V1)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->log("POST request received");
            // V2: Parse the XML body to extract the email address
            $postdata = file_get_contents("php://input");
            $this->log("Postdata: " . $postdata, 'DEBUG');
            if (strlen($postdata) > 0) {
                try {
                    $xml = simplexml_load_string($postdata);
                    if ($xml === false) {
                        $this->log("Could not load XML from POST data", 'ERROR');
                        throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
                    }
                    // $this->log("XML: " . var_export($xml, true), 'DEBUG');
                    $emailaddress = (string) $xml->Request->EMailAddress;  // Ensure it's a string
                    if (empty($emailaddress)) {
                        $this->log("No email address in XML using fallback", 'WARNING');
                        $emailaddress = (string) $xml->EMailAddress;
                    }
                    if (empty($emailaddress)) {
                        $this->log("Email address not found in XML after fallback", 'ERROR');
                        throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
                    }
                    $this->log("Email address found: " . $emailaddress, 'INFO');
                    if (filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
                        $this->log("No valid email address in XML: " . $emailaddress, 'ERROR');
                        throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
                    }
                    $this->log("Email address found: " . $emailaddress);
                    return (object) ['email' => $emailaddress];
                } catch (Exception $e) {
                    $this->log("Could not process request: " . $e->getMessage(), 'ERROR');
                    throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
                }
            } else {
                $this->log("No POST data received", 'WARNING');
                throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->log("GET request received");
            // V1: Extract the email address from the URL path or query string

            // Check if the email is passed in the query string
            if (isset($_GET['email'])) {
                $this->log("Email address in query string: " . $_GET['email']);
                $emailaddress = $_GET['email'];
                // Validate email format
                if (filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
                    $this->log("Email address in query string is invalid: " . $emailaddress, 'ERROR');
                    throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
                }
                $this->log("Valid email found in query string: " . $emailaddress, 'INFO');
                return (object) ['email' => $emailaddress];
            }

            // If email is not in query string, check if it's part of the URL path (e.g., /autodiscover.json/v1.0/{email})
            $request_uri = $_SERVER['REQUEST_URI'];
            $pattern = '/\/autodiscover\.json\/v1\.0\/([^\/?]+)/'; // Adjusted regex to allow URL parameters
            if (preg_match($pattern, $request_uri, $matches)) {
                $emailaddress = $matches[1];
                // Validate email format
                if (filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
                    $this->log("Email address in URL path is invalid: " . $emailaddress, 'ERROR');
                    throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
                }
                $this->log("Valid email address found in URL path: " . $emailaddress, "INFO");
                return (object) ['email' => $emailaddress];
            }
        }

        // Default: No email address provided in either request
        $this->log("No email address provided", 'ERROR');
        throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
        $this->log("Starting XML document creation for Outlook");
        $writer->startDocument("1.0", "utf-8");
        $writer->setIndent(4);
        $writer->startElement("Autodiscover");
        $writer->writeAttribute("xmlns", "http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006");
        $writer->startElement("Response");
        $writer->writeAttribute(
            "xmlns",
            "http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a"
        );
        $writer->startElement("User");
        $writer->writeElement("DisplayName", $config->get_name());
        $writer->endElement();
        $writer->startElement("Account");
        $writer->writeElement("AccountType", "email");
        $writer->writeElement("Action", "settings");

        foreach ($config->get_servers() as $server) {
            if ($server->type == 'mta-sts') {
                continue;
            }
            foreach ($server->endpoints as $endpoint) {
                if ($this->write_protocol($writer, $server, $endpoint, $request)) {
                    break;
                }
            }
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $this->log("Finished writing XML document for Outlook");
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     *
     * @return bool
     */
    protected function write_protocol(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request)
    {
        $this->log("Writing protocol configuration for server type: " . $server->type . " host: " . $server->hostname, 'DEBUG');
        // Skip unsupported protocols
        if (!in_array($server->type, [ConnectionType::IMAP, ConnectionType::POP3, ConnectionType::SMTP])) {
            $this->log("Skipping protocol configuration due to unsupported type: " . $server->type, 'WARNING');
            return false;
        }
        switch ($endpoint->authentication) {
            case 'password-cleartext':
            case 'SPA':
                break;
            case 'none':
                if ($server->type !== 'smtp') {
                    $this->log("Skipping protocol configuration for 'none' authentication on non-SMTP server: " . $server->type, 'WARNING');
                    return false;
                }
                break;
            default:
                $this->log("Skipping protocol configuration due to unsupported authentication type: " . $endpoint->authentication, 'WARNING');
                return false;
        }

        $writer->startElement('Protocol');
        if (isset($_GET['debug_output'])) {
            $writer->writeComment("Server Config from: " . $server->getMethod());
        }
        $writer->writeElement('Type', strtoupper($server->type));
        $writer->writeElement('Server', $server->hostname);
        $writer->writeElement('Port', $endpoint->port);
        $writer->writeElement('LoginName', $this->get_username($server, $request));
        $writer->writeElement('DomainRequired', $server->domainRequired ? 'on' : 'off');
        $writer->writeElement('SPA', $endpoint->authentication === 'SPA' ? 'on' : 'off');

        if (property_exists($endpoint, 'socket_type')) {
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
            $this->log("Socket type configuration: " . $endpoint->socket_type, 'DEBUG');
        }

        $writer->writeElement("AuthRequired", $endpoint->authentication !== 'none' ? 'on' : 'off');

        if ($server->type == 'smtp') {
            $writer->writeElement('UsePOPAuth', $server->same_password ? 'on' : 'off');
            $writer->writeElement('SMTPLast', 'off');
        }

        $writer->endElement();
        $this->log("Finished writing protocol configuration for server type: " . $server->type . " host: " . $server->hostname, 'DEBUG');
        return true;
    }

    /**
     * @param string $authentication
     *
     * @return bool|false|string|null
     */
    protected function map_authentication_type(string $authentication)
    {
        switch ($authentication) {
            case 'password-cleartext':
                return 'password-cleartext';
            case 'CRAM-MD5':
                return 'password-encrypted';
            case 'none':
                return false;
            default:
                return null;
        }
    }
}