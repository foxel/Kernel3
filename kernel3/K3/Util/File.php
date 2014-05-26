<?php
/**
 * Copyright (C) 2014 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Util_File
 */
class K3_Util_File extends K3_Util
{
    public static function pathHash($path)
    {
        return md5(realpath($path)); // TODO: maybe this needs to be modified
    }

    public static function basename($name)
    {
        return (preg_match('#[\x80-\xFF]#', $name))
            ? preg_replace('#^.*[\\\/]#', '', $name)
            : basename($name);
    }

    public static function basenameExtension($name)
    {
        return (preg_match('#[\x80-\xFF]#', $name))
            ? preg_replace('#^.*\.#', '', $name)
            : pathinfo($name, PATHINFO_EXTENSION);
    }

} 
