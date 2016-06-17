<?php
/**
 * Copyright (C) 2011 - 2012 Andrey F. Kupreychik (Foxel)
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
        F()->Registry->set($name, $value, $storeToBack);
    }

    static public function drop($name, $dropInBack)
    {
        F()->Registry->drop($name, $dropInBack);
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
        parent::__construct('FRegistry');
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
