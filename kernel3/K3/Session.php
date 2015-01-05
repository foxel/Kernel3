<?php
/**
 * Copyright (C) 2012 - 2015 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Session
 *
 * @author Foxel
 */
class K3_Session extends K3_Environment_Element
{
    const EVENT_CREATE  = 'created';
    const EVENT_LOAD    = 'loaded';
    const EVENT_PREOPEN = 'preopen';
    const EVENT_OPEN    = 'opened';
    const EVENT_PRESAVE = 'presave';

    protected $SID       = '';           // Session ID

    protected $clicks    = 1;            // session clicks stats

    protected $mode      = 0;            // status
    protected $tried     = false;        // if the session loading try was performed

    protected $securityLevel = 3;        // security level for client signature used

    /** @var FDataBase */
    protected $dbObject = null;
    /** @var string */
    protected $dbTableName = 'sessions';

    const SID_NAME     = 'FSID';
    const LIFETIME     = 3600;
    const CACHE_PREFIX = 'F_SESSIONS.';

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
        if (is_null($dbo)) {
            $dbo = F()->DBase;
        }

        if (!$dbo || !$dbo->check() || ($this->mode & self::MODE_STARTED)) {
            return false;
        }

        $this->dbObject = $dbo;
        if (is_string($tbname) && $tbname) {
            $this->dbTableName = $tbname;
        }

        $this->mode |= self::MODE_DBASE;

