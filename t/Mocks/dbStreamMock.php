<?php

require_once dirname(__FILE__)."/../../include/dbStream.php";

class dbStreamMock extends dbStream {
	private $buf;
	private $pos;
	
	public function __construct($fname, $readOnly) {
		$this->readOnly = $readOnly;
	}
	
	public function write($buf) {
		$this->checkWrite();
		$this->buf .= $buf;
		$this->pos += strlen($buf);
		return true;	
	}
	
	public function read($count) {
		$result = substr($this->buf, $this->pos, $count);
		$this->pos += $count;
		return $result;
	}
	
	public function tell() {
		return $this->pos;
	}
	
	public function seek($pos, $whence = SEEK_SET) {
		switch($whence) {
		case SEEK_SET: 
			$this->pos = $pos;
			break;
		case SEEK_END:
			$this->pos = strlen($this->buf) + $pos;
			break;
		case SEEK_CUR:
			$this->pos += $pos;
			break;
		}
	}
	
	public function close() {
	}
}