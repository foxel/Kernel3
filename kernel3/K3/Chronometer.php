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
 * Class K3_Chronometer
 * @author Andrey F. Kupreychik
 * @property-read int $startTime
 * @property-read float $timeSpent
 */
class K3_Chronometer extends FBaseClass
{
    /** @var int  */
    protected $_startTime;
    /** @var float  */
    protected $_startMicroTime;
    /** @var float[]  */
    protected $_timePoints = array();

    /**
     * constructor
     */
    public function __construct()
    {
        $this->_startTime      = time();
        $this->_startMicroTime = self::microTime();
    }

    /**
     * @return float
     */
    public static function microTime()
    {
        return microtime(true);
    }

    /**
     * @param $pointId
     * @return $this
     */
    public function setMeasurePoint($pointId = null)
    {
        if (!$pointId) {
            $pointId = count($this->_timePoints);
        }
        $this->_timePoints[$pointId] = self::microTime();

        return $pointId;
    }

    /**
     * @param string $pointId
     * @param bool $restart
     * @return bool|float
     */
    public function getMeasuredTime($pointId, $restart = false)
    {
        if (!isset($this->_timePoints[$pointId])) {
            return false;
        }
        $out = $this->microTime() - $this->_timePoints[$pointId];
        if ($restart) {
            $this->_timePoints[$pointId] = $this->microTime();
        }

        return $out;
    }

    /**
     * @return float
     */
    public function getTimeSpent()
    {
        return (self::microTime() - $this->_startMicroTime);
    }


    /**
     * @return int
     */
    public function getStartTime()
    {
        return $this->_startTime;
    }
}
