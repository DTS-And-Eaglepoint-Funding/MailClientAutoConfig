<?php

class LDAPUsernameResolver implements UsernameResolver {
    private $fileName;
    private $ldap;
    private $server;
    private $password;
    private $tree;
    private $attrs;
    private $filter;
    private $user_dn;
    

    function __construct($server, $user_dn, $password, $filter, $tree, $attrs) {
        $this->server = $server;
        $this->user_dn = $user_dn;
        $this->password = $password;
        $this->tree = $tree;
        $this->attrs = $attrs;
        $this->filter = $filter;
    }

    public function find_username($request) {
        static $cachedEmail = null;
        static $cachedUsername = null;

        if ($request->email === $cachedEmail) {
            return $cachedUsername;
        }

        // connect
        $ldapconn = ldap_connect($this->server) or die("Could not connect to LDAP server.");
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3) ;

        if($ldapconn) {
            // binding to ldap server
            $ldapbind = ldap_bind($ldapconn, $this->user_dn, $this->password) or die ("Error trying to bind: ".ldap_error($ldapconn));
            // verify binding
            if ($ldapbind) {
                $mail = $request->email;
                $mail_escaped = ldap_escape($mail, "", LDAP_ESCAPE_FILTER);
                $expanded_filter = str_replace("%m",$mail_escaped, $this->filter);
                $result = ldap_search($ldapconn, $this->tree, $expanded_filter, $this->attrs) or die ("Error in search query: ".ldap_error($ldapconn));
                $data = ldap_get_entries($ldapconn, $result);
                if($data["count"]==1){
                    $username = $data[0]["uid"][0];
                }
            } else {
                throw new Exception("LDAP bind failed");
            }
        }
        ldap_close($ldapconn);

        $cachedEmail = $request->email;
        $cachedUsername = $username;
        return $username;
    }
}