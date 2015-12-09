<?php

require_once dirname(__FILE__) . '/../../include/csvParser.php';

class csvParserTest extends PHPUnit_Framework_TestCase {
	public function testQuotedCsv() {
		$csvParser = new csvParser(dirname(__FILE__)."/testCsv/quotes.csv");
		$data = $csvParser->getNext();
		$etalon = array(
			array("field1" => 1, "field2" => "text1"),
			array("field1" => 2, "field2" => "text2"),
		);
		$this->assertEquals($etalon, $data);
	}
	
	public function testNoHeaderCsv() {
		$csvParser = new csvParser(dirname(__FILE__)."/testCsv/noHeader.csv", false);
		$data = $csvParser->getNext();
		$etalon = array(
			array("val1", "val2", 123),
		);
		$this->assertEquals($etalon, $data);
	}
	
	public function testNonExistentCsv() {
		$this->setExpectedException('Exception');
		$csvParser = new csvParser(dirname(__FILE__)."/testCsv/nonExistent.csv");
	}
};	
