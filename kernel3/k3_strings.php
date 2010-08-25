<?php
/*
 * QuickFox kernel 3 'SlyFox' String parsing module
 * Requires PHP >= 5.1.0
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

class FStr
{
    const COMM = 0;
    const HEX  = 1;
    const WORD = 2;
    const HTML = 3;
    const PATH = 4;

    const LINE = 8; // flag

    const URL_MASK_R = '[\w\#$%&~/\.\-;:=,?@+\(\)\[\]\|]+';
    const URL_MASK_F = '(?>[0-9A-z]+://[0-9A-z_\-\.]+\.[A-z]{2,4})(?:\/[\w\#$%&~/\.\-;:=,?@+\(\)\[\]\|]+)?';
    const EMAIL_MASK = '[0-9A-z_\-\.]+@[0-9A-z_\-\.]+\.[A-z]{2,4}';
    const PHPWORD_MASK = '[A-z_]\w*';

    const LTT_CACHEPREFIX = 'FSTR.LTT.';
    const CHR_CACHEPREFIX = 'FSTR.CHR.';
    const INT_ENCODING = F_INTERNAL_ENCODING;

    const ENDL = "\n";

    private static $ltts = Array(); // alphabetic chars data array
    private static $chrs = Array(); // Charconv tables
    private static $useMB = false;

    private function __construct() {}

    static public function initEncoders()
    {
        self::$useMB = extension_loaded('mbstring');

        setlocale(LC_ALL, 'EN');
        if (self::$useMB)
        {
            mb_substitute_character(63);
            mb_language('uni');
            mb_internal_encoding(self::INT_ENCODING);
        }
        if (function_exists('iconv_set_encoding'))
            iconv_set_encoding('internal_encoding', self::INT_ENCODING);
    }

    // multibyte string routines
    static public function strToUpper($string, $encoding = self::INT_ENCODING)
    {
        if (function_exists('mb_strtoupper') && $out = mb_strtoupper($string, $encoding))
            return $out;

        $table = self::_getLetterTable($encoding);
        return strtr($string, $table);
    }

    static public function strToLower($string, $encoding = self::INT_ENCODING)
    {
        if (function_exists('mb_strtolower') && $out = mb_strtolower($string, $encoding))
            return $out;

        $table = self::_getLetterTable($encoding);
        return strtr($string, array_flip($table));
    }

    static public function strLen($string, $encoding = self::INT_ENCODING)
    {
        if (self::$useMB && $out = mb_strlen($string, $encoding))
            return $out;
        elseif (function_exists('iconv_strlen') && $out = iconv_strlen($string, $encoding))
            return $out;

        $encoding = strtolower($encoding);
        if ($encoding == 'utf-8')
        {
            $string = preg_replace('#[\x80-\xBF]+#', '', $string);
            return strlen($string);
        }
        else
            return strlen($string);
    }

    static public function strRecode($string, $to_enc = self::INT_ENCODING, $from_enc = self::INT_ENCODING )
    {
        $from_enc = strtolower($from_enc);
        $to_enc   = strtolower($to_enc);
        if (!$to_enc || !$from_enc)
            return false;
        if ($to_enc == $from_enc)
            return $string;

        if (self::$useMB && $out = mb_convert_encoding($string, $to_enc, $from_enc))
            return $out;
        elseif (extension_loaded('iconv') && $out = iconv($from_enc, $to_enc.'//IGNORE//TRANSLIT', $string))
            return $out;

        if ($from_enc == 'utf-8')
            return self::_subFromUtf($string, $to_enc);
        elseif ($to_enc == 'utf-8')
            return self::_subToUtf($string, $from_enc);
        else
        {
            if (!($table1 = self::_getCharTable($from_enc)) || !($table2 = self::_getCharTable($to_enc)))
                return false;

            $table = Array();
            foreach($table1 as $ut=>$cp)
                $table[ord($cp)] = $table2[$ut];

            $unk = (isset($table2[0x3F])) // Try set unknown to '?'
                 ? $table2[0x3F]
                 : '';
            unset($table1, $table2);

            $out = '';
            $in_len = strlen($string);
            for ($i=0; $i<$in_len; $i++)
            {
                $ch = ord($string[$i]);

                $out.= (isset($table[$ch]))
                     ? $table[$ch]
                     : $out.= '?';
            }
            return $out;
        }
    }

    static public function strToMime($string, $recode_to = '', $Quoted_Printable = false)
    {
        if (!$recode_to)
            $recode_to = self::INT_ENCODING;
        //if (self::$useMB && $out = mb_encode_mimeheader($string, $recode_to, 'B'))
        //    return $out;

        if ($recode_to && $recoded = self::strRecode($string, $recode_to))
            $string = $recoded;
        else
            $recode_to = self::INT_ENCODING;

        if ($Quoted_Printable)
            $out = '=?'.$recode_to.'?Q?'.strtr(rawurlencode($string), '%', '=').'?=';
        else
            $out = '=?'.$recode_to.'?B?'.base64_encode($string).'?=';

        return $out;
    }

    static public function subStr($string, $start, $length = false, $encoding = self::INT_ENCODING)
    {
        if ($length === false)
            $length = strlen($string);

        if (self::$useMB && $out = mb_substr($string, $start, $length, $encoding))
            return $out;
        elseif (function_exists('iconv_substr') && $out = iconv_substr($string, $start, $length, $encoding))
            return $out;

        $encoding = strtolower($encoding);
        if ($encoding != 'utf-8')
            return substr($string, $start, $length);

        if ($letters = self::_utfExplode($string))
        {
            $strLen = count($letters);
            if ($strLen <= $start)
                return false;
            $letters = array_slice($letters, $start, $length);
            $out = implode('', $letters);
            return $out;
        }
        return '';
    }

    static public function smartTrim($string, $length = 15, $encoding = self::INT_ENCODING)
    {
        $len = self::strLen($string, $encoding);

        if ($len > $length)
        {
            $string = self::subStr($string, 0, $length);
            $pos = strrpos($string, ' ');
            if ($pos > 0)
                $string = substr($string, 0, $pos);
            return $string.'...';
        }
        else
            return $string;
    }


    // subtype casting
    static public function cast($val, $type = self::COMM)
    {
        $val = (string) $val;

        switch ($type & 7)
        {
            case self::HEX:
                $val = strtolower(preg_replace('#[^0-9a-fA-F]#', '', $val));
                break;

            case self::HTML:
                $val = htmlspecialchars($val); // TODO: revise
                break;

            case self::WORD:
                $val = preg_replace('#[^0-9a-zA-Z_\-]#', '', $val);
                break;

            case self::PATH:
                $val = preg_replace('#(\\\\|/)+#', DIRECTORY_SEPARATOR, $val);
                $val = preg_replace('#[\x00-\x1F\*\?\;\|]|/$|\\\\$|^\.$|(?<!^[A-z])\:#', '', $val);
                $val = trim($val);
                break;
        }

        if ($type & self::LINE)
            $val = preg_replace('#[\r\n]#', '', $val);

        return $val;
    }

    static public function addslashesHeredoc($text, $heredoc_id = false)
    {
        $text = str_replace(Array('\\', '$'), Array('\\\\', '\\$'), $text);
        if ($heredoc_id)
            $text = str_replace("\n\r?".$heredoc_id, "\n ".$heredoc_id, $text);
        return $text;
    }

    static private $JS_REPLACE = array(
           '\\' => '\\\\', '/'  => '\\/', "\r" => '\\r', "\n" => '\\n',
           "\t" => '\\t',  "\b" => '\\b', "\f" => '\\f', '"'  => '\\"',
           );

    static public function addslashesJS($text)
    {
        $text = strtr($text, self::$JS_REPLACE);
        return $text;
    }

    static public function unslash($data)
    {
        return FMisc::iterate($data, 'stripslashes', true);
    }

    static public function htmlschars($data, $q_mode = ENT_COMPAT)
    {
        return FMisc::iterate($data, 'htmlspecialchars', true, $q_mode);
    }

    static public function JSDefine($data)
    {
        $odata = 'null';

        if (is_bool($data))
            $odata = $data ? 'true' : 'false';
        elseif (is_scalar($data))
        {
            $odata = '"'.strtr($data, self::$JS_REPLACE).'"';
        }
        elseif (is_array($data) || is_object($data))
        {
            $odata = Array();
            foreach ($data as $key=>$val)
                $odata[] = $key.': '.self::JSDefine($val);
            $odata = '{ '.implode(', ', $odata).' }';
        }

        return $odata;
    }

    static public function PHPDefine($data, $tabs = 0)
    {
        $tab  = '    ';
        $tabs = intval($tabs);
        $pref = str_repeat($tab, $tabs+1);

        if (is_numeric($data))
            $def = $data;
        elseif (is_bool($data))
            $def = (($data) ? 'true' : 'false');
        elseif (is_null($data))
            $def = 'null';
        elseif (is_array($data) || is_object($data))
        {            $def = "Array (\n";
            $fields = Array();
            $maxlen = 0;
            foreach( $data as $key => $val )
            {
                $field = (is_numeric($key)) ? $key." => " : '\''.addslashes($key).'\' => ';
                $field.= self::PHPDefine($val, $tabs+1);
                $fields[]= $pref.$field;
            }
            $def.=implode(" ,\n", $fields)."\n".$pref.') ';
        }
        else
            $def = '\''.addslashes($data).'\'';
        return $def;
    }

    static public function heredocDefine($str, $heredoc_id = 'HSTR', $add_semicolon = false)
    {
        return '<<<'.$heredoc_id.self::ENDL.self::addslashesHeredoc($val, $heredoc_id).self::ENDL.$heredoc_id.($add_semicolon ? ';' : '').self::ENDL;
    }

    static public function smartAmpersands($string)
    {
        return preg_replace('#\&(?!([A-z]+|\#\d{1,5}|\#x[0-9a-fA-F]{2,4});)#', '&amp;', $string);
    }

    static private $SCHARS = null;
    static private $NQSCHARS = null;

    static public function smartHTMLSchars($string, $no_quotes = false)
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

    static public function smartSprintf($string, array $params)
    {
        $params = array_values($params);
        $count = preg_match_all('#\%(\d+)\$|\%\w#', $string, $masks, PREG_PATTERN_ORDER);
        if ($count > 0)
        {
            $masks = $masks[1];
            $count = max($count, max($masks));
            $params+= array_fill(0, $count, '');
            $string = vsprintf($string, $params);
        }

        return $string;
    }

    static public function isWord($string)
    {
        return !!preg_match('#^'.self::PHPWORD_MASK.'$#D', $string);
    }

    static public function isEmail($string)
    {
        return !!preg_match('#^'.self::EMAIL_MASK.'$#D', $string);
    }

    static public function isUrl($string)
    {
        if (preg_match('#^'.self::URL_MASK_F.'$#D', $string))
            return 1;
        if (preg_match('#^'.self::URL_MASK_R.'$#D', $string))
            return 2;
        return 0;
    }

    static public function path($path)
    {
        return self::cast($path, self::PATH);
    }

    static public function basename($name)
    {
        return (preg_match('#[\x80-\xFF]#', $name)) ? preg_replace('#^.*[\\\/]#', '', $name) : basename($name);
    }

    static public function basenameExt($name)
    {
        return (preg_match('#[\x80-\xFF]#', $name)) ? preg_replace('#^.*\.#', '', $name) : pathinfo($name, PATHINFO_EXTENSION);
    }

    static public function urlencode($string, $spec_rw = false)
    {
        $string = rawurlencode($string);
        if ($spec_rw)
            $string = str_replace('%2F', '/', $string); // strange but needed for mod_rw
        return $string;
    }

    // url parsing functions
    static public function urlAddParam($url, $pname, $pdata, $with_amps = false, $replace = false)
    {
        $sep = ($with_amps) ? '&amp;' : '&';

        if (stristr($url, 'javascript'))
            return $url;

        if (strstr($url, $pname.'='))
        {
            if ($replace)
                $url = self::urlDropParam($url, $pname);
            else
                return $url;
        }

        $insert = ( !strstr($url, '?') ) ? '?' : $sep;
        $insert.= $pname.'='.rawurlencode($pdata);

        $url = preg_replace('#(\#|$)#', $insert.'\\1', $url, 1);

        return $url;
    }

    // TODO: rebuild add/drop param functions
    static public function urlDropParam($url, $pname)
    {
        if (stristr($url, 'javascript'))
            return $url;

        list($url, $anchor) = explode('#', $url, 2);
        list($url, $query) = explode('?', $url, 2);

        $query = preg_replace('#(&amp;|&)?'.preg_quote($pname, '#').'=[^&]*(&amp;|&)?#', '$1', $query);
        return $url.($query ? '?'.$query : '').($anchor ? '#'.$anchor : '');
    }

    static public function urlDataPack($data)
    {
        $data = (string) $data;
        $hash = self::shortHash($data);
        return rawurlencode(base64_encode($hash.'|'.$data));
    }

    static public function urlDataUnpack($data)
    {
        $data = (string) $data;
        $data = base64_decode(rawurldecode($data));
        list($hash, $data) = explode('|', $data, 2);
        $rhash = self::shortHash($data);
        return ($hash == $rhash) ? $data : false;
    }

    // generates full url
    static public function fullUrl($url, $with_amps = false, $force_host = '')
    {
        if ($url[0] == '#')
            return $url;

        $url_p = parse_url($url);

        if (isset($url_p['scheme']))
        {
            $scheme = strtolower($url_p['scheme']);
            if ($scheme == 'mailto')
                return $url;
            $url = $scheme.'://';
        }
        else
            $url = (F('HTTP')->secure) ? 'https://' : 'http://';

        if (isset($url_p['host']))
        {
            if (isset($url_p['username']))
            {
                $url.= $url_p['username'];
                if (isset($url_p['password']))
                    $url.= $url_p['password'];
                $url.= '@';
            }
            $url.= $url_p['host'];
            if (isset($url_p['port']))
                $url.= ':'.$url_p['port'];

            if (isset($url_p['path']))
                $url.= preg_replace('#(\/|\\\)+#', '/', $url_p['path']);
        }
        else
        {
            $url.= ($force_host) ? $force_host : F('HTTP')->srvName;
            if (isset($url_p['path']))
            {
                if ($url_p['path']{0} != '/')
                    $url_p['path'] = '/'.F('HTTP')->rootDir.'/'.$url_p['path'];
            }
            else
                $url_p['path'] = '/'.F('HTTP')->rootDir.'/'.F_SITE_INDEX;

            $url_p['path'] = preg_replace('#(\/|\\\)+#', '/', $url_p['path']);
            $url.= $url_p['path'];
        }

        if (isset($url_p['query']))
            $url.= '?'.$url_p['query'];

        if (isset($url_p['fragment']))
            $url.= '#'.$url_p['fragment'];

        $url = ($with_amps) ? preg_replace('#\&(?![A-z]+;)#', '&amp;', $url) : str_replace('&amp;', '&', $url);

        return $url;
    }

    // UIDs and hashes
    static public function shortUID($add_entr = '')
    {
        static $etropy = '';
        $out = str_pad(dechex(crc32(uniqid($add_entr.$etropy))), 8, '0', STR_PAD_LEFT);
        $etropy = $out;
        return $out;
    }

    static public function shortHash($data)
    {
        return str_pad(dechex(crc32($data)), 8, '0', STR_PAD_LEFT);
    }

    static public function pathHash($path)
    {        return md5(realpath($path)); // TODO: maybe this needs to be modified
    }

    // private functions
    static private function _subFromUtf($string, $to_enc)
    {
        if ($to_enc == 'utf-8')
            return $string;

        if (!($table = self::_getCharTable($to_enc)))
            return false;

        $unk = (isset($table[0x3F])) // Try set unknown to '?'
             ? $table[0x3F]
             : '';
        if ($letters = self::_utfExplode($string))
        {
            $out = '';
            reset($letters);
            while (list($i, $lett) = each($letters))
            {
                $uni = ord($lett[0]);

                if ($uni < 0x80)
                    $uni = $uni;
                elseif (($uni >> 5) == 0x06)
                    $uni = (($uni & 0x1F) <<  6) | (ord($lett[1]) & 0x3F);
                elseif (($uni >> 4) == 0x0E)
                    $uni = (($uni & 0x0F) << 12) | ((ord($lett[1]) & 0x3F) <<  6) | (ord($lett[2]) & 0x3F);
                elseif (($uni >> 3) == 0x1E)
                    $uni = (($uni & 0x07) << 18) | ((ord($lett[1]) & 0x3F) << 12) | ((ord($lett[2]) & 0x3F) << 6) | (ord($lett[3]) & 0x3F);
                else
                {
                    $out.= $unk;
                    continue;
                }

                $out.= (isset($table[$uni]))
                     ? $table[$uni]
                     : $unk;
            }
        }
        return $out;
    }

    static private function _subToUtf($string, $from_enc)
    {
        if ($from_enc == 'utf-8')
            return $string;

        if (!($table0 = self::_getCharTable($from_enc)))
            return false;

        $table = Array();
        foreach ($table0 as $ut=>$cp)
            $table[ord($cp)] = $ut;
        unset($table0);

        $out = '';
        $in_len = strlen($string);
        for ($i=0; $i<$in_len; $i++)
        {
            $ch = ord($string[$i]);

            if (isset($table[$ch]))
            {
                $uni = $table[$ch];
                if ($uni < 0x80)
                    $out.= chr($uni);
                elseif ($UtfCharInDec < 0x800)
                    $out.= chr(($uni >>  6) + 0xC0).chr(($uni & 0x3F) + 0x80);
                elseif ($UtfCharInDec < 0x10000)
                    $out.= chr(($uni >> 12) + 0xE0).chr((($uni >>  6) & 0x3F) + 0x80).chr(($uni & 0x3F) + 0x80);
                elseif ($UtfCharInDec < 0x200000)
                    $out.= chr(($uni >> 18) + 0xF0).chr((($uni >> 12) & 0x3F) + 0x80).chr((($uni >> 6)) & 0x3F + 0x80). chr(($uni & 0x3F) + 0x80);
                else
                    $out.= '?';
            }
            else
                $out.= '?';
        }
        return $out;
    }

    static private function _getLetterTable($encoding = self::INT_ENCODING)
    {
        $encoding = strtolower($encoding);
        $is_utf = ($encoding == 'utf-8');

        $cachename = self::LTT_CACHEPREFIX.$encoding;
        if (isset(self::$ltts[$encoding]))
        {
            return self::$ltts[$encoding];
        }
        elseif ($data = FCache::get($cachename))
        {
            return (self::$ltts[$encoding] = $data);
        }
        elseif ($data = file_get_contents(F_KERNEL_DIR.'/chars/'.$encoding.'.ltt')) // we'll try to load chars data
        {
            $table = Array();
            preg_match_all('#0x([0-9a-fA-F]{1,6})\[0x([0-9a-fA-F]{1,6})\]#', $data, $matches, PREG_SET_ORDER);
            if ($is_utf)
                foreach ($matches as $part)
                    $table[self::_hexToUtf($part[1])] = self::_hexToUtf($part[2]);
            else
                foreach ($matches as $part)
                    $table[self::_hexToChr($part[1])] = self::_hexToChr($part[2]);

            FCache::set($cachename, $table);
            return (self::$ltts[$encoding] = $table);
        }
        else
        {
            trigger_error('UString: There is no letter table for '.$encoding, E_USER_NOTICE);
            return Array();
        }
    }

    // loads unicode to charset table
    static private function _getCharTable($encoding = self::INT_ENCODING)
    {
        $encoding = strtolower($encoding);
        if ($encoding == 'utf-8')
            return false;

        $cachename = self::CHR_CACHEPREFIX.$encoding;

        if (isset(self::$chrs[$encoding]))
        {
            return self::$chrs[$encoding];
        }
        elseif ($data = F('Cache')->Get($cachename))
        {
            return (self::$chrs[$encoding] = $data);
        }
        elseif ($data = file_get_contents(F_KERNEL_DIR.'/chars/'.$encoding.'.chr')) // we'll try to load chars data
        {
            $table = Array();
            preg_match_all('#0x([0-9a-fA-F]{1,6})\[0x([0-9a-fA-F]{1,6})\]#', $data, $matches, PREG_SET_ORDER);
            foreach ($matches as $part)
            {
                $table[hexdec($part[1])] = self::_hexToChr($part[2]);
            }

            F('Cache')->Set($cachename, $table);
            return (self::$chrs[$encoding] = $table);
        }
        else
        {
            trigger_error('UString: Can\'t load chartable for '.$encoding, E_USER_WARNING);
            return false;
        }
    }

    static private function _hexToChr($Hex)
    {
        $dec = (hexdec($Hex) & 255);
        return chr($dec);
    }

    static private function _hexToUtf($UtfCharInHex)
    {
        $OutputChar = '';
        $UtfCharInDec = hexdec($UtfCharInHex);

        if ($UtfCharInDec & 0x1F0000)
            return pack('C*', ($UtfCharInDec >> 18) | 0xF0, ($UtfCharInDec >> 12) & 0x3F | 0x80, ($UtfCharInDec >> 6) & 0x3F | 0x80, $UtfCharInDec & 0x3F | 0x80);
        elseif ($UtfCharInDec & 0xF800)
            return pack('C*', ($UtfCharInDec >> 12) | 0xE0, ($UtfCharInDec >>  6) & 0x3F | 0x80, $UtfCharInDec & 0x3F | 0x80);
        elseif ($UtfCharInDec & 0x780)
            return pack('C*', ($UtfCharInDec >>  6) | 0xC0, $UtfCharInDec & 0x3F | 0x80);
        else
            return chr($UtfCharInDec & 0x7F);
    }

    static private function _utfExplode($string)
    {
        $letters = Array();
        if (preg_match_all('#[\x00-\x7F]|[\xC0-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF7][\x80-\xBF]{3}#', $string, $letters))
            $letters = $letters[0];
        else
            $letters = Array();
        return $letters;
    }

}
FStr::initEncoders();

?>