<?php
/**
 * QuickFox kernel 3 'SlyFox' Database driver
 * Requires PHP >= 5.1.0 and PDO
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

    private $dbDrivers = Array('mysql' => 'mysql');
    private $dbDSNType = Array('mysql' => 'mysql');
    private $dbType = null;
    private $tbPrefix = 'qf_';
    private $c = null;
    private $qc = null;
    private $qResult = null;
    private $history = Array();
    private $queriesTime = 0;
    private $queriesCount = 0;

    public function __construct($dbaseType = 'mysql')
    {
        $this->dbType = $dbaseType;
        if (!isset($this->dbDrivers[$dbaseType]))
            trigger_error($dbaseType.' driver is not supported by F DataBase manager', E_USER_ERROR);
        
        require_once(F_KERNEL_DIR.'k3_dbqc_'.$this->dbDrivers[$dbaseType].'.php');

        $this->pool['dbType']  =& $this->dbType;
        $this->pool['qResult'] =& $this->qResult;
        $this->pool['history'] =& $this->history;
        $this->pool['queriesTime']  =& $this->queriesTime;
        $this->pool['queriesCount'] =& $this->queriesCount;
    }

    public function connect($params, $username = '', $password = '', $tbPrefix = 'qf_', $options = Array())
    {        $conn_pars = Array();
        if (!is_array($params))
            return false;
        foreach ($params as $key => $value)
            $conn_pars[] = $key.'='.$value;
        $conn_pars = $this->dbDSNType[$this->dbType].':'.implode(';', $conn_pars);
        $this->c = new PDO($conn_pars, $username, $password, $options);
        $this->tbPrefix = (string) $tbPrefix;
        $qcDriver = 'FDBaseQC'.$this->dbDrivers[$this->dbType];
        $this->qc = new $qcDriver($this->c);
        
        return true;
    }
    
    public function check()
    {
        return ($this->c ? true : false);
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

        $start_time = F('Timer')->MicroTime();

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

        $query_time = F('Timer')->MicroTime() - $start_time;

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
    {        if (!$this->c)
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

class FDBQuery
{
    const JOIN_INNER = 0;
    const JOIN_LEFT  = 1;
    const JOIN_RIGNT = 2;
    const JOIN_CROSS = 3;

    private $tables = array();
    private $fields = array();
    private $where  = array();
    private $joins  = array();
    private $joints = array();
    private $order  = array();
    
    public function __construct($tableName, $tableAlias = false, array $fields = null)
    {
        if (!$tableAlias || !is_string($tableAlias))
            $tableAlias = 't'.count($this->tables);

        $this->tables[$tableAlias] = (string) $tableName;
        $this->fields[$tableAlias] = array();
        $this->where[$tableAlias]  = array();
        $this->joins[$tableAlias]  = array();
        $this->order[$tableAlias]  = array();

        if (!is_null($fields))
            $this->columns($fields, $tableAlias);

        return $this;
    }

    public function join($tableName, $joinOn, $tableAlias = false, array $fields = null, $joinType = self::JOIN_INNER)
    {
        if (!$tableAlias || !is_string($tableAlias))
            $tableAlias = 't'.count($this->tables);

        $this->tables[$tableAlias] = (string) $tableName;
        $this->fields[$tableAlias] = array();
        $this->where[$tableAlias]  = array();
        $this->joins[$tableAlias]  = array();
        $this->joints[$tableAlias] = (int) $joinType;
        $this->order[$tableAlias]  = array();

        if (is_array($joinOn) && count($joinOn))
        {
            foreach ($joinOn as $field => &$toField)
                $this->joins[$tableAlias][$field] = $toField;
        }
        elseif (is_string($joinOn))
            $this->joins[$tableAlias] = $joinOn;

        if (!is_null($fields))
            $this->columns($fields, $tableAlias);

        return $this;
    }

    public function joinLeft($tableName, $joinOn, $tableAlias = false, array $fields = null)
    {
        return $this->join($tableName, $joinOn, $tableAlias, $fields, self::JOIN_LEFT)
    }
    
    public function column($column, $alias = false, $tableAlias = false)
    {
        $this->_determineTableAlias($tableAlias);

        if (FStr::isWord($column)) {
            if ($alias && FStr::isWord($alias))
                $this->fields[$tableAlias][$alias] = (string) $column;
            else
                $this->fields[$tableAlias][] = (string) $column;
        }
        else
            $this->fields[0][] = (string) $column;
        
        return $this;
    }

    public function columns(array $columns, $tableAlias = false)
    {
        foreach ($columns as $key => &$val)
            $this->column($val, is_string($key) ? $key : false, $tableAlias);

        return $this;
    }

    public function where($where, $value = null, $tableAlias = false)
    {
        $this->_determineTableAlias($tableAlias);

        if (FStr::isWord($where))
            $this->where[$tableAlias][$where] = $value;
        elseif (preg_match('#(?<!\w|\\\\)\?#', $where))
            $this->where[0][] = array($where, $value);
        else
            $this->where[0][] = $where;

        return $this;
    }

    public function order($order, $desc = false, $tableAlias = false)
    {
        $this->_determineTableAlias($tableAlias);

        if (FStr::isWord($order)) // clumn given
            $this->order[$tableAlias][$order] = (boolean) $desc;
        else
            $this->order[0][] = $order;

        return $this;
    }

    public function group($group, $tableAlias = false)
    {
        $this->_determineTableAlias($tableAlias);

        if (FStr::isWord($group)) // clumn given
            $this->group[$tableAlias][] = $group;
        else
            $this->group[0][] = $group;

        return $this;
    }

    protected function _determineTableAlias(&$tableAlias)
    {
        if (is_null($tableAlias) || $tableAlias == 0)
            $tableAlias = 0;
        elseif (!$tableAlias)
            list($tableAlias) = array_keys($this->tables);
        else
            $tableAlias = (string) $tableAlias;

        return $tableAlias;
    }
}
?>
