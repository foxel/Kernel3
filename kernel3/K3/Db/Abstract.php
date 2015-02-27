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
 * Class K3_Db_Abstract
 * @property string $type
 * @property mixed  $lastQueryResult
 * @property array  $history
 * @property float  $queriesTime
 * @property int    $queriesCount
 * @property int    $lastSelectRowsCount
 * @property string $tablePrefix
 * @property bool   $inTransaction
 * @property string $UID
 * @author Andrey F. Kupreychik
 */
abstract class K3_Db_Abstract extends FEventDispatcher
{
    /** @var string  */
    protected $_tablePrefix = '';

    /**
     * @var PDO
     */
    protected $_pdo = null;

    /** @var PDOStatement|int|null */
    protected $_queryResult = null;
    /** @var int  */
    protected $_qRowsNum = 0;
    /** @var bool  */
    protected $_inTransaction = false;

    /** @var array[]  */
    protected $_history = array();
    /** @var float  */
    protected $_queriesTime = 0.0;
    /** @var int  */
    protected $_queriesCount = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pool['lastQueryResult']     =& $this->_queryResult;
        $this->pool['history']             =& $this->_history;
        $this->pool['queriesTime']         =& $this->_queriesTime;
        $this->pool['queriesCount']        =& $this->_queriesCount;
        $this->pool['lastSelectRowsCount'] =& $this->_qRowsNum;
        $this->pool['tablePrefix']         =& $this->_tablePrefix;
        $this->pool['inTransaction']       =& $this->_inTransaction;

