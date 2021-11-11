<?php

class MozillaHandler extends RequestHandler
{

    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request()
    {
        $emailaddress = array_key_exists('emailaddress', $_GET) ? $_GET['emailaddress'] : null;
        if (is_null($emailaddress)) {
            throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
        }
        return (object)['email' => $emailaddress];
    }

    /**
     * @param XMLWriter $writer
     * @param DomainConfiguration $config
     * @param stdClass $request
     */
    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
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
    protected function write_email_provider(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
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
        $authentication = $this->map_authentication_type($endpoint->authentication);
        if (empty($authentication)) {
            return;
        }

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
    protected function write_smtp_server(XMLWriter $writer, Server $server, stdClass $endpoint, stdClass $request)
    {
        $authentication = $this->map_authentication_type($endpoint->authentication);
        if ($authentication === null) {
            return;
        }

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
