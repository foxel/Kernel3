<?php

/**
 * QuickFox Kernel 3 'SlyFox' registry module
 * globally stores data (in memory and DB/File)
 * @package kernel3
 * @subpackage core
 */

class K3_Registry
{
    protected $frontData = array();
    protected $backData  = null;
    protected $backUpd   = array();

    protected $backFileName = null;

    /**
     * @var FDataBase
     */
    protected $backDbObject = null;
    protected $backDbTable  = 'registry';

    // real working functgions
    public function __construct()
    {
        FMisc::addShutdownCallback(Array($this, 'close'));
    }

    public function get($name)
    {
        $name = strtolower($name);

        if (isset($this->frontData[$name])) {
            return $this->frontData[$name];
        }

        if (is_null($this->backData)) {
            $this->loadBackData();
        }

        if (isset($this->backData[$name])) {
            return $this->backData[$name];
        }

        return null;
    }

    public function getAll()
    {
        if (is_null($this->backData)) {
            $this->loadBackData();
        }

        return $this->frontData + (array) $this->backData;
    }

    public function set($name, $value, $storeToBack = false)
    {
        $name = strtolower($name);

        if ($storeToBack) {
            if (isset($this->frontData[$name])) {
                unset($this->frontData[$name]);
            }

            if (is_null($this->backData)) {
                $this->loadBackData();
            }

            $this->backData[$name] = $value;
            $this->backUpd[$name]  = true;
        } else {
            $this->frontData[$name] = $value;
        }
    }

    public function drop($name, $dropInBack)
    {
        if (isset($this->frontData[$name])) {
            unset($this->frontData[$name]);
        }

        if ($dropInBack) {
            if (is_null($this->backData)) {
                $this->loadBackData();
            }

            if (isset($this->backData[$name])) {
                unset($this->backData[$name]);
                $this->backUpd[$name]  = true;
            }
        }
    }

    public function setBackDB(FDataBase $dbo, $table = false)
    {
        if (is_null($dbo)) {
            $dbo = F()->DBase;
        }

        if (!$dbo || !$dbo->check()) {
            return false;
        }

        $this->backDbObject = $dbo;
        if (is_string($table) && $table) {
            $this->backDbTable= $table;
        }

        $this->backFileName = null;

        return true;
    }

    public function setBackFile($filename)
    {
        if (!$filename) {
            return false;
        }

        if ((file_exists($filename) && is_writeable($filename)) || touch($filename)) {
            $this->backDbObject = null;
            $this->backFileName = realpath($filename);
            return true;
        }

        return false;
    }

    public function close()
    {
        if (!empty($this->backUpd)) {
            return $this->saveBackData();
        } else {
            return true;
        }
    }

    public function saveBackData()
    {
        if (!is_null($this->backDbObject)) {
            return $this->saveBackDataDB();
        } elseif (!is_null($this->backFileName)) {
            return $this->saveBackDataFile();
        }

        return false;
    }

    protected function saveBackDataDB()
    {
        $replaceData = array();
        $deleteKeys  = array();

        foreach ($this->backUpd as $name => $val) {
            if (isset($this->backData[$name])) {
                $isScalar = is_scalar($this->backData[$name]);
                $replaceData[] = array(
                    'name'      => $name,
                    'value'     => $isScalar ? $this->backData[$name] : serialize($this->backData[$name]),
                    'is_scalar' => $isScalar,
                );
            } else {
                $deleteKeys[] = $name;
            }
        }

        $res = true;
        if (!empty($deleteKeys)) {
            $res = $res && $this->backDbObject->doDelete($this->backDbTable, array('name' => $deleteKeys));
        }
        if (!empty($replaceData)) {
            $res = $res && $this->backDbObject->doInsert($this->backDbTable, $replaceData, true, FDataBase::SQL_MULINSERT);
        }

        return $res;
    }

    protected function saveBackDataFile()
    {
        ksort($this->backData);
        $serialized = serialize($this->backData);
        return file_put_contents($this->backFileName, $serialized);
    }

    public function loadBackData()
    {
        self:$backData = array();

        if (!is_null($this->backDbObject)) {
            return $this->loadBackDataDB();
        } elseif (!is_null($this->backFileName)) {
            return $this->loadBackDataFile();
        }

        return false;
    }

    protected function loadBackDataDB()
    {
        $rows = $this->backDbObject->doSelectAll($this->backDbTable);
        if (!is_array($rows)) {
            return false;
        }

        $this->backData = array();
        foreach ($rows as &$row) {
            $this->backData[$row['name']] = $row['is_scalar']
                ? $row['value']
                : unserialize($row['value']);
        }

        return true;
    }

    protected function loadBackDataFile()
    {
        if ($serialized = file_get_contents($this->backFileName)) {
            $data = unserialize($serialized);
            if (is_array($data)) {
                $this->backData = $data;
                return true;
            }
        }

        return false;
    }

    const MySQL_TABLE_DEF = 'CREATE TABLE IF NOT EXISTS `registry` (
        `name` varchar(128) NOT NULL,
        `value` blob NOT NULL,
        `is_scalar` tinyint(1) NOT NULL,
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`name`),
        KEY `is_scalar` (`is_scalar`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;';
}

