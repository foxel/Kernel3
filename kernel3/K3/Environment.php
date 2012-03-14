<?php

/**
 * @property K3_Request  $request
 * @property K3_Response $response
 * @property K3_Session  $session
 * 
 * @property string $clientIP
 * @property int    $clientIPInteger
 * @property string $rootUrl
 * @property string $rootPath
 * @property string $rootRealPath
 * @property string $serverName
 * @property int    $serverPort

 * @property string $cookieDomain
 * @property string $cookiePrefix
 */
abstract class K3_Environment extends FEventDispatcher
{
    const DEFAULT_COOKIE_PREFIX = 'K3';

    /**
     * @var K3_Request
     */
    protected $_request = null;

    /**
     * @var K3_Response
     */
    protected $_response = null;

    /**
     * @var K3_Session
     */
    protected $_session = null;

    /**
     * @var array
     */
    protected $_cookies = array();

    /**
     * @var array
     */
    protected $_elements = array();

    public function __construct()
    {
        $this->pool = array(
            'clientIP'          => '',
            'clientIPInteger'   => 0,
            'rootUrl'           => '',
            'rootPath'          => '',
            'rootRealPath'      => '',
            'serverName'        => '',
            'serverPort'        => 80,

            'cookieDomain'      => false,
            'cookiePrefix'      => self::DEFAULT_COOKIE_PREFIX,
        );
    }

    /**
     * @param  integer $securityLevel
     * @return string
     */
    public function getClientSignature($securityLevel = 0)
    {
        return md5(implode('|', array_slice(explode('.', $this->clientIP), 0, $securityLevel)));
    }

    /**
     * getter
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getterMethod = 'get'.ucfirst($name);
        if (method_exists($this, $getterMethod)) {
            return $this->$getterMethod();
        } else {
            return parent::__get($name);
        }
    }

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

    public function getCookie($name, $addPrefix = true)
    {
        if ($addPrefix) {
            $name = $this->pool['cookiePrefix'].'_'.$name;
        }

        return (isset($this->_cookies[$name])) ? $this->_cookies[$name] : null;
    }

    // sets cookies domain (checks if current client request is sent on that domain or it's sub)
    public function setCookieDomain($domain)
    {
        if (!preg_match('#[\w\.]+\w\.\w{2,4}#', $domain)) {
            trigger_error('Tried to set incorrect cookies domain.', E_USER_WARNING);
        } else {
            $my_domain = '.'.ltrim(strtolower($this->serverName), '.');
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

    abstract public function setCookie($name, $value = false, $expire = false, 
        $root = false, $addPrefix = true, $setDomain = true);

    /**
     * @param K3_Request $request
     */
    public function setRequest(K3_Request $request = null)
    {
        $this->_request = $request;
        $this->_request->setEnvironment($this);
    }

    /**
     * @return K3_Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @param K3_Response $response
     */
    public function setResponse(K3_Response $response = null)
    {
        $this->_response = $response;
        $this->_response->setEnvironment($this);
    }

    /**
     * @return K3_Response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param K3_Session $session
     */
    public function setSession(K3_Session $session = null)
    {
        $this->_session = $session;
        $this->_session->setEnvironment($this);
    }

    /**
     * @return K3_Session
     */
    public function getSession()
    {
        return $this->_session;
    }

    /**
     * @param  string $name
     * @param  mixed $element
     * @return K3_Environment
     */
    public function put($name, $element)
    {
        $this->_elements[$name] = $element;

        return $this;
    }

    /**
     * @param  string $name
     * @return mixed
     */
    public function get($name)
    {
        return isset($this->_elements[$name])
            ? $this->_elements[$name]
            : null;
    }
}