        $this->pool['UID'] = function_exists('spl_object_hash')
            ? spl_object_hash($this)
            : uniqid(get_class($this), true);
    }

    /**
     * @param array $dataSource
     * @return string
     */
    abstract protected function _prepareConnectionString(array $dataSource);

    /**
     * @return void
     */
    abstract protected function _initializeConnection();

    /**
     * @param array|K3_Config $dataSource
     * @param string $username
     * @param string $password
     * @param string $tablePrefix
     * @param array $pdoOptions
     * @throws FException
     * @return bool
     */
    public function connect($dataSource, $username = '', $password = '', $tablePrefix = '', $pdoOptions = array())
    {
        if ($dataSource instanceof K3_Config) {
            /** @var $dataSource K3_Config */
            if (isset($dataSource->dataSource)) {
                return $this->connect(
                    $dataSource->dataSource->toArray(false),
                    $dataSource->username,
                    $dataSource->password,
                    $dataSource->prefix,
                    (array)$dataSource->pdoOptions
                );
            }

            $dataSource = $dataSource->toArray(false);
        } elseif (!is_array($dataSource)) {
            throw new FException('$dataSource must be either an array or K3_Config instance');
        }

        /** @var array $dataSource */
        $this->_pdo      = new PDO($this->_prepareConnectionString($dataSource), $username, $password, $pdoOptions);
        $this->_tablePrefix = (string)$tablePrefix;
        $this->_initializeConnection();

        return true;
    }

    /**
     * @return bool
     */
    public function check()
    {
        return !!$this->_pdo;
    }

    /**
     * @param string $tableName
     * @param string|bool $tableAlias
     * @param array|null $fields
     * @return K3_Db_Select
     */
    public function select($tableName, $tableAlias = false, array $fields = null)
    {
        return new K3_Db_Select($this, $tableName, $tableAlias, $fields);
    }

    /**
     * @return bool
     * @throws FException
     */
    public function beginTransaction()
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $res = $this->_pdo->beginTransaction();

        $this->_inTransaction = (boolean)$res;

        return $res;
    }

    /**
     * @return bool
     * @throws FException
     */
    public function commit()
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        return $this->_pdo->commit();
    }

    /**
     * @return bool
     * @throws FException
     */
    public function rollBack()
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        return $this->_pdo->rollBack();
    }

    /**
     * @param K3_Db_Select $select
     * @param int $flags
     * @return string
     * @throws FException
     */
    public function parseDBSelect(K3_Db_Select $select, $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        return $this->_parseDBSelect($select, $flags);
    }

    /**
     * @param K3_Db_Select $select
     * @param int $flags
     * @return array|mixed|null
     * @throws FException
     */
    public function execDBSelect(K3_Db_Select $select, $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $query = $this->_parseDBSelect($select, $flags);
        
        if ($result = $this->query($query, 0)) {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & K3_Db::SQL_CALC_ROWS) && $result = $this->query($this->_prepareCalcRowsQuery(), 0)) {
                $this->_qRowsNum = $this->fetchResult($result);
                $result->closeCursor();
            } else {
                $this->_qRowsNum = false;
            }

            return $ret;
        } else {
            return null;
        }
    }

    /**
     * @param string $name
     * @param K3_Db_Select|string $select
     * @param int $flags
     * @return bool
     * @throws FException
     */
    public function createView($name, $select, $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $query = $this->_prepareCreateViewQuery($name, $select, $flags);

        return $this->exec($query, 0);
    }

    /**
     * simple one table select
     * @param string $table
     * @param string|array $fields
     * @param string|array $where
     * @param string|array $other
     * @param int $flags
     * @return array|mixed|null
     * @throws FException
     */
    public function doSelect($table, $fields = array(), $where = '', $other = '', $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $query = $this->_prepareSimpleSelectQuery($table, $fields, $where, $other, $flags);
        if ($result = $this->query($query, 0)) {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & K3_Db::SQL_CALC_ROWS) && $result = $this->query($this->_prepareCalcRowsQuery(), 0)) {
                $this->_qRowsNum = $this->fetchResult($result);
                $result->closeCursor();
            } else {
                $this->_qRowsNum = false;
            }

            return $ret;
        } else {
            return null;
        }
    }

    /**
     * simple one table select all records
     * @param string $table
     * @param string|array $fields
     * @param string|array $where
     * @param string|array $other
     * @param int $flags
     * @return array|mixed|null
     */
    public function doSelectAll($table, $fields = array(), $where = '', $other = '', $flags = 0)
    {
        return $this->doSelect($table, $fields, $where, $other, $flags | K3_Db::SQL_SELECT_ALL);
    }

    /**
     * multitable select
     * $queries = array (
     *     'table1_name' => array('fields' => '*', 'where' => '...', 'prefix' => 't1_'),
     *     'table2_name' => array('fields' => '*', 'where' => '...', 'prefix' => 't2_', 'join' => array('[table2_filed_name]' => '[main_table_field_name]', ...) ),
     *     ...
     *     )
     * @param array $queries
     * @param string $other
     * @param int $flags
     * @return array|mixed|null
     * @throws FException
     * @see FDBaseQCmysql::multitableSelect
     */
    public function doMultiTableSelect(array $queries, $other = '', $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $query = $this->_prepareMultiTableSelectQuery($queries, $other, $flags);
        if ($result = $this->query($query, 0)) {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & K3_Db::SQL_CALC_ROWS) && $result = $this->query($this->_prepareCalcRowsQuery(), 0)) {
                $this->_qRowsNum = $this->fetchResult($result);
                $result->closeCursor();
            } else {
                $this->_qRowsNum = false;
            }

            return $ret;
        } else {
            return null;
        }
    }

    /**
     * @param string $table
     * @param array $data
     * @param bool $replace
     * @param int $flags
     * @return bool|null|mixed
     * @throws FException
     */
    public function doInsert($table, array $data, $replace = false, $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $query = $this->_prepareInsertQuery($table, $data, $replace, $flags);
        if ($result = $this->exec($query, 0)) {
            $ret = $this->_pdo->lastInsertId();

            //$result->closeCursor();

            return $ret ? $ret : true;
        } else {
            return null;
        }
    }

    /**
     * @param string $table
     * @param array $data
     * @param string|array $where
     * @param int $flags
     * @return int|null
     * @throws FException
     */
    public function doUpdate($table, array $data, $where = '', $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $ret   = null;
        $query = $this->_prepareUpdateQuery($table, $data, $where, $flags);

        return $this->exec($query, 0);
    }

    /**
     * @param string $table
     * @param string|array $where
     * @param int $flags
     * @return int|null
     * @throws FException
     */
    public function doDelete($table, $where = '', $flags = 0)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        $ret   = null;
        $query = $this->_prepareDeleteQuery($table, $where, $flags);

        return $this->exec($query, 0);
    }

    /**
     * Base direct query method
     * @param string $query
     * @param int $flags
     * @throws FException
     * @return int|PDOStatement|null
     */
    public function query($query, $flags = K3_Db::QUERY_DEFAULT)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        if (!$query) {
            return null;
        }

        $clock = new K3_Chronometer();

        $this->_queryResult = null;

        if ($flags & K3_Db::QUERY_REPLACE_PREFIX) {
            $query = preg_replace('#(?<=\W|^)(`?)\{DBKEY\}(\w+)(\\1)(?=\s|$|\n|\r)#s', '`'.$this->_tablePrefix.'$2`', $query);
        }

        $this->_queryResult = ($flags & K3_Db::QUERY_EXEC)
            ? $this->_pdo->exec($query)
            : $this->_pdo->query($query);

        $err = $this->_pdo->errorInfo();

        if ($err[1]) {
            $this->_queryResult = null;
            throw new FException('SQL Error '.$err[0].' ('.$err[1].'): '.$err[2].PHP_EOL.'Query: '.$query);
        }

        $query_time = $clock->timeSpent;

        $this->_queriesCount++;
        $this->_queriesTime += $query_time;
        $this->_history[] = array('query' => $query, 'time' => $query_time);

        return $this->_queryResult;
    }

    /**
     * @param $query
     * @param int $flags
     * @return null|PDOStatement
     */
    public function exec($query, $flags = K3_Db::QUERY_DEFAULT)
    {
        return $this->query($query, $flags | K3_Db::QUERY_EXEC);
    }

    /**
     * @param string $string
     * @return string
     * @throws FException
     */
    public function quote($string)
    {
        if (!$this->_pdo) {
            throw new FException('DB is not connected');
        }

        return $this->_pdo->quote($string);
    }

    /**
     * @param PDOStatement $result
     * @param int $flags
     * @return array|mixed
     */
    public function fetchResult(PDOStatement $result, $flags = 0)
    {
        $ret = null;

        if ($flags & K3_Db::SQL_SELECT_ALL) {
            if ($result->columnCount() == 1) {
                $ret = $result->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $ret = $result->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($result->columnCount() == 1) {
            $ret = $result->fetchColumn(0);
        } else {
            $ret = $result->fetch(PDO::FETCH_ASSOC);
        }

        return $ret;
    }

    /**
     * @param string $name
     * @param K3_Db_Select|string $select
     * @param int $flags
     * @return string
     */
    abstract protected function _prepareCreateViewQuery($name, $select, $flags = 0);

    /**
     * @return string
     */
    abstract protected function _prepareCalcRowsQuery();

    /**
     * @param string $table
     * @param string|array $fields
     * @param string|array $where
     * @param string|array $other
     * @param int $flags
     * @return string
     */
    abstract protected function _prepareSimpleSelectQuery($table, $fields, $where, $other, $flags);

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
    abstract protected function _prepareMultiTableSelectQuery($queries, $other, $flags);

    /**
     * @param string $table
     * @param array $data
     * @param bool $replace
     * @param int $flags
     * @return bool|string
     */
    abstract protected function _prepareInsertQuery($table, $data, $replace, $flags);

    /**
     * @param string $table
     * @param array $data
     * @param string|array $where
     * @param int $flags
     * @return bool|string
     */
    abstract protected function _prepareUpdateQuery($table, $data, $where, $flags);

    /**
     * @param string $table
     * @param string|array $where
     * @param int $flags
     * @return string
     */
    abstract protected function _prepareDeleteQuery($table, $where, $flags);

    /**
     * @param K3_Db_Select $select
     * @param int $flags
     * @return string
     */
    abstract protected function _parseDBSelect(K3_Db_Select $select, $flags = 0);
}
