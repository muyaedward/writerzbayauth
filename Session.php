<?php

namespace WriterzbayAuth;
use PHPMailer\PHPMailer\PHPMailer;
/**
 * Auth class
 * Required PHP 5.4 and above.
 */

class Session
{
    protected $dbh;
    public $config;
    /**
     * Initiates database connection
     */
    public function __construct(\PDO $dbh, $config)
    {
        $this->dbh = $dbh;
        $this->config = $config;

        if (version_compare(phpversion(), '5.4.0', '<')) {
            die('PHP 5.4.0 required for Writerzbay engine!');
        }
        date_default_timezone_set($this->config->site_timezone);
    }

   /**
    * Returns IP address
    * @return string $ip
    */
    protected function getTheIp()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '') {
           return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
           return $_SERVER['REMOTE_ADDR'];
        }
    }

    protected function getTheAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }
    /**
    * Logs out the session, identified by hash
    * @param string $hash
    * @return boolean
    */

    public function logout()
    {
        $hash = $this->getSessionHash();
        if (strlen($hash) != 40) {
            return false;
        }
        return $this->deleteSession($hash);
    }   
    /**
    * Creates a session for a specified user id
    * @param int $uid
    * @param boolean $remember
    * @return array $data
    */

    public function addSession($user, $sessions, $remember = false)
    {
        $ip = $this->getTheIp();
        $token_type = $sessions['token_type'];
        $expires_in = $sessions['expires_in'];
        $access_token = $sessions['access_token'];
        $refresh_token = $sessions['refresh_token'];
        $uid = $sessions['uid'];
        $agent = $this->getTheAgent();

        $data['hash'] = sha1($this->config->site_key . $access_token . microtime());
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $this->deleteExistingSessions($uid);
        if ($remember == true) {
            $data['expire'] = date("Y-m-d H:i:s", strtotime($this->config->cookie_remember));
            $data['expiretime'] = strtotime($data['expire']);
        } else {
            $data['expire'] = date("Y-m-d H:i:s", strtotime($this->config->cookie_forget));
            $data['expiretime'] = 0;
        }
        $data['cookie_crc'] = sha1($data['hash'] . $this->config->site_key);
        $query = $this->dbh->prepare("INSERT INTO {$this->config->table_sessions} (uid, token_type, expires_in, ip, agent, access_token, refresh_token, hash, cookie_crc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$query->execute(array($uid, $token_type, $data['expire'], $ip, $agent, $access_token, $refresh_token, $data['hash'], $data['cookie_crc']))) {
            return false;
        }
        $data['expire'] = strtotime($data['expire']);
        return $data;
    }

    /**
    * Removes all existing sessions for a given UID
    * @param int $uid
    * @return boolean
    */

    protected function deleteExistingSessions($uid)
    {
        $query = $this->dbh->prepare("DELETE FROM {$this->config->table_sessions} WHERE uid = ?");
        $query->execute(array($uid));
        setcookie("user", "", time() - 3600);
        setcookie($this->config->cookie_name, "", time() - 3600);
        return $query->rowCount() == 1;
    }

    /**
    * Removes a session based on hash
    * @param string $hash
    * @return boolean
    */

    protected function deleteSession($hash)
    {
        $query = $this->dbh->prepare("DELETE FROM {$this->config->table_sessions} WHERE hash = ?");
        $query->execute(array($hash));
        setcookie("user", "", time() - 3600);
        setcookie($this->config->cookie_name, "", time() - 3600);
        return $query->rowCount() == 1;
    }

    /**
    * Function to check if a session is valid
    * @param string $hash
    * @return boolean
    */

    public function checkSession($hash)
    {
        $query = $this->dbh->prepare("SELECT id, uid, expires_in, cookie_crc FROM {$this->config->table_sessions} WHERE hash = ?");
        $query->execute(array($hash));
        if ($query->rowCount() == 0) {
            return false;
        }
        $row = $query->fetch(\PDO::FETCH_ASSOC);
        $sid = $row['id'];
        $uid = $row['uid'];
        $expiredate = strtotime($row['expires_in']);
        $currentdate = strtotime(date("Y-m-d H:i:s"));
        $db_cookie = $row['cookie_crc'];
        if ($currentdate > $expiredate) {
            $this->deleteExistingSessions($uid);

            return false;
        }
        if ($db_cookie == sha1($hash . $this->config->site_key)) {
            return true;
        }
        return false;
    }

    /**
    * Retrieves the UID associated with a given session hash
    * @param string $hash
    * @return int $uid
    */

    public function getSessionUID($hash)
    {
        $query = $this->dbh->prepare("SELECT uid FROM {$this->config->table_sessions} WHERE hash = ?");
        $query->execute(array($hash));
        if ($query->rowCount() == 0) {
            return false;
        }
        return $query->fetch(\PDO::FETCH_ASSOC)['uid'];
    }

    /**
    * Returns is user logged in
    * @return boolean
    */
    public function isLogged() {
        return (isset($_COOKIE[$this->config->cookie_name]) && $this->checkSession($_COOKIE[$this->config->cookie_name]));
    }

    /**
     * Returns current session hash
     * @return string
     */
    public function getSessionHash(){
        if (isset($_COOKIE[$this->config->cookie_name])) {
            return $_COOKIE[$this->config->cookie_name];
        }
        return false;        
    }
}
