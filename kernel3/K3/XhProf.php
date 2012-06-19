<?php

define('XHPROF_FLAGS_K3_DEFAULT', XHPROF_FLAGS_NO_BUILTINS | XHPROF_FLAGS_MEMORY | XHPROF_FLAGS_CPU);

final class K3_XhProf
{
    /** @var string */
    protected static $_xhProfRunId = null;
    /** @var string */
    protected static $_xhProfRunSource = 'K3';
    /** @var resource */
    protected static $_handle = null;
    /** @var int */
    protected static $_flags = XHPROF_FLAGS_K3_DEFAULT;

    protected function __construct() {}

    /**
     * @static
     * @return boolean
     */
    public static function start()
    {
        $dir = ini_get('xhprof.output_dir');
        if (empty($dir)) {
            $dir = '/tmp';
        }

        $xhProfRunId = uniqid();
        $filename = $dir.DIRECTORY_SEPARATOR.$xhProfRunId.'.'.self::$_xhProfRunSource;

        if (!self::$_xhProfRunId && self::$_handle = fopen($filename, 'w')) {
            self::$_xhProfRunId = $xhProfRunId;
            xhprof_enable(self::$_flags);
            register_shutdown_function(array(__CLASS__, 'stop'));
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