<?php

require_once dirname(__FILE__) . '/../../include/collectorOptions.php';

class collectorOptionsTest extends PHPUnit_Framework_TestCase
{
	/*
	$jsonOpts = array(
			'connTypeCsv' => array("isFileName" => true, "desc" => "имя файла csv с соединениями"),
			'ispNameCsv' => array("isFileName" => true, "desc" => "имя файла csv с именами провайдеров"),
			'countryCodeCsv' => array("isFileName" => true, "desc" => "имя файла csv с названиями стран"),
			'translateCsv' => array("isFileName" => true, "desc" => "имя файла csv с переводами провайдеров"),
			'outputCsv' => array("isFileName" => false, "desc" => "имя файла csv в который будут складываться коды провайдеров"),
			'outputDb' => array("isFileName" => false, "desc" => "имя файла с итоговой базой"),
			self::VERSION_NAME => array("isFileName" => false, "desc" => "текущая версия БД с провайдерами"),
		);
	*/
	public function testNonExistentFile() {
		$this->setExpectedException('Exception');
		$opts = new collectorOptions(array(), "Collector/nonExistent.json");
	}
	
	public function testNonExistentParam() {
		$this->setExpectedException('Exception');
		$jsonOpts = array(
			'nonExistent' => array("desc" => "test desc")
		);
		$opts = new collectorOptions($jsonOpts, "Collector/testJson/test.json");
	}
	
	public function testNonExistentClassAttr() {
		$this->setExpectedException('Exception');
		$jsonOpts = array(
			'param' => array("desc" => "test desc")
		);
		$opts = new collectorOptions($jsonOpts, "Collector/testJson/test.json");
		$opts->nonExistent;
	}
	
	public function testNonExistentParamFileName() {
		$this->setExpectedException('Exception');
		$jsonOpts = array(
			'fileName2' => array("isFileName" => 1, "desc" => "test desc")
		);
		$opts = new collectorOptions($jsonOpts, "Collector/testJson/test.json");
	}
	
	public function testExistentParam() {
		$jsonOpts = array(
			'param' => array("desc" => "test desc"),
			'fileName' => array("isFileName" => 1, "desc" => "test desc")
		);
		$opts = new collectorOptions($jsonOpts, "Collector/testJson/test.json");
		$this->assertEquals("val", $opts->param);
		$this->assertEquals(realpath(dirname(__FILE__)."/testCsv/quotes.csv"), realpath($opts->fileName));
	}
};