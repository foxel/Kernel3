<?php

/**
 * QuickFox Kernel 3 'SlyFox' registry module
 * globally stores data (in memory and DB/File)
 * @package kernel3
 * @subpackage core
 */

class FRegistry
{
    static protected $frontData = array();
    static protected $backData  = null;
    static protected $backUpd   = array();

    static protected $backFileName = null;
    static protected $backDbObject = null;
    static protected $backDbTable  = 'registry';

    // functions for using registry as part of kernel object
    private static $self = null;
    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FRegistryInstance();
        return self::$self;
    }

    // real working functgions
    static public function init()
    {
        FMisc::addShutdownCallback(Array(__CLASS__, 'close'));
    }

    static public function get($name)
    {
        $name = strtolower($name);

        if (isset(self::$frontData[$name])) {
            return self::$frontData[$name];
        }

        if (is_null(self::$backData)) {
            self::loadBackData();
        }

        if (isset(self::$backData[$name])) {
            return self::$backData[$name];
        }

        return null;
    }

    static public function getAll()
    {
        if (is_null(self::$backData)) {
            self::loadBackData();
        }

        return self::$frontData + (array) self::$backData;
    }

    static public function set($name, $value, $storeToBack = false)
    {
        $name = strtolower($name);

        if ($storeToBack) {
            if (isset(self::$frontData[$name])) {
                unset(self::$frontData[$name]);
            }

            if (is_null(self::$backData)) {
                self::loadBackData();
            }

            self::$backData[$name] = $value;
            self::$backUpd[$name]  = true;
        } else {
            self::$frontData[$name] = $value;
        }
    }

    static public function drop($name, $dropInBack)
    {
        if (isset(self::$frontData[$name])) {
            unset(self::$frontData[$name]);
        }

        if ($storeToBack) {
            if (is_null(self::$backData)) {
                self::loadBackData();
            }

            if (isset(self::$backData[$name])) {
                unset(self::$backData[$name]);
                self::$backUpd[$name]  = true;
            }
        }
    }

    static public function setBackDB(FDataBase $dbo, $table)
    {
        if (is_null($dbo)) {
            $dbo = F()->DBase;
        }

        if (!$dbo || !$dbo->check()) {
            return false;
        }

        self::$backDbObject = $dbo;
        if (is_string($table) && $table) {
            self::$backDbTable= $table;
        }

        self::$backFileName = null;

        return true;
    }

    static public function setBackFile($filename)
    {
        if (!$filename) {
            return false;
        }

        if ((file_exists($filename) && is_writeable($filename)) || touch($filename)) {
            self::$backDbObject = null;
            self::$backFileName = realpath($filename);
            return true;
        }

        return false;
    }

    static public function close()
    {
        if (!empty(self::$backUpd)) {
            return self::saveBackData();
        } else {
            return true;
        }
    }

    static public function saveBackData()
    {
        if (!is_null(self::$backDbObject)) {
            return self::saveBackDataDB();
        } elseif (!is_null(self::$backFileName)) {
            return self::saveBackDataFile();
        }

        return false;
    }

    static protected function saveBackDataDB()
    {
        $replaceData = array();
        $deleteKeys  = array();

        foreach (self::$backUpd as $name => $val) {
            if (isset(self::$backData[$name])) {
                $isScalar = is_scalar(self::$backData[$name]);
                $replaceData[] = array(
                    'name'      => $name,
                    'value'     => $isScalar ? self::$backData[$name] : serialize(self::$backData[$name]),
                    'is_scalar' => $isScalar,
                );
            } else {
                $deleteKeys[] = $name;
            }
        }

        $res = true;
        if (!empty($deleteKeys)) {
            $res = $res && self::$backDbObject->doDelete(self::$backDbTable, array('name' => $deleteKeys));
        }
        if (!empty($replaceData)) {
            $res = $res && self::$backDbObject->doInsert(self::$backDbTable, $replaceData, true, FDataBase::SQL_MULINSERT);
        }

        return $res;
    }

    static protected function saveBackDataFile()
    {
        ksort(self::$backData);
        $serialized = serialize(self::$backData);
        return file_put_contents(self::$backFileName, $serialized);
    }

    static public function loadBackData()
    {
        self:$backData = array();

        if (!is_null(self::$backDbObject)) {
            return self::loadBackDataDB();
        } elseif (!is_null(self::$backFileName)) {
            return self::loadBackDataFile();
        }

        return false;
    }

    static protected function loadBackDataDB()
    {
        $rows = self::$backDbObject->doSelectAll(self::$backDbTable);
        if (!is_array($rows)) {
            return false;
        }

        self::$backData = array();
        foreach ($rows as &$row) {
            self::$backData[$row['name']] = $row['is_scalar']
                ? $row['value']
                : unserialize($row['value']);
        }

        return true;
    }

    static protected function loadBackDataFile()
    {
        if ($serialized = file_get_contents(self::$backFileName)) {
            $data = unserialize($serialized);
            if (is_array($data)) {
                self::$backData = $data;
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

class FRegistryInstance extends StaticInstance
{

    public function __construct() 
    {
        $this->c = 'FRegistry';
    }

    public function __get($name)
    {
        return FRegistry::get($name);
    }

    public function __set($name, $value)
    {
        return FRegistry::set($name, $value, false);
    }

    public function __unset($name)
    {
        return FRegistry::drop($name, false);
    }
}

FRegistry::init();

class FConfig extends FRegistryInstance
{
    const NAME_SEPARATOR  = '.';
    const REGISTRY_PREFIX = 'config';
    protected $schemaPrefix = 'main';

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FConfig();
        return self::$self;
    }

    public function get($name)
    {
        $name = implode(self::NAME_SEPARATOR, array(
            self::REGISTRY_PREFIX,
            $this->schemaPrefix,
            $name,
        ));
        return parent::get($name);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function set($name, $value)
    {
        $name = implode(self::NAME_SEPARATOR, array(
            self::REGISTRY_PREFIX,
            $this->schemaPrefix,
            $name,
        ));
        return parent::set($name, $value, true);
    }

    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    public function drop($name)
    {
        $name = implode(self::NAME_SEPARATOR, array(
            self::REGISTRY_PREFIX,
            $this->schemaPrefix,
            $name,
        ));
        return parent::drop($name, true);
    }

    public function __unset($name)
    {
        return $this->drop($name);
    }
}
