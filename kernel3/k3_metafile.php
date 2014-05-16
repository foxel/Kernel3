<?php
/**
 * Copyright (C) 2011 - 2012, 2014 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' MetaFile Class
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage extra
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

/** @deprecated */
final class FMetaFileFactory implements I_K3_Deprecated
{
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FMetaFileFactory();
        return self::$self;
    }

    private function __construct() {}

    public function _Call($cluster = 1, $fillchr = "\0") { return new K3_Stream_MetaFile($cluster, $fillchr); }
    public function create($cluster = 1, $fillchr = "\0") { return new K3_Stream_MetaFile($cluster, $fillchr); }
    public function createTar($root_link = false) { return new K3_Stream_MetaTar($root_link); }
    public function save(K3_Stream_Compound $file, $filename) { return (bool) file_put_contents($filename, serialize($file)); }
    public function load($filename) { $o = unserialize(file_get_contents($filename)); return is_a($o, 'K3_Stream_Compound') ? $o : new FNullObject(); }
    public function cacheSave(K3_Stream_Compound $file, $cachename) { FCache::set($cachename, serialize($file)); }
    public function cacheLoad($cachename) { $o = unserialize(FCache::get($cachename)); return is_a($o, 'K3_Stream_Compound') ? $o : new FNullObject(); }
}

/** @deprecated */
class FMetaFile extends K3_Stream_MetaFile implements I_K3_Deprecated
{
    /**
     * @param string $filename
     * @return bool
     */
    public function toFile($filename)
    {
        return $this->saveToFile($filename);
    }
}

/** @deprecated */
class FMetaTar extends K3_Stream_MetaTar implements I_K3_Deprecated {}

