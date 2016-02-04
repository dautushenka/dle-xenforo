<?php

/**
 * Class XenIntegration
 * 
 * @property array $options
 * @property XenForo_PasswordHash $passwordGenerator
 * @property \PDO $db
 * @property \DLE_API $dleAPI
 */
class XenIntegration
{
    static protected $_instance;

    protected $config;
    protected $XenConfig;
    protected $DLEConfig;
    protected $lang;

    protected $_user = array();

    /**
     * @var ParseFilter
     */
    protected $_parse = null;

    protected function __construct()
    {   
        $forumConfigFile = dirname(__FILE__) . "/config.php";
        if (!file_exists($forumConfigFile)) {
            $this->displayAndExit("Вы должны скопировать файл конфигурации %s с форума в папку с модулем интеграции %s", 'library/config.php', $forumConfigFile);
        }
        
        $config = require dirname(__FILE__) . "/xen_default_config.php";
        require $forumConfigFile;
        $this->XenConfig = $config;
        
        if (empty($this->XenConfig['globalSalt'])) {
            $this->displayAndExit("Значение для globalSalt не установлено в конфиге %s", $forumConfigFile);
        }
        
        $this->DLEConfig = $GLOBALS['config'];

        define('F_PREFIX', 'xf_');

        if (!defined('F_CHARSET'))
        {
            define('F_CHARSET', 'UTF-8');
        }

        $configFile = ENGINE_DIR . "/data/dle_xen_conf.php";
        if (!file_exists($configFile))
        {
            $this->displayAndExit("Не найден конфиг интеграции. Пройдите процесс установки");
        }
        $this->config = require $configFile;

        $this->lang = require ROOT_DIR . '/language/Russian/dle_xen.lng';
        $lngFile = ROOT_DIR . '/language/' . $GLOBALS['config']['langs'] . '/dle_xen.lng';
        if (file_exists($lngFile)) {
            $this->lang = array_merge($this->lang, include $lngFile);
        }
    }
    
    protected function displayAndExit($text)
    {
        $params = func_get_args();
        array_shift($params);
        
        @header("Content-type: text/html; charset=UTF-8");
        call_user_func_array('printf', array($text) + $params);
        exit();
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
        static $dbh;

        if (!$dbh)
        {
            $dbh = new PDO("mysql:host={$this->XenConfig['db']['host']};port={$this->XenConfig['db']['port']};dbname=" . $this->XenConfig['db']['dbname'], $this->XenConfig['db']['username'], $this->XenConfig['db']['password']);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $dbh->exec('SET SQL_MODE=""');
            $dbh->exec('SET NAMES `utf8`');
        }

        return $dbh;
    }

