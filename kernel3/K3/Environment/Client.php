<?php

/**
 * @property string $IP
 * @property int    $IPInteger
 * @property string $cookieDomain
 * @property string $cookiePrefix
 */
abstract class K3_Environment_Client extends K3_Environment_Element
{
    const DEFAULT_COOKIE_PREFIX = 'K3';

    /**
     * @static
     * @param string $class
     * @param K3_Environment|null $env
     * @return K3_Environment_Client
     * @throws FException
     */
    public static function construct($class, K3_Environment $env = null)
    {
        if (empty($class)) {
            throw new FException('K3_Environment_Client construct without class specified');
        }

        $className = __CLASS__.'_'.ucfirst($class);

        return new $className($env);
    }

    /**
     * @var array
     */
    protected $_cookies = array();

    public function __construct(K3_Environment $env = null)
    {
        $this->pool = array(
            'IP'           => '',
            'IPInteger'    => 0,

            'cookieDomain' => false,
            'cookiePrefix' => self::DEFAULT_COOKIE_PREFIX,
        );

        parent::__construct($env);
    }

    /**
     * @param  integer $securityLevel
     * @return string
     */
    public function getSignature($securityLevel = 0)
    {
        return md5(implode('|', array_slice(explode('.', $this->IP), 0, $securityLevel)));
    }

    /**
     * @param bool $newPrefix
     * @param bool $renameOldCookies
     * @return K3_Environment
     */
    public function setCookiePrefix($newPrefix = false, $renameOldCookies = false)
    {
        if (!$newPrefix || !is_string($newPrefix))
            $newPrefix = self::DEFAULT_COOKIE_PREFIX;

        // special for chenging prefix without dropping down the session
        if ($renameOldCookies && $this->pool['cookiePrefix'] != $newPrefix)
        {
            $oldPrefix_ = $this->pool['cookiePrefix'].'_';
            foreach ($this->_cookies as $name => $value)
            {
                if (strpos($name, $oldPrefix_) === 0)
                {
                    $this->setCookie($name, false, false, false, false, true);
                    $name = $newPrefix.'_'.substr($name, strlen($oldPrefix_));
                    $this->setCookie($name, $value, false, false, false, true);
                }
                $this->_cookies[$name] = $value;
            }
        }
        $this->pool['cookiePrefix'] = (string) $newPrefix;

        return $this;
    }

    /**
     * @param string $name
     * @param bool $addPrefix
     * @return null|string
     */
    public function getCookie($name, $addPrefix = true)
    {
        if ($addPrefix) {
            $name = $this->pool['cookiePrefix'].'_'.$name;
        }

        return (isset($this->_cookies[$name])) ? $this->_cookies[$name] : null;
    }

    /**
     * sets cookies domain (checks if current client request is sent on that domain or it's sub)
     * @param string $domain
     * @return K3_Environment_Client
     */
    public function setCookieDomain($domain)
    {
        if (!preg_match('#[\w\.]+\w\.\w{2,4}#', $domain)) {
            trigger_error('Tried to set incorrect cookies domain.', E_USER_WARNING);
        } else {
            $my_domain = '.'.ltrim(strtolower($this->env->server->domain), '.');
            $domain    = '.'.ltrim(strtolower($domain), '.');
            $len = strlen($domain);
            if (substr($my_domain, -$len) == $domain) {
                $this->pool['cookieDomain'] = $domain;
            } else {
                trigger_error('Tried to set incorrect cookies domain.', E_USER_WARNING);
            }
        }

        return $this;
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
    abstract public function setCookie($name, $value = false, $expire = false, $rootPath = false, $addPrefix = true, $setDomain = true);

}