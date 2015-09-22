<?php
/**
 * Copyright (C) 2015 Andrey F. Kupreychik (Foxel)
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

if (!defined('F_STARTED')) {
    die('Hacking attempt');
}

/**
 * QuickFox kernel 3 'SlyFox' Cache module
 * @package kernel3
 * @subpackage cache
 */
class K3_Cache extends FEventDispatcher
{
    const LIFETIME = 86400; // 1 day cache lifetime

    /** @var array */
    protected $_cacheData = array();
    /** @var string[] */
    protected $_updatedKeys = array();
    /** @var int */
    protected $_startTime = 0;
    /** @var I_K3_Cache_Backend */
    protected $_backend = null;

    /**
     * @param I_K3_Cache_Backend $backend
     */
    public function __construct(I_K3_Cache_Backend $backend = null)
    {
        $this->_backend = $backend ?: new K3_Cache_FileBackend();
        $this->_startTime = time();
        FMisc::addShutdownCallback(array($this, 'flush'));
    }

    /** @var K3_Cache */
    private static $_self = null;

    /**
     * @return K3_Cache
     */
    public static function getInstance()
    {
        if (!self::$_self) {
            self::$_self = new self();
        }

        return self::$_self;
    }

    /**
     * @param string $name
     * @param int $ifModifiedSince
     * @return mixed
     */
    public function get($name, $ifModifiedSince = null)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->_cacheData)) {
            if (!is_int($ifModifiedSince)) {
                $ifModifiedSince = $this->_startTime - self::LIFETIME;
            }
            
            $this->_cacheData[$name] = $this->_unserialize($this->_backend->load($name, $ifModifiedSince));
        }

        return $this->_cacheData[$name];
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        $name = strtolower($name);

        $this->_cacheData[$name] = $value;

        $this->_updatedKeys[] = $name;
    }

    /**
     * @param string $name
     */
    public function drop($name)
    {
        $name = strtolower($name);

        $this->_cacheData[$name] = null;
        if (substr($name, -1) == '.')
        {
            $keys = array_keys($this->_cacheData);
            foreach ($keys as $key)
                if (strpos($key, $name) === 0)
                    $this->_cacheData[$key] = null;
        }

        $this->_updatedKeys[] = $name;
    }

    /**
     * @param array $names
     */
    public function dropList(array $names)
    {
        foreach ($names as $name) {
            $this->drop($name);
        }
    }

    /**
     * @return void
     */
    public function flush()
    {
        $this->_updatedKeys = array_unique($this->_updatedKeys);

        foreach ($this->_updatedKeys as $name) {
            if (isset($this->_cacheData[$name])) {
                $this->_backend->save($name, $this->_serialize($this->_cacheData[$name]));
            } else {
                $this->_backend->drop($name);
            }
        }

        $this->_cacheData = array();
        $this->_updatedKeys = array();
    }

    /**
     * @return void
     */
    public function clear()
    {
        $this->_cacheData = array();
        $this->_updatedKeys = array();

        $this->_backend->clear();
    }

    /**
     * @param mixed $data
     * @return string
     */
    protected function _serialize($data)
    {
        return serialize($data);
    }

    /**
     * @param string $string
     * @return mixed|null
     */
    protected function _unserialize($string)
    {
        return is_null($string)
            ? null
            : unserialize($string);
    }
}
