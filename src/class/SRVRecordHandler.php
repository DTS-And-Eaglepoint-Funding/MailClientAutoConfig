<?php

class SRVRecordHandler extends RequestHandler
{

    private $domain;
    private $serverTypes = ['imap', 'smtp', 'pop3', 'caldav', 'carddav', 'activesync'];
    protected function parse_request()
    {
        $domain = isset($_GET['domain']) ? $_GET['domain'] : $_SERVER['SERVER_NAME'];
        if (strpos($domain, "mta-sts.") === 0) {
            $domain = substr($domain, 8);
        }
        $this->domain = $domain;
        $this->log("Parsed domain: " . $domain, 'DEBUG');
        return (object) ['domain' => $domain, 'email' => 'null@' . $domain];
    }


    protected function map_authentication_type(string $authentication)
    {
        // Not needed for SRV record output
        return null;
    }

    protected function write_xml(XMLWriter $writer, DomainConfiguration $config, stdClass $request)
    {
        // Not needed for HTML output
    }

    /**
     * @return string
     * @throws Exception
     */
    public function get_response(): string
    {
        $this->log("Starting get_response method.", 'DEBUG');
        $request = $this->parse_request();
        $this->log("Request parsed successfully.", 'DEBUG');
        $this->expand_request($request);
        $this->log("Request expanded successfully.", 'DEBUG');
        return $this->generate_html_output($request);
    }


    /**
     * @param stdClass $request
     *
     * @return string
     * @throws Exception
     */
    protected function generate_html_output_bak(stdClass $request): string
    {
        header("Content-Type: text/html");
        $this->log("Starting generate_html_output method.", 'DEBUG');
        $domain = $request->domain;
        $this->log("Generating HTML output for domain: " . $domain, 'DEBUG');
        $srvRecords = $this->resolveRecords($domain);

        $html = "<html><head><title>SRV Records for {$domain}</title><style>h1 {text-align: center;} h2 {text-align: center;} h3 {text-align: center;}  h5 {text-align: center;} table { border-collapse: collapse; width: 80%; margin: 20px auto; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style></head><body>";
        $html .= "<h1>SRV Records for: {$domain}</h1>";
        $html .= "<h5>NOTE: This page does NOT check the values; it only looks for the existence of SRV records.</h5>";
        foreach ($srvRecords as $type => $value) {
            $this->log("Generating HTML output for server type: " . $type, 'DEBUG');
            if (count($value['SRVStatus']['foundRecords']) === count($value['SRVStatus']['requiredRecords'])) {
                $html .= "<h2>" . strtoupper($type) . "</h2>";
                $html .= "<h2>All required SRV records for " . strtoupper($type) . " found.</h2>";
                $this->log("All required SRV records for server type: " . $type . " found.", 'DEBUG');
            } elseif ((count($value['SRVStatus']['requiredRecords']) - count($value['SRVStatus']['foundRecords'])) > 0) {
                if (!array_key_exists('hostname', $value)) {
                    $html .= "<h2>" . strtoupper($type) . " Values Unknown.</h2>";
                    $html .= "<br>";
                    $this->log($type . " values unknown.", 'WARNING');
                    continue;
                }
                if ($value['method'] == 'fallback') {
                    $html .= "<h2>" . strtoupper($type) . " Values Unknown.</h2>";
                    $html .= "<br>";
                    $this->log($type . " values unknown.", 'WARNING');
                    continue;
                }
                $html .= "<h2>" . strtoupper($type) . "</h2>";
                $html .= "<h3>Values from " . $value['method'] . ".</h3>";
                $html .= "<table>";
                $html .= "<thead><tr><th>Service</th><th>Protocol</th><th>Priority</th><th>Weight</th><th>Port</th><th>Target</th><th>Found</th></tr></thead><tbody>";
                foreach ($value['SRVStatus']['requiredRecords'] as $record) {
                    $parts = explode(".", $record);
                    $service = $parts[0];
                    $this->log('Service: ' . $service, 'DEBUG');
                    $protocol = $parts[1];
                    $this->log('Protocol: ' . $protocol, 'DEBUG');
                    if (in_array($record, $value['SRVStatus']['foundRecords'])) {
                        $html .= "<tr>";
                        $html .= "<td>{$service}</td>";
                        $html .= "<td>{$protocol}</td>";
                        $html .= "<td>0</td>";
                        $html .= "<td>1</td>";
                        $html .= "<td>{$value['port']}</td>";
                        $html .= "<td>{$value['hostname']}</td>";
                        $html .= "<td>&#9989;</td>";
                        $html .= "</tr>";
                        continue;
                    }
                    $html .= "<tr>";
                    $html .= "<td>{$service}</td>";
                    $html .= "<td>{$protocol}</td>";
                    $html .= "<td>0</td>";
                    $html .= "<td>1</td>";
                    $html .= "<td>{$value['port']}</td>";
                    $html .= "<td>{$value['hostname']}</td>";
                    $html .= "<td>&#10060;</td>";
                    $html .= "</tr>";

                }
                $html .= "</tbody></table>";
                $html .= "<br>";
            }
        }
        $html .= "</body></html>";
        $this->log("HTML output generated successfully", 'DEBUG');
        return $html;
    }

