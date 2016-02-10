<?php
/**
 * Copyright (C) 2012, 2016 Andrey F. Kupreychik (Foxel)
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

class K3_Environment_Client_HTTP extends K3_Environment_Client
{
    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        $this->_cookies =& $_COOKIE; // TODO: think if we need to get a copy instead

        $this->pool['IP']        = static::getClientIp();
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
        if (!$rootPath) {
            $rootPath = ($this->env->server->rootPath) ? '/'.$this->env->server->rootPath.'/' : '/';
        }

        if ($addPrefix) {
            $name = $this->pool['cookiePrefix'].'_'.$name;
        }

        if ($value === false && !isset($this->_cookies[$name])) {
            return true;
        }

        $result = setcookie(
            $name,
            $value,
            $expire,
            $rootPath,
            ($setDomain) ? $this->pool['cookieDomain'] : false, // domain
            ($this->env->getRequest() instanceof I_K3_Request) && $this->env->getRequest()->isSecure // secure only
        );

        if ($result) {
            $this->_cookies[$name] = $value;
        }

        return $result;
    }

    /**
     * @param int $securityLevel
     * @return string
     */
    public function getSignature($securityLevel = 0)
    {
        static $signParts = array('HTTP_USER_AGENT'); //, 'HTTP_ACCEPT_CHARSET', 'HTTP_ACCEPT_ENCODING'
        static $psignParts = array('HTTP_VIA', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP');

        $sign = array();
        foreach ($signParts as $key)
            if (isset($_SERVER[$key]))
                $sign[] = $_SERVER[$key];

        if ($securityLevel > 0) {
            $ip = explode('.', $this->pool['IP']);
            $sign[] = $ip[0];
            $sign[] = $ip[1];
            if ($securityLevel > 1)
            {
                $sign[] = $ip[2];
                foreach ($psignParts as $key)
                    if (isset($_SERVER[$key]))
                        $sign[] = $_SERVER[$key];
            }
            if ($securityLevel > 2)
                $sign[] = $ip[3];
        }

        $sign = implode('|', $sign);
        $sign = md5($sign);
        return $sign;
    }

    /**
     * @return string
     */
    public static function getClientIp()
    {
        $privateNetworkIpRange = array(
            'A' => array(ip2long('10.0.0.0'), ip2long('10.255.255.255')),     // single class A network
            'B' => array(ip2long('172.16.0.0'), ip2long('172.31.255.255')),   // 16 contiguous class B network
            'C' => array(ip2long('192.168.0.0'), ip2long('192.168.255.255')), // 256 contiguous class C network
        );

        $headers = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);

                foreach ($ips as $ip) {
                    $ip     = trim($ip);
                    $longIp = ip2long($ip);

                    if ($longIp === false) {
                        continue;
                    }

                    $isPrivateIp = false;
                    foreach ($privateNetworkIpRange AS $ipRange) {
                        if ($longIp >= $ipRange[0] && $longIp <= $ipRange[1]) {
                            $isPrivateIp = true;
                        }
                    }

                    if (!$isPrivateIp) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'];
    }
}
