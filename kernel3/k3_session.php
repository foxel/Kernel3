<?php
/**
 * QuickFox kernel 3 'SlyFox' Session module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage core
 * @deprecated
 */
class FSession extends K3_Session implements I_K3_Deprecated
{
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FSession(F()->appEnv);
        return self::$self;
    }

    private function __construct(K3_Environment $env) { parent::__construct($env); }

}

/**
 * @return FSession
 * @deprecated
 */
function FSession()
{
    return FSession::getInstance();
}

/**
 * @return FSession
 * @deprecated
 */
function Session()
{
    return FSession::getInstance();
}

