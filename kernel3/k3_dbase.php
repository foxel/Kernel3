<?php
/**
 * QuickFox kernel 3 'SlyFox' Database driver
 * Requires PHP >= 5.1.0 and PDO
 * @package kernel3
 * @subpackage database
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

/**
 * @property string $type
 * @property mixed  $lastQueryResult
 * @property array  $history
 * @property float  $queriesTime
 * @property int    $queriesCount
 * @property int    $lastSelectRowsCount
 * @property string $tbPrefix
 * @property bool   $inTransaction
 *
 * @property string $UID
 */
class FDataBase extends FEventDispatcher
{
    const SQL_NOESCAPE  = 1;
    const SQL_USEFUNCS  = 2;
    const SQL_WHERE_OR  = 4;
    const SQL_SELECTALL = 8;
    const SQL_NOPREFIX  = 16;
    const SQL_LEFTJOIN  = 32;
    const SQL_DISTINCT  = 64;
    const SQL_MULINSERT = 128;
    const SQL_CALCROWS  = 256;
    const SQL_CRREPLACE = 512;

    protected $_dbDrivers = array('mysql' => 'mysql');
    protected $_dbDSNType = array('mysql' => 'mysql');
    protected $_dbType    = null;
    protected $_tbPrefix  = '';

    /**
     * @var PDO
     */
    protected $_pdo = null;

    /**
     * TODO: class abstraction
     * @var FDBaseQCmysql
     */
    protected $_queryConstructor = null;
    protected $_qResult = null;
    protected $_qRowsNum = 0;
    protected $_inTransaction = false;
    
    protected $_history = array();
    protected $_queriesTime = 0;
    protected $_queriesCount = 0;

    /**
     * @param string $dbType
     */
    public function __construct($dbType = 'mysql')
    {
        $this->_dbType = $dbType;
        if (!isset($this->_dbDrivers[$dbType]))
            trigger_error($dbType.' driver is not supported by F DataBase manager', E_USER_ERROR);
        
        require_once(F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_dbqc_'.$this->_dbDrivers[$dbType].'.php'); // TODO: refactor to use autoloader

        $this->pool['type']                =& $this->_dbType;
        $this->pool['lastQueryResult']     =& $this->_qResult;
        $this->pool['history']             =& $this->_history;
        $this->pool['queriesTime']         =& $this->_queriesTime;
        $this->pool['queriesCount']        =& $this->_queriesCount;
        $this->pool['lastSelectRowsCount'] =& $this->_qRowsNum;
        $this->pool['tbPrefix']            =& $this->_tbPrefix;
        $this->pool['inTransaction']       =& $this->_inTransaction;

        $this->pool['UID'] = function_exists('spl_object_hash')
            ? spl_object_hash($this)
            : uniqid($this->_dbType, true);

        // deprecated
        $this->pool['dbType']  =& $this->_dbType;
        $this->pool['qResult'] =& $this->_qResult;
    }

    /**
     * @param array $params
     * @param string $username
     * @param string $password
     * @param string $tbPrefix
     * @param array $options
     * @return bool
     */
    public function connect(array $params, $username = '', $password = '', $tbPrefix = '', $options = array())
    {
        $conn_pars = array();
        if (!is_array($params))
            return false;
        foreach ($params as $key => $value)
            $conn_pars[] = $key.'='.$value;
        $conn_pars = $this->_dbDSNType[$this->_dbType].':'.implode(';', $conn_pars);
        $this->_pdo = new PDO($conn_pars, $username, $password, $options);
        $this->_tbPrefix = (string) $tbPrefix;
        $qcDriver = 'FDBaseQC'.$this->_dbDrivers[$this->_dbType];
        $this->_queryConstructor = new $qcDriver($this->_pdo, $this);
        
        return true;
    }

    /**
     * @return bool
     */
    public function check()
    {
        return ($this->_pdo ? true : false);
    }

    /**
     * @param string $tableName
     * @param string|bool $tableAlias
     * @param array|null $fields
     * @return FDBSelect
     */
    public function select($tableName, $tableAlias = false, array $fields = null)
    {
        return new FDBSelect($tableName, $tableAlias, $fields);
    }

    /**
     * @return bool
     * @throws FException
     */
    public function beginTransaction()
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $res = $this->_pdo->beginTransaction();
        $this->_inTransaction = (boolean) $res;
        return $res;
    }

