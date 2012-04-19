<?php

class K3_Environment_Server_HTTP extends K3_Environment_Server
{
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        $this->pool['domain'] = isset($_SERVER['HTTP_HOST']) ? array_shift(explode(':', $_SERVER['HTTP_HOST'])) : $_SERVER['SERVER_NAME'];
        $this->pool['port']   = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;

        $this->pool['rootUrl']  = 'http://'.$this->pool['domain'].(($this->pool['port'] != 80) ? $this->pool['port'] : '').'/';
        $this->pool['rootPath'] = dirname($_SERVER['SCRIPT_NAME']);

        if ($this->pool['rootPath'] = trim($this->pool['rootPath'], '/\\'))
        {
            $this->pool['rootPath'] = preg_replace('#\/|\\\\+#', '/', $this->pool['rootPath']);
            $this->pool['rootUrl'] .= $this->pool['rootPath'].'/';
        }

        $this->pool['rootRealPath'] = preg_replace(Array('#\/|\\\\+#', '#(\/|\\\\)*$#'), Array(DIRECTORY_SEPARATOR, ''), $_SERVER['DOCUMENT_ROOT']).'/'.$this->pool['rootPath'];
    }

}