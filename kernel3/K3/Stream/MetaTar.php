<?php
/**
 * Copyright (C) 2014 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Stream_MetaTar
 * @author Andrey F. Kupreychik
 */
class K3_Stream_MetaTar extends K3_Stream_Compound
{
    private $_contents = array();
    private $_rootPath = '';

    /**
     * @param string $root_link
     */
    public function __construct($root_link = null)
    {
        parent::__construct(512, "\0");
        $this->_contents = array();
        $this->_rootPath = preg_replace('#^(\\\\|/)#', '', FStr::cast($root_link ? $root_link : F_SITE_ROOT, FStr::UNIXPATH)).DIRECTORY_SEPARATOR;
    }

    /**
     * Packs a real file to archive
     * @param string $filename
     * @param string $packName
     * @param string|int $forceMode
     * @param string|int $forceFileMode
     * @param callable|null $filterCallback
     * @return bool
     */
    public function add($filename, $packName = '', $forceMode = '', $forceFileMode = '', $filterCallback = null)
    {
        if (!is_file($filename) && !is_dir($filename)) {
            return false;
        }

        if (!$packName) {
            $packName = preg_replace('#^(\\\\|/)#', '', FStr::cast($filename, FStr::UNIXPATH));
            if ($this->_rootPath) {
                $packName = preg_replace('#^('.preg_quote($this->_rootPath, '#').')#', '', $packName);
            }
        } else {
            $packName = preg_replace('#^(\\\\|/)#', '', FStr::cast($packName, FStr::UNIXPATH));
        }

        if (in_array($packName, $this->_contents)) {
            return false;
        }

        if ($dir = dirname($packName)) {
            if (!in_array($dir, $this->_contents)) {
                $dirMode = decoct(fileperms(dirname($filename)));
                $this->makeDir($dir, $dirMode);
            }
        }

        $header = array(
            'name' => $packName,
            'mode' => decoct(fileperms($filename)),
            'uid'  => fileowner($filename),
            'gid'  => filegroup($filename),
            'size' => is_file($filename) ? filesize($filename) : 0,
            'time' => filemtime($filename),
            'type' => is_file($filename) ? 0 : 5,
        );

        if (preg_match('#^[0-7]{3}$#', $forceMode)) {
            $header['mode'] = $forceMode;
        }

        $header = $this->_makeRawHeader($header);
        $this->_addItem(new K3_Stream_String($header));

        $this->_contents[] = $packName;

        if (is_file($filename)) {
            $this->_addItem(new K3_Stream_File($filename));
        } elseif ($oDir = opendir($filename)) {
            if (!is_callable($filterCallback)) {
                $filterCallback = null;
            }

            while ($dirFile = readdir($oDir)) {
                if ($dirFile != '.' && $dirFile != '..') {
                    $file = $filename.DIRECTORY_SEPARATOR.$dirFile;
                    if (!$filterCallback || call_user_func($filterCallback, $file)) {
                        $this->add($file, $packName.DIRECTORY_SEPARATOR.$dirFile, is_file($file) ? $forceFileMode : $forceMode, $forceFileMode, $filterCallback);
                    }
                }
            }

            closedir($oDir);
        }

        return true;
    }

    //
    /**
     * Packs a data string as file
     *
     * @param string $data
     * @param string $packName
     * @param string $mode
     * @return bool
     */
    public function addData($data, $packName = '', $mode = '')
    {
        static $dataPackId = 1;

        if (!strlen($data)) {
            return false;
        }

        if (!$packName) {
            $packName = 'data_'.($dataPackId++).'.bin';
        } else {
            $packName = preg_replace('#^(\\\\|/)#', '', FStr::cast($packName, FStr::UNIXPATH));
        }

        if (in_array($packName, $this->_contents)) {
            return false;
        }

        if ($dir = dirname($packName)) {
            if (!in_array($dir, $this->_contents)) {
                $this->makeDir($dir);
            }
        }

        $header = array(
            'name' => $packName,
            'mode' => '644',
            'uid'  => fileowner(__FILE__),
            'gid'  => filegroup(__FILE__),
            'size' => strlen($data),
            'time' => time(),
            'type' => 0,
        );

        if (preg_match('#^[0-7]{3}$#', $mode)) {
            $header['mode'] = $mode;
        }

        $header = $this->_makeRawHeader($header);

        $this->_addItem(new K3_Stream_String($header));
        $this->_addItem(new K3_Stream_String($data));

        $this->_contents[] = $packName;

        return True;
    }

    /**
     * Makes an empty directory inside archive
     * @param string $dirName
     * @param string $mode
     * @return bool
     */
    public function makeDir($dirName, $mode = '')
    {
        if (!$dirName) {
            return false;
        }

        $dirName = preg_replace('#^(\\\\|/)#', '', FStr::cast($dirName, FStr::UNIXPATH));

        if (in_array($dirName, $this->_contents)) {
            return false;
        }

        if ($dir = dirname($dirName)) {
            if (!in_array($dir, $this->_contents)) {
                $this->makeDir($dir, $mode);
            }
        }

        $header = array(
            'name' => $dirName,
            'mode' => '755',
            'uid'  => fileowner(__FILE__),
            'gid'  => filegroup(__FILE__),
            'size' => 0,
            'time' => time(),
            'type' => 5,
        );

        if (preg_match('#^[0-7]{3}$#', $mode)) {
            $header['mode'] = $mode;
        }

        $header = $this->_makeRawHeader($header);
        $this->_addItem(new K3_Stream_String($header));

        $this->_contents[] = $dirName;

        return true;
    }

    /**
     * @param array $header
     * @return bool|string
     */
    protected function _makeRawHeader($header)
    {
        static $headerFields = array(
            'name'     => '',
            'mode'     => '',
            'uid'      => '',
            'gid'      => '',
            'size'     => '',
            'time'     => '',
            'chsum'    => '',
            'type'     => '',
            'linkname' => '',
            'magic'    => 'ustar  ',
        );

        $header += $headerFields;
        if (!$header['name']) {
            return false;
        }

        $header['name']     = FStr::fixLength($header['name'], 100, "\0", STR_PAD_RIGHT);
        $header['mode']     = FStr::fixLength(preg_replace('#[^0-7]#', '', $header['mode']), 6, '0', STR_PAD_LEFT)." \0";
        $header['uid']      = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['uid'])), 6, '0', STR_PAD_LEFT)." \0";
        $header['gid']      = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['gid'])), 6, '0', STR_PAD_LEFT)." \0";
        $header['size']     = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['size'])), 11, '0', STR_PAD_LEFT)." ";
        $header['time']     = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['time'])), 11, '0', STR_PAD_LEFT)." ";
        $header['chsum']    = str_repeat(' ', 8);
        $header['type']     = ($header['type'] == 5) ? 5 : 0;
        $header['linkname'] = FStr::fixLength($header['linkname'], 100, "\0", STR_PAD_RIGHT);
        $header['magic']    = FStr::fixLength($header['magic'], 8, "\0", STR_PAD_RIGHT);;

        $csumm = 0;
        foreach (array_keys($headerFields) as $key) {
            $val = $header[$key];
            $len = strlen($val);
            for ($i = 0; $i < $len; ++$i) {
                $csumm += ord(substr($val, $i, 1));
            }
        }
        $header['chsum'] = FStr::fixLength(decoct($csumm), 6, '0', STR_PAD_LEFT)." \x00";

        $rawHeader = '';
        foreach (array_keys($headerFields) as $key) {
            $rawHeader .= $header[$key];
        }

        $rawHeader = FStr::fixLength($rawHeader, 512, chr(0), STR_PAD_RIGHT);

        return $rawHeader;
    }
} 
