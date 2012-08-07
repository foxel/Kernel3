<?php
/**
 * Copyright (C) 2012 Andrey F. Kupreychik (Foxel)
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

class K3_RSS_Item_Enclosure
{
    /** @var string */
    protected $_type = '';
    /** @var int */
    protected $_length = 0;
    /** @var string */
    protected $_url = '';

    /**
     * @param array|object $data
     * @throws Exception
     */
    public function __construct($data)
    {
        if (!is_object($data)) {
            $data = (object) $data;
        }

        if (empty($data->type) || empty($data->url)) {
            throw new Exception('type and url are required');
        }

        $this->_url    = (string) $data->url;
        $this->_type   = (string) $data->type;
        $this->_length = isset($data->length)
            ? (int) $data->length
            : null;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->_length;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }
}