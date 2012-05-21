<?php
/**
 * QuickFox kernel 3 'SlyFox' MPD Client class
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage extra
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

/**
 * @property string $host
 * @property bool   $connected
 * @property string $version
 * @property array  $status
 * @property string $state
 * @property int    $curTrackPos
 * @property int    $curTrackLen
 * @property int    $curTrack
 * @property int    $nextTrack
 * @property int    $volume
 * @property bool   $repeat
 * @property bool   $random
 * @property int    $uptime
 * @property int    $playtime
 * @property string $dbLastRefresh
 * @property int    $playlistCount
 * @property int    $numArtists
 * @property int    $numAlbums
 * @property int    $numSongs
 * @property array  $playlist
 * @property string $errStr
 * @property float  $lastQueryTime
 * @property float  $totalQueryTime
 */
class FMPC extends FBaseClass
{
    const DEFAULT_PORT = 6600;

    // TCP/Connection variables
    protected $host;
    protected $port;

    protected $mpd_sock   = null;
    protected $connected  = false;

    // MPD Status protectediables
    protected $version    = "(unknown)";

    protected $status = array();
    protected $state;
    protected $curTrackPos;
    protected $curTrackLen;
    protected $curTrack;
    protected $nextTrack;
    protected $volume;
    protected $repeat;
    protected $random;

    protected $uptime;
    protected $playtime;
    protected $dbLastRefresh;
    protected $playlistCount;
    
    protected $numArtists;
    protected $numAlbums;
    protected $numSongs;
    
    protected $playlist = array();
    protected $errStr   = ''; // last error message
    protected $lastQueryTime = 0;
    protected $totalQueryTime = 0;

    public function __construct($host, $port = self::DEFAULT_PORT, $password = null)
    {
        $this->host = (string) $host;
        $this->port = $port
            ? (int) $port
            : self::DEFAULT_PORT;

        // connecting
        $this->mpd_sock = fsockopen($this->host, $this->port, $errNo, $errStr, 10);
        
        if (!$this->mpd_sock)
            throw new FException('MPD Socket connection error #'.$errNo.': '.$errStr);
        
        if (is_null($helloResp = $this->_readResponse(true))) 
            throw new FException('MPD connection error '.$this->errStr);
        list($this->version) = sscanf(array_pop($helloResp), self::RESPONSE_OK.' MPD %s');

        if ($password && is_null($this->sendCommand(self::CMD_PASSWORD, $password)))
            throw new FException('MPD password error '.$this->errStr);
        
        $this->refreshInfo(true);
        $this->connected = true;

        $this->pool = array(
            'host' => &$this->host, 'port' => &$this->port,
            'connected'     => &$this->connected,
            'version'       => &$this->version,
            'status'        => &$this->status,
            'state'         => &$this->state,
            'curTrackPos'   => &$this->curTrackPos,
            'curTrackLen'   => &$this->curTrackLen,
            'curTrack'      => &$this->curTrack,
            'nextTrack'     => &$this->nextTrack,
            'volume'        => &$this->volume,
            'repeat'        => &$this->repeat,
            'random'        => &$this->random,
            'uptime'        => &$this->uptime,
            'playtime'      => &$this->playtime,
            'dbLastRefresh' => &$this->dbLastRefresh,
            'playlistCount' => &$this->playlistCount,
            'numArtists'    => &$this->numArtists,
            'numAlbums'     => &$this->numAlbums,
            'numSongs'      => &$this->numSongs,
            'playlist'      => &$this->playlist,
            'errStr'        => &$this->errStr,
            'lastQueryTime'  => &$this->lastQueryTime,
            'totalQueryTime' => &$this->totalQueryTime,
        );
    }

