<?php
/**
 * Copyright (C) 2011 - 2013 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' Cptcha generating module
 * Requires PHP >= 5.1.0, GD2
 * Thanks to http://www.captcha.ru/
 * @package kernel3
 * @subpackage extra
 */

if (!defined('F_STARTED')) {
    die('Hacking attempt');
}

/**
 * Class FCaptcha
 *
 * @author Foxel
 * @deprecated
 */
final class FCaptcha extends K3_Captcha
    implements I_K3_Deprecated
{
    /** @var FCaptcha */
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new FCaptcha(F()->appEnv);
        }
        return self::$self;
    }

    /**
     * @param bool $string
     * @param int $bgcolor
     * @param int $fgcolor
     * @param bool $no_session
     * @return string
     */
    public function _Call($string = false, $bgcolor = 0xffffff, $fgcolor = 0, $no_session = false)
    {
        return $this->generate($string, $bgcolor, $fgcolor, $no_session);
    }
}

