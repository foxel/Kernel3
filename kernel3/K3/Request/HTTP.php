<?php
/**
 * Copyright (C) 2012 Andrey F. Kupreychik (Foxel)
 *
 * This file is part of QuickFox Kernel 3.
 * See https://github.com/foxel/Kernel3/ for more details.
 *
 * Kernel 3 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Kernel 3 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Kernel 3. If not, see <http://www.gnu.org/licenses/>.
 */

class K3_Request_HTTP extends K3_Request
{
    /**
     * @var array
     */
    protected $raw = array();

    /**
     * @var array|null
     */
    protected $UPLOADS = null;

    /**
     * @var bool
     */
    protected $doGPCStrip = false;

    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);
        $this->doGPCStrip = (bool) get_magic_quotes_gpc();

        $this->pool['isSecure'] = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off'));
        $this->pool['isAjax']   = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'));
        $this->pool['isPost']   = !empty($_POST);
    }

    /**
     * @param K3_Environment $env
     * @return K3_Request_HTTP
     */
    public function setEnvironment(K3_Environment $env)
    {
        parent::setEnvironment($env);
        
        $this->pool['url'] = preg_replace('#\/|\\\\+#', '/', trim($_SERVER['REQUEST_URI']));
        $this->pool['url'] = preg_replace('#^/+#s', '', $this->pool['url']);
        if ($this->env->server->rootPath) {
            $this->pool['url'] = preg_replace('#^'.$this->env->server->rootPath.'\/+#', '', $this->pool['url']);
        }
        if (isset($_SERVER['HTTP_REFERER']) && ($this->pool['referer'] = trim($_SERVER['HTTP_REFERER']))) {
            if (strpos($this->pool['referer'], $this->env->server->rootUrl) === 0) {
                $this->pool['referer'] = substr($this->pool['referer'], strlen($this->env->server->rootUrl));
                $this->pool['refererIsExternal'] = false;
            } else {
                $this->pool['refererIsExternal'] = true;
            }
        }

        return $this;
    }

    /**
     * useful for special inpur parsings
     * @param array $datas
     * @param int $set
     * @return bool
     */
    public function setRaws(array $datas, $set = self::GET)
    {
        $raw =& $this->raw;
        foreach ($datas as $key => $data)
            $raw[$set][$key] = $data;

        return $this;
    }

    /**
     * @param string $varName
     * @param int $source
     * @param mixed $default
     * @return mixed|null
     */
    public function get($varName, $source = self::ALL, $default = null)
    {
        $raw = $this->raw;

        if (isset($raw[$source][$varName])) {
            return $raw[$source][$varName];
        }

        // cookie requests are redirected
        if ($source == self::COOKIE) {
            $val = $this->env->client->getCookie($varName);
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

    /**
     * @param string $varName
     * @return array|null
     */
    public function getFile($varName)
    {
        if (is_null($this->UPLOADS))
            $this->recheckFiles();

        if (isset($this->UPLOADS[$varName]))
            return $this->UPLOADS[$varName];
        else
            return null;
    }

    /**
     * @param string $varName
     * @param string $toFile
     * @param bool $forceReplace
     * @return bool
     */
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
        if (file_exists($oldFile) && is_uploaded_file($oldFile)) // TODO: Throw exceptions on different errors
        {
            if (!file_exists($toFile) || $forceReplace)
            {
                if (move_uploaded_file($oldFile, $toFile))
                {
                    $file['error'] = self::UPLOAD_MOVED;
                    return true;
                }
            }
        }

        return false;
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
                if (is_callable($this->stringRecodeFunc))
                    $upload['name'] = call_user_func($this->stringRecodeFunc, $upload['name']);

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
