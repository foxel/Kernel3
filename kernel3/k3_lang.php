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

    private static $self = null;

    private $lang       = Array();
    private $klang      = null;
    private $lang_name  = 'EN';
    private $LNG_loaded = Array();
    private $time_tr    = null;
    private $bsize_tr   = null;

    private $auto_loads = Array();

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FLNGData();
        return self::$self;
    }

    private function __construct()
    {
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
        else
            return $this->lang($name);
    }

    public function _Start()
    {
        $this->loadKernelDefs();
    }

    public function select($lang)
    {
        trigger_error('LANG: tried to use "select"', E_USER_WARNING );
        return false;
    }

    public function ask()
    {
        return $this->lang_name;
    }

    public function addAutoLoadDir($directory, $file_suff = '.lng')
    {
        $directory = FStr::path($directory);
        $hash = FStr::pathHash($directory);
        if (isset($this->auto_loads[$hash]))
            return true;

        $cachename = self::CACHEPREFIX.'ald-'.$hash;
        if ($aldata = FCache::get($cachename))
        {
            $this->auto_loads[$hash] = $aldata;
            F('Timer')->logEvent($directory.' lang autoloads installed (from global cache)');
        }
        else
        {
            if ($dir = opendir($directory))
            {
                $aldata = Array(0 => $directory);
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
                F('Timer')->logEvent($filename.' lang autoloads installed (from filesystem)');
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
    {        $this->loadLanguage($filename);
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
                F('Timer')->logEvent('"'.$filename.'" language loaded (from global cache)');
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
                F('Timer')->logEvent('"'.$filename.'" language file loaded (from lang file)');
                //trigger_error('LANG: error parsing '.$this->lang_name.'.'.$part.' lang file', E_USER_WARNING );
            }
            else
                trigger_error('LANG: error loading "'.$filename.'" lang file', E_USER_WARNING );

            $this->LNG_loaded[] = $hash;
        }
        return true;

    }

    public function lang($key, $params = false, $load = false)
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

        if ($params)
        {
            $params = is_array($params) ? array_values($params) : Array($params);
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

            if (!isset($this->lang[$lnames[0]]))
                $this->tryAutoLoad($lnames[0]);

            $translate = Array();
            for ($i = 1; $i<=4; $i++)
            {
                $lname = $lnames[$i];
                if ($this->privateLang($lname))
                {
                    $part = explode('|', $this->privateLang($lname));
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
                $out = sprintf($this->privateLang('DATETIME_FUTURE_SECS'), ($timestamp - $now));
            elseif ($timestamp < $now + 3600)
                $out = sprintf($this->privateLang('DATETIME_FUTURE_MINS'), round(($timestamp - $now)/60));
            else
                $out = gmdate($format, $timetodraw);
        }
        elseif ($timestamp > ($now - 60))
            $out = sprintf($this->privateLang('DATETIME_PAST_SECS'), ($now - $timestamp));
        elseif ($timestamp > ($now - 3600))
            $out = sprintf($this->privateLang('DATETIME_PAST_MINS'), round(($now - $timestamp)/60));
        elseif ($timetodraw > $today)
            $out = sprintf($this->privateLang('DATETIME_TODAY'), gmdate($time_f, $timetodraw));
        elseif ($timetodraw > $yesterday)
            $out = sprintf($this->privateLang('DATETIME_YESTERDAY'), gmdate($time_f, $timetodraw));
        else
            $out = gmdate($format, $timetodraw);

        if (count($translate))
            $out = strtr($out, $translate);

        return $out;
    }

    public function sizeFormat($size, $bits = false)
    {
        $size = (int) $size;

        if (!is_array($this->bsize_tr))
        {
            $bnames = Array(0 => 'BSIZE_FORM_BYTES', 1 => 'BSIZE_FORM_BITS');
            if (!isset($this->lang[$bnames[0]]))
                $this->tryAutoLoad($bnames[0]);

            $this->bsize_tr = Array(0 => Array(1 => 'B'), 1 => Array(1 => 'b'));
            foreach ($bnames as $class => $cl_lang)
                if ($lang_data = $this->privateLang($cl_lang))
                {
                    $parts = explode('|', $lang_data);
                    $i = 1;
                    $this->bsize_tr[$class] = Array();
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
        static $cache = Array();
        if (!$acc_str)
            $acc_str = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $hash = FStr::shortHash($acc_str);
        
        if (isset($cache[$hash]))
            return $cache[$hash];
            
        $acc_str = str_replace(' ', '', $acc_str);
        $pairs = Array();
        $res = Array();
        
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
        foreach ($lngs as $lng)
            if (file_exists($file = F::KERNEL_DIR.'krnl_'.$lng.'.lng'))
                break;

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

        return true;
    }
    
}
?>