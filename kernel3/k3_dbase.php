<?php
/**
 * QuickFox kernel 3 'SlyFox' Database driver
 * Requires PHP >= 5.1.0 and PDO
 * @package kernel3
 * @subpackage database
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

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

    private $dbDrivers = Array('mysql' => 'mysql');
    private $dbDSNType = Array('mysql' => 'mysql');
    private $dbType = null;
    private $tbPrefix = '';
    private $c = null;
    private $qc = null;
    private $qResult = null;
    private $qCalcRows = 0;
    
    private $history = Array();
    private $queriesTime = 0;
    private $queriesCount = 0;

    public function __construct($dbaseType = 'mysql')
    {
        $this->dbType = $dbaseType;
        if (!isset($this->dbDrivers[$dbaseType]))
            trigger_error($dbaseType.' driver is not supported by F DataBase manager', E_USER_ERROR);
        
        require_once(F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_dbqc_'.$this->dbDrivers[$dbaseType].'.php');

        $this->pool['type']                =& $this->dbType;
        $this->pool['lastQueryResult']     =& $this->qResult;
        $this->pool['history']             =& $this->history;
        $this->pool['queriesTime']         =& $this->queriesTime;
        $this->pool['queriesCount']        =& $this->queriesCount;
        $this->pool['lastSelectRowsCount'] =& $this->qCalcRows;
        $this->pool['tbPrefix']            =& $this->tbPrefix;

        // deprecated 
        $this->pool['dbType']  =& $this->dbType;
        $this->pool['qResult'] =& $this->qResult;
        $this->pool['UID'] = function_exists('spl_object_hash')
            ? spl_object_hash($this)
            : uniqid($this->dbType, true);
    }

    public function connect($params, $username = '', $password = '', $tbPrefix = '', $options = Array())
    {
        $conn_pars = Array();
        if (!is_array($params))
            return false;
        foreach ($params as $key => $value)
            $conn_pars[] = $key.'='.$value;
        $conn_pars = $this->dbDSNType[$this->dbType].':'.implode(';', $conn_pars);
        $this->c = new PDO($conn_pars, $username, $password, $options);
        $this->tbPrefix = (string) $tbPrefix;
        $qcDriver = 'FDBaseQC'.$this->dbDrivers[$this->dbType];
        $this->qc = new $qcDriver($this->c, $this);
        
        return true;
    }
    
    public function check()
    {
        return ($this->c ? true : false);
    }

    public function select($tableName, $tableAlias = false, array $fields = null)
    {
        return new FDBSelect($tableName, $tableAlias, $fields);
    }

    public function beginTransaction()
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        return $this->c->beginTransaction();
    }

    public function commit()
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        return $this->c->commit();
    }

    public function rollBack()
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        return $this->c->rollBack();
    }

    public function parseDBSelect(FDBSelect $select, $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        return $this->qc->parseDBSelect($select->toArray(), $flags);
    }

    public function execDBSelect(FDBSelect $select, $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        $ret = Array();
        $query = $this->qc->parseDBSelect($select->toArray(), $flags);
        if ($result = $this->query($query, true))
        {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & FDataBase::SQL_CALCROWS) && $result = $this->query($this->qc->calcRowsQuery(), true))
            {
                $this->qCalcRows = $this->fetchResult($result);
                $result->closeCursor();
            }
            else
                $this->qCalcRows = false;

            return $ret;
        }
        else
            return null;
    }

    public function createView($name, $select, $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        if ($select instanceof FDBSelect)
            $select = $select->toString();

        if (!is_string($select))
            return false;
            
        $query = $this->qc->createView($name, $select, $flags);
        return $this->query($query, true, true);
    }

    // simple one table select
    public function doSelect($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        $ret = Array();
        $query = $this->qc->simpleSelect($table, $fields, $where, $other, $flags);
        if ($result = $this->query($query, true))
        {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & FDataBase::SQL_CALCROWS) && $result = $this->query($this->qc->calcRowsQuery(), true))
            {
                $this->qCalcRows = $this->fetchResult($result);
                $result->closeCursor();
            }
            else
                $this->qCalcRows = false;

            return $ret;
        }
        else
            return null;
    }

    // simple one table select all records
    public function doSelectAll($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        return $this->doSelect ($table, $fields, $where, $other, $flags | self::SQL_SELECTALL);
    }
    
    // multitable select
    public function doMultitableSelect($tqueries, $other = '', $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        $ret = Array();
        $query = $this->qc->multitableSelect($tqueries, $other, $flags);
        if ($result = $this->query($query, true))
        {
            $ret = $this->fetchResult($result, $flags);

            $result->closeCursor();

            if (($flags & FDataBase::SQL_CALCROWS) && $result = $this->query($this->qc->calcRowsQuery(), true))
            {
                $this->qCalcRows = $this->fetchResult($result);
                $result->closeCursor();
            }
            else
                $this->qCalcRows = false;

            return $ret;
        }
        else
            return null;
    }
    
    public function doInsert($table, Array $data, $replace = false, $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        $ret = null;
        $query = $this->qc->insert($table, $data, $replace, $flags);
        if ($result = $this->exec($query, true))
        {
            $ret = $this->c->lastInsertId();

            //$result->closeCursor();

            return $ret ? $ret : true;
        }
        else
            return null;
    }

    public function doUpdate($table, Array $data, $where = '', $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        $ret = null;
        $query = $this->qc->update($table, $data, $where, $flags);
        return $this->exec($query, true);
    }

    public function doDelete($table, $where = '', $flags = 0)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        $ret = null;
        $query = $this->qc->delete($table, $where, $flags);
        return $this->exec($query, true);
    }

    // Base direct query method
    public function query($query, $noprefixrepl = false, $exec = false)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        if (!$query)
            return false;

        $start_time = F()->Timer->MicroTime();

        $this->qResult = null;

        if (!$noprefixrepl)
            $query = preg_replace('#(?<=\W|^)(`?)\{DBKEY\}(\w+)(\\1)(?=\s|$|\n|\r)#s', '`'.$this->tbPrefix.'$2`', $query);

        $this->qResult = ($exec)
            ? $this->c->exec($query)
            : $this->c->query($query);

        $err = $this->c->errorInfo();

        if ($err[1])
        {
            $this->qResult = null;
            throw new FException('SQL Error '.$err[0].' ('.$this->dbType.' '.$err[1].'): '.$err[2]);
        }

        $query_time = F()->Timer->MicroTime() - $start_time;

        $this->queriesCount++;
        $this->queriesTime += $query_time;
        $this->history[] = Array('query' => $query, 'time' => $query_time);

        return $this->qResult;
    }

    public function exec($query, $noprefixrepl = false)
    {
        return $this->query($query, $noprefixrepl, true);
    }

    public function quote($string)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        return $this->c->quote($string);
    }

    public function fetchResult (PDOStatement $result, $flags = 0)
    {
        $ret = Array();
        if ($flags & self::SQL_SELECTALL)
        {
            if ($result->columnCount() == 1)
                $ret = $result->fetchAll(PDO::FETCH_COLUMN);
            else
                $ret = $result->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($result->columnCount() == 1)
            $ret = $result->fetchColumn(0);
        else
            $ret = $result->fetch(PDO::FETCH_ASSOC);

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

    protected $dbo    = null;
    
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
    
    public function distinct()
    {
        $this->flags|= FDataBase::SQL_DISTINCT;
        return $this;
    }

    public function calculateRows()
    {
        $this->flags|= FDataBase::SQL_CALCROWS;
        return $this;
    }

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
                        if (FStr::isWord($toField)) {
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

    public function joinLeft($tableName, $joinOn, $tableAlias = false, array $fields = null)
    {
        return $this->join($tableName, $joinOn, $tableAlias, $fields, self::JOIN_LEFT);
    }
    
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

    public function columns(array $columns, $tableAlias = false)
    {
        foreach ($columns as $key => &$val)
            $this->column($val, is_string($key) ? $key : false, $tableAlias);

        return $this;
    }

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

        if (FStr::isWord($where))
        {
            if (isset($this->fields[$where]) && is_array($this->fields[$where]))
                list($tableAlias, $where) = $this->fields[$where];
                
            $this->where[] = array($tableAlias, $where, $value, (boolean) $whereOr);
        }
        elseif (preg_match('#(?<!\w|\\\\)\?#', $where))
            $this->where[] = array($where, $value, (boolean) $whereOr);
        else
            $this->where[] = array((string) $where, (boolean) $whereOr);

        return $this;
    }

    public function whereOr($where, $value = null, $tableAlias = false)
    {
        return $this->where($where, $value, $tableAlias, true);
    }

    public function order($order, $desc = false, $tableAlias = false)
    {
        $this->_determineTableAliasWithColumn($order, $tableAlias);

        if (FStr::isWord($order)) // column given
            $this->order[] = array($tableAlias, $order, (boolean) $desc);
        else
            $this->order[] = $order;

        return $this;
    }

    public function group($group, $tableAlias = false)
    {
        $this->_determineTableAliasWithColumn($group, $tableAlias);

        if (FStr::isWord($group)) // column given
            $this->group[] = array($tableAlias, $group);
        else
            $this->group[] = (string) $group;

        return $this;
    }

    public function limit($count, $start = false)
    {
        $this->limit = array((int) $count, $start ? (int) $start : null);

        return $this;
    }
    
    public function toString($add_params = 0)
    {
        return $this->dbo->parseDBSelect($this, $this->flags | (int) $add_params);
    }

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

    public function getDBO()
    {
        return $this->dbo;
    }
    
    public function fetch($fetch_mode = self::FETCH_ALL, $add_params = 0)
    {
        if ($fetch_mode == self::FETCH_ALL)
            $add_params|= FDataBase::SQL_SELECTALL;

        return $this->dbo->execDBSelect($this, $this->flags | (int) $add_params);
    }

    public function fetchAll($add_params = 0)
    {
        return $this->fetch(self::FETCH_ALL, $add_params);
    }
    
    public function fetchOne($add_params = 0)
    {
        return $this->fetch(self::FETCH_ONE, $add_params);
    }

    public function createView($name, $add_params = 0)
    {
        return $this->dbo->createView($name, $this, $this->flags | (int) $add_params);
    }

    public function addFlags($add_params)
    {
        $this->flags |= (int) $add_params;
        return $this;
    }
    
    protected function _determineTableAliasWithColumn(&$field, &$tableAlias = false)
    {
        if (count($fParts = explode('.', $field)) == 2 // two parts separated by '.'
            && (FStr::isWord($fParts[1]) || $fParts[1] == '*') // second part is field name
            && FStr::isWord($fParts[0])) // first part is table alias name
        {
            $field = $fParts[1];
            $tableAlias = $fParts[0];
        }
        elseif (is_null($tableAlias) || isset($this->fields[$field]))
            return ($tableAlias = null);
            
        if (!$tableAlias)
            list($tableAlias) = array_keys($this->tables);
        else
            $tableAlias = (string) $tableAlias;

        return $tableAlias;
    }

    public function __sleep()
    {
        return array('tables', 'fields', 'where', 'joins', 'joints', 'order', 'group', 'limit', 'flags');
    }
}

?>
