<?php
/**
 * Copyright (C) 2015 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Db
 * @author Andrey F. Kupreychik
 */
final class K3_Db
{
    const SQL_NO_ESCAPE     = 1;
    const SQL_USE_FUNCTIONS = 2;
    const SQL_WHERE_OR      = 4;
    const SQL_SELECT_ALL    = 8;
    const SQL_NO_PREFIX     = 16;
    const SQL_JOIN_LEFT     = 32;
    const SQL_DISTINCT      = 64;
    const SQL_INSERT_MULTI  = 128;
    const SQL_CALC_ROWS     = 256;
    const SQL_REPLACE       = 512;

    const QUERY_EXEC           = 1;
    const QUERY_REPLACE_PREFIX = 2;
    const QUERY_DEFAULT        = 2;
}
