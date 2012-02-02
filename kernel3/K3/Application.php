<?php

abstract class K3_Application extends FEventDispatcher
{
    /**
     * @var K3_Environment
     */
    protected $env = null;

    /**
     * @param  K3_Environment $env
     */
    public function __construct(K3_Environment $env = null)
    {
        $this->env = is_null($env) ? F()->appEnv : $env;
    }

    abstract public function run();
}
