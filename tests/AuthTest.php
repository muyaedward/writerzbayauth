<?php

class AuthTest extends PHPUnit_Framework_TestCase
{
    public $auth;
    public $config;
    public $dbh;

    private $hash;

    public function __construct()
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../Auth.php';
        require_once __DIR__ . '/../Config.php';

        $this->dbh = new PDO("mysql:host=127.0.0.1;dbname=phpauthtest", "root", "");
        $this->config = new WriterzbayAuth\Config($this->dbh);
        $this->auth   = new WriterzbayAuth\Auth($this->dbh, $this->config);

        // Clean up the database
        $this->dbh->exec("DELETE FROM attempts;");
        $this->dbh->exec("DELETE FROM users;");
        $this->dbh->exec("DELETE FROM sessions;");
        $this->dbh->exec("DELETE FROM requests;");
    }
}
