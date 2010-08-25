<?php
/*
 * QuickFox kernel 3 'SlyFox' main file
 * Requires PHP >= 5.1.0
 */

if (!defined('STARTED'))
    die('Hacking attempt');

if (defined('FSTARTED'))
    die('Scripting error');

define ('F_STARTED', True);

// let's check the kernel requirements
if (PHP_VERSION < '5.1.0')
    die('PHP 5.1.0 required');

define('F_KERNEL_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR);
define('F_SITE_INDEX', basename($_SERVER['PHP_SELF']));

if (!defined('F_INTERNAL_ENCODING'))
    define('F_INTERNAL_ENCODING', 'utf-8');
if (!defined('F_SITE_ROOT'))
    define('F_SITE_ROOT', dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR);
if (!defined('F_DATA_ROOT'))
    define('F_DATA_ROOT', F_SITE_ROOT.'data'.DIRECTORY_SEPARATOR);

Error_Reporting(0);
if (get_magic_quotes_runtime())
    set_magic_quotes_runtime(0);
set_time_limit(30);  // not '0' - once i had my script running for a couple of hours collecting GBytes of errors :)
// here comes the fatal catcher :P
register_shutdown_function(create_function('', 'if (($a = error_get_last()) && $a[\'type\'] == E_ERROR)
    { file_put_contents(F_SITE_ROOT.\'php_fatal.log\', sprintf(\'E%d "%s" at %s:%d\', $a[\'type\'], $a[\'message\'], $a[\'file\'], $a[\'line\']));
    $i = ob_get_level(); while ($i--) @ob_end_clean(); print \'Fatal error. Sorry :(\'; }'));
// here we set an error catcher
set_error_handler(create_function('$c, $m, $f, $l', 'throw new ErrorException($m, 0, $c, $f, $l);'),
    E_ALL & ~(E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING | E_STRICT));


// includes
$base_modules_files = Array(
    F_KERNEL_DIR.'k3_misc.php',          // kernel 2 classes and functions library
    F_KERNEL_DIR.'k3_timer.php',         // kernel 2 basic classes
    F_KERNEL_DIR.'k3_cache.php',         // kernel 2 cacher class
    F_KERNEL_DIR.'k3_strings.php',       // kernel 2 strings parsing
    F_KERNEL_DIR.'k3_http.php',          // kernel 2 HTTP interface
    F_KERNEL_DIR.'k3_request.php',       // kernel 2 GPC interface
    F_KERNEL_DIR.'k3_lang.php',          // kernel 2 LNG interface
    F_KERNEL_DIR.'k3_dbase.php',         // kernel 2 database interface
    //F_KERNEL_DIR.'k3_session.php',       // kernel 2 session extension
);
// we'll do some trick with caching base modules in one file
$base_modules_stats = Array();
foreach ($base_modules_files as $fname)
    $base_modules_stats[] = filemtime($fname).'|'.filesize($fname);
$base_modules_stats = md5(implode('|', $base_modules_stats));
$base_modules_file  = F_KERNEL_DIR.'k3_bases-'.$base_modules_stats.'.krninc';
if (file_exists($base_modules_file))
    require_once($base_modules_file);
else
{
    foreach (scandir(F_KERNEL_DIR) as $fname)
        if (preg_match('#^k3_bases\-[0-9a-fA-F]{32}\.krninc$#', $fname))
            unlink(F_KERNEL_DIR.$fname);
    $base_modules_eval = '';
    foreach ($base_modules_files as $fname)
        $base_modules_eval.= preg_replace('#^\s*\<\?php\s*|^\s*\<\?\s*|\?\>\s*$#D', '', php_strip_whitespace($fname));
    if (file_put_contents($base_modules_file, "<?php\n".$base_modules_eval."\n?>"))
        eval($base_modules_eval);
    else
    {
        trigger_error('Kernel: Error creating base modules cache.', E_USER_WARNING);
        foreach ($base_modules_files as $fname)
            require_once($fname);
    }
    unset($base_modules_eval);
}
unset($base_modules_files, $base_modules_stats, $base_modules_file);

// the main kernel class
class F extends FEventDispatcher
{    // internal encoding (usually UTF-8)
    const INTERNAL_ENCODING = F_INTERNAL_ENCODING;
    const KERNEL_DIR = F_KERNEL_DIR;

    static private $ERR_TYPES = Array(
        E_ERROR        => 'PHP ERROR',
        E_WARNING      => 'PHP WARNING',
        E_NOTICE       => 'PHP NOTICE',
        E_USER_ERROR   => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE  => 'USER NOTICE',
        E_STRICT       => 'PHP5 STRICT',
        );

    static private $self = null;
    private $clfiles = Array();
    private $classes = Array();
    private $clclose = Array();

    private function __construct()
    {        $this->pool['Timer'] = new FTimer();
        $this->pool['Cache'] = new StaticInstance('FCache');
        $this->pool['Str']   = new StaticInstance('FStr');
        $this->pool['HTTP']  = FHTTPInterface::getInstance();
        $this->pool['GPC']   = new StaticInstance('FGPC');
        $this->pool['LNG']   = FLNGData::getInstance();

        //$this->pool['DBase'] = new FDataBase();
        $this->classes['DBase'] = 'FDataBase';
        //$this->classes['Session'] =
        //$this->classes['Sess'] = 'FSession';

        set_exception_handler(Array($this, 'handleException'));
        set_error_handler(Array($this, 'logError'), E_ALL & ~(E_NOTICE | E_USER_NOTICE | E_STRICT));

        $this->pool['LNG']->_Start();

        if ($CL_Config = FMisc::loadDatafile(self::KERNEL_DIR.'modules.qfc', FMisc::DF_SLINE, false, '|'))
        {
            foreach ($CL_Config as $mod => $cfg)
            {
                $this->classes[$mod] = ($cfg[1]) ? array_shift($cfg) : 'F'.$mod;
                $this->clfiles[$mod] = self::KERNEL_DIR.array_shift($cfg);
            }
        }
    }

    public static function kernel($name = null)
    {        if (!self::$self)
            self::$self = new F();
        return is_null($name)
            ? self::$self
            : self::$self->__get($name);
    }

    public function ping($name)
    {
        return isset($this->pool[$name]);
    }

    public function runModule($mod_name)
    {
        if (preg_match('#\W#', $mod_name))
            return false;

        if (!isset($this->classes[$mod_name]))
            return false;

        $mod_class = $this->classes[$mod_name];

        if (isset($this->pool[$mod_name]))
            return ($this->pool[$mod_name] instanceof $mod_class);

        if (!class_exists($mod_class) && isset($this->clfiles[$mod_name]))
        {
            $mod_file = $this->clfiles[$mod_name];
            if (file_exists($mod_file))
                include_once($mod_file);
            else
                trigger_error('Kernel: can\'t locate "'.$mod_file.'" module file', E_USER_ERROR);
        }

        if (class_exists($mod_class))
        {
            $res = method_exists($mod_class, 'getInstance')
                ? (eval('$this->pool[$mod_name] = '.$mod_class.'::getInstance();') !== false)
                : ($this->pool[$mod_name] = new $mod_class());

            if ($res)
            {                if (method_exists($mod_class, '_Start'))
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

    public function handleException(Exception $e)
    {        $logfile = F_SITE_ROOT.'fatal.log';
        $eName = get_class($e).(($e instanceof ErrorException) ? '['.self::$ERR_TYPES[$e->getSeverity()].']' : '');
        if ($logfile = fopen($logfile, 'ab'))
        {
            fwrite($logfile, date('[d M Y H:i]').' '.$eName.': '.$e->getMessage().'. File: '.$e->getFile().'. Line: '.$e->getLine().".\r\n".$e->getTraceAsString().".\r\n");
            fclose($logfile);
        }

        FMisc::obFree();
        header ($_SERVER["SERVER_PROTOCOL"].' 503 Service Unavailable');
        print '<html><head><title>'.F('LNG')->lang('ERR_CRIT_PAGE', false, true).'</title></head><body><h1>'.F('LNG')->lang('ERR_CRIT_PAGE', false, true).'</h1>'.F('LNG')->lang('ERR_CRIT_MESS', false, true).'</body></html>';
    }

    public function logError($c, $m, $f = '', $l = 0)
    {
        static $logfile = null;
        if ($c & ~(E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_STRICT))
            throw new ErrorException($m, 0, $c, $f, $l);
        if ($logfile == null)
            $logfile = fopen(F_SITE_ROOT.'error.log', 'ab');
        $eName = isset(self::$ERR_TYPES[$c]) ? '['.self::$ERR_TYPES[$c].']' : '[UNKNOWN ERROR]';
        if ($logfile)
            fwrite($logfile, date('[d M Y H:i]').' '.$eName.': '.$m.'. File: '.$f.'. Line: '.$l.".\r\n".FStr::PHPDefine(array_slice(debug_backtrace(),1)).".\r\n");
    }

    public function __get($name)
    {        if (isset($this->pool[$name]))
            return $this->pool[$name];
        elseif (isset($this->classes[$name]) && $this->runModule($name))
            return $this->pool[$name];

        return new FNullObject('F(\''.$name.'\')');
    }

    public function __call($name, $arguments)
    {         if (isset($this->pool[$name]) || $this->runModule($name))
             if (method_exists($this->pool[$name], '_Call'))
                 return call_user_func_array(Array(&$this->pool[$name],  '_Call'), $arguments);
         return null;
    }
}

function F($name = null) { return F::kernel($name); }
$GLOBALS['QF'] = $GLOBALS['F'] = F();

?>