<?php

class ConnectionType
{
    public const IMAP = 'imap';
    public const SMTP = 'smtp';
    public const POP3 = 'pop3';
    public const ACTIVESYNC = 'activesync';
    public const CALDAV = 'caldav';
    public const CARDDAV = 'carddav';
    public const MTASTS = 'mta-sts';

    /**
     * Get all supported connection types
     * @return array
     */
    public static function getAllTypes(): array
    {
        return [
            self::IMAP,
            self::SMTP,
            self::POP3,
            self::ACTIVESYNC,
            self::CALDAV,
            self::CARDDAV,
            self::MTASTS
        ];
    }

    /**
     * Check if a given type is valid
     * @param string $type
     * @return bool
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getAllTypes(), true);
    }
}