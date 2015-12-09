<?php

require_once dirname(__FILE__) . '/../../include/binaryPacker.php';

class binaryPackerTest extends PHPUnit_Framework_TestCase
{
    public function testPackSmallInt()
    {
        $buf = binaryPacker::packInt(5);
        $this->assertEquals(5, binaryPacker::unpackInt($buf));
    }

    public function testPackLargeInt()
    {
        $buf = binaryPacker::packInt(3000000000);
        $this->assertEquals(3000000000, binaryPacker::unpackInt($buf));
    }
};