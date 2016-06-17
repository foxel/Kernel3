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
 * Class K3_Db_MySQL
 * @author Andrey F. Kupreychik
 */
class K3_Db_MySQL extends K3_Db_Abstract
{
    /** html charset transformation table TODO: filling */
    static $charSets = array();

    /**
     * @param array $dataSource
     * @return string
     */
    protected function _prepareConnectionString(array $dataSource)
    {
        $parts = array();
        if (!is_array($dataSource)) {
            return false;
        }
        foreach ($dataSource as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return 'mysql:'.implode(';', $parts);
    }

    /**
     * @return void
     */
    protected function _initializeConnection()
    {
        // html charset names to SQL ones converting
        $charset = strtr(strtolower(F::INTERNAL_ENCODING), array('-' => '', 'windows' => 'cp'));
        $charset = (isset(self::$charSets[$charset]))
            ? self::$charSets[$charset]
            : $charset;

        $this->exec('set names '.$charset);
        $this->exec('set time_zone = \'+0:00\'');
    }

    /**
     * @param string $name
     * @param K3_Db_Select|string $select
     * @param int $flags
     * @return string
     */
    protected function _prepareCreateViewQuery($name, $select, $flags = 0)
    {
        if ($select instanceof K3_Db_Select) {
            $select = $select->toString();
        }

        if (!is_string($select)) {
            return false;
        }

        $query = 'CREATE ';
        if ($flags & K3_Db::SQL_REPLACE) {
            $query .= 'OR REPLACE ';
        }
        $query .= 'VIEW `'.$name.'` AS ('.$select.')';

        return $query;
    }

    /**
     * @return string
     */
    protected function _prepareCalcRowsQuery()
    {
        return 'SELECT FOUND_ROWS();';
    }

    /**
     * @param string $table
     * @param string|array $fields
     * @param string|array $where
     * @param string|array $other
     * @param int $flags
     * @return string
     */
    protected function _prepareSimpleSelectQuery($table, $fields, $where, $other, $flags)
    {
        if ($where = $this->_parseWhere($where, $flags)) {
            $where = 'WHERE '.$where;
        }
        $other = $this->_parseOther($other, $flags);

        $query = 'SELECT ';
        if ($flags & K3_Db::SQL_DISTINCT) {
            $query .= 'DISTINCT ';
        }
        if ($flags & K3_Db::SQL_CALC_ROWS) {
            $query .= 'SQL_CALC_FOUND_ROWS ';
        }

        if (is_array($fields)) {
            if (count($fields)) {
                foreach ($fields as $id => $name) {
                    $fields[$id] = '`'.$name.'`';
                }
                $fields = implode(', ', $fields).' ';
            } else {
                $fields = '*';
            }
        }

        if (empty($fields)) {
            $fields = '*';
        }

        $query .= $fields.' ';

        if (!($flags & K3_Db::SQL_NO_PREFIX)) {
            $table = $this->_tablePrefix.$table;
        }

        $query .= 'FROM `'.$table.'` '.$where.' '.strval($other);

        return $query;
    }

    /**
     * complex multi-table select
     * $queries = array (
     *     'table1_name' => array('fields' => '*', 'where' => '...', 'prefix' => 't1_'),
     *     'table2_name' => array('fields' => '*', 'where' => '...', 'prefix' => 't2_', 'join' => array('[table2_filed_name]' => '[main_table_field_name]', ...) ),
     *     ...
     *     )
     * @param array $queries
     * @param string $other
     * @param int $flags
     * @return string
     */
    protected function _prepareMultiTableSelectQuery($queries, $other, $flags)
    {
        $qc_fields = $qc_where = $qc_order = array();
        $qc_tables = '';

        if (!is_array($queries)) {
            return '';
        }

        $tableIndex   = 0;
        $inheritFlags = $flags & (K3_Db::SQL_NO_ESCAPE);

        foreach ($queries as $table => $params) {
            if (!($flags & K3_Db::SQL_NO_PREFIX)) {
                $table = $this->_tablePrefix.$table;
            }

            $tableAlias = 't'.$tableIndex;

            if ($tableIndex > 0) {
                $joinBy = array();
                if (isset($params['join']) && is_array($params['join']) && count($params['join'])) {
                    $joinTo = 0;
                    if (isset($params['join_to']) && ($joinTo = (int)$params['join_to'])) {
                        $joinTo = min(max(0, $joinTo), $tableIndex - 1);
                    }

                    foreach ($params['join'] as $joinField => $joinToField) {
                        $joinBy[] = $tableAlias.'.`'.$joinField.'` = t'.$joinTo.'.`'.$joinToField.'`';
                    }
                }

                if (($params['flags'] | $inheritFlags) & K3_Db::SQL_JOIN_LEFT && count($joinBy)) {
                    $qc_tables .= ' LEFT JOIN `'.$table.'` '.$tableAlias;
                } else {
                    $qc_tables .= ' JOIN `'.$table.'` '.$tableAlias;
                }

                if (count($joinBy)) {
                    $qc_tables .= ' ON ('.implode(', ', $joinBy).')';
                }
            } else {
                $qc_tables = '`'.$table.'` '.$tableAlias;
            }

            if (isset($params['fields'])) {
                $fields = $params['fields'];
                if (is_array($fields)) {
                    $prefix = (isset($params['prefix']))
                        ? preg_replace('#[^A-Za-z_]#', '', $params['prefix'])
                        : false;
                    if (count($fields)) {
                        foreach ($fields as $key => $value) {
                            if (is_int($key)) {
                                $field = $tableAlias.'.`'.$value.'`';
                                if ($prefix) {
                                    $field .= ' AS `'.$prefix.$value.'`';
                                }
                            } else {
                                $field = $tableAlias.'.`'.$key.'` AS `'.$value.'`';
                            }

                            $qc_fields[] = $field;
                        }
                    } else {
                        $qc_fields[] = $tableAlias.'.*';
                    }
                } elseif ($fields == '*') {
                    $qc_fields[] = $tableAlias.'.*';
                } elseif ($fields) {
                    $qc_fields[] = $tableAlias.'.`'.$fields.'`';
                }
            }

            if (isset($params['order'])) {
                $order = $params['order'];
                if (is_array($order)) {
                    foreach ($order as $key => $value) {
                        if (is_int($key)) {
                            $field = $tableAlias.'.`'.$value.'` ASC';
                        } else {
                            $type  = strtolower($value);
                            $field = $tableAlias.'.`'.$key.'`'.(($type == 'desc' || $type == '-1') ? ' DESC' : ' ASC');
                        }
                        $qc_order[] = $field;
                    }
                } elseif ($order) {
                    $qc_order[] = $tableAlias.'.`'.$order.'`';
                }
            }

            if ($where = $this->_parseWhere($params['where'], $params['flags'] | $inheritFlags, $tableAlias)) {
                $qc_where[] = '('.$where.')';
            }
            $tableIndex++;
        }

        if (count($qc_fields)) {
            $qc_fields = implode(', ', $qc_fields);
        } else {
            $qc_fields = '*';
        }

        if (count($qc_where)) {
            $qc_where = 'WHERE '.implode(($flags & K3_Db::SQL_WHERE_OR) ? ' OR ' : ' AND ', $qc_where);
        } else {
            $qc_where = '';
        }

        if (count($qc_order)) {
            $qc_order = 'ORDER BY '.implode(', ', $qc_order);
        } else {
            $qc_order = '';
        }

        $query = 'SELECT ';
        if ($flags & K3_Db::SQL_DISTINCT) {
            $query .= 'DISTINCT ';
        }
        if ($flags & K3_Db::SQL_CALC_ROWS) {
            $query .= 'SQL_CALC_FOUND_ROWS ';
        }

        $query .= $qc_fields.' FROM '.$qc_tables.' '.$qc_where.' '.$qc_order.' '.strval($other);

        return $query;
    }

    /**
     * @param string $table
     * @param array $data
     * @param bool $replace
     * @param int $flags
     * @return bool|string
     */
    protected function _prepareInsertQuery($table, $data, $replace, $flags)
    {
        $query = ($replace) ? 'REPLACE INTO ' : 'INSERT INTO ';

        if (!($flags & K3_Db::SQL_NO_PREFIX)) {
            $table = $this->_tablePrefix.$table;
        }

        $query .= '`'.$table.'` ';

        if (count($data)) {
            $names = $rowValues = array();
            if (!($flags & K3_Db::SQL_INSERT_MULTI)) {
                $data = array($data);
            }

            $fixNames = false;
            foreach ($data as $row) {
                $values = array();

                if (!count($row)) {
                    continue;
                }

                foreach ($row as $field => $val) {
                    if (!$fixNames) {
                        $names[] = $field;
                    } elseif (!in_array($field, $names)) {
                        continue;
                    }

                    if (is_scalar($val)) {
                        if (is_bool($val)) {
                            $val = (int)$val;
                        } elseif (is_string($val)) {
                            if (!($flags & K3_Db::SQL_NO_ESCAPE) && (!is_numeric($val) || $val[0] == '0')) {
                                $val = $this->_pdo->quote($val);
                            }
                            //$val = '"'.$val.'"';
                        } else {
                            $val = (string)$val;
                        }

                        $values[$field] = $val;
                    } elseif (is_null($val)) {
                        $values[$field] = 'NULL';
                    } else {
                        $values[$field] = '""';
                    }
                }
                $rowValues[]  = implode(', ', $values);
                $fixNames = true;
            }

            if (count($names)) {
                $query .= '(`'.implode('`, `', $names).'`) VALUES ('.implode('), (', $rowValues).')';

                return $query;
            }
        }

        return false;
    }

    /**
     * @param string $table
     * @param array $data
     * @param string|array $where
     * @param int $flags
     * @return bool|string
     */
    protected function _prepareUpdateQuery($table, $data, $where, $flags)
    {
        if ($where = $this->_parseWhere($where, $flags)) {
            $where = 'WHERE '.$where;
        }

        if (!($flags & K3_Db::SQL_NO_PREFIX)) {
            $table = $this->_tablePrefix.$table;
        }

        $query = 'UPDATE `'.$table.'` SET ';

        if (count($data)) {
            $names = $fields = array();
            foreach ($data AS $field => $val) {
                if (($flags & K3_Db::SQL_USE_FUNCTIONS) && $part = $this->_parseFieldFunction($field, $val, false)) {
                    $fields[] = $part;
                } elseif (is_scalar($val)) {
                    $names[] = '`'.$field.'`';

                    if (is_bool($val)) {
                        $val = (int)$val;
                    } elseif (is_string($val)) {
                        if (!($flags & K3_Db::SQL_NO_ESCAPE) && (!is_numeric($val) || $val[0] == '0')) {
                            $val = $this->_pdo->quote($val);
                        }
                        //$val = '"'.$val.'"';
                    } else {
                        $val = (string)$val;
                    }

                    $fields[] = '`'.$field.'` = '.$val;
                } elseif (is_null($val)) {
                    $fields[] = '`'.$field.'` = NULL';
                }
            }
            $query .= implode(', ', $fields);
            $query .= ' '.$where;

            return $query;
        } else {
            return false;
        }
    }

    /**
     * @param string $table
     * @param string|array $where
     * @param int $flags
     * @return string
     */
    protected function _prepareDeleteQuery($table, $where, $flags)
    {
        if ($where = $this->_parseWhere($where, $flags)) {
            $where = 'WHERE '.$where;
        }

        if (!($flags & K3_Db::SQL_NO_PREFIX)) {
            $table = $this->_tablePrefix.$table;
        }

        $query = 'DELETE FROM `'.$table.'` '.$where;

        return $query;
    }


    /**
     * @param string|array $where
     * @param int $flags
     * @param string $tableAlias
     * @return mixed|string
     */
    private function _parseWhere($where, $flags = 0, $tableAlias = '')
    {
        if (empty($where)) {
            return '';
        }

        if (is_array($where)) {
            $parts = array();
            foreach ($where AS $field => $val) {
                $field = '`'.$field.'`';
                if ($tableAlias) {
                    $field = $tableAlias.'.'.$field;
                }

                if (($flags & K3_Db::SQL_USE_FUNCTIONS) && ($part = $this->_parseFieldFunction($field, $val, true))) {
                    $parts[] = $part;
                } elseif (is_scalar($val)) {
                    if (is_bool($val)) {
                        $val = (int)$val;
                    } elseif (is_string($val)) {
                        if (!($flags & K3_Db::SQL_NO_ESCAPE) && (!is_numeric($val) || $val[0] == '0')) {
                            $val = $this->_pdo->quote($val);
                        }
                        //$val = '"'.$val.'"';
                    } else {
                        $val = (string)$val;
                    }

                    $parts[] = $field.' = '.$val;
                } elseif (is_array($val) && count($val)) {
                    $subValues = array();
                    foreach ($val as $id => $sub) {
                        if (is_bool($sub)) {
                            $sub = (int)$sub;
                        } elseif (is_string($sub)) {
                            if (!($flags & K3_Db::SQL_NO_ESCAPE) && (!is_numeric($val) || $val[0] == '0')) {
                                $sub = $this->_pdo->quote($sub);
                            }
                            //$sub = '"'.$sub.'"';
                        } elseif (is_null($sub)) {
                            $sub = 'NULL';
                        }

                        if (is_scalar($sub)) {
                            $subValues[$id] = $sub;
                        }
                    }

                    if (count($subValues)) {
                        $parts[] = $field.' IN ('.implode(', ', $subValues).')';
                    }
                } elseif (is_null($val)) {
                    $parts[] = $field.' IS NULL';
                }
            }
            if (count($parts)) {
                return implode(($flags & K3_Db::SQL_WHERE_OR) ? ' OR ' : ' AND ', $parts);
            } else {
                return 'false';
            }
        } elseif (empty($tableAlias)) {
            $where = trim(strval($where));
            $where = preg_replace('#^WHERE\s+#i', '', $where);

            return $where;
        } else {
            return '';
        }
    }

    /**
     * @param array $where
     * @param int $flags
     * @return string
     */
    private function _parseWhereNew(array $where, $flags = 0)
    {
        if (empty($where)) {
            return '';
        }

        $string = '';
        foreach ($where AS $part) {
            $operator = array_pop($part)
                ? ' OR '
                : ' AND ';
            $val     = array_pop($part);
            $field   = array_pop($part);
            $tblPref = array_pop($part);

            if (!is_null($field)) {
                if (!is_null($tblPref)) {
                    $field = '`'.$field.'`';
                    if ($tblPref) {
                        $field = '`'.$tblPref.'`.'.$field;
                    }
                }

                if (($flags & K3_Db::SQL_USE_FUNCTIONS) && ($part = $this->_parseFieldFunction($field, $val, true))) {

                    $string = $string
                        ? '('.$string.')'.$operator.'('.$part.')'
                        : (string)$part;
                } elseif (is_scalar($val)) {
                    if (is_bool($val)) {
                        $val = (int)$val;
                    } elseif (is_string($val)) {
                        if (!($flags & K3_Db::SQL_NO_ESCAPE) && (!is_numeric($val) || $val[0] == '0')) {
                            $val = $this->_pdo->quote($val);
                        }
                        //$val = '"'.$val.'"';
                    } else {
                        $val = (string)$val;
                    }

                    $part   = (is_null($tblPref))
                        ? preg_replace('#(?<!\w|\\\\)\?#', $val, $field)
                        : $field.' = '.$val;
                    $string = $string
                        ? '('.$string.')'.$operator.'('.$part.')'
                        : $part;
                } elseif (is_array($val) && count($val)) {
                    $subValues = array();
                    foreach ($val as $id => $sub) {
                        if (is_bool($sub)) {
                            $sub = (int)$sub;
                        } elseif (is_string($sub)) {
                            if (!($flags & K3_Db::SQL_NO_ESCAPE) && (!is_numeric($val) || $val[0] == '0')) {
                                $sub = $this->_pdo->quote($sub);
                            }
                            //$sub = '"'.$sub.'"';
                        } elseif (is_null($sub)) {
                            $sub = 'NULL';
                        }

                        if (is_scalar($sub)) {
                            $subValues[$id] = $sub;
                        }
                    }

                    if (count($subValues)) {
                        $part   = (is_null($tblPref))
                            ? preg_replace('#(?<!\w|\\\\)\?#', implode(', ', $subValues), $field)
                            : $field.' IN ('.implode(', ', $subValues).')';
                        $string = $string
                            ? '('.$string.')'.$operator.'('.$part.')'
                            : $part;
                    }
                } elseif ($val instanceof K3_Db_Select) {
                    /** @var $val FDBSelect */
                    $value  = $val->toString();
                    $part   = (is_null($tblPref))
                        ? preg_replace('#(?<!\w|\\\\)\?#', $value, $field)
                        : $field.' IN ('.$value.')';
                    $string = $string
                        ? '('.$string.')'.$operator.'('.$part.')'
                        : $part;
                } elseif (is_null($val) && !is_null($tblPref)) {
                    $part   = $field.' IS NULL';
                    $string = $string
                        ? '('.$string.')'.$operator.'('.$part.')'
                        : $part;
                }
            } else {
                $part   = (string)$val;
                $string = $string
                    ? '('.$string.')'.$operator.'('.$part.')'
                    : $part;
            }
        }

        return $string;
    }

    /**
     * constructs simple ORDER and LIMIT
     * @param string|array $other
     * @param int $flags
     * @param string $tableAlias
     * @return string
     */
    private function _parseOther($other, /** @noinspection PhpUnusedParameterInspection */ $flags = 0, $tableAlias = '')
    {
        if (empty($other)) {
            return '';
        }

        if (is_array($other)) {
            $parts = array();

            if (isset($other['order'])) {
                $order = $other['order'];
                if (is_array($order)) {
                    $orderBy = array();
                    foreach ($order as $key => $value) {
                        if (is_int($key)) {
                            $field = '`'.$value.'` ASC';
                        } else {
                            $type  = strtolower($value);
                            $field = '`'.$key.'`'.(($type == 'desc' || $type == '-1') ? ' DESC' : ' ASC');
                        }
                        if ($tableAlias) {
                            $field = $tableAlias.'.'.$field;
                        }
                        $orderBy[] = $field;
                    }
                    if (count($orderBy)) {
                        $parts[] = 'ORDER BY '.implode(', ', $orderBy);
                    }
                } elseif (empty($tableAlias)) {
                    $parts[] = 'ORDER BY '.$order;
                }
            }

            if (isset($other['limit'])) {
                $limit = $other['limit'];
                if (!is_array($limit)) {
                    $limit = preg_split('#\D#', $limit, -1, PREG_SPLIT_NO_EMPTY);
                }
                $limit       = array_slice($limit, 0, 2);
                $limitCount  = (int)array_pop($limit);
                $limitOffset = (int)array_pop($limit);

                if ($limitCount > 0) {
                    $parts[] = 'LIMIT '.$limitOffset.', '.$limitCount;
                }
            }

            if (count($parts)) {
                return implode(' ', $parts);
            } else {
                return '';
            }
        } elseif (empty($tableAlias)) {
            $other = trim(strval($other));

            return $other;
        } else {
            return '';
        }
    }

    /**
     * @param string $field
     * @param string $data
     * @param bool $isCompare
     * @return bool|string
     */
    private function _parseFieldFunction($field, $data, $isCompare = false)
    {
        static $updateFunctions = array(
            '++' => '%1$s = %1$s + %2$s',
            '--' => '%1$s = %1$s - %2$s',
        );

        static $compareFunctions = array(
            '<'      => '%1$s < %2$s', '<=' => '%1$s <= %2$s',
            '>'      => '%1$s > %2$s', '>=' => '%1$s >= %2$s',
            '<>'     => '%1$s <> %2$s', '!=' => '%1$s != %2$s',
            'LIKE'   => '%1$s LIKE %2$s', '!LIKE' => '%1$s NOT LIKE %2$s',
            'ISNULL' => 'ISNULL(%1$s) = %2$s',
        );

        if (!is_string($data)) {
            return false;
        }

        $functions = ($isCompare) ? $compareFunctions : $updateFunctions;
        $expr      = explode(' ', $data, 2);
        if (count($expr) == 2) {

            if (isset($functions[$expr[0]])) {
                $val = $expr[1];
                if ($val == 'NULL') {
                    $val = 'NULL';
                } elseif (!is_numeric($val) || $val[0] == '0') {
                    $val = $this->_pdo->quote($val);
                }

                $out = sprintf($functions[$expr[0]], $field, $val);

                return $out;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param K3_Db_Select $select
     * @param int $flags
     * @return string
     */
    protected function _parseDBSelect(K3_Db_Select $select, $flags = 0)
    {
        static $joinTypes = array(
            K3_Db_Select::JOIN_INNER => ' INNER JOIN ',
            K3_Db_Select::JOIN_LEFT  => ' LEFT JOIN ',
            K3_Db_Select::JOIN_RIGNT => ' RIGNT JOIN ',
            K3_Db_Select::JOIN_CROSS => ' CROSS JOIN ',
        );

        $selectInfo = $select->toArray();

        if (!isset($selectInfo['tables']) || !is_array($selectInfo['tables'])) {
            return '';
        }

        $query = 'SELECT ';
        if ($flags & K3_Db::SQL_DISTINCT) {
            $query .= 'DISTINCT ';
        }
        if ($flags & K3_Db::SQL_CALC_ROWS) {
            $query .= 'SQL_CALC_FOUND_ROWS ';
        }

        // fields
        if (isset($selectInfo['fields']) && is_array($selectInfo['fields'])) {
            $fields = array();
            foreach ($selectInfo['fields'] as $key => $val) {
                if ($val instanceof K3_Db_Select) {
                    /* @var FDBSelect $val */
                    $field = '('.$val->toString().')';
                } elseif (is_array($val)) {
                    $field = (string) $val[1];
                    if (K3_String::isWord($field)) {
                        $field = '`'.$field.'`';
                    }
                    if ($val[0]) {
                        $field = '`'.$val[0].'`.'.$field;
                    }
                } else {
                    $field = '('.strval($val).')';
                }

                $fields[] = (is_string($key))
                    ? $field.' as `'.$key.'`'
                    : $field;
            }

            if (count($fields)) {
                $query .= implode(', ', $fields);
            } else {
                $query .= ' * ';
            }
        } else {
            $query .= ' * ';
        }

        // FROM
        $tables = '';
        foreach ($selectInfo['tables'] as $tableAlias => $tableName) {
            if ($tableName instanceof K3_Db_Select) {
                $tableName = $tableName->toString();
            } elseif (!($flags & K3_Db::SQL_NO_PREFIX) && K3_String::isWord($tableName)) {
                $tableName = $this->_tablePrefix.$tableName;
            }

            if ($tableAlias != $tableName) {
                $table = (K3_String::isWord($tableName))
                    ? '`'.$tableName.'` as `'.$tableAlias.'`'
                    : '('.$tableName.') as `'.$tableAlias.'`';
            }
            else
                $table = '`'.$tableName.'`';

            if (!$tables) {
                $tables = $table;
            } elseif (isset($selectInfo['joins']) && isset($selectInfo['joins'][$tableAlias])) {
                $joinOn = array();
                foreach((array) $selectInfo['joins'][$tableAlias] as $field => $toField) {
                    if (is_string($field)) {
                        if (is_array($toField)) {
                            $joinOnField = '`'.$toField[1].'`';
                            if ($toField[0]) {
                                $joinOnField = '`'.$toField[0].'`.'.$joinOnField;
                            }
                        } else {
                            $joinOnField = $toField;
                        }
                        $joinOn[] = '`'.$tableAlias.'`.`'.$field.'` = '.$joinOnField;
                    } else {
                        $joinOn[] = (string) $toField;
                    }
                }
                $tables = '('.$tables.')'.$joinTypes[$selectInfo['joints'][$tableAlias]].$table.' ON ('.implode(' AND ', $joinOn).')';
            } else {
                $tables.= ', '.$table;
            }
        }
        $query.= ' FROM '.$tables;

        // WHERE
        if (isset($selectInfo['where']) && is_array($selectInfo['where']) && $where = $this->_parseWhereNew($selectInfo['where'], $flags)) {
            $query .= ' WHERE '.$where;
        }

        // GROUP
        if (isset($selectInfo['group']) && is_array($selectInfo['group']) && $parts = $selectInfo['group']) {
            $group = array();
            foreach($parts as $part) {
                if (is_array($part)) {
                    $field = '`'.$part[1].'`';
                    if ($part[0]) {
                        $field = '`'.$part[0].'`.'.$field;
                    }
                    $group[] = $field;
                } else {
                    $group[] = '('.$part.')';
                }
            }
            if (count($group)) {
                $query.= ' GROUP BY '.implode(', ', $group);
            }
        }

        // ORDER
        if (isset($selectInfo['order']) && is_array($selectInfo['order']) && $parts = $selectInfo['order']) {
            $order = array();
            foreach($parts as $part) {
                if (is_array($part)) {
                    $field = (string)$part[1];
                    if (K3_String::isWord($field)) {
                        $field = '`'.$field.'`';
                    }
                    if ($part[0]) {
                        $field = '`'.$part[0].'`.'.$field;
                    }
                    $order[] = $field.($part[2] ? ' DESC' : ' ASC');
                } else {
                    $order[] = '('.strval($part).')';
                }
            }

            if (count($order)) {
                $query.= ' ORDER BY '.implode(', ', $order);
            }
        }

        // LIMIT
        if (isset($selectInfo['limit']) && is_array($selectInfo['limit']) && $limit = $selectInfo['limit']) {
            $query.= ' LIMIT '.($limit[1] ? $limit[1].', ' : '').$limit[0];
        }

        return $query;
    }
}
