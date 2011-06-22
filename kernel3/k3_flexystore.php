<?php
/**
 * QuickFox kernel 3 'SlyFox' Flexible data storage table class
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage database
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

class FFlexyStore extends FBaseClass
{
    const TYPE_STRING = 'str';
    const TYPE_INT    = 'int';
    const TYPE_FLOAT  = 'float';
    const TYPE_TIME   = 'int';
    const TYPE_TEXT   = 'text';

    protected $dbo = null;
    protected $tbname = '';
    protected $textTbname = '';

    private $types = array(
        self::TYPE_STRING,
        self::TYPE_INT,
        self::TYPE_FLOAT,
        self::TYPE_TIME,
        self::TYPE_TEXT,
        );

    protected $classes = array();

    public function __construct($tableName, FDataBase $dbo = null, $textTbname = false)
    {
        if (is_null($dbo))
            $dbo = F()->DBase;

        $this->dbo = $dbo;
        $this->tbname = $tableName;
        $this->textTbname = $textTbname ? $textTbname : $this->tbname;
        $this->pool['classes'] = new FDataPool($this->classes, true);

        return $this;
    }

    public function loadClassesFromDB($tableName)
    {
        $newClasses = array();
        $rows = $this->dbo->doSelectAll($tableName);
        foreach ($rows as &$row)
        {
            if (!isset($newClasses[$row['class_id']]))
                $newClasses[$row['class_id']] = array();

            $newClasses[$row['class_id']][$row['key']] = $row['type'];
        }

        foreach ($newClasses as $className => $class)
            $this->classes[$className] = $class;

        return $this;
    }

    public function addClass($className, array $clInfo = null)
    {
        if (isset($this->classes[$className]))
            return $this;

        $this->classes[$className] = array();

        if (is_array($clInfo))
            foreach($clInfo as $propName => $propType)
                if (in_array($propType, $this->types))
                    $this->classes[$className][$propName] = $propType;

        return $this;
    }

    public function addClassProperty($className, $propName, $propType = self::TYPE_STRING)
    {
        if (isset($this->classes[$className]) && in_array($propType, $this->types))
            $this->classes[$className][$propName] = $propType;
        
        return $this;
    }

    public function joinToSelect(FDBSelect $select, $className, $filter = null)
    {
        if (!is_null($filter) && !is_array($filter))
            $filter = array($filter);

        if (isset($this->classes[$className]))
            foreach ($this->classes[$className] as $propName => $propType)
            {
                if ($filter && !in_array($propName, $filter))
                    continue;

                $tbAlias = $propName.'_data';
                $select->joinLeft($propType == 'text' ? $this->textTbname : $this->tbname, array('obj_id' => 'id', 'key' => $select->getDBO()->quote($propName)), $tbAlias, array($propName => $propType));
            }

        return $select;
    }

    public function __sleep()
    {
        return array('tbname', 'textTbname', 'classes', 'types');
    }

    public function __wakeup()
    {
        if (is_null($this->dbo))
            $this->dbo = F()->DBase;
    }
}

class FFlexyStoreFactory
{
    const CACHEPREFIX = 'FlexyStores.';
    
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FFlexyStoreFactory();
        return self::$self;
    }

    private function __construct() {}

    public function create($tableName, FDataBase $dbo = null, $textTbname = false)
    {
        return new FFlexyStore($tableName, $dbo, $textTbname);
    }

    public function _Call($tableName, FDataBase $dbo = null, $textTbname = false)
    {
        return new FFlexyStore($tableName, $dbo, $textTbname);
    }

    public function createCached($cachename, $tableName, FDataBase $dbo = null, $textTbname = false)
    {
        $obj = FCache::get(self::CACHEPREFIX.$cachename);
        if (!($obj instanceof FFlexyStore))
            $obj = new FFlexyStore($tableName, $dbo, $textTbname);
        FCache::set(self::CACHEPREFIX.$cachename, $obj);
        
        return $obj;
    }

}

?>
