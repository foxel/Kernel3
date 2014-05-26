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
 * Class K3_Util_Array
 */
class K3_Util_Array extends K3_Util
{
    /**
     * @param string $glue
     * @param array $array
     * @return string
     */
    public static function implodeRecursive($glue, array $array)
    {
        $out = array();
        foreach ($array as $value) {
            if (is_array($value)) {
                $value = self::implodeRecursive($glue, $value);
            }
            $out[] = (string) $value;
        }

        return implode($glue, $out);
    }
} 
