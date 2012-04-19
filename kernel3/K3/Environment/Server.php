<?php

/**
 * @property string $rootUrl
 * @property string $rootPath
 * @property string $rootRealPath
 * @property string $domain
 * @property int    $port
 */
abstract class K3_Environment_Server extends K3_Environment_Element
{
    /**
     * @static
     * @param string $class
     * @param K3_Environment|null $env
     * @return K3_Environment_Server
     * @throws FException
     */
    public static function construct($class, K3_Environment $env = null)
    {
        if (empty($class)) {
            throw new FException('K3_Environment_Server construct without class specified');
        }

        $className = __CLASS__.'_'.ucfirst($class);

        return new $className($env);
    }

    /**
     * @param K3_Environment|null $env
     */
    public function __construct(K3_Environment $env = null)
    {
        $this->pool = array(
            'rootUrl'      => '',
            'rootPath'     => '',
            'rootRealPath' => '',
            'domain'       => '',
            'port'         => 80,
        );

        parent::__construct($env);
    }

}