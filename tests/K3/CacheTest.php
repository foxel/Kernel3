<?php

/**
 * Class FCacheTest
 * @author Andrey F. Kupreychik
 */
class K3_CacheTest extends PHPUnit_Framework_TestCase
{
    /** @var string */
    protected $_testValue;
    /** @var K3_Cache */
    protected $_instance;

    public function testSetAndGet()
    {
        $this->_storeTestValue();

        $this->assertEquals($this->_testValue, $this->_instance->get('testValue'), 'Value should be loaded same as saved');
    }

    public function testClear()
    {
        $this->_storeTestValue();
        $this->_instance->clear();

        $this->assertNull($this->_instance->get('testValue'), 'NULL should be returned for empty cache');
    }

    public function testGetEmpty()
    {
        $this->assertNull($this->_instance->get('testValue'), 'NULL should be returned for empty cache');
    }

    public function testGetWithExpire()
    {
        $this->_storeTestValue();
        $timeStored = time();

        $this->assertEquals($this->_testValue, $this->_instance->get('testValue', $timeStored), 'Value should be loaded same as saved');
    }

    public function testGetExpired()
    {
        $this->_storeTestValue();
        $timeStored = time();

        sleep(1);
        $timeToCheck = $timeStored + 1;

        $this->assertNull($this->_instance->get('testValue', $timeToCheck), 'Value should be null as it wa stored before $timeToCheck');
    }

    protected function setUp()
    {
        $this->_testValue = uniqid('Tes Random', true);
        $this->_instance = F()->Cache;
        $this->_instance->clear();
    }

    protected function tearDown()
    {
        $this->_instance->clear();
    }

    protected function _storeTestValue()
    {
        $this->_instance->set('testValue', $this->_testValue);
        $this->_instance->flush();
    }

}
