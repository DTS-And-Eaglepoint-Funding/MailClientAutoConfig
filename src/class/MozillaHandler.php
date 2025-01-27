<?php

class MozillaHandler extends RequestHandler
{

    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request()
    {
        $this->log("Parsing request for Mozilla configuration");
        $emailaddress = array_key_exists('emailaddress', $_GET) ? $_GET['emailaddress'] : null;
        if (is_null($emailaddress)) {
            $this->log("No email address provided in the request", 'ERROR');
            throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
        }
        $this->log("Email address found in request: " . $emailaddress);
        return (object) ['email' => $emailaddress];
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
        $this->log("Starting XML document creation");
        $writer->startDocument("1.0");
        $writer->setIndent(4);
        $writer->startElement("clientConfig");
        $writer->writeAttribute("version", "1.1");

        $this->write_email_provider($writer, $config, $request);
        $this->write_oauth2_config($writer, $config);
        $this->write_additional_services($writer, $config);

        $writer->endElement();
        $writer->endDocument();
        $this->log("Finished writing XML document for Mozilla configuration");
    }
    /**
     * Write OAuth2 configuration to XML
     * 
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     */
    protected function write_oauth2_config(XMLWriter $writer, DomainConfiguration $config)
    {
        $this->log("Writing OAuth2 configuration");
        $oauth2 = $config->get_oauth2_config();
        if ($oauth2) {
            $writer->startElement("oAuth2");
            $writer->writeElement("issuer", $oauth2['issuer']);
            $writer->writeElement("scope", implode(' ', $oauth2['scopes']));
            $writer->writeElement("authURL", $oauth2['authUrl']);
            $writer->writeElement("tokenURL", $oauth2['tokenUrl']);
            $writer->endElement();
            $this->log("OAuth2 configuration written successfully");
        } else {
            $this->log("No OAuth2 configuration found for this domain", 'DEBUG');
        }
    }
    /**
     * Write additional services (CalDAV, CardDAV) to XML
     * 
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     */
    protected function write_additional_services(XMLWriter $writer, DomainConfiguration $config)
    {
        $this->log("Writing additional services (CalDAV, CardDAV) configuration");
        foreach ($config->get_servers() as $server) {
            if ($server->type == 'caldav') {
                $writer->startElement("calendarServer");
                if (isset($_GET['debug_output'])) {
                    $writer->writeComment("Server Config from: " . $server->getMethod());
                }
                $writer->writeElement("hostname", $server->hostname);
                $writer->endElement();
                $this->log("CalDAV server configuration written for host: " . $server->hostname);
            }
            if ($server->type == 'carddav') {
                $writer->startElement("addressBookServer");
                if (isset($_GET['debug_output'])) {
                    $writer->writeComment("Server Config from: " . $server->getMethod());
                }
                $writer->writeElement("hostname", $server->hostname);
                $writer->endElement();
                $this->log("CardDAV server configuration written for host: " . $server->hostname);
            }

        }
        $this->log("Finished writing additional services configuration");
    }
    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_email_provider(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
        $this->log("Writing email provider configuration");
        $writer->startElement("emailProvider");
        $writer->writeAttribute("id", $config->get_id());

        foreach ($config->get_domains() as $domain) {
            $writer->writeElement("domain", $domain);
            $this->log("Domain added to provider: " . $domain);
        }

        $writer->writeElement("displayName", $config->get_name());
        $writer->writeElement("displayShortName", $config->get_shortname());

        foreach ($config->get_servers() as $server) {
            if ($server->type == 'mta-sts') {
                continue;
            }
            foreach ($server->endpoints as $endpoint) {
                $this->write_server($writer, $server, $endpoint, $request);
            }
        }

        if ($config->get_documentation()) {
            // Add documentation
            foreach ($config->get_documentation() as $doc) {
                $writer->startElement("documentation");
                $writer->writeAttribute("url", $doc['url']);
                $writer->writeElement("descr", $doc['description']);
                $writer->endElement();
                $this->log("Documentation link added: " . $doc['url']);
            }
        }
        $writer->endElement();
        $this->log("Finished writing email provider configuration");
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     */
    protected function write_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request)
    {
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
    protected function write_incoming_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request)
    {
        $this->log("Writing incoming server configuration for type: " . $server->type . " host: " . $server->hostname);
        $authentication = $this->map_authentication_type($endpoint->authentication);
        if (empty($authentication)) {
            $this->log("Skipping incoming server config due to no authentication type", "WARNING");
            return;
        }

        $writer->startElement("incomingServer");
        $writer->writeAttribute("type", $server->type);
        if (isset($_GET['debug_output'])) {
            $writer->writeComment("Server Config from: " . $server->getMethod());
        }
        $writer->writeElement("hostname", $server->hostname);
        $writer->writeElement("port", $endpoint->port);
        $writer->writeElement("socketType", $endpoint->socketType);
        $writer->writeElement("username", $this->get_username($server, $request));
        $writer->writeElement("authentication", $authentication);
        $writer->endElement();
        $this->log("Finished writing incoming server config for type: " . $server->type . " host: " . $server->hostname);
    }

    /**
     * @param XMLWriter $writer
     * @param Server $server
     * @param stdClass $endpoint
     * @param stdClass $request
     */
    protected function write_smtp_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request)
    {
        $this->log("Writing outgoing server configuration for host: " . $server->hostname);
        $authentication = $this->map_authentication_type($endpoint->authentication);
        if ($authentication === null) {
            $this->log("Skipping outgoing server config due to no authentication type", "WARNING");
            return;
        }

        $writer->startElement("outgoingServer");
        if (isset($_GET['debug_output'])) {
            $writer->writeComment("Server Config from: " . $server->getMethod());
        }
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
        $this->log("Finished writing outgoing server config for host: " . $server->hostname);
    }

    /**
     * @param string $authentication
     *
     * @return string|null
     */
    protected function map_authentication_type(string $authentication)
    {
        switch ($authentication) {
            case 'password-cleartext':
                return 'password-cleartext';
            case 'CRAM-MD5':
                return 'password-encrypted';
            case 'none':
                return '';
            default:
                return null;
        }
    }
}