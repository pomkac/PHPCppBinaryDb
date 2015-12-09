<?php
require_once dirname(__FILE__)."/binaryPacker.php";

class dbReader {
	private $dbStream;
	
	private $header;
	private $structVersion;
	private $buildVersion;
	private $dateTime;
	private $count;
	
	private $contentPtrs = array();
	
	private $hashPtrDeclared = array();
	
	private $hashStartReal;
	
	private $hashMin;
	private $hashMax;
	private $hashStep;
	
	private $curContent;
	private $curHashPos;
	private $curHashListCount;
	private $curHashListPos;
	
	const HASH_ELEM_START_IP_POS = 0;
	const HASH_ELEM_END_IP_POS = 1;
	const HASH_ELEM_CODE_PTR_POS = 2;
	
	const CONTENT_PTR_POS = 0;
	const CONTENT_VAL_POS = 1;
	
	private function readHeader() {
		$this->dbStream->seek(0);
		$this->header = $this->dbStream->read(4);
		$this->structVersion = $this->readInt();
		$this->buildVersion = $this->readInt();
		$this->dateTime = $this->readInt();
		$this->count = $this->readInt();
	}
	
	private function readContentPtrs() {
		for($i = 0; $i < $this->count; $i++) {
			$this->contentPtrs[] = $this->dbStream->tell();
			$this->readVarChar();
		}
	}
	
	private function readHashParams() {
		$this->dbStream->seek(-12, SEEK_END);
		$this->hashMin = $this->readInt();
		$this->hashMax = $this->readInt();
		$this->hashStep = $this->readInt();
		$this->readHashPtrDeclared();
	}
	
	private function readHashPtrDeclared() {
		$count = ($this->hashMax - $this->hashMin) / $this->hashStep;
		$this->dbStream->seek((-12 - $count * 4), SEEK_END);
		$buf = $this->dbStream->read($count * 4);
		for ($i = 0; $i < $count; $i++) {
			$this->hashPtrDeclared[] = binaryPacker::unpackInt(substr($buf, $i*4, 4));
		}
	}
	
	private function readHashIndexPtrs() {
		$this->hashStartReal = $this->dbStream->tell();
		$this->readHashParams();
	}
	
	private function readInt() {
		$buf = $this->dbStream->read(4);
		return binaryPacker::unpackInt($buf);
	}
	
	private function readVarChar() {
		$len = $this->readInt();
		return $this->dbStream->read($len);
	}
	
	private function resetContentPtrs() {
		$this->curContent = 0;
	}
	
	private function resetHashListPtrs() {
		$this->curHashPos = 0;
		$this->curHashListPos = 0;
		$this->curHashListCount = 0;
		$this->getNextHashListPtrs();
	}
	
	private function getNextContentPtr() {
		if ($this->curContent >= count($this->contentPtrs)) {
			return false;
		} 
		$result = $this->contentPtrs[$this->curContent];
		$this->curContent++;
		return $result;
	}
	
	private function getNextHashListPtrs() {
		do {
			if ($this->curHashPos >= count($this->hashPtrDeclared)) {
				return false;
			}
			$this->dbStream->seek($this->hashPtrDeclared[$this->curHashPos]);
			$this->curHashListCount = $this->readInt();
			$this->curHashPos++;
		} while($this->curHashListCount == 0);
		return true;
	}
	
	private function getNextHashElem() {
		if ($this->curHashListPos >= $this->curHashListCount) {
			$result = $this->getNextHashListPtrs();
			if (!$result) return false;
			$this->curHashListPos = 0;
		}
		$result = array();
		$result['startIp'] = $this->readInt();
		$result['endIp'] = $this->readInt();
		$result['ptr'] = $this->readInt();
		$this->curHashListPos++;
		return $result;
	}
	
	public function __construct($dbStream) {
		$this->dbStream = $dbStream;
	}
	
	public function readAll() {
		$this->readHeader();
		$this->readContentPtrs();
		$this->readHashIndexPtrs();
	}
	
	public function getHeader() {
		return array(
			"header" => $this->header,
			"structVersion" => $this->structVersion,
			"buildVersion" => $this->buildVersion,
			"dateTime" => $this->dateTime,
			"count" => $this->count,
		);
	}
	
	public function getContentByPos($pos) {
		if ($pos >= count($this->contentPtrs)) {
			return false;
		}
		$ptrs = $this->contentPtrs[$pos];
		$this->dbStream->seek($ptrs);
		$str = $this->readVarChar();
		return $str;
	}
	
	public function getHashListByPos($pos) {
		if ($pos >= count($this->hashPtrDeclared)) {
			return false;
		}
		$ptrs = $this->hashPtrDeclared[$pos];
		$this->dbStream->seek($ptrs);
		$count = $this->readInt();
		$result = array();
		for ($i = 0; $i < $count; $i++) {
			$row = array();
			$row[] = $this->readInt();
			$row[] = $this->readInt();
			$row[] = $this->readInt();
			$result[] = $row;
		}
		return $result;
	}
	
	public function checkConsistency() {
		//Проверка заголовка
		if ($this->header !== "DBCA") {
			throw new Exception("Не корректный заголовок. Получен {$this->header}");
		}
		//Проверка наличия мусора между содержимым и индексом
		if ($this->hashStartReal != $this->hashPtrDeclared[0]) {
			throw new Exception("Не совпадает адрес начала хеш индекса. Должен быть {$this->hashPtrDeclared[0]}, а соджимое заканчивается на {$this->hashStartReal}");
		}
		//Проверка соответствия индекса и данных
		$this->resetContentPtrs();
		$this->resetHashListPtrs();
		$contentPtr = $this->getNextContentPtr();
		$hashElem = $this->getNextHashElem();
		while($contentPtr !== false && $hashElem != false) {
			flush();
    		if ($hashElem['ptr'] > $contentPtr) {
				throw new Exception("В индексе отсутствует код {$contentPtr}");
			} elseif ($hashElem['ptr'] == $contentPtr) {
				$prevHashElem = $hashElem;
				while($prevHashElem['ptr'] == $hashElem['ptr']) {
					$hashElem = $this->getNextHashElem();
				}
				$contentPtr = $this->getNextContentPtr();
			} elseif ($hashElem['ptr'] < $contentPtr) {
				throw new Exception("В индексе присутствуют записи отсутствующие в данных");
			}
		}
		if ($contentPtr !== false) {
			$contentPtr = $this->getNextContentPtr();
			if ($contentPtr !== false) {
				$this->dbStream->seek($contentPtr);
				throw new Exception("Часть данных не проиндексирована (адрес {$contentPtr}):" . $this->readVarChar());
			}
		}
		if ($hashElem !== false) {
			if ($this->getNextHashElem() !== false) {
				throw new Exception("В индексе присутствуют записи отсутствующие в данных");
			}
		}
	}
}