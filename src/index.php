<?php

include __DIR__ . '/autoconfig.php';
include __DIR__ . '/class/ExceptionHandling.php';

if (!function_exists('get_dns_record')) {
    function get_dns_record($hostname, $type = DNS_A)
    {
        return dns_get_record($hostname, $type);
    }
}

/** @var MozillaHandler|OutlookHandler|null $handler */
$handler = null;

// Check URL path for service type
$path = strtolower($_SERVER['REQUEST_URI']);
if (
    strpos($path, "/srv") !== false
) {
    $handler = new SRVRecordHandler();
}else if (
    strpos($_SERVER['SERVER_NAME'], "autoconfig.") === 0 ||
    strpos($path, "/mail/config-v1.1.xml") !== false ||
    strpos($path, "/autoconfig") !== false
) {
    // Configuration for Mozilla Thunderbird, Evolution, KMail, Kontact
    $handler = new MozillaHandler();

} else if (
    strpos($_SERVER['SERVER_NAME'], "autodiscover.") === 0 ||
    strpos($path, "/autodiscover") !== false ||
    strpos($path, "/Autodiscover") !== false
) {
    // Configuration for Outlook
    $handler = new OutlookHandler();
} else if (
    strpos($_SERVER['SERVER_NAME'], "mta-sts.") === 0 ||
    strpos($path, "/.well-known/mta-sts.txt") !== false
) {
    // Configuration for MTA-STS
    $handler = new MTASTSHandler();
} 


$exception_handler = new ExceptionHandling();

if ($handler) {
    $response = '';
    try {
        $response = $handler->get_response();
    } catch (Exception $exception) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [error] [Index] " . $exception->getMessage();
        // Log to PHP error log
        error_log($logMessage);
        $exception_handler->set_status(500)->set_exception($exception->getMessage());
        $response = $exception_handler->get_response();
    } finally {
        echo $response;
        return;
    }
}
$message = sprintf(Exceptions::INVALID_HOSTNAME_CALLED, $_SERVER['SERVER_NAME']);
$exception_handler->set_exception($message);
echo $exception_handler->get_response();
