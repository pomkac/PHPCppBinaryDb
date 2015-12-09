<?php 

class ipSegment {
	private $startIp;
	private $endIp;
	
	public $data = false;
	
	private function setIp($inVal, &$mVal) {
		if (is_numeric($inVal)) {
			$mVal = (float) $inVal;
		} else {
			$mVal = (float) sprintf("%u", ip2long($inVal));
		}
	}
	
	private function getIp(&$mVal, $asString = true) {
		if ($asString) {
			return long2ip($mVal);
		} else {
			return $mVal;
		}
	}
	
	public function __construct($startIp, $endIp, $data = false) {
		$this->setStartIp($startIp);
		$this->setEndIp($endIp);
		$this->data = $data;
	}
	
	public function setCidr($cidrIp, $data = false) {
		$cidr = explode('/', $cidrIp);
		if (count($cidr) != 2) {
			throw new Exception("Не корректный cidr {$cidrIp}");
		} 		
		$this->setStartIp(long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1])))));
		$this->setEndIp(long2ip((ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1));
		$this->data = $data;
	}
	
	public function setStartIp($startIp) {
		$this->setIp($startIp, $this->startIp);
	}
	
	public function setEndIp($endIp) {
		$this->setIp($endIp, $this->endIp);
	}
	
	public function getStartIpString() {
		return $this->getIp($this->startIp);
	}
	
	public function getEndIpString() {
		return $this->getIp($this->endIp);
	}
	
	public function getStartIpFloat() {
		return $this->getIp($this->startIp, false);
	}
	
	public function getEndIpFloat() {
		return $this->getIp($this->endIp, false);
	}
	
	public function checkIntersection($otherIpSeg) {
		return 	$this->getEndIpFloat() >= $otherIpSeg->getStartIpFloat() && 
				$otherIpSeg->getEndIpFloat() >= $this->getStartIpFloat();
	}
	
	public function getIntersection($otherIpSeg) {
		$min = max($this->getStartIpFloat(), $otherIpSeg->getStartIpFloat());
		$max = min($this->getEndIpFloat(), $otherIpSeg->getEndIpFloat());
		if ($min>$max) {
			throw new Exception("Не пересекающиеся интервалы");
		}
		return new ipSegment($min, $max);
	}
	
	public function isInsideIp($ip) {
		if ($this->getStartIpFloat() <= $ip && $ip <= $this->getEndIpFloat()) return true;
		else return false;
	}
};