<?php
/*
 * QuickFox kernel 3 'SlyFox' Request module
 * Requires PHP >= 5.1.0
 */

// default Cookie Prefix
define('F_DEF_COOKIE_PREFIX', 'QF2');

// GPC resource types
define('F_GPC_ALL', 0);
define('F_GPC_GET', 1);
define('F_GPC_POST', 2);
define('F_GPC_COOKIE', 3);

// GPC String parse subtypes
define('F_STR_HEX',  1);
define('F_STR_HTML', 2);
define('F_STR_WORD', 3);
define('F_STR_LINE', 4);

// GPC files err-codes
define('F_UPLOAD_OK', 0); // OK status
define('F_UPLOAD_ERR_INI_SIZE', 1); // this four statuses are equal to PHP ones
define('F_UPLOAD_ERR_FORM_SIZE', 2);
define('F_UPLOAD_ERR_PARTIAL', 3);
define('F_UPLOAD_ERR_NO_FILE', 4);
define('F_UPLOAD_ERR_SERVER', 0x10); // this means that was a error on server we'll give PHP 15 status message variants for future
define('F_UPLOAD_MOVED', 0x20); // this means that file already moved

class FGPC extends FEventDispatcher
{
    const ALL = 0;
    const GET = 1;
    const POST = 2;
    const COOKIE = 3;

    private $raw = array();
    private $str_recode_func = null;
    private $UPLOADS = null;
    private $CPrefix = F_DEF_COOKIE_PREFIX;

    function __construct()
    {
        // here was if (PHP_VERSION<'4.1.0') block
        $this->CPrefix = QF_DEF_COOKIE_PREFIX;

        // Clear all the registered globals if they not cleared on kernel load
        if (ini_get('register_globals') && !defined('QF_GLOBALS_CLEARED'))
        {
            $drop_globs = $_REQUEST + $_FILES;
            foreach ($drop_globs as $rvar => $rval)
               if ($GLOBALS[$rvar] === $rval)
                   unset ($GLOBALS[$rvar]);
            define('QF_GLOBALS_CLEARED', true);
        }
    }

    function _Start()
    {
        global $QF;
        $this->CPrefix = $QF->Config->Get('cookie_prefix', 'common', F_DEF_COOKIE_PREFIX);
        $QF->Config->Add_Listener('cookie_prefix', 'common', Array(&$this, 'Rename_Cookies'));
    }

    // special function for chenging prefix without dropping down the session
    function Rename_Cookies($new_prefix)
    {
        global $QF;
        if (!$new_prefix)
            $new_prefix = QF_DEF_COOKIE_PREFIX;

        $new_cookies = Array();
        $o_prefix = $this->CPrefix.'_';
        foreach ($_COOKIE as $val => $var)
        {
            if (strpos($val, $o_prefix) === 0)
            {
                $QF->HTTP->Set_Cookie($val, false, false, false, false, true);
                $val = $new_prefix.'_'.substr($val, strlen($o_prefix));
                $QF->HTTP->Set_Cookie($val, $var, false, false, false, true);
            }
            $new_cookies[$val] = $var;
        }
        $this->CPrefix = $new_prefix;
        $_COOKIE = $new_cookies;
    }

    // useful for special inpur parsings
    function Set_Raws($datas, $set = QF_GPC_GET)
    {
        if (!is_array($datas))
            return false;

        $raw = &$this->raw;
        foreach ($datas as $key => $data)
            $raw[$set][$key] = $data;

        return true;
    }

