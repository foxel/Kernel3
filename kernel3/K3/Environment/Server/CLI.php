<?php
/**
 * Copyright (C) 2013 Andrey F. Kupreychik (Foxel)
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

class K3_Environment_Server_CLI extends K3_Environment_Server
{
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        $this->pool['domain'] = 'localhost';
        $this->pool['rootRealPath'] = dirname(realpath($_SERVER['SCRIPT_NAME']));
        $this->pool['rootUrl']  = 'file://'.$this->pool['rootRealPath'];
    }

}
