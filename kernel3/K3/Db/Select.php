<?php
/**
 * Copyright (C) 2015 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Db_Select
 * @author Andrey F. Kupreychik
 */
class K3_Db_Select
{
    const JOIN_INNER = 0;
    const JOIN_LEFT  = 1;
    const JOIN_RIGNT = 2;
    const JOIN_CROSS = 3;

    const FETCH_ALL = 0;
    const FETCH_ONE = 1;

    /** @var array */
    protected $_tables = array();
    /** @var array */
    protected $_fields = array();
    /** @var array */
    protected $_where = array();
    /** @var array */
    protected $_joins = array();
    /** @var array */
    protected $_joints = array();
    /** @var array */
    protected $_order = array();
    /** @var array */
    protected $_group = array();
    /** @var array */
    protected $_limit = array();
    /** @var int */
    protected $_flags = 0;

    /**
     * @var K3_Db_Abstract
     */
    protected $_db = null;

    /**
     * @param K3_Db_Abstract $db
     * @param string|FDBSelect $tableName
     * @param string|bool $tableAlias - false for auto
     * @param array|null $fields
     */
    public function __construct(K3_Db_Abstract $db = null, $tableName, $tableAlias = false, array $fields = null)
    {
        if (!$tableAlias || !is_string($tableAlias)) {
            $tableAlias = is_string($tableName) && K3_String::isWord($tableName)
                ? $tableName
                : 't0';
        }

        $this->_db = $db;

        $this->_tables[$tableAlias] = $tableName instanceof K3_Db_Select
            ? $tableName
            : (string)$tableName;

        if (!is_null($fields)) {
            $this->columns($fields, $tableAlias);
        } else {
            $this->_fields[] = array($tableAlias, '*');
        }

        return $this;
    }

    /**
     * @return FDBSelect
     */
    public function distinct()
    {
        $this->_flags |= K3_Db::SQL_DISTINCT;

        return $this;
    }

    /**
     * @return FDBSelect
     */
    public function calculateRows()
    {
        $this->_flags |= K3_Db::SQL_CALC_ROWS;

        return $this;
    }

    /**
     * @param string $tableName
     * @param string|array $joinOn
     * @param string|bool $tableAlias - false for auto
     * @param array|null $fields
     * @param int $joinType
     * @return FDBSelect
     */
    public function join($tableName, $joinOn, $tableAlias = false, array $fields = null, $joinType = self::JOIN_INNER)
    {
        if (!$tableAlias || !is_string($tableAlias)) {
            $tableAlias = isset($this->_tables[$tableName])
                ? 't'.count($this->_tables)
                : $tableName;
        }

        $this->_tables[$tableAlias] = (string)$tableName;
        $this->_joins[$tableAlias]  = array();
        $this->_joints[$tableAlias] = (int)$joinType;

        if (is_array($joinOn) && count($joinOn)) {
            foreach ($joinOn as $field => &$toField) {
                if (K3_String::isWord($field)) {
                    if (is_string($toField)) {
                        $refTableAlias = $this->_determineTableAliasWithColumn($toField);
                        if (K3_String::isWord($refTableAlias)) {
                            $this->_joins[$tableAlias][$field] = array($refTableAlias, $toField);
                        } else {
                            $this->_joins[$tableAlias][$field] = $toField;
                        }
                    } else {
                        $this->_joins[$tableAlias][$field] = $toField;
                    }
                } else {
                    $this->_joins[$tableAlias][] = $toField;
                }
            }
        } elseif (is_string($joinOn)) {
            $this->_joins[$tableAlias] = $joinOn;
        }

        if (!is_null($fields)) {
            $this->columns($fields, $tableAlias);
        } else {
            $this->_fields[] = array($tableAlias, '*');
        }

        return $this;
    }

    /**
     * @param string $tableName
     * @param string|array $joinOn
     * @param string|bool $tableAlias - false for auto
     * @param array|null $fields
     * @return FDBSelect
     */
    public function joinLeft($tableName, $joinOn, $tableAlias = false, array $fields = null)
    {
        return $this->join($tableName, $joinOn, $tableAlias, $fields, self::JOIN_LEFT);
    }

    /**
     * @param string|FDBSelect $column
     * @param string|bool $alias
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @return FDBSelect
     */
    public function column($column, $alias = false, $tableAlias = false)
    {
        $this->_determineTableAliasWithColumn($column, $tableAlias);

        if ($column instanceof self) {
            $expr = $column;
        } elseif ($column == '*' || K3_String::isWord($column)) {
            $expr = array($tableAlias, $column);
            if (!K3_String::isWord($alias)) {
                $alias = $column;
            }
        } else {
            $expr = (string)$column;
        }

        if (K3_String::isWord($alias)) {
            $this->_fields[$alias] = $expr;
        } else {
            $this->_fields[] = $expr;
        }

        return $this;
    }

    /**
     * @param array $columns
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @return FDBSelect
     */
    public function columns(array $columns, $tableAlias = false)
    {
        foreach ($columns as $key => &$val) {
            $this->column($val, is_string($key) ? $key : false, $tableAlias);
        }

        return $this;
    }

