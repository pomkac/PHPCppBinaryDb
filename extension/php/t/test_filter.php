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
sbf_set($fh, $test['fields'],array(0));
$k=1;
foreach($test['test'] as $item) {
	$i=0;
	foreach($item as $field) { 
		for ($i = $k; $i < $k+$field['times']; $i++) {
			sbf_set($fh, array($field['index']=>$field['data']),array(1,$i));	
		}
    }
	$k=$i;
}
fclose($fh);

$eta = $test['eta'];
//$tst = array();

# читаем данные из sbf-файла
$fh = sbf_open($dat_filename);
if (!isset($test['filter'])) 
  throw new Exception("can not read size of $dat_filename");
$res=sbf_get_filtered_list(1, 0, $fh, sbf_read_hash_entry($fh), array(0 => 1), $test['filter'], array_flip($test['fields']));
print_r($res);
$tst=count($res);


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