    public function refreshInfo($plRefresh = false)
    {
        // Get the Server Statistics
        if ($rawStats = $this->sendCommand(self::CMD_STATISTICS))
        {
            $stats = array();
            foreach ($rawStats as &$line) {
                list ($key, $value) = explode(': ', $line, 2);
                $stats[$key] = $value;
            } 
        }
        else
            return null;

        // Get the Server Status
        if ($rawStatus = $this->sendCommand(self::CMD_STATUS))
        {
            $this->status = array();
            foreach ($rawStatus as &$line) {
                list ($key, $value) = explode(': ', $line, 2);
                $this->status[$key] = $value;
            } 
        }
        else
            return null;

        // Get the Playlist
        if ($plRefresh && $rawPlaylist = $this->sendCommand(self::CMD_LIST))
            $this->playlist = $this->_parseListResponse($rawPlaylist, self::ITEM_FILE);
        $this->playlistCount = count($this->playlist);

        // Set Misc Other Variables
        $this->state = $this->status['state'];
        if (($this->state == self::STATE_PLAYING) || ($this->state == self::STATE_PAUSED)) 
        {
            $this->curTrack  = $this->status['song'];
            $this->nextTrack = $this->status['nextsong'];
            list ($this->curTrackPos, $this->curTrackLen) = explode(':', $this->status['time'], 2);
        } 
        else
        {
            $this->curTrack = -1;
            $this->curTrackPos = -1;
            $this->curTrackLen = -1;
            $this->nextTrack = -1;
        }

        $this->repeat = $this->status['repeat'];
        $this->random = $this->status['random'];

        $this->dbLastRefresh = $stats['db_update'];

        $this->volume = $this->status['volume'];
        $this->uptime = $stats['uptime'];
        $this->playtime = $stats['playtime'];
        $this->numArtists = $stats['artists'];
        $this->numSongs = $stats['songs'];
        $this->numAlbums = $stats['albums'];

        return true;
    }

