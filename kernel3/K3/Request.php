<?php
/**
 * Copyright (C) 2012 Andrey F. Kupreychik (Foxel)
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
 * @property string $url
 * @property string $referer
 * @property bool   $refererIsExternal
 * @property bool   $isSecure
 * @property bool   $isPost
 * @property bool   $isAjax
 */
abstract class K3_Request extends K3_Environment_Element implements I_K3_Request
{
    // GPC source types
    const ALL    = 0;
    const GET    = 1;
    const POST   = 2;
    const COOKIE = 3;

    const UPLOAD_OK            = 0; // OK status
    const UPLOAD_ERR_INI_SIZE  = 1; // this four statuses are equal to PHP ones
    const UPLOAD_ERR_FORM_SIZE = 2;
    const UPLOAD_ERR_PARTIAL   = 3;
    const UPLOAD_ERR_NO_FILE   = 4;
    const UPLOAD_ERR_SERVER    = 0x10; // this means that was a error on server we'll give PHP 15 status message variants for future
    const UPLOAD_MOVED         = 0x20; // this means that file already moved

    /**
     * @var callable $stringRecodeFunc
     */
    protected $stringRecodeFunc = null;

    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        $this->pool = array(
            'url'               => '',
            'referer'           => '',
            'refererIsExternal' => false,
            'isSecure'          => false,
            'isPost'            => false,
            'isAjax'            => false,
        );

        parent::__construct($env);
    }

    /**
     * must be implemented in extending class
     * @param  string $varName
     * @param  integer $source
     * @param  mixed $default
     * @return mixed
     */
    public function get($varName, $source = self::ALL, $default = null) {}

    /**
     * @param  string $varName
     * @param  integer $source
     * @param  boolean $getFlags
     * @return bool|null
     */
    public function getBinary($varName, $source = self::ALL, $getFlags = true)
    {
        $val = $this->get($varName, $source);
        if ($val === null)
            return null;
        if ($getFlags && is_string($val) && !strlen($val))
            $val = true;
        return ($val) ? true : false;
    }

    /**
     * @param  string $varName
     * @param  integer $source
     * @param  boolean $getFloat
     * @return int|float|null
     */
    public function getNumber($varName, $source = self::ALL, $getFloat = false )
    {
        $val = $this->get($varName, $source);
        if ($val === null)
            return null;
        return ($getFloat) ? floatval($val) : intval($val);
    }

    /**
     * @param  string $varName
     * @param  integer $source
     * @param  integer $stringCastType
     * @return string|null
     */
    public function getString($varName, $source = self::ALL, $stringCastType = null )
    {
        $val = $this->get($varName, $source);
        if ($val === null)
            return null;
        $val = trim(strval($val));

        if (is_callable($this->stringRecodeFunc))
            $val = call_user_func($this->stringRecodeFunc, $val);

        if ($stringCastType) {
            $val = FStr::cast($val, $stringCastType);
        }

        return $val;
    }

    /**
     * @return array
     */
    public function getURLParams()
    {
        $res = Array();
        parse_str(parse_url($this->url, PHP_URL_QUERY), $res);
        return $res;
    }

    /**
     * @param  string $varName
     * @return array|null
     */
    abstract public function getFile($varName);

    /**
     * @param  string $varName
     * @param  string $toFile
     * @param  bool $forceReplace
     * @return bool
     */
    abstract public function moveFile($varName, $toFile, $forceReplace = false);
}
