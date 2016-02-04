<?php

class db {};

class DLEIntegration_DLE
{
    static protected $_instance;

    protected $_cfg;

    protected function __construct()
    {
        require_once dirname(__FILE__) . '/config/dbconfig.php';
        require_once dirname(__FILE__) . '/config/dle_config.php';

        if (!defined('F_CHARSET'))
        {
            define('F_CHARSET', 'UTF-8');
        }

        if ($this->_clean_url($_SERVER['HTTP_HOST']) == $this->_getCookieDomain() && !session_id())
        {
            session_start();
        }
    }

    /**
     *
     * @return self
     */
    static public function getInstance()
    {
        if (!self::$_instance)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     *
     * @staticvar PDO $dbh
     * @return \PDO
     */
    protected function _getDb()
    {
        static $dbh = null;

        if (!$dbh)
        {
            $dbh = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBNAME, DBUSER, DBPASS);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $dbh->exec('SET SQL_MODE=""');
            $dbh->exec('SET NAMES ' . COLLATE);
        }

        return $dbh;
    }


    protected function _getParser()
    {
        static $parse = null;

        if (!$parse)
        {
            $parse = new DLEIntegration_ParseFilter();
        }

        return $parse;
    }

    /**
     * @return XenForo_BbCode_Parser
     */
    protected function _getXenParser()
    {
        static $parse = null;

        if (!$parse)
        {
            $parse = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Base'));
        }

        return $parse;
    }
    

    #region Public function

    public function login($username, $password)
    {
        if (!DLE_LOGIN)
        {
            return false;
        }

        $user = $this->findDLEUser($username, $password);

        if ($user)
        {
            $password = $this->_convert_encoding($password);

            $domain = "." . $this->_getCookieDomain();
            setcookie ("dle_password", md5($password), time() + 3600 * 24 * 365, "/", $domain);
            setcookie ("dle_user_id", $user->user_id, time() + 3600 * 24 * 365, "/", $domain);
        }
        
        return false;
    }

    public function logout()
    {
        if (!DLE_LOGIN)
        {
            return false;
        }

        $domain = "." . $this->_getCookieDomain();
        setcookie("dle_name", "", time() - 3600, "/", $domain);
        setcookie("forum_session_id", "", time() - 3600, "/", $domain);
        setcookie("dle_user_id", "", time() - 3600, "/", $domain);
        setcookie("dle_user_id", "", time() - 3600, "/");
        setcookie("dle_password", "", time() - 3600, "/", $domain);
        setcookie("dle_skin", "", time() - 3600, "/", $domain);
        setcookie("dle_newpm", "", time() - 3600, "/", $domain);
        setcookie("dle_hash", "", time() - 3600, "/", $domain);
        setcookie("PHPSESSID", "", time() - 3600, "/", $domain);
        setcookie("PHPSESSID", "", time() - 3600, "/");
        setcookie(session_name(),"",time() - 3600, "/", $domain);

        if (session_id())
        {
            $_SESSION['dle_name']        = "";
            $_SESSION['dle_password']    = "";
            @session_destroy();
            @session_unset();
        }
        
        return false;
    }

    public function insert(array $fields)
    {
        if (!DLE_REGISTER) {
            return false;
        }
        
        $fields = array_merge(array(
            "reg_date"      => time(),
            "lastdate"      => time(),
            "user_group"    => USER_GROUP,
            "info"          => '',
            "signature"     => '',
            "xfields"       => '',
            "favorites"     => '',
            "logged_ip"     => $_SERVER['REMOTE_ADDR'],
        ), $fields);

        $this->prepareValues($fields);

        $sth = $this->_getDb()->prepare('SELECT user_id FROM ' . USERPREFIX . "_users WHERE name=? OR email=?");
        $sth->execute(array($fields['name'], $fields['email']));

        if ($sth->fetchColumn())
        {
            return true;
        }

        $this->_getDb()->prepare('INSERT INTO ' . USERPREFIX . '_users (' . implode(", " , array_keys($fields)) . ") VALUES (" . implode(", ", $this->_getDBPrepareKeys($fields)) . ")")
            ->execute($fields);
        
        return false;
    }
    
    public function update($username, array $fields)
    {
        if (!DLE_PROFILE) {
            return false;
        }
        
        $user = $this->findDLEUser($username);
        
        if (!$user) {
            return true;
        }
        
        $update = array();
        foreach ($fields as $field => $value) {
            $update[] = "`$field`=:" . $field;
        }

        $this->prepareValues($fields);

        $this->_getDb()->prepare('UPDATE ' . USERPREFIX . '_users SET ' . implode(", " , $update) . " WHERE user_id=:user_id")
            ->execute(array_merge($fields, array("user_id" => $user->user_id)));
        
        return false;
    }
    
    public function prepareValues(array &$fields)
    {
        foreach ($fields as $name => &$value) {
            if (in_array($name, array('signature', 'info'))) {
                $value = strip_tags($this->_getXenParser()->render($value), "<br>");
            }
            
            $value = $this->_convert_encoding($value);
        }
    }
    
    public function findDLEUser($login, $password = null)
    {
        $username = $this->_convert_encoding($login);
        $password = $this->_convert_encoding($password);

        if (strpos($username, "@")) {
            $sth = $this->_getDb()->prepare("SELECT * FROM " . USERPREFIX . "_users WHERE email=?");
        }
        else {
            $sth = $this->_getDb()->prepare("SELECT * FROM " . USERPREFIX . "_users WHERE name=?");
        }
        $sth->execute(array($username));
        $user = $sth->fetchObject();

        if ($user && (!$password || md5(md5($password)) == $user->password))
        {
            return $user;
        }
        
        return false;
    }

    protected function _getDBPrepareKeys(array $array)
    {
        $return = array();
        foreach (array_keys($array) as $key)
        {
            $return[] = ":" . $key;
        }

        return $return;
    }

    protected function _getCookieDomain()
    {
        return $this->_clean_url(DLE_DOMAIN);
    }

    protected function _clean_url($url)
    {
        if (!$url)
        {
            return '';
        }

        $url = str_replace("http://", "", $url);
        if (strtolower(substr($url, 0, 4)) == 'www.')  $url = substr($url, 4);
        $url = explode('/', $url);
        $url = reset($url);
        $url = explode(':', $url);
        $url = reset($url);

        return $url;
    }

    protected function _convert_encoding($text, $revert = false)
    {
        if (!$revert)
        {
            $in_charset = F_CHARSET;
            $out_charset = DLE_CHARSET;
        }
        else
        {
            $in_charset = DLE_CHARSET;
            $out_charset = F_CHARSET;
        }

        if (is_array($text))
        {
            foreach($text as $k => $t)
            {
                $text[$k] = $this->_convert_encoding($t);
            }
        }
        else
        {
            if (strtoupper($in_charset) != strtoupper($out_charset))
            {
                $text = iconv($in_charset, $out_charset, $text);
            }
        }

        return $text;
    }

    /**
     * @param string $string
     * @return array|string
     */
    public function convertEncodingFromDLE($string)
    {
        return $this->_convert_encoding($string, true);
    }

    /**
     * @param string $string
     * @return array|string
     */
    public function convertEncodingToDLE($string)
    {
        return $this->_convert_encoding($string);
    }

    public function __get($varname)
    {
        throw new Exception('unknown property ' . $varname);
    }

    public function __desctruct()
    {

    }
}
