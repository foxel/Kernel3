<?php
/**
 * Copyright (C) 2015 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Profiler
 */
class K3_Profiler extends FBaseClass
{
    /** @var K3_Chronometer */
    protected $_chronometer;
    /** @var array[] */
    protected $_profile = array();

    /**
     * @param K3_Chronometer $clock
     */
    public function __construct(K3_Chronometer $clock = null)
    {
        $this->_chronometer = $clock ?: F()->appEnv->clock;
    }

    /**
     * @param string $event
     * @param array $meta
     */
    public function logEvent($event, array $meta = null)
    {
        $this->_profile[] = array(
            'time'  => $this->_chronometer->timeSpent,
            'event' => $event,
            'meta'  => $meta,
        );
    }

    /**
     * @return array[]
     */
    public function getProfile()
    {
        return $this->_profile;
    }
}
