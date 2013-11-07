<?php
/**
 * Copyright (C) 2013 Andrey F. Kupreychik (Foxel)
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
 * CSV file access
 */
class K3_Csv
{
    /** @var string */
    protected $_filename;
    /** @var resource */
    protected $_stream;
    /** @var string */
    protected $_mode;
    /** @var string */
    protected $_delimiter = ',';
    /** @var string */
    protected $_enclosure = '"';

    /**
     * @param string $filename
     * @param string $delimiter
     * @param string $enclosure
     */
    public function __construct($filename, $delimiter = ',', $enclosure = '"')
    {
        $this->_filename = $filename;
        if (strlen($delimiter) != 1) {
            trigger_error('delimiter must be a character', E_USER_WARNING);
        } else {
            $this->_delimiter = $delimiter;
        }

        if (strlen($enclosure) != 1) {
            trigger_error('enclosure must be a character', E_USER_WARNING);
        } else {
            $this->_enclosure = $enclosure;
        }
    }

    /**
     * @param string $mode
     * @return bool
     */
    public function open($mode = 'w')
    {
        return ($this->_stream = fopen($this->_filename, $this->_mode = $mode)) !== false;
    }

    /**
     * @param array $data
     * @return int
     */
    public function write(array $data)
    {
        $escape_char = '\\';

        $line = array();
        foreach ($data as $field) {
            if (is_string($field) && (
                    strpos($field, $this->_delimiter)!==false ||
                    strpos($field, $this->_enclosure)!==false ||
                    strpos($field, $escape_char)!==false ||
                    strpos($field, "\n")!==false ||
                    strpos($field, "\r")!==false ||
                    strpos($field, "\t")!==false ||
                    strpos($field, ' ')!==false )
            ) {
                $line[] = '"'.strtr($field, array('"' => '""'));
            } else {
                $line[] = (string) $field;
            }
        }

        return fwrite($this->_stream, implode($this->_delimiter, $line)."\n");
    }

    /**
     * @param mixed $data
     * @param int|null $length
     * @return array
     */
    public function read(&$data, $length = null)
    {
        return count($data = fgetcsv($this->_stream, $length, $this->_delimiter, $this->_enclosure));
    }

    /**
     * @return bool
     */
    public function EOF()
    {
        return feof($this->_stream);
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->_stream;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        return fflush($this->_stream);
    }

    /**
     * @return bool
     */
    public function close()
    {
        return fclose($this->_stream);
    }

    public function __destruct()
    {
        $this->close();
    }
}
