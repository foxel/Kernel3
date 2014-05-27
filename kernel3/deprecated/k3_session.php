<?php
/**
 * Copyright (C) 2010 - 2012 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' Session module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 * @deprecated
 */
class FSession extends K3_Session implements I_K3_Deprecated
{
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FSession(F()->appEnv);
        return self::$self;
    }

    private function __construct(K3_Environment $env) { parent::__construct($env); }

}

/**
 * @return FSession
 * @deprecated
 */
function FSession()
{
    return FSession::getInstance();
}

/**
 * @return FSession
 * @deprecated
 */
function Session()
{
    return FSession::getInstance();
}

