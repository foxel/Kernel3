<?php
/**
 * Copyright (C) 2012, 2015 Andrey F. Kupreychik (Foxel)
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

class K3_Environment_Server_HTTP extends K3_Environment_Server
{
    /**
     * @param K3_Environment $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        if (isset($_SERVER['HTTP_HOST'])) {
            $hostParts = explode(':', $_SERVER['HTTP_HOST']);
            $this->pool['domain'] = array_shift($hostParts);
        } else {
            $this->pool['domain'] = $_SERVER['SERVER_NAME'];
        }

        $this->pool['port']   = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;

        $this->pool['rootUrl']  = 'http://'.$this->pool['domain'].(($this->pool['port'] != 80) ? $this->pool['port'] : '').'/';
        $this->pool['rootPath'] = dirname($_SERVER['SCRIPT_NAME']);

        if ($this->pool['rootPath'] = trim($this->pool['rootPath'], '/\\'))
        {
            $this->pool['rootPath'] = preg_replace('#\/|\\\\+#', '/', $this->pool['rootPath']);
            $this->pool['rootUrl'] .= $this->pool['rootPath'].'/';
        }

        $this->pool['rootRealPath'] = preg_replace(array('#\/|\\\\+#', '#(\/|\\\\)*$#'), array(DIRECTORY_SEPARATOR, ''), $_SERVER['DOCUMENT_ROOT']).'/'.$this->pool['rootPath'];
    }

}
