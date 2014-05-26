<?php
/**
 * Copyright (C) 2014 Andrey F. Kupreychik (Foxel)
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

/**
 * Class K3_Util_Url
 */
class K3_Util_Url extends K3_Util
{
    /**
     * generates full url
     * @param $url
     * @param K3_Environment $env
     * @param string $forceHost
     * @return string
     */
    public static function fullUrl($url, K3_Environment $env, $forceHost = '')
    {
        $url = (string)$url;

        if ($url && $url[0] == '#') {
            return $url;
        }

        $url_p = parse_url($url);

        if (isset($url_p['scheme'])) {
            $scheme = strtolower($url_p['scheme']);
            if ($scheme == 'mailto') {
                return $url;
            }
            $url = $scheme.'://';
        } else {
            $url = ($env->request->isSecure) ? 'https://' : 'http://';
        }

        if (isset($url_p['host'])) {
            if (isset($url_p['username'])) {
                $url .= $url_p['username'];
                if (isset($url_p['password'])) {
                    $url .= ':'.$url_p['password'];
                }
                $url .= '@';
            }
            $url .= $url_p['host'];
            if (isset($url_p['port'])) {
                $url .= ':'.$url_p['port'];
            }

            if (isset($url_p['path'])) {
                $url .= preg_replace('#(\/|\\\)+#', '/', $url_p['path']);
            }
        } else {
            $url .= ($forceHost) ? $forceHost : $env->server->domain;
            if (isset($url_p['path']) && strlen($url_p['path'])) {
                if ($url_p['path'][0] != '/') {
                    $url_p['path'] = '/'.$env->server->rootPath.'/'.$url_p['path'];
                }
            } else {
                $url_p['path'] = '/'.$env->server->rootPath.'/'.F_SITE_INDEX;
            }

            $url_p['path'] = preg_replace('#(\/|\\\)+#', '/', $url_p['path']);
            $url .= $url_p['path'];
        }

        if (isset($url_p['query'])) {
            $url .= '?'.$url_p['query'];
        }

        if (isset($url_p['fragment'])) {
            $url .= '#'.$url_p['fragment'];
        }

        return $url;
    }

    /**
     * @param string $string
     * @param bool $modRWFix
     * @return mixed|string
     */
    public static function urlencode($string, $modRWFix = false)
    {
        $string = rawurlencode($string);
        if ($modRWFix) {
            $string = str_replace('%2F', '/', $string); // strange but needed for mod_rw
        }

        return $string;
    }

    /**
     * @param string $data
     * @return string
     */
    public static function packString($data)
    {
        $data = (string)$data;
        $hash = K3_Util_String::shortHash($data);

        return rawurlencode(base64_encode($hash.'|'.$data));
    }

    /**
     * @param string $data
     * @return bool|string
     */
    public static function unpackString($data)
    {
        $data = (string)$data;
        $data = base64_decode(rawurldecode($data));
        list($hash, $data) = explode('|', $data, 2);
        $realHash = K3_Util_String::shortHash($data);

        return ($hash == $realHash) ? $data : false;
    }

    /**
     * @param string $url
     * @return array
     */
    public static function parseZendStyleURLParams($url)
    {
        $parts = parse_url($url);
        $out   = array();
        if (isset($parts['path'])) {
            $pathParams = explode('/', $parts['path']);
            reset($pathParams);
            while ($key = current($pathParams)) {
                $out[$key] = rawurldecode(next($pathParams));
                next($pathParams);
            }
        }
        if (isset($parts['query'])) {
            parse_str($parts['query'], $tmp = array());
            foreach ($tmp as $key => $value) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    // url parsing functions
    /**
     * @param string $url
     * @param string $paramName
     * @param string $paramValue
     * @param bool $xmlEncoded
     * @param bool $replace
     * @return string
     */
    public static function urlAddParam($url, $paramName, $paramValue, $xmlEncoded = false, $replace = false)
    {
        $separator = ($xmlEncoded) ? '&amp;' : '&';

        if (stristr($url, 'javascript')) {
            return $url;
        }

        $paramName = self::urlencode($paramName);

        if (strstr($url, $paramName.'=')) {
            if ($replace) {
                $url = self::urlDropParam($url, $paramName, $xmlEncoded);
            } else {
                return $url;
            }
        }

        $paramPair = $paramName.'='.self::urlencode($paramValue);
        if ($xmlEncoded) {
            $paramPair = htmlspecialchars($paramPair);
        }

        $insert = (!strstr($url, '?')) ? '?' : $separator;
        $insert .= $paramPair;

        $url = preg_replace('#(\#|$)#', $insert.'\\1', $url, 1);

        return $url;
    }

    /**
     * @param string $url
     * @param string $paramName
     * @param bool $xmlEncoded
     * @return string
     */
    public static function urlDropParam($url, $paramName, $xmlEncoded = false)
    {
        $separator = ($xmlEncoded) ? '&amp;' : '&';

        if (stristr($url, 'javascript')) {
            return $url;
        }

        list($url, $anchor) = explode('#', $url, 2);
        list($url, $query) = explode('?', $url, 2);

        $paramName = self::urlencode($paramName);
        if ($xmlEncoded) {
            $paramName = htmlspecialchars($paramName);
        }

        $query = preg_replace('#('.$separator.'|^)'.preg_quote($paramName, '#').'=.*?('.$separator.'|$)#', '$1', $query);

        return $url.($query ? '?'.$query : '').($anchor ? '#'.$anchor : '');
    }
} 
