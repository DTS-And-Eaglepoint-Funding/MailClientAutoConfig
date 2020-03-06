<?php
include './autoconfig.php';
include './exception_handling.php';

if (strpos($_SERVER['SERVER_NAME'], "autoconfig.") === 0) {
    // Configuration for Mozilla Thunderbird, Evolution, KMail, Kontact
    $handler = new MozillaHandler();
}
else if (strpos($_SERVER['SERVER_NAME'], "autodiscover.") === 0) {
    // Configuration for Outlook
    $handler = new OutlookHandler();
}

$exception_handler = new ExceptionHandling();

if ($handler) {
    try {
        $handler->get_response();
    }
    catch (Exception $exception) {
        $exception_handler->set_status(500)->set_exception($exception->getMessage());
        echo $exception_handler->get_response();
        return;
    }
}
$message = sprintf(Exceptions::INVALID_HOSTNAME_CALLED, $_SERVER['SERVER_NAME']);
$exception_handler->set_exception($message);
echo $exception_handler->get_response();
