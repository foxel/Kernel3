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
 * Class K3_Util_String
 * @author Andrey F. Kupreychik
 */
class K3_Util_String extends K3_Util
{
    const FILTER_NONE      = 0;
    const FILTER_HEX       = 1;
    const FILTER_WORD      = 2;
    const FILTER_HTML      = 3;
    const FILTER_PATH      = 4;
    const FILTER_PATH_UNIX = 5;

    const FILTER_LINE = 8; // flag

    /** @var array */
    protected static $_JSONEscapeChars = array(
        '\\'   => '\\\\',
        '/'    => '\\/',
        "\r"   => '\\r',
        "\n"   => '\\n',
        "\t"   => '\\t',
        "\x08" => '\\b',
        "\f"   => '\\f',
        '"'    => '\\"',
    );

    /**
     * creates a constant lenght string
     * use STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_BOTH as mode
     * @param string $string
     * @param int $length
     * @param string $padWith
     * @param int $mode STR_PAD_RIGHT|STR_PAD_LEFT|STR_PAD_BOTH
     * @return string
     */
    public static function fixLength($string, $length, $padWith = ' ', $mode = null)
    {
        if (!is_scalar($string)) {
            return $string;
        }
        if ($length <= 0) {
            return $string;
        }

        $len = strlen($string);
        if ($len > $length) {
            switch ($mode) {
                case STR_PAD_LEFT:
                    return substr($string, -$length);
                    break;
                case STR_PAD_BOTH;
                    return substr($string, ($len - $length)/2, $length);
                    break;
                case STR_PAD_RIGHT:
                default:
                    return substr($string, 0, $length);
            }
        } elseif ($len < $length) {
            if (!strlen($padWith)) {
                $padWith = ' ';
            }

            switch ($mode) {
                case STR_PAD_LEFT:
                case STR_PAD_RIGHT:
                case STR_PAD_BOTH:
                    return str_pad($string, $length, $padWith, $mode);
                    break;
                default:
                    return str_pad($string, $length, $padWith);
            }
        }

        return $string;
    }


    /**
     * @param string $string
     * @param int $filter
     * @return mixed|string
     */
    public static function filter($string, $filter = self::FILTER_NONE)
    {
        $string = (string)$string;

        switch ($filter & 7) {
            case self::FILTER_HEX:
                $string = strtolower(preg_replace('#[^0-9a-fA-F]#', '', $string));
                break;

            case self::FILTER_HTML:
                $string = htmlspecialchars($string); // TODO: revise
                break;

            case self::FILTER_WORD:
                $string = preg_replace('#[^0-9a-zA-Z_\-]#', '', $string);
                break;
            /** @noinspection PhpMissingBreakStatementInspection */
            case self::FILTER_PATH_UNIX:
                $string = preg_replace('#^[A-z]\:(\\\\|/)#', DIRECTORY_SEPARATOR, $string);
            case self::FILTER_PATH:
                $string = preg_replace('#(\\\\|/)+#', DIRECTORY_SEPARATOR, $string);
                $string = preg_replace('#[\x00-\x1F\*\?\;\|]|/$|\\\\$|^\.$|(?<!^[A-z])\:#', '', $string);
                $string = trim($string);
                break;
        }

        if ($filter & self::FILTER_LINE) {
            $string = preg_replace('#[\r\n]#', '', $string);
        }

        return $string;
    }

    /**
     * @param string $text
     * @param bool $heredocSectionId
     * @param bool $doWrap
     * @param bool $addSemicolon
     * @return string
     */
    public static function escapeHeredoc($text, $heredocSectionId = false, $doWrap = false, $addSemicolon = false)
    {
        $text = strtr($text, array(
            '\\' => '\\\\',
            '$'  => '\\$',
        ));

        if ($heredocSectionId) {
            $text = preg_replace('#([\r\n])'.$heredocSectionId.'#', '$1 '.$heredocSectionId, $text);
        }

        if ($doWrap && $heredocSectionId) {
            $text = '<<<'.$heredocSectionId.PHP_EOL.$text.PHP_EOL.$heredocSectionId.($addSemicolon ? ';' : '').PHP_EOL;
        }

        return $text;
    }

    /**
     * @param string $text
     * @param bool $doWrap
     * @return string
     */
    public static function escapeJSON($text, $doWrap = false)
    {
        $text = strtr($text, self::$_JSONEscapeChars);

        if ($doWrap) {
            $text = '"'.$text.'"';
        }

        return $text;
    }

    /**
     * @param string $string
     * @param array $params
     * @return string
     */
    public static function smartSprintf($string, array $params)
    {
        $params = array_values($params);
        $count  = preg_match_all('#\%(\d+)\$|\%\w#', $string, $masks, PREG_PATTERN_ORDER);
        if ($count > 0) {
            $masks = $masks[1];
            $count = max($count, max($masks));
            $params += array_fill(0, $count, '');
            $string = vsprintf($string, $params);
        }

        return $string;
    }

    // UIDs and hashes
    /**
     * @param string $string
     * @return string
     */
    public static function shortHash($string)
    {
        return str_pad(dechex(crc32($string)), 8, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $addEntropy
     * @return string
     */
    public static function shortUID($addEntropy = '')
    {
        return self::shortHash(uniqid($addEntropy));
    }
}
