<?php
/**
 * QuickFox kernel 3 'SlyFox' Request module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

class FGPC
{
    // GPC source types
    const ALL = 0;
    const GET = 1;
    const POST = 2;
    const COOKIE = 3;

    const UPLOAD_OK = 0; // OK status
    const UPLOAD_ERR_INI_SIZE = 1; // this four statuses are equal to PHP ones
    const UPLOAD_ERR_FORM_SIZE = 2;
    const UPLOAD_ERR_PARTIAL   = 3;
    const UPLOAD_ERR_NO_FILE   = 4;
    const UPLOAD_ERR_SERVER = 0x10; // this means that was a error on server we'll give PHP 15 status message variants for future
    const UPLOAD_MOVED      = 0x20; // this means that file already moved

    const DEF_COOKIE_PREFIX = 'QF2';

    private static $raw = array();
    private static $str_recode_func = null;
    private static $UPLOADS = null;
    private static $CPrefix = self::DEF_COOKIE_PREFIX;
    private static $doGPCStrip = false;

    private function __construct() {}

    public static function init()
    {
        self::$CPrefix = self::DEF_COOKIE_PREFIX;
        self::$doGPCStrip = (bool) get_magic_quotes_gpc();

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

    /* public static function _Start()
    {
        self::$CPrefix = $QF->Config->get('cookie_prefix', 'common', F_DEF_COOKIE_PREFIX);
        $QF->Config->Add_Listener('cookie_prefix', 'common', Array(&$this, 'renameCookies'));
    } */

    // useful for special inpur parsings
    public static function setRaws($datas, $set = self::GET)
    {
        if (!is_array($datas))
            return false;

        $raw = &self::$raw;
        foreach ($datas as $key => $data)
            $raw[$set][$key] = $data;

        return true;
    }

    public static function getURLParams()
    {
        $res = Array();
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $res);
        return $res;
    }

    public static function get($var_name, $from = self::GET )
    {

        $raw = &self::$raw;

        if (!isset($raw[$from][$var_name]))
        {
            $svar_name = $var_name;
            switch ($from) 
            {
                case self::GET:
                    $source =& $_GET;
                    break;
                case self::POST:
                    $source =& $_POST;
                    break;
                case self::COOKIE:
                    // do nothing, see "if" structure below
                    break;
                default:
                    $source =& $_REQUEST;
            }

            if ($from == self::COOKIE)
                $val = F()->HTTP->getCookie($svar_name);
            elseif (isset($source[$svar_name]))
                $val = $source[$svar_name];
            else
                $val = null;

            if (self::$doGPCStrip)
                $val = FStr::unslash($val);

            $raw[$from][$var_name] = $val;
        }
        else
            $val = $raw[$from][$var_name];

        return $val;
    }

    public static function getBin($var_name, $from = self::GET, $get_flags = true)
    {
        $val = self::get($var_name, $from);
        if ($val === null)
            return null;
        if ($get_flags && is_string($val) && !strlen($val))
            $val = true;
        return ($val) ? true : false;
    }

    public static function getNum($var_name, $from = self::GET, $get_float = false )
    {
        $val = self::get($var_name, $from);
        if ($val === null)
            return null;
        return ($get_float) ? floatval($val) : intval($val);
    }

    public static function getString($var_name, $from = self::GET, $strtype = false )
    {
        $val = self::get($var_name, $from);
        if ($val === null)
            return null;
        $val = trim(strval($val));

        if (is_callable(self::$str_recode_func))
            $val = call_user_func(self::$str_recode_func, $val);

        if ($strtype)
            $var = FStr::cast($val, $strtype);

        return $val;
    }

    public static function getFile($var_name)
    {
        if (is_null(self::$UPLOADS))
            self::recheckFiles();

        if (isset(self::$UPLOADS[$var_name]))
            return self::$UPLOADS[$var_name];
        else
            return null;
    }

    public static function moveFile($var_name, $to_file, $force_replace = false)
    {
        if (is_null(self::$UPLOADS))
            self::recheckFiles();

        if (!isset(self::$UPLOADS[$var_name]))
            return false;

        $file =&self::$UPLOADS[$var_name];
        if (isset($file['is_group']))
            return false;
        elseif ($file['error'])
            return false;

        $old_file = $file['tmp_name'];
        if (file_exists($old_file) && is_uploaded_file($old_file))
        {
            if (!file_exists($to_file) || $force_replace)
            {
                if (move_uploaded_file($old_file, $to_file))
                {
                    $file['error'] = self::UPLOAD_MOVED;
                    return true;
                }
                else
                    return false;
            }
            else
                return false;
        }
    }

    // inner funtions
    private static function recheckFiles()
    {
        static $empty_file = Array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 0, 'size' => 0);

        $fgroups = Array();
        self::$UPLOADS = $_FILES;
        // reparsing arrays
        do
        {
            $need_loop = false;
            $files = Array();
            foreach(self::$UPLOADS as $varname=>$fileinfo)
            {
                $tmp_file = $fileinfo['tmp_name'];
                if (is_array($tmp_file))
                {
                    $fgroup = Array('is_group' => true);
                    $need_loop = true;
                    foreach($tmp_file as $id=>$data)
                    {
                        $sub_var = $varname.'['.$id.']';
                        $fgroup[] = $sub_var;
                        $sub_info = Array();
                        foreach ($fileinfo as $var=>$val)
                            $sub_info[$var] = $val[$id];
                        $files[$sub_var] = $sub_info;
                    }
                    $fgroups[$varname] = $fgroup;
                }
                else
                    $files[$varname] = $fileinfo;
            }
            self::$UPLOADS = $files;
        } while ($need_loop);

        // checking files
        foreach(self::$UPLOADS as $varname=>$upload)
        {
            $upload = self::$UPLOADS[$varname] + $empty_file;
            if (self::$doGPCStrip)
                $upload = FStr::unslash($upload);

            $tmp_file = $upload['tmp_name'];
            if ($upload['name'])
            {
                if (is_callable(self::$str_recode_func))
                    $upload['name'] = call_user_func(self::$str_recode_func, $upload['name']);

                $upload['name'] = FStr::basename($upload['name']);
            }

            if (!$upload['name']) //there is no uploaded file
            {
                $upload = null;
            }
            elseif ($upload['error'])
            {
                trigger_error('GPC: error uploading file to server: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'].'; srv_err='.$upload['error'], E_USER_WARNING);
            }
            elseif (!file_exists($tmp_file) || !is_uploaded_file($tmp_file))
            {
                trigger_error('GPC: uploaded file not found: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'], E_USER_WARNING);
                $upload['error'] = self::UPLOAD_ERR_SERVER;
            }
            elseif (($fsize = filesize($tmp_file)) != $upload['size'])
            {
                trigger_error('GPC: uploaded file is not totally uploaded: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'].'; realsize='.$fsize, E_USER_WARNING);
                $upload['error'] = self::UPLOAD_ERR_PARTIAL;
            }
            self::$UPLOADS[$varname] = $upload;
        }


        self::$UPLOADS+= $fgroups;
    }

}
FGPC::init();

?>
