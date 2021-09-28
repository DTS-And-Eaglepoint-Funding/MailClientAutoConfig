<?php

class AliasesFileUsernameResolver implements UsernameResolver
{
    private $file_name;

    /**
     * AliasesFileUsernameResolver constructor.
     *
     * @param string $fileName
     */
    function __construct(string $fileName = "/etc/mail/aliases")
    {
        $this->file_name = $fileName;
    }

    /**
     * @param stdClass $request
     *
     * @return string|null
     * @throws Exception
     */
    public function find_username(stdClass $request)
    {
        static $cached_email = null;
        static $cached_username = null;

        if ($request->email === $cached_email) {
            return $cached_username;
        }

        $fp = fopen($this->file_name, 'rb');

        if ($fp === false) {
            throw new Exception(sprintf(Exceptions::ALIAS_NOT_OPENABLE, $this->file_name));
        }

        $username = $this->find_localpart($fp, $request->localpart);
        if (strpos($username, "@") !== false || strpos($username, ",") !== false) {
            $username = null;
        }

        $cached_email = $request->email;
        $cached_username = $username;
        return $username;
    }

    /**
     * @param resource $fp
     * @param string $localpart
     *
     * @return string
     * @throws Exception
     */
    protected function find_localpart($fp, string $localpart)
    {
        while (($line = fgets($fp)) !== false) {
            $matches = [];
            if (!preg_match("/^\s*" . preg_quote($localpart) . "\s*:\s*(\S+)\s*$/", $line, $matches)) {
                continue;
            }
            return $matches[1];
        }
        throw new Exception(Exceptions::LOCALPART_NOT_PARSEABLE);
    }
}
