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
 * Interface I_K3_Stream
 */
interface I_K3_Stream
{
    /**
     * @param string $mode
     * @return boolean
     */
    public function open($mode = 'rb');

    /**
     * @return boolean
     */
    public function close();

    /**
     * @return boolean
     */
    public function EOF();

    /**
     * @return int
     */
    public function size();

    /**
     * @param mixed $data
     * @param int $len
     * @return int data amount
     */
    public function read(&$data, $len);

    /**
     * @param int $pos
     * @return boolean
     */
    public function seek($pos);

    /**
     * @param mixed $data
     * @return int data amount
     */
    public function write($data);

    /**
     * @return int
     */
    public function mode();

    /**
     * @return int UNIX timestamp
     */
    public function mtime();

    /**
     * @return string
     */
    public function toString();
} 
