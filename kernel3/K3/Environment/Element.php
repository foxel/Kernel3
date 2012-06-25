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

abstract class K3_Environment_Element extends FEventDispatcher
{
    /**
     * @var K3_Environment $env
     */
    protected $env = null;

    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        $this->setEnvironment(!is_null($env) ? $env : F()->appEnv);
    }

    /**
     * @param K3_Environment $env
     * @return K3_Environment_Element
     */
    public function setEnvironment(K3_Environment $env)
    {
        $this->env = $env;
        return $this;
    }

    /**
     * getter
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getterMethod = 'get'.ucfirst($name);
        if (method_exists($this, $getterMethod)) {
            return $this->$getterMethod();
        } else {
            return parent::__get($name);
        }
    }
}
