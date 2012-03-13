<?php

abstract class K3_Environment_Element extends FEventDispatcher
{
    /**
     * @var K3_Environment $env
     */
    protected $env = null;

    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        $this->setEnvironment(!is_null($env) ? $env : F()->appEnv);
    }

    /**
     * @param K3_Environment $env
     * @return K3_Environment_Element
     */
    public function setEnvironment(K3_Environment $env)
    {
        $this->env = $env;
        return $this;
    }
}
