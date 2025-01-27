<?php

class Exceptions
{
    const DOMAIN_NOT_CONFIGURED = "No configuration found for requested domain '%s'.";
    const UNKNOWN_SOCKET_TYPE = "Unrecognized server type '%s'";
    const ALIAS_NOT_OPENABLE = "Unable to open aliases file '%s'";
    const LOCALPART_NOT_PARSEABLE = "Unable to parse \$localPart";
    const NO_MAILADDRESS_PROVIDED = 'No emailaddress provided';
    const INVALID_HOSTNAME_CALLED = 'Invalid hostname called "%s"';
    const UNKNOWN_CONNECTION_TYPE = 'Unknown connection type "%s"';
    const OVERLAPPING_DOMAINS = "Overlapping domains '%s' found in configurations.";
}