    protected function generate_html_output(stdClass $request): string
    {
        header("Content-Type: text/html");
        $this->log("Starting generate_html_output method.", 'DEBUG');
        $domain = $request->domain;
        $this->log("Generating HTML output for domain: " . $domain, 'DEBUG');
        $srvRecords = $this->resolveRecords($domain);

        $html = "<html>
<head>
    <title>SRV Records for {$domain}</title>
    <style>
        h1 { text-align: center; } 
        h2 { text-align: center; } 
        h3 { text-align: center; } 
        h5 { text-align: center; } 
        table { border-collapse: collapse; width: 80%; margin: 20px auto; } 
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; cursor: pointer; } 
        th { background-color: #f2f2f2; } 
        td:hover { background-color: #f9f9f9; }
    </style>
    <script>
        function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
        const toast = document.createElement('div');
        toast.innerText = `Copied: \${text}`;
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.backgroundColor = 'black';
        toast.style.color = 'white';
        toast.style.padding = '10px';
        toast.style.borderRadius = '5px';
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }, function(err) {
        alert('Failed to copy: ' + err);
    });
        }
    </script>
</head>
<body>";
        $html .= "<h1>SRV Records for: {$domain}</h1>";
        $html .= "<h5>NOTE: This page does NOT check the values; it only looks for the existence of SRV records.</h5>";

        foreach ($srvRecords as $type => $value) {
            $this->log("Generating HTML output for server type: " . $type, 'DEBUG');
            if (count($value['SRVStatus']['foundRecords']) === count($value['SRVStatus']['requiredRecords'])) {
                $html .= "<h2>" . strtoupper($type) . "</h2>";
                $html .= "<h2>All required SRV records for " . strtoupper($type) . " found.</h2>";
            } elseif ((count($value['SRVStatus']['requiredRecords']) - count($value['SRVStatus']['foundRecords'])) > 0) {
                if (!array_key_exists('hostname', $value)) {
                    $html .= "<h2>" . strtoupper($type) . " Values Unknown.</h2><br>";
                    continue;
                }
                if ($value['method'] == 'fallback') {
                    $html .= "<h2>" . strtoupper($type) . " Values Unknown.</h2><br>";
                    continue;
                }
                $html .= "<h2>" . strtoupper($type) . "</h2>";
                $html .= "<h3>Values from " . $value['method'] . ".</h3>";
                $html .= "<table>";
                $html .= "<thead><tr>
                <th>Service</th>
                <th>Protocol</th>
                <th>Priority</th>
                <th>Weight</th>
                <th>Port</th>
                <th>Target</th>
                <th>Found</th>
            </tr></thead><tbody>";
                foreach ($value['SRVStatus']['requiredRecords'] as $record) {
                    $parts = explode(".", $record);
                    $service = $parts[0];
                    $protocol = $parts[1];
                    $isFound = in_array($record, $value['SRVStatus']['foundRecords']);
                    $icon = $isFound ? "&#9989;" : "&#10060;";

                    $html .= "<tr>";
                    $html .= "<td onclick=\"copyToClipboard('{$service}')\">{$service}</td>";
                    $html .= "<td onclick=\"copyToClipboard('{$protocol}')\">{$protocol}</td>";
                    $html .= "<td onclick=\"copyToClipboard('0')\">0</td>";
                    $html .= "<td onclick=\"copyToClipboard('1')\">1</td>";
                    $html .= "<td onclick=\"copyToClipboard('{$value['port']}')\">{$value['port']}</td>";
                    $html .= "<td onclick=\"copyToClipboard('{$value['hostname']}')\">{$value['hostname']}</td>";
                    $html .= "<td>{$icon}</td>";
                    $html .= "</tr>";
                }
                $html .= "</tbody></table><br>";
            }
        }
        $html .= "</body></html>";
        $this->log("HTML output generated successfully", 'DEBUG');
        return $html;
    }


    protected function resolveRecords(string $domain)
    {
        $this->log("Resolving SRV records for domain: " . $domain, 'DEBUG');
        $resolver = new ServerConfigResolver('null@' . $domain);
        if (isset($_GET['debug'])) {
            $this->log("ServerConfigResolver cache disable", 'DEBUG');
            $resolver->disableCache();
        }

        $records = [];
        foreach ($this->serverTypes as $type) {
            try {
                $this->log("Resolving values for server type: " . $type, 'DEBUG');
                $serverConfig = $resolver->resolveSRVConfig($type);
                $records[$type] = $serverConfig;
                $this->log("Resolved values for server " . $type . ": " . json_encode($serverConfig), 'DEBUG');
            } catch (Exception $e) {
                // Log or handle specific server configuration errors if needed
                $this->log("Failed to resolve {$type} server config: " . $e->getMessage(), 'ERROR');
            }
        }
        $this->log("Got values: " . json_encode($records), 'DEBUG');
        return $records;
    }

    protected function get_domain_config(stdClass $request)
    {
        //Not used
        return null;
    }

    protected function read_config(stdClass $vars): void
    {
        //Not used
        return;
    }
}