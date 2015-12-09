<?php

require_once dirname(__FILE__) . '/../../include/ipSegment.php';

class ipSegmentTest extends PHPUnit_Framework_TestCase {
	public function testInsertStringIp() {
		$ipSeg = new ipSegment("0.0.0.1", "127.0.0.1");
		
		$this->assertEquals(1, $ipSeg->getStartIpFloat());
		$this->assertEquals(2130706433, $ipSeg->getEndIpFloat());
		$this->assertEquals("0.0.0.1", $ipSeg->getStartIpString());
		$this->assertEquals("127.0.0.1", $ipSeg->getEndIpString());
	}
	
	public function testInsertLongIp() {
		$ipSeg = new ipSegment(1, 2130706433);
		
		$this->assertEquals(1, $ipSeg->getStartIpFloat());
		$this->assertEquals(2130706433, $ipSeg->getEndIpFloat());
		$this->assertEquals("0.0.0.1", $ipSeg->getStartIpString());
		$this->assertEquals("127.0.0.1", $ipSeg->getEndIpString());
	}
	
	public function testInsertString64Ip() {
		$ipSeg = new ipSegment("0.0.0.1", "255.255.255.255");
		
		$this->assertTrue($ipSeg->getEndIpFloat() > $ipSeg->getStartIpFloat());
		$this->assertEquals("0.0.0.1", $ipSeg->getStartIpString());
		$this->assertEquals("255.255.255.255", $ipSeg->getEndIpString());
	}
	
	public function testInsertLong64Ip() {
		$ipSeg = new ipSegment("1", "4294967295");
		
		$this->assertTrue($ipSeg->getEndIpFloat() > $ipSeg->getStartIpFloat());
		$this->assertEquals("0.0.0.1", $ipSeg->getStartIpString());
		$this->assertEquals("255.255.255.255", $ipSeg->getEndIpString());
	}
	
	public function testCheckIntersection() {
		$ipSeg = new ipSegment(3, 5);
		$this->assertFalse($ipSeg->checkIntersection(new ipSegment(1,2)));
		$this->assertTrue($ipSeg->checkIntersection(new ipSegment(1,3)));
		$this->assertTrue($ipSeg->checkIntersection(new ipSegment(1,4)));
		$this->assertTrue($ipSeg->checkIntersection(new ipSegment(3,4)));
		$this->assertTrue($ipSeg->checkIntersection(new ipSegment(4,4)));
		$this->assertTrue($ipSeg->checkIntersection(new ipSegment(4,5)));
		$this->assertTrue($ipSeg->checkIntersection(new ipSegment(5,6)));
		$this->assertFalse($ipSeg->checkIntersection(new ipSegment(6,7)));
	}
	
	public function testGetIntersection() {
		$ipSeg = new ipSegment(3, 5);
		$this->assertEquals(new ipSegment(3,3), $ipSeg->getIntersection(new ipSegment(1,3)));
		$this->assertEquals(new ipSegment(3,4), $ipSeg->getIntersection(new ipSegment(1,4)));
		$this->assertEquals(new ipSegment(3,4), $ipSeg->getIntersection(new ipSegment(3,4)));
		$this->assertEquals(new ipSegment(4,4), $ipSeg->getIntersection(new ipSegment(4,4)));
		$this->assertEquals(new ipSegment(4,5), $ipSeg->getIntersection(new ipSegment(4,5)));
		$this->assertEquals(new ipSegment(5,5), $ipSeg->getIntersection(new ipSegment(5,6)));
	}
	
	public function testIsInsideIp() {
		$ipSeg = new ipSegment(3, 5);
		$this->assertFalse($ipSeg->isInsideIp(2));
		$this->assertTrue($ipSeg->isInsideIp(3));
		$this->assertTrue($ipSeg->isInsideIp(4));
		$this->assertTrue($ipSeg->isInsideIp(5));
		$this->assertFalse($ipSeg->isInsideIp(6));
	}
	
	public function testCidr() {
		$ipSeg1 = new ipSegment(0, 0);
		$ipSeg1->setCidr("1.0.0.0/14");
		$this->assertEquals("1.0.0.0", $ipSeg1->getStartIpString());
		$this->assertEquals("1.3.255.255", $ipSeg1->getEndIpString());
	}
}
