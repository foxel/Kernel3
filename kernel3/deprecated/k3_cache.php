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


// caching class indeed
class FCache implements I_K3_Deprecated
{
    const LIFETIME = K3_Cache::LIFETIME;
    const TEMPPREF = 'TEMP.';

    private function __construct() {}

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new K3_Cache();
        return self::$self;
    }

    static public function initCacher()
    {
        self::getInstance();
    }

    /** cache control functions **/

    /**
     * @param string $name
     * @param int $ifModifiedSince
     * @return mixed
     */
    static public function get($name, $ifModifiedSince = null)
    {
        return self::getInstance()->get($name, $ifModifiedSince);
    }

    static public function set($name, $value)
    {
        self::getInstance()->set($name, $value);
    }

    static public function drop($name)
    {
        self::getInstance()->drop($name);
    }

    static public function dropList($list)
    {
        self::getInstance()->dropList(explode(' ', $list));
        return true;
    }

    static public function flush()
    {
        self::getInstance()->flush();
        return true;
    }

    static public function clear()
    {
        self::getInstance()->clear();
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


        $filename = sys_get_temp_dir().'/'.$name;

        return (FMisc::mkdirRecursive(dirname($filename)))
            ? $filename
            : null;
    }
}


