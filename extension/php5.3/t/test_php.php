<?php

require_once "SBF0.php";

if (count($argv) != 2) die("bad args: " . count($argv) . "\n");
$test_filename = $argv[1];

# $test_filename = "000.t";

$dat_filename = "$test_filename.dat";
$tst_filename = "$test_filename.tst";
$eta_filename = "$test_filename.eta";

# читаем данные теста
$test = read_test_data($test_filename);

# записываем данные в sbf
if (file_exists($dat_filename))
  unlink($dat_filename);
$fh = sbf_openw($dat_filename);
foreach($test['test'] as $item) 
{
  sbf_set($fh, $item['data'], $item['index']);	
}
fclose($fh);

$eta = $test['eta'];
$tst = array();

# проверяем размер sbf-файла
if (isset($eta['0 - size'])) 
{
  clearstatcache();
  $size = filesize($dat_filename);
  if ($size == FALSE) 
    throw new Exception("can not read size of $dat_filename");
  $tst['0 - size'] = $size;
}    

# читаем данные из sbf-файла
$fh = sbf_open($dat_filename);
foreach($eta['1 - get'] as $item) 
{
  $index_list = $item['0 - index'];
    
  $tst['1 - get'][] = array(
    '0 - index' => $index_list,
    '1 - data'  => sbf_get($fh, $index_list)
  );
}
#fclose($fh);

# записываем результаты
file_put_contents($eta_filename, var_export($eta, TRUE));
file_put_contents($tst_filename, var_export($tst, TRUE));

# -----------------------------------------------------------------------------

function read_test_data($test_filename) 
{
  $text = file_get_contents("$test_filename.txt");
    
  $result = NULL;
  $text = '$result = ' . $text . ';';
  eval($text);
  return $result;
}

# -----------------------------------------------------------------------------

?>
