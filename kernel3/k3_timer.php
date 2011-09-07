<?php
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
