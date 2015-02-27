<?php
/**
 * Copyright (C) 2010 - 2012, 2014 - 2015 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' Database driver
 * Requires PHP >= 5.1.0 and PDO
 * @package kernel3
 * @subpackage database
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

/**
 * Class FDataBase
 * @deprecated
 */
class FDataBase extends K3_Db_MySQL implements I_K3_Deprecated
{
    const SQL_NOESCAPE  = K3_Db::SQL_NO_ESCAPE;
    const SQL_USEFUNCS  = K3_Db::SQL_USE_FUNCTIONS;
    const SQL_WHERE_OR  = K3_Db::SQL_WHERE_OR;
    const SQL_SELECTALL = K3_Db::SQL_SELECT_ALL;
    const SQL_NOPREFIX  = K3_Db::SQL_NO_PREFIX;
    const SQL_LEFTJOIN  = K3_Db::SQL_JOIN_LEFT;
    const SQL_DISTINCT  = K3_Db::SQL_DISTINCT;
    const SQL_MULINSERT = K3_Db::SQL_INSERT_MULTI;
    const SQL_CALCROWS  = K3_Db::SQL_CALC_ROWS;
    const SQL_CRREPLACE = K3_Db::SQL_REPLACE;

    /**
     * @param string $dbType
     * @throws FException
     */
    public function __construct($dbType = 'mysql')
    {
        if ($dbType != 'mysql') {
            throw new FException('Use K3_Db_*');
        }

        // deprecated
        $this->pool['tbPrefix'] =& $this->_tablePrefix;
        $this->pool['qResult']  =& $this->_queryResult;
        $this->pool['dbType']   = 'mysql';
    }

    /**
     * @param string $query
     * @param bool $noPrefixReplace
     * @param bool $exec
     * @return int|null|PDOStatement
     */
    public function query($query, $noPrefixReplace = false, $exec = false)
    {
        if (is_int($noPrefixReplace)) {
            return parent::query($query, $noPrefixReplace);
        }

        $flags = 0;
        if (!$noPrefixReplace) {
            $flags |= K3_Db::QUERY_REPLACE_PREFIX;
        }
        if ($exec) {
            $flags |= K3_Db::QUERY_EXEC;
        }
        return parent::query($query, $flags);
    }

    /**
     * @param $query
     * @param bool $noPrefixReplace
     * @return null|PDOStatement
     */
    public function exec($query, $noPrefixReplace = false)
    {
        if (is_int($noPrefixReplace)) {
            return parent::query($query, $noPrefixReplace);
        }

        $flags = 0;
        if (!$noPrefixReplace) {
            $flags |= K3_Db::QUERY_REPLACE_PREFIX;
        }
        return parent::exec($query, $flags);
    }
}

/**
 * Class FDBSelect
 * @deprecated
 */
class FDBSelect extends K3_Db_Select implements I_K3_Deprecated
{
    /**
     * @param string|FDBSelect $tableName
     * @param string|bool $tableAlias - false for auto
     * @param array|null $fields
     * @param K3_Db_Abstract|null $dbo
     */
    public function __construct($tableName, $tableAlias = false, array $fields = null, K3_Db_Abstract $dbo = null)
    {
        parent::__construct($dbo ?: F()->DBase, $tableName, $tableAlias, $fields);
    }

    /**
     * @return K3_Db_Abstract
     */
    public function getDBO()
    {
        return parent::getDB();
    }
}

