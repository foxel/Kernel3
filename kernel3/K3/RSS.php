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

class K3_RSS
{
    /** @var SimpleXMLElement */
    protected $_xml;
    /** @var SimpleXMLElement */
    protected $_channel;

    protected static $_channelAttributes = array(
        'title'       => 'K3 RSS',
        'link'        => null,
        'description' => null,
        'generator'   => 'Kernel 3',
        'ttl'         => 120,
    );

    /**
     * @param array $params
     * @param array[]|object[] $items
     */
    public function __construct(array $params, array $items = array())
    {
        $this->_xml = new SimpleXMLElement('<?xml version="1.0" encoding="'.F::INTERNAL_ENCODING.'"?><rss version="2.0" />');
        $this->_channel = $this->_xml->addChild('channel');

        foreach (self::$_channelAttributes as $attribute => $defaultValue) {
            $this->_channel->addChild($attribute, isset($params[$attribute]) ? $params[$attribute] : $defaultValue);
        }

        if (!empty($items)) {
            $this->addItems($items);
        }
    }

    /**
     * @param array[]|object[] $items
     * @return K3_RSS
     */
    public function addItems(array $items)
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    /**
     * @param array|object|I_K3_RSS_Item $itemData
     * @return K3_RSS
     */
    public function addItem($itemData)
    {
        if (!($itemData instanceof I_K3_RSS_Item)) {
            $itemData = new K3_RSS_Item($itemData);
        }

        $item = $this->_channel->addChild('item');

        $item->addChild('title', $itemData->getTitle());
        $item->addChild('link', $itemData->getLink());
        $item->addChild('description', $itemData->getDescription());
        $item->addChild('giud', $itemData->getGUID());
        $item->addChild('pubDate', $itemData->getPubDate());
        $item->addChild('author', $itemData->getAuthor());
        foreach ($itemData->getCategories() as $category) {
            $item->addChild('category', $category);
        }
        $enclosuresData = $itemData->getEnclosures();
        foreach ($enclosuresData as $enclosureData) {
            /** @var $enclosureData I_K3_RSS_Item_Enclosure */
            $enclosure = $item->addChild('enclosure');
            $enclosure->addAttribute('url', $enclosureData->getUrl());
            $enclosure->addAttribute('type', $enclosureData->getType());
            $enclosure->addAttribute('length', $enclosureData->getLength());
        }

        return $this;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        return $this->_xml->asXML();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toXML();
    }
}