<?php

abstract class K3_Response extends K3_Environment_Element implements I_K3_Response
{
    // HTTP send content types
    const DISPOSITION_ATTACHMENT = 1;
    const FILENAME_RFC1522       = 8;
    const FILENAME_TRICKY        = 16;
    const FILENAME_RFC2231       = 32;

    private $buffer  = '';
    private $headers = array();

    public function __construct(K3_Environment $env = null)
    {
        $this->pool = array(
            'doHTMLParse' => true,
            'useGZIP'     => false,
            'statusCode'  => 0,
        );
        parent::__construct($env);
    }

    public function write($text, $noNewLine = false)
    {
        if (is_scalar($text))
            $this->buffer.= (string) $text;

        if (!$noNewLine)
            $this->buffer.= PHP_EOL;

        return $this;
    }

    public function clearBuffer()
    {
        $this->buffer = '';

        return $this;
    }

    public function getBuffer()
    {
        return $this->buffer;
    }

    public function isEmpty()
    {
        return !strlen($this->buffer);
    }

    public function sendDataStream(FDataStream $stream, array $params = array(), $flags = 0)
    {
        ignore_user_abort(false);

        if (!isset($params['mimeType']))
            $params['mimeType'] = 'application/octet-stream';
        if (!isset($params['contentTime']))
            $params['contentTime'] = $stream->mtime();

        $streamLength = $stream->size();

        $seekStream = isset($params['seekStream'])
            ? intval($params['seekStream'])
            : 0;

        $params['contentLength'] = $contentLength = isset($params['contentLength'])
            ? min($params['contentLength'], $streamLength - $seekStream)
            : $streamLength - $seekStream;

        if ($stream->open('rb'))
        {
            $this->setDefaultHeaders($params, $flags);

            $this->setHeader('Content-Transfer-Encoding', 'binary');
            $this->setHeader('X-K3-Page-GenTime', F()->Timer->timeSpent());

            if ($flags & self::STREAM_SETRANGE)
            {
                $this->setStatus(206);
                $this->setHeader('Content-Range', 'bytes '.$seekFile.'-'.($streamLength-1).'/'.$streamLength);
            }

            $stream->seek($seekFile);

            $this->sendHeadersData();
            $buff = '';
            while ($stream->read($buff, 10485760)) // 10MB
                $this->sendResponseData($buff);

            $stream->close();

            exit();
        }

        return false;
    }

    public function sendFile($file, array $params = array(), $flags = 0)
    {
        if (!file_exists($file))
            return false;

        if (!isset($params['filename'])) {
            $params['filename'] = $file;
        }

        return $this->sendDataStream(new FFileStream($file), $params, $flags);
    }

    public function sendBuffer($encoding = '', array $params = array(), $flags = 0)
    {
        if (!$this->isEmpty()) {
            if ($this->doHTMLParse)
            {
                $this->throwEventRef('HTML_parse', $this->buffer);

                $statstring = sprintf(F()->LNG->lang('FOOT_STATS_PAGETIME'), F()->Timer->timeSpent()).' ';
                if (F()->ping('DBase') && F()->DBase->queriesCount)
                    $statstring.= sprintf(F()->LNG->lang('FOOT_STATS_SQLSTAT'), F()->DBase->queriesCount, F()->DBase->queriesTime).' ';

                $this->buffer = str_replace('<!--Page-Stats-->', $statstring, $this->buffer);
                $params['mimeType'] = (preg_match('#[\w\-]+/[\w\-]+#', $params['mimeType'])) ? $params['mimeType'] : 'text/html';
            }
            else
                $params['mimeType'] = (preg_match('#[\w\-]+/[\w\-]+#', $params['mimeType'])) ? $params['mimeType'] : 'text/plain';

            if ($encoding) {
                if ($buffer = FStr::strRecode($this->buffer, $encoding))
                    $this->buffer = $buffer;
                else
                    $encoding = F::INTERNAL_ENCODING;
            } else {
                $encoding = F::INTERNAL_ENCODING;
            }

            if (strpos($params['mimeType'], 'text/') === 0) {
                $params['mimeType'] = $params['mimeType'].'; charset='.$encoding;
            }

            if ($this->doHTMLParse) {
                $meta_conttype = '<meta http-equiv="Content-Type" content="'.$params['mimeType'].'" />';
                $this->buffer = str_replace('<!--Meta-Content-Type-->', $meta_conttype, $this->buffer);
            }

            if ($this->useGZIP && $this->tryGZIPEncode($this->buffer)) {
                $this->setHeader('Content-Encoding', 'gzip');
            }
        }

        $params['contentLength'] = strlen($this->buffer);

        $this->setDefaultHeaders($params, $flags);
        $this->setHeader('X-K3-Page-GenTime', F()->Timer->timeSpent());

        $this->sendHeadersData();
        $this->sendResponseData($this->buffer);
        exit();
    }

    public function sendRedirect($url, $useHTTP1 = false)
    {
        $url = FStr::fullUrl($url);
        $this->throwEventRef('URL_Parse', $url );
        $hurl = strtr($url, Array('&' => '&amp;'));

        if ($useHTTP1) {
            $this->setHeader('Refresh', '0; URL='.$url);
        } else {
            $this->setHeader('Location', $url);
        }

        $this->sendHeadersData();
        $this->sendResponseData('<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset='.F::INTERNAL_ENCODING.'"><meta http-equiv="refresh" content="0; url='.$hurl.'"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="'.$hurl.'">HERE</a> to be redirected</div></body></html>');
        exit();
    }

    protected function setDefaultHeaders(array $params, $flags = 0)
    {
        // content length 
        if (isset($params['contentLength'])) {
            $this->setHeader('Content-Length', $params['contentLength']);
        }
        // mime type
        if (isset($params['mimeType'])) {
            $this->setHeader('Content-Type', $params['mimeType']);
        }
        // modified time
        if (isset($params['contentTime'])) {
            $cTimestamp = is_int($params['contentTime']) ? $params['contentTime'] : strtotime($params['contentTime']);
            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s ', $cTimestamp).'GMT');
        }
        // expires time
        if (isset($params['contentCacheTime'])) {
            $this->setHeader('Expires', date('r', F()->Timer->qTime() + (int) $params['contentCacheTime']), true);
        } else {
            $this->setHeader('Cache-Control', 'no-cache');
        }
        // disposition
        $disposition = ($flags & self::FILE_ATTACHMENT)
            ? 'attachment'
            : 'inline';
        $dispositionHeader = array(&$disposition);

        if (isset($params['filename'])) {
            $filename = preg_replace('#[\x00-\x1F]+#', '', $params['filename']);

            if (preg_match('#[^\x20-\x7F]#', $filename))
            {
                // according to RFC 2183 all headers must contain only of ASCII chars
                // according to RFC 1522 there is a way to represent non-ASCII chars
                //  in MIME encoded strings like =?utf-8?B?0KTQsNC50LsuanBn?=
                //  but actually only Gecko-based browsers accepted that type of message...
                //  so in this part non-ASCII chars will be transliterated according to
                //  selected language and all unknown chars will be replaced with '_'
                //  if you want to send non-ASCII filename to FireFox you'll need to
                //  set 'K3_Response::FILE_RFC1522' flag
                // Or you may use tricky_mode to force sending urlencoded UTF-8 filenames.
                //  IE will get it but other browsers probably not
                //  so don't use it if you don't really need to
                if ($flags & self::FILENAME_RFC1522) {
                    $dispositionParts[] = 'filename="'.FStr::strToMime($filename).'"';
                }
                elseif ($flags & self::FILENAME_TRICKY) {
                    if (preg_match('#^text/#i', $filemime))
                        $disposition = 'attachment';
                    $dispositionHeader[] = 'filename="'.rawurlencode($filename).'"';
                    if (F::INTERNAL_ENCODING != 'utf-8') {
                        $dispositionHeader[] = 'encoding="'.F::INTERNAL_ENCODING.'"';
                    }
                } else {
                    $dispositionHeader[] = 'filename="'.F()->LNG->Translit($filename).'"';
                }

                // RFC2231 filename* token for modern browsers
                if ($flags & self::FILENAME_RFC2231) {
                    $dispositionHeader[] = 'filename*='.FStr::strToRFC2231($filename);
                }
            }
            else {
                $dispositionHeader[] = 'filename="'.$filename.'"';
            }
        }

        $this->setHeader('Content-Disposition', implode('; ', $dispositionHeader));
        $this->setHeader('X-Powered-By', 'QuickFox kernel 3 (PHP/'.PHP_VERSION.')');
        return $this;
    }

    // setters
    public function setStatusCode($statusCode)
    {
        $this->pool['statusCode'] = (int) $statusCode;
        return $this;
    }

    public function setDoHTMLParse($doHTMLParse)
    {
        $this->pool['doHTMLParse'] = (boolean) $doHTMLParse;
        return $this;
    }

    public function setUseGZIP($useGZIP)
    {
        $this->pool['useGZIP'] = (boolean) $useGZIP;
        return $this;
    }

    public function setHeader($headerName, $value, $replace = true)
    {
        if ($replace || !isset($this->headers[$headerName])) {
            $this->headers[$headerName] = $value;
        } else {
            $this->headers[$headerName] = (array) $this->headers[$headerName] + array($value);
        }

        return $this;
    }

    abstract protected function sendHeadersData();
    abstract protected function sendResponseData($data = null);

    protected function tryGZIPEncode(&$data, $level = 9)
    {
        if (!extension_loaded('zlib'))
            return false;

        $level = abs(intval($level)) % 10;

        try {
            $compressed = gzencode($data, $level);
            $data = $compressed;
            return true;
        } catch (Exception $e) {
            trigger_error('GZIP compression failed ('.__CLASS__.')', E_USER_WARNING);
        }

        return false;
    }
}
