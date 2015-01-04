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
        if (isset($this->pool[$name])) {
            return $this->pool[$name];
        } elseif (method_exists($this, $getter = 'get'.ucfirst($name))) {
            return $this->$getter();
        }

        return null;
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
    /**
     * @param string|array $message
     * @param int $code
     * @param Exception $previous
     */
    public function __construct($message = "", $code = 0, Exception $previous = null)
    {
        if (is_array($message)) {
            $msgTemplate = (string) array_shift($message);
            $message = vsprintf($msgTemplate, $message);
        }

        parent::__construct($message, $code, $previous);
    }

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
    const EXTENDS_KEYWORD = '@extends';

    private static $dfMasks  = array(
        self::DF_PLAIN => '',
        self::DF_SERIALIZED => '',
        self::DF_SLINE => '#^\s*(@?[\w\-\.\/]+)\s*=>(.*)$#m',
        self::DF_MLINE => '#^((?>@?\w+)):(.*?)\r?\n---#sm',
        self::DF_BLOCK => '#<<\+ \'(?>(@?\w+))\'>>(.*?)<<- \'\\1\'>>#s',
    );
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
     * @param string $dataSource
     * @param int $format
     * @param bool $forceUpCase
     * @param string $explodeBy
     * @return mixed
     */
    static public function loadDatafile($dataSource, $format = self::DF_PLAIN, $forceUpCase = false, $explodeBy = '')
    {
        $dataFileName = null;

        if ($format & self::DF_FROMSTR) {
            $inData = $dataSource;
        } else {
            $dataFileName = $dataSource;
            if (!file_exists($dataSource)) {
                return false;
            }
            $inData = file_get_contents($dataFileName);
        }

        if ($inData == false) {
            return false;
        }

        $format &= 0xf;

        switch ($format)
        {
            case self::DF_SERIALIZED:
                return unserialize($inData);
            case self::DF_SLINE:
            case self::DF_MLINE:
            case self::DF_BLOCK:
                $matches = array();
                $arr = array();
                preg_match_all(self::$dfMasks[$format], $inData, $matches);
                if (is_array($matches[1]))
                {
                    $names =& $matches[1];
                    $vars  =& $matches[2];
                    foreach ($names as $num => $name)
                    {
                        if ($forceUpCase) {
                            $name = strtoupper($name);
                        }
                        $var = trim($vars[$num]);
                        if ($explodeBy) {
                            $var = explode($explodeBy, $var);
                        }
                        $arr[$name] = $var;
                    }
                }

                $extendKey = self::EXTENDS_KEYWORD;
                if ($forceUpCase) {
                    $extendKey = strtoupper($extendKey);
                }

                if (isset($arr[$extendKey])) {
                    $baseFileName = K3_Util_String::replaceConstants(trim($arr[$extendKey]));
                    unset($arr[$extendKey]);
                    if ($dataFileName) {
                        // TODO: export to separate function
                        $cwd = getcwd();
                        chdir(dirname($dataFileName));
                        $baseArr = static::loadDatafile($baseFileName, $format, $forceUpCase, $explodeBy);
                        $arr = array_merge($baseArr, $arr);
                        chdir($cwd);
                    }
                }

                return $arr;
            default:
                return $inData;
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

    /**
     * simple scalar compare
     *
     * @param mixed $a
     * @param mixed $b
     * @return int
     */
    static public function scalarCmp($a, $b)
    {
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
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

