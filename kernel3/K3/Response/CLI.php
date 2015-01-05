<?php
/**
 * Copyright (C) 2013, 2015 Andrey F. Kupreychik (Foxel)
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

class K3_Response_CLI extends K3_Response
{
    /**
     * @param K3_Environment $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);

        ini_set ('default_mimetype', '');
        ini_set ('default_charset', '');
    }

    /**
     * @return $this
     */
    protected function sendHeadersData()
    {
        foreach ($this->headers as $name => &$values) {
            $replace = true;
            foreach ((array) $values as $value) {
                header($name.': '.$value, $replace); // TODO: think about raw headers and auto encoding
                $replace = false;
            }
        }
        return $this;
    }

    /**
     * @param string|null $data
     */
    protected function sendResponseData($data = null)
    {
        if (is_null($data)) {
            $data = $this->buffer;
        }
        echo $data;
    }

    protected function closeAndExit()
    {
        $this->throwEvent(self::EVENT_CLOSE_AND_EXIT);
        exit($this->statusCode);
    }
}
