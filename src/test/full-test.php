<?php
// --------------------------------------------------
// Helper functions for XML handling, request fetching and validation (same as before)
// --------------------------------------------------

function loadAndParseXML(string $xmlContent): SimpleXMLElement|false
{
    libxml_use_internal_errors(true); // Suppress libxml warnings
    try {
        $xml = new SimpleXMLElement($xmlContent);
        return $xml;
    } catch (Exception $e) {
        return false;
    }
}

function fetchXmlFromUrl(string $url, string $method = 'GET', string $postData = null, array $headers = []): string|false
{
    $curl = curl_init($url);

    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    $xmlContent = curl_exec($curl);

    if (curl_errno($curl)) {
        error_log('Error fetching XML: ' . curl_error($curl));
        curl_close($curl);
        return false;
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Error fetching XML. Response Status: " . $httpCode);
        return false;
    }


    return $xmlContent;
}

function validateThunderbirdAutoconfig(SimpleXMLElement $xml): array
{
    $errors = [];

    if (!$xml->emailProvider) {
        $errors[] = "Missing <emailProvider> element.";
        return $errors;
    }
    $emailProvider = $xml->emailProvider;
    if (!$emailProvider->domain) {
        $errors[] = "Missing <domain> element.";
    }
    if (!$emailProvider->incomingServer) {
        $errors[] = "Missing <incomingServer> element.";
    } else {
        if (!$emailProvider->incomingServer->hostname) {
            $errors[] = "Missing <incomingServer><hostname> element.";
        }
        if (!$emailProvider->incomingServer->port) {
            $errors[] = "Missing <incomingServer><port> element.";
        }
        if (!$emailProvider->incomingServer->socketType) {
            $errors[] = "Missing <incomingServer><socketType> element.";
        }
    }

    if (!$emailProvider->outgoingServer) {
        $errors[] = "Missing <outgoingServer> element.";
    } else {
        if (!$emailProvider->outgoingServer->hostname) {
            $errors[] = "Missing <outgoingServer><hostname> element.";
        }
        if (!$emailProvider->outgoingServer->port) {
            $errors[] = "Missing <outgoingServer><port> element.";
        }
        if (!$emailProvider->outgoingServer->socketType) {
            $errors[] = "Missing <outgoingServer><socketType> element.";
        }
    }

    return $errors;
}

function validateOutlookAutodiscover(SimpleXMLElement $xml): array
{
    $errors = [];

    if (!$xml->Response) {
        $errors[] = "Missing <Response> element.";
        return $errors;
    }
    $response = $xml->Response;
    if (!$response->User) {
        $errors[] = "Missing <User> element.";
    }
    if (!$response->Account) {
        $errors[] = "Missing <Account> element.";
        return $errors;
    }
    $account = $response->Account;
    if (!$account->AccountType) {
        $errors[] = "Missing <AccountType> element.";
    }
    if (!$account->Protocol) {
        $errors[] = "Missing <Protocol> element.";
    }

    foreach ($account->Protocol as $protocol) {
        if (!$protocol->Type) {
            $errors[] = "Missing <Protocol><Type> element.";
        }
        if (!$protocol->Server) {
            $errors[] = "Missing <Protocol><Server> element.";
        }
    }
    return $errors;
}
function validateMtaSts(string $stsRecord): array
{
    $errors = [];
    $lines = explode("\n", $stsRecord);
    $requiredFields = ['version', 'mode', 'max_age'];
    $foundFields = [];

    foreach ($lines as $line) {
        $line = trim($line); // Trim whitespace
        if (empty($line)) {
            continue; // Skip empty lines
        }
        if (strpos($line, ':') === false) {
            $errors[] = "Invalid MTA-STS line (missing colon): " . $line;
            continue; // skip to next line
        }

        list($key, $value) = explode(":", $line, 2);
        $key = trim($key);
        $value = trim($value);


        if (in_array($key, $requiredFields)) {
            $foundFields[$key] = $value;
        } elseif ($key === 'mx') {
            if (!isset($foundFields['mx'])) {
                $foundFields['mx'] = [];
            }
            $foundFields['mx'][] = $value;
        } else {
            $errors[] = "Unknown MTA-STS key: " . $key;
        }
    }

    foreach ($requiredFields as $field) {
        if (!isset($foundFields[$field])) {
            $errors[] = "Missing $field field in MTA-STS record.";
        }
    }

    if (!empty($errors)) {
        return $errors;
    }

    // Additional checks
    if ($foundFields['version'] !== 'STSv1') {
        $errors[] = "Invalid version: " . $foundFields['version'] . ". Expected STSv1.";
    }
    $allowedModes = ['enforce', 'testing', 'none'];
    if (!in_array($foundFields['mode'], $allowedModes)) {
        $errors[] = "Invalid mode: " . $foundFields['mode'] . ". Allowed modes are: " . implode(", ", $allowedModes);
    }
    if (!is_numeric($foundFields['max_age']) || $foundFields['max_age'] <= 0) {
        $errors[] = "Invalid max_age: " . $foundFields['max_age'] . ". Must be a positive integer.";
    }

    return $errors;
}
function validateXMLOutput(string $url, string $type, string $postData = null, array $headers = []): array
{
    $errors = [];
    $xmlContent = fetchXmlFromUrl($url, $postData ? 'POST' : 'GET', $postData, $headers);
    if ($xmlContent === false) {
        $errors[] = "Error: could not fetch XML content from URL.";
        return $errors;
    }

    $xml = loadAndParseXML($xmlContent);

    if ($xml === false) {
        $errors[] = "Error: invalid XML format";
        return $errors;
    }
    if ($type === 'thunderbird') {
        $errors = validateThunderbirdAutoconfig($xml);
    } elseif ($type === 'outlook') {
        $errors = validateOutlookAutodiscover($xml);
    } else {
        $errors[] = "Invalid validation type specified.";
    }

    return $errors;
}
// --------------------------------------------------
// Main script for CLI
// --------------------------------------------------

