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

if (!defined('F_STARTED')) {
    die('Hacking attempt');
}

define('_STR_URL_ESCAPED', '%[[:xdigit:]]{2}');
define('_STR_URL_CHAR_ALNUM', 'A-Za-z0-9');
define('_STR_URL_CHAR_MARK', '-_.!~*\'()\[\]');
define('_STR_URL_CHAR_RESERVED', ';/?:@&=+$,');
define('_STR_URL_CHAR_SEGMENT', ':@&=+$,;');
define('_STR_URL_CHAR_UNWISE', '{}|\\\\^`');

// Segment can use escaped, unreserved or a set of additional chars
define('_STR_URL_SEGMENT', '(?:'._STR_URL_ESCAPED.'|['._STR_URL_CHAR_ALNUM._STR_URL_CHAR_MARK._STR_URL_CHAR_SEGMENT.'])*');

// Path can be a series of segmets char strings seperated by '/'
define('_STR_URL_PATH', '(?:/|'._STR_URL_SEGMENT.')+');

// URI characters can be escaped, alphanumeric, mark or reserved chars
define('_STR_URL_URIC', '(?:'._STR_URL_ESCAPED.'|['._STR_URL_CHAR_ALNUM._STR_URL_CHAR_MARK._STR_URL_CHAR_RESERVED.'])');

define('_STR_URL_RELATIVE', '('._STR_URL_PATH.')?(\?'._STR_URL_URIC.')?(\#'._STR_URL_SEGMENT.')?');
define('_STR_URL_ABSOLUTE', '(?>[0-9A-z]+://(?:[0-9A-z_\-\.]+\.[A-z]{2,4}|\d{1-3}\.\d{1-3}\.\d{1-3}\.\d{1-3}))/'._STR_URL_RELATIVE);


/**
 * Class K3_String
 * @author Andrey F. Kupreychik
 * @property-reed string $string
 * @property-reed int $length
 * @property-reed string $encoding
 * @immutable
 */
class K3_String extends FBaseClass
{
    const INTERNAL_ENCODING = F::INTERNAL_ENCODING;

//    const MASK_URL_REL = _STR_URL_RELATIVE;
//    const MASK_URL_FULL = _STR_URL_ABSOLUTE;
//    const URL_CHAR_ALNUM    = _STR_URL_CHAR_ALNUM;
//    const URL_CHAR_MARK     = _STR_URL_CHAR_MARK;
//    const URL_CHAR_RESERVED = _STR_URL_CHAR_RESERVED;
//    const URL_CHAR_SEGMENT  = _STR_URL_CHAR_SEGMENT;

    const MASK_URL_REL  = '[\w\#$%&~/\\\.\-;:=,?@+\(\)\[\]\|]+';
    const MASK_URL_FULL = '(?>(?:[0-9A-z]+:)?//[0-9A-z_\-\.]+\.[A-z]{2,4})(?:\/[\w\#$%&~/\.\-;:=,?@+\(\)\[\]\|]+)?';

    const MASK_EMAIL    = '[0-9A-z_\-\.]+@[0-9A-z_\-\.]+\.[A-z]{2,4}';
    const MASK_PHP_WORD = '[A-z_]\w*';

    /** @var string  */
    protected $_string   = '';
    /** @var string  */
    protected $_encoding = self::INTERNAL_ENCODING;
    /** @var int  */
    protected $_length   = 0;

    /**
     * @param string $string
     * @param string $encoding
     * @throws FException
     */
    public function __construct($string, $encoding = self::INTERNAL_ENCODING)
    {
        if (!self::$_initialized) {
            self::_initialize();
        }

        $this->_string   = $string;
        $this->_encoding = $encoding ?: self::INTERNAL_ENCODING;
        $this->_length   = mb_strlen($this->_string, $this->_encoding);
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->_length;
    }

    /**
     * @return string
     */
    public function getString()
    {
        return $this->_string;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->_encoding;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return $this->_string;
    }

    /**
     * @return K3_String
     */
    public function toUpper()
    {
        return new self(mb_strtoupper($this->_string, $this->_encoding), $this->_encoding);
    }

    /**
     * @return K3_String
     */
    public function toLower()
    {
        return new self(mb_strtolower($this->_string, $this->_encoding), $this->_encoding);
    }

    /**
     * @param string $targetEncoding
     * @return K3_String
     * @throws FException
     */
    public function recode($targetEncoding = self::INTERNAL_ENCODING)
    {
        if (!$targetEncoding) {
            throw new FException('No target encoding');
        }

        if ($targetEncoding == $this->_encoding) {
            return $this;
        }

        return new self(mb_convert_encoding($this->_string, $targetEncoding, $this->_encoding), $targetEncoding);
    }

    /**
     * @param string $targetEncoding
     * @return string
     * @throws FException
     */
    public function toRFC2231($targetEncoding = self::INTERNAL_ENCODING)
    {
        if (!$targetEncoding) {
            throw new FException('No target encoding');
        }

        $string = $this->recode($targetEncoding)->getString();

        if (!strlen($string)) {
            return $string;
        }

        $out = $targetEncoding.'\'\''.rawurlencode($string);

        return $out;
    }

