<?php

class ActiveSyncHandler extends RequestHandler {


    /**
     * @return object
     * @throws Exception
     */
    protected function parse_request(): object
    {
        // Check if email is provided in the query parameters FOR TESTING
        if (isset($_GET['emailaddress'])) {
            $emailaddress = filter_var($_GET['emailaddress'], FILTER_VALIDATE_EMAIL);
            if ($emailaddress) {
                return (object)['email' => $emailaddress];
            }
            throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
        }

    // Check if it's a POST request (V2) or GET request (V1)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // V2: Parse the XML body to extract the email address
        $postdata = file_get_contents("php://input");
        if (strlen($postdata) > 0) {
            $xml = simplexml_load_string($postdata);
            $emailaddress = (string)$xml->Request->EMailAddress;  // Ensure it's a string
            if (empty($emailaddress) || filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
            }
            return (object)['email' => $emailaddress];
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // V1: Extract the email address from the URL path or query string
        
        // Check if the email is passed in the query string
        if (isset($_GET['email'])) {
            $emailaddress = $_GET['email'];
            // Validate email format
            if (filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
            }
            return (object)['email' => $emailaddress];
        }
        
        // If email is not in query string, check if it's part of the URL path (e.g., /autodiscover.json/v1.0/{email})
        $request_uri = $_SERVER['REQUEST_URI'];
        $pattern = '/\/autodiscover\.json\/v1\.0\/([^\/]+)/';
        if (preg_match($pattern, $request_uri, $matches)) {
            $emailaddress = $matches[1];
            // Validate email format
            if (filter_var($emailaddress, FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
            }
            return (object)['email' => $emailaddress];
        }
    }

    // Default: No email address provided in either request
    throw new Exception(Exceptions::NO_MAILADDRESS_PROVIDED);
    }


    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request) {
        $writer->startDocument("1.0", "utf-8");
        $writer->setIndent(4);
        $writer->startElement("Autodiscover");
        $writer->writeAttribute("xmlns", "http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006");
        $writer->writeAttribute("xmlns:xsd", "http://www.w3.org/2001/XMLSchema");
        $writer->writeAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");

        $writer->startElement("Response");

        $writer->writeElement("Culture",'en:us');

        $writer->startElement("User");
        $writer->writeElement("DisplayName", 'Offcode' /*$config->displayName*/);
        $writer->writeElement("EMailAddress", $request->email);
        $writer->endElement(); // User

        $writer->startElement("Action");
        $writer->startElement("Settings");

        foreach ($config->get_servers() as $server) {
            foreach ($server->endpoints as $endpoint) {
                if ($this->writeProtocol($writer, $server, $endpoint, $request))
                    break;
            }
        }

        $writer->endElement(); //Settings
        $writer->endElement(); //Action
        $writer->endElement(); //Response
        $writer->endElement(); //Autodiscover
        $writer->endDocument();
    }

    protected function writeProtocol($writer, $server, $endpoint, $request) {
        if ($server->type !== 'activesync') return false;

        $writer->startElement('Server');
        $writer->writeElement('Type', 'MobileSync');

        switch ($endpoint->socketType) {
            case 'http':
                $url='http://';
                $suffix=($endpoint->port == 80)?'':(':'.$endpoint->port);
                break;
            case 'https':
                $url='https://';
                $suffix=($endpoint->port == 443)?'':(':'.$endpoint->port);
                break;
        }
        $url.=$server->hostname.$suffix.'/Microsoft-Server-ActiveSync';

        $writer->writeElement('Url', $url);
        $writer->writeElement('Name', $url);

        $writer->endElement();

        return true;
    }

    protected function map_authentication_type(string $authentication)
    {
        return null;
    }

}