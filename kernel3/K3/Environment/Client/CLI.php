<?php
/**
 * Copyright (C) 2013 Andrey F. Kupreychik (Foxel)
 * This file is part of QuickFox Kernel 3.
 * See https://github.com/foxel/Kernel3/ for more details.
 * Kernel 3 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * Kernel 3 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with Kernel 3. If not, see <http://www.gnu.org/licenses/>.
 */

class K3_Environment_Client_CLI extends K3_Environment_Client
{
    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        $this->_cookies =& $_COOKIE; // TODO: think if we need to get a copy instead

        $this->pool['IP']        = '127.0.0.1';
        $this->pool['IPInteger'] = ip2long($this->pool['IP']);
    }

    /**
     * @param $name
     * @param string|bool $value
     * @param int|bool $expire
     * @param string|bool $rootPath
     * @param bool $addPrefix
     * @param bool $setDomain
     * @return bool
     */
    public function setCookie($name, $value = false, $expire = false, $rootPath = false, $addPrefix = true, $setDomain = true)
    {
        $this->_cookies[$name] = $value;

        return true;
    }

    /**
     * @param int $securityLevel
     * @return string
     */
    public function getSignature($securityLevel = 0)
    {
        static $signParts = array('USER');
        static $psignParts = array('SHELL', 'SSH_CLIENT', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

        $sign = array();
        foreach ($signParts as $key) {
            if (isset($_SERVER[$key])) {
                $sign[] = $_SERVER[$key];
            }
        }

        if ($securityLevel > 0) {
            $ip     = explode('.', $this->pool['IP']);
            $sign[] = $ip[0];
            $sign[] = $ip[1];
            if ($securityLevel > 1) {
                $sign[] = $ip[2];
                foreach ($psignParts as $key) {
                    if (isset($_SERVER[$key])) {
                        $sign[] = $_SERVER[$key];
                    }
                }
            }
            if ($securityLevel > 2) {
                $sign[] = $ip[3];
            }
        }

        $sign = implode('|', $sign);
        $sign = md5($sign);
        return $sign;
    }
}
