<?php
/**
 * QuickFox kernel 3 'SlyFox' HTTP interface
 * Outputs data to user and manages cookies data
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 * @deprecated
 */
final class FHTTPInterface implements I_K3_Deprecated
{
    const DEF_COOKIE_PREFIX = K3_Environment_Client::DEFAULT_COOKIE_PREFIX;
    // HTTP send content types
    const FILE_ATTACHMENT = K3_Response::DISPOSITION_ATTACHMENT;
    const FILE_RFC1522    = K3_Response::FILENAME_RFC1522;
    const FILE_TRICKY     = K3_Response::FILENAME_TRICKY;
    const FILE_RFC2231    = K3_Response::FILENAME_RFC2231;

    public function __get($varName)
    {
        switch ($varName) {
            case 'IP':       return F()->appEnv->client->IP;
            case 'IPInt':    return F()->appEnv->client->IPInteger;
            case 'rootUrl':  return F()->appEnv->server->rootUrl;
            case 'request':  return F()->appEnv->request->url;
            case 'rootDir':  return F()->appEnv->server->rootPath;
            case 'rootFull': return F()->appEnv->server->rootRealPath;
            case 'srvName':  return F()->appEnv->server->domain;
            case 'srvPort':  return F()->appEnv->server->port;
            case 'referer':  return F()->appEnv->request->referer;
            case 'extRef':   return F()->appEnv->request->refererIsExternal;

            case 'cDomain':  return F()->appEnv->client->cookieDomain;
            case 'cPrefix':  return F()->appEnv->client->cookiePrefix;
            case 'secure':   return F()->appEnv->request->isSecure;
            case 'isAjax':   return F()->appEnv->request->isAjax;

            case 'doHTML':   return F()->appEnv->response->doHTMLParse;
            case 'doGZIP':   return F()->appEnv->response->useGZIP;
            default:         return null;
        }
    }

