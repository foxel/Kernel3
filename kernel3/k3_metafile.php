<?php
/**
 * QuickFox kernel 3 'SlyFox' MetaFile Class
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage extra
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

final class FMetaFileFactory
{
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FMetaFileFactory();
        return self::$self;
    }

    private function __construct() {}

    public function _Call($cluster = 1, $fillchr = "\0") { return new FMetaFile($cluster, $fillchr); }
    public function create($cluster = 1, $fillchr = "\0") { return new FMetaFile($cluster, $fillchr); }
    public function createTar($root_link = false) { return new FMetaTar($root_link); }
    public function save(FMetaFile $file, $filename) { return (bool) file_put_contents($filename, serialize($file)); }
    public function load($filename) { $o = unserialize(file_get_contents($filename)); return is_a($o, 'FMetaFile') ? $o : new FNullObject(); }
    public function cacheSave(FMetaFile $file, $cachename) { return (bool) FCache::set($cachename, serialize($file)); }
    public function cacheLoad($cachename) { $o = unserialize(FCache::get($cachename)); return is_a($o, 'FMetaFile') ? $o : new FNullObject(); }
}

class FMetaFilePart
{
    public $o = null;
    public $s = 0;
    public $l = 0;
    public $p = 0;
}

class FMetaFile extends FDataStream
{
    private $parts = Array();
    private $sel_part = -1;
    private $pos = 0;
    private $fsize = 0;
    private $cluster = 1;
    private $fillchr = "\0";
    private $mtime = 0;
    public function __construct($cluster = 1, $fillchr = "\0")
    {
        $this->filename = 'foo';
        $this->fsize = 0;
        $this->pos = 0;
        $this->sel_part = -1;
        $this->cluster = $cluster;
        $this->mtime = time();
        if (is_string($fillchr))
            $this->fillchr = $fillchr[0];
        elseif (is_int($fillchr))
            $this->fillchr = chr($fillchr & 0xff);
    }

    public function add(FDataStream $part)
    {
        if (!$part->size())
            return false;
        $i = count($this->parts);
        $this->parts[$i] = $p = new FMetaFilePart();
        $p->o = $part;
        $p->l = $part->size();
        if ($this->cluster > 0 && $p->l%$this->cluster)
            $p->l = intval($p->l/$this->cluster+1)*$this->cluster;
        $p->p = $this->fsize;
        $this->fsize+= $p->l;
        return true;
    }

    public function open($mode = 'rb')
    {
        if (!$this->parts)
            return false;
        if ($this->sel_part >= 0 && $this->sel_part < count($this->parts))
            $this->parts[$this->sel_part]->o->close();
        return $this->parts[$this->sel_part = 0]->o->open($this->mode = 'rb');
    }

    public function close()
    {
        if (!$this->parts || $this->sel_part < 0 || $this->sel_part >= count($this->parts))
            return false;
        return $this->parts[$this->sel_part]->o->close();
    }

    public function EOF()
    {
        if (!$this->parts || $this->sel_part < 0 || $this->sel_part >= count($this->parts))
            return true;
        return (($this->sel_part == count($this->parts)-1) && $this->parts[$this->sel_part]->o->EOF());
    }

    public function size() { return $this->fsize; }

    public function read(&$data, $len)
    {
        if (!$this->parts || $this->sel_part < 0 || $this->sel_part >= count($this->parts))
            return false;

        $adata = $data = '';
        while ($len)
        {
            $rlen = min($len, $this->parts[$this->sel_part]->l - ($this->pos - $this->parts[$this->sel_part]->p));
            $glen = $this->parts[$this->sel_part]->o->read($adata, $rlen);
            if ($glen < $rlen)
                $adata.= str_repeat($this->fillchr, $rlen - $glen);
            $len-= $rlen;
            $this->pos+= $rlen;
            $data.= $adata;
            if ($len)
            {
                $this->parts[$this->sel_part++]->o->close();
                if ($this->sel_part >= count($this->parts))
                    break;
                $this->parts[$this->sel_part]->o->open($this->mode);
            }
        }
        return strlen($data);
    }

    public function seek($pos)
    {
        if (!$this->parts || $this->sel_part < 0 || $pos >= $this->fsize)
            return false;

        if ($this->sel_part < count($this->parts))
            $this->parts[$this->sel_part]->o->close();

        $this->sel_part = 0;
        while ($this->parts[$this->sel_part]->p + $this->parts[$this->sel_part]->l <= $pos)
            $this->sel_part++;
        $this->parts[$this->sel_part]->o->open($this->mode);
        if ($this->parts[$this->sel_part]->o->seek($pos - $this->parts[$this->sel_part]->p))
        {
            $this->pos = $pos;
            return true;
        }
        return false;
    }

    public function write($data)
    {
        return null;
    }
    
    public function mtime() { return $this->mtime; }

    public function toFile($filename)
    {
        if (!$filename)
            return false;

        if ($out = fopen($filename, 'wb'))
        {
            $this->open();
            $buff = '';
            while($this->read($buff, 1048576)) // 1MB
                fwrite($out, $buff);
            fclose($out);
            return true;
        }

        return false;
    }
}

class FMetaTar extends FMetaFile
{
    private $conts = Array();
    private $root_link = '';
    public function __construct($root_link = false)
    {
        parent::__construct(512, "\0");
        $this->conts = Array();
        $this->root_link = preg_replace('#^(\\\\|/)#', '', FStr::cast($root_link ? $root_link : F_SITE_ROOT, FStr::UNIXPATH)).DIRECTORY_SEPARATOR;
    }

    // Packs a real file to archive
    public function add($filename, $pack_to = '', $force_mode = '', $force_fmode = '', $ch_callback = null)
    {
        if (!is_file($filename) && !is_dir($filename))
            return false;

        if (!$pack_to)
        {
            $pack_to = preg_replace('#^(\\\\|/)#', '', FStr::cast($filename, FStr::UNIXPATH));
            if ($this->root_link)
                $pack_to = preg_replace('#^('.preg_quote($this->root_link, '#').')#', '', $pack_to);
        }
        else
            $pack_to = preg_replace('#^(\\\\|/)#', '', FStr::cast($pack_to, FStr::UNIXPATH));

        if (in_array($pack_to, $this->conts))
            return false;

        if ($dir = dirname($pack_to))
            if (!in_array($dir, $this->conts))
            {
                $dir_perms = decoct(fileperms(dirname($filename)));
                $this->makeDir($dir, $dir_perms);
            }

        $header = Array(
            'name'  => $pack_to,
            'mode'  => decoct(fileperms($filename)),
            'uid'   => fileowner($filename),
            'gid'   => filegroup($filename),
            'size'  => is_file($filename) ? filesize($filename) : 0,
            'time'  => filemtime($filename),
            'type'  => is_file($filename) ? 0 : 5,
            );

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $header = $this->makeRawHeader($header);
        parent::add(new FStringStream($header));

        $this->conts[] = $pack_to;

        if (is_file($filename))
            parent::add(new FFileStream($filename));
        elseif ($odir = opendir($filename))
        {
            if (!is_callable($ch_callback))
                $ch_callback = null;

            while ($dfile = readdir($odir))
                if ($dfile != '.' && $dfile != '..')
                {
                    $ffile = $filename.DIRECTORY_SEPARATOR.$dfile;
                    if (!$ch_callback || call_user_func($ch_callback, $ffile))
                        $this->add($ffile, $pack_to.DIRECTORY_SEPARATOR.$dfile, is_file($ffile) ? $force_fmode : $force_mode, $force_fmode, $ch_callback);
                }

            closedir($odir);
        }

        return true;
    }

    // Packs a datastring as file
    public function addData($inp, $pack_to = '', $force_mode = '')
    {
        static $packd_id = 1;

        if (!strlen($inp))
            return false;

        if (!$pack_to)
            $pack_to = 'data_'.($packd_id++).'.bin';
        else
            $pack_to = preg_replace('#^(\\\\|/)#', '', FStr::cast($pack_to, FStr::UNIXPATH));

       if (in_array($pack_to, $this->conts))
            return false;

        if ($dir = dirname($pack_to))
            if (!in_array($dir, $this->conts))
                $this->makeDir($dir);

        $header = Array(
            'name'  => $pack_to,
            'mode'  => '644',
            'uid'   => fileowner(__FILE__),
            'gid'   => filegroup(__FILE__),
            'size'  => strlen($inp),
            'time'  => time(),
            'type'  => 0,
            );

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $header = $this->makeRawHeader($header);

        parent::add(new FStringStream($header));
        parent::add(new FStringStream($inp));

        $this->conts[] = $pack_to;

        return True;
    }

    // Makes an empty directory inside archive
    public function makeDir($dirname, $force_mode = '')
    {
        if (!$dirname)
            return false;

        $dirname = preg_replace('#^(\\\\|/)#', '', FStr::cast($dirname, FStr::UNIXPATH));

        if (in_array($dirname, $this->conts))
            return false;

        if ($dir = dirname($dirname))
            if (!in_array($dir, $this->conts))
                $this->makeDir($dir, $force_mode);

        $header = Array(
            'name'  => $dirname,
            'mode'  => '755',
            'uid'   => fileowner(__FILE__),
            'gid'   => filegroup(__FILE__),
            'size'  => 0,
            'time'  => time(),
            'type'  => 5,
            );

        if (preg_match('#^[0-7]{3}$#', $force_mode))
            $header['mode'] = $force_mode;

        $header = $this->makeRawHeader($header);
        parent::add(new FStringStream($header));

        $this->conts[] = $dirname;

        return true;
    }

    private function makeRawHeader($header)
    {
        static $h_fields = Array(
            'name' => '', 'mode' => '', 'uid'   => '', 'gid'  => '',
            'size' => '', 'time' => '', 'chsum' => '', 'type' => '',
            'linkname' => '', 'magic' => 'ustar  ');

        $header+= $h_fields;
        if (!$header['name'])
            return false;

        $header['name']  = FStr::fixLength($header['name'], 100, "\0", STR_PAD_RIGHT);
        $header['mode']  = FStr::fixLength(preg_replace('#[^0-7]#', '', $header['mode']), 6 , '0', STR_PAD_LEFT)." \0";
        $header['uid']   = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['uid']) ), 6 , '0', STR_PAD_LEFT)." \0";
        $header['gid']   = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['gid']) ), 6 , '0', STR_PAD_LEFT)." \0";
        $header['size']  = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['size'])), 11, '0', STR_PAD_LEFT)." ";
        $header['time']  = FStr::fixLength(preg_replace('#[^0-7]#', '', decoct($header['time'])), 11, '0', STR_PAD_LEFT)." ";
        $header['chsum'] = str_repeat(' ', 8);
        $header['type']  = ($header['type']==5) ? 5 : 0;
        $header['linkname'] = FStr::fixLength($header['linkname'], 100, "\0", STR_PAD_RIGHT);
        $header['magic']    = FStr::fixLength($header['magic'], 8, "\0", STR_PAD_RIGHT);;

        $csumm = 0;
        foreach (array_keys($h_fields) as $key)
        {
            $val = $header[$key];
            $len = strlen($val);
            for ($i=0; $i<$len; ++$i)
                $csumm += ord(substr($val, $i, 1));
        }
        $header['chsum'] = FStr::fixLength(decoct($csumm), 6, '0', STR_PAD_LEFT)." \x00";

        $rawheader = '';
        foreach (array_keys($h_fields) as $key)
            $rawheader.= $header[$key];
        $rawheader = FStr::fixLength($rawheader, 512, chr(0), STR_PAD_RIGHT);

        return $rawheader;
    }

}

?>