        return true;
    }

    public function open($mode = 0)
    {
        if ($this->mode & self::MODE_STARTED) {
            return true;
        }

        $oldMode = $this->mode;

        $this->mode |= $mode & (self::MODES_ALLOW);

        $this->SID = $this->env->client->getCookie(self::SID_NAME);
        if ($ForceSID = $this->env->request->getString('ForceFSID', K3_Request::POST, K3_Util_String::FILTER_HEX)) {
            $this->SID = $ForceSID;
        }

        if (!$this->SID) {
            $this->mode |= self::MODE_URLS;
            $this->SID = $this->env->request->getString(self::SID_NAME, K3_Request::ALL, K3_Util_String::FILTER_HEX);
        }

        if (!$this->SID || $this->tried || !$this->load()) {
            if (!($this->mode & self::MODE_TRY)) {
                $this->create();
            }
        }


        if ($this->mode & self::MODE_STARTED) {
            // allows mode changes and any special reactions
            $this->throwEventRef(self::EVENT_PREOPEN, $this->mode, $this->SID);

            if (!($this->mode & self::MODE_FIXED)) {
                $this->env->client->setCookie(self::SID_NAME, $this->SID);
            }

            // allows mode changes and any special reactions
            $this->throwEventRef(self::EVENT_OPEN, $this->mode, $this->SID);

            // TODO: allowing from config
            if (!($this->mode & self::MODE_NOURLS) && ($this->mode & self::MODE_URLS)) {
                $this->env->response->addEventHandler(K3_Response::EVENT_HTML_PARSE, array($this, 'HTMLURLsAddSID'));
                $this->env->response->addEventHandler(K3_Response::EVENT_URL_PARSE, array($this, 'addSID'));
            }

            FMisc::addShutdownCallback(array(&$this, 'close'));

            return true;
        } else {
            $this->env->client->setCookie(self::SID_NAME);
        }

        $this->mode = $oldMode;
        return false;
    }

    private function load()
    {
        $this->tried = true;

        $sess = ($this->mode & self::MODE_DBASE)
            ? $this->dbObject->doSelect($this->dbTableName, '*', array('sid' => $this->SID))
            : FCache::get(self::CACHE_PREFIX.$this->SID);

        if (!is_array($sess) || !$sess) {
            return false;
        }

        if ($sess['ip'] != $this->env->client->IPInteger
            || $sess['lastused'] < ($this->env->clock->startTime - self::LIFETIME)
            || $sess['clsign'] != $this->env->client->getSignature($this->securityLevel)
        ) {
            FCache::drop(self::CACHE_PREFIX.$this->SID);
            return false;
        }

        $vars = unserialize($sess['vars']);

        $this->clicks = $sess['clicks'] + 1;

        $this->mode |= (self::MODE_STARTED | self::MODE_LOADED);
        $this->throwEventRef(self::EVENT_LOAD, $vars, $this->mode);

        $this->pool = is_array($vars)
            ? $vars
            : array();

        F()->Timer->logEvent('Session data loaded');

        return true;
    }

    private function create()
    {
        $this->SID    = md5(uniqid('SESS', true));
        $this->clicks = 1;


        $vars = array();

        $this->mode |= (self::MODE_STARTED | self::MODE_URLS); // TODO: MODEURLS only if it's allowed by config
        $this->throwEventRef(self::EVENT_CREATE, $vars, $this->mode);

        $this->pool = is_array($vars)
            ? $vars
            : array();

        F()->Timer->logEvent('Session data created');

        return true;
    }

    public function save()
    {
        if (!($this->mode & self::MODE_STARTED)) {
            return false;
        }

        if ($this->mode & self::MODE_FIXED) {
            return true;
        }

        $this->throwEventRef(self::EVENT_PRESAVE, $this->pool);

        $dataArray = array(
            'ip'       => $this->env->client->IPInteger,
            'clsign'   => $this->env->client->getSignature($this->securityLevel),
            'vars'     => serialize($this->pool),
            'lastused' => $this->env->clock->startTime,
            'clicks'   => $this->clicks,
        );

        if (!($this->mode & self::MODE_DBASE)) {
            $dataArray['sid'] = $this->SID;
            FCache::set(self::CACHE_PREFIX.$this->SID, $dataArray);
            return true;
        }

        // if using database
        if ($this->mode & self::MODE_LOADED) {
            $this->dbObject->doUpdate($this->dbTableName, $dataArray, array('sid' => $this->SID));
        } else {
            $dataArray['sid']       = $this->SID;
            $dataArray['starttime'] = $this->env->clock->startTime;
            $this->dbObject->doInsert($this->dbTableName, $dataArray, true);
        }

        // delete old session data
        $this->dbObject->doDelete($this->dbTableName, array('lastused' => '< '.($this->env->clock->startTime - self::LIFETIME)), FDataBase::SQL_USEFUNCS);

        return true;
    }

    public function getStatus($check = false)
    {
        return ($check ? $this->mode & $check : $this->mode);
    }

    public function getSID()
    {
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY))) {
            return null;
        }

        return $this->SID;
    }

    // session variables control
    public function get($query)
    {
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY))) {
            return null;
        }

        $names = explode(' ', $query);
        if (count($names) > 1) {
            $out = array();
            foreach ($names as $name) {
                $out[$name] = (isset($this->pool[$name])) ? $this->pool[$name] : null;
            }

            return $out;
        } else {
            return (isset($this->pool[$query])) ? $this->pool[$query] : null;
        }
    }

    public function set($name, $val)
    {
        if (!($this->mode & self::MODE_STARTED) && !$this->open()) {
            return false;
        }

        return ($this->pool[$name] = $val);
    }

    public function drop($query)
    {
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY))) {
            return false;
        }

        $names = explode(' ', $query);
        if (count($names)) {
            foreach ($names as $name) {
                unset ($this->pool[$name]);
            }
        }

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
        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY))) {
            return false;
        }

        $this->pool = array();

        return true;
    }

    public function addSID($url, $ampersand = false)
    {
        $url = trim($url);

        if (!($this->mode & self::MODE_STARTED) && ($this->tried || !$this->open(self::MODE_TRY))) {
            return $url;
        }

        $url = K3_Util_Url::urlAddParam($url, self::SID_NAME, $this->SID, $ampersand);

        return $url;
    }

    public function HTMLURLsAddSID(&$buffer)
    {
        if (!($this->mode & self::MODE_STARTED) || !($this->mode & self::MODE_URLS)) {
            return $buffer;
        }

        $buffer = preg_replace_callback('#(<(a|form)\s+[^>]*)(href|action)\s*=\s*(\"([^\"<>\(\)]*)\"|\'([^\'<>\(\)]*)\'|[^\s<>\(\)]+)#i', array(&$this, 'SIDParseCallback'), $buffer);
        //$buffer = preg_replace('#(<form [^>]*>)#i', "\\1\n".'<input type="hidden" name="'.self::SID_NAME.'" value="'.$this->SID.'" />', $buffer);

        return $buffer;
    }

    public function SIDParseCallback($vars)
    {
        if (!is_array($vars)) {
            return false;
        }

        if (isset($vars[6])) {
            $url    = $vars[6];
            $bounds = '\'';
        } elseif (isset($vars[5])) {
            $url    = $vars[5];
            $bounds = '"';
        } else {
            $url    = $vars[4];
            $bounds = '';
        }

        if (preg_match('#^\w+:#', $url) && strpos($url, F()->appEnv->server->rootUrl) !== 0) {
            // foreign url. no SID add
        } else {
            $url = K3_Util_Url::urlAddParam($url, self::SID_NAME, $this->SID, true);
        }

        return $vars[1].$vars[3].' = '.$bounds.$url.$bounds;

    }

    public function close()
    {
        if (!($this->mode & self::MODE_STARTED)) {
            return true;
        }

        return $this->save();
    }
}

