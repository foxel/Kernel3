<?php

abstract class K3_Request extends K3_Environment_Element implements I_K3_Request
{
    // GPC source types
    const ALL    = 0;
    const GET    = 1;
    const POST   = 2;
    const COOKIE = 3;

    const UPLOAD_OK            = 0; // OK status
    const UPLOAD_ERR_INI_SIZE  = 1; // this four statuses are equal to PHP ones
    const UPLOAD_ERR_FORM_SIZE = 2;
    const UPLOAD_ERR_PARTIAL   = 3;
    const UPLOAD_ERR_NO_FILE   = 4;
    const UPLOAD_ERR_SERVER    = 0x10; // this means that was a error on server we'll give PHP 15 status message variants for future
    const UPLOAD_MOVED         = 0x20; // this means that file already moved

    /**
     * @var callback $stringRecodeFunc
     */
    protected $stringRecodeFunc = null;

    public function __construct(K3_Environment $env = null)
    {
        $this->pool = array(
            'isSecure' => false,
            'isPost'   => false,
        );

        parent::__construct($env);
    }

    /**
     * must be implemented in extending class
     * @param  string $varName
     * @param  integer $source
     * @return mixed
     */
    public function get($varName, $source = self::ALL, $defailt = null) {}

    /**
     * @param  string $varName
     * @param  integer $source
     * @param  boolean $getFlags
     * @return mixed
     */
    public function getBinary($varName, $source = self::ALL, $getFlags = true)
    {
        $val = $this->get($varName, $source);
        if ($val === null)
            return null;
        if ($getFlags && is_string($val) && !strlen($val))
            $val = true;
        return ($val) ? true : false;
    }

    /**
     * @param  string $varName
     * @param  integer $source
     * @param  boolean $getFloat
     * @return mixed
     */
    public function getNumber($varName, $source = self::ALL, $getFloat = false )
    {
        $val = $this->get($varName, $source);
        if ($val === null)
            return null;
        return ($getFloat) ? floatval($val) : intval($val);
    }

    /**
     * @param  string $varName
     * @param  integer $source
     * @param  integer $stringCastType
     * @return mixed
     */
    public function getString($varName, $source = self::ALL, $stringCastType = null )
    {
        $val = $this->get($varName, $source);
        if ($val === null)
            return null;
        $val = trim(strval($val));

        if (is_callable($this->stringRecodeFunc))
            $val = call_user_func($this->stringRecodeFunc, $val);

        if ($stringCastType)
            $var = FStr::cast($val, $stringCastType);

        return $val;
    }

    /**
     * @return array
     */
    public function getURLParams()
    {
        $res = Array();
        parse_str(parse_url($this->env->requestUrl, PHP_URL_QUERY), $res);
        return $res;
    }

    /**
     * @param  string $varName
     * @return mixed
     */
    abstract public function getFile($varName);

    /**
     * @param  string $varName
     * @param  string $toFile
     * @param  boolean $forceReplace
     * @return mixed
     */
    abstract public function moveFile($varName, $toFile, $forceReplace = false);
}
