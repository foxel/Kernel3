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
 * Class K3_Stream_String
 * @author Andrey F. Kupreychik
 */
class K3_Stream_String extends K3_Stream
{
    /** @var int  */
    protected $_position = 0;
    /** @var string */
    protected $_string = '';

    /**
     * @param string $string
     */
    public function __construct($string)
    {
        $this->_string = $string;
    }

    /**
     * @param string $mode
     * @return bool
     */
    public function open($mode = 'rb')
    {
        $this->_position = 0;
        $this->_mode = $mode;
        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function EOF()
    {
        return $this->_position >= strlen($this->_string);
    }

    /**
     * @param mixed $data
     * @param int $len
     * @return int
     */
    public function read(&$data, $len)
    {
        $data = substr($this->_string, $this->_position, $len);
        $this->_position += strlen($data);
        return strlen($data);
    }

    /**
     * @param mixed $data
     * @return int
     */
    public function write($data)
    {
        $this->_string    = substr_replace($this->_string, $data, $this->_position, 0);
        $this->_position += strlen($data);
        return strlen($data);
    }

    /**
     * @param int $pos
     * @return bool
     */
    public function seek($pos)
    {
        if ($pos < strlen($this->_string) && $pos >= 0) {
            $this->_position = $pos;
            return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->_string;
    }

    /**
     * @return int
     */
    public function size()
    {
        return strlen($this->_string);
    }

    /**
     * @return int
     */
    public function mtime()
    {
        return time();
    }

}