    /**
     * @param string|array $where
     * @param mixed $value
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @param bool $whereOr
     * @return FDBSelect
     */
    public function where($where, $value = null, $tableAlias = false, $whereOr = false)
    {
        if (is_array($where)) {
            foreach ($where as $key => &$value) {
                if (is_string($key)) {
                    $this->where($key, $value, $tableAlias, $whereOr);
                } else {
                    $this->where($value, false, $tableAlias, $whereOr);
                }
            }

            return $this;
        }

        $columnGiven = K3_String::isWord($where);
        $this->_determineTableAliasWithColumn($where, $tableAlias);

        if ($columnGiven || K3_String::isWord($where)) { // column given
            $this->_where[] = array($tableAlias, $where, $value, (boolean)$whereOr);
        } elseif (preg_match('#(?<!\w|\\\\)\?#', $where)) {
            $this->_where[] = array($where, $value, (boolean)$whereOr);
        } else {
            $this->_where[] = array((string)$where, (boolean)$whereOr);
        }

        return $this;
    }

    /**
     * @param string|array $where
     * @param mixed $value
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @return FDBSelect
     */
    public function whereOr($where, $value = null, $tableAlias = false)
    {
        return $this->where($where, $value, $tableAlias, true);
    }

    /**
     * @param string $order
     * @param bool $desc
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @return FDBSelect
     */
    public function order($order, $desc = null, $tableAlias = false)
    {
        $this->_determineTableAliasWithColumn($order, $tableAlias);
        $columnGiven = K3_String::isWord($order);

        if ($columnGiven || !is_null($desc)) { // column given
            $this->_order[] = array($tableAlias, $order, (boolean)$desc);
        } else {
            $this->_order[] = (string)$order;
        }

        return $this;
    }

    /**
     * @param string $group
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @return FDBSelect
     */
    public function group($group, $tableAlias = false)
    {
        $this->_determineTableAliasWithColumn($group, $tableAlias);

        if (K3_String::isWord($group)) // column given
        {
            $this->_group[] = array($tableAlias, $group);
        } else {
            $this->_group[] = (string)$group;
        }

        return $this;
    }

    /**
     * @param int $count
     * @param int|bool $offset
     * @return FDBSelect
     */
    public function limit($count, $offset = false)
    {
        $this->_limit = array((int)$count, $offset ? (int)$offset : null);

        return $this;
    }

    /**
     * @param int $add_params
     * @return string
     */
    public function toString($add_params = 0)
    {
        return $this->_db->parseDBSelect($this, $this->_flags | (int)$add_params);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'tables' => $this->_tables,
            'fields' => $this->_fields,
            'where'  => $this->_where,
            'joins'  => $this->_joins,
            'joints' => $this->_joints,
            'order'  => $this->_order,
            'group'  => $this->_group,
            'limit'  => $this->_limit,
            'flags'  => $this->_flags,
        );
    }

    /**
     * @return K3_Db_Abstract
     */
    public function getDB()
    {
        return $this->_db;
    }

    /**
     * @param int $fetch_mode
     * @param int $add_params
     * @return array|mixed|null
     */
    public function fetch($fetch_mode = self::FETCH_ALL, $add_params = 0)
    {
        if ($fetch_mode == self::FETCH_ALL) {
            $add_params |= K3_Db::SQL_SELECT_ALL;
        }

        return $this->_db->execDBSelect($this, $this->_flags | (int)$add_params);
    }

    /**
     * @param int $add_params
     * @return array|mixed|null
     */
    public function fetchAll($add_params = 0)
    {
        return $this->fetch(self::FETCH_ALL, $add_params);
    }

    /**
     * @param int $add_params
     * @return array|mixed|null
     */
    public function fetchOne($add_params = 0)
    {
        return $this->fetch(self::FETCH_ONE, $add_params);
    }

    /**
     * @param string $name
     * @param int $add_params
     * @return bool
     */
    public function createView($name, $add_params = 0)
    {
        return $this->_db->createView($name, $this, $this->_flags | (int)$add_params);
    }

    /**
     * @param int $add_params
     * @return FDBSelect
     */
    public function addFlags($add_params)
    {
        $this->_flags |= (int)$add_params;

        return $this;
    }

    /**
     * @param string $field
     * @param string|bool|null $tableAlias - false for autodetection, null to force no tableAlias
     * @return string|null
     */
    protected function _determineTableAliasWithColumn(&$field, &$tableAlias = false)
    {
        if (count($fParts = explode('.', $field)) == 2 // two parts separated by '.'
            && (K3_String::isWord($fParts[1]) || $fParts[1] == '*') // second part is field name
            && K3_String::isWord($fParts[0])
        ) // first part is table alias name
        {
            $field      = $fParts[1];
            $tableAlias = $fParts[0];
        } elseif (is_null($tableAlias)) {
            return ($tableAlias = null);
        } elseif (!$tableAlias && isset($this->_fields[$field])) {
            if (is_array($this->_fields[$field])) {
                list($tableAlias, $field) = $this->_fields[$field];

                return $tableAlias;
            } elseif (is_string($this->_fields[$field])) {
                $field = $this->_fields[$field];

                return $tableAlias;
            }
        } elseif (K3_String::isWord($field)) {
            if (!$tableAlias) {
                list($tableAlias) = array_keys($this->_tables);
            } else {
                $tableAlias = (string)$tableAlias;
            }
        }

        return $tableAlias;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return array('tables', 'fields', 'where', 'joins', 'joints', 'order', 'group', 'limit', 'flags');
    }
}
