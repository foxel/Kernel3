<?php
/**
 * Copyright (C) 2010 - 2012, 2014 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' String parsing module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');


/** @deprecated */
class FStr implements I_K3_Deprecated
{
    const COMM = K3_Util_String::FILTER_NONE;
    const HEX  = K3_Util_String::FILTER_HEX;
    const WORD = K3_Util_String::FILTER_WORD;
    const HTML = K3_Util_String::FILTER_HTML;
    const PATH = K3_Util_String::FILTER_PATH;
    const UNIXPATH = K3_Util_String::FILTER_PATH_UNIX;

    const LINE = K3_Util_String::FILTER_LINE; // flag

    const URL_MASK_R = K3_String::MASK_URL_REL;
    const URL_MASK_F = K3_String::MASK_URL_FULL;

    const EMAIL_MASK = K3_String::MASK_EMAIL;
    const PHPWORD_MASK = K3_String::MASK_PHP_WORD;

    const ENDL = PHP_EOL;

    private function __construct() {}

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new StaticInstance('FStr');
        return self::$self;
    }

    // multibyte string routines
    public static function strToUpper($string, $encoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strToUpper($string, $encoding);
    }

    public static function strToLower($string, $encoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strToLower($string, $encoding);
    }

    public static function strLen($string, $encoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strLength($string, $encoding);
    }

    public static function strRecode($string, $toEncoding = K3_String::INTERNAL_ENCODING, $fromEncoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strRecode($string, $toEncoding, $fromEncoding);
    }

    public static function strToRFC2231($string, $toEncoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strToRFC2231($string, K3_String::INTERNAL_ENCODING, $toEncoding);
    }

    public static function strToMime($string, $toEncoding = K3_String::INTERNAL_ENCODING, $quotedPrintable = false)
    {
        return K3_String::strToMime($string, K3_String::INTERNAL_ENCODING, $toEncoding, $quotedPrintable);
    }

    public static function subStr($string, $start, $length = null, $encoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strSubString($string, $start, $length, $encoding);
    }

    public static function smartTrim($string, $length = 15, $encoding = K3_String::INTERNAL_ENCODING)
    {
        return K3_String::strSmartTrim($string, $length, $encoding);
    }

    public static function fixLength($string, $length, $pad_with = ' ', $mode = null)
    {
        return K3_Util_String::fixLength($string, $length, $pad_with, $mode);
    }

    // subtype casting
    public static function cast($val, $type = self::COMM)
    {
        return K3_Util_String::filter($val, $type);
    }

    public static function addslashesHeredoc($text, $heredoc_id = false)
    {
        return K3_Util_String::escapeHeredoc($text, $heredoc_id);
    }


    public static function addslashesJS($text)
    {
        return K3_Util_String::escapeJSON($text);
    }

    public static function unslash($data)
    {
        return FMisc::iterate($data, 'stripslashes', true);
    }

    public static function htmlschars($data, $q_mode = ENT_COMPAT)
    {
        return FMisc::iterate($data, 'htmlspecialchars', true, $q_mode);
    }

    public static function JSDefine($data)
    {
        return K3_Util_Value::defineJSON($data);
    }

    public static function PHPDefine($data)
    {
        return K3_Util_Value::definePHP($data);
    }

    public static function implodeRecursive(array $array, $glue = '')
    {
        return K3_Util_Array::implodeRecursive($glue, $array);
    }

    public static function heredocDefine($str, $heredoc_id = 'HSTR', $add_semicolon = false)
    {
        return K3_Util_String::escapeHeredoc($str, $heredoc_id, true, $add_semicolon);
    }

    /** @deprecated */
    public static function smartAmpersands($string)
    {
        return preg_replace('#\&(?!([A-z]+|\#\d{1,5}|\#x[0-9a-fA-F]{2,4});)#', '&amp;', $string);
    }

    /** @var array */
    static private $SCHARS = null;
    /** @var array */
    static private $NQSCHARS = null;

    /** @deprecated */
    public static function smartHTMLSchars($string, $no_quotes = false)
    {
        if (is_null(self::$SCHARS))
        {
            self::$SCHARS = get_html_translation_table(HTML_SPECIALCHARS);
            unset(self::$SCHARS['&']);
            self::$NQSCHARS = self::$SCHARS;
            unset(self::$NQSCHARS['"'], self::$NQSCHARS['\'']);
        }

        return strtr(self::smartAmpersands($string), $no_quotes ? self::$NQSCHARS : self::$SCHARS);
    }

    public static function smartSprintf($string, array $params)
    {
        return K3_Util_String::smartSprintf($string, $params);
    }

    public static function isWord($string)
    {
        return K3_String::isWord($string);
    }

    public static function isEmail($string, $checkDNS = false)
    {
        return K3_String::isEmail($string, $checkDNS);
    }

    public static function isUrl($string)
    {
        return K3_String::isUrl($string);
    }

    /** @deprecated */
    public static function path($path)
    {
        return self::cast($path, self::PATH);
    }

    public static function basename($name)
    {
        return K3_Util_File::basename($name);
    }

    public static function basenameExt($name)
    {
        return K3_Util_File::basenameExtension($name);
    }

    public static function urlencode($string, $spec_rw = false)
    {
        return K3_Util_Url::urlencode($string, $spec_rw);
    }

    // url parsing functions
    public static function urlAddParam($url, $pname, $pdata, $with_amps = false, $replace = false)
    {
        return K3_Util_Url::urlAddParam($url, $pname, $pdata, $with_amps, $replace);
    }

    // TODO: rebuild add/drop param functions
    public static function urlDropParam($url, $pname)
    {
        return K3_Util_Url::urlDropParam($url, $pname, !!strstr($url, '&amp;'));
    }

    public static function urlDataPack($data)
    {
        return K3_Util_Url::packString($data);
    }

    public static function urlDataUnpack($data)
    {
        return K3_Util_Url::unpackString($data);
    }

    // generates full url
    public static function fullUrl($url, $with_amps = false, $force_host = '', K3_Environment $env = null)
    {
        $url = K3_Util_Url::fullUrl($url, $env ? : F()->appEnv, $force_host);
        return ($with_amps) ? preg_replace('#\&(?![A-z]+;)#', '&amp;', $url) : str_replace('&amp;', '&', $url);
    }

    /**
     * @static
     * @param string $url
     * @return array
     */
    public static function getZendStyleURLParams($url)
    {
        return K3_Util_Url::parseZendStyleURLParams($url);
    }

    // UIDs and hashes
    public static function shortUID($add_entr = '')
    {
        return K3_Util_String::shortUID($add_entr);
    }

    public static function shortHash($data)
    {
        return K3_Util_String::shortHash($data);
    }

    public static function pathHash($path)
    {
        return K3_Util_File::pathHash($path);
    }
}
