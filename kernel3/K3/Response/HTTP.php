<?php
/**
 * Copyright (C) 2012 - 2013 Andrey F. Kupreychik (Foxel)
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

class K3_Response_HTTP extends K3_Response
{
    /**
     * @param K3_Environment $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);
        $this->pool['useGZIP']    = (boolean) strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
        $this->pool['statusCode'] = 200;

        ini_set ('default_mimetype', '');
        ini_set ('default_charset', '');
    }

    /**
     * @param string $file
     * @param array $params
     * @param int $flags
     * @return bool|void
     */
    public function sendFile($file, array $params = array(), $flags = 0)
    {
        // TODO:: move to request headers
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('#bytes\=(\d+)\-(\d*?)#i', $_SERVER['HTTP_RANGE'], $ranges)) {
            $flags |= K3_Response::STREAM_SETRANGE;
            $params['seekStream'] = intval($ranges[1]);
        }

        parent::sendFile($file, $params, $flags);
    }

    /**
     * @param $statusCode
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        if (isset(self::$statusCodes[$statusCode])) {
            parent::setStatusCode($statusCode);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function sendHeadersData()
    {
        // sending HTTP status
        header(implode(' ', array($_SERVER['SERVER_PROTOCOL'], $this->statusCode, self::$statusCodes[$this->statusCode])), true, $this->statusCode);

        foreach ($this->headers as $name => &$values) {
            $replace = true;
            foreach ((array) $values as $value) {
                header($name.': '.$value, $replace); // TODO: think about raw headers and auto encoding
                $replace = false;
            }
        }
        return $this;
    }

    /**
     * @param null $data
     */
    protected function sendResponseData($data = null)
    {
        if (is_null($data)) {
            $data = $this->buffer;
        }
        echo $data;
    }

    protected static $statusCodes = array(
        200 => 'OK',
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
