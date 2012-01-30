<?php

abstract class K3_Environment extends FEventDispatcher
{
    const DEF_COOKIE_PREFIX = 'K3';

    /**
     * var K3_Request
     */
    protected $request = null;

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
            'cookiePrefix'      => self::DEF_COOKIE_PREFIX,
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
        if (is_callable(array(&$this, $setterMethod))) {
            return $this->$getterMethod();
        } else {
            return parent::__get($name);
        }
    }

    /**
     * setter
     * @param  string $name
     * @param  mixed $val
     */
    public function __set($name, $val)
    {
        $setterMethod = 'set'.ucfirst($name);
        if (is_callable(array(&$this, $setterMethod))) {
            $this->$setterMethod($val);
        } else {
            parent::__set($name, $val);
        }
    }

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
}
