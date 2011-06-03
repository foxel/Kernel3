<?php
/**
 * QuickFox kernel 3 'SlyFox' HTTP interface
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

// HTTP interface
// Outputs data to user and manages cookies data
final class FHTTPInterface extends FEventDispatcher
{
    const DEF_COOKIE_PREFIX = 'K3';
    // HTTP send content types
    const FILE_ATTACHMENT = 1;
    const FILE_RFC1522    = 8;
    const FILE_TRICKY     = 16;

    private $buffer = '';

    public $doHTML = true;
    public $doGZIP = true;

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FHTTPInterface();
        return self::$self;
    }

    private function __construct()
    {
        $this->pool = Array(
            'IP'       => '',
            'IPInt'    => 0,
            'rootUrl'  => '',
            'request'  => '',
            'rootDir'  => '',
            'rootFull' => '',
            'srvName'  => '',
            'srvPort'  => 80,
            'referer'  => '',
            'extRef'   => false,

            'cDomain'  => false,
            'cPrefix'  => self::DEF_COOKIE_PREFIX,
            'secure'   => false,
            );

        $this->pool['IP']      = $_SERVER['REMOTE_ADDR'];
        $this->pool['IPInt']   = ip2long($this->pool['IP']);
        
        // trick for x64/x86 compatibility
        if ($this->pool['IPInt'] < 0)
            $this->pool['IPInt'] = sprintf('%u', $this->pool['IPInt']);

        $this->pool['srvName'] = isset($_SERVER['HTTP_HOST']) ? preg_replace('#:\d+#', '', $_SERVER['HTTP_HOST']) : $_SERVER['SERVER_NAME'];
        $this->pool['srvPort'] = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;
        $this->pool['rootUrl'] = 'http://'.$this->pool['srvName'].(($this->pool['srvPort'] != 80) ? $this->pool['srvPort'] : '').'/';
        
        $this->pool['request'] = preg_replace('#\/|\\\+#', '/', trim($_SERVER['REQUEST_URI']));
        $this->pool['request'] = preg_replace('#^/+#s', '', $this->pool['request']);

        $this->pool['rootDir'] = dirname($_SERVER['PHP_SELF']);
        $this->pool['rootDir'] = preg_replace('#\/|\\\+#', '/', $this->pool['rootDir']);

        if ( $this->pool['rootDir'] = preg_replace('#^\/*|\/*$#', '', $this->pool['rootDir']) )
        {
            $this->pool['rootUrl'].= $this->pool['rootDir'].'/';
            $this->pool['request'] = preg_replace('#^\/*'.$this->pool['rootDir'].'\/+#', '', $this->pool['request']);
        }

        $this->pool['rootFull'] = preg_replace(Array('#\/|\\\+#', '#\/*$#'), Array('/', ''), $_SERVER['DOCUMENT_ROOT']).$this->pool['rootDir'];

        if (isset($_SERVER['HTTP_REFERER']) && ($this->pool['referer'] = trim($_SERVER['HTTP_REFERER'])))
        {
            if (strpos($this->pool['referer'], $this->pool['rootUrl']) === 0)
                $this->pool['referer'] = substr($this->pool['referer'], strlen($this->pool['rootUrl']));
            else
                $this->pool['extRef'] = true;
        }

        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on'))
            $this->pool['secure'] = true;

        if (headers_sent($file, $line))
            trigger_error('QuickFox Kernel 3 HTTP initialization error (Headers already sent)', E_USER_ERROR);

        if (!FMisc::obFree())
            trigger_error('QuickFox Kernel 3 HTTP initialization error (Output buffering is started elsewhere)', E_USER_ERROR);

        header('X-Powered-By: QuickFox kernel 3 (PHP/'.PHP_VERSION.')');
        ob_start(array( &$this, 'obOutFilter'));
        ini_set ('default_charset', '');

        //$this->pool['cPrefix'] = F()->Config->Get('cookie_prefix', 'common', self::DEF_COOKIE_PREFIX);
        //F()->Config->Add_Listener('cookie_prefix', 'common', Array(&$this, 'setCPrefix'));
    }

    public function setCPrefix($new_prefix = false, $do_rename = false)
    {
        if (!$new_prefix || !is_string($new_prefix))
            $new_prefix = self::DEF_COOKIE_PREFIX;

        // special for chenging prefix without dropping down the session
        if ($do_rename)
        {            $o_prefix = $this->pool['cPrefix'].'_';
            foreach ($_COOKIE as $val => $var)
            {
                if (strpos($val, $o_prefix) === 0)
                {
                    $this->setCookie($val, false, false, false, false, true);
                    $val = $new_prefix.'_'.substr($val, strlen($o_prefix));
                    $this->setCookie($val, $var, false, false, false, true);
                }
                $_COOKIE[$val] = $var;
            }
        }
        $this->pool['cPrefix'] = (string) $new_prefix;
    }

    public function getCookie($name)
    {        $name = $this->pool['cPrefix'].'_'.$name;

        return (isset($_COOKIE[$name])) ? $_COOKIE[$name] : null;
    }

    public function write($text, $no_nl = false)
    {
        if (is_scalar($text))
            $this->buffer.= (string) $text;

        if (!$no_nl)
            $this->buffer.= "\n";
    }

    public function writeFromOB($append = false, $no_nl = false)
    {
        if ($append)
            $this->buffer.= ob_get_contents();
        else
            $this->buffer = ob_get_contents();

        ob_clean();
        if (!$no_nl)
            $this->buffer.= "\n";
    }

    public function getOB()
    {
        $data = ob_get_contents();
        ob_clean();
        return $data;
    }

    public function clearBuffer()
    {
        $this->buffer = '';
    }

    public function sendDataStream(FDataStream $stream, $filename, $filemime = false, $filemtime = false, $flags = 0)
    {
        if (headers_sent())
        {
            trigger_error('QuickFox Kernel 3 HTTP error', E_USER_ERROR);
            return false;
        }

        ignore_user_abort(false);

        if (!$filemime)
            $filemime = 'application/octet-stream';

        $filename = FStr::basename($filename);
        $disposition = ($flags & self::FILE_ATTACHMENT)
            ? 'attachment'
            : 'inline';

        $FileSize = $stream->size();
        $FileTime = (is_int($filemtime))
            ? $filemtime
            : $stream->mtime();

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $reqModTime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))
            if ($FileTime <= $reqModTime)
            {
                FMisc::obFree();
                $this->setStatus(304);
                header('Last-Modified: '.gmdate('D, d M Y H:i:s ', $FileTime).'GMT');
                exit();
            }
        
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('#bytes\=(\d+)\-(\d*?)#i', $_SERVER['HTTP_RANGE'], $ranges))
        {
            $NeedRange = true;
            $SeekFile  = intval($ranges[1]);
        }
        else
        {
            $NeedRange = false;
            $SeekFile  = 0;
        }

        if ($stream->open('rb'))
        {
            FMisc::obFree();

            $filename = preg_replace('#[\x00-\x1F]+#', '', $filename);

            if (preg_match('#[^\x20-\x7F]#', $filename))
            {
                // according to RFC 2183 all headers must contain only of ASCII chars
                // according to RFC 1522 there is a way to represent non-ASCII chars
                //  in MIME encoded strings like =?utf-8?B?0KTQsNC50LsuanBn?=
                //  but actually only Gecko-based browsers accepted that type of message...
                //  so in this part non-ASCII chars will be transliterated according to
                //  selected language and all unknown chars will be replaced with '_'
                //  if you want to send non-ASCII filename to FireFox you'll need to
                //  set 'FHTTPInterface::FILE_RFC1522' flag
                // Or you may use tricky_mode to force sending 8-bit UTF-8 filenames
                //  via breaking some standarts. Opera will get it but IE not
                //  so don't use it if you don't really need to
                if ($flags & self::FILE_RFC1522)
                {
                    $filename = FStr::strToMime($filename);
                }
                elseif ($flags & self::FILE_TRICKY)
                {
                    if (preg_match('#^text/#i', $filemime))
                        $disposition = 'attachment';
                    $filemime.= '; charset="'.F::INTERNAL_ENCODING.'"';
                }
                else
                    $filename = F('LNG')->Translit($filename);
            }

            header('Last-Modified: '.gmdate('D, d M Y H:i:s ', $FileTime).'GMT');
            header('Expires: '.date('r', F('Timer')->qTime() + 3600*24), true);
            header('Content-Transfer-Encoding: binary');
            header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');
            header('Content-Type: '.$filemime);
            header('Content-Length: '.($FileSize - $SeekFile));
            header('Accept-Ranges: bytes');
            header('X-QF-GenTime: '.F('Timer')->timeSpent());

            if ($NeedRange)
            {
                header($_SERVER['SERVER_PROTOCOL'] . ' 206 Partial Content');
                header('Content-Range: bytes '.$SeekFile.'-'.($FileSize-1).'/'.$FileSize);
            }

            $stream->seek($SeekFile);
            $buff = '';
            while($stream->read($buff, 10485760)) // 10MB
                print($buff);
            $stream->close();

            exit();
        }
        else
            return false;
    }

    public function sendFile($file, $filename = false, $filemime = false, $filemtime = false, $flags = 0)
    {
        if (!file_exists($file))
            return false;
            
        if (!$filename)
            $filename = $file;

        return $this->sendDataStream(new FFileStream($file), $filename, $filemime, $filemtime, $flags);
    }

    public function sendBuffer($recode_to = '', $c_type = '', $force_cache = 0, $send_filename = '')
    {
        if (headers_sent())
        {
            trigger_error('QuickFox Kernel 3 HTTP error', E_USER_ERROR);
            return false;
        }

        FMisc::obFree();

        if ($this->doHTML)
        {
            $this->throwEventRef('HTML_parse', $this->buffer);

            $statstring = sprintf(F('LNG')->lang('FOOT_STATS_PAGETIME'), F('Timer')->timeSpent()).' ';
            if (F()->ping('DBase') && F('DBase')->queriesCount)
                $statstring.= sprintf(F('LNG')->lang('FOOT_STATS_SQLSTAT'), F('DBase')->queriesCount, F('DBase')->queriesTime).' ';

            $this->buffer = str_replace('<!--Page-Stats-->', $statstring, $this->buffer);
            $c_type = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'text/html';
        }
        else
            $c_type = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'text/plain';

        if ($encoding = $recode_to)
        {
            if ($buffer = FStr::strRecode($this->buffer, $encoding))
                $this->buffer = $buffer;
            else
                $encoding = F::INTERNAL_ENCODING;

            header('Content-Type: '.$c_type.'; charset='.$encoding);
            $meta_conttype = '<meta http-equiv="Content-Type" content="'.$c_type.'; charset='.$encoding.'" />';
        }
        else
        {
            header('Content-Type: '.$c_type.'; charset='.F::INTERNAL_ENCODING);
            $meta_conttype = '<meta http-equiv="Content-Type" content="'.$c_type.'; charset='.F::INTERNAL_ENCODING.'" />';
        }

        if ($this->doHTML)
            $this->buffer = str_replace('<!--Meta-Content-Type-->', $meta_conttype, $this->buffer);

        if ($this->doGZIP)
        {
            if ($this->tryGZIPEncode($this->buffer))
                header('Content-Encoding: gzip');
        }


        if ($force_cache > 0)
            header('Expires: '.date('r', F('Timer')->qTime() + $force_cache), true);
        else
            header('Cache-Control: no-cache');

        if ($send_filename)
            header('Content-Disposition: attachment; filename="'.$send_filename.'"');

        header('Content-Length: '.strlen($this->buffer));
        header('X-K3-Page-GenTime: '.F('Timer')->timeSpent());
        print $this->buffer;
        exit();
    }

    public function sendBinary($data = '', $c_type = '', $force_cache = 0, $send_filename = '')
    {
        if (headers_sent())
        {
            trigger_error('QuickFox Kernel 3 HTTP error', E_USER_ERROR);
            return false;
        }

        FMisc::obFree();

        $c_type = (preg_match('#[\w\-]+/[\w\-]+#', $c_type)) ? $c_type : 'application/octet-stream';

        header('Content-Type: '.$c_type);

        if (false && $this->doGZIP)
        {
            if ($this->tryGZIPEncode($this->buffer))
                header('Content-Encoding: gzip');
        }


        if ($force_cache > 0)
            header('Expires: '.date('r', F('Timer')->qTime() + $force_cache), true);
        else
            header('Cache-Control: no-cache');

        $data = ($data) ? $data : $this->buffer;

        if ($send_filename)
            header('Content-Disposition: attachment; filename="'.$send_filename.'"');

        header('Content-Length: '.strlen($data));
        header('X-K3-Page-GenTime: '.F('Timer')->timeSpent());
        print $data;
        exit();
    }

    // sets cookies domain (checks if current client request is sent on that domain or it's sub)
    public function setCookiesDomain($domain)
    {
        if (!preg_match('#[\w\.]+\w\.\w{2,4}#', $domain))
            return false;
        $my_domain = '.'.ltrim(strtolower($this->SrvName), '.');
        $domain    = '.'.ltrim(strtolower($domain), '.');
        $len = strlen($domain);
        if (substr($my_domain, -$len) == $domain)
        {
            $this->pool['cDomain'] = $domain;
            return true;
        }
        return false;
    }

    // Sets cookie with root dir parameter (needed on sites with many independent systems in folders)
    public function setCookie($name, $value = false, $expire = false, $root = false, $no_domain = false, $no_prefix = false)
    {
        if (!$root)
            $root = ($this->pool['rootDir']) ? '/'.$this->pool['rootDir'].'/' : '/';
        if (!$no_prefix)
            $name = $this->pool['cPrefix'].'_'.$name;
            
        if ($value === false && !isset($_COOKIE[$name]))
            return true;
            
        $res = ($no_domain)
            ? setcookie($name, $value, $expire, $root)
            : setcookie($name, $value, $expire, $root, $this->pool['cDomain']);
        
        if ($res)
            $_COOKIE[$name] = $value;
        
        return $res;
    }

    // Redirecting function
    public function redirect($url)
    {
        if (headers_sent())
        {
            trigger_error('QuickFox Kernel 3 HTTP error', E_USER_ERROR);
            return false;
        }

        if (strstr(urldecode($url), "\n") || strstr(urldecode($url), "\r"))
            trigger_error('Tried to redirect to potentially insecure url.', E_USER_ERROR);

        FMisc::obFree();

        $url = FStr::fullUrl($url);
        $this->throwEventRef('URL_Parse', $url );
        $hurl = strtr($url, Array('&' => '&amp;'));

        // Redirect via an HTML form for PITA webservers
        if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE')))
        {
            header('Refresh: 0; URL='.$url);
            echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url='.$hurl.'"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="'.$hurl.'">HERE</a> to be redirected</div></body></html>';
            exit();
        }

        // Behave as per HTTP/1.1 spec for others
        header('Location: '.$url);
        exit();
    }

    public function setStatus($stat_code)
    {
        static $codes = Array(
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            503 => 'Service Unavailable',
            );

        if (isset($codes[$stat_code]))
            header(implode(' ', Array($_SERVER["SERVER_PROTOCOL"], $stat_code, $codes[$stat_code])), true, $stat_code);
    }

    // returns client signature based on browser, ip and proxy
    public function getClientSignature($level = 0)
    {
        static $sign_parts = Array('HTTP_USER_AGENT'); //, 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING'
        static $psign_parts = Array('HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

        $sign = Array();
        foreach ($sign_parts as $key)
            if (isset($_SERVER[$key]))
                $sign[] = $_SERVER[$key];

        if ($level > 0)
        {
            $ip = explode('.', $this->pool['IP']);
            $sign[] = $ip[0];
            $sign[] = $ip[1];
            if ($level > 1)
            {
                $sign[] = $ip[2];
                foreach ($psign_parts as $key)
                    if (isset($_SERVER[$key]))
                        $sign[] = $_SERVER[$key];
            }
            if ($level > 2)
                $sign[] = $ip[3];
        }

        $sign = implode('|', $sign);
        $sign = md5($sign);
        return $sign;
    }

    // private functions
    private function tryGZIPEncode(&$text, $level = 9)
    {
        if (!extension_loaded('zlib'))
            return false;

        $compress = false;
        if ( strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') )
            $compress = true;

        $level = abs(intval($level)) % 10;

        if ($compress)
        {
            $gzip_size = strlen($text);
            $gzip_crc = crc32($text);


            $text = gzcompress($text, $level);
            $text = substr($text, 0, strlen($text) - 4);

            $out = "\x1f\x8b\x08\x00\x00\x00\x00\x00";
            $out.=  $text;
            $out.=  pack('V', $gzip_crc);
            $out.=  pack('V', $gzip_size);

            $text = $out;
        }
        // $text = gzencode($text, $level); // strange but does not work on PHP 4.0.6

        return $compress;
    }

    // filters off OB output
    public function obOutFilter($text)
    {
        //return $text;
        if (!$this->buffer)
            return false;
        else
            return 'Output error. Sorry :(';
    }

    function _Close()
    {
        if ( headers_sent($file, $line) )
            if ($file)
            {
                // Critical error - some script module violated QF HTTP otput rules
                if ($file != __FILE__)
                    trigger_error('Script module "'.$file.'" violated QF HTTP otput rules at line '.$line, E_USER_ERROR);

            }
    }

}

?>
