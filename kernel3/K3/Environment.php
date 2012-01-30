<?php

class K3_Environment extends FEventDispatcher
{
    /**
     * var K3_Request
     */
    protected $request = null;

    /**
     * getter
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getterMethod = 'get'.ucfirst($name);
        if (is_callable(array(&$this, $setterMethod))) {
            return $this->$getterMethod();
        } else {
            return parent::__get($name);
        }
    }

    /**
     * setter
     * @param  string $name
     * @param  mixed $val
     */
    public function __set($name, $val)
    {
        $setterMethod = 'set'.ucfirst($name);
        if (is_callable(array(&$this, $setterMethod))) {
            $this->$setterMethod($val);
        } else {
            parent::__set($name, $val);
        }
    }

    /**
     * @param K3_Request $request
     */
    public function setRequest(K3_Request $request = null)
    {
        $this->request = $request;
        $this->request->setEnvironment($this);
    }

    /**
     * @return K3_Request
     */
    public function getRequest()
    {
        return $this->request;
    }
}
