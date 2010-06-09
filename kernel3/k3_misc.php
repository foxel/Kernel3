<?php
/*
 * QuickFox kernel 3 'SlyFox' misc file
 * Provides some basic functionality
 * Requires PHP >= 5.1.0
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

abstract class FBaseClass
{    protected $pool = Array();

    protected function __get($name)
    {
        if (isset($this->pool[$name]))
            return $this->pool[$name];

        return null;
    }

    protected function __set($name, $val)
    {
        return $val;
    }

    protected function poolLink($names)
    {
        print 'hello';
        if (is_array($names))
            foreach ($names as $name)
                $this->pool[$name] =& $this->$name;
        if (is_string($names) && !is_numeric($names))
            $this->pool[$names] =& $this->$names;
    }
}

abstract class FEventDispatcher extends FBaseClass
{
    private $events = Array();

    private function __construct() {}

    public function addEventHandler($ev_name, $func_link)
    {
        $ev_name = strtolower($ev_name);

        if (!is_callable($func_link))
            return false;

        $this->events[$ev_name][] = $func_link;
        return true;
    }

    // first three arguments may be parsed by link
    protected function throwEvent($ev_name)
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->events[$ev_name]))
            return false;

        $args = (func_num_args() > 1)
            ? array_slice(func_get_args(), 1)
            : Array();

        $ev_arr =& $this->events[$ev_name];
        foreach ($ev_arr as $ev_link)
            call_user_func_array($ev_link, $args);
        return true;
    }

    // variant with arrayed args (references inside array may be used)
    protected function throwEventArr($ev_name, $args = Array())
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->events[$ev_name]))
            return false;

        if (!is_array($args))
            $args = Array();

        $ev_arr =& $this->events[$ev_name];
        foreach ($ev_arr as $ev_link)
            call_user_func_array($ev_link, $args);
        return true;
    }

    // special variant to parse first param by reference, all other params are passed by value
    protected function throwEventRef($ev_name, &$var)
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->events[$ev_name]))
            return false;

        $args = Array(&$var);
        if (func_num_args() > 2)
            $args = array_merge($args, array_slice(func_get_args(), 2));

        $ev_arr =& $this->events[$ev_name];
        foreach ($ev_arr as $ev_link)
            call_user_func_array($ev_link, $args);
        return true;
    }
}

final class StaticInstance
{
    private $c = null;

    public function __construct($c)
    {
        if (class_exists($c))
            $this->c = $c;
    }

    public function __call($m, $data)
    {
        if ($this->c && method_exists($this->c, $m))
            return call_user_func_array(Array($this->c, $m), $data);
        return null;
    }

    public function __get($p)
    {
        if ($this->c)
            return @constant($this->c.'::'.$p);
        return null;
    }
}

class FException extends Exception
{
}


class FMisc
{
    const DF_PLAIN = 0;
    const DF_SERIALIZED = 1;
    const DF_SLINE = 2;
    const DF_MLINE = 3;
    const DF_BLOCK = 4;

    private static $dfMasks = Array('', '', '#^\s*([\w\-\/]+)\s*=>(.*)$#m', '#^((?>\w+)):(.*?)\r?\n---#sm', '#<<\+ \'(?>(\w+))\'>>(.*?)<<- \'\\1\'>>#s');

    private function __construct() {}

    static public function obFree()
    {
        $i = ob_get_level();
        while ($i--)
            ob_end_clean();
        return (ob_get_level() == 0);
    }

    static public function mkdirRecursive($path, $chmod = null)
    {
        if (is_dir($path))
            return true;
        elseif (is_file($path))
            return false;

        if (!is_int($chmod))
            $chmod = 0755;

        $pdir = dirname($path);

        if (!is_dir($pdir))
            self::mkdirRecursive($pdir, $chmod);

        return mkdir($path, $chmod);
    }

    static public function loadDatafile($path, $format = self::DF_PLAIN, $force_upcase = false, $explode_by = '')
    {
        $indata = (file_exists($filename)) ? file_get_contents($filename) : false;

        if ($indata == false)
            return false;

        switch ($format)
        {
            case DF_SERIALIZED:
                return unserialize($indata);
            case DF_SLINE:
            case DF_MLINE:
            case DF_BLOCK:
                $matches = Array();
                $arr = Array();
                preg_match_all(self::$dfMasks[$format], $indata, $matches);
                if (is_array($matches[1]))
                {
                    $names =& $matches[1];
                    $vars  =& $matches[2];
                    foreach ($names as $num => $name)
                    {
                        if ($force_upcase)
                            $name = strtoupper($name);
                        $var = trim($vars[$num]);
                        if ($explode_by)
                            $var = explode($explode_by, $var);
                        $arr[$name] = $var;
                    }
                }

                return $arr;
            default:
                return $indata;
        }

        return null;
    }

    // checks if given timestamp is in DST period
    static public function timeDST($time, $tz = 0, $style = '')
    {
        static $styles = Array(
            'eur' => Array('+m' => 3, '+d' => 25, '+wd' => 0, '+h' => 2, '-m' => 10, '-d' => 25, '-wd' => 0, '-h' => 2),
            'usa' => Array('+m' => 3, '+d' =>  8, '+wd' => 0, '+h' => 2, '-m' => 11, '-d' =>  1, '-wd' => 0, '-h' => 2),
            );
        static $defstyle = 'eur';

        $style = strtolower($style);

        if (isset($styles[$style]))
            $DST = $styles[$style];
        else
            $DST = $styles[$defstyle];

        if (!isset($DST['gmt']))
            $time += (int) $tz*3600;

        if ($data = gmdate('n|j|w|G', $time))
        {
            $data = explode('|', $data);
            $cm = $data[0];
            if ($cm < $DST['+m'] || $cm > $DST['-m'])
                return false;
            elseif ($cm > $DST['+m'] && $cm < $DST['-m'])
                return true;
            else
            {
                if ($cm == $DST['+m'])
                {
                    $dd = $DST['+d'];
                    if (isset($DST['+wd']))
                        $dwd = $DST['+wd'];
                    $dh = $DST['+h'];
                    $bres = false;
                }
                else
                {
                    $dd = $DST['-d'];
                    if (isset($DST['-wd']))
                        $dwd = $DST['-wd'];
                    $dh = $DST['-h'];
                    $bres = true;
                }
                $cd = $data[1];


                if ($cd < $dd)
                    return $bres;
                elseif (!isset($dwd))
                {
                    if ($cd > $dd)
                        return !$bres;
                    else
                        return ($data[3] >= $dh) ? !$bres : $bres;
                }
                else
                {
                    $cvwd = $cd - $dd;
                    if ($cvwd >= 7)
                        return !$bres;

                    $cwd = $data[2];
                    $dvwd = ($dwd - $cwd + $cvwd) % 7;
                    if ($dvwd < 0)
                        $dvwd += 7;

                    if ($cvwd < $dvwd)
                        return $bres;
                    elseif ($cvwd > $dvwd)
                        return !$bres;
                    else
                        return ($data[3] >= $dh) ? !$bres : $bres;
                }
            }
        }
        else
            return false;
    }
}

?>