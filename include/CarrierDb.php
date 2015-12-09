<?php

require_once dirname(__FILE__)."/binaryPacker.php";

class CarrierDbPhp {
	private $structVersion; 
	private $buildVersion; 
	private $buildTimestamp; 
	private $count;
	private $hashMin;
	private $hashMax;
	private $hashStep;
	private $hashListCount;
	private $fp;
	
	const STRUCT_VERSION = 1;
	
	private function hashFunc($val) {
		return floor(($val-$this->hashMin)/$this->hashStep);
	}
	
	public function __construct($fileName) {
		$this->fp = fopen($fileName, "rb");
		if (!$this->fp) {
			throw new Exception("Не удалось открыть файл {$fileName}");
		}
		fseek($this->fp, 4);
		$buf = fread($this->fp, 16);
		$this->structVersion = binaryPacker::unpackInt(substr($buf, 0, 4));
		if (self::STRUCT_VERSION != $this->structVersion) {
			throw new Exception("Структура базы не подходит для данного клиента");
		}
		$this->buildVersion = binaryPacker::unpackInt(substr($buf, 4, 4));
		$this->buildTimestamp = binaryPacker::unpackInt(substr($buf, 8, 4));
		$this->count = binaryPacker::unpackInt(substr($buf, 12, 4));
		fseek($this->fp, -12, SEEK_END);
		$buf = fread($this->fp, 12);
		$this->hashMin = binaryPacker::unpackInt(substr($buf, 0, 4));
		$this->hashMax = binaryPacker::unpackInt(substr($buf, 4, 4));
		$this->hashStep = binaryPacker::unpackInt(substr($buf, 8, 4));
		$this->hashListCount = ($this->hashMax - $this->hashMin) / $this->hashStep;
	}	
	
	public function getDbInfo() {
		$result = array();
		$result["structVersion"] = $this->structVersion;
		$result["buildVersion"] = $this->buildVersion;
		$result["buildTimestamp"] = $this->buildTimestamp;
		$result["recCount"] = $this->count;
		return $result;
	}
	
	public function get($ip) {
		$ipFloat = (float) sprintf("%u", ip2long($ip));
		if ($ipFloat < $this->hashMin || $ipFloat >= $this->hashMax) {
			return NULL;
		}
		$pos = $this->hashFunc($ipFloat);
		fseek($this->fp, -12 - $this->hashListCount * 4 + $pos * 4,SEEK_END);
		$ptr = binaryPacker::unpackInt(fread($this->fp, 4));
		fseek($this->fp, $ptr);
		$listCount = binaryPacker::unpackInt(fread($this->fp, 4));
		$found = false;
		if ($listCount == 0) return NULL;
		$buf = fread($this->fp, $listCount * 12);
		for ($i = 0; $i < $listCount; $i++) {
			$curSegmentMin = binaryPacker::unpackInt(substr($buf, $i * 12, 4));
			if ($ipFloat<$curSegmentMin) return NULL;
			$curSegmentMax = binaryPacker::unpackInt(substr($buf, 4 + $i * 12, 4));
			if ($curSegmentMin <= $ipFloat && $ipFloat <= $curSegmentMax) {
				$codePtrs = binaryPacker::unpackInt(substr($buf, 8 + $i * 12, 4));
				$found = true;
				break;
			}
		}
		if (!$found) return NULL;
		fseek($this->fp, $codePtrs);
		$varCharSize = binaryPacker::unpackInt(fread($this->fp, 4));
		return fread($this->fp, $varCharSize);
	}
};