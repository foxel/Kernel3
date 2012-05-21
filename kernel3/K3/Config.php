<?php

class K3_Config extends FDataPool
{
    const DEFAULT_SEPARATOR = '.';

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
                            $groups[$groupKey][] =& $data[$groupKey];
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


}
