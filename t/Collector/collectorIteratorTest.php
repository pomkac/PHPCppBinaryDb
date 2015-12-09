<?php

require_once dirname(__FILE__)."/../../include/collectorIterator.php";
require_once dirname(__FILE__)."/../Mocks/csvParserMock.php";

class collectorIteratorTest extends PHPUnit_Framework_TestCase {
	public function testMainCollect() {
		$dataConn = array(
			array('network_start_ip' => 1, 'network_last_ip' => 2, 'connection_type' => 'Dsl'),
			array('network_start_ip' => 3, 'network_last_ip' => 9, 'connection_type' => 'Cellular'),	
		);
		$dataIspName = array(
			array(4, 6, 'ISP1'),
			array(7, 9, 'ISP2'),
		);
		$dataCountry = array(
			array('startIpNum' => 2, 'endIpNum' => 4, 'country' => 'RU'),	
			array('startIpNum' => 5, 'endIpNum' => 6, 'country' => 'EN'),	
			array('startIpNum' => 7, 'endIpNum' => 9, 'country' => 'AL'),	
		);
		$dataTranslate = array(
			array('ispName' => 'ISP2', 'translate' => 'Провайдер2'),
		);
		$connCsvParser = new csvParserMock($dataConn);
		$ispNameCsvParser = new csvParserMock($dataIspName);
		$countryCodeCsvParser = new csvParserMock($dataCountry);
		$translateCsvParser = new csvParserMock($dataTranslate);
		$collIterator = new collectorIterator($connCsvParser, $ispNameCsvParser, $countryCodeCsvParser, $translateCsvParser);
		$result = array();
		while ($data = $collIterator->getNext()) {
			$result[] = $data;
		}
		$etalon = array(
			array("ipSeg" => new ipSegment(4,4), 'ispNameEn' => 'ISP1', 'countryCode' => 'RU', 'ispNameRu' => null),
			array("ipSeg" => new ipSegment(5,6), 'ispNameEn' => 'ISP1', 'countryCode' => 'EN', 'ispNameRu' => null),
			array("ipSeg" => new ipSegment(7,9), 'ispNameEn' => 'ISP2', 'countryCode' => 'AL', 'ispNameRu' => 'Провайдер2'),
		);
		$this->assertEquals($etalon, $result);
	}
};