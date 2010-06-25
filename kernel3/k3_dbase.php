<?php
/*
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
    }

    // simple one table select
    public function doSelect ($table, $fields = Array(), $where = '', $other = '', $flags = 0)
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
    public function doSelectAll ($table, $fields = Array(), $where = '', $other = '', $flags = 0)
    {
        return $this->doSelect ($table, $fields, $where, $other, $flags | self::SQL_SELECTALL);
    }

    // Base direct query method
    public function query($query = '', $noprefixrepl = false)
    {
        if (!$this->c)
            throw new FException('DB is not connected');

        if( empty($query) )
            return false;

        $start_time = F('Timer')->MicroTime();

        $this->qResult = null;

        if (!$noprefixrepl)
            $query = preg_replace('#(?<=\W|^)(`?)\{DBKEY\}(\w+)(\\1)(?=\s|$|\n|\r)#s', '`'.$this->tbPrefix.'$2`', $query);

        $this->qResult = $this->c->query($query);

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
?>