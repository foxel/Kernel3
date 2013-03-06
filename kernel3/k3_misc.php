<?php
/**
 * Copyright (C) 2010 - 2013 Andrey F. Kupreychik (Foxel)
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
{
    protected $pool = array();

    public function __get($name)
    {
        return (isset($this->pool[$name]))
            ? $this->pool[$name]
            : null;
    }

    public function __set($name, $val)
    {
        $setter = 'set'.ucfirst($name);
        if (method_exists($this, $setter)) {
            $this->$setter($val);
        }
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
    public function __construct(array &$data, $byLink = false)
    {
        if ($byLink)
            $this->pool = &$data;
        else
            $this->pool = $data;
    }

    public function offsetGet($name)
    {
        return $this->__get($name);
    }

    public function offsetSet($name, $val)
    {
        return $this->__set($name, $val);
    }

    public function offsetExists($name)
    {
        return $this->__isset($name);
    }

    public function offsetUnset($name)
    {
        return $this->__unset($name);
    }

    public function getKeys()
    {
        return array_keys($this->pool);
    }
}

/** basic event dispatcher class */
abstract class FEventDispatcher extends FBaseClass
{
    protected $_events = array();

    /**
     * @param string $ev_name
     * @param callable $func_link
     * @return bool
     */
    public function addEventHandler($ev_name, $func_link)
    {
        $ev_name = strtolower($ev_name);

        if (is_callable($func_link)) {
            $this->_events[$ev_name][] = $func_link;
            return true;
        }

        return false;
    }

    // first three arguments may be parsed by link
    protected function throwEvent($ev_name)
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->_events[$ev_name]))
            return false;

        $args = (func_num_args() > 1)
            ? array_slice(func_get_args(), 1)
            : array();

        $ev_arr =& $this->_events[$ev_name];
        foreach ($ev_arr as $ev_link)
            call_user_func_array($ev_link, $args);
        return true;
    }

    // variant with arrayed args (references inside array may be used)
    protected function throwEventArr($ev_name, $args = array())
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->_events[$ev_name]))
            return false;

        if (!is_array($args))
            $args = array();

        $ev_arr =& $this->_events[$ev_name];
        foreach ($ev_arr as $ev_link)
            call_user_func_array($ev_link, $args);
        return true;
    }

    // special variant to parse first param by reference, all other params are passed by value
    protected function throwEventRef($ev_name, &$var)
    {
        $ev_name = strtolower($ev_name);

        if (!isset($this->_events[$ev_name]))
            return false;

        $args = array(&$var);
        if (func_num_args() > 2)
            $args = array_merge($args, array_slice(func_get_args(), 2));

        $ev_arr =& $this->_events[$ev_name];
        foreach ($ev_arr as $ev_link)
            call_user_func_array($ev_link, $args);
        return true;
    }
}

/** basic data streaming abstraction class */
abstract class FDataStream extends FBaseClass
{
    abstract public function open($mode = 'rb');
    abstract public function close();
    abstract public function EOF();
    abstract public function size();
    abstract public function read(&$data, $len);
    abstract public function seek($pos);
    abstract public function write($data);

    /** @var int */
    protected $mode = 0;
    public function mode() { return $this->mode; }
    public function mtime() { return time(); }
    public function toString()
    {
        if ($this->open('rb'))
        {
            $this->seek(0);
            $data = '';
            $this->read($data, $this->size());
            $this->close();
            return $data;
        }
        else
            return '';
    }
}

/** file data streaming */
class FFileStream extends FDataStream
{
    /** @var resource|null */
    protected $stream = null;
    /** @var string */
    protected $filename = '';
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
        $this->len = strlen($this->string);
        $this->pos+= strlen($data);
        return strlen($data);
    }
    public function toString() { return $this->string; }
}

/** special class to represent class with static methods as object */
class StaticInstance
{
    protected $c = null;

    public function __construct($c)
    {
        if (class_exists($c))
            $this->c = $c;
    }

    public function __call($m, $data)
    {
        if ($this->c && method_exists($this->c, $m))
            return call_user_func_array(array($this->c, $m), $data);
        return null;
    }

    public function __get($p)
    {
        if ($this->c) {
            return method_exists($this->c, 'get')
                ? call_user_func(array($this->c, 'get'), $p)
                : @constant($this->c.'::'.$p);
        }

        return null;
    }
}

/** special null object for object actions error handling */
final class FNullObject
{
    private $message = '';

    public function __construct($var_id = 'Object') { $this->message = $var_id.' is not defined but used as object'; }

    public function __get($name) { throw new FException($this->message); }

    public function __set($name, $val) { throw new FException($this->message); }

    public function __call($name, $arguments) { throw new FException($this->message); }
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

    private static $dfMasks  = array('', '', '#^\s*([\w\-\.\/]+)\s*=>(.*)$#m', '#^((?>\w+)):(.*?)\r?\n---#sm', '#<<\+ \'(?>(\w+))\'>>(.*?)<<- \'\\1\'>>#s');
    private static $cbCode   = false;
    private static $sdCBacks = array();
    private static $inited   = false;

    // system private functions
    private function __construct() {}