    protected function _getConfigForum()
    {
        static $config = array();

        if ($config)
        {
            return $config;
        }

        if (!function_exists("dle_cache") || !($cache = dle_cache("config_xen")))
        {
            $sth = $this->_getdb()->query("SELECT 
                        option_id, 
                        option_value, 
                        data_type
                        FROM xf_option WHERE option_id IN ('boardUrl', 'registrationDefaults', 'guestTimeZone')");

            while ($row = $sth->fetch(PDO::FETCH_ASSOC))
            {
                if ($row['data_type'] == "array"){
                    $config[$row['option_id']] = unserialize($row['option_value']);
                }
                else {
                    $config[$row['option_id']] = $row['option_value'];
                }
            }

            if (function_exists("create_cache"))
            {
                create_cache("config_xen", serialize($config));
            }

            return $config;
        }
        elseif ($cache)
        {
            $config = unserialize($cache);
        }

        return $config;
    }

    protected function _init_parse()
    {
        if (!$this->_parse)
        {
            if (empty($GLOBALS['parse']) || !($GLOBALS['parse'] instanceof ParseFilter))
            {
                if (!class_exists('ParseFilter'))
                {
                    require_once(ENGINE_DIR . "/classes/parse.class.php");
                }
                $this->_parse = new ParseFilter();
            }
            else
            {
                $this->_parse = $GLOBALS['parse'];
            }
        }

        return $this->_parse;
    }
    
    protected function _getPasswordGenerator()
    {
        static $password;
        
        if (!$password) {
            require_once dirname(__FILE__) . "/PasswordHash.php";
            $password = new XenForo_PasswordHash($this->XenConfig['passwordIterations'], false);
        }
        
        return $password;
    }
    
    protected function getDLEAPI()
    {
        global $config, $db;
        static $dle_api;
        
        if (!$dle_api) {
            
            if (!empty($GLOBALS['dle_api'])) {
                $dle_api = $GLOBALS['dle_api'];
            }
            else {
                require_once ENGINE_DIR . "/api/api.class.php";
            }
        }
        
        return $dle_api;
    }

    protected function convertIpStringToBinary($ip)
    {
        $originalIp = $ip;
        $ip = trim($ip);

        if (strpos($ip, ':') !== false)
        {
            // IPv6
            if (preg_match('#:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$#', $ip, $match))
            {
                // embedded IPv4
                $long = ip2long($match[1]);
                if (!$long)
                {
                    return false;
                }

                $hex = str_pad(dechex($long), 8, '0', STR_PAD_LEFT);
                $v4chunks = str_split($hex, 4);
                $ip = str_replace($match[0], ":$v4chunks[0]:$v4chunks[1]", $ip);
            }

            if (strpos($ip, '::') !== false)
            {
                if (substr_count($ip, '::') > 1)
                {
                    // ambiguous
                    return false;
                }

                $delims = substr_count($ip, ':');
                if ($delims > 7)
                {
                    return false;
                }

                $ip = str_replace('::', str_repeat(':0', 8 - $delims) . ':', $ip);
                if ($ip[0] == ':')
                {
                    $ip = '0' . $ip;
                }
            }

            $ip = strtolower($ip);

            $parts = explode(':', $ip);
            if (count($parts) != 8)
            {
                return false;
            }

            foreach ($parts AS &$part)
            {
                $len = strlen($part);
                if ($len > 4 || preg_match('/[^0-9a-f]/', $part))
                {
                    return false;
                }

                if ($len < 4)
                {
                    $part = str_repeat('0', 4 - $len) . $part;
                }
            }

            $hex = implode('', $parts);
            if (strlen($hex) != 32)
            {
                return false;
            }

            return $this->convertHexToBin($hex);
        }
        else if (strpos($ip, '.'))
        {
            // IPv4
            if (!preg_match('#(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})#', $ip, $match))
            {
                return false;
            }

            $long = ip2long($match[1]);
            if (!$long)
            {
                return false;
            }

            return $this->convertHexToBin(
                str_pad(dechex($long), 8, '0', STR_PAD_LEFT)
            );
        }
        else if (strlen($ip) == 4 || strlen($ip) == 16)
        {
            // already binary encoded
            return $ip;
        }
        else if (is_numeric($originalIp) && $originalIp < pow(2, 32))
        {
            // IPv4 as integer
            return $this->convertHexToBin(
                str_pad(dechex($originalIp), 8, '0', STR_PAD_LEFT)
            );
        }
        else
        {
            return false;
        }
    }

    protected function convertHexToBin($hex)
    {
        if (function_exists('hex2bin'))
        {
            return hex2bin($hex);
        }

        $len = strlen($hex);

        if ($len % 2)
        {
            trigger_error('Hexadecimal input string must have an even length', E_USER_WARNING);
        }

        if (strspn($hex, '0123456789abcdefABCDEF') != $len)
        {
            trigger_error('Input string must be hexadecimal string', E_USER_WARNING);
        }

        return pack('H*', $hex);
    }
    
    protected function createDLEUser(stdClass $user, $password)
    {
        /** @var $db \db */
        $db = $GLOBALS['db'];
        
        $statusCode = $this->dleAPI->external_register(
            $this->_convert_encoding($user->username, true),
            $password,
            $this->_convert_encoding($user->email, true),
            $this->DLEConfig['reg_group']
        );

        if ($statusCode !== 1) {
            return false;
        }
        $user_id = $db->insert_id();

        $stm = $this->db->prepare("SELECT location, about, signature FROM " . F_PREFIX . "user_profile WHERE user_id=?");
        $stm->execute(array($user->user_id));
        
        $profile = $stm->fetchObject();
        
        $info = $db->safesql($this->_convert_encoding($profile->about, true));
        $land = $db->safesql($this->_convert_encoding($profile->location, true));
        $signature = $db->safesql($this->_convert_encoding($profile->signature, true));

        $db->query("UPDATE " . USERPREFIX . "_users SET info='$info', land='$land', signature='$signature', reg_date={$user->register_date}, lastdate={$user->last_activity} WHERE user_id=" . $user_id);
        
        $GLOBALS['member_id'] = $member_id = $db->super_query("SELECT * FROM " . USERPREFIX . "_users WHERE user_id=" . $user_id);

        set_cookie( "dle_user_id", $member_id['user_id'], 365 );
        set_cookie( "dle_password", $_POST['login_password'], 365 );
        $_SESSION['dle_user_id'] = $member_id['user_id'];
        $_SESSION['dle_password'] = $_POST['login_password'];
        $_SESSION['member_lasttime'] = $member_id['lastdate'];

        $GLOBALS['is_logged'] = true;
        $GLOBALS['tpl']->result['info'] = '';
        
        return $member_id;
    }
    
    public function findXenUser($username, $email, $password = null)
    {
        $email = $this->_convert_encoding($email);
        $username = $this->_convert_encoding($username);
        $password = $this->_convert_encoding($password);
        
        $sth = $this->_getDb()->prepare("SELECT * FROM " . F_PREFIX . "user_authenticate a
                               LEFT JOIN " . F_PREFIX ."user u
                               ON u.user_id=a.user_id
                               WHERE u.username=? AND u.email=?");
        
        $sth->execute(array($username, $email));
        $user = $sth->fetchObject();

        if ($user)
        {
            $authData = unserialize($user->data);
            if (!$password || $this->passwordGenerator->CheckPassword($password, $authData['hash'])) {
                return $user;
            }
        }

        return false;
    }

    #region Public function

    public function login($member_id, $force = false)
    {
        if (!$this->config['allow_module'] || !$this->config['allow_login'])
        {
            return false;
        }

        if(!$force && !(isset($_POST['login']) AND $_POST['login_name'] AND $_POST['login_password'] AND $_POST['login'] == "submit")) {
            return false;
        }

        if (empty($member_id['user_id'])) {
            $sth = $this->_getDb()->prepare("SELECT * FROM " . F_PREFIX . "user_authenticate a
                               LEFT JOIN " . F_PREFIX ."user u
                               ON u.user_id=a.user_id
                               WHERE u." . ($this->DLEConfig['auth_metod']?"email":"username") . "=?");

            $sth->execute(array($_POST['login_name']));
            $auth = $sth->fetchObject();

            if (!$auth) {
                return true;
            }
            $authData = unserialize($auth->data);
            if (!$this->passwordGenerator->CheckPassword($this->_convert_encoding($_REQUEST['login_password']), $authData['hash'])) {
                return true;
            }
            
            if (!($member_id = $this->createDLEUser($auth, $_REQUEST['login_password']))) {
                return true;
            }
        }
        else {
            $auth = $this->findXenUser($member_id['name'], $member_id['email'], $_REQUEST['login_password']);
        }
        
        if (!$auth) {
            return true;
        }
        
        $this->doLogin($auth->user_id, $auth->remember_key, $auth->last_activity);
        
        return false;
    }

    public function logout()
    {
        if (!$this->config['allow_module'] || !$this->config['allow_logout'])
        {
            return false;
        }

        $domain = $this->_getCookieDomain();
        $sessionCookieName = $this->XenConfig['cookie']['prefix'] . "session";

        setcookie($sessionCookieName,  "", time() - 31536000, $this->XenConfig['cookie']['path'], $domain);
        setcookie($this->XenConfig['cookie']['prefix'] . "user",  "", time() - 31536000, $this->XenConfig['cookie']['path'], $domain);

        return false;
    }

    public function createMember($name, $passwordMD5, $email)
    {
        if (!$this->config['allow_module'] || !$this->config['allow_reg']) {
            return false;
        }
        
        $username = $this->_convert_encoding($name);
        $email = $this->_convert_encoding($email);
        
        $stm = $this->db->prepare("SELECT * FROM " . F_PREFIX . "user WHERE username=? OR email=?");
        $stm->execute(array($username, $email));
        
        if ($stm->rowCount()) {
            return true;
        }
        
        $registrationDefaults = $this->options['registrationDefaults'];
        function mergeWithDefault($data, $registrationDefaults) {
            return array_merge($data, array_intersect_key($registrationDefaults, $data));
        }
        
        $data = array(
            'username'          => $username,
            'email'             => $email,
            'gender'            => '',
            'language_id'       => 0,
            'style_id'          => 0,
            'timezone'          => $this->options['guestTimeZone'],
            'user_group_id'     => 2,
            'display_style_group_id'     => 2,
            'permission_combination_id'  => 2,
            'register_date'     => time(),
            'last_activity'     => time(),
            'visible'           => 1,
        );
        
        $this->db->prepare("INSERT INTO " . F_PREFIX . "user (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", $this->_getDBPrepareKeys($data)) . ")")
             ->execute(mergeWithDefault($data, $registrationDefaults));
        
        $user_id = $this->db->lastInsertId();
        
        $this->db->prepare("INSERT INTO " . F_PREFIX . "user_profile (user_id, csrf_token) VALUES (?, ?)")
            ->execute(array($user_id, substr(sha1(time() . uniqid()), 0, 40)));
        
        $data = array(
            'user_id'                   => $user_id,
            'show_dob_year'             => 1,
            'show_dob_date'             => 1,
            'content_show_signature'    => 1,
            'receive_admin_email'       => 1,
            'email_on_conversation'     => 1,
            'is_discouraged'            => 0,
            'default_watch_state'       => '',
            'alert_optout'              => '',
            'enable_rte'                => 'watch_email',
            'enable_flash_uploader'     => 'watch_email',
        );
        
        $this->db->prepare("INSERT INTO " . F_PREFIX . "user_option (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", $this->_getDBPrepareKeys($data)) . ")")
            ->execute(mergeWithDefault($data, $registrationDefaults));

        $data = array(
            'user_id'                           => $user_id,
            'allow_view_profile'                => 'everyone',
            'allow_post_profile'                => 'everyone',
            'allow_send_personal_conversation'  => 'everyone',
            'allow_view_identities'             => 'everyone',
            'allow_receive_news_feed'           => 'everyone',
        );
        
        $this->db->prepare("INSERT INTO " . F_PREFIX . "user_privacy (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", $this->_getDBPrepareKeys($data)) . ")")
            ->execute(mergeWithDefault($data, $registrationDefaults));
        
        $remember_key = substr(sha1(time() . uniqid()), 0, 40);
        $this->db->prepare("INSERT INTO " . F_PREFIX . "user_authenticate (user_id, scheme_class, data, remember_key) VALUES (?, ?, ?, ?)")
            ->execute(array(
                    $user_id, 
                    empty($_POST['password1'])?
                        'XenForo_Authentication_vBulletin':
                        'XenForo_Authentication_Core12',
                    empty($_POST['password1'])?
                        serialize(array('hash' => md5($passwordMD5), 'salt' => '')):
                        serialize(array('hash' => $this->passwordGenerator->HashPassword($this->_convert_encoding($_POST['password1'])))),
                    $remember_key
                    ));

        $this->doLogin($user_id, $remember_key, time());
        
        return false;
    }

