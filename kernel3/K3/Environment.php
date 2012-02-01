<?php

abstract class K3_Environment extends FEventDispatcher
{
    const DEFAULT_COOKIE_PREFIX = 'K3';

    /**
     * var K3_Request
     */
    protected $request = null;

    /**
     * var K3_Response
     */
    protected $response = null;

    /**
     * var array
     */
    protected $cookies = array();

    public function __construct()
    {
        $this->pool = array(
            'clientIP'          => '',
            'clientIPInteger'   => 0,
            'rootUrl'           => '',
            'requestUrl'        => '',
            'rootPath'          => '',
            'rootRealPath'      => '',
            'serverName'        => '',
            'serverPort'        => 80,
            'referer'           => '',
            'refererIsExternal' => false,

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
            foreach ($this->cookies as $name => $value)
            {
                if (strpos($name, $oldPrefix_) === 0)
                {
                    $this->setCookie($name, false, false, false, false, true);
                    $name = $newPrefix.'_'.substr($name, strlen($oldPrefix_));
                    $this->setCookie($name, $value, false, false, false, true);
                }
                $this->cookies[$name] = $value;
            }
        }
        $this->pool['cookiePrefix'] = (string) $newPrefix;

        return $this;
    }

    public function getCookie($name)
    {
        $name = $this->pool['cookiePrefix'].'_'.$name;

        return (isset($this->cookies[$name])) ? $this->cookies[$name] : null;
    }

    abstract public function setCookie($name, $value = false, $expire = false, 
        $root = false, $addPrefix = true, $setDomain = true);

    /**
     * @param K3_Request $request
     */
    public function setRequest(K3_Request $request = null)
    {
        $this->request = $request;
        $this->request->setEnvironment($this);
    }

    /**
     * @return K3_Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param K3_Response $response
     */
    public function setResponse(K3_Response $response = null)
    {
        $this->response = $response;
        $this->response->setEnvironment($this);
    }

    /**
     * @return K3_Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}
