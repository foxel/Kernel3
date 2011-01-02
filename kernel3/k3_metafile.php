<?php
/**
 * QuickFox kernel 3 'SlyFox' MetaFile Class
 * Requires PHP >= 5.1.0
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

    public function create($cluster = 1, $fillchr = "\0") { return new FMetaFile($cluster, $fillchr); }
    public function _Call($cluster = 1, $fillchr = "\0") { return new FMetaFile($cluster, $fillchr); }
}

class FMetaFilePart
{    public $o = null;
    public $s = 0;
    public $l = 0;
    public $p = 0;
}

class FMetaFile extends FDataStream
{    private $parts = Array();
    private $sel_part = 0;
    private $pos = 0;
    private $fsize = 0;
    private $cluster = 1;
    private $fillchr = "\0";
    public function __construct($cluster = 1, $fillchr = "\0")
    {
        $this->filename = 'foo';
        $this->fsize = 0;
        $this->pos = 0;
        $this->sel_part = 0;
        $this->cluster = $cluster;
        if (is_string($fillchr))
            $this->fillchr = $fillchr[0];
        elseif (is_int($fillchr))
            $this->fillchr = chr($fillchr & 0xff);
    }

    public function add(FDataStream $part)
    {        $i = count($this->parts);
        $this->parts[$i] = $p = new FMetaFilePart();
        $p->o = $part;
        $p->l = $p->o->size();
        if ($this->cluster > 0 && $p->l%$this->cluster)
            $p->l = intval($p->l/$this->cluster+1)*$this->cluster;
        $p->p = $this->fsize;
        $this->fsize+= $p->l;
    }

    public function open($mode = 'rb')
    {
        if (!$this->parts)
            return false;
        $this->parts[$this->sel_part]->o->close();
        return $this->parts[$this->sel_part = 0]->o->open($this->mode = 'rb');
    }

    public function close()
    {
        if (!$this->parts)
            return false;
        return $this->parts[$this->sel_part]->o->close();
    }

    public function EOF()
    {
        if (!$this->parts)
            return true;
        return ($this->sel_part >= count($this->parts)-1 && $this->parts[$this->sel_part]->o->EOF());
    }

    public function size() { return $this->fsize; }

    public function read(&$data, $len)
    {
        $adata = $data = '';
        while ($len)
        {            $rlen = min($len, $this->parts[$this->sel_part]->l - ($this->pos - $this->parts[$this->sel_part]->p));
            $glen = $this->parts[$this->sel_part]->o->read($adata, $rlen);
            if ($glen < $rlen)
                $adata.= str_repeat($this->fillchr, $rlen - $glen);
            $len-= $rlen;
            $this->pos+= $rlen;
            $data.= $adata;
            if ($len)
            {
                $this->parts[$this->sel_part++]->o->close();
                $this->parts[$this->sel_part]->o->open($this->mode);
            }
        }
        return strlen($data);
    }

    public function seek($pos)
    {
        $this->parts[$this->sel_part]->o->close();
        $this->sel_part = 0;
        while ($this->parts[$this->sel_part+1]->p < $pos)
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
}

?>