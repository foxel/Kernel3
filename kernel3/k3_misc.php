<?php
/**
 * QuickFox kernel 3 'SlyFox' misc file
 * Provides some basic functionality
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

/** base kernel class */
abstract class FBaseClass
{    protected $pool = Array();

    public function __get($name)
    {
        return (isset($this->pool[$name]))
            ? $this->pool[$name]
            : null;
    }

    public function __set($name, $val)
    {
        return $val;
    }

    public function __isset($name)
    {
        return isset($this->pool[$name]);
    }

    public function __unset($name)
    {
        return false;
    }

    public function __call($name, $arguments)
    {
         throw new FException(get_class($this).' has no '.$name.' method');
         return null;
    }

    protected function poolLink($names)
    {
        if (is_array($names))
            foreach ($names as $name)
                $this->pool[$name] =& $this->$name;
        if (is_string($names) && !is_numeric($names))
            $this->pool[$names] =& $this->$names;
    }
}

/** data pool class for quick storing read-only data */
class FDataPool extends FBaseClass implements ArrayAccess
{
    public function __construct(Array &$data, $byLink = false)
    {
        if ($byLink)
            $this->pool = &$data;
        else
            $this->pool = $data;
    }

    public function offsetGet($name)
    {
        return (isset($this->pool[$name]))
            ? $this->pool[$name]
            : null;
    }

    public function offsetSet($name, $val)
    {
        return $val;
    }

    public function offsetExists($name)
    {
        return isset($this->pool[$name]);
    }

    public function offsetUnset($name)
    {
        return false;
    }
}

/** basic event dispatcher class */
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

/** basic data streaming abstraction class */
abstract class FDataStream extends FBaseClass
{    abstract public function open($mode = 'rb');
    abstract public function close();
    abstract public function EOF();
    abstract public function size();
    abstract public function read(&$data, $len);
    abstract public function seek($pos);
    abstract public function write($data);

    protected $mode = 0;
    public function mode() { return $this->mode; }
    public function mtime() { return time(); }
}

/** file data streaming */
class FFileStream extends FDataStream
{
    private $stream = null;
    private $filename = '';
    public function __construct($fname) { $this->filename = $fname; }
    public function open($mode = 'rb') { return (($this->stream = fopen($this->filename, $this->mode = $mode)) !== false); }
    public function close() { return fclose($this->stream); }
    public function EOF() { return feof($this->stream); }
    public function size() { return filesize($this->filename); }
    public function read(&$data, $len) { return strlen($data = fread($this->stream, $len)); }
    public function seek($pos) { return (fseek($this->stream, $pos, SEEK_SET) === 0); }
    public function write($data) { return fwrite($this->stream, $data); }
    public function mtime() { return filemtime($this->filename); }
}

/** string data streaming */
class FStringStream extends FDataStream
{
    private $string = null;
    private $len = 0;
    private $pos = 0;
    public function __construct($string) { $this->len = strlen($this->string = $string); }
    public function open($mode = 'rb') { return (bool) ($this->mode = $mode); }
    public function close() { $this->pos = 0; return true; }
    public function EOF() { return ($this->pos >= $this->len-1); }
    public function size() { return $this->len; }
    public function read(&$data, $len)
    {
        $data = substr($this->string, $this->pos, $len);
        $got = strlen($data);
        $this->pos+= $got;
        return $got;
    }
    public function seek($pos)
    {
        if ($pos < 0)
            return false;
        $this->pos = $pos;
        return true;
    }
    public function write($data)
    {
        $this->string = substr_replace($this->string, $data, $this->pos, 0);
        $this->pos+= strlen($data);
        return strlen($data);
    }
}

/** special class to represent class with static methods as object */
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

/** special null object for object actions error handling */
final class FNullObject
{    private $message = '';

    public function __construct($var_id = 'Object') { $this->message = $var_id.' is not defined but used as object'; }

    protected function __get($name) { throw new FException($this->message); }

    protected function __set($name, $val) { throw new FException($this->message); }

    protected function __call($name, $arguments) { throw new FException($this->message); }
}

class FException extends Exception
{
}


/** collection of misc functions used all over the kernel */
final class FMisc
{
    const DF_PLAIN = 0;
    const DF_SERIALIZED = 1;
    const DF_SLINE = 2;
    const DF_MLINE = 3;
    const DF_BLOCK = 4;
    const DF_FROMSTR = 16; //flag

    private static $dfMasks  = Array('', '', '#^\s*([\w\-\/]+)\s*=>(.*)$#m', '#^((?>\w+)):(.*?)\r?\n---#sm', '#<<\+ \'(?>(\w+))\'>>(.*?)<<- \'\\1\'>>#s');
    private static $cbCode   = false;
    private static $sdCBacks = Array();
    private static $inited   = false;

    // system private functions
    private function __construct() {}

    static public function initCore()
    {
        if (self::$inited)
            return false;
            
        self::$cbCode = rand();
        register_shutdown_function(Array(__CLASS__, 'phpShutdownCallback'), self::$cbCode);
        self::$inited = true;
    }
    
    static public function phpShutdownCallback($code)
    {
        if ($code != self::$cbCode)
            return false;
            
        while (!is_null($cBack = array_pop(self::$sdCBacks)))
        {
            $func = array_shift($cBack);
            if (is_callable($func))
                call_user_func_array($func, $cBack);
        }
    }
    
    // system functions
    static public function obFree()
    {
        $i = ob_get_level();
        while ($i--)
            ob_end_clean();
        return (ob_get_level() == 0);
    }
 
