<?php

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
