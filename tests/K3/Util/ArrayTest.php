<?php

/**
 * Class K3_Util_ArrayTest
 * @author Andrey F. Kupreychik
 */
Class K3_Util_ArrayTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers K3_Util_Array::implodeRecursive
     */
    public function testImplodeRecursivePlain()
    {
        $this->assertEquals(
            'a,b,c',
            K3_Util_Array::implodeRecursive(',', array('a', 'b', 'c')),
            'Should implode plain array same as implode'
        );
    }

    /**
     * @covers K3_Util_Array::implodeRecursive
     */
    public function testImplodeRecursive1Level()
    {
        $this->assertEquals(
            'a,b,c',
            K3_Util_Array::implodeRecursive(',', array('a', array('b', 'c'))),
            'Should implode plain array same as implode'
        );
    }

    /**
     * @covers K3_Util_Array::implodeRecursive
     */
    public function testImplodeRecursive2Level()
    {
        $this->assertEquals(
            'a,b1,b2,c',
            K3_Util_Array::implodeRecursive(',', array('a', array(array('b1', 'b2'), 'c'))),
            'Should implode plain array same as implode'
        );
    }

}
