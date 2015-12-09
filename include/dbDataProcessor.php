<?php

require_once dirname(__FILE__)."/binaryPacker.php";
require_once dirname(__FILE__)."/collectorOptions.php";

class dbDataProcessor {
    private $curIpSeg = null;
    private $curCode = null;
    private $data = array();
    
    const IP_START_POS = 0;
    const IP_END_POS = 1;
    const CODE_POS = 2;
    
    private function addRecord() {
        $row = array(
            self::IP_START_POS => $this->curIpSeg->getStartIpFloat(),
            self::IP_END_POS => $this->curIpSeg->getEndIpFloat(),
            self::CODE_POS => $this->curCode,
        );
        $this->data[] = $row;
    }
    
    public function getMinIp() {
        $first = reset($this->data);
        return $first[self::IP_START_POS];
    }
    
    public function getMaxIp() {
        $last = end($this->data);
        return $last[self::IP_END_POS];
    }
    
    public function resetData() {
        return reset($this->data);
    }
    
    public function getNext() {
        return next($this->data);
    }
    
    public function getCurrentKey() {
        return key($this->data);
    }
    
    public function getStartIpByKey($key) {
        return $this->data[$key][self::IP_START_POS];
    }
    
    public function getEndIpByKey($key) {
        return $this->data[$key][self::IP_END_POS];
    }
    
    public function getCount() {
        return count($this->data);
    }
    
    public function addCarrierInterval($ipSeg, $code) {
        if ($code == $this->curCode && $ipSeg->getStartIpFloat() - $this->curIpSeg->getEndIpFloat() < 1 + 0.001) {
            $pos = count($this->data) - 1;
            $this->data[$pos][self::IP_END_POS] = $ipSeg->getEndIpFloat();
        } else {
            $this->curIpSeg = $ipSeg;
            $this->curCode = $code;
            $this->addRecord();
        }
    }
};