    static public function initCore()
    {
        if (self::$inited) {
            return;
        }

        self::$cbCode = rand();
        register_shutdown_function(array(__CLASS__, 'phpShutdownCallback'), self::$cbCode);
        self::$inited = true;
    }

    static public function phpShutdownCallback($code)
    {
        if ($code != self::$cbCode) {
            return;
        }

        while (!is_null($cBack = array_pop(self::$sdCBacks)))
        {
            $func = array_shift($cBack);
            if (is_callable($func)) {
                call_user_func_array($func, $cBack);
            }
        }
    }

    // system functions
    static public function obFree()
    {
        $i = ob_get_level();
        while ($i--) {
            ob_end_clean();
        }

        return (ob_get_level() == 0);
    }

    /**
     * @param callable $callback
     * @param mixed $_ [optional]
     */
    static public function addShutdownCallback($callback, $_ = null)
    {
        $cBack = func_get_args();

        array_push(self::$sdCBacks, $cBack);
    }

    /**
     * @param string $path
     * @param int|string $chmod
     * @return bool
     */
    static public function mkdirRecursive($path, $chmod = null)
    {
        if (is_dir($path)) {
            return true;
        } elseif (is_file($path)) {
            return false;
        }

        if (!is_int($chmod)) {
            $chmod = 0755;
        }

        $pdir = dirname($path);

        if (!is_dir($pdir)) {
            self::mkdirRecursive($pdir, $chmod);
        }

        return mkdir($path, $chmod);
    }

    /**
     * @param string $datasource
     * @param int $format
     * @param bool $force_upcase
     * @param string $explode_by
     * @return mixed
     */
    static public function loadDatafile($datasource, $format = self::DF_PLAIN, $force_upcase = false, $explode_by = '')
    {
        $indata = ($format & self::DF_FROMSTR)
            ? $datasource
            : (file_exists($datasource) ? file_get_contents($datasource) : false);

        if ($indata == false) {
            return false;
        }

        $format &= 0xf;

        switch ($format)
        {
            case self::DF_SERIALIZED:
                return unserialize($indata);
            case self::DF_SLINE:
            case self::DF_MLINE:
            case self::DF_BLOCK:
                $matches = array();
                $arr = array();
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
    }

    /**
     * checks if given timestamp is in DST period
     * @param $timestamp
     * @param int $timeZone
     * @return bool
     * @throws FException
     */
    static public function timeDST($timestamp, $timeZone = 0)
    {
        if (is_numeric($timeZone)) {
            // compatibility
            $zones = DateTimeZone::listIdentifiers();
            while (!empty($zones)) {
                $temp = new DateTimeZone(array_shift($zones));
                if ($temp->getOffset($timestamp) == $timeZone*3600) {
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

        $info = $timeZone->getTransitions($timestamp, $timestamp);

        return (bool) $info['isdst'];
    }

    // recursively builds a linear array of references to all the scalar elements of array or object based tree
    // usefull for iterating complex data trees
    static public function linearize(&$data)
    {
        $res = array();
        if (is_scalar($data))
            $res[] =& $data;
        if (is_array($data) || is_object($data))
        {
            foreach ($data as &$val)
                $res = array_merge($res, self::linearize($val));
        }

        return $res;
    }

    /**
     * recursive iterator based on 'linearize'
     *
     * @param mixed $data
     * @param callable $func_link
     * @param bool $do_change sets a changing parse mode
     * @return bool
     */
    static public function iterate(&$data, $func_link, $do_change = false)
    {
        if (!is_callable($func_link))
            return false;

        $linear = FMisc::linearize($data);
        $args = (func_num_args() > 3)
            ? array_slice(func_get_args(), 3)
            : array();
        array_unshift($args, 0);

        if ($do_change) // selecting iteration mode here (optimization)
            foreach ($linear as &$val)
            {
                $args[0] =&$val;
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
        $resorter = array();
        foreach ($array as $key=>$val)
        {
            if (!is_array($val) || !isset($val[$field]))
                $skey = 0;
            else
                $skey = $val[$field];

            if (!isset($resorter[$skey]))
                $resorter[$skey] = array();
            $resorter[$skey][$key] = $val;
        }
        if ($rsort)
            krsort($resorter, $sort_flags);
        else
            ksort($resorter, $sort_flags);
        $array = array();
        foreach ($resorter as $valblock)
            $array+= $valblock;

        return $array;
    }

    static public function keycol(&$array, $field, $unset_key = false)
    {
        if (!is_array($array))
            return $array;
        $narray = array();
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
            $fields = array(0 => $fields);
        }

        $result = array();

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
        $narray = array();
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
        $itm_pars = $itm_tmps = array();

        foreach ($array as $item) // temporary data dividing
        {
            $itm_pars[$item[$by_id]] = $item[$by_par];
            $itm_tmps[$item[$by_id]] = $item;
        }
        unset ($array);

        $out_tree = array();
        $cur_itm = $root_id;
        $cstack = array();
        while (count($itm_pars)) // tree resorting
        {
            if ($children = array_keys($itm_pars, $cur_itm))
            {
                array_push($cstack, $cur_itm);
                $cur_itm = $children[0];
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

