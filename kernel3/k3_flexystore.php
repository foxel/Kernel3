<?php
/**
 * Copyright (C) 2011 - 2012, 2015 Andrey F. Kupreychik (Foxel)
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

    public function __construct($tableName, K3_Db_Abstract $dbo = null, $textTbname = false)
    {
        if (is_null($dbo))
            $dbo = F()->DBase;

        $this->dbo = $dbo;
        $this->tbname = $tableName;
        $this->textTbname = $textTbname ? $textTbname : $this->tbname;
        $this->pool['classes'] = new FDataPool($this->classes, true);

        return $this;
    }

    public function loadClassesFromDB($tableName, $classNames = null)
    {
        $newClasses = array();
        $rows = $this->dbo->doSelectAll($tableName, '*', $classNames ? array('class_id' => $classNames) : false);
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

    public function pushClassesToDB($tableName, $classNames = null)
    {
        if ($classNames && !is_array($classNames))
            $classNames = array($classNames);
            
        foreach ($this->classes as $className => &$class)
        {
            if ($classNames && !in_array($className, $classNames))
                continue;

            $this->dbo->doDelete($tableName, array('class_id' => $className));
            $insert = array();
            foreach ($class as $key => $type)
                $insert[] = array('class_id' => $className, 'key' => $key, 'type' => $type);
            $this->dbo->doInsert($tableName, $insert, false, K3_Db::SQL_INSERT_MULTI);
        }

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

    public function dropClassProperty($className, $propName)
    {
        if (isset($this->classes[$className]) && isset($this->classes[$className][$propName]))
            unset($this->classes[$className][$propName]);
        
        return $this;
    }

    public function joinToSelect(K3_Db_Select $select, $className, $filter = null)
    {
        if (!is_null($filter) && !is_array($filter))
            $filter = array($filter);

        if (isset($this->classes[$className]))
            foreach ($this->classes[$className] as $propName => $propType)
            {
                if ($filter && !in_array($propName, $filter))
                    continue;

                $tbAlias = $propName.'_data';
                $select->joinLeft($propType == 'text' ? $this->textTbname : $this->tbname, array('obj_id' => 'id', 'key' => $select->getDB()->quote($propName)), $tbAlias, array($propName => $propType));
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

    public function create($tableName, K3_Db_Abstract $dbo = null, $textTbname = false)
    {
        return new FFlexyStore($tableName, $dbo, $textTbname);
    }

    public function _Call($tableName, K3_Db_Abstract $dbo = null, $textTbname = false)
    {
        return new FFlexyStore($tableName, $dbo, $textTbname);
    }

    public function createCached($cachename, $tableName, K3_Db_Abstract $dbo = null, $textTbname = false)
    {
        $obj = F()->Cache->get(self::CACHEPREFIX.$cachename);
        if (!($obj instanceof FFlexyStore)) {
            $obj = new FFlexyStore($tableName, $dbo, $textTbname);
        }
        F()->Cache->set(self::CACHEPREFIX.$cachename, $obj);
        
        return $obj;
    }

}
