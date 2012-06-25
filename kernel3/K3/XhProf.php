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

define('XHPROF_FLAGS_K3_DEFAULT', XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_CPU);

/**
 * Facebook XhProf support class
 * Supports runs storage from facebook github recent version
 * @link https://github.com/facebook/xhprof/
 */
final class K3_XhProf
{
    const DEFAULT_SOURCE = 'K3';
    const DEFAULT_SUFFIX = 'xhprof';

    /** @var string */
    protected static $_xhProfRunId = null;
    /** @var string */
    protected static $_xhProfRunSource = self::DEFAULT_SOURCE;
    /** @var string */
    protected static $_xhProfRunFileSuffix = self::DEFAULT_SUFFIX;
    /** @var resource */
    protected static $_handle = null;
    /** @var int */
    protected static $_flags = XHPROF_FLAGS_K3_DEFAULT;

    protected function __construct() {}

    /**
     * @static
     * @param string|null source
     * @param bool $setHeader
     * @return boolean
     */
    public static function start($source = null, $setHeader = false)
    {
        $dir = ini_get('xhprof.output_dir');
        if (empty($dir)) {
            $dir = '/tmp';
        }

        $xhProfRunId = uniqid();
        self::$_xhProfRunSource = (string) ($source ?: self::DEFAULT_SOURCE);
        $filename = $dir.DIRECTORY_SEPARATOR.$xhProfRunId.'.'.self::$_xhProfRunSource.'.'.self::$_xhProfRunFileSuffix;

        if (!self::$_xhProfRunId && self::$_handle = fopen($filename, 'w')) {
            self::$_xhProfRunId = $xhProfRunId;
            xhprof_enable(self::$_flags);
            register_shutdown_function(array(__CLASS__, 'stop'));
            if ($setHeader) {
                header('X-XhProf-QueryString: ', self::getXhProfUIRequest());
            }
            return true;
        }

        return false;
    }

    /**
     * @static
     * @return null|string
     */
    public static function getXhProfUIRequest()
    {
        if (self::$_xhProfRunId) {
            return http_build_query(array(
                'run'    => self::$_xhProfRunId,
                'source' => self::$_xhProfRunSource,
            ));
        } else {
            return null;
        }
    }

    /**
     * @static
     * @return null|array
     */
    public static function stop()
    {
        if (!self::$_xhProfRunId) {
            return null;
        }

        $xhProfData = xhprof_disable();
        if (empty($xhProfData)) {
            return null;
        }

        if (self::$_handle) {
            fwrite(self::$_handle, serialize($xhProfData));
            fclose(self::$_handle);
        }

        self::$_xhProfRunId = self::$_handle = null;

        return $xhProfData;
    }
}
