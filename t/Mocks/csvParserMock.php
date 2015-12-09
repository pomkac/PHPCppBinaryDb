<?php

class csvParserMock {
	private $data;
	private $pos;
	
	public function __construct($data) {
		$this->data = $data;
		$this->pos = 0;
	}
	
	public function getNext($count = 0) {
		if ($count == 0) {
			$result = $this->data;
		} elseif ($count == 1) {
			$result = array();
			if (count($this->data) == $this->pos) {
				return false;
			} else {
				$result[] = $this->data[$this->pos];
				$this->pos++;
			}
		} else {
			throw new Exception("Mock error");
		}
		return $result;
	}
}