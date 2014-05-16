<?php
/**
 * Copyright (C) 2014 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Stream_Compound
 * @author Andrey F. Kupreychik
 */
abstract class K3_Stream_Compound extends K3_Stream
{
    /** @var K3_Stream_Compound_Part[] */
    protected $_parts = array();
    protected $_currentPart = -1;
    protected $_position = 0;
    protected $_size = 0;
    protected $_cluster = 1;
    protected $_fillChar = "\0";
    protected $_mtime = 0;

    /**
     * @param int $cluster
     * @param string|int $fillChar
     */
    public function __construct($cluster = 1, $fillChar = "\0")
    {
        $this->_uri     = 'foo';
        $this->_cluster = $cluster;
        $this->_mtime   = time();
        if (is_string($fillChar)) {
            $this->_fillChar = $fillChar[0];
        } elseif (is_int($fillChar)) {
            $this->_fillChar = chr($fillChar & 0xff);
        }
    }

    /**
     * @param I_K3_Stream $stream
     * @return bool
     */
    public function _addItem(I_K3_Stream $stream)
    {
        if (!$stream->size()) {
            return false;
        }
        $i    = count($this->_parts);
        $part = new K3_Stream_Compound_Part();

        $part->obj = $stream;
        $part->len = $stream->size();
        if ($this->_cluster > 1 && $part->len % $this->_cluster) {
            $part->len = intval($part->len / $this->_cluster + 1) * $this->_cluster;
        }
        $part->pos = $this->_size;

        $this->_size += $part->len;
        $this->_parts[$i] = $part;

        return true;
    }

    /**
     * @param string $mode [ignored]
     * @return bool
     */
    public function open($mode = 'rb')
    {
        if (!$this->_parts) {
            return false;
        }
        if ($this->_currentPart >= 0 && $this->_currentPart < count($this->_parts)) {
            $this->_parts[$this->_currentPart]->obj->close();
        }

        return ($this->_parts[$this->_currentPart = 0]->obj->open($this->_mode = 'rb'));
    }

    /**
     * @return bool
     */
    public function close()
    {
        if (!$this->_parts || $this->_currentPart < 0 || $this->_currentPart >= count($this->_parts)) {
            return false;
        }

        return $this->_parts[$this->_currentPart]->obj->close();
    }

    /**
     * @return bool
     */
    public function EOF()
    {
        if (!$this->_parts || $this->_currentPart < 0 || $this->_currentPart >= count($this->_parts)) {
            return true;
        }

        return (($this->_currentPart == count($this->_parts) - 1) && $this->_parts[$this->_currentPart]->obj->EOF());
    }

    /**
     * @return int
     */
    public function size()
    {
        return $this->_size;
    }

    /**
     * @param string $data
     * @param int $len
     * @return bool|int
     */
    public function read(&$data, $len)
    {
        if (!$this->_parts || $this->_currentPart < 0 || $this->_currentPart >= count($this->_parts)) {
            return false;
        }

        $partData = $data = '';
        while ($len) {
            $partLen = min($len, $this->_parts[$this->_currentPart]->len - ($this->_position - $this->_parts[$this->_currentPart]->pos));
            $readLen = $this->_parts[$this->_currentPart]->obj->read($partData, $partLen);
            if ($readLen < $partLen) {
                $partData .= str_repeat($this->_fillChar, $partLen - $readLen);
            }
            $len -= $partLen;
            $this->_position += $partLen;
            $data .= $partData;
            if ($len) {
                $this->_parts[$this->_currentPart++]->obj->close();
                if ($this->_currentPart >= count($this->_parts)) {
                    break;
                }
                $this->_parts[$this->_currentPart]->obj->open($this->_mode);
            }
        }

        return strlen($data);
    }

    /**
     * @param int $pos
     * @return bool
     */
    public function seek($pos)
    {
        if (!$this->_parts || $this->_currentPart < 0 || $pos >= $this->_size) {
            return false;
        }

        if ($this->_currentPart < count($this->_parts)) {
            $this->_parts[$this->_currentPart]->obj->close();
        }

        $this->_currentPart = 0;
        while ($this->_parts[$this->_currentPart]->pos + $this->_parts[$this->_currentPart]->len <= $pos) {
            $this->_currentPart++;
        }

        $this->_parts[$this->_currentPart]->obj->open($this->_mode);

        if ($this->_parts[$this->_currentPart]->obj->seek($pos - $this->_parts[$this->_currentPart]->pos)) {
            $this->_position = $pos;

            return true;
        }

        return false;
    }

    /**
     * @param mixed $data
     * @throws FException
     * @return int|null
     */
    public function write($data)
    {
        throw new FException('Not supported');
    }

    /**
     * @return int
     */
    public function mtime()
    {
        return $this->_mtime;
    }

    /**
     * @param string $filename
     * @return bool
     */
    public function saveToFile($filename)
    {
        if (!$filename) {
            return false;
        }

        if ($out = fopen($filename, 'wb')) {
            $this->open();
            $buff = '';
            while ($this->read($buff, 1048576)) { // 1MB
                fwrite($out, $buff);
            }
            fclose($out);

            return true;
        }

        return false;
    }
}

/**
 * Class K3_Stream_Compound_Part
 * @author Andrey F. Kupreychik
 */
class K3_Stream_Compound_Part
{
    /** @var I_K3_Stream */
    public $obj = null;
    public $len = 0;
    public $pos = 0;
}