    public function updateMember($member, $land, $info)
    {
        if (!$this->config['allow_module'] || !$this->config['allow_reg']) {
            return false;
        }
        
        $user = $this->findXenUser($member['name'], $member['email']);
        if (!$user) {
            return true;
        }

        $data = array(
            'location'       => $this->_convert_encoding(strip_tags($land)),
            'about'          => $this->_convert_encoding(strip_tags($info)),
            'user_id'        => $user->user_id
        );

        $this->db->prepare("UPDATE " . F_PREFIX . "user_profile SET location = :location, about = :about WHERE user_id = :user_id LIMIT 1")
            ->execute($data);

        return false;
    }

    public function updateProfile($member, $email, $password, $land, $info)
    {
        if (!$this->config['allow_module'] || !$this->config['allow_profile']) {
            return false;
        }
        
        if (!$user = $this->findXenUser($member['name'], $member['email'])) {
            return true;
        }

        $sign = strip_tags($this->_init_parse()->process($_POST['signature']));

        $ProfileData = array(
            'location'      => $this->_convert_encoding(strip_tags($land)),
            'about'         => $this->_convert_encoding(strip_tags($info)),
            'signature'     => $this->_convert_encoding($sign)
        );
        
        $this->db->prepare("UPDATE " . F_PREFIX . "user_profile SET " . implode(", ", $this->_getDBPrepareKeysForUpdate($ProfileData)) . " WHERE user_id=:user_id")
             ->execute(array_merge($ProfileData, array('user_id' => $user->user_id)));

        if ($email != $member['email']) {
            $this->db->prepare("UPDATE " . F_PREFIX . "user SET email=? WHERE user_id=?")
                ->execute(array($this->_convert_encoding($email), $user->user_id));
        }
        
        if (strlen(trim($password)) > 0) {
            $this->db->prepare("UPDATE " . F_PREFIX . "user_authenticate SET scheme_class=?, data=? WHERE user_id=?")
                ->execute(array(
                    'XenForo_Authentication_Core12',
                    serialize(array(
                        'hash' => $this->passwordGenerator->HashPassword($this->_convert_encoding($password))
                    )),
                    $user->user_id
                ));
        }
        
        return false;
    }

