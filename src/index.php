<?php

include './autoconfig.php';
include './class/ExceptionHandling.php';

if (strpos($_SERVER['SERVER_NAME'], "autoconfig.") === 0) {
    // Configuration for Mozilla Thunderbird, Evolution, KMail, Kontact
    $handler = new MozillaHandler();
} else {
    if (strpos($_SERVER['SERVER_NAME'], "autodiscover.") === 0) {
        // Configuration for Outlook
        $handler = new OutlookHandler();
    }
}

$exception_handler = new ExceptionHandling();

if ($handler) {
    $response = '';
    try {
        $response = $handler->get_response();
    } catch (Exception $exception) {
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
