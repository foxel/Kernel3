<?php

/**
 * QuickFox Kernel 3 'SlyFox' registry module
 * globally stores data (in memory and DB/File)
 * @package kernel3
 * @subpackage core
 * @deprecated
 */
class FRegistry implements I_K3_Deprecated
{
    static protected $frontData = array();
    static protected $backData  = null;
    static protected $backUpd   = array();

    static protected $backFileName = null;
    static protected $backDbObject = null;
    static protected $backDbTable  = 'registry';

    static public function init()
    {
        F()->Registry;
    }

    static public function get($name)
    {
        return F()->Registry->get($name);
    }

    static public function getAll()
    {
        return F()->Registry->getAll();
    }

    static public function set($name, $value, $storeToBack = false)
    {
        return F()->Registry->set($name, $value, $storeToBack);
    }

    static public function drop($name, $dropInBack)
    {
        return F()->Registry->drop($name, $dropInBack);
    }

    static public function setBackDB(FDataBase $dbo, $table = false)
    {
        return F()->Registry->setBackDB($dbo, $table);
    }

    static public function setBackFile($filename)
    {
        return F()->Registry->setBackFile($filename);
    }

    static public function close()
    {
        return F()->Registry->close();
    }

    static public function saveBackData()
    {
        return F()->Registry->saveBackData();
    }

    static public function loadBackData()
    {
        return F()->Registry->loadBackData();
    }
}

/**
 * @deprecated
 */
class FRegistryInstance extends StaticInstance implements I_K3_Deprecated
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

/**
 * @deprecated
 */
class FConfig extends FRegistryInstance implements I_K3_Deprecated
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
