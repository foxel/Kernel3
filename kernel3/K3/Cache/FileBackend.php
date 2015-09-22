<?php
/**
 * Copyright (C) 2015 Andrey F. Kupreychik (Foxel)
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

if (!defined('F_STARTED')) {
    die('Hacking attempt');
}

/**
 * Class K3_Cache_FileBackend
 * @author Andrey F. Kupreychik
 */
class K3_Cache_FileBackend implements I_K3_Cache_Backend
{
    const CACHE_FILE_EXTENSION = 'chd';
    /** @var string */
    protected $_cacheFolder = '';

    /**
     * @param string $cacheFolder
     */
    function __construct($cacheFolder = null)
    {
        $this->_cacheFolder = $cacheFolder ?: F_DATA_ROOT.DIRECTORY_SEPARATOR.'cache';
        FMisc::mkdirRecursive($this->_cacheFolder);
    }

    /**
     * return void
     */
    public function clear()
    {
        $this->_clearFolder('');
    }

    /**
     * @param string $name
     * @param int|null $ifModifiedSince
     * @return string
     */
    public function load($name, $ifModifiedSince)
    {
        if (!$name) {
            return false;
        }

        $filename = $this->_getFilePath($name);

        if (!is_file($filename)) {
            return null;
        }

        if (filemtime($filename) < $ifModifiedSince) {
            return null;
        }

        if ($data = file_get_contents($filename)) {
            return $data;
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $data
     */
    public function save($name, $data)
    {
        if (!$name) {
            return;
        }

        $filename = $this->_getFilePath($name);

        FMisc::mkdirRecursive(dirname($filename));
        file_put_contents($filename, $data);
    }

    /**
     * @param string $name
     */
    public function drop($name)
    {
        if (!$name) {
            return;
        }

        $file = $this->_getFilePath($name);

        if (is_file($file)) {
            unlink($file);
        } elseif (is_dir($file)) {
            $this->_clearFolder($file);
        }
    }

    /**
     * @param string $folder
     */
    public function _clearFolder($folder)
    {
        $folder = rtrim($folder, '/');

        $folder = (strpos($folder, $this->_cacheFolder.'/') === 0)
            ? $folder
            : $this->_cacheFolder;

        $stack = array();
        if (is_dir($folder) && $dir = opendir($folder)) {
            array_push($stack, array($dir, $folder, true));
        }

        while (list($dir, $folder, $dirIsEmpty) = array_pop($stack)) {
            while ($entry = readdir($dir)) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                $entry = $folder.'/'.$entry;
                if (is_file($entry)) {
                    $fileExtension = pathinfo($entry, PATHINFO_EXTENSION);
                    if (strtolower($fileExtension) == self::CACHE_FILE_EXTENSION) {
                        unlink($entry);
                    } else {
                        $dirIsEmpty = false;
                    }
                } elseif (is_dir($entry)) {
                    if ($subDir = opendir($entry)) {
                        array_push($stack, array($dir, $folder, $dirIsEmpty));

                        $dir = $subDir;
                        $folder = $entry;
                        $dirIsEmpty = true;
                    } else {
                        $dirIsEmpty = false;
                    }
                } else {
                    $dirIsEmpty = false;
                }
            }

            closedir($dir);
            if ($dirIsEmpty) {
                rmdir($folder);
            }
        }
    }

    /**
     * @param $name
     * @return string
     */
    protected function _getFilePath($name)
    {
        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name);
        if (substr($name, -1) != '/') {
            $name .= '.'.self::CACHE_FILE_EXTENSION;
        }

        $file = $this->_cacheFolder.'/'.$name;

        return $file;
    }

}