    /**
     * @param string $targetEncoding
     * @param bool $quotedPrintable
     * @return string
     */
    public function toMime($targetEncoding = self::INTERNAL_ENCODING, $quotedPrintable = false)
    {
        if (!$targetEncoding) {
            $targetEncoding = $this->_encoding;
        }

        if ($out = mb_encode_mimeheader($this->_string, $targetEncoding, $quotedPrintable ? 'Q' : 'B')) {
            return $out;
        }

        $string = $this->recode($targetEncoding)->getString();

        if (!strlen($string))
            return $string;

        $out = ($quotedPrintable)
            ? '=?'.$targetEncoding.'?Q?'.strtr(rawurlencode($string), '%', '=').'?='
            : '=?'.$targetEncoding.'?B?'.base64_encode($string).'?=';

        return $out;
    }

    /**
     * @param int $start
     * @param int $length
     * @return K3_String
     */
    public function subString($start, $length = null)
    {
        return new self(mb_substr($this->_string, $start, $length, $this->_encoding), $this->_encoding);
    }

    /**
     * @param int $length
     * @return K3_String
     */
    public function smartTrim($length = 15)
    {
        $len = $this->_length;

        if ($len > $length) {
            $string = $this->substring(0, $length)->getString();
            $spacePos = strrpos($string, ' ');
            if ($spacePos > 0) {
                $string = substr($string, 0, $spacePos);
            }
            return new self($string.'...', $this->_encoding);
        } else {
            return $this;
        }
    }


    
    /*******************\
     * STATIC VARIANTS *
    \*******************/

    /**
     * @param string $string
     * @param string $encoding
     * @return string
     */
    public static function strToUpper($string, $encoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $encoding);
        return $str->toUpper()->getString();
    }

    /**
     * @param string $string
     * @param string $encoding
     * @return string
     */
    public static function strToLower($string, $encoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $encoding);
        return $str->toLower()->getString();
    }

    /**
     * @param string $string
     * @param string $encoding
     * @return int
     */
    public static function strLength($string, $encoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $encoding);
        return $str->getLength();
    }

    /**
     * @param string $string
     * @param string $toEncoding
     * @param string $fromEncoding
     * @return string
     */
    public static function strRecode($string, $toEncoding = self::INTERNAL_ENCODING, $fromEncoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $fromEncoding);
        return $str->recode($toEncoding)->getString();
    }

    /**
     * @param string $string
     * @param string $encoding
     * @param string $toEncoding
     * @return string
     */
    public static function strToRFC2231($string, $encoding = self::INTERNAL_ENCODING, $toEncoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $encoding);
        return $str->toRFC2231($toEncoding);
    }

    /**
     * @param string $string
     * @param string $encoding
     * @param string $toEncoding
     * @param bool $quotedPrintable
     * @return string
     */
    public static function strToMime($string, $encoding = self::INTERNAL_ENCODING, $toEncoding = self::INTERNAL_ENCODING, $quotedPrintable = false)
    {
        $str = new self($string, $encoding);
        return $str->toMime($toEncoding, $quotedPrintable);
    }

    /**
     * @param string $string
     * @param int $start
     * @param int $length
     * @param string $encoding
     * @return string
     */
    public static function strSubString($string, $start, $length = null, $encoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $encoding);
        return $str->subString($start, $length)->getString();
    }

    /**
     * @param string $string
     * @param int $length
     * @param string $encoding
     * @return string
     */
    public static function strSmartTrim($string, $length = 15, $encoding = self::INTERNAL_ENCODING)
    {
        $str = new self($string, $encoding);
        return $str->smartTrim($length)->getString();
    }

    /*********************\
     * testing functions *
    \*********************/

    /**
     * @param string $string
     * @return bool
     */
    public static function isWord($string)
    {
        return !!preg_match('#^'.self::MASK_PHP_WORD.'$#D', $string);
    }

    /**
     * @param string $string
     * @param bool $checkDNS
     * @return bool
     */
    public static function isEmail($string, $checkDNS = false)
    {
        $result = !!preg_match('#^'.self::MASK_EMAIL.'$#D', $string);

        if ($result && $checkDNS)
        {
            list($user, $domain) = explode('@', $string);
            $result = checkdnsrr($domain, 'MX');
        }

        return $result;
    }

    /**
     * @param string $string
     * @return int
     */
    public static function isUrl($string)
    {
        if (preg_match('#^'.self::MASK_URL_FULL.'$#D', $string)) {
            return 1;
        }
        if (preg_match('#^'.self::MASK_URL_REL.'$#D', $string)) {
            return 2;
        }
        return 0;
    }

    /** @var bool */
    protected static $_initialized = false;
    protected static function _initialize()
    {
        if (!extension_loaded('mbstring')) {
            throw new FException(array('using %s requires mbstring extension', __CLASS__));
        }

        mb_substitute_character(63);
        mb_language('uni');
        mb_internal_encoding(self::INTERNAL_ENCODING);

        self::$_initialized = true;
    }
} 