    public function play($pos = null)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLAY, $pos)))
            $this->refreshInfo();
        return $res;
    }

    public function pause($state = null)
    {
        if (is_null($state)) // if no argument given - toggle pause
            $state = ($this->state == self::STATE_PLAYING);
        if ($res = !is_null($this->sendCommand(self::CMD_PAUSE, (int) $state)))
            $this->refreshInfo();
        return $res;
    }

    public function stop()
    {
        if ($res = !is_null($this->sendCommand(self::CMD_STOP)))
            $this->refreshInfo();
        return $res;
    }

    public function next()
    {
        if ($res = !is_null($this->sendCommand(self::CMD_NEXT)))
            $this->refreshInfo();
        return $res;
    }

    public function prev()
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PREV)))
            $this->refreshInfo();
        return $res;
    }

    public function seek($pos, $time = 0)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_SEEK, $pos, $time)))
            $this->refreshInfo();
        return $res;
    }

    public function setRandom($state)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_RANDOM, (int) $state)))
            $this->refreshInfo();
        return $res;
    }

    public function setRepeat($state)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_REPEAT, (int) $state)))
            $this->refreshInfo();
        return $res;
    }

    public function setConsume($state)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_CONSUME, (int) $state)))
            $this->refreshInfo();
        return $res;
    }

    public function setVolume($vol)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_SETVOL, (int) $vol)))
            $this->refreshInfo();
        return $res;
    }

    public function setCrossfade($val)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_CROSSFADE, (float) $val)))
            $this->refreshInfo();
        return $res;
    }
    
    
    public function add($file)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_ADD, $file)))
            $this->refreshInfo(true);
        return $res;
    }

    public function del($pos, $toPos = null)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_REMOVE, !is_null($toPos) ? $pos.':'.$toPos : $pos)))
            $this->refreshInfo(true);
        return $res;
    }

    public function move($from, $to)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_MOVETRACK, $from, $to)))
            $this->refreshInfo(true);
        return $res;
    }

    public function swap($pos1, $pos2)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_SWAPTRACK, $pos1, $pos2)))
            $this->refreshInfo(true);
        return $res;
    }

    public function shuffle($pos1 = null, $pos2 = null)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_SHUFFLE, (is_numeric($pos1) && is_numeric($pos2)) ? $pos1.':'.$pos2 : null)))
            $this->refreshInfo(true);
        return $res;
    }

    public function clear()
    {
        if ($res = !is_null($this->sendCommand(self::CMD_CLEAR)))
            $this->refreshInfo(true);
        return $res;
    }


    public function getPlaylists()
    {
        if ($rawPlaylists = $this->sendCommand(self::CMD_PLAYLISTS))
            return $this->_parseListResponse($rawPlaylists, self::ITEM_PLIST);
        return array();
    }

    public function getPlaylist($plName = null)
    {
        if (!$plName)
            return $this->playlist;
        if ($rawPlaylist = $this->sendCommand(self::CMD_PLLIST, $plName))
            return $this->_parseListResponse($rawPlaylist, self::ITEM_FILE);
        return array();
    }

    public function plLoad($plName)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLLOAD, $plName)))
            $this->refreshInfo(true);
        return $res;
    }
    
    public function plSave($plName)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLSAVE, $plName)))
            $this->refreshInfo();
        return $res;
    }

    public function plDrop($plName)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLDROP, $plName)))
            $this->refreshInfo();
        return $res;
    }

    public function plRename($plName, $newName)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLRENAME, $plName, $newName)))
            $this->refreshInfo();
        return $res;
    }
    
    public function plAdd($plName, $file)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLADD, $plName, $file)))
            $this->refreshInfo();
        return $res;
    }

    public function plDel($plName, $pos)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLREMOVE, $plName, $pos)))
            $this->refreshInfo();
        return $res;
    }

    public function plMove($plName, $from, $to)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLMOVETRACK, $plName, $from, $to)))
            $this->refreshInfo();
        return $res;
    }

    public function plClear($plName)
    {
        if ($res = !is_null($this->sendCommand(self::CMD_PLCLEAR)))
            $this->refreshInfo();
        return $res;
    }


    public function lsDir($dir = null, $filter = null)
    {
        if ($data = $this->sendCommand(self::CMD_LSDIR, $dir))
            return $this->_parseListResponse($data, $filter);
        return array();
    }

    public function lsAll($dir = null, $filter = null)
    {
        if ($data = $this->sendCommand(self::CMD_LSALL, $dir))
            return $this->_parseListResponse($data, $filter);
        return array();
    }
    
    public function refreshDB($dir = null, $rescan = false)
    {
        if ($res = !is_null($this->sendCommand($rescan ? self::CMD_RESCAN : self::CMD_REFRESH, $dir)))
            $this->refreshInfo();
        return $res;
    }

    public function search($type, $needle, $caseSence = false)
    {
        if ($data = $this->sendCommand($caseSence ? self::CMD_FIND : self::CMD_SEARCH, $type, $needle))
            return $this->_parseListResponse($data);
        return array();
    }

    public function listDB($type, $param = null)
    {
        if ($data = $this->sendCommand(self::CMD_TABLE, $type, $param))
            return $this->_parseSimpleListResponse($data, $type);
        return array();
    }

    public function sendCommand($cmd)
    {
        if (!$this->mpd_sock)
            throw new FException('MPD not connected');

        $args = array_slice(func_get_args(), 1);
        foreach ($args as $arg)
            if (strlen($arg))
                $cmd.= ' "'.$arg.'"';
        $qtime = microtime(true);
        fputs($this->mpd_sock, $cmd.PHP_EOL);
        $res = $this->_readResponse();
        $this->lastQueryTime = microtime(true) - $qtime;
        $this->totalQueryTime+= $this->lastQueryTime;
        
        return $res;
    }
    
    const RESPONSE_OK  = 'OK';
    const RESPONSE_ERR = 'ACK';
    protected function _readResponse($getOKLine = false)
    {
        $out = '';
        $this->errStr = '';
        while(!feof($this->mpd_sock)) {
            $data = fgets($this->mpd_sock, 1024);

            if ($data === false)
                throw new FException('MPD Socket reding error');

            // An OK signals the end of transmission -- we'll ignore it
            if (strpos($data, self::RESPONSE_OK) === 0)
            {
                if ($getOKLine)
                    $out.= $data;
                break;
            }

            // An ERR signals the end of transmission with an error! Let's grab the single-line message.
            if (strpos($data, self::RESPONSE_ERR) === 0)
            {
                $this->errStr = $data;
                return null;
            }

            // Build the response string
            $out.= $data;
        }
        
        return explode("\n", trim($out, "\n"));
    }

    protected function _parseListResponse(array $rawList, $itemType = null)
    {
        $list = array();
        $pos = -1;
        $found = false;
        $doFilter = in_array($itemType, self::$itemTypes);
        foreach ($rawList as &$line) {
            list ($key, $value) = explode(': ', $line, 2);
            if (in_array($key, self::$itemTypes) && $found = (boolean) (!$doFilter || $key == $itemType))
                $list[++$pos] = array($key => $value);
            elseif ($found)
                $list[$pos][strtolower($key)] = $value;
        } 
        return $list;
    }

    protected function _parseSimpleListResponse(array $rawList, $itemType = null)
    {
        $list = array();
        $pos = -1;
        $found = false;
        foreach ($rawList as &$line) {
            list ($key, $value) = explode(': ', $line, 2);
            if (!$itemType || strcasecmp($key, $itemType) == 0)
                $list[++$pos] = $value;
        } 
        return $list;
    }
    
    // MPD commands
    // status
    const CMD_STATUS =     'status';
    const CMD_STATISTICS = 'stats';
    // playback
    const CMD_PLAY =       'play';
    const CMD_STOP =       'stop';
    const CMD_PAUSE =      'pause';
    const CMD_NEXT =       'next';
    const CMD_PREV =       'previous';
    const CMD_SETVOL =     'setvol';
    const CMD_CONSUME =    'consume';
    const CMD_CROSSFADE =  'crossfade';
    const CMD_REPEAT =     'repeat';
    const CMD_RANDOM =     'random';
    // current playlist
    const CMD_LIST =       'playlistinfo';
    const CMD_ADD =        'add';
    const CMD_REMOVE =     'delete';
    const CMD_CLEAR =      'clear';
    const CMD_SHUFFLE =    'shuffle';
    const CMD_SWAPTRACK =  'swap';
    const CMD_MOVETRACK =  'move';
    // stored playlists
    const CMD_PLLIST =      'listplaylistinfo';
    const CMD_PLADD =       'playlistadd';
    const CMD_PLREMOVE =    'playlistdelete';
    const CMD_PLCLEAR =     'playlistclear';
    const CMD_PLMOVETRACK = 'playlistmove';
    const CMD_PLRENAME =    'rename';
    const CMD_PLDROP =      'rm';
    const CMD_PLAYLISTS =   'listplaylists';
    const CMD_PLLOAD =      'load';
    const CMD_PLSAVE =      'save';
    // DB commands
    const CMD_REFRESH =    'update';
    const CMD_RESCAN =     'rescan';
    const CMD_LSDIR =      'lsinfo';
    const CMD_LSALL =      'listallinfo';
    const CMD_SEARCH =     'search';
    const CMD_FIND =       'find';
    const CMD_SEEK =       'seek';
    const CMD_TABLE =      'list';
    // sys commands
    const CMD_PASSWORD =   'password';
    const CMD_KILL =       'kill';
    const CMD_START_BULK = 'command_list_begin';
    const CMD_END_BULK =   'command_list_end';

    // MPD State Constants
    const STATE_PLAYING = 'play';
    const STATE_STOPPED = 'stop';
    const STATE_PAUSED =  'pause';

    // MPD Searching Constants
    const SEARCH_ARTIST = 'artist';
    const SEARCH_TITLE =  'title';
    const SEARCH_ALBUM =  'album';

    // MPD Item Type Constants
    const ITEM_FILE  = 'file';
    const ITEM_DIR   = 'directory';
    const ITEM_PLIST = 'playlist';

    protected static $itemTypes = array(
        self::ITEM_FILE,
        self::ITEM_DIR,
        self::ITEM_PLIST,
        );

}

class FMPCFactory
{
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FMPCFactory();
        return self::$self;
    }

    private function __construct() {}

    public function create($host, $port = 6600, $password = null)
    {
        return new FMPC($host, $port, $password);
    }

    public function _Call($host, $port = 6600, $password = null)
    {
        return $this->create($host, $port, $password);
    }

}

