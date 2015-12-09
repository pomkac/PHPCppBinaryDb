<?php

require_once dirname(__FILE__)."/include/collectorOptions.php";
require_once dirname(__FILE__)."/include/csvParser.php";
require_once dirname(__FILE__)."/include/collectorIterator.php";
require_once dirname(__FILE__)."/include/carrierCsvWriter.php";
require_once dirname(__FILE__)."/include/dbDataProcessor.php";
require_once dirname(__FILE__)."/include/dbStream.php";
require_once dirname(__FILE__)."/include/dbWriter.php";
require_once dirname(__FILE__)."/include/dbReader.php";

ini_set("memory_limit","2048M");
while (@ob_end_flush());
	ob_implicit_flush(true);
ini_set('implicit_flush', 1);

$time1 = microtime(true);
$options = getopt("", array(
			"configFile:", //имя файла с настройками сборки базы
		));

if (empty($options['configFile'])) {
	echo "Usage: --configFile=<value>";
	exit();
}
		
$jsonOpts = array(
			'connTypeCsv' => array("isFileName" => 1, "desc" => "имя файла csv с соединениями"),
			'ispNameCsv' => array("isFileName" => 1, "desc" => "имя файла csv с именами провайдеров"),
			'countryCodeCsv' => array("isFileName" => 1, "desc" => "имя файла csv с названиями стран"),
			'translateCsv' => array("isFileName" => 1, "desc" => "имя файла csv с переводами провайдеров"),
			'outputCsv' => array("desc" => "имя файла csv в который будут складываться коды провайдеров"),
			'outputDb' => array("desc" => "имя файла с итоговой базой"),
			collectorOptions::VERSION_NAME => array("desc" => "текущая версия БД с провайдерами"),
		);
echo "INFO: Получение опций\n";
$opts = new collectorOptions($jsonOpts, $options['configFile']);
echo "INFO: Подготовка файлов\n";
$connCsvParser = new csvParser($opts->connTypeCsv);
$ispNameCsvParser = new csvParser($opts->ispNameCsv, false);//Нет заголовка у csv
$countryCodeCsvParser = new csvParser($opts->countryCodeCsv);
$translateCsvParser = new csvParser($opts->translateCsv);

$collectorIterator = new collectorIterator($connCsvParser, $ispNameCsvParser, $countryCodeCsvParser, $translateCsvParser);

$carrierCsvWriter = new carrierCsvWriter($opts->outputCsv);
$dbDataProcessor = new dbDataProcessor();

echo "INFO: Обработка данных\n";
$i=0;
while ($row = $collectorIterator->getNext()) {
	$carrierCsvWriter->addNext($row);
    $dbDataProcessor->addCarrierInterval($row['ipSeg'], $carrierCsvWriter->getCode($row));
    $i++;
    if ($i%1000 == 0) echo "INFO: обработано {$i} записей\n";
}
unset($carrierCsvWriter);
echo "INFO: Запись базы\n";
$dbStream = new dbStream($opts->outputDb, false);
$dbWriter = new dbWriter($opts, $dbDataProcessor, $dbStream);
$dbWriter->writeDb();
$dbStream->close();
unset($dbStream);
unset($dbWriter);
echo "INFO: Обновление версии\n";
$versionName = collectorOptions::VERSION_NAME;
$opts->updateJsonFile($opts->$versionName + 1);
$dbStream = new dbStream($opts->outputDb, true);
echo "INFO: Проверка консистентности базы\n";
$dbReader = new dbReader($dbStream);
$dbReader->readAll();
$dbReader->checkConsistency();
echo "INFO: В пике использовано ".memory_get_peak_usage()."B\n";
$time2 = microtime(true);
echo "INFO: Создание заняло ".($time2-$time1)." сек";