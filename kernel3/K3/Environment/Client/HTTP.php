<?php

class K3_Environment_Client_HTTP extends K3_Environment_Client
{
    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        $this->_cookies =& $_COOKIE; // TODO: think if we need to get a copy instead

        $this->pool['IP']        = $_SERVER['REMOTE_ADDR'];
        $this->pool['IPInteger'] = ip2long($this->pool['IP']);
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
    public function setCookie($name, $value = false, $expire = false, $rootPath = false, $addPrefix = true, $setDomain = true)
    {
        if (!$rootPath) {
            $rootPath = ($this->env->server->rootPath) ? '/'.$this->env->server->rootPath.'/' : '/';
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
            ($this->env->getRequest() instanceof I_K3_Request) && $this->env->getRequest()->isSecure // secure only
        );

        if ($result) {
            $this->_cookies[$name] = $value;
        }

        return $result;
    }

    /**
     * @param int $securityLevel
     * @return string
     */
    public function getSignature($securityLevel = 0)
    {
        static $signParts = Array('HTTP_USER_AGENT'); //, 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING'
        static $psignParts = Array('HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

        $sign = Array();
        foreach ($signParts as $key)
            if (isset($_SERVER[$key]))
                $sign[] = $_SERVER[$key];

        if ($securityLevel > 0) {
            $ip = explode('.', $this->pool['IP']);
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
}