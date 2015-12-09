<?php

require_once dirname(__FILE__) . '/../../include/dbStream.php';

class dbStreamTest extends PHPUnit_Framework_TestCase {
	public function testReadWrite() {
		$fname = dirname(__FILE__)."/tmp/test.db";
		$dbStream = new dbStream($fname, false);
		$dbStream->write("test");
		$this->assertEquals(4, $dbStream->tell());
		$dbStream->seek(0);
		$this->assertEquals("test", $dbStream->read(4));
		$dbStream->close();
		$this->assertEquals(4, filesize($fname));
	}
}