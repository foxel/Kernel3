<?php
/**
 * Copyright (C) 2010 - 2011 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' timer module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

// timing and time logging class
class FTimer
{
    private $qTime;
    private $sTime;
    private $timePoints = Array();
    private $timeLog = Array();

    public function __construct()
    {
        $this->qTime = time();
        $this->timePoints[] = $this->sTime = $this->microTime();
    }

    public function microTime()
    {
        return microtime(true);
    }

    public function setTimer($id)
    {
        $id = $id ? $id : count($this->timePoints);
        $this->timePoints[$id] = $this->microTime();
        return $id;
    }

    public function getTimer($id, $reset = false)
    {
        if (!isset($this->timePoints[$id]))
            return false;
        $out = $this->microTime() - $this->timePoints[$id];
        if ($reset)
            $this->timePoints[$id] = $this->microTime();
        return $out;
    }

    public function timeSpent()
    {
        return ($this->microTime() - $this->sTime);
    }

    public function logEvent($event = 'unknown')
    {
        $this->timeLog[] = Array(
            'time' => $this->timeSpent(),
            'name' => $event );
    }

    public function getLog()
    {
        return $this->timeLog;
    }

    public function qTime()
    {
        return $this->qTime;
    }

    public function setQTime($time)
    {
        $this->qTime = (int) $time;
    }
}

?>
