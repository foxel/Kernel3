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

class K3_DOM extends DOMDocument
{
    /**
     * @return K3_DOM
     */
    public function stripXSSVulnerableCode()
    {
        $xpath = new DOMXPath($this);
        // Register the php: namespace
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        // Register PHP function
        $xpath->registerPHPFunctions('stripos');

        $forbiddenNodes = $xpath->query('//script|//iframe|//frame');
        foreach ($forbiddenNodes as $item) {
            /** @var $item DOMElement */
            $item->parentNode->removeChild($item);
        }

        // TODO: improve this list or move to allowed tags and attributes
        $forbiddenAttributes = array(
            'onload', 'onunload', 'onabort', 'onerror',
            'onblur', 'onchange', 'onfocus', 'onreset', 'onselect', 'onsubmit',
            'onkeydown', 'onkeypress', 'onkeyup',
            'onclick', 'ondblclick', 'onmousedown', 'onmouseup', 'onmousemove', 'onmouseout', 'onmouseover',
            'href' => 'javascript:'
        );
        foreach ($forbiddenAttributes as $key => $value) {
            if (!is_int($key)) {
                $selector      = "//*[php:functionString('stripos', @$key, '$value')]";
                $attributeName = $key;
            } else {
                $selector      = "//*[@$value]";
                $attributeName = $value;
            }
            $items = $xpath->query($selector);
            foreach ($items as $item) {
                /** @var $item DOMElement */
                $item->removeAttribute($attributeName);
            }
        }

        return $this;
    }

    /**
     * sets all HTML url attributes to contain full urls
     *
     * @return K3_DOM
     */
    public function fixFullUrls()
    {
        $attributes = array('href', 'action', 'src');
        $xpathSelector = array();
        foreach ($attributes as $attributeName) {
            $xpathSelector[] = "//*[@$attributeName]";
        }
        $xpath = new DOMXPath($this);

        $nodes = $xpath->query(implode('|', $xpathSelector));
        foreach ($nodes as $node) {
            /** @var $node DOMElement */
            foreach ($attributes as $attributeName) {
                if ($url = $node->getAttribute($attributeName)) {
                    $node->setAttribute($attributeName, FStr::fullUrl($url));
                }
            }
        }

        return $this;
    }
}
