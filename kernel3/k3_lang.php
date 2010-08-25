<?php
/*
 * QuickFox kernel 3 'SlyFox' LNG Data Interface
 * Requires PHP >= 5.1.0
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

// HTTP interface
// Outputs data to user
class FLNGData // extends FEventDispatcher
{
    const CACHEPREFIX = 'LANG.';
    const DEFLANG = 'RU';
    const COMMON = 'kernel';
    private $DATA_DIR = '';

    private static $self = null;

    private $lang       = Array();
    private $lang_name  = 'RU';
    private $LNG_loaded = Array();
    private $time_tr    = null;
    private $bsize_tr   = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FLNGData();
        return self::$self;
    }

    private function __construct()
    {
        $this->DATA_DIR = F_DATA_ROOT.'langs'.DIRECTORY_SEPARATOR;

        $this->pool = Array(
            'language' => &$this->lang_name,
            'name' => &$this->lang_name,
            );

        function FLang($key, $params = false, $load = false)
        {
            return F('LNG')->lang($key, $params, $load);
        }
    }

    private function __get($name)
    {
        if (isset($this->pool[$name]))
            return $this->pool[$name];
        elseif (isset($this->lang[$name]))
            return $this->lang[$name];

        return null;
    }

    public function _Start()
    {
        $this->loadKernelDefs();
    }

    public function select($lang)
    {
        $n_lang = preg_replace('#\W#', '_', $lang);
        if ($this->lang_name == $n_lang)
            return true;

        $this->lang_name = $n_lang;
        $this->time_tr = null;
        $this->lang = Array();
        $this->loadKernelDefs();
        if ($parts = $this->LNG_loaded)
        {
            $this->LNG_loaded = Array();
            foreach ($parts as $part)
                $this->loadLanguage($part);
        }
        return true;
    }

    public function ask()
    {
        return $this->lang_name;
    }

    public function loadLanguage($part = '')
    {
        if (!$part)
            $part = self::COMMON;
        else
        {
            $part = preg_replace('#\W#', '_', $part);
            if ($part != self::COMMON && !in_array(self::COMMON, $this->LNG_loaded))
                $this->loadLanguage();
        }

        if (!in_array($part, $this->LNG_loaded))
        {
            $cachename = self::CACHEPREFIX.$this->lang_name.'.'.$part;

            if ($Ldata = FCache::get($cachename))
            {
                $this->lang = $Ldata + $this->lang;
                F('Timer')->logEvent($this->lang_name.'.'.$part.' language loaded (from global cache)');
            }
            else
            {
                $file = $part.'.lng';
                $vdir = $this->DATA_DIR.$this->lang_name;
                $ddir = $this->DATA_DIR.self::DEFLANG;
                $odir = (file_exists($vdir.'/'.$file)) ? $vdir : $ddir;
                $file = $odir.'/'.$file;

                if (!file_exists($file))
                {
                    trigger_error('LANG: '.$this->lang_name.'.'.$part.' lang file does not exist', E_USER_NOTICE );
                }
                elseif (($Ldata = FMisc::loadDatafile($file, FMisc::DF_MLINE, true)) !== false)
                {

                    FCache::set($cachename, $Ldata);
                    $this->lang = $Ldata + $this->lang;
                    F('Timer')->logEvent($this->lang_name.'.'.$part.' language file loaded (from lang file)');
                    //trigger_error('LANG: error parsing '.$this->lang_name.'.'.$part.' lang file', E_USER_WARNING );
                }
                else
                    trigger_error('LANG: error loading '.$this->lang_name.'.'.$part.' lang file', E_USER_WARNING );

            }

            $this->LNG_loaded[] = $part;
        }
        return true;

    }

    public function lang($key, $params = false, $load = false)
    {
        $key = strtoupper($key);
        if (!$key)
            return '';

        if ($load && !in_array($load, $this->LNG_loaded))
                $this->loadLanguage($load);

        if (isset($this->lang[$key]))
        {
            $out = $this->lang[$key];
            if ($params)
            {
                $params = is_array($params) ? array_values($params) : Array($params);
                $out = FStr::smartSprintf($out, $params);
            }
            return $out;
        }
        else
            return '['.$key.']';
    }

    public function _Call($key, $params = false, $load = false)
    {
        return $this->lang($key, $params, $load);
    }

    public function langParse($data, $prefix = 'L_')
    {
        $linear = FMisc::linearize($data);
        foreach ($linear as &$val)
        {            if (preg_match('#^'.preg_quote($prefix, '#').'\w+$#D', $val))
                $val = $this->lang(substr($val, strlen($prefix)));
        }

        return $data;
    }

    public function timeFormat($timestamp = false, $format = '', $tz = false, $force_no_rels = false)
    {
        static $now, $correct, $today, $yesterday, $time_f, $last_tz = null, $no_rels;

        if (!$now)
        {
            $now = F('Timer')->qTime();
            $correct = 0;     //(int) F('Config')->Get('time_correction', 'common', 0);
            $time_f  = 'H:i'; //F('Config')->Get('def_time_format', 'visual', 'H:i');
            $no_rels = false; //(bool) F('Config')->Get('force_no_rel_time', 'common', false);
        }

        if (!count($this->LNG_loaded))
            $this->loadLanguage();

        if (!is_array($this->time_tr))
        {
            $keys = Array(
                1 => Array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                2 => Array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
                3 => Array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'),
                4 => Array('Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'),
                );
            $lnames = Array(
                1 => 'DATETIME_TR_DAYS',
                2 => 'DATETIME_TR_DAYS_SHORT',
                3 => 'DATETIME_TR_MONTHS',
                4 => 'DATETIME_TR_MONTHS_SHORT',
                );

            $translate = Array();
            for ($i = 1; $i<=4; $i++)
            {
                $lname = $lnames[$i];
                if (isset($this->lang[$lname]))
                {
                    $part = explode('|', $this->lang[$lname]);
                    $pkeys = $keys[$i];
                    if (count($part) == count($pkeys))
                        foreach ($pkeys as $id => $key)
                            $translate[$key] = $part[$id];
                }
            }
            $this->time_tr = $translate;
        }
        else
            $translate = $this->time_tr;

        if (!is_numeric($timestamp))
            $timestamp = $now;

        if (!is_numeric($tz))
            $tz = 0; //(int) F('Config')->Get('time_zone', 'common', 0);
        else
            $tz = intval($tz);

        $tzc = (3600 * $tz + 60 * $correct); // correction of GMT

        if ($last_tz !== $tz) {
            $today = $now + $tzc;
            if (FMisc::timeDST($now, $tz))
                $today+= 3600;
            $today = floor($today/86400)*86400;
            $yesterday = $today - 86400;
            $last_tz = $tz;
        }

        if (!$format)
            $format = 'd M Y H:i'; //F('Config')->Get('def_date_format', 'visual', 'd M Y H:i');

        $timetodraw = $timestamp + $tzc;
        if (FMisc::timeDST($timestamp, $tz))
            $timetodraw+= 3600;

        if ($no_rels || $force_no_rels || $timestamp == $now)
            $out = gmdate($format, $timetodraw);
        elseif ($timestamp > $now) {
            if ($timestamp < $now + 60)
                $out = sprintf($this->lang['DATETIME_FUTURE_SECS'], ($timestamp - $now));
            elseif ($timestamp < $now + 3600)
                $out = sprintf($this->lang['DATETIME_FUTURE_MINS'], round(($timestamp - $now)/60));
            else
                $out = gmdate($format, $timetodraw);
        }
        elseif ($timestamp > ($now - 60))
            $out = sprintf($this->lang['DATETIME_PAST_SECS'], ($now - $timestamp));
        elseif ($timestamp > ($now - 3600))
            $out = sprintf($this->lang['DATETIME_PAST_MINS'], round(($now - $timestamp)/60));
        elseif ($timetodraw > $today)
            $out = sprintf($this->lang['DATETIME_TODAY'], gmdate($time_f, $timetodraw));
        elseif ($timetodraw > $yesterday)
            $out = sprintf($this->lang['DATETIME_YESTERDAY'], gmdate($time_f, $timetodraw));
        else
            $out = gmdate($format, $timetodraw);

        if (count($translate))
            $out = strtr($out, $translate);

        return $out;
    }

    public function sizeFormat($size, $bits = false)
    {
        if (!count($this->LNG_loaded))
            $this->loadLanguage();

        $size = (int) $size;

        if (!is_array($this->bsize_tr))
        {
            $bnames = Array(0 => 'BSIZE_FORM_BYTES', 1 => 'BSIZE_FORM_BITS');

            $this->bsize_tr = Array(0 => Array(1 => 'B'), 1 => Array(1 => 'b'));
            foreach ($bnames as $class => $cl_lang)
                if (isset($this->lang[$cl_lang]) && $this->lang[$cl_lang])
                {
                    $parts = explode('|', $this->lang[$cl_lang]);
                    $i = 1;
                    $this->bsize_tr[$class] = Array();
                    foreach ($parts as $part)
                    {
                        if (!$i)
                            break;
                        $this->bsize_tr[$class][$i] = $part;
                        $i *= 1024;
                    }
                    krsort($this->bsize_tr[$class]);
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
            {
                $trans_arr = false;
                return preg_replace('#[\x80-\xFF]+#', '_', $inp);
            }

            $from = explode('|', $this->lang['__TRANSLIT_FROM']);
            $to = explode('|', $this->lang['__TRANSLIT_TO']);
            foreach ($from as $id => $ent)
                if (isset($to[$id]))
                    $trans_arr[$ent] = $to[$id];
        }

        if ($trans_arr)
            $inp =  strtr($inp, $trans_arr);

        return preg_replace('#[\x80-\xFF]+#', '_', $inp);
    }

    private function loadKernelDefs()
    {
        static $K_lang = null;

        if (!is_null($K_lang))
        {
            $this->lang = $K_lang + $this->lang;
            return true;
        }

        $file = F::KERNEL_DIR.'krnl_def.lng';

        $cachename = self::CACHEPREFIX.'krnl_defs';

        if ($Ldata = FCache::get($cachename))
        {
            $this->lang = $Ldata + $this->lang;
            $K_lang = $Ldata;
        }
        elseif ($Ldata = FMisc::loadDatafile($file, FMisc::DF_MLINE, true))
        {
            FCache::set($cachename, $Ldata);
            $this->lang = $Ldata + $this->lang;
            $K_lang = $Ldata;
        }
        else
            trigger_error('LANG: error loading kernel lang file: '.$file, E_USER_ERROR);

        return true;
    }
}
?>