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
 * Class K3_Util_Value
 * @author Andrey F. Kupreychik
 */
class K3_Util_Value extends K3_Util
{
    public static function defineJSON($value)
    {
        if (function_exists('json_encode')) {
            return json_encode($value, JSON_FORCE_OBJECT);
        }

        $string = 'null';

        if (is_bool($value)) {
            $string = $value ? 'true' : 'false';
        } elseif (is_scalar($value)) {
            $string = is_string($value)
                ? K3_Util_String::escapeJSON($value, true)
                : (string)$value;
        } elseif (is_array($value) || is_object($value)) {
            $string = array();
            foreach ($value as $key => $subValue) {
                $key      = K3_Util_String::escapeJSON($key, true);
                $string[] = $key.':'.self::defineJSON($subValue);
            }
            $string = '{ '.implode(',', $string).' }';
        }

        return $string;
    }

    public static function definePHP($value, $tabDepth = 0)
    {
        $tab      = '    ';
        $tabDepth = intval($tabDepth);
        $prefix   = str_repeat($tab, $tabDepth + 1);

        if (is_numeric($value)) {
            $def = $value;
        } elseif (is_bool($value)) {
            $def = (($value) ? 'true' : 'false');
        } elseif (is_null($value)) {
            $def = 'null';
        } elseif (is_array($value) || is_object($value)) {
            $def    = 'array ('.PHP_EOL;
            $fields = array();
            foreach ($value as $key => $subValue) {
                $field = (is_numeric($key)) ? $key." => " : '\''.addslashes($key).'\' => ';
                $field .= self::definePHP($subValue, $tabDepth + 1);
                $fields[] = $prefix.$field;
            }
            $def .= implode(','.PHP_EOL, $fields).PHP_EOL.$prefix.') ';
        } else {
            $def = '\''.addslashes($value).'\'';
        }

        return $def;
    }
} 
