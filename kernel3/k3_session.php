<?php
/**
 * QuickFox kernel 3 'SlyFox' Session module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

class FSession extends FEventDispatcher
{
    private static $self = null;

    private $SID       = '';           // Session ID

    private $clicks    = 1;            // session clicks stats

    private $mode      = 0;            // status
    private $tried     = false;        // if the session loading try was performed

    private $secu_lvl  = 3;            // security level for client signature used 

    private $db_object = null;
    private $db_tbname = 'sessions';

    protected $env = null;

    const SID_NAME     = 'FSID';
    const LIFETIME     = 3600;
    const CACHEPREFIX  = 'F_SESSIONS.';

    const MODE_FIXED   = 1;
    const MODE_URLS    = 2;
    const MODE_TRY     = 4;
    const MODE_NOURLS  = 8;
    const MODE_STARTED = 16;
    const MODE_LOADED  = 32;
    const MODE_DBASE   = 64;

    // Modes mask for flags allowed to set with "open()"
    const MODES_ALLOW  = 15; // MODE_FIXED + MODE_URLS + MODE_TRY + MODE_NOURLS

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FSession();
        return self::$self;
    }

    private function __construct() { }
    
    public function setDBase(FDataBase $dbo = null, $tbname = false)
    {
        if (is_null($dbo))
            $dbo = F()->DBase;

        if (!$dbo || !$dbo->check() || ($this->mode & self::MODE_STARTED))
            return false;
            
        $this->db_object = $dbo;
        if (is_string($tbname) && $tbname)
            $this->db_tbname = $tbname;
        
        $this->mode |= self::MODE_DBASE;
        
        return true;
    }

    public function open($mode = 0, K3_Environment $env = null)
    {
        if ($this->mode & self::MODE_STARTED)
            return true;

        $this->env = is_null($env) ? F()->appEnv : $env;

        $old_mode = $this->mode;

        $this->mode |= $mode & (self::MODES_ALLOW);

        $this->SID = $this->env->getCookie(self::SID_NAME);
        if ($ForceSID = $this->env->request->getString('ForceFSID', K3_Request::POST, FStr::HEX))
            $this->SID = $ForceSID;

        if (!$this->SID)
        {
            $this->mode |= self::MODE_URLS;
            $this->SID = $this->env->request->getString(self::SID_NAME, K3_Request::ALL, FStr::HEX);
        } 

        if (!$this->SID || $this->tried || !$this->load())
        {
            if (!($this->mode & self::MODE_TRY)) 
                $this->create();
        }


        if ($this->mode & self::MODE_STARTED)
        {
            // allows mode changes and any special reactions
            $this->throwEventRef('preopen', $this->mode, $this->SID);

            if (!($this->mode & self::MODE_FIXED))
                $this->env->setCookie(self::SID_NAME, $this->SID);

            // allows mode changes and any special reactions
            $this->throwEventRef('opened', $this->mode, $this->SID);

            if (!($this->mode & self::MODE_NOURLS) && ($this->mode & self::MODE_URLS)) // TODO: allowing from config
            {
                $this->env->response->addEventHandler('HTML_parse', Array($this, 'HTMLURLsAddSID') );
                $this->env->response->addEventHandler('URL_Parse', Array($this, 'addSID') );
            }

            FMisc::addShutdownCallback(Array(&$this, 'close'));

            return true;
        }
        else
            $this->env->setCookie(self::SID_NAME);
        
        $this->mode = $old_mode;
        return false;
    }

    private function load()
    {
        $this->tried = true;
        
        $sess = ($this->mode & self::MODE_DBASE)
                ? $this->db_object->doSelect($this->db_tbname, '*', Array('sid' => $this->SID) )
                : FCache::get(self::CACHEPREFIX.$this->SID);

        if (!is_array($sess) || !$sess)
            return false;
            
        if ($sess['ip'] != $this->env->clientIPInteger 
            || $sess['lastused'] < (F()->Timer->qTime() - self::LIFETIME)
            || $sess['clsign'] != $this->env->getClientSignature($this->secu_lvl))
        {
            FCache::drop(self::CACHEPREFIX.$this->SID);
            return false;       
        }

        $vars = unserialize($sess['vars']);

        $this->clicks = $sess['clicks'] + 1;

        $this->mode |= (self::MODE_STARTED | self::MODE_LOADED);
        $this->throwEventRef('loaded', $vars, $this->mode);

        $this->pool = is_array($vars)
            ? $vars
            : Array();

        F()->Timer->logEvent('Session data loaded');

        return true;
    }

    private function create()
    {
        $this->SID    = md5(uniqid('SESS', true));
        $this->clicks = 1;


        $vars = Array();
        
        $this->mode |= (self::MODE_STARTED | self::MODE_URLS); // TODO: MODEURLS only if it's allowed by config
        $this->throwEventRef('created', $vars, $this->mode);
        
        $this->pool = is_array($vars)
            ? $vars
            : Array();

        F()->Timer->logEvent('Session data created');

        return true;
    }

    public function save()
    {
        if (!($this->mode & self::MODE_STARTED))
            return false;

        if ($this->mode & self::MODE_FIXED)
            return true;

        $this->throwEventRef('presave', $this->pool);

        $q_arr = Array(
            'ip'       => $this->env->clientIPInteger,
            'clsign'   => $this->env->getClientSignature($this->secu_lvl),
            'vars'     => serialize($this->pool),
            'lastused' => F()->Timer->qTime(),
            'clicks'   => $this->clicks,
            );

        if (!($this->mode & self::MODE_DBASE))
        {
            $q_arr['sid'] = $this->SID;
            return FCache::set(self::CACHEPREFIX.$this->SID, $q_arr);
        }

        // if using database
        if ($this->mode & self::MODE_LOADED)
            $this->db_object->doUpdate($this->db_tbname, $q_arr, Array('sid' => $this->SID) );
        else
        {
            $q_arr['sid'] = $this->SID;
            $q_arr['starttime'] = F()->Timer->qTime();
            $this->db_object->doInsert($this->db_tbname, $q_arr, true);
        }

        // delete old session data
        $this->db_object->doDelete($this->db_tbname, Array('lastused' => '< '.(F()->Timer->qTime() - self::LIFETIME)), FDataBase::SQL_USEFUNCS );

        return true;
    }

    public function getStatus($check = false)
    {
        return ($check ? $this->mode & $check : $this->mode);
    }

    // session variables control
    public function get($query)
    {
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY)))
            return null;

        $names = explode(' ', $query);
        if (count($names)>1)
        {
            $out = Array();
            foreach ($names as $name)
                $out[$name] = (isset($this->pool[$name])) ? $this->pool[$name] : null;

            return $out;
        }
        else
            return (isset($this->pool[$query])) ? $this->pool[$query] : null;
    }
    
    public function set($name, $val)
    {
        if (!($this->mode & self::MODE_STARTED) && !$this->open())
            return false;

        return ($this->pool[$name] = $val);
    }

    public function drop($query)
    {
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY)))
            return false;

        $names = explode(' ', $query);
        if (count($names)) 
            foreach ($names as $name)
                unset ($this->pool[$name]);

        return true;
    }

    public function __get($name)
    {
        return $this->get($name);
    }
    
    public function __set($name, $val)
    {
        return $this->set($name, $val);
    }
    
    public function __unset($name)
    {
        return $this->drop($name);
    }
    
    // totally clears session data
    public function clear()
    {
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY)))
            return false;

        $this->pool = Array();

        return true;
    }

    public function addSID($url, $ampersand = false)
    {
        $url = trim($url);

        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY)))
            return $url;

        $url = FStr::urlAddParam($url, self::SID_NAME, $this->SID, $ampersand);

        return $url;
    }

    public function HTMLURLsAddSID(&$buffer)
    {
        if (!($this->mode & self::MODE_STARTED) || !($this->mode & self::MODE_URLS))
            return $buffer;

        $buffer = preg_replace_callback('#(<(a|form)\s+[^>]*)(href|action)\s*=\s*(\"([^\"<>\(\)]*)\"|\'([^\'<>\(\)]*)\'|[^\s<>\(\)]+)#i', Array(&$this, 'SIDParseCallback'), $buffer);
        //$buffer = preg_replace('#(<form [^>]*>)#i', "\\1\n".'<input type="hidden" name="'.self::SID_NAME.'" value="'.$this->SID.'" />', $buffer);
    }

    public function SIDParseCallback($vars)
    {
        if (!is_array($vars))
            return false;

        if (isset($vars[6]))
        {
            $url = $vars[6];
            $bounds = '\'';
        }
        elseif (isset($vars[5]))
        {
            $url = $vars[5];
            $bounds = '"';
        }
        else
        {
            $url = $vars[4];
            $bounds = '';
        }

        if (preg_match('#^\w+:#', $url))
            if (strpos($url, F()->appEnv->rootUrl) !== 0)
                return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

        $url = FStr::urlAddParam($url, self::SID_NAME, $this->SID, true);

        return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

    }

    public function close()
    {
        if (!($this->mode & self::MODE_STARTED))
            return true;

        return $this->save();
    }
}

function FSession()
{
    return FSession::getInstance();
}

function Session()
{
    return FSession::getInstance();
}

?>
