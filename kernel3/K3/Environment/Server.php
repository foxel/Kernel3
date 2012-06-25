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
 * @property string $rootUrl
 * @property string $rootPath
 * @property string $rootRealPath
 * @property string $domain
 * @property int    $port
 */
abstract class K3_Environment_Server extends K3_Environment_Element
{
    /**
     * @static
     * @param string $class
     * @param K3_Environment|null $env
     * @return K3_Environment_Server
     * @throws FException
     */
    public static function construct($class, K3_Environment $env = null)
    {
        if (empty($class)) {
            throw new FException('K3_Environment_Server construct without class specified');
        }

        $className = __CLASS__.'_'.ucfirst($class);

        return new $className($env);
    }

    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        $this->pool = array(
            'rootUrl'      => '',
            'rootPath'     => '',
            'rootRealPath' => '',
            'domain'       => '',
            'port'         => 80,
        );

        parent::__construct($env);
    }

}