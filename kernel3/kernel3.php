<?php
/**
 * Copyright (C) 2010 - 2012 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' main file
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('STARTED'))
    die('Hacking attempt');

if (defined('F_STARTED'))
    die('Scripting error');

/** kernel started flag */
define ('F_STARTED', True);

/** kernel debug flag */
if (!defined('F_DEBUG'))
    define('F_DEBUG', false);

/** kernel profile flag */
if (!defined('F_PROFILE'))
    define('F_PROFILE', F_DEBUG);

// let's check the kernel requirements
if (version_compare(PHP_VERSION, '5.1.0', '<'))
    die('PHP 5.1.0 required');

/** kernel files directory */
define('F_KERNEL_DIR', dirname(__FILE__));
/** Site index script file
 * Actually the file that had been opened in browser */
define('F_SITE_INDEX', basename($_SERVER['PHP_SELF']));

/**#@+ kernel internal encoding (can be defined before outside the kernel to customize the system) */
if (!defined('F_INTERNAL_ENCODING'))
    define('F_INTERNAL_ENCODING', 'utf-8');
/**#@+ site root directory (can be defined before outside the kernel) */
if (!defined('F_SITE_ROOT'))
    define('F_SITE_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
/**#@+ directory to store logs (can be defined before outside the kernel) */
if (!defined('F_LOGS_ROOT'))
    define('F_LOGS_ROOT', F_SITE_ROOT);
/**#@+ site data storing root directory (can be defined before outside the kernel) */
if (!defined('F_DATA_ROOT'))
    define('F_DATA_ROOT', F_SITE_ROOT.DIRECTORY_SEPARATOR.'data');
/**#@+ site code cache directory (can be defined before outside the kernel) */
if (!defined('F_CODECACHE_DIR'))
    define('F_CODECACHE_DIR', F_SITE_ROOT);
/**#@+*/

/**#@+
 * @internal this will add missing error constants for older PHP
 * @ignore
 */
if (!defined('E_RECOVERABLE_ERROR'))
    define('E_RECOVERABLE_ERROR', 4096);
if (!defined('E_DEPRECATED'))
    define('E_DEPRECATED', 8192);
if (!defined('E_USER_DEPRECATED'))
    define('E_USER_DEPRECATED', 16384);
/**#@+*/


Error_Reporting(F_DEBUG ? E_ALL : 0);
if (F_DEBUG) {
    ini_set('display_errors', 'On');
}

if (get_magic_quotes_runtime()) {
    set_magic_quotes_runtime(0);
}

set_time_limit(30);  // not '0' - once i had my script running for a couple of hours collecting GBytes of errors :)
// here comes the fatal catcher :P
register_shutdown_function(create_function('', 'if (($a = error_get_last()) && $a[\'type\'] == E_ERROR)
    { file_put_contents(F_LOGS_ROOT.DIRECTORY_SEPARATOR.\'php_fatal.log\', sprintf(\'E%d "%s" at %s:%d\', $a[\'type\'], $a[\'message\'], $a[\'file\'], $a[\'line\']));
    $i = ob_get_level(); while ($i--) @ob_end_clean(); print \'Fatal error. Sorry :(\'; }'));

// here we set an error catcher
set_error_handler(create_function('$c, $m, $f, $l', 'throw new ErrorException($m, 0, $c, $f, $l);'),
    E_ALL & ~(E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED));

// load xhprof
if (F_PROFILE && extension_loaded('xhprof')) {
    require_once(F_KERNEL_DIR.DIRECTORY_SEPARATOR.'K3/XhProf.php');
    K3_XhProf::start();
}

/**#@+
 * @internal this will build a list of base includes to include in one file
 * @ignore
 */
$base_modules_files = Array(
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'K3/Autoloader.php',    // kernel 3 autoloader
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_misc.php',          // kernel 3 classes and functions library
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_timer.php',         // kernel 3 basic classes
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_cache.php',         // kernel 3 cacher class
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_strings.php',       // kernel 3 strings parsing
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_lang.php',          // kernel 3 LNG interface
    F_KERNEL_DIR.DIRECTORY_SEPARATOR.'k3_dbase.php',         // kernel 3 database interface
);
// we'll do some trick with caching base modules in one file
if (F_DEBUG) {
    foreach ($base_modules_files as $fname) {
        require_once($fname);
    }
} else {
    $base_modules_stats = Array();
    foreach ($base_modules_files as $fname)
        $base_modules_stats[] = filemtime($fname).'|'.filesize($fname);
    $kernel_codecache_dir = is_writable(F_KERNEL_DIR) ? F_KERNEL_DIR : F_CODECACHE_DIR;
    $base_modules_stats = md5(implode('|', $base_modules_stats));
    $base_modules_file = $kernel_codecache_dir.DIRECTORY_SEPARATOR.'.k3.compiled.'.$base_modules_stats;
    if (false && extension_loaded('bcompiler') && file_exists($base_modules_file.'.bc'))
        require_once($base_modules_file.'.bc');
    elseif (file_exists($base_modules_file.'.php'))
        require_once($base_modules_file.'.php');
    else
    {
        foreach (scandir($kernel_codecache_dir) as $fname)
            if (preg_match('#^.k3\.compiled\.[0-9a-fA-F]{32}\.(php|bc)?$#', $fname))
                unlink($kernel_codecache_dir.DIRECTORY_SEPARATOR.$fname);
        $base_modules_eval = array();
        foreach ($base_modules_files as $fname)
            $base_modules_eval[] = preg_replace('#^\s*\<\?php\s*|^\s*\<\?\s*|\?\>\s*$#D', '', php_strip_whitespace($fname));
        $base_modules_eval = preg_replace('#(?<!^)if\s+\(!defined\(\'F_STARTED\'\)\)\s+die\(\'[^\']+\'\)\;\s*#', '', implode(PHP_EOL, $base_modules_eval));

        if (file_put_contents($base_modules_file.'.php', "<?php\n".$base_modules_eval."\n?>"))
        {
            if (function_exists('bcompiler_write_file'))
            {
                $fileHandle = fopen($base_modules_file.'.bc', 'w');
                bcompiler_write_header($fileHandle);
                bcompiler_write_file($fileHandle, $base_modules_file.'.php');
                bcompiler_write_footer($fileHandle);
                fclose($fileHandle);
                unset($fileHandle);
            }
            eval($base_modules_eval);
        }
        else
        {
            trigger_error('Kernel: Error creating base modules cache.', E_USER_WARNING);
            foreach ($base_modules_files as $fname)
                require_once($fname);
        }
        unset($base_modules_eval);
    }
}
unset($kernel_codecache_dir, $base_modules_files, $base_modules_stats, $base_modules_file);
/**#@+*/

/**
 * the main kernel class
 * used to control all the modules
 * @property K3_Autoloader $Autoloader
 * @property FTimer $Timer
 * @property FCache $Cache
 * @property FStr $Str
 * @property K3_Environment $appEnv
 * @property K3_Request $Request
 * @property K3_Response $Response
 * @property K3_Session $Sess
 * @property K3_Session $Session
 * @property FLNGData $LNG
 * @property FDataBase $DBase
 * @property K3_Registry $Registry
 *
 * @property FVISInterface $VIS
 * @property FFlexyStoreFactory $FlexyStore
 * @property FCaptcha $Captcha
 * @property FParser $Parser
 * @property FMetaFileFactory $MetaFile
 */
class F extends FEventDispatcher
{
    /** internal encoding (usually UTF-8) */
    const INTERNAL_ENCODING = F_INTERNAL_ENCODING;
    /** kernel files directory */
    const KERNEL_DIR = F_KERNEL_DIR;

    static private $ERR_TYPES = Array(
        E_ERROR             => 'PHP ERROR',
        E_WARNING           => 'PHP WARNING',
        E_NOTICE            => 'PHP NOTICE',
        E_USER_ERROR        => 'USER ERROR',
        E_USER_WARNING      => 'USER WARNING',
        E_USER_NOTICE       => 'USER NOTICE',
        E_STRICT            => 'PHP5 STRICT',
        E_DEPRECATED        => 'PHP DEPRECATED',
        E_USER_DEPRECATED   => 'USER DEPRECATED',
        E_RECOVERABLE_ERROR => 'PHP RECOVERABLE',
        );

    /**
     * @var F
     */
    static private $self = null;
    private $classes = Array();
    private $clclose = Array();

    private function __construct()
    {
        set_exception_handler(Array($this, 'handleException'));
        set_error_handler(Array($this, 'logError'), F_DEBUG ? E_ALL : E_ALL & ~(E_NOTICE | E_USER_NOTICE | E_STRICT));

        $this->pool['Autoloader'] = new K3_Autoloader();
        $this->pool['Timer']      = new FTimer();
        $this->pool['Cache']      = FCache::getInstance();
        $this->pool['Str']        = FStr::getInstance();
        $this->pool['LNG']        = FLNGData::getInstance();
        $this->pool['appEnv']     = $e = $this->prepareDefaultEnvironment();
        $this->pool['Request']    = $e->getRequest();
        $this->pool['Response']   = $e->getResponse();
        $this->pool['Sess']       =
        $this->pool['Session']    = $e->getSession();
        //$this->pool['DBase'] = new FDataBase();
        $this->classes['DBase']    = 'FDataBase';
        $this->classes['Registry'] = 'K3_Registry';
        $this->classes['Config']   = 'FConfig';
        //$this->pool['DBObject'] = new StaticInstance('FDBObject');

        $this->pool['LNG']->_Start(); // TODO: introduce F_Kernel_Module abstract class / interface

        if ($CL_Config = FMisc::loadDatafile(self::KERNEL_DIR.DIRECTORY_SEPARATOR.'modules.qfc', FMisc::DF_SLINE, false, '|'))
        {
            $this->Autoloader->registerClassPath(self::KERNEL_DIR);

            foreach ($CL_Config as $mod => $cfg)
            {
                $this->classes[$mod] = ($cfg[1]) ? array_shift($cfg) : 'F'.$mod;
                $this->Autoloader->registerClassFile($this->classes[$mod], array_shift($cfg));
            }
        }
    }

    /** This method is used to access the kernel from any context (as it is a static method).
     * @return F|object Returns module object (if $name is defined) or kernel root object.
     * @param string $name Name of the kernel module to access. If empty - kernel object returned
     */
    public static function kernel($name = null)
    {
        if (!self::$self)
            self::$self = new F();
        return is_null($name)
            ? self::$self
            : self::$self->__get($name);
    }

    /**
     * @return K3_Environment
     */
    public function e()
    {
        return $this->pool['appEnv'];
    }

    /**
     * @return K3_Environment
     */
    protected function prepareDefaultEnvironment()
    {
        $env = new K3_Environment('HTTP');
        $env->setRequest(new K3_Request_HTTP($env));
        $env->setResponse(new K3_Response_HTTP($env));
        $env->setSession(new K3_Session($env));
        return $env;
    }

    /** Tests if module with given name is accessable
     * @return bool
     * @param string $name Name of module to ping
     */
    public function ping($name)
    {
        return isset($this->pool[$name]);
    }

    /** Loads and initializes $mod_name module
     * @return bool
     * @param string $mod_name Name of module to run
     */
    public function runModule($mod_name)
    {
        if (preg_match('#\W#', $mod_name))
            return false;

        if (!isset($this->classes[$mod_name]))
            return false;

        $mod_class = $this->classes[$mod_name];

        if (isset($this->pool[$mod_name]))
            return ($this->pool[$mod_name] instanceof $mod_class);

        if (class_exists($mod_class, true))
        {
            $res = method_exists($mod_class, 'getInstance')
                ? ($this->pool[$mod_name] = call_user_func(Array($mod_class, 'getInstance')))
                : ($this->pool[$mod_name] = new $mod_class());

            if ($res)
            {
                if (method_exists($mod_class, '_Start'))
                    $this->pool[$mod_name]->_Start();
                if (method_exists($mod_class, '_Close'))
                    $this->clclose[] = Array(&$this->pool[$mod_name], '_Close');
                $this->throwEvent('moduleStart', $mod_name);
                return true;
            }
        }

        trigger_error('Kernel: can\'t create "'.$mod_name.'" module object', E_USER_ERROR);
        return false; // just in case ))
    }

    /** Handles exceptions and writes logs
     * @access private
     * @param Exception $e
     * @ignore
     */
    public function handleException(Exception $e)
    {
        $logfile = F_LOGS_ROOT.DIRECTORY_SEPARATOR.'fatal.log';
        $eName = get_class($e);
        if ($e instanceof ErrorException) {
            /* @var ErrorException $e */
            $eName.= '['.self::$ERR_TYPES[$e->getSeverity()].']';
        }

        if ($logfile = fopen($logfile, 'ab'))
        {
            fwrite($logfile, date('[d M Y H:i]').' '.$eName.': '.$e->getMessage().'. File: '.$e->getFile().'. Line: '.$e->getLine().'.'.PHP_EOL.$e->getTraceAsString().'.'.PHP_EOL);
            fclose($logfile);
        }

        FMisc::obFree();
        if (!headers_sent()) {
            header ($_SERVER["SERVER_PROTOCOL"].' 503 Service Unavailable');
            header('Content-Type: text/html; charset='.self::INTERNAL_ENCODING);
        }
        if (F_DEBUG) {
            print $eName.': '.$e->getMessage().'. File: '.$e->getFile().'. Line: '.$e->getLine().'.'.PHP_EOL;
        } else {
            print '<html><head><title>'.$this->LNG->lang('ERR_CRIT_PAGE', false, true).'</title></head><body><h1>'.$this->LNG->lang('ERR_CRIT_PAGE', false, true).'</h1>'.$this->LNG->lang('ERR_CRIT_MESS', false, true).'</body></html>';
        }
    }

    /** Handles errors, writes logs and throws exceptions for critical errors
     * @access private
     * @param int $c
     * @param string $m
     * @param string $f
     * @param int $l
     * @ignore
     */
    public function logError($c, $m, $f = '', $l = 0)
    {
        static $logfile = null;

        if ($c & ~(E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED))
            throw new ErrorException($m, 0, $c, $f, $l);

        if ($logfile == null)
            $logfile = fopen(F_LOGS_ROOT.DIRECTORY_SEPARATOR.'error.log', 'ab');

        $eName = isset(self::$ERR_TYPES[$c]) ? '['.self::$ERR_TYPES[$c].']' : '[UNKNOWN ERROR]';
        if ($logfile) {
            flock($logfile, LOCK_EX);
            fwrite($logfile, date('[d M Y H:i]').' '.$eName.': '.$m.'. File: '.$f.'. Line: '.$l.'.'.PHP_EOL.FStr::PHPDefine(array_slice(debug_backtrace(),1)).'.'.PHP_EOL);
            flock($logfile, LOCK_UN);
        }
    }

    /** Handles accessing to modules in F()->module form
     * @return object
     * @param string $name Name of module to access
     */
    public function __get($name)
    {
        if (isset($this->pool[$name]))
            return $this->pool[$name];
        elseif (isset($this->classes[$name]) && $this->runModule($name))
            return $this->pool[$name];

        return new FNullObject('F()->'.$name);
    }

    /** Handles calling modules like functions
     * Calls '_Call' method of '$name' module
     * @return mixed
     * @param string $name
     * @param array $arguments
     */
    public function __call($name, $arguments)
    {
        $mod_class = $this->classes[$name];

        if (isset($this->pool[$name]) || $this->runModule($name))
            if (method_exists($mod_class, '_Call'))
                return call_user_func_array(Array(&$this->pool[$name],  '_Call'), $arguments);
                
        return null;
    }
}

/** This function is used to access the kernel from any context with 'F()'.
 * @return F|object Returns module object (if $name is defined) or kernel root object.
 * @param string $name Name of the kernel module to access
 * @see F::kernel
 */
function F($name = null) { return F::kernel($name); }
$GLOBALS['QF'] = $GLOBALS['F'] = F();

