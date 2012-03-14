<?php

class K3_Environment_HTTP extends K3_Environment
{

    public function __construct()
    {
        parent::__construct();
        $this->_cookies =& $_COOKIE; // TODO: think if we need to get a copy instead

        $this->pool['clientIP']        = $_SERVER['REMOTE_ADDR'];
        $this->pool['clientIPInteger'] = ip2long($this->pool['clientIP']);

        $this->pool['serverName'] = isset($_SERVER['HTTP_HOST']) ? array_shift(explode(':', $_SERVER['HTTP_HOST'])) : $_SERVER['SERVER_NAME'];
        $this->pool['serverPort'] = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;

        $this->pool['rootUrl']    = 'http://'.$this->pool['serverName'].(($this->pool['serverPort'] != 80) ? $this->pool['serverPort'] : '').'/';
        $this->pool['rootPath']   = dirname($_SERVER['SCRIPT_NAME']);

        if ( $this->pool['rootPath'] = trim($this->pool['rootPath'], '/\\') )
        {
            $this->pool['rootPath']   = preg_replace('#\/|\\\\+#', '/', $this->pool['rootPath']);
            $this->pool['rootUrl']   .= $this->pool['rootPath'].'/';
        }

        $this->pool['rootRealPath'] = preg_replace(Array('#\/|\\\\+#', '#(\/|\\\\)*$#'), Array(DIRECTORY_SEPARATOR, ''), $_SERVER['DOCUMENT_ROOT']).'/'.$this->pool['rootPath'];
    }

    /**
     * returns client signature based on browser, ip and proxy
     * @param  integer $securityLevel
     * @return string
     */
    public function getClientSignature($securityLevel = 0)
    {
        static $signParts = Array('HTTP_USER_AGENT'); //, 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING'
        static $psignParts = Array('HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

        $sign = Array();
        foreach ($signParts as $key)
            if (isset($_SERVER[$key]))
                $sign[] = $_SERVER[$key];

        if ($securityLevel > 0) {
            $ip = explode('.', $this->pool['clientIP']);
            $sign[] = $ip[0];
            $sign[] = $ip[1];
            if ($securityLevel > 1)
            {
                $sign[] = $ip[2];
                foreach ($psignParts as $key)
                    if (isset($_SERVER[$key]))
                        $sign[] = $_SERVER[$key];
            }
            if ($securityLevel > 2)
                $sign[] = $ip[3];
        }

        $sign = implode('|', $sign);
        $sign = md5($sign);
        return $sign;
    }

    /**
     * @param $name
     * @param string|bool $value
     * @param int|bool $expire
     * @param string|bool $rootPath
     * @param bool $addPrefix
     * @param bool $setDomain
     * @return bool
     */
    public function setCookie($name, $value = false, $expire = false,
        $rootPath = false, $addPrefix = true, $setDomain = true)
    {
        if (!$rootPath) {
            $rootPath = ($this->pool['rootPath']) ? '/'.$this->pool['rootPath'].'/' : '/';
        }

        if ($addPrefix) {
            $name = $this->pool['cookiePrefix'].'_'.$name;
        }

        if ($value === false && !isset($this->_cookies[$name])) {
            return true;
        }

        $result = setcookie(
            $name,
            $value,
            $expire,
            $rootPath,
            ($setDomain) ? $this->pool['cookieDomain'] : false, // domain
            ($this->_request instanceof I_K3_Request) && $this->_request->isSecure // secure only
        );

        if ($result) {
            $this->_cookies[$name] = $value;
        }

        return $result;
    }

}