    public function __set($varName, $value)
    {
        switch ($varName) {
            case 'doHTML': F()->appEnv->response->doHTMLParse = $value; break;
            case 'doGZIP': F()->appEnv->response->useGZIP = $value; break;
        }
    }

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FHTTPInterface();
        return self::$self;
    }

    private function __construct()
    {
        ob_start(array( &$this, 'obOutFilter'));
    }

    public function setCPrefix($new_prefix = false, $do_rename = false)
    {
        F()->appEnv->client->setCookiePrefix($new_prefix, $do_rename);
        return $this;
    }

    public function getCookie($name)
    {
        return F()->appEnv->client->getCookie($name);
    }

    public function write($text, $no_nl = false)
    {
        F()->appEnv->response->write($text, $no_nl);

        return $this;
    }

    public function writeFromOB($append = false, $no_nl = false)
    {
        if (!$append) 
            F()->appEnv->response->clearBuffer();

        F()->appEnv->response->write(ob_get_contents(), $no_nl);
        ob_clean();

        return $this;
    }

    public function getOB()
    {
        $data = ob_get_contents();
        ob_clean();
        return $data;
    }

    public function clearBuffer()
    {
        F()->appEnv->response->clearBuffer();
        return $this;
    }

    public function sendDataStream(FDataStream $stream, $filename, $filemime = false, $filemtime = false, $flags = 0)
    {
        $params = array(
            'filename' => $filename
        );
        if ($filemime)  $params['contentType'] = $filemime;
        if ($filemtime) $params['contentTime'] = $filemtime;
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('#bytes\=(\d+)\-(\d*?)#i', $_SERVER['HTTP_RANGE'], $ranges))
        {
            $flags |= K3_Response::STREAM_SETRANGE;
            $params['seekStream'] = intval($ranges[1]);
        }

        $FileTime = (is_int($filemtime))
            ? $filemtime
            : $stream->mtime();

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $reqModTime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))
            if ($FileTime <= $reqModTime)
            {
                FMisc::obFree();
                F()->appEnv->response
                    ->clearBuffer()
                    ->setStatusCode(304)
                    ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s ', $FileTime).'GMT')
                    ->sendBuffer();

                exit();
            }

        FMisc::obFree();
        return F()->appEnv->response->sendDataStream($stream, $params, $flags);
    }

    public function sendFile($file, $filename = false, $filemime = false, $filemtime = false, $flags = 0)
    {
        if (!file_exists($file))
            return false;
            
        if (!$filename)
            $filename = $file;

        FMisc::obFree();
        return $this->sendDataStream(new FFileStream($file), $filename, $filemime, $filemtime, $flags);
    }

    public function sendBuffer($recode_to = '', $c_type = '', $force_cache = 0, $send_filename = '')
    {
        $params = array();
        $flags = 0;
        if ($c_type)  $params['contentType'] = $c_type;
        if ($force_cache) $params['contentCacheTime'] = $force_cache;
        if ($send_filename) {
            $params['filename'] = $send_filename;
            $flags |= K3_Response::DISPOSITION_ATTACHMENT;
        }

        FMisc::obFree();
        return F()->appEnv->response->sendBuffer($recode_to, $params, $flags);
    }

    public function sendBinary($data = '', $c_type = '', $force_cache = 0, $send_filename = '')
    {
        $params = array();
        $flags = 0;
        if ($data) {
            F()->appEnv->response
                ->setDoHTMLParse(false)
                ->clearBuffer()
                ->write($data);
        }
        $params['contentType'] = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'application/octet-stream';
        if ($force_cache) $params['contentCacheTime'] = $force_cache;
        if ($send_filename) {
            $params['filename'] = $send_filename;
            $flags |= K3_Response::DISPOSITION_ATTACHMENT;
        }

        FMisc::obFree();
        return F()->appEnv->response->sendBuffer(false, $params, $flags);
    }

    // sets cookies domain (checks if current client request is sent on that domain or it's sub)
    public function setCookiesDomain($domain)
    {
        F()->appEnv->client->setCookieDomain($domain);

        return $this;
    }

    // Sets cookie with root dir parameter (needed on sites with many independent systems in folders)
    public function setCookie($name, $value = false, $expire = false, $root = false, $no_domain = false, $no_prefix = false)
    {
        return F()->appEnv->client->setCookie($name, $value, $expire, $root, !$no_prefix, !$no_domain);
    }

    // Redirecting function
    public function redirect($url)
    {
        FMisc::obFree();

        F()->appEnv->response->sendRedirect($url);
    }

    public function setStatus($stat_code)
    {
        F()->appEnv->response->setStatusCode($stat_code);

        return $this;
    }

    // returns client signature based on browser, ip and proxy
    public function getClientSignature($level = 0)
    {
        return F()->appEnv->client->getSignature($level);
    }

    // filters off OB output
    public function obOutFilter($text)
    {
        //return $text;
        // if the buffer is empty then we get a direct writing without using FHTTP
        if (F()->appEnv->response->isEmpty()) {
            // magic to set Content-Type if it's not set already
            if (!preg_match('#^Content-Type: #mi', implode(PHP_EOL, headers_list()))) {
                $cType = preg_match('#\<(\w+)\>.*\</\1\>#', $text)
                    ? 'text/html'
                    : 'text/plain';
                header('Content-Type: '.$cType.'; charset='.F::INTERNAL_ENCODING);
            }
            return false;
        } else {
            return 'Output error. Sorry :(';
        }
    }

    function _Close()
    {
        if ( headers_sent($file, $line) ) {
            if ($file)
            {
                // Critical error - some script module violated QF HTTP otput rules
                if ($file != __FILE__)
                    trigger_error('Script module "'.$file.'" violated QF HTTP otput rules at line '.$line, E_USER_ERROR);

            }
        }
    }

    public function addEventHandler($ev_name, $func_link)
    {
        F()->appEnv->response->addEventHandler($ev_name, $func_link);
    }
}
