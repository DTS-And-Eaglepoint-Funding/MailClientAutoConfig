<?php

class PdoUsernameResolver implements UsernameResolver {
    private $query;
    private $user;
    private $password;
    private $dbname;
    private $host;
    private $driver;

    function __construct($query = 'select u.email as user from virtual_users u where u.email = :email union select u.email as user from virtual_users u, virtual_aliases a where u.email = a.destination and a.source=:email', $user = "mailread", $password="password", $dbname="mailserver", $host="127.0.0.1", $driver='mysql') {
        $this->query = $query;
        $this->user = $user;
        $this->password = $password;
        $this->dbname = $dbname;
        $this->host = $host;
        $this->driver = $driver;
    }

    public function find_username($request) {
        static $cachedEmail = null;
        static $cachedUsername = null;

        if ($request->email === $cachedEmail) {
            return $cachedUsername;
        }

        $username = null;

        $pdo = new PDO(sprintf('%s:host=%s;dbname=%s', $this->driver, $this->host, $this->dbname), $this->user, $this->password);
        $stmt = $pdo->prepare($this->query);
        $stmt->bindParam(':email', $request->email);
        if ($stmt->execute())
        {
            while ($row = $stmt->fetch()) {
                $username = $row[0];
                break;
            }
        }
        $pdo = null;

        $cachedEmail = $request->email;
        $cachedUsername = $username;
        return $username;        
    }
}