    function Get_Raw($var_name, $from = QF_GPC_GET )
    {

        $raw = &$this->raw;

        if (!isset($raw[$from][$var_name]))
        {
            $svar_name = $var_name;
            switch ($from) {
                case QF_GPC_GET:
                    $source =& $_GET;
                    break;
                case QF_GPC_POST:
                    $source =& $_POST;
                    break;
                case QF_GPC_COOKIE:
                    $svar_name = $this->CPrefix.'_'.$var_name;
                    $source =& $_COOKIE;
                    break;
                default:
                    $source =& $_REQUEST;
            }

            if (isset($source[$svar_name]))
                $val = $source[$svar_name];
            else
                $val = null;

            if (QF_GPC_STRIP)
                $val = qf_value_unslash($val);

            $raw[$from][$var_name] = $val;
        }
        else
            $val = $raw[$from][$var_name];

        return $val;
    }

    function Get_Bin($var_name, $from = QF_GPC_GET)
    {
        $val = $this->Get_Raw($var_name, $from);
        if ($val === null)
            return null;
        return ($val) ? true : false;
    }

    function Get_Num($var_name, $from = QF_GPC_GET, $get_float = false )
    {
        $val = $this->Get_Raw($var_name, $from);
        if ($val === null)
            return null;
        return ($get_float) ? floatval($val) : intval($val);
    }

    function Get_String($var_name, $from = QF_GPC_GET, $subtype = false )
    {
        $val = $this->Get_Raw($var_name, $from);
        if ($val === null)
            return null;
        $val = trim(strval($val));

        if (is_callable($this->str_recode_func))
            $val = call_user_func($this->str_recode_func, $val);

        switch ($subtype)
        {
            case QF_STR_HEX:
                $val = strtolower(preg_replace('#[^0-9a-fA-F]#', '', $val));
                break;

            case QF_STR_HTML:
                $val = htmlspecialchars($val); //, ENT_NOQUOTES
                break;

            case QF_STR_WORD:
                $val = preg_replace('#[^0-9a-zA-Z_\-]#', '', $val);
                break;

            case QF_STR_LINE:
                $val = preg_replace('#[\r\n]#', '', $val);
                break;
        }

        return $val;
    }

    function Get_File($var_name)
    {
        if (is_null($this->UPLOADS))
            $this->_Recheck_Files();

        if (isset($this->UPLOADS[$var_name]))
            return $this->UPLOADS[$var_name];
        else
            return null;
    }

    function Move_File($var_name, $to_file, $force_replace = false)
    {
        if (is_null($this->UPLOADS))
            $this->_Recheck_Files();

        if (!isset($this->UPLOADS[$var_name]))
            return false;

        $file =&$this->UPLOADS[$var_name];
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
                    $file['error'] = QF_UPLOAD_MOVED;
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
    function _Recheck_Files()
    {
        static $empty_file = Array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 0, 'size' => 0);

        $fgroups = Array();
        $this->UPLOADS = $_FILES;
        // reparsing arrays
        do
        {
            $need_loop = false;
            $files = Array();
            foreach($this->UPLOADS as $varname=>$fileinfo)
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
            $this->UPLOADS = $files;
        } while ($need_loop);

        // checking files
        foreach($this->UPLOADS as $varname=>$upload)
        {
            $upload = $this->UPLOADS[$varname] + $empty_file;
            if (QF_GPC_STRIP)
                $upload = qf_value_unslash($upload);

            $tmp_file = $upload['tmp_name'];
            if ($upload['name'])
            {
                if (is_callable($this->str_recode_func))
                    $upload['name'] = call_user_func($this->str_recode_func, $upload['name']);

                $upload['name'] = qf_basename($upload['name']);
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
                $upload['error'] = QF_UPLOAD_ERR_SERVER;
            }
            elseif (($fsize = filesize($tmp_file)) != $upload['size'])
            {
                trigger_error('GPC: uploaded file is not totally uploaded: filename="'.$upload['name'].'"; tmp="'.$tmp_file.'"; size='.$upload['size'].'; realsize='.$fsize, E_USER_WARNING);
                $upload['error'] = QF_UPLOAD_ERR_PARTIAL;
            }
            $this->UPLOADS[$varname] = $upload;
        }


        $this->UPLOADS+= $fgroups;
    }

}

?>