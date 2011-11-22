<?php
/**
 * QuickFox kernel 3 'SlyFox' Caching module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');


//define('FCACHE_USE_MEMCACHED', class_exists('Memcached'));
    
// caching class indeed
class FCache
{
    const LIFETIME = 86400; // 1 day cache lifetime
    //const LIFETIME = 300; // 5 mins cache lifetime - debus needs
    const TEMPPREF = 'TEMP.';

    static private $chdata = Array();
    static private $got_cache = Array();
    static private $upd_cache = Array();
    static private $cache_folder = '';
    static private $qTime = 0;

    private function __construct() {}

    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new StaticInstance('FCache');
        return self::$self;
    }

    static public function initCacher()
    {
        self::$cache_folder = F_DATA_ROOT.DIRECTORY_SEPARATOR.'cache';
        self::$qTime = time();
        if (!is_dir(self::$cache_folder))
            FMisc::mkdirRecursive(self::$cache_folder);
        FMisc::addShutdownCallback(Array(__CLASS__, 'close'));
    }

    // cache control functiond
    static public function get($name)
    {
        $name = strtolower($name);

        if (!in_array($name, self::$got_cache)) {

            self::$chdata[$name] = self::CFS_Load($name);
            self::$got_cache[] = $name;
        }

        return self::$chdata[$name];
    }

    static public function set($name, $value)
    {
        $name = strtolower($name);

        self::$chdata[$name] = $value;

        self::$got_cache[] = $name;
        self::$upd_cache[] = $name;
    }

    static public function drop($name)
    {
        $name = strtolower($name);

        self::$chdata[$name] = null;
        if (substr($name, -1) == '.')
        {
            $keys = array_keys(self::$chdata);
            foreach ($keys as $key)
                if (strpos($key, $name) === 0)
                    self::$chdata[$key] = null;
        }

        self::$upd_cache[] = $name;
    }

    static public function dropList($list)
    {
        $names = explode(' ', $list);
        if (count($names)) {
            $out = Array();
            foreach ($names as $name)
                self::drop($name);
            return true;
        }
        else
            return false;
    }

    static public function close()
    {
        self::$upd_cache = array_unique(self::$upd_cache);

        foreach (self::$upd_cache as $name) {
            $query = false;
            if (is_null(self::$chdata[$name]))
                self::CFS_Drop($name);
            else
                self::CFS_Save($name, self::$chdata[$name]);
        }

        self::$upd_cache = Array();
        return true;
    }

    static public function clear()
    {
        self::$chdata = Array();
        self::$upd_cache = Array();

        self::CFS_Clear();

        return true;
    }


    // Temp files managing funtion
    static public function requestTempFile($name)
    {
        if (!$name)
            return false;

        $name = strtolower(self::TEMPPREF.$name);
        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';


        $filename = self::$cache_folder.'/'.$name;

        return (FMisc::mkdirRecursive(dirname($filename)))
            ? $filename
            : null;
    }

    //Cacher filesystem functions
    static private function CFS_Clear($folder = false)
    {
        $folder = rtrim($folder, '/');

        $folder = (strpos($folder, self::$cache_folder.'/') === 0) ? $folder : self::$cache_folder;
        $stack = Array();
        if (is_dir($folder) && $dir = opendir($folder))
        {
            do {
                while ($entry = readdir($dir))
                    if ($entry!='.' && $entry!='..') {
                        $entry = $folder.'/'.$entry;
                        if (is_file($entry))
                        {
                            $einfo = pathinfo($entry);
                            if (strtolower($einfo['extension'])=='chd')
                                unlink($entry);
                        }
                        elseif (is_dir($entry))
                        {
                            if ($ndir = opendir($entry))
                            {
                                array_push($stack, Array($dir, $folder));
                                $dir = $ndir;
                                $folder = $entry;
                            }
                        }
                    }
                closedir($dir);
                rmdir($folder);
            } while (list($dir, $folder) = array_pop($stack));
        }
    }

    static private function CFS_Load($name)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $filename = self::$cache_folder.'/'.$name;

        if (!file_exists($filename))
            return null;

        if (filemtime($filename) < (self::$qTime - self::LIFETIME))
            return null;

        if ($data = file_get_contents($filename)) {
            $data = unserialize($data);
            return $data;
        }
        else
            return null;
    }

    static private function CFS_Save($name, $data)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name).'.chd';

        $data = serialize($data);

        $filename = self::$cache_folder.'/'.$name;
        return FMisc::mkdirRecursive(dirname($filename)) && file_put_contents($filename, $data);
    }

    static private function CFS_Drop($name)
    {
        if (!$name)
            return false;

        $name = preg_replace('#[^0-9a-zA-Z_\-\.]#', '_', $name);
        $name = str_replace('.', '/', $name);
        if (substr($name, -1) != '/')
            $name.= '.chd';

        $file = self::$cache_folder.'/'.$name;
        if (is_file($file))
            return unlink($file);
        elseif (is_dir($file))
            return self::CFS_Clear($file);
        else
            return true;
    }
}

FCache::initCacher();

?>
