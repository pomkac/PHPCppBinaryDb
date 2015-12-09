<?php

class collectorOptions {
	const VERSION_NAME = 'version';
	
	private $jsonFields;
		
	private $confFileName;
		
	public function __get($name) {
		if (isset($this->jsonFields[$name]["val"])) {
    		return($this->jsonFields[$name]["val"]);
		} else {
			throw new Exception("Неизвестный аттрибут {$name}");
		}
	}
	
	public function __construct($jsonFields, $confFileName) {
		$this->jsonFields = $jsonFields;
		$this->confFileName = getcwd()."/".$confFileName;
		if (!is_file($this->confFileName)) {
			throw new Exception("Не удалось найти файл {$this->confFileName}");
		}
		
		$jsonOpts = json_decode(file_get_contents($this->confFileName), true);
		foreach ($this->jsonFields as $key => $val) {
			if (!isset($jsonOpts[$key])) {
				throw new Exception("В файле с параметрами отсутствует ".$this->jsonFields[$key]['desc']);
			}
			if(!empty($this->jsonFields[$key]['isFileName'])) {
				$jsonOpts[$key] = dirname($this->confFileName) . "/" . $jsonOpts[$key];
			} 
			if (!empty($this->jsonFields[$key]['isFileName']) && !is_file($jsonOpts[$key])) {
				throw new Exception("Не найден файл указанный в переменной содержащей ".$this->jsonFields[$key]['desc']);
			}
			$this->jsonFields[$key]['val'] = $jsonOpts[$key];
		}
	}
	
	public function updateJsonFile($newVersion) {
		$jsonOpts = json_decode(file_get_contents($this->confFileName), true);
		$jsonOpts[self::VERSION_NAME] = $newVersion;
		if (file_put_contents($this->confFileName,json_encode($jsonOpts)) === false) {
			throw new Exception("Не удалось записать новый json");
		}
	}
};