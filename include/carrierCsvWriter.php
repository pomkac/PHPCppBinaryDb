<?php

class carrierCsvWriter {
	private $carrierDic = array();
	private $fp;
	
	public function __construct($fileName) {
		$this->fp = fopen($fileName, "w");
		if (!$this->fp) {
			throw new Exception("Не удалось открыть {$fileName}");
		}
		fwrite($this->fp, "isp_code,isp_name_en,isp_name_ru\n");
	}
	
	public function addNext($arr) {
		$code = $this->getCode($arr);
		if (isset($this->carrierDic[$code])) {
			return;
		} else {
			$this->carrierDic[$code] = 1;
		}
		$row = array(
			$code,
			$arr['ispNameEn'],
			$arr['ispNameRu'],
		);
		fputcsv($this->fp, $row);
	}
	
	public function getCode($arr) {
		$code = str_replace(array(" ",",",".","&","!","-","(",")","'","/"), "_", strtolower($arr['ispNameEn']))."_".strtolower($arr['countryCode']);
		$code = preg_replace('/_+/', '_', $code); 
		return $code;
	}
};