<?php


class FSession extends FEventDispatcher
{
    private static $self = null;

    private $SID        = '';           // Session ID
    private $sess_data  = Array();      // session variables

    private $clicks     = 1;            // session clicks stats

    private $started    = false;        // if the session is started
    private $loaded     = false;        // if the session is loaded

    private $mode       = 0;            // status

    private $sess_dbase = null;
    private $secu_lvl  = 3;

    private $db_object = null;
    private $db_tbname = 'sessions';

    const SID_NAME     = 'FSID';
    const LIFETIME     = 3600;
    const CACHEPREFIX  = 'F_SESSIONS.';

    const MODE_FIXED   = 1;
    const MODE_URLS    = 2;
    const MODE_STARTED = 4;
    const MODE_LOADED  = 8;
    const MODE_DBASE  = 16;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FSession();
        return self::$self;
    }

    private function __construct()
    {
        $this->pool =& $this->sess_data;
    }

    public function open($mode = 0, $no_url_mods = false)
    {
        if ($this->mode & self::MODE_STARTED)
            return true;

        $this->mode = $mode;

        $this->SID = F('HTTP')->getCookie(self::SID_NAME);
        if ($ForceSID = FGPC::getString('ForceQFSID', FGPC::POST, FStr::HEX))
            $this->SID = $ForceSID;

        if (!$this->SID)
        {
            $this->mode |= self::MODE_URLS;
            $this->SID = FGPC::getString(self::SID_NAME, FGPC::ALL, FStr::HEX);
        }

        if ($this->SID)
            $this->load();
        else
            $this->create();


        if ($this->mode & self::MODE_STARTED)
        {
            // allows mode changes and any special reactions
            $this->throwEventRef('preopen', $this->mode, $this->SID);

            if (!($this->mode & self::MODE_FIXED))
                F('HTTP')->setCookie(self::SID_NAME, $this->SID);

            // allows mode changes and any special reactions
            $this->throwEventRef('opened', $this->mode, $this->SID);

            if (!$no_url_mods && ($this->mode & self::MODE_URLS)) // TODO: && $QF->Config->Get('sid_urls', 'session', true))
            {
                F('HTTP')->addEventHandler('HTML_parse', Array(&$this, 'HTMLURLsAddSID') );
                F('HTTP')->addEventHandler('URL_Parse', Array(&$this, 'addSID') );
            }

        }

        return true;
    }

    private function load()
    {
        if ($this->mode & self::MODE_STARTED)
            return true;

        $sess = ($this->mode & self::MODE_DBASE)
                ? $this->db_object->doSelect($this->bd_tbname, '*', Array('sid' => $this->SID) )
                : FCache->get(self::CACHEPREFIX.$this->SID);

        if ($sess)
        {
            if ($sess['ip'] != F('HTTP')->IPInt)
                $sess = null;
            if ($sess['lastused'] < (F('Timer')->qTime - self::LIFETIME))
                $sess = null;
        }
        else
            $sess = null;


        if (is_array($sess))
        {
            $this->sess_data = unserialize($sess['vars']);

            $this->clicks   = $sess['clicks'] + 1;

            F('Timer')->logEvent('Session data loaded');

            $this->mode |= (self::MODE_STARTED | self::MODE_LOADED);
            $this->throwEventRef('loaded', $this->sess_data, $this->mode);

            return true;
        }
        else
            return $this->create();

    }

    private function create()
    {
        if ($this->mode & self::MODE_STARTED)
            return true;

        $this->SID       = md5(uniqid('SESS', true));
        $this->clicks    = 1;
        $this->sess_data = Array();


        F('Timer')->qTime_Log('Session data created');

        $this->Cache_Clear();

        $this->mode |= (self::MODE_STARTED | self::MODE_URLS);
        $this->throwEventRef('created', $this->sess_data, $this->mode );

        return true;
    }

    public function save()
    {
        Global $QF;

        if (!($this->mode & self::MODE_STARTED))
            return false;

        if ($this->mode & self::MODE_FIXED)
            return true;

        $this->throwEventRef('session_save', $this->sess_data);

        $q_arr = Array(
            'ip'       => $QF->HTTP->IP_int,
            'vars'     => serialize($this->sess_data),
            'lastused' => F('Timer')->qTime,
            'clicks'   => $this->clicks,
            );

        if (!($this->mode & self::MODE_DBASE))
        {
            $q_arr['sid'] = $this->SID;
            return FCache::set(self::CACHEPREFIX.$this->SID, $q_arr);
        }

        // if using database
        if ($this->mode & self::MODE_LOADED)
            $this->db_object->doUpdate($this->bd_tbname, $q_arr, Array('sid' => $this->SID) );
        else
        {
            $q_arr['sid'] = $this->SID;
            $q_arr['starttime'] = F('Timer')->qTime;
            $this->db_object->doInsert($this->bd_tbname, $q_arr, true);
        }

        // delete old session data
        $this->db_object->doDelete($this->bd_tbname, Array('lastused' => '< '.(F('Timer')->qTime - self::LIFETIME)), QF_SQL_USEFUNCS );

        return true;
    }

    public function getStatus($check = false)
    {
        return ($check ? $this->mode & $check : $this->mode);
    }

    // session variables control
    public function get($query)
    {
        if (!($this->mode & self::MODE_STARTED))
            return null;

        $names = explode(' ', $query);
        if (count($names)>1)
        {
            $out = Array();
            foreach ($names as $name)
                $out[$name] = (isset($this->sess_data[$name])) ? $this->sess_data[$name] : null;

            return $out;
        }
        else
            return (isset($this->sess_data[$query])) ? $this->sess_data[$query] : null;
    }

    public function set($name, $val)
    {
        if (!($this->mode & self::MODE_STARTED))
            return false;

        return ($this->sess_data[$name] = $val);
    }

    public function drop($query)
    {
        if (!($this->mode & self::MODE_STARTED))
            return false;

        $names = explode(' ', $query);
        if (count($names)) {
            $out = Array();
            foreach ($names as $name)
                unset ($this->sess_data[$name]);
            return true;
        }
        else
            unset ($this->sess_data[$query]);
        return true;
    }

    // totally clears session data
    public function clear()
    {
        if (!($this->mode & self::MODE_STARTED))
            return false;

        $this->sess_data = Array();

        return true;
    }

    public function addSID($url, $ampersand=false)
    {
        $url=trim($url);

        if (!($this->mode & self::MODE_STARTED))
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
        Global $QF;
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
            if (strpos($url, F('HTTP')->rootUrl) !== 0)
                return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

        $url = FStr::urlAddParam($url, self::SID_NAME, $this->SID, true);

        return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

    }

    function _Close()
    {
        if (!($this->mode & self::MODE_STARTED))
            return false;

        $this->save();
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