    public function lostPassword($member, $new_pass)
    {
        if (!$this->config['allow_module'] || !$this->config['allow_lostpass']) {
            return false;
        }
        
        if (!$user = $this->findXenUser($member['name'], $member['email'])) {
            return true;
        }

        $this->db->prepare("UPDATE " . F_PREFIX . "user_authenticate SET scheme_class=?, data=? WHERE user_id=?")
            ->execute(array(
                'XenForo_Authentication_Core12',
                serialize(array(
                    'hash' => $this->passwordGenerator->HashPassword($this->_convert_encoding($new_pass))
                )),
                $user->user_id
            ));

        return false;
    }

    public function lastTopics(dle_template $tpl)
    {
        if (!$this->config['allow_forum_block'] || !$this->config['allow_module']) {
            return '';
        }

        if ((int)$this->config['block_cache_time']) {
            $cache = dle_cache('xen_block_cache_time');
            if ($cache) {
                $cache = unserialize($cache);
                if (!empty($cache['time']) && $cache['time'] > (time() - $this->config['block_cache_time'])) {
                    return $cache['data'];
                }
            }
        }

        $forum_id = "";
        if ($this->config['bad_forum_for_block'] && !$this->config['good_forum_for_block'])
        {
            $forum_bad = explode(",", $this->config['bad_forum_for_block']);
            $forum_id = " AND t.node_id NOT IN('". implode("','", $forum_bad) ."')";
        }
        elseif (!$this->config['bad_forum_for_block'] && $this->config['good_forum_for_block'])
        {
            $forum_good = explode(",", $this->config['good_forum_for_block']);
            $forum_id = " AND t.node_id IN('". implode("','", $forum_good) ."')";
        }

        if (!(int)$this->config['count_post']) {
            $this->config['count_post'] = 10;
        }

        $sth = $this->db->query('SELECT t.title, t.thread_id, t.last_post_date, t.reply_count, t.view_count, f.title as forum_title, t.node_id, t.last_post_username, t.last_post_user_id
                FROM ' . F_PREFIX . 'thread AS t
                LEFT JOIN ' . F_PREFIX . 'node AS f
                ON f.node_id = t.node_id
                WHERE discussion_state="visible"' . $forum_id . ' 
                ORDER BY t.last_post_date DESC 
                LIMIT 0, ' . intval($this->config['count_post']));


        $forum_url = rtrim($this->options['boardUrl'], "/") . "/";
        
        if (!$this->config['block_rewrite_url']) {
            $forum_url .= "index.php?";
        }

        $tpl->load_template('block_forum_posts.tpl');
        preg_match("'\[row\](.*?)\[/row\]'si", $tpl->copy_template, $matches);

        $block_content = '';
        while ($row = $sth->fetch(PDO::FETCH_ASSOC))
        {
            $short_name = $title = $this->_convert_encoding($row["title"], true);
            $row['last_post_username'] = $this->_convert_encoding($row['last_post_username'], true);

            if (
                !empty($this->config['length_name']) && 
                dle_strlen($title, $this->DLEConfig['charset']) > $this->config['length_name']
                )
            {
                $short_name = dle_substr($title, 0, $this->config['length_name'], $this->DLEConfig['charset']) . " ...";
            }

            switch (date("d.m.Y", $row["last_post_date"]))
            {
                case date("d.m.Y"):
                    $date = date($this->lang['today_in'] . "H:i", $row["last_post_date"]);
                    break;

                case date("d.m.Y", time() - 86400):
                    $date = date($this->lang['yesterday_in'] . "H:i", $row["last_post_date"]);
                    break;

                default:
                    $date = date("d.m.Y H:i", $row["last_post_date"]);
            }

            $replace = array(
                '{user}'            => $this->_convert_encoding($row['last_post_username'], true),
                '{user_url}'        => $forum_url . "members/" . $this->getTitleForUrl($row['last_post_username']) ."." . $row['last_post_user_id'] . "/",
                '{reply_count}'     => $row["reply_count"],
                '{view_count}'      => $row["view_count"],
                '{full_name}'       => $title,
                '{post_url}'        => $forum_url . "threads/" . $this->getTitleForUrl($row['title']) ."." . $row["thread_id"] . "/",
                '{shot_name_post}'  => $short_name,
                '{forum_name}'      => $this->_convert_encoding($row['forum_title'], true),
                '{forum_url}'       => $forum_url . "forums/" . $this->getTitleForUrl($row['forum_title']) ."." . $row["node_id"] . "/",
                '{date}'            => $date
            );

            $block_content .= strtr($matches[1], $replace);
        }
        $tpl->set_block("'\[row\](.*?)\[/row\]'si", $block_content);
        $tpl->compile('block_forum_posts');
        $tpl->clear();

        if ((int)$this->config['block_cache_time'])
        {
            create_cache('xen_block_cache_time', serialize(array('time' => time(), 'data' => $tpl->result['block_forum_posts'])));
        }
        
        return $tpl->result['block_forum_posts'];
    }
    
    protected function doLogin($user_id, $remember_key, $last_activity)
    {
        $domain = $this->_getCookieDomain();
        if (empty($_POST['login_not_save'])) {
            $value = intval($user_id) . ',' . sha1($this->XenConfig['globalSalt'] . $remember_key);
            setcookie($this->XenConfig['cookie']['prefix'] . 'user', $value, time() + 30 * 86400, $this->XenConfig['cookie']['path'], $domain, false, true);
        }

        $sessionCookieName = $this->XenConfig['cookie']['prefix'] . "session";

        if (!empty($_COOKIE[$sessionCookieName]) && strlen($_COOKIE[$sessionCookieName]) == 32) {
            $this->db->prepare("DELETE FROM " . F_PREFIX . "session WHERE session_id=?")->execute(array($_COOKIE[$sessionCookieName]));
        }

        $sessionId = md5(uniqid(time()));
        $sessionData = array(
            'sessionStart'  => time(),
            'user_id'       => $user_id,
            'ip'            => $this->convertIpStringToBinary($_SERVER['REMOTE_ADDR']),
            'previousActivity'  => $last_activity
        );

        if (!empty($_SERVER['HTTP_USER_AGENT']))
        {
            $sessionData['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
            $sessionData['robotId'] = '';
        }

        if (!empty($_SERVER['HTTP_REFERER']))
        {
            $sessionData['referer'] = $_SERVER['HTTP_REFERER'];
            $sessionData['fromSearch'] = '';
        }

        setcookie($sessionCookieName, $sessionId, false, $this->XenConfig['cookie']['path'], $domain, false, true);

        $this->db->prepare("REPLACE INTO " . F_PREFIX . "session (session_id, session_data, expiry_date) VALUES (?, ?, ?)")->execute(array(
            $sessionId,
            serialize($sessionData),
            time() + 3600
        ));
    }

    protected function getTitleForUrl($title)
    {
        $title = strval($title);

        $title = strtr(
            $title,
            '`!"$%^&*()-+={}[]<>;:@#~,./?|' . "\r\n\t\\",
            '                             ' . '    '
        );
        $title = strtr($title, array('"' => '', "'" => ''));

        $title = preg_replace('/[ ]+/', '-', trim($title));
        $title = strtr($title, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');

        return urlencode($title);
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

    protected function _getDBPrepareKeysForUpdate(array $array)
    {
        $return = array();
        foreach (array_keys($array) as $key)
        {
            $return[] = "`{$key}`=:" . $key;
        }

        return $return;
    }

    protected function _getCookieDomain()
    {
        if (!empty($this->XenConfig['cookie']['domain']))
        {
            return $this->XenConfig['cookie']['domain'];
        }
        else
        {
            return "." . $this->_clean_url($this->options['boardUrl']);
        }
    }

    protected function _clean_url($url)
    {
        if (!$url)
        {
            return false;
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
        if (strtoupper($this->DLEConfig['charset']) == strtoupper(F_CHARSET)) {
            return $text;
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
            if ($revert) {
                $text = iconv(F_CHARSET, $this->DLEConfig['charset'], $text);
            }
            else {
                $text = iconv($this->DLEConfig['charset'], F_CHARSET, $text);
            }
        }

        return $text;
    }

    protected function __get($varname)
    {
        switch ($varname)
        {
            case 'options':
                return $this->_getConfigForum();
                break;
            
            case 'passwordGenerator':
                return $this->_getPasswordGenerator();
                break;
            
            case 'db':
                return $this->_getDb();
                break;
            
            case 'dleAPI':
                return $this->getDLEAPI();
                break;

            default:
                throw new Exception('Property "' . $varname . '"not found');
                break;
        }
    }

    public function __desctruct()
    {

    }
}


?>