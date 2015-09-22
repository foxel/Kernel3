<?php
/**
 * Copyright (C) 2010 - 2012, 2015 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' Caching module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');


//define('FCACHE_USE_MEMCACHED', class_exists('Memcached'));
    
// caching class indeed
class FCache
{
    const LIFETIME = 86400; // 1 day cache lifetime
    //const LIFETIME = 300; // 5 mins cache lifetime - debus needs
    const TEMPPREF = 'TEMP.';

    static private $chdata = array();
    static private $upd_cache = array();
    static private $cache_folder = '';
    static private $qTime = 0;

    private function __construct() {}

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new StaticInstance('FCache');
        return self::$self;
    }

    static public function initCacher()
    {
        self::$cache_folder = F_DATA_ROOT.DIRECTORY_SEPARATOR.'cache';
        self::$qTime = time();
        if (!is_dir(self::$cache_folder))
            FMisc::mkdirRecursive(self::$cache_folder);
        FMisc::addShutdownCallback(array(__CLASS__, 'flush'));
    }

    /** cache control functions **/

    /**
     * @param string $name
     * @param int $ifModifiedSince
     * @return mixed
     */
    static public function get($name, $ifModifiedSince = null)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, self::$chdata)) {
            self::$chdata[$name] = self::CFS_Load($name, $ifModifiedSince);
        }

        return self::$chdata[$name];
    }

    static public function set($name, $value)
    {
        $name = strtolower($name);

        self::$chdata[$name] = $value;

        self::$upd_cache[] = $name;
    }

    static public function drop($name)
    {
        $name = strtolower($name);

        self::$chdata[$name] = null;
        if (substr($name, -1) == '.')
        {
            $keys = array_keys(self::$chdata);
            foreach ($keys as $key)
                if (strpos($key, $name) === 0)
                    self::$chdata[$key] = null;
        }

        self::$upd_cache[] = $name;
    }

    static public function dropList($list)
    {
        $names = explode(' ', $list);
        if (count($names)) {
            foreach ($names as $name)
                self::drop($name);
            return true;
        }
        else
            return false;
    }

    static public function flush()
    {
        self::$upd_cache = array_unique(self::$upd_cache);

        foreach (self::$upd_cache as $name) {
            if (isset(self::$chdata[$name])) {
                self::CFS_Save($name, self::$chdata[$name]);
            } else {
                self::CFS_Drop($name);
            }
        }

        self::$chdata = array();
        self::$upd_cache = array();
        return true;
    }

    static public function clear()
    {
        self::$chdata = array();
        self::$upd_cache = array();

        self::CFS_Clear();

        return true;
    }


    // Temp files managing funtion
    static public function requestTempFile($name)
    {
        if (!$name)
            return false;

        $name = strtolower(self::TEMPPREF.$name);
        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';


        $filename = self::$cache_folder.'/'.$name;

        return (FMisc::mkdirRecursive(dirname($filename)))
            ? $filename
            : null;
    }

    //Cacher filesystem functions
    static private function CFS_Clear($folder = false)
    {
        $folder = rtrim($folder, '/');

        $folder = (strpos($folder, self::$cache_folder.'/') === 0) ? $folder : self::$cache_folder;
        $stack = array();
        if (is_dir($folder) && $dir = opendir($folder)) {
            do {
                $dirNotEmpty = true;
                while ($entry = readdir($dir))
                    if ($entry!='.' && $entry!='..') {
                        $entry = $folder.'/'.$entry;
                        if (is_file($entry)) {
                            $einfo = pathinfo($entry);
                            if (strtolower($einfo['extension'])=='chd') {
                                unlink($entry);
                            }
                        } elseif (is_dir($entry)) {
                            if ($ndir = opendir($entry)) {
                                array_push($stack, array($dir, $folder));
                                $dir = $ndir;
                                $folder = $entry;
                            }
                        } else {
                            $dirNotEmpty = true;
                        }
                    }
                closedir($dir);
                if (!$dirNotEmpty) {
                    rmdir($folder);
                }
            } while (list($dir, $folder) = array_pop($stack));
        }
    }

    static private function CFS_Load($name, $ifModifiedSince = null)
    {
        if (!$name) {
            return false;
        }

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $filename = self::$cache_folder.'/'.$name;

        if (!file_exists($filename)) {
            return null;
        }

        if (!is_int($ifModifiedSince)) {
            $ifModifiedSince = self::$qTime - self::LIFETIME;
        }

        if (filemtime($filename) < $ifModifiedSince) {
            return null;
        }

        if ($data = file_get_contents($filename)) {
            return unserialize($data);
        }

        return null;
    }

    static private function CFS_Save($name, $data)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $data = serialize($data);

        $filename = self::$cache_folder.'/'.$name;
        return FMisc::mkdirRecursive(dirname($filename)) && file_put_contents($filename, $data);
    }

    static private function CFS_Drop($name)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name);
        if (substr($name, -1) != '/')
            $name.= '.chd';

        $file = self::$cache_folder.'/'.$name;
        if (is_file($file))
            return unlink($file);
        elseif (is_dir($file))
            return self::CFS_Clear($file);
        else
            return true;
    }
}

FCache::initCacher();

