<?php

require_once dirname(__FILE__) . '/../../include/dbWriter.php';
require_once dirname(__FILE__) . '/../../include/dbReader.php';
require_once dirname(__FILE__) . '/../../include/dbDataProcessor.php';
require_once dirname(__FILE__) . '/../../include/dbStream.php';
require_once dirname(__FILE__) . '/../Mocks/optionsMock.php';
require_once dirname(__FILE__) . '/../../include/CarrierDb.php';

class extensionTest extends PHPUnit_Framework_TestCase {
	public function testExtension() {
		$this->assertTrue(extension_loaded("dbCarrier"));
	}
	
	public function testReading() {
		$dbData = new dbDataProcessor();
		$hashStep = dbWriter::HASH_STEP;
		$dbData->addCarrierInterval(new ipSegment(1,4), "carrier1");
    	$dbData->addCarrierInterval(new ipSegment(2*$hashStep, 3*$hashStep-1), "carrier2");
		$opts = new optionsMock();
		$dbStream = new dbStream(dirname(__FILE__)."/tmp/test1.db", false);
		$dbWriter = new dbWriter($opts, $dbData, $dbStream);
		$dbWriter->writeDb();
		$dbReader = new dbReader($dbStream);
		$dbReader->readAll();
		$dbReader->checkConsistency();
		$dbStream->close();
		$dbCarrier = new CarrierDb(dirname(__FILE__)."/tmp/test1.db");
		$info = $dbCarrier->getDbInfo();
		$this->assertEquals(dbWriter::STRUCT_VERSION, $info['structVersion']);
		$this->assertEquals(1, $info['buildVersion']);
		$this->assertTrue(time() - $info['buildTimestamp'] < 10); // меньше 10 секунд назад
		$this->assertEquals(2, $info['recCount']);
		$this->assertEquals(NULL, $dbCarrier->get("0.0.0.0"));
		$this->assertEquals("carrier1", $dbCarrier->get("0.0.0.1"));
		$this->assertEquals("carrier1", $dbCarrier->get("0.0.0.2"));
		$this->assertEquals("carrier1", $dbCarrier->get("0.0.0.4"));
		$this->assertEquals(NULL, $dbCarrier->get("0.0.0.5"));
		$ipSeg = new ipSegment(floor(1.5 * $hashStep), floor(2.5 * $hashStep));
		$this->assertEquals(NULL, $dbCarrier->get($ipSeg->getStartIpString()));
		$this->assertEquals("carrier2", $dbCarrier->get($ipSeg->getEndIpString()));
		$ipSeg = new ipSegment(3*$hashStep-1, 3*$hashStep);
		$this->assertEquals("carrier2", $dbCarrier->get($ipSeg->getStartIpString()));
		$this->assertEquals(NULL, $dbCarrier->get($ipSeg->getEndIpString()));
	}
	
	public function testReadingPhp() {
		$dbData = new dbDataProcessor();
		$hashStep = dbWriter::HASH_STEP;
		$dbData->addCarrierInterval(new ipSegment(1,4), "carrier1");
    	$dbData->addCarrierInterval(new ipSegment(2*$hashStep, 3*$hashStep-1), "carrier2");
		$opts = new optionsMock();
		$dbStream = new dbStream(dirname(__FILE__)."/tmp/test1.db", false);
		$dbWriter = new dbWriter($opts, $dbData, $dbStream);
		$dbWriter->writeDb();
		$dbReader = new dbReader($dbStream);
		$dbReader->readAll();
		$dbReader->checkConsistency();
		$dbStream->close();
		$dbCarrier = new CarrierDbPhp(dirname(__FILE__)."/tmp/test1.db");
		$info = $dbCarrier->getDbInfo();
		$this->assertEquals(dbWriter::STRUCT_VERSION, $info['structVersion']);
		$this->assertEquals(1, $info['buildVersion']);
		$this->assertTrue(time() - $info['buildTimestamp'] < 10); // меньше 10 секунд назад
		$this->assertEquals(2, $info['recCount']);
		$this->assertEquals(NULL, $dbCarrier->get("0.0.0.0"));
		$this->assertEquals("carrier1", $dbCarrier->get("0.0.0.1"));
		$this->assertEquals("carrier1", $dbCarrier->get("0.0.0.2"));
		$this->assertEquals("carrier1", $dbCarrier->get("0.0.0.4"));
		$this->assertEquals(NULL, $dbCarrier->get("0.0.0.5"));
		$ipSeg = new ipSegment(floor(1.5 * $hashStep), floor(2.5 * $hashStep));
		$this->assertEquals(NULL, $dbCarrier->get($ipSeg->getStartIpString()));
		$this->assertEquals("carrier2", $dbCarrier->get($ipSeg->getEndIpString()));
		$ipSeg = new ipSegment(3*$hashStep-1, 3*$hashStep);
		$this->assertEquals("carrier2", $dbCarrier->get($ipSeg->getStartIpString()));
		$this->assertEquals(NULL, $dbCarrier->get($ipSeg->getEndIpString()));
	}
}