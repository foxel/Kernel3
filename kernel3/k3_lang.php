<?php
/**
 * Copyright (C) 2010 - 2014 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' LNG Data Interface
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

// HTTP interface
// Outputs data to user
class FLNGData // extends FEventDispatcher
{
    const CACHEPREFIX = 'LANG.';

    private static $self = null;

    private $lang          = array();
    private $klang         = null;
    private $lang_name     = 'en';
    private $LNG_loaded    = array();
    private $timeTranslate = null;
    private $bsize_tr      = null;

    private $auto_loads = array();
    public  $timeZone   = 0;

    /**
     * @static
     * @return FLNGData
     */
    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FLNGData();
        return self::$self;
    }

    private function __construct()
    {
        $this->pool = array(
            'language' => &$this->lang_name,
            'name' => &$this->lang_name,
            );

        function FLang($key, $params = false, $load = false)
        {
            return F()->LNG->lang($key, $params, $load);
        }
    }

    public function __get($name)
    {
        if (isset($this->pool[$name]))
            return $this->pool[$name];
        else
            return $this->lang($name);
    }

    public function _Start()
    {
        $this->loadKernelDefs();
    }

    public function select($lang)
    {
        trigger_error('LANG: tried to use "select('.$lang.')"', E_USER_WARNING );
        return false;
    }

    public function ask()
    {
        return $this->lang_name;
    }

    public function addAutoLoadDir($directory, $file_suff = '.lng')
    {
        $directory = K3_Util_String::filter($directory, K3_Util_String::FILTER_PATH);
        $hash      = K3_Util_File::pathHash($directory);

        if (isset($this->auto_loads[$hash]))
            return true;

        $cachename = self::CACHEPREFIX.'ald-'.$hash;
        if ($aldata = FCache::get($cachename))
        {
            $this->auto_loads[$hash] = $aldata;
            F()->Timer->logEvent($directory.' lang autoloads installed (from global cache)');
        }
        else
        {
            if ($dir = opendir($directory))
            {
                $aldata = array(0 => $directory);
                $preg_pattern = '#'.preg_quote($file_suff, '#').'$#';
                while ($entry = readdir($dir))
                {
                    $filename = $directory.DIRECTORY_SEPARATOR.$entry;
                    if (preg_match($preg_pattern, $entry) && is_file($filename) && $datas = FMisc::loadDatafile($filename, FMisc::DF_MLINE, true))
                    {
                        $datas = array_keys($datas);
                        foreach ($datas as $key)
                            $aldata[$key] = $entry;
                    }
                }
                closedir($dir);

                ksort($aldata);
                FCache::set($cachename, $aldata);
                $this->auto_loads[$hash] = $aldata;
                F()->Timer->logEvent($directory.' lang autoloads installed (from filesystem)');
            }
            else
            {
                trigger_error('LANG: error installing '.$directory.' auto loading directory', E_USER_WARNING );
                return false;
            }
        }

        return true;
    }

    public function load($filename)
    {
        $this->loadLanguage($filename);
    }

    public function loadLanguage($filename)
    {
        $hash = FStr::pathHash($filename);

        if (!in_array($hash, $this->LNG_loaded))
        {
            $cachename = self::CACHEPREFIX.$hash;

            if ($Ldata = FCache::get($cachename))
            {
                $this->lang = $Ldata + $this->lang;
                if (isset($Ldata['__LNG']))
                    $this->lang_name = strtoupper($Ldata['__LNG']);
                F()->Timer->logEvent('"'.$filename.'" language loaded (from global cache)');
            }
            elseif (!file_exists($filename))
            {
                trigger_error('LANG: "'.$filename.'" lang file does not exist', E_USER_NOTICE );
            }
            elseif (($Ldata = FMisc::loadDatafile($filename, FMisc::DF_MLINE, true)) !== false)
            {
                if (isset($Ldata['__LNG']))
                    $this->lang_name = strtoupper($Ldata['__LNG']);
                FCache::set($cachename, $Ldata);
                $this->lang = $Ldata + $this->lang;
                F()->Timer->logEvent('"'.$filename.'" language file loaded (from lang file)');
                //trigger_error('LANG: error parsing '.$this->lang_name.'.'.$part.' lang file', E_USER_WARNING );
            }
            else
                trigger_error('LANG: error loading "'.$filename.'" lang file', E_USER_WARNING );

            $this->LNG_loaded[] = $hash;
        }
        return true;

    }

    /**
     * @param $key
     * @param mixed $params
     * @param bool $load
     * @return string
     */
    public function lang($key, $params = null, $load = false)
    {
        $key = strtoupper($key);
        if (!$key)
            return '';

        if ($load)
            $this->loadLanguage($load);
        elseif (!isset($this->lang[$key]))
            $this->tryAutoLoad($key);

        if (isset($this->lang[$key]))
            $out = $this->lang[$key];
        elseif (isset($this->klang[$key]))
            $out = $this->klang[$key]; 
        else
            return '['.$key.']';

        if ($params) {
            $params = is_array($params) ? array_values($params) : array($params);
            $out = FStr::smartSprintf($out, $params);
        }
        return $out;
    }

    public function _Call($key, $params = false, $load = false)
    {
        return $this->lang($key, $params, $load);
    }

    public function langParse($data, $prefix = 'L_')
    {
        $linear = FMisc::linearize($data);
        foreach ($linear as &$val)
        {
            if (preg_match('#^'.preg_quote($prefix, '#').'\w+$#D', $val))
                $val = $this->lang(substr($val, strlen($prefix)));
        }

        return $data;
    }

    public function timeFormat($timestamp = false, $format = '', $timeZone = false, $forceNoRelativeFormat = false)
    {
        static $now, $correction, $today, $yesterday, $timeFormat, $lastTimeZone = null, $relativeFormat;

        if (!$now) {
            $now = F()->Timer->qTime();
            $correction = 0;        //(int) F()->Config->Get('time_correction', 'common', 0);
            $timeFormat = 'H:i';    //F()->Config->Get('def_time_format', 'visual', 'H:i');
            $relativeFormat = true; //(bool) F()->Config->Get('force_no_rel_time', 'common', false);
        }

        if (!is_array($this->timeTranslate)) {
            $keys = array(
                1 => array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                2 => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
                3 => array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                4 => array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'),
            );
            $langNames = array(
                1 => 'DATETIME_TR_DAYS',
                2 => 'DATETIME_TR_DAYS_SHORT',
                3 => 'DATETIME_TR_MONTHS',
                4 => 'DATETIME_TR_MONTHS_SHORT',
            );

            if (!isset($this->lang[$langNames[1]])) {
                $this->tryAutoLoad($langNames[1]);
            }

            $translate = array();
            for ($i = 1; $i<=4; ++$i) {
                $langName = $langNames[$i];
                if ($this->privateLang($langName)) {
                    $part = explode('|', $this->privateLang($langName));
                    $pkeys = $keys[$i];
                    if (count($part) == count($pkeys)) {
                        foreach ($pkeys as $id => $key) {
                            $translate[$key] = $part[$id];
                        }
                    }
                }
            }
            $this->timeTranslate = $translate;
        } else {
            $translate = $this->timeTranslate;
        }

        if ($timeZone === false) {
            $timeZone = $this->timeZone;
        }

        if ($lastTimeZone !== $timeZone) {
            $today = $now + 60*$correction;
            $today = floor($today/86400)*86400;
            $yesterday = $today - 86400;
            $lastTimeZone = $timeZone;
        }

        if (!$format) {
            $format = 'd M Y H:i'; //F()->Config->Get('def_date_format', 'visual', 'd M Y H:i');
        }

        if (!is_numeric($timestamp)) {
            $timestamp = $now;
        }
        $timestamp += 60*$correction;

        $timeToDraw = new DateTime();
        $timeToDraw->setTimestamp($timestamp);

        if (is_numeric($timeZone)) {
            // compatibility
            $zones = DateTimeZone::listIdentifiers();
            while (!empty($zones)) {
                $temp = new DateTimeZone(array_shift($zones));
                if ($temp->getOffset($timeToDraw) == $timeZone*3600) {
                    $timeZone = $temp;
                    break;
                }
            }
            if (!$timeZone instanceof DateTimeZone) {
                throw new FException('Time Zone with given offset not found');
            }
        } elseif (!$timeZone instanceof DateTimeZone) {
            $timeZone= new DateTimeZone($timeZone);
        }
        $timeToDraw->setTimezone($timeZone);

        if (!$relativeFormat || $forceNoRelativeFormat || $timestamp == $now) {
            $out = $timeToDraw->format($format);
        } elseif ($timestamp > $now) {
            if ($timestamp < $now + 60)
                $out = sprintf($this->privateLang('DATETIME_FUTURE_SECS'), ($timestamp - $now));
            elseif ($timestamp < $now + 3600)
                $out = sprintf($this->privateLang('DATETIME_FUTURE_MINS'), round(($timestamp - $now)/60));
            else
                $out = $timeToDraw->format($format);
        } elseif ($timestamp > ($now - 60)) {
            $out = sprintf($this->privateLang('DATETIME_PAST_SECS'), ($now - $timestamp));
        } elseif ($timestamp > ($now - 3600)) {
            $out = sprintf($this->privateLang('DATETIME_PAST_MINS'), round(($now - $timestamp)/60));
        } elseif ($timestamp >= $today) {
            $out = ($timestamp > ($now - 3*3600))
                ? sprintf($this->privateLang('DATETIME_PAST_HOURS'), round(($now - $timestamp)/3600))
                : sprintf($this->privateLang('DATETIME_TODAY'), $timeToDraw->format($timeFormat));
        } elseif ($timestamp >= $yesterday) {
            $out = sprintf($this->privateLang('DATETIME_YESTERDAY'), $timeToDraw->format($timeFormat));
        } else {
            $out = $timeToDraw->format($format);
        }

        if (count($translate)) {
            $out = strtr($out, $translate);
        }

        return $out;
    }

    public function sizeFormat($size, $bits = false)
    {
        $size = (int) $size;

        if (!is_array($this->bsize_tr))
        {
            $bnames = array(0 => 'BSIZE_FORM_BYTES', 1 => 'BSIZE_FORM_BITS');
            if (!isset($this->lang[$bnames[0]]))
                $this->tryAutoLoad($bnames[0]);

            $this->bsize_tr = array(0 => array(1 => 'B'), 1 => array(1 => 'b'));
            foreach ($bnames as $class => $cl_lang)
                if ($lang_data = $this->privateLang($cl_lang))
                {
                    $parts = explode('|', $lang_data);
                    $i = 1;
                    $this->bsize_tr[$class] = array();
                    foreach ($parts as $part)
                    {
                        if (!$i)
                            break;
                        $this->bsize_tr[$class][$i] = $part;
                        $i *= 1024;
                    }
                    krsort($this->bsize_tr[$class], SORT_NUMERIC);
                }
        }

        $bnames = $this->bsize_tr[(int) $bits];

        $out = $size;
        foreach ($bnames as $bsize => $name)
        {
            if ($bsize == 1)
            {
                $out = sprintf('%d %s', $size, $name);
                break;
            }
            elseif ($size >= $bsize)
            {
                $size = $size/$bsize;
                $out = sprintf('%01.2f %s', $size, $name);
                break;
            }
        }

        return $out;
    }

    public function translit($inp)
    {
        static $trans_arr = null;
        if (is_null($trans_arr))
        {
            
            if (!isset($this->lang['__TRANSLIT_FROM']) || !isset($this->lang['__TRANSLIT_TO']))
                $this->tryAutoLoad('__TRANSLIT_FROM');
            
            if (!($from = $this->privateLang('__TRANSLIT_FROM')) || 
                !($to = $this->privateLang('__TRANSLIT_TO')))
            {
                $trans_arr = false;
                return preg_replace('#[\x80-\xFF]+#', '_', $inp);
            }

            $from = explode('|', $from);
            $to = explode('|', $to);
            foreach ($from as $id => $ent)
                if (isset($to[$id]))
                    $trans_arr[$ent] = $to[$id];
        }

        if ($trans_arr)
            $inp =  strtr($inp, $trans_arr);

        return preg_replace('#[\x80-\xFF]+#', '_', $inp);
    }

    public function getAcceptLang($acc_str = '')
    {
        static $cache = array();
        if (!$acc_str)
            $acc_str = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $hash = FStr::shortHash($acc_str);
        
        if (isset($cache[$hash]))
            return $cache[$hash];
            
        $acc_str = str_replace(' ', '', $acc_str);
        $pairs = array();
        $res = array();
        
        preg_match_all('#(\w{2,3})(?:\-\w{2,3})?(?:;q=([\d\.]+))?\,*#', $acc_str, $pairs, PREG_SET_ORDER);
        foreach($pairs as $pair)
        {
            $lng = strtolower($pair[1]); 
            $q = isset($pair[2]) && $pair[2] 
                ? (float) $pair[2] 
                : 1;
            $res[$lng] = isset($res[$lng])
                ? max($res[$lng], $q)
                : $q;
        }
        arsort($res);
        
        return $cache[$hash] = $res;
    }

    // private lang function
    private function privateLang($key)
    {
        $out = null;
        if (isset($this->lang[$key]))
            $out = $this->lang[$key];
        elseif (isset($this->klang[$key]))
            $out = $this->klang[$key];        
        
        return $out;
    }
    
    // private loaders
    private function tryAutoLoad($key)
    {
        $loads = end($this->auto_loads);
        do {
            if (isset($loads[$key]))
            {
                $this->loadLanguage($loads[0].DIRECTORY_SEPARATOR.$loads[$key]);
                return isset($this->lang[$key]);
            }
        } while ($loads = prev($this->auto_loads));

        return false;
    }

    private function loadKernelDefs()
    {
        if (!is_null($this->klang))
            return true;

        $lngs = array_keys($this->getAcceptLang());
        $lngs[] = 'en';
        $lng = $file = '';
        foreach ($lngs as $lng) {
            if (file_exists($file = F::KERNEL_DIR.DIRECTORY_SEPARATOR.'krnl_'.$lng.'.lng')) {
                break;
            }
        }
        
        $cachename = self::CACHEPREFIX.'krnl_'.$lng;

        if ($Ldata = FCache::get($cachename))
        {
            $this->klang = $Ldata;
        }
        elseif ($Ldata = FMisc::loadDatafile($file, FMisc::DF_MLINE, true))
        {
            FCache::set($cachename, $Ldata);
            $this->klang = $Ldata;
        }
        else
            trigger_error('LANG: error loading kernel lang file: '.$file, E_USER_ERROR);

        $this->lang_name = $lng;
        return true;
    }
    
}
