<?php
/**
 * Copyright (C) 2010 - 2012, 2014 Andrey F. Kupreychik (Foxel)
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
class FTimer extends K3_Chronometer implements I_K3_Deprecated
{
    /** @var array[]  */
    protected $_timeLog = array();

    /**
     * @param $id
     * @return $this
     */
    public function setTimer($id)
    {
        return $this->setMeasurePoint($id);
    }

    /**
     * @param $id
     * @param bool $reset
     * @return bool|float
     */
    public function getTimer($id, $reset = false)
    {
        return $this->getMeasuredTime($id, $reset);
    }

    /**
     * @return int
     */
    public function qTime()
    {
        return $this->getStartTime();
    }

    /** @deprecated */
    public function setQTime($time)
    {
        $this->_startTime = (int) $time;
    }

    /**
     * @param string $event
     */
    public function logEvent($event = 'unknown')
    {
        $this->_timeLog[] = array(
            'time' => $this->timeSpent(),
            'name' => $event,
        );
    }

    /**
     * @return array[]
     */
    public function getLog()
    {
        return $this->_timeLog;
    }

    /**
     * @return float
     */
    public function timeSpent()
    {
        return $this->getTimeSpent();
    }
}
