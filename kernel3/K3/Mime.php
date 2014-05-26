<?php
/**
 * Copyright (C) 2011 - 2012, 2014 Andrey F. Kupreychik (Foxel)
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

class K3_Mime
{
    /** @var array|null */
    protected $_mimeMap = null;
    protected $_mimeMapFile = '';

    public function __construct()
    {

    }

    /**
     * @param string $uri
     * @param bool $byNameOnly unused for now
     * @return string
     */
    public function getMime($uri, $byNameOnly = false)
    {
        $map = $this->_getMimeMap();
        $ext = strtolower(K3_Util_File::basenameExtension($uri));
        return isset($map[$ext])
            ? $map[$ext]
            : 'application/octet-stream';
    }

    /**
     * @return array
     */
    protected function _getMimeMap()
    {
        if ($this->_mimeMap !== null) {
            return $this->_mimeMap;
        }
        $this->_mimeMap = array();

        $mimeFile = $this->_mimeMapFile;

        if (!$mimeFile) {
            if (is_readable('/etc/mime.types')) {
                $mimeFile = '/etc/mime.types';
            } else {
                $mimeFile = F_KERNEL_DIR.DIRECTORY_SEPARATOR.'mime.types';
            }
        }

        $rawMap = file_get_contents($mimeFile);

        $regex = "/^([\w\+\-\.\/]+)\s+([\w\s]+)$/im";
        if (preg_match_all($regex, $rawMap, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $row) {
                $extensions = trim($row[2]);
                $mime = trim($row[1]);
                if (!$extensions) {
                    continue;
                }
                $extensions = explode(' ', $extensions);
                foreach ($extensions as $extension) {
                    $this->_mimeMap[strtolower($extension)] = $mime;
                }
            }
        }

        return $this->_mimeMap;
    }
}
