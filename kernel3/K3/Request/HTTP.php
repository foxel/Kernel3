<?php

class K3_Request_HTTP extends K3_Request
{
    protected $raw = array();
    protected $UPLOADS = null;
    protected $doGPCStrip = false;

    public function __construct()
    {
        parent::__construct();
        $this->doGPCStrip = (bool) get_magic_quotes_gpc();
    }

    // useful for special inpur parsings
    public static function setRaws(array $datas, $set = self::GET)
    {
        $raw =& self::$raw;
        foreach ($datas as $key => $data)
            $raw[$set][$key] = $data;

        return true;
    }

    public function getURLParams()
    {
        $res = Array();
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $res);
        return $res;
    }

    public function get($varName, $source = self::ALL, $default = null)
    {

        $raw = $this->raw;

        if (isset($raw[$source][$varName])) {
            return $raw[$source][$varName];
        }

        // cookie requests are redirected
        if ($source == self::COOKIE) {
            $val = F()->HTTP->getCookie($svarName);
            return !is_null($val)
                ? $val
                : $default;
        }

        // determining data source
        $svarName = $varName;
        switch ($source) 
        {
            case self::GET:
                $dataSource =& $_GET;
                break;
            case self::POST:
                $dataSource =& $_POST;
                break;
            default:
                $dataSource =& $_REQUEST;
        }

        // if the item is not set return default (NULL)
        if (!isset($dataSource[$svarName])) {
            return $default;
        }

        $val = $dataSource[$svarName];

        if ($this->doGPCStrip) {
            $val = FStr::unslash($val);
        }

        // setting for future use
        $raw[$source][$varName] = $val;

        return $val;
    }

    public function getFile($varName)
    {
        if (is_null($this->UPLOADS))
            $this->recheckFiles();

        if (isset($this->UPLOADS[$varName]))
            return $this->UPLOADS[$varName];
        else
            return null;
    }

    public function moveFile($varName, $toFile, $forceReplace = false)
    {
        if (is_null($this->UPLOADS))
            $this->recheckFiles();

        if (!isset($this->UPLOADS[$varName]))
            return false;

        $file =&$this->UPLOADS[$varName];
        if (isset($file['is_group']))
            return false;
        elseif ($file['error'])
            return false;

        $oldFile = $file['tmp_name'];
        if (file_exists($oldFile) && is_uploaded_file($oldFile))
        {
            if (!file_exists($toFile) || $forceReplace)
            {
                if (move_uploaded_file($oldFile, $toFile))
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
    private function recheckFiles()
    {
        static $emptyFile = Array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 0, 'size' => 0);

        $fgroups = Array();
        $this->UPLOADS = $_FILES;
        // reparsing arrays
        do
        {
            $needLoop = false;
            $files = Array();
            foreach($this->UPLOADS as $varname=>$fileinfo)
            {
                $tmpFile = $fileinfo['tmp_name'];
                if (is_array($tmpFile))
                {
                    $fgroup = Array('is_group' => true);
                    $needLoop = true;
                    foreach($tmpFile as $id=>$data)
                    {
                        $subVar = $varname.'['.$id.']';
                        $fgroup[] = $subVar;
                        $subInfo = Array();
                        foreach ($fileinfo as $var=>$val)
                            $subInfo[$var] = $val[$id];
                        $files[$subVar] = $subInfo;
                    }
                    $fgroups[$varname] = $fgroup;
                }
                else
                    $files[$varname] = $fileinfo;
            }
            $this->UPLOADS = $files;
        } while ($needLoop);

        // checking files
        foreach($this->UPLOADS as $varname=>$upload)
        {
            $upload = $this->UPLOADS[$varname] + $emptyFile;
            if ($this->doGPCStrip)
                $upload = FStr::unslash($upload);

            $tmpFile = $upload['tmp_name'];
            if ($upload['name'])
            {
                if (is_callable($this->str_recode_func))
                    $upload['name'] = call_user_func($this->str_recode_func, $upload['name']);

                $upload['name'] = FStr::basename($upload['name']);
            }

            if (!$upload['name']) //there is no uploaded file
            {
                $upload = null;
            }
            elseif ($upload['error'])
            {
                trigger_error('GPC: error uploading file to server: filename="'.$upload['name'].'"; tmp="'.$tmpFile.'"; size='.$upload['size'].'; srv_err='.$upload['error'], E_USER_WARNING);
            }
            elseif (!file_exists($tmpFile) || !is_uploaded_file($tmpFile))
            {
                trigger_error('GPC: uploaded file not found: filename="'.$upload['name'].'"; tmp="'.$tmpFile.'"; size='.$upload['size'], E_USER_WARNING);
                $upload['error'] = self::UPLOAD_ERR_SERVER;
            }
            elseif (($fsize = filesize($tmpFile)) != $upload['size'])
            {
                trigger_error('GPC: uploaded file is not totally uploaded: filename="'.$upload['name'].'"; tmp="'.$tmpFile.'"; size='.$upload['size'].'; realsize='.$fsize, E_USER_WARNING);
                $upload['error'] = self::UPLOAD_ERR_PARTIAL;
            }
            $this->UPLOADS[$varname] = $upload;
        }


        $this->UPLOADS+= $fgroups;
    }

}
