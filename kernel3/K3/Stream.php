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
 * Class K3_Stream
 * @author Andrey F. Kupreychik
 */
abstract class K3_Stream implements I_K3_Stream
{
    /** @var string */
    protected $_mode = 'rb';

    /**
     * @return string
     */
    public function mode()
    {
        return $this->_mode;
    }


    /**
     * @param string $mode
     * @return bool
     */
    abstract public function open($mode = 'rb');

    /**
     * @return bool
     */
    abstract public function close();

    /**
     * @return bool
     */
    abstract public function EOF();

    /**
     * @param mixed $data
     * @param int $len
     * @return int
     */
    abstract public function read(&$data, $len);

    /**
     * @param mixed $data
     * @return int
     */
    abstract public function write($data);

    /**
     * @param int $pos
     * @return bool
     */
    abstract public function seek($pos);

    /**
     * @return int
     */
    abstract public function size();

    /**
     * @return int
     */
    abstract public function mtime();

    /**
     * @return string
     */
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
        return '';
    }

}