    static public function addShutdownCallback()
    {
        $cBack = func_get_args();
        
        array_push(self::$sdCBacks, $cBack);
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

    static public function loadDatafile($datasource, $format = self::DF_PLAIN, $force_upcase = false, $explode_by = '')
    {
        $indata = ($format & self::DF_FROMSTR)
            ? $datasource
            : (file_exists($datasource) ? file_get_contents($datasource) : false);

        if ($indata == false)
            return false;

        $format &= 0xf;

        switch ($format)
        {
            case self::DF_SERIALIZED:
                return unserialize($indata);
            case self::DF_SLINE:
            case self::DF_MLINE:
            case self::DF_BLOCK:
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

        $DST = (isset($styles[$style]))
            ? $styles[$style]
            : $styles[$defstyle];

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

    // recursively builds a linear array of references to all the scalar elements of array or object based tree
    // usefull for iterating complex data trees
    static public function linearize(&$data)
    {        $res = Array();
        if (is_scalar($data))
            $res[] =& $data;
        if (is_array($data) || is_object($data))
        {
            foreach ($data as &$val)
                $res = array_merge($res, self::linearize($val));
        }

        return $res;
    }

    // recursive iterator based on 'linearize'
    // $do_change sets a changing parse mode
    static public function iterate(&$data, $func_link, $do_change = false)
    {
        if (!is_callable($func_link))
            return false;

        $linear = FMisc::linearize($data);
        $args = (func_num_args() > 3)
            ? array_slice(func_get_args(), 3)
            : Array();
        array_unshift($args, 0);

        if ($do_change) // selecting iteration mode here (optimization)
            foreach ($linear as &$val)
            {                $args[0] =&$val;
                $val = call_user_func_array($func_link, $args);
            }
        else
            foreach ($linear as &$val)
            {
                $args[0] =&$val;
                call_user_func_array($func_link, $args);
            }

        return $data;
    }
}

FMisc::initCore();


/** 2D array parsing functions collection */
class F2DArray
{
    private function __construct() {}

    static public function sort(&$array, $field, $rsort = false, $sort_flags = SORT_REGULAR)
    {
        if (!is_array($array))
            return $array;
        $resorter = Array();
        foreach ($array as $key=>$val)
        {
            if (!is_array($val) || !isset($val[$field]))
                $skey = 0;
            else
                $skey = $val[$field];

            if (!isset($resorter[$skey]))
                $resorter[$skey] = Array();
            $resorter[$skey][$key] = $val;
        }
        if ($rsort)
            krsort($resorter, $sort_flags);
        else
            ksort($resorter, $sort_flags);
        $array = Array();
        foreach ($resorter as $valblock)
            $array+= $valblock;

        return $array;
    }

    static public function keycol(&$array, $field, $unset_key = false)
    {
        if (!is_array($array))
            return $array;
        $narray = Array();
        foreach ($array as $val)
        {
            if (!is_array($val) || !isset($val[$field]))
                $skey = 0;
            else
            {
                $skey = $val[$field];
                if ($unset_key)
                    unset($val[$field]);
            }

            if (!isset($narray[$skey]))
                $narray[$skey] = $val;
        }
        $array = $narray;

        return $array;
    }

    static public function cols($array, $fields)
    {
        if (!is_array($array))
            return $array;

        $get_one = false;
        if (!is_array($fields))
        {
            $get_one = true;
            $fields = Array(0 => $fields);
        }

        $result = Array();

        foreach ($array as $key => $row)
            foreach($fields as $fkey => $field)
                $result[$fkey][$key] = (isset($row[$field]))
                    ? $row[$field]
                    : null;

        if ($get_one)
            $result = $result[0];

        return $result;
    }
    
    static public function toVector($array, $keycol, $valcol)
    {
        if (!is_array($array))
            return $array;
        $narray = Array();
        foreach ($array as $val)
        {
            if (!is_array($val))
                $skey = 0;
            else
            {
                $skey = isset($val[$keycol])
                    ? $val[$keycol]
                    : 0;
                $val = isset($val[$valcol])
                    ? $val[$valcol]
                    : null;
            }

            if (!isset($narray[$skey]))
                $narray[$skey] = $val;
        }

        return $narray;
    }

    static public function tree($array, $by_id = 'id', $by_par = 'parent', $root_id = 0, $by_lvl = 't_level')
    {
        $itm_pars = $itm_tmps = Array();

        foreach ($array as $item) // temporary data dividing
        {
            $itm_pars[$item[$by_id]] = $item[$by_par];
            $itm_tmps[$item[$by_id]] = $item;
        }
        unset ($array);

        $out_tree = Array();
        $cur_itm = $root_id;
        $cstack = Array();
        while (count($itm_pars)) // tree resorting
        {
            if ($childs = array_keys($itm_pars, $cur_itm))
            {
                array_push($cstack, $cur_itm);
                $cur_itm = $childs[0];
                $child = $itm_tmps[$cur_itm];
                $child[$by_lvl] = count($cstack); // level
                $out_tree[$cur_itm] = $child;
                unset($itm_pars[$cur_itm]);
            }
            elseif (count ($cstack) && ($st_top = array_pop($cstack)) !== null)
            {
                // getting off the branch
                $cur_itm = $st_top;
            }
            else // this will open looped parentship
            {
                reset($itm_pars);
                $key = key($itm_pars);
                $itm_tmps[$key][$by_par] = $root_id; // we'll link one item to root
                $itm_pars[$key] = $root_id;
            }
        }

        unset ($itm_pars, $itm_tmps);
        return $out_tree;
    }
}

?>
