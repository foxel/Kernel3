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

class K3_Autoloader
{
    protected $_folders = array(F_KERNEL_DIR);
    protected $_classNameReplacePattern = array(
        '_'  => DIRECTORY_SEPARATOR,
        '\\' => DIRECTORY_SEPARATOR,
    );
    protected $_includeFileSuffix = '.php';
    protected $_fixedClassFiles = array();

    public function __construct()
    {
        spl_autoload_register(array($this, 'autoload'));
    }

    public function registerClassFile($className, $fileName)
    {
        $this->_fixedClassFiles[$className] = (string) $fileName;
    }

    public function registerClassPath($dirPath, $nameSpace = null)
    {
        if (!in_array($dirPath, $this->_folders)) {
            if (strlen($nameSpace)) {
                $this->_folders[(string) $nameSpace] = $dirPath;
            } else {
                $this->_folders[] = $dirPath;
            }
        }
    }

    protected function autoload($className)
    {
        $fixedFileName = isset($this->_fixedClassFiles[$className])
            ? $this->_fixedClassFiles[$className]
            : null;

        foreach ($this->_folders as $nameSpace => $folder) {
            if ($fixedFileName) {
                $fileName = $fixedFileName;
            } elseif (is_string($nameSpace)) {
                $fileName = null;
                foreach (array_keys($this->_classNameReplacePattern) as $classNameSeparator) {
                    $nsPrefix = $nameSpace.$classNameSeparator;
                    if (strlen($nsPrefix) < strlen($className) && strpos($className, $nsPrefix) === 0) {
                        $fileName = strtr(substr_replace($className, '', 0, strlen($nsPrefix)), $this->_classNameReplacePattern).$this->_includeFileSuffix;
                        break;
                    }
                }
            } else {
                $fileName = strtr($className, $this->_classNameReplacePattern).$this->_includeFileSuffix;
            }

            if (!$fileName) {
                continue;
            }

            $fullFile = $folder.DIRECTORY_SEPARATOR.$fileName;
            if (is_file($fullFile)) {
                include_once($fullFile);

                if (!class_exists($className) && !interface_exists($className)) {
                    throw new FException('Error Loading class "'.$className.'" from "'.$fullFile.'" file.');
                }
                break;
            }
        }
    }
}
