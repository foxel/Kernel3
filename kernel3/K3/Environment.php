<?php

/**
 * @property K3_Request  $request
 * @property K3_Response $response
 * @property K3_Session  $session
 *
 * @property K3_Environment_Client $client
 * @property K3_Environment_Server $server
 *
 */
class K3_Environment extends FEventDispatcher
{
    /**
     * @var K3_Request
     */
    protected $_request = null;

    /**
     * @var K3_Response
     */
    protected $_response = null;

    /**
     * @var K3_Session
     */
    protected $_session = null;

    /**
     * @var K3_Environment_Server
     */
    protected $_server = null;

    /**
     * @var K3_Environment_Client
     */
    protected $_client = null;

    /**
     * @var array
     */
    protected $_elements = array();

    /**
     * @param string $class
     */
    public function __construct($class = 'HTTP')
    {
        $this->setClient(K3_Environment_Client::construct($class, $this));
        $this->setServer(K3_Environment_Server::construct($class, $this));
    }

    /**
     * @param  string $name
     * @param  mixed $element
     * @return K3_Environment
     */
    public function put($name, $element)
    {
        $this->_elements[$name] = $element;

        return $this;
    }

    /**
     * @param  string $name
     * @return mixed
     */
    public function get($name)
    {
        return isset($this->_elements[$name])
            ? $this->_elements[$name]
            : null;
    }

    /**
     * getter
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getterMethod = 'get'.ucfirst($name);
        if (method_exists($this, $getterMethod)) {
            return $this->$getterMethod();
        } else {
            return parent::__get($name);
        }
    }

    /**
     * @param K3_Request $request
     */
    public function setRequest(K3_Request $request = null)
    {
        $this->_request = $request;
        $this->_request->setEnvironment($this);
    }

    /**
     * @return K3_Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * @param K3_Response $response
     */
    public function setResponse(K3_Response $response = null)
    {
        $this->_response = $response;
        $this->_response->setEnvironment($this);
    }

    /**
     * @return K3_Response
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * @param K3_Session $session
     */
    public function setSession(K3_Session $session = null)
    {
        $this->_session = $session;
        $this->_session->setEnvironment($this);
    }

    /**
     * @return K3_Session
     */
    public function getSession()
    {
        return $this->_session;
    }

    /**
     * @param K3_Environment_Client $client
     */
    public function setClient(K3_Environment_Client $client)
    {
        $this->_client = $client;
        $this->_client->setEnvironment($this);
    }

    /**
     * @return K3_Environment_Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * @param K3_Environment_Server $server
     */
    public function setServer(K3_Environment_Server $server)
    {
        $this->_server = $server;
        $this->_server->setEnvironment($this);
    }

    /**
     * @return K3_Environment_Server
     */
    public function getServer()
    {
        return $this->_server;
    }
}
