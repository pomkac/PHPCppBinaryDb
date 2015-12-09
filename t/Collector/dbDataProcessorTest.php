<?php

require_once dirname(__FILE__) . '/../../include/dbDataProcessor.php';

class dbDataProcessorTest extends PHPUnit_Framework_TestCase {
	public function testData() {
		$dbData = new dbDataProcessor();
		$dbData->addCarrierInterval(new ipSegment(1,3), "name1");
		$dbData->addCarrierInterval(new ipSegment(4,5), "name1");
		$dbData->addCarrierInterval(new ipSegment(7,8), "name1");
		$dbData->addCarrierInterval(new ipSegment(9,10), "name2");
		$etalon = array(
			dbDataProcessor::IP_START_POS => 1,
			dbDataProcessor::IP_END_POS => 5,
			dbDataProcessor::CODE_POS => "name1",	
		);
		$this->assertEquals($etalon,$dbData->resetData());	
		$etalon = array(
			dbDataProcessor::IP_START_POS => 7,
			dbDataProcessor::IP_END_POS => 8,
			dbDataProcessor::CODE_POS => "name1",	
		);
		$this->assertEquals($etalon,$dbData->getNext());	
		$etalon = array(
			dbDataProcessor::IP_START_POS => 9,
			dbDataProcessor::IP_END_POS => 10,
			dbDataProcessor::CODE_POS => "name2",	
		);
		$this->assertEquals($etalon,$dbData->getNext());	
		$this->assertEquals(false,$dbData->getNext());
		$this->assertEquals(3, $dbData->getCount());	
	}
	
	public function testData2() {
		$dbData = new dbDataProcessor();
		$dbData->addCarrierInterval(new ipSegment(1,3), "name1");
		$dbData->addCarrierInterval(new ipSegment(4,5), "name1");
		$dbData->addCarrierInterval(new ipSegment(7,8), "name2");
		$dbData->addCarrierInterval(new ipSegment(8,9), "name2");
		$etalon = array(
			dbDataProcessor::IP_START_POS => 1,
			dbDataProcessor::IP_END_POS => 5,
			dbDataProcessor::CODE_POS => "name1",	
		);
		$this->assertEquals($etalon,$dbData->resetData());	
		$etalon = array(
			dbDataProcessor::IP_START_POS => 7,
			dbDataProcessor::IP_END_POS => 9,
			dbDataProcessor::CODE_POS => "name2",	
		);
		$this->assertEquals($etalon,$dbData->getNext());	
		$this->assertEquals(false,$dbData->getNext());
		$this->assertEquals(2, $dbData->getCount());	
	}
};