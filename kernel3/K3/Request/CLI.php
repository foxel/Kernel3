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
 * Class K3_Request_CLI
 *
 * @author Andrey F. Kupreychik
 */
class K3_Request_CLI extends K3_Request
{
    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        parent::__construct($env);
        $this->doGPCStrip = (bool) get_magic_quotes_gpc();

        $this->pool['isSecure'] = false;
        $this->pool['isAjax']   = false;
        $this->pool['isPost']   = !empty($_POST);

        @parse_str(trim(file_get_contents('php://stdin')), $_POST);
        $this->_POST = (array) $_POST;

        $args = $GLOBALS['argv'];
        if (isset($args[1])) {
            $this->pool['url'] = preg_replace('#\/|\\\\+#', '/', trim($args[1]));
            $this->pool['url'] = preg_replace('#^/+#s', '', $this->pool['url']);

            if (preg_match('#\?([^\#]+)$#', $this->pool['url'], $matches)) {
                @parse_str($matches[1], $_GET);
                $this->_GET = (array) $_GET;
            }
        }

        $this->_REQUEST = array_merge($this->_GET, $this->_POST);
    }

    /**
     * @param string $varName
     * @return array|null
     */
    public function getFile($varName)
    {
        return null;
    }

    /**
     * @param string $varName
     * @param string $toFile
     * @param bool $forceReplace
     * @return bool
     */
    public function moveFile($varName, $toFile, $forceReplace = false)
    {
        return false;
    }
}
