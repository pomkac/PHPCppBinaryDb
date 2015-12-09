<?php

require_once dirname(__FILE__)."/collectorOptions.php";
require_once dirname(__FILE__)."/dbDataProcessor.php";

class dbWriter {
	private $options;
	private $dbData;
    private $dbStream;
    
	private $hashMin;
    private $hashMax;
    private $hashStep;
	private $dataFilePtr;
    
	const STRUCT_VERSION = 1;
    const HASH_STEP = 4096;
    
	private function writeHeader() {
        $this->dbStream->write("DBCA"); // file header
        $this->writeInt(self::STRUCT_VERSION); // binary structure version
        $versionName = collectorOptions::VERSION_NAME;
        $this->writeInt($this->options->$versionName); // binary build version
        $this->writeInt(time()); // binary build date
        $this->writeInt($this->dbData->getCount()); // count of data
    }
    
    private function writeContent() {
		$data = $this->dbData->resetData();
		do {
			$this->dataFilePtr[$this->dbData->getCurrentKey()] = $this->dbStream->tell();
            $this->writeVarChar($data[dbDataProcessor::CODE_POS]);// carrier code
        } while($data = $this->dbData->getNext());
    }
    
	private function countHashParams() {
        $minIp = $this->dbData->getMinIp();
        $maxIp = $this->dbData->getMaxIp() + 1; // +1 т.к. у нас каждый хэш интервал имеет вид [a;b)
        $this->hashMin = $minIp;
        $this->hashStep = self::HASH_STEP;
        $this->hashMax = $this->hashMin + ceil(($maxIp - $minIp) / $this->hashStep) * $this->hashStep;	
    }
        
    private function writeHashIndex() {
        $this->hashListPtr = array();
        $curData = $this->dbData->resetData();
        $ipSeg = new ipSegment($curData[dbDataProcessor::IP_START_POS], $curData[dbDataProcessor::IP_END_POS]);
        $ipSeg2 = new ipSegment(0, 0);
        $count = ($this->hashMax - $this->hashMin) / $this->hashStep;
        for ($i = 0; $i < $count; $i ++) {
            $hashSegmentStart = $this->hashMin + $this->hashStep * $i;
            $ipSeg2->setStartIp($hashSegmentStart);
            $ipSeg2->setEndIp($hashSegmentStart+$this->hashStep - 1);
            //Проверяем вхождение
            $hashList = array();
            while($ipSeg->checkIntersection($ipSeg2)) {
                $hashList[] =  $this->dbData->getCurrentKey();
                if ($curData[dbDataProcessor::IP_END_POS] > $hashSegmentStart + $this->hashStep - 0.001) {
                    break; // Продлеваем текущий интервал на следующий шаг
                }
                $curData = $this->dbData->getNext();
                $ipSeg->setStartIp($curData[dbDataProcessor::IP_START_POS]);
                $ipSeg->setEndIp($curData[dbDataProcessor::IP_END_POS]);
                if ($curData === false) break;
            }
            $hashListPtr[] = $this->dbStream->tell();
            $this->writeInt(count($hashList));
            foreach($hashList as $dataKey) {
                $this->writeInt($this->dbData->getStartIpByKey($dataKey));
                $this->writeInt($this->dbData->getEndIpByKey($dataKey));
                $this->writeInt($this->dataFilePtr[$dataKey]);
            }
            unset($hashList);
            if ($curData === false) break;
        }
        //Записываем указатели на Хэши
        foreach ($hashListPtr as $hashPtr) {
            $this->writeInt($hashPtr);
        }
        //Записываем параметры хэш функции
        $this->writeInt($this->hashMin);
        $this->writeInt($this->hashMax);
        $this->writeInt($this->hashStep);;
    }
	
	private function writeInt($intVal) {
        $this->dbStream->write(binaryPacker::packInt($intVal));    
    }
    
    private function writeVarChar($strVal) {
        $this->writeInt(strlen($strVal)); // length of carrier code
        $this->dbStream->write($strVal); // carrier code
    }
	
    public function __construct(&$options, &$dbData, &$dbStream) {
        $this->dbStream = $dbStream;
        $this->options = $options;
		$this->dbData = $dbData;
    }
	
	public function writeDb() {
        $this->writeHeader();
        $this->writeContent();
        $this->countHashParams();
        $this->writeHashIndex();
	}
}