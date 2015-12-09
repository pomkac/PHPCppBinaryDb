<?php

require_once dirname(__FILE__)."/ipSegment.php";

class collectorIterator {
	private $connCsvParser;
	private $ispNameCsvParser;
	private $countryCodeCsvParser;
	private $translateDic = array();
	private $curCellSeg;
	private $curIspNameSeg;
	private $curCountrySeg;
	private $curCellIspIntersetSeg = false;
	private $curFinalIntersetSeg = false;
	
	private function getNextCellular() {
		while($connRow = $this->connCsvParser->getNext(1)){
			if ($connRow[0]['connection_type'] === 'Cellular') {
				if (!isset($connRow[0]['network']) && !isset($connRow[0]['network_start_ip'],$connRow[0]['network_last_ip'])) {
					throw new Exception("ERROR: Не найдено поле network");
				}
				if (isset($connRow[0]['network_start_ip'])) {
					$cellSeg = new ipSegment($connRow[0]['network_start_ip'], $connRow[0]['network_last_ip']);
				} else {
					$cellSeg = new ipSegment(0, 0);
					$cellSeg->setCidr($connRow[0]['network']);
				}
				return $cellSeg;
			}
		}
		return false;
	}
	
	private function getNextIspName() {
		if ($ispRow = $this->ispNameCsvParser->getNext(1)){
			$ispIpSeg = new ipSegment($ispRow[0][0], $ispRow[0][1], $ispRow[0][2]);
			return $ispIpSeg;
		} else {
			return false;
		}
	}
	
	private function getNextCountryCode() {
		if ($codeRow = $this->countryCodeCsvParser->getNext(1)){
			$countryIpSeg = new ipSegment($codeRow[0]['startIpNum'], $codeRow[0]['endIpNum'], $codeRow[0]['country']);
			return $countryIpSeg;
		} else {
			return false;
		}
	}
	
	private function getReturnStatement() {
		$result = array(
			'ipSeg' => $this->curFinalIntersetSeg,
			'ispNameEn' => $this->curIspNameSeg->data,
			'countryCode' => $this->curCountrySeg->data,
			'ispNameRu' => isset($this->translateDic[$this->curIspNameSeg->data]) ? $this->translateDic[$this->curIspNameSeg->data] : NULL,
		);
		return $result;
	}
	
	public function __construct($connCsvParser, $ispNameCsvParser, $countryCodeCsvParser, $translateCsvParser) {
		$this->connCsvParser = $connCsvParser;
		$this->ispNameCsvParser = $ispNameCsvParser;
		$this->countryCodeCsvParser = $countryCodeCsvParser;
		$data = $translateCsvParser->getNext();
		foreach ($data as $row) {
			$this->translateDic[$row['ispName']] = $row['translate'];
		}
		$this->curCellSeg = $this->getNextCellular();
		if (!$this->curCellSeg) {
			throw new Exception("В csv с типами соединений отсутствует Cellular");
		}
		$this->curIspNameSeg = $this->getNextIspName();
		if (!$this->curIspNameSeg) {
			throw new Exception("В csv с названи¤ми провайдеров отсутствует данные");
		}
		$this->curCountrySeg = $this->getNextCountryCode();
		if (!$this->curCountrySeg) {
			throw new Exception("В csv со странами отсутствует данные");
		}
	}
	
	public function getNext() {
		if (!$this->curCellSeg) return false;
		if (!$this->curIspNameSeg) return false;
		if (!$this->curCountrySeg) return false;
		$maxStartIp = max(	$this->curCellSeg->getStartIpFloat(),
							$this->curIspNameSeg->getStartIpFloat(),
							$this->curCountrySeg->getStartIpFloat());
		while ($this->curCellSeg->getEndIpFloat()<$maxStartIp) {
			$this->curCellSeg = $this->getNextCellular();
			if (!$this->curCellSeg) return false;
		}
		while ($this->curIspNameSeg->getEndIpFloat()<$maxStartIp) {
			$this->curIspNameSeg = $this->getNextIspName();
			if (!$this->curIspNameSeg) return false;
		}
		while ($this->curCountrySeg->getEndIpFloat()<$maxStartIp) {
			$this->curCountrySeg = $this->getNextCountryCode();
			if (!$this->curCountrySeg) return false;
		}
        if ($this->curCellSeg->checkIntersection($this->curIspNameSeg) &&
            $this->curCellSeg->checkIntersection($this->curCountrySeg) &&
            $this->curIspNameSeg->checkIntersection($this->curCountrySeg)) {

            $this->curFinalIntersetSeg = $this->curCellSeg->getIntersection($this->curIspNameSeg)->getIntersection($this->curCountrySeg);
        	$result = $this->getReturnStatement();
            if ($this->curCellSeg->getEndIpFloat() == $this->curFinalIntersetSeg->getEndIpFloat()) {
                $this->curCellSeg = $this->getNextCellular();
            }
            if ($this->curIspNameSeg->getEndIpFloat() == $this->curFinalIntersetSeg->getEndIpFloat()) {
                $this->curIspNameSeg = $this->getNextIspName();
            }
            if ($this->curCountrySeg->getEndIpFloat() == $this->curFinalIntersetSeg->getEndIpFloat()) {
                $this->curCountrySeg = $this->getNextCountryCode();
            }
            return $result;
        } else {
			$minEndIp = min($this->curCellSeg->getEndIpFloat(),
				$this->curIspNameSeg->getEndIpFloat(),
				$this->curCountrySeg->getEndIpFloat());
			$hasInterval = array();
			$notHasInterval = array();
			if (!$this->curCellSeg->isInsideIp($minEndIp)) {
				$notHasInterval[] = "тип соединения (ближайший ".$this->curCellSeg->getStartIpString()."-".$this->curCellSeg->getEndIpString().":Cellular)";
			} else {
				$hasInterval[] = "типа соединения (".$this->curCellSeg->getStartIpString()."/".$this->curCellSeg->getEndIpString().":Cellular)";
			}
            if (!$this->curIspNameSeg->isInsideIp($minEndIp)) {
				$notHasInterval[] = "название провайдера (ближайшее ".$this->curIspNameSeg->getStartIpString()."-".$this->curIspNameSeg->getEndIpString().":".$this->curIspNameSeg->data.")";
			} else {
				$hasInterval[] = "названия провайдера (".$this->curIspNameSeg->getStartIpString()."/".$this->curIspNameSeg->getEndIpString().":".$this->curIspNameSeg->data.")";
			}
			if (!$this->curCountrySeg->isInsideIp($minEndIp)) {
				$notHasInterval[] = "страна провайдера (ближайшая ".$this->curCountrySeg->getStartIpString()."-".$this->curCountrySeg->getEndIpString().":".$this->curCountrySeg->data.")";
			} else {
				$hasInterval[] = "страны провайдера (".$this->curCountrySeg->getStartIpString()."/".$this->curCountrySeg->getEndIpString().":".$this->curCountrySeg->data.")";
			}
			$message = "Для ".implode(' и ', $hasInterval)." не найдено ".implode(' и ',$notHasInterval);
			echo "ERROR:$message\n";
            return $this->getNext();
        }
	}
};