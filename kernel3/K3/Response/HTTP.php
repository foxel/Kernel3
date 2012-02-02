<?php

class K3_Response_HTTP extends K3_Response
{
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);
        $this->pool['useGZIP']    = (boolean) strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
        $this->pool['statusCode'] = 200;

        ini_set ('default_mimetype', '');
        ini_set ('default_charset', '');
    }

    public function startObHandling()
    {
        return ob_start(array($this, 'obOutputHanlder'));
    }

    public function obOutputHanlder($text)
    {
        //return $text;
        // if the buffer is empty then we get a direct writing without using FHTTP
        if ($this->isEmpty()) {
            $cType = preg_match('#\<(\w+)\>.*\</\1\>#', $text)
                    ? 'text/html'
                    : 'text/plain';
            $this->setDefaultHeaders(array(
                'contentLength' => strlen($text),
                'contentType'   => 'Content-Type: '.$cType.'; charset='.F::INTERNAL_ENCODING,
            ));
            $this->sendHeadersData();
            return false;
        } else {
            return 'Output conflict. Sorry :(';
        }
    }

    public function sendFile($file, array $params = array(), $flags = 0)
    {
        // TODO:: move to request headers
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('#bytes\=(\d+)\-(\d*?)#i', $_SERVER['HTTP_RANGE'], $ranges)) {
            $flags |= K3_Response::STREAM_SETRANGE;
            $params['seekStream'] = intval($ranges[1]);
        }

        parent::sendFile($file, $params, $flags);
    }

    public function setStatusCode($statusCode)
    {
        if (isset($codes[$stat_code])) {
            parent::setStatusCode($statusCode);
        }
        return $this;
    }

    protected function sendHeadersData()
    {
        foreach ($this->headers as $name => &$values) {
            $replace = true;
            foreach ((array) $values as $value) {
                header($name.': '.$value, $replace); // TODO: think about raw headers and auto encoding
                $replace = false;
            }
        }
        return $this;
    }

    protected function sendResponseData($data = null)
    {
        if (is_null($data)) {
            $data = $this->buffer;
        }
        echo $data;
    }

    protected static $statusCodes = Array(
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
}
