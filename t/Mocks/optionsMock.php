<?php

require_once dirname(__FILE__)."/../../include/collectorOptions.php";

class optionsMock {
	public function __get($name) {
		if ($name == collectorOptions::VERSION_NAME)
			return 1;
		else 
			throw new Exception("Не правильный вызов mock объекта");
	}
}