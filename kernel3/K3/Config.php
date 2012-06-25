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

class K3_Config extends FDataPool implements Countable, Iterator
{
    const DEFAULT_SEPARATOR = '.';

    protected $_itemIndex = 0;
    protected $_indexShift = 0;

    /**
     * @param array $data
     * @param string $separator
     */
    public function __construct(array $data, $separator = self::DEFAULT_SEPARATOR)
    {
        if ($separator) {
            $dataKeys = array_keys($data);
            $groups = array();
            $lastGroupKey = null;
            foreach ($dataKeys as $fullKey)
            {
                if (strpos($fullKey, $separator) === false) {
                    continue;
                }

                list($groupKey, $itemKey) = explode($separator, $fullKey, 2);
                if (strlen($itemKey)) {
                    if (!isset($groups[$groupKey])) {
                        $groups[$groupKey] = array();
                        if (isset($data[$groupKey])) {
                            $groups[$groupKey][0] =& $data[$groupKey];
                            unset($data[$groupKey]);
                        }
                    }
                    $groups[$groupKey][$itemKey] =& $data[$fullKey];
                    unset($data[$fullKey]);
                }
            }
            foreach ($groups as $groupKey => $group) {
                $data[$groupKey] = new K3_Config($group, $separator);
            }
            parent::__construct($data);
        }
        else {
            parent::__construct($data);
        }

        $keys = $this->getKeys();
        if ($keys[0] === 0) {
            $this->_indexShift = 1;
        }
    }

    /**
     * @param bool $recursive
     * @return array
     */
    function toArray($recursive = true)
    {
        $array = array();
        foreach ($this->pool as $key => $value) {
            if ($value instanceof K3_Config) {
                /** @var $value K3_Config */
                $value = $recursive
                    ? $value->toArray($recursive)
                    : $value->__toString();
            }
            $array[$key] = $value;
        }
        return $array;
    }

    /**
     * @return null|string
     */
    function value()
    {
        return $this->__get(0);
    }

    /**
     * @return null|string
     */
    function __toString()
    {
        return $this->value();
    }

    /**
     * @param int $index
     * @return mixed|null
     */
    protected function _checkKey($index)
    {
        $keys = $this->getKeys();

        if (isset($keys[$index + $this->_indexShift])) {
            return $keys[$index + $this->_indexShift];
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        if (($key = $this->_checkKey($this->_itemIndex)) !== null) {
            return $this->__get($key);
        }
        return null;
    }

    /**
     * @return void
     */
    public function next()
    {
        $this->_itemIndex++;
    }

    /**
     * @return mixed|null
     */
    public function key()
    {
        return $this->_checkKey($this->_itemIndex);
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return ($this->_checkKey($this->_itemIndex) !== null);
    }

    /**
     * @return void
     */
    public function rewind()
    {
        $this->_itemIndex = 0;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->getKeys()) - $this->_indexShift;
    }
}
