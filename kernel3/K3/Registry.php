<?php
/**
 * Copyright (C) 2012 Andrey F. Kupreychik (Foxel)
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
 */

class K3_Registry
{
    /**
     * @var array
     */
    protected $frontData = array();

    /**
     * @var array|null
     */
    protected $backData  = null;

    /**
     * @var array
     */
    protected $backUpd   = array();

    /**
     * @var string|null
     */
    protected $backFileName = null;

    /**
     * @var FDataBase|null
     */
    protected $backDbObject = null;

    /**
     * @var string
     */
    protected $backDbTable  = 'registry';

    // real working functgions
    public function __construct()
    {
        FMisc::addShutdownCallback(array($this, 'close'));
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
        $this->backData = array();

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

