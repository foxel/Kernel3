<?php
/**
 * Copyright (C) 2012, 2014 Andrey F. Kupreychik (Foxel)
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
    /** @var DOMDocument */
    protected $_xml;
    /** @var DOMElement */
    protected $_channel;
    /** @var DOMElement */
    protected $_currentItem;
    /** @var K3_Environment */
    protected $_env;

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
     * @param K3_Environment $env
     */
    public function __construct(array $params, array $items = array(), K3_Environment $env = null)
    {
        $this->_env = $env
            ? $env
            : F()->appEnv;

        $this->_xml = new K3_DOM('1.0', F::INTERNAL_ENCODING);
        $this->_xml->appendChild($rssNode = $this->_xml->createElement('rss'));
        $rssNode->setAttribute('version', '2.0');
        $rssNode->appendChild($this->_channel = $this->_xml->createElement('channel'));

        if (isset($params['link'])) {
            $params['link'] = K3_Util_Url::fullUrl($params['link'], $this->_env);
        }

        foreach (self::$_channelAttributes as $attribute => $defaultValue) {
            $this->_channel->appendChild($this->_xml->createElement($attribute, isset($params[$attribute]) ? $params[$attribute] : $defaultValue));
        }

        if (isset($params['feedLink']) && $feedLink = $params['feedLink']) {
            $this->_channel->appendChild($linkNode = $this->_xml->createElementNS('http://www.w3.org/2005/Atom', 'atom:link'));
            $linkNode->setAttribute('href', K3_Util_Url::fullUrl($feedLink, $this->_env));
            $linkNode->setAttribute('rel', 'self');
            $linkNode->setAttribute('type', 'application/rss+xml');
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

        $this->_currentItem = $item = $this->_channel->appendChild($this->_xml->createElement('item'));

        $item->appendChild($this->_xml->createElement('title', $itemData->getTitle()));
        $item->appendChild($this->_xml->createElement('link', K3_Util_Url::fullUrl($itemData->getLink(), $this->_env)));
        $item->appendChild($description = $this->_xml->createElement('description'));
        $description->appendChild($this->_xml->createCDATASection($itemData->getDescription()));
        $item->appendChild($this->_xml->createElement('pubDate', $itemData->getPubDate()));
        $item->appendChild($this->_xml->createElement('author', $itemData->getAuthor()));

        $guid = $itemData->getGUID();
        $item->appendChild($guidNode = $this->_xml->createElement('guid', $guid));
        if (K3_String::isUrl($guid) !== 1) {
            $guidNode->setAttribute('isPermaLink', 'false');
        }

        foreach ($itemData->getCategories() as $category) {
            $item->appendChild($this->_xml->createElement('category', $category));
        }

        $enclosuresData = $itemData->getEnclosures();
        foreach ($enclosuresData as $enclosureData) {
            /** @var $enclosureData I_K3_RSS_Item_Enclosure */
            $item->appendChild($enclosure = $this->_xml->createElement('enclosure'));
            $enclosure->setAttribute('url', K3_Util_Url::fullUrl($enclosureData->getUrl(), $this->_env));
            $enclosure->setAttribute('type', $enclosureData->getType());
            $enclosure->setAttribute('length', $enclosureData->getLength());
        }

        return $this;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        return $this->_xml->saveXML();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toXML();
    }
}
