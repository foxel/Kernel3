<?php

class K3_Session extends K3_Environment_Element
{
    protected $SID       = '';           // Session ID

    protected $clicks    = 1;            // session clicks stats

    protected $mode      = 0;            // status
    protected $tried     = false;        // if the session loading try was performed

    protected $securityLevel  = 3;            // security level for client signature used

    protected $dbObject = null;
    protected $dbTableName = 'sessions';

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

    public function setDBase(FDataBase $dbo = null, $tbname = false)
    {
        if (is_null($dbo))
            $dbo = F()->DBase;

        if (!$dbo || !$dbo->check() || ($this->mode & self::MODE_STARTED))
            return false;

        $this->dbObject = $dbo;
        if (is_string($tbname) && $tbname)
            $this->dbTableName = $tbname;

        $this->mode |= self::MODE_DBASE;

        return true;
    }

    public function open($mode = 0)
    {
        if ($this->mode & self::MODE_STARTED)
            return true;

        $oldMode = $this->mode;

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

        $this->mode = $oldMode;
        return false;
    }

    private function load()
    {
        $this->tried = true;

        $sess = ($this->mode & self::MODE_DBASE)
                ? $this->dbObject->doSelect($this->dbTableName, '*', Array('sid' => $this->SID) )
                : FCache::get(self::CACHEPREFIX.$this->SID);

        if (!is_array($sess) || !$sess)
            return false;

        if ($sess['ip'] != $this->env->clientIPInteger
            || $sess['lastused'] < (F()->Timer->qTime() - self::LIFETIME)
            || $sess['clsign'] != $this->env->getClientSignature($this->securityLevel))
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

        $dataArray = Array(
            'ip'       => $this->env->clientIPInteger,
            'clsign'   => $this->env->getClientSignature($this->securityLevel),
            'vars'     => serialize($this->pool),
            'lastused' => F()->Timer->qTime(),
            'clicks'   => $this->clicks,
        );

        if (!($this->mode & self::MODE_DBASE))
        {
            $dataArray['sid'] = $this->SID;
            return FCache::set(self::CACHEPREFIX.$this->SID, $dataArray);
        }

        // if using database
        if ($this->mode & self::MODE_LOADED)
            $this->dbObject->doUpdate($this->dbTableName, $dataArray, Array('sid' => $this->SID) );
        else
        {
            $dataArray['sid'] = $this->SID;
            $dataArray['starttime'] = F()->Timer->qTime();
            $this->dbObject->doInsert($this->dbTableName, $dataArray, true);
        }

        // delete old session data
        $this->dbObject->doDelete($this->dbTableName, Array('lastused' => '< '.(F()->Timer->qTime() - self::LIFETIME)), FDataBase::SQL_USEFUNCS );

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

