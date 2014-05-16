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
 * Class K3_Stream_File
 * @author Andrey F. Kupreychik
 */
class K3_Stream_File extends K3_Stream
{
    /** @var string */
    protected $_uri = '';

    /** @var resource|null */
    protected $_stream = null;

    /**
     * @param string $filename
     */
    public function __construct($filename)
    {
        $this->_uri = $filename;
    }

    /**
     * @param string $mode
     * @return bool
     */
    public function open($mode = 'rb')
    {
        return (($this->_stream = fopen($this->_uri, $this->_mode = $mode)) !== false);
    }

    /**
     * @return bool
     */
    public function close()
    {
        return fclose($this->_stream);
    }

    /**
     * @return bool
     */
    public function EOF()
    {
        return feof($this->_stream);
    }

    /**
     * @param mixed $data
     * @param int $len
     * @return int
     */
    public function read(&$data, $len)
    {
        return strlen($data = fread($this->_stream, $len));
    }

    /**
     * @param mixed $data
     * @return int
     */
    public function write($data)
    {
        return fwrite($this->_stream, $data);
    }

    /**
     * @param int $pos
     * @return bool
     */
    public function seek($pos)
    {
        return (fseek($this->_stream, $pos, SEEK_SET) === 0);
    }

    /**
     * @return int
     */
    public function size()
    {
        return filesize($this->_uri);
    }

    /**
     * @return int
     */
    public function mtime()
    {
        return filemtime($this->_uri);
    }
} 
