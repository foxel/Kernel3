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

class K3_RSS_Item implements I_K3_RSS_Item
{
    /** @var string */
    protected $_title = '';
    /** @var string */
    protected $_link = '';
    /** @var string */
    protected $_description = null;
    /** @var string */
    protected $_author = null;
    /** @var string */
    protected $_guid = null;
    /** @var string */
    protected $_pubDate = null;
    /** @var string[] */
    protected $_categories = array();

    /**
     * @param array|object $data
     * @throws Exception
     */
    public function __construct($data)
    {
        if (!is_object($data)) {
            $data = (object) $data;
        }

        if (empty($data->title) || empty($data->link)) {
            throw new Exception('Link and title are required');
        }

        $vars = get_object_vars($this);
        foreach ($vars as $varName => $defaultValue)
        {
            $extVarName = ltrim($varName, '_');
            $this->$varName  = isset($data->$extVarName)
                ? $data->$extVarName
                : $defaultValue;

            if ($defaultValue !== null) {
                settype($this->$varName, gettype($defaultValue));
            }
        }

        if (empty($this->_guid)) {
            $this->_guid = md5(FStr::fullUrl($this->_link));
        }
    }

    /**
     * @return null|string
     */
    public function getAuthor()
    {
        return $this->_author;
    }

    /**
     * @return null|string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * @return string
     */
    public function getGUID()
    {
        return $this->_guid;
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->_link;
    }

    /**
     * @return string
     */
    public function getPubDate()
    {
        return $this->_pubDate;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * @return string[]
     */
    public function getCategories()
    {
        return $this->_categories;
    }


}