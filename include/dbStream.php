<?php

class dbStream {
	private $fp;
	private $readOnly;
	
	private function init($fname) {
		$this->fp = fopen($fname, ($this->readOnly? "rb" : "w+b"));
        if (!$this->fp) {
            throw new Exception("Не удалось открыть {$fname}");
        }
	}
	
	protected function checkWrite() {
		if ($this->readOnly) {
		    throw new Exception("Файл открыт для чтения");
		}
	}
	
	public function __construct($fname, $readOnly) {
		$this->readOnly = $readOnly;
		$this->init($fname);
	}
	
	public function write($buf) {
		$this->checkWrite();
		return fwrite($this->fp, $buf);
	}
	
	public function read($count) {
		return fread($this->fp, $count);
	}
	
	public function tell() {
		return ftell($this->fp);
	}
	
	public function seek($pos, $whence = SEEK_SET) {
		return fseek($this->fp, $pos, $whence);
	}
	
	public function close() {
		fclose($this->fp);
	}
}