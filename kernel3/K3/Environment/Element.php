<?php

abstract class K3_Environment_Element extends FEventDispatcher
{
    /**
     * @var K3_Environment $env
     */
    protected $env = null;

    public function __construct(K3_Environment $env = null)
    {
        if (is_null($env)) {
            $env = E();
        }

        $this->setEnvironment(!is_null($env) ? $env : E());
    }

    /**
     * @param  K3_Environment $env
     */
    public function setEnvironment(K3_Environment $env)
    {
        $this->env = $env;
        return $this;
    }
}
