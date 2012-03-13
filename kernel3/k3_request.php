<?php
/**
 * QuickFox kernel 3 'SlyFox' Request module
 * Requires PHP >= 5.1.0
 * !deprecated
 * @package kernel3
 * @subpackage core
 * @deprecated
 */
class FGPC implements I_K3_Deprecated
{
    // GPC source types
    const ALL    = K3_Request::ALL;
    const GET    = K3_Request::GET;
    const POST   = K3_Request::POST;
    const COOKIE = K3_Request::COOKIE;

    const UPLOAD_OK            = K3_Request::UPLOAD_OK; // OK status
    const UPLOAD_ERR_INI_SIZE  = K3_Request::UPLOAD_ERR_INI_SIZE; // this four statuses are equal to PHP ones
    const UPLOAD_ERR_FORM_SIZE = K3_Request::UPLOAD_ERR_FORM_SIZE;
    const UPLOAD_ERR_PARTIAL   = K3_Request::UPLOAD_ERR_PARTIAL;
    const UPLOAD_ERR_NO_FILE   = K3_Request::UPLOAD_ERR_NO_FILE;
    const UPLOAD_ERR_SERVER    = K3_Request::UPLOAD_ERR_SERVER; // this means that was a error on server we'll give PHP 15 status message variants for future
    const UPLOAD_MOVED         = K3_Request::UPLOAD_MOVED; // this means that file already moved

    private function __construct() {}

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new StaticInstance('FGPC');
        return self::$self;
    }

    public static function init()
    {
        // Clear all the registered globals if they not cleared on kernel load
        if (ini_get('register_globals') && !defined('F_GLOBALS_CLEARED'))
        {
            $drop_globs = $_REQUEST + $_FILES;
            foreach ($drop_globs as $rvar => $rval)
               if ($GLOBALS[$rvar] === $rval)
                   unset ($GLOBALS[$rvar]);
            define('F_GLOBALS_CLEARED', true);
        }
    }

    // useful for special inpur parsings
    public function setRaws($datas, $set = self::GET)
    {
        if (!is_array($datas))
            return false;

        return F()->Request->setRaws($datas, $set);
    }

    public static function getURLParams()
    {
        return F()->Request->getURLParams();
    }

    public static function get($var_name, $from = self::GET )
    {
        return F()->Request->get($var_name, $from);
    }

    public static function getBin($var_name, $from = self::GET, $get_flags = true)
    {
        return F()->Request->getBinary($var_name, $from, $get_flags);
    }

    public static function getNum($var_name, $from = self::GET, $get_float = false )
    {
        return F()->Request->getNumber($var_name, $from, $get_float);
    }

    public static function getString($var_name, $from = self::GET, $strtype = false )
    {
        return F()->Request->getString($var_name, $from, $strtype);
    }

    public static function getFile($var_name)
    {
        return F()->Request->getFile($var_name);
    }

    public static function moveFile($var_name, $to_file, $force_replace = false)
    {
        return F()->Request->moveFile($var_name, $to_file, $force_replace);
    }
}
FGPC::init();