$emailAddresses = [
    'CONFIG_TEST@test.config',
    'TEXT_DNS_TEST@test.txt',
    'SRV_DNS_TEST@test.srv',
    'ISPDB_TEST@test.ispd',
    'KNOWN_MX_TEST@test.notispd',
    'FALLBACK_TEST@test.fallback',
    'INVALID_FALLBACK_TEST@test.invalid',

];

echo "Validation Results:\n";

foreach ($emailAddresses as $email) {
    $domain = substr($email, strpos($email, '@') + 1);
    $thunderbirdUrl = "http://autoconfig.".$domain.":8000/mail/config-v1.1.xml?emailaddress=" . $email;
    $outlookUrl = 'http://autodiscover.'.$domain.':8000/autodiscover/autodiscover.xml';
    $outlookPostData = '<Request><EMailAddress>' . $email . '</EMailAddress></Request>';

    $outlookHeaders = [
        'Content-Type: application/xml'
    ];

    echo "\nResults for: " . $email . "\n";

    $thunderbirdErrors = validateXMLOutput($thunderbirdUrl, 'thunderbird');
    $outlookErrors = validateXMLOutput($outlookUrl, 'outlook', $outlookPostData, $outlookHeaders);

    if (empty($thunderbirdErrors)) {
        echo "\n✅ Thunderbird XML from $thunderbirdUrl is valid.\n";
    } else {
        echo "\n❌ Thunderbird XML from $thunderbirdUrl errors:\n";
        foreach ($thunderbirdErrors as $error) {
            echo "    - " . $error . "\n";
        }
    }

    if (empty($outlookErrors)) {
        echo "\n✅ Outlook XML from $outlookUrl is valid.\n";
    } else {
        echo "\n❌ Outlook XML from $outlookUrl errors:\n";
        foreach ($outlookErrors as $error) {
            echo "    - " . $error . "\n";
        }
    }
}



$mtaStsDomains = [
    'Short_TXT@mtaststest.shorttxt',
    'Long_TXT@mtaststest.longtxt',
    'MX_No_TXT@mtaststest.mxnotxt',
    'Fallback@test.fallback',
    'Config_Mode_Only@mtaststest.configmode',          // Test 1: Only Mode
    'Config_Max_Age_Only@mtaststest.configmaxage',      // Test 2: Only Max Age
    'Config_Mode_And_Max_Age@mtaststest.configboth',        // Test 3: Mode and Max Age
    'Config_MX_Only@mtaststest.configmx',            // Test 4: Only MX Records
    'Config_All_Settings@mtaststest.configall',          // Test 5: All settings
    'Config_No_Settings@mtaststest.confignone',         // Test 6: No settings (fallback)
    'Config_Mode_Testing@mtaststest.mode.testing',    // Test 1: Mode testing
    'Config_Mode_Enforce@mtaststest.mode.enforce',    // Test 2: Mode enforce
    'Config_Mode_None@mtaststest.mode.none',        // Test 3: Mode none
    'Config_Mode_Invalid@mtaststest.mode.invalid',    // Test 4: Mode invalid
    'Config_Max_Age_Only@mtaststest.maxage',        // Test 5: Only Max Age
    'Config_Max_Age_Zero@mtaststest.maxage.zero',    // Test 6: Max Age Zero
    'Config_Max_Age_Negative@mtaststest.maxage.negative', // Test 7: Max Age Negative
    'Config_Mode_And_Max_Age@mtaststest.both',        // Test 9: Both Mode and Max Age
    'Config_MX_Only@mtaststest.mx',                // Test 10: Only MX Records
    'Config_Empty_MX@mtaststest.mx.empty',            // Test 11: Empty MX Records
    'Config_Invalid_MX@mtaststest.mx.invalid',        // Test 12: Invalid MX Records
    'Config_All_Settings@mtaststest.all',            // Test 13: All settings
    'Config_No_Settings@mtaststest.none',            // Test 14: No settings (fallback)
];
// $mtaStsDomains = [];
echo "\nMTA-STS Validation Results:\n";

foreach ($mtaStsDomains as $domain) {
    $parts = explode('@', $domain);
    $domain = $parts[1];
    $test_name = str_replace('_', ' ', $parts[0]);
    echo "\n\nTesting: " . $test_name . "\n";
    echo "Domain: " . $domain . "\n";
    echo "Results for: " . $domain . "\n";
    $mtaStsUrl = "http://mta-sts.".$domain.":8000/.well-known/mta-sts.txt";



    $mtaStsRecord = fetchXmlFromUrl($mtaStsUrl); // Reusing fetchXmlFromUrl

    if ($mtaStsRecord === false) {
        echo "  Error fetching MTA-STS record from $mtaStsUrl.\n";
    } else {
        $mtaStsErrors = validateMtaSts($mtaStsRecord);
        if (empty($mtaStsErrors)) {
            echo "\n✅ MTA-STS record from $mtaStsUrl is valid.\n";
            
        } else {
            echo "\n❌ MTA-STS record from $mtaStsUrl errors:\n";
            foreach ($mtaStsErrors as $error) {
                echo "    - " . $error . "\n";
            }
        }
    }
    echo "\n===========================\n";
}