    /**
     * @return bool
     * @throws FException
     */
    public function commit()
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        return $this->_pdo->commit();
    }

    /**
     * @return bool
     * @throws FException
     */
    public function rollBack()
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        return $this->_pdo->rollBack();
    }

    /**
     * @param FDBSelect $select
     * @param int $flags
     * @return string
     * @throws FException
     */
    public function parseDBSelect(FDBSelect $select, $flags = 0)
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        return $this->_queryConstructor->parseDBSelect($select->toArray(), $flags);
    }

    /**
     * @param FDBSelect $select
     * @param int $flags
     * @return array|mixed|null
     * @throws FException
     */
    public function execDBSelect(FDBSelect $select, $flags = 0)
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $query = $this->_queryConstructor->parseDBSelect($select->toArray(), $flags);
        if ($result = $this->query($query, true)) {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & FDataBase::SQL_CALCROWS) && $result = $this->query($this->_queryConstructor->calcRowsQuery(), true))
            {
                $this->_qRowsNum = $this->fetchResult($result);
                $result->closeCursor();
            }
            else
                $this->_qRowsNum = false;

            return $ret;
        }
        else
            return null;
    }

    /**
     * @param string $name
     * @param FDBSelect|string $select
     * @param int $flags
     * @return bool
     * @throws FException
     */
    public function createView($name, $select, $flags = 0)
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        if ($select instanceof FDBSelect) {
            $select = $select->toString();
        }

        if (!is_string($select)) {
            return false;
        }
            
        $query = $this->_queryConstructor->createView($name, $select, $flags);
        return $this->query($query, true, true);
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
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $query = $this->_queryConstructor->simpleSelect($table, $fields, $where, $other, $flags);
        if ($result = $this->query($query, true)) {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & FDataBase::SQL_CALCROWS) && $result = $this->query($this->_queryConstructor->calcRowsQuery(), true))
            {
                $this->_qRowsNum = $this->fetchResult($result);
                $result->closeCursor();
            }
            else
                $this->_qRowsNum = false;

            return $ret;
        }
        else
            return null;
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
        return $this->doSelect ($table, $fields, $where, $other, $flags | self::SQL_SELECTALL);
    }
    
    /**
     * multitable select
     * @param array $tqueries
     * @param string $other
     * @param int $flags
     * @return array|mixed|null
     * @throws FException
     * @see FDBaseQCmysql::multitableSelect
     */
    public function doMultitableSelect(array $tqueries, $other = '', $flags = 0)
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $query = $this->_queryConstructor->multitableSelect($tqueries, $other, $flags);
        if ($result = $this->query($query, true)) {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & FDataBase::SQL_CALCROWS) && $result = $this->query($this->_queryConstructor->calcRowsQuery(), true))
            {
                $this->_qRowsNum = $this->fetchResult($result);
                $result->closeCursor();
            }
            else
                $this->_qRowsNum = false;

            return $ret;
        }
        else
            return null;
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
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $query = $this->_queryConstructor->insert($table, $data, $replace, $flags);
        if ($result = $this->exec($query, true)) {
            $ret = $this->_pdo->lastInsertId();

            //$result->closeCursor();

            return $ret ? $ret : true;
        }
        else
            return null;
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
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $ret = null;
        $query = $this->_queryConstructor->update($table, $data, $where, $flags);
        return $this->exec($query, true);
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
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        $ret = null;
        $query = $this->_queryConstructor->delete($table, $where, $flags);
        return $this->exec($query, true);
    }

    /**
     * Base direct query method
     * @param string $query
     * @param bool $noPrefixReplace
     * @param bool $exec
     * @return int|PDOStatement|null
     * @throws FException
     */
    public function query($query, $noPrefixReplace = false, $exec = false)
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        if (!$query)
            return null;

        $start_time = F()->Timer->MicroTime();

        $this->_qResult = null;

        if (!$noPrefixReplace)
            $query = preg_replace('#(?<=\W|^)(`?)\{DBKEY\}(\w+)(\\1)(?=\s|$|\n|\r)#s', '`'.$this->_tbPrefix.'$2`', $query);

        $this->_qResult = ($exec)
            ? $this->_pdo->exec($query)
            : $this->_pdo->query($query);

        $err = $this->_pdo->errorInfo();

        if ($err[1]) {
            $this->_qResult = null;
            throw new FException('SQL Error '.$err[0].' ('.$this->_dbType.' '.$err[1].'): '.$err[2]);
        }

        $query_time = F()->Timer->MicroTime() - $start_time;

        $this->_queriesCount++;
        $this->_queriesTime += $query_time;
        $this->_history[] = array('query' => $query, 'time' => $query_time);

        return $this->_qResult;
    }

    /**
     * @param $query
     * @param bool $noprefixrepl
     * @return null|PDOStatement
     */
    public function exec($query, $noprefixrepl = false)
    {
        return $this->query($query, $noprefixrepl, true);
    }

    /**
     * @param string $string
     * @return string
     * @throws FException
     */
    public function quote($string)
    {
        if (!$this->_pdo)
            throw new FException('DB is not connected');

        return $this->_pdo->quote($string);
    }

    /**
     * @param PDOStatement $result
     * @param int $flags
     * @return array|mixed
     */
    public function fetchResult (PDOStatement $result, $flags = 0)
    {
        $ret = null;

        if ($flags & self::SQL_SELECTALL) {
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
}

class FDBSelect
{
    const JOIN_INNER = 0;
    const JOIN_LEFT  = 1;
    const JOIN_RIGNT = 2;
    const JOIN_CROSS = 3;
    
    const FETCH_ALL  = 0;
    const FETCH_ONE  = 1;

    protected $tables = array();
    protected $fields = array();
    protected $where  = array();
    protected $joins  = array();
    protected $joints = array();
    protected $order  = array();
    protected $group  = array();
    protected $limit  = array();
    protected $flags  = 0;

    /**
     * @var FDataBase
     */
    protected $dbo    = null;

    /**
     * @param $tableName
     * @param string|bool $tableAlias - false for auto
     * @param array|null $fields
     * @param FDataBase|null $dbo
     */
    public function __construct($tableName, $tableAlias = false, array $fields = null, FDataBase $dbo = null)
    {
        if (!$tableAlias || !is_string($tableAlias))
            $tableAlias = $tableName;

        $this->dbo = (!is_null($dbo))
            ? $dbo
            : F()->DBase;
        
        $this->tables[$tableAlias] = (string) $tableName;

        if (!is_null($fields))
            $this->columns($fields, $tableAlias);
        else
            $this->fields[] = array($tableAlias, '*');
            
        return $this;
    }

    /**
     * @return FDBSelect
     */
    public function distinct()
    {
        $this->flags|= FDataBase::SQL_DISTINCT;
        return $this;
    }

    /**
     * @return FDBSelect
     */
    public function calculateRows()
    {
        $this->flags|= FDataBase::SQL_CALCROWS;
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
        if (!$tableAlias || !is_string($tableAlias))
            $tableAlias = isset($this->tables[$tableName])
                ? 't'.count($this->tables)
                : $tableName;

        $this->tables[$tableAlias] = (string) $tableName;
        $this->joins[$tableAlias]  = array();
        $this->joints[$tableAlias] = (int) $joinType;

        if (is_array($joinOn) && count($joinOn))
        {
            foreach ($joinOn as $field => &$toField)
            {
                if (FStr::isWord($field))
                {
                    if (is_string($toField)) {
                        $refTableAlias = $this->_determineTableAliasWithColumn($toField);
                        if (FStr::isWord($refTableAlias)) {
                            $this->joins[$tableAlias][$field] = array($refTableAlias, $toField);
                        } 
                        else 
                            $this->joins[$tableAlias][$field] = $toField;
                    }
                    else
                        $this->joins[$tableAlias][$field] = $toField;
                }
                else
                    $this->joins[$tableAlias][] = $toField;
            }
        }
        elseif (is_string($joinOn))
            $this->joins[$tableAlias] = $joinOn;

        if (!is_null($fields))
            $this->columns($fields, $tableAlias);
        else
            $this->fields[] = array($tableAlias, '*');

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
        } elseif ($column == '*' || FStr::isWord($column)) {
            $expr = array($tableAlias, $column);
            if (!FStr::isWord($alias)) {
                $alias = $column;
            }
        } else {
            $expr = (string) $column;
        }

        if (FStr::isWord($alias)) {
            $this->fields[$alias] = $expr;
        } else {
            $this->fields[] = $expr;
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
        foreach ($columns as $key => &$val)
            $this->column($val, is_string($key) ? $key : false, $tableAlias);

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
        if (is_array($where))
        {
            foreach ($where as $key => &$value)
                if (is_string($key))
                    $this->where($key, $value, $tableAlias, $whereOr);
                else
                    $this->where($value, false, $tableAlias, $whereOr);

            return $this;
        }

        $this->_determineTableAliasWithColumn($where, $tableAlias);

        if (FStr::isWord($where)) {
            $this->where[] = array($tableAlias, $where, $value, (boolean) $whereOr);
        }
        elseif (preg_match('#(?<!\w|\\\\)\?#', $where))
            $this->where[] = array($where, $value, (boolean) $whereOr);
        else
            $this->where[] = array((string) $where, (boolean) $whereOr);

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
    public function order($order, $desc = false, $tableAlias = false)
    {
        $this->_determineTableAliasWithColumn($order, $tableAlias);

        if (FStr::isWord($order)) // column given
            $this->order[] = array($tableAlias, $order, (boolean) $desc);
        else
            $this->order[] = (string) $order;

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

        if (FStr::isWord($group)) // column given
            $this->group[] = array($tableAlias, $group);
        else
            $this->group[] = (string) $group;

        return $this;
    }

    /**
     * @param int $count
     * @param int|bool $start
     * @return FDBSelect
     */
    public function limit($count, $start = false)
    {
        $this->limit = array((int) $count, $start ? (int) $start : null);

        return $this;
    }

    /**
     * @param int $add_params
     * @return string
     */
    public function toString($add_params = 0)
    {
        return $this->dbo->parseDBSelect($this, $this->flags | (int) $add_params);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'tables' => $this->tables,
            'fields' => $this->fields,
            'where'  => $this->where,
            'joins'  => $this->joins,
            'joints' => $this->joints,
            'order'  => $this->order,
            'group'  => $this->group,
            'limit'  => $this->limit,
            'flags'  => $this->flags,
            );
    }

    /**
     * @return FDataBase|null
     */
    public function getDBO()
    {
        return $this->dbo;
    }

    /**
     * @param int $fetch_mode
     * @param int $add_params
     * @return array|mixed|null
     */
    public function fetch($fetch_mode = self::FETCH_ALL, $add_params = 0)
    {
        if ($fetch_mode == self::FETCH_ALL)
            $add_params|= FDataBase::SQL_SELECTALL;

        return $this->dbo->execDBSelect($this, $this->flags | (int) $add_params);
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
        return $this->dbo->createView($name, $this, $this->flags | (int) $add_params);
    }

    /**
     * @param int $add_params
     * @return FDBSelect
     */
    public function addFlags($add_params)
    {
        $this->flags |= (int) $add_params;
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
            && (FStr::isWord($fParts[1]) || $fParts[1] == '*') // second part is field name
            && FStr::isWord($fParts[0])) // first part is table alias name
        {
            $field = $fParts[1];
            $tableAlias = $fParts[0];
        }
        elseif (is_null($tableAlias)) {
            return ($tableAlias = null);
        }
        elseif (!$tableAlias && isset($this->fields[$field]) && is_array($this->fields[$field])) {
            list($tableAlias, $field) = $this->fields[$field];
            return $tableAlias;
        }

        if (!$tableAlias) {
            list($tableAlias) = array_keys($this->tables);
        } else {
            $tableAlias = (string) $tableAlias;
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

