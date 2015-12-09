<?php

require_once dirname(__FILE__) . '/../../include/dbWriter.php';
require_once dirname(__FILE__) . '/../../include/dbReader.php';
require_once dirname(__FILE__) . '/../../include/dbDataProcessor.php';
require_once dirname(__FILE__) . '/../../include/dbStream.php';
require_once dirname(__FILE__) . '/../Mocks/optionsMock.php';

class dbWriterTest extends PHPUnit_Framework_TestCase {
	public function testWriteDb() {
		$dbData = new dbDataProcessor();
		$hashStep = dbWriter::HASH_STEP;
		$dbData->addCarrierInterval(new ipSegment(0,1*$hashStep), "carrier1");
    	$dbData->addCarrierInterval(new ipSegment(2*$hashStep, 3*$hashStep-1), "carrier2");
		$dbData->addCarrierInterval(new ipSegment(4*$hashStep, 5*$hashStep+1), "carrier3");
		$dbData->addCarrierInterval(new ipSegment(6*$hashStep-1, 7*$hashStep-1), "carrier4");
		$dbData->addCarrierInterval(new ipSegment(8*$hashStep+1, 9*$hashStep-1), "carrier5");
		$dbData->addCarrierInterval(new ipSegment(9*$hashStep, 10*$hashStep), "carrier6");
		$opts = new optionsMock();
		$dbStream = new dbStream(dirname(__FILE__)."/tmp/test2.db", false);
		$dbWriter = new dbWriter($opts, $dbData, $dbStream);
		$dbWriter->writeDb();
		$dbReader = new dbReader($dbStream);
		$dbReader->readAll();
		$dbReader->checkConsistency();
		$header = $dbReader->getHeader();
		$this->assertEquals("DBCA", $header['header']);
		$this->assertEquals(dbWriter::STRUCT_VERSION, $header['structVersion']);
		$this->assertEquals(1, $header['buildVersion']);
		$this->assertTrue(time() - $header['dateTime'] < 10); // меньше 10 секунд назад
		$this->assertEquals(6, $header['count']);
		$this->assertEquals("carrier1", $dbReader->getContentByPos(0));
		$this->assertEquals("carrier2", $dbReader->getContentByPos(1));
		$this->assertEquals("carrier3", $dbReader->getContentByPos(2));
		$this->assertEquals("carrier4", $dbReader->getContentByPos(3));
		$this->assertEquals("carrier5", $dbReader->getContentByPos(4));
		$this->assertEquals("carrier6", $dbReader->getContentByPos(5));
		//В комментах пример для HASH_STEP = 128
		$etalon = array (
			array (// [0; 128) | [0; $hashStep)
				array (0,$hashStep,20) 
			),
			array (// [128; 256) | [$hashStep; 2 * $hashStep)
				array (0, $hashStep, 20) 
			),
			array (// [256; 384) | [2 * $hashStep; 3 * $hashStep)
				array (2 * $hashStep, 3 * $hashStep-1, 32) 
			),
			array (// [384; 512) | [3 * $hashStep; 4 * $hashStep)				
			),
			array (// [512; 640) | [4 * $hashStep; 5 * $hashStep)
				array (4 * $hashStep, 5 * $hashStep + 1, 44) 
			),
			array (// [640; 768) | [5 * $hashStep; 6 * $hashStep)
				array (4 * $hashStep, 5 * $hashStep + 1, 44),
				array (6 * $hashStep - 1, 7 * $hashStep - 1, 56),
			),
			array (// [768; 896) | [6 * $hashStep; 7 * $hashStep)
				array (6 * $hashStep - 1, 7 * $hashStep - 1, 56),
			),
			array (// [896; 1024) | [7 * $hashStep; 8 * $hashStep)
			),
			array (// [1024; 1152) | [8 * $hashStep; 9 * $hashStep)
				array (8 * $hashStep + 1, 9 * $hashStep - 1, 68),
			),
			array (// [1152; 1280) | [9 * $hashStep; 10 * $hashStep)
				array (9 * $hashStep, 10 * $hashStep, 80),
			),
			array (// [1280; 1408) | [10 * $hashStep; 11 * $hashStep)
				array (9 * $hashStep, 10 * $hashStep, 80),
			),
		);
		for ($i = 0; $i < count($etalon); $i++) {
			$this->assertEquals($etalon[$i], $dbReader->getHashListByPos($i));
		}
		$this->assertEquals(false, $dbReader->getHashListByPos($i));
	}
}