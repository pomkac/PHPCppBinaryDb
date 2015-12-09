<?php

/*! =h1 SBF0.php */

/*+ =h2 Константы */

define('SINGLE_SEARCH_FOR_SCALAR', 1);
define('SINGLE_SEARCH_FOR_SUBHASH', 2);

define('REF_SIZE_READ_MASK', 224);//0b11100000;
define('REF_SIZE_SHIFT', 5);

define('FIELD_QT_READ_MASK', 31);//0b00011111;

define('NULL_TYPE', 0); //0b00000000;
define('HASH_TYPE', 4);//0b00000100;
define('INTEGER_TYPE', 64);//0b01000000;
define('STRING_TYPE', 128);//0b10000000;
define('STRINT_TYPE_MASK', 192);//0b11000000;

define('SSS_READ_MASK', 7);//0b00000111;
define('SSS_SHIFT', 0);

define('RRR_READ_MASK', 56);    //0b00111000;
define('RRR_WRITE_MASK', 199);  //0b11000111
define('RRR_SHIFT', 3);

define('DEBUG', 0);
define('TRACE', 0);
define('DUMP',  0);
define('ASSERTION',  1);

define('TRACE_WRITE', 0);

/*-*/

/*! =h2 Функции */

/*+ =h3 */
function sbf_openw($filename) 
/*-*/
{
	clearstatcache();
	
	$mode = file_exists($filename) ? 'r+b' : 'x+b';
	$fh = fopen($filename, $mode);
	if (!$fh) 
		throw new Exception("Failed to open $filename");
	if (filesize($filename) < 8) 
		write_buf(pack('A4N', 'SBF0', 0), $fh, SEEK_SET, 0);
	else {
		$header = read_buf(4, $fh, SEEK_SET, 0);
		if ((ASSERTION) && ( $header != 'SBF0'))
			throw new Exception('Failed to find SBF0 signature in ' . $filename);
	}
	return $fh;
}

/*! =h3 function sbf_get($fh, [индекс] )

$fh - дескриптор sbf-файла.

[индекс] - допустимо задавать либо NULL, либо в виде массива(линейный список), 
либо просто в виде аргументов функции:

  =pre
  $data = sbf_get($fh); 
  $data = sbf_get($fh, NULL ); 
  $data = sbf_get($fh, array() ); 

  =pre
  $data = sbf_get($fh, 1, 2, 3); 
  $data = sbf_get($fh, array(1, 2, 3)); 

*/
function sbf_getw() 
{
	$args = func_get_args();
	if (!count($args)) 
		throw new Exception('bad args');
	
    $fh = array_shift($args);	
	
	if (!count($args)) 
		$index_list = array();
	elseif ( ( count($args) == 1) && (gettype($args[0]) == 'array') )
		$index_list = $args[0];
	elseif ( ( count($args) == 1) && (gettype($args[0]) == 'NULL') )
		$index_list = array();	
	else 
		$index_list = $args;
	
	foreach ($index_list as $index) validate_index($index);
		
	$hash_entry_ptr = read_hash_entry($fh);
	return read_hash($fh, $hash_entry_ptr, $index_list);
}

/*+ =h3 */
function sbf_set($fh, $data, $index_list) 
/*-*/
{
	if (TRACE_WRITE) printf("<===SET== data=%s\nindex_list=%s===>\n", var_export($data, TRUE), var_export($index_list, TRUE));
	if (!isset($index_list))
		$index_list = array();
	elseif (gettype($index_list) != 'array') 
		$index_list = array($index_list);
	
	foreach ($index_list as $index => $value) validate_index($index);
	
	# !ref($data) && @{$index_list} || ref($data) eq 'HASH' || die;  
	
	if (gettype($data) != 'array')
		$data = array( array_pop($index_list) => $data );
	
	prepare_fields($data);
	
	$index_list[] = $data;
	
	write_hash($fh, 4, $index_list);
}

/*! =h2 Internals */

/*+ =h3 */
function write_hash($fh, $hash_ptr_addr, $index_list) 
/*-*/
{
	if (!isset($hash_ptr_addr)) throw new Exception('bad hash_ptr_addr');
	if (gettype($index_list) != 'array') throw new Exception('index_list: array expected');
	if (!count($index_list)) throw new Exception('empty index_list');
		
    if (TRACE_WRITE) $tmp_index_list = var_export($index_list, TRUE);
	
	$ptr = read_hash_entry($fh, $hash_ptr_addr);	
	
	# получаем первый элемент индекса
	$index = array_shift($index_list);
	$single_search = count($index_list) ? TRUE : FALSE;
	
	if (TRACE_WRITE) printf("write_hash start: hash_ptr_addr=%03Xh ptr=%Xh single_search=%s index=%s \$index_list=%s\n", 
		$hash_ptr_addr, $ptr, var_export($single_search, TRUE), var_export($index, TRUE), $tmp_index_list);
	
	# !$single_search || @{$index_list} || die;
	if ($single_search && count($index_list) == 0) 
		throw new Exception();
		
	$result = array();	
	
	# все элементы индекса, которые осталось посмотреть
   	$everywhere_look_for = array();
	$new_fields = array();
   	if (!$single_search) {
   		$keys = array_keys($index);
   		if (!count($keys)) return;
		$everywhere_look_for = $index;
   	}
	$is_first_round = 1;
   	$prev_rec_ptr = $ptr;
	
	if (TRACE_WRITE) printf("write_hash: everywhere_look_for=%s\n", var_export($everywhere_look_for, TRUE));
	
	# обход уже имеющихся записей хэша
	while (
		($prev_rec_ptr) && ( $single_search || count($everywhere_look_for) )
    ) {
		$ptr = $prev_rec_ptr;		
		
		$rec_desc = read_rec_desc($fh, $ptr);
		
		# if (TRACE_WRITE) printf("read rec_desc: ptr=0x%X; field_qt_bits=0x%X; ref_size_bits=0b%b\n", 
		#	$ptr, $rec_desc['FIELD_QT_BITS'], $rec_desc['REF_SIZE_BITS']);
		
		if ($is_first_round && $rec_desc['FIELD_QT_BITS'] == 0) {
			$list_entry_pos = ftell($fh);
			
			if ($single_search && ($index > 0)) {
   				$list_item_addr = force_get_list_item_addr($fh, $list_entry_pos, $index);
   				write_hash($fh, $list_item_addr, $index_list);
   				return;
       		} 
			elseif (!$single_search) {
				
				$list_ids = array();
				foreach ($everywhere_look_for as $id => $value)
					if ($id > 0) 
						$list_ids[] = $id;
				
        		foreach ($list_ids as $id) {
     		    	$upd = $everywhere_look_for[$id];
					if (ASSERTION && !isset($upd))  throw new Exception();
        			$list_item_addr = force_get_list_item_addr($fh, $list_entry_pos, $id);
					
   					if ($list_item_addr) 
						write_hash($fh, $list_item_addr, array($upd['value']) );
   					else 
						$new_fields[$id] = $upd;
					
					unset($everywhere_look_for[$id]);
        		}
 	    		if (!count($everywhere_look_for)) break;

			}
			$is_first_round = 0;
          	$prev_rec_ptr = read_int(4, $fh, SEEK_SET, $list_entry_pos + 1024);			
		}
		elseif ( (!$is_first_round) && ($rec_desc['FIELD_QT_BITS'] == 0) ) {
			$ref = !$ref_size ? 0 : read_int($ref_size, $fh, SEEK_CUR, 1024 + 4);
           	$prev_rec_ptr = !$ref ? 0 : $ptr - $ref;

		}
		// FIELD_QT_BITS > 0
		else {
			if ($is_first_round) {
				if (!$single_search) {
					foreach($everywhere_look_for as $id => $value) 
						if ($id > 0) {
							$new_fields[$id] = $everywhere_look_for[$id];
							unset($everywhere_look_for[$id]);
						}
					if (!count($everywhere_look_for))
						break;					
				} 
				elseif ($single_search && $index > 0) {
                	$hash_ptr_addr = mk_new_hash_rec( $index, $fh, $hash_ptr_addr );
                	write_hash($fh, $hash_ptr_addr, $index_list);
					return;
				}
				$is_first_round = 0;
			} 			
			
			// читаем количество полей
			$field_qt = ($rec_desc['FIELD_QT_BITS'] == FIELD_QT_READ_MASK) 
				? read_int(1, $fh, SEEK_CUR, 0) + 1
				: $rec_desc['FIELD_QT_BITS'];
			
            $fields = array();
			$hash_ids = array();
			
			# читаем FIELD_DEFS
			for ($i = 0; $i < $field_qt; $i++) {
           		$id = - read_int(1, $fh, SEEK_CUR, 0);
           		$def_pos = ftell($fh);
           		$def = read_int(1, $fh, SEEK_CUR, 0);
           		
           		$type = ($def & STRINT_TYPE_MASK) ? ($def & STRINT_TYPE_MASK) : $def;
				$fields[$id] = array( 'type' => $type );
           		$field =& $fields[$id];
				
				if (
					$type == STRING_TYPE && (
						!$single_search && array_key_exists($id, $everywhere_look_for)
						||
						$single_search && $index == $id
					)
				)	{
					$field['def'] = $def;
					$field['def_pos'] = $def_pos;
				}

           		$field['SSS'] = ($def & SSS_READ_MASK) >> SSS_SHIFT;
				
				if ($type == STRING_TYPE)
					$field['RRR'] = ($def & RRR_READ_MASK) >> RRR_SHIFT;

           		$hash_ids[] = $id;				
			}
			
			# читаем REF
            $ref = $rec_desc['REF_SIZE_BITS'] ? read_int($rec_desc['REF_SIZE_BITS'], $fh, SEEK_CUR, 0) : 0;
           	$prev_rec_ptr = $ref ? $ptr - $ref : 0;
			
			if (TRACE_WRITE) {
				printf("write_hash: got field_defs=%s\nhash_ids=%s\n", var_export($fields, TRUE), var_export($hash_ids, TRUE));
				printf("write_hash: read record and get ref: ref=%s\n", $ref);			
			}
			
			$at_this_rec_look_for = array();
			if ($single_search) { 
				
           		if (!array_key_exists($index, $fields)) {
           			continue;
           		} 
				elseif (
           			$single_search && 
           			$fields[$index]['type'] != HASH_TYPE
           			||
           			$fields[$index]['type'] == NULL_TYPE
           		) {
           			break;
           		}
           	}
           	else {
           		$ids = array();
				foreach ($everywhere_look_for as $id => $upd) {
					
					if (!array_key_exists($id, $fields))
						continue;
					
					$type = $fields[$id]['type'];
					$SSS = $fields[$id]['SSS'];
					
       				$inplace = ( ($upd['type'] == $type) && ($upd['SSS'] <= $SSS));
					
       				if (!($inplace && $type == NULL_TYPE)) {
       					if ($inplace) 
       						$at_this_rec_look_for[$id] = $upd;
       					else 
       						$new_fields{$id} = $upd;
       				}
       				$ids[] = $id;
           		}
				foreach($ids as $id) 
					unset($everywhere_look_for[$id]);
           		if (!count($at_this_rec_look_for))
					continue;
           	} 

			$skip_len = 0;
        	$sids = array();
			$hids  = array();
        	foreach ($hash_ids as $id) {
        		$field =& $fields[$id]; 
				$type =& $field['type'];
				$SSS =& $field['SSS'];
				
        		if (ASSERTION) {
					if (!
						(
							!$single_search || 
							$fields[$index]['type'] == HASH_TYPE
						)
					)
						throw new Exception(sprintf("type=%s single_search=%s", $fields[$index]['type'], $single_search));
				}
				
       		    $dont_skip_this_field = 
       		    	($single_search && $index == $id)
       		    	|| 
       		    	(
						!$single_search && (
							$type == STRING_TYPE || array_key_exists($id, $at_this_rec_look_for)
						)
					)
       		    ;
				
				// пропускаем этот ключ
       		    if (!$dont_skip_this_field) {
        			$skip_len += $SSS;
    		    } 
				elseif ( $type == HASH_TYPE ) {
        			$field['value_ptr_addr'] = ftell($fh) + $skip_len;
        			$hids[] = $id;
        			$skip_len += 4;
        		} 
				else {
					$short_value = read_int($SSS, $fh, SEEK_CUR, $skip_len);
        			$skip_len = 0;
			
        			if (ASSERTION && $single_search) throw new Exception();
					
					// update для integer
            		if ( $type == INTEGER_TYPE ) {

     		    		$upd = $at_this_rec_look_for[$id];
						
     		    		if ($upd['value'] != $field['value']) {
     		    			$SSS =& $field['SSS'];
							if ($upd['SSS'] == $SSS)
								$buf = $upd['short_value_buf'];
							else
								list($buf) = pack_int($upd['short_value'], $SSS);

       						write_buf($buf, $fh, SEEK_CUR, -$SSS);
     		    		}
     		    		unset($at_this_rec_look_for[$id]);
						if (!count($at_this_rec_look_for)) break;
        		    } 
					elseif ( $type == STRING_TYPE ) {

						
            			$field['short_value'] = $short_value;
            			$sids[] = $id;
						


            			if (array_key_exists($id, $at_this_rec_look_for)) {
         		    		$upd =& $at_this_rec_look_for[$id];

         		    		if ($field['short_value'] < $upd['short_value']) {
           						$new_fields[$id] = $upd;
           						unset($at_this_rec_look_for[$id]);
								if (!count($at_this_rec_look_for)) break;
         		    		} 
            			}

        		    } 
					else 
						throw new Exception("unexpected type $type");
    		    }
        	}			

			if (TRACE_WRITE) 
				printf("hids=%s\n", var_export($hids, TRUE));
				
			
			foreach ($sids as $id) {
				
        		$field =& $fields[$id];

        		if (ASSERTION && $single_search)
					throw new Exception();
				
       		    if (!(
       		    	($single_search && $index == $id)
       		    	|| 
       		    	(!$single_search && array_key_exists($id, $at_this_rec_look_for))
       		    )) {
        			$skip_len += $field['short_value'];
       		    } 
				else {
        			$value = read_buf($field['short_value'], $fh, SEEK_CUR, $skip_len);
        			if ($field['RRR']) {
        				$RRR = $field['RRR'];
						
						$RRR_buf = substr($value, -$RRR, $RRR);
						$value = substr_replace($value, '', -$RRR, $RRR);
						
        				$RRR_value = unpack_int(strlen($RRR_buf), $RRR_buf);
        				$tail_len = $RRR_value - $RRR;
						if ($tail_len)
							$value = substr_replace($value, '', -$tail_len, $tail_len);
        			}
        			$skip_len = 0;
					
					$upd = $at_this_rec_look_for[$id];
					
    	   			if ($upd['value'] != $value) {
   		    		    $buf =& $value;
   		    		    $buf_len =& $field['short_value'];
   		    		    $upd_len =& $upd['short_value'];
						$buf = substr_replace($buf, $upd['value'], 0, $upd_len);
   		    		    $RRR_value = $buf_len - $upd_len;
   		    		    list($RRR_buf, $RRR) = pack_int($RRR_value);
   	    		    	if ($RRR_value > 0)
							$buf = substr_replace($buf, $RRR_buf, -$RRR, $RRR);
       					write_buf($buf, $fh, SEEK_CUR, -$buf_len);
     		    		if ($RRR != $field['RRR']) {
     		    			$def = ($field['def'] & RRR_WRITE_MASK) | ($RRR << RRR_SHIFT);
     		    			$def_pos =& $field['def_pos'];
     		    			$pos = ftell($fh);
     		    			write_buf(pack('C', $def), $fh, SEEK_SET, $def_pos);
     		    			$skip_len += $pos - $def_pos - 1;
     		    		}
    	   			}
		    		unset($at_this_rec_look_for[$id]);
		    		if (!count($at_this_rec_look_for)) 
						break;
       		    }
        	}			
			
			foreach ($hids as $id) {
         		$field =& $fields[$id]; 
         		if ($single_search) {
         			$field['value'] = write_hash($fh, $field['value_ptr_addr'], $index_list);
         			if ($single_search) return;
         		} else {
         			$upd = $at_this_rec_look_for[$id];
         			write_hash($fh, $field['value_ptr_addr'], array( $upd['value']));
   		    		unset($at_this_rec_look_for[$id]);
					if (!count($at_this_rec_look_for)) break;;
         		} 				
			}
			
			if (ASSERTION ) {
        		if ($single_search || count($at_this_rec_look_for)) throw new Exception();
			}
		}
	}
	
	if ($single_search) {
		$hash_ptr_addr = mk_new_hash_rec( $index, $fh, $hash_ptr_addr );
		if (TRACE_WRITE) printf("write_hash(hash_ptr_addr=%03Xh): single_search, switch to child\n", $hash_ptr_addr);		
       	write_hash($fh, $hash_ptr_addr, $index_list);
		if (TRACE_WRITE) printf("write_hash(hash_ptr_addr=%s): single_search, return from child\n", $hash_ptr_addr);				
   	} 
	else {
		foreach ($everywhere_look_for as $key => $value)
		  $new_fields[$key] = $value;
		
		if (TRACE_WRITE) printf("write_hash(hash_ptr_addr=%Xh) new_fields=%s\n", $hash_ptr_addr, var_export($new_fields, TRUE));		

       	if (count($new_fields))
			mk_new_hash_rec($new_fields, $fh, $hash_ptr_addr);
	}
}

/*+ =h3 */
function force_get_list_item_addr($fh, $pos, $i, $force_write = true) 
/*-
  Поиск элемента с индексом $i в массиве.
  Если $force_write == true, в массиве "прокладывется" путь для отсутствующего элемента
  и забивается нулями.

  Returns: $pos == позиция элемента в файле. Если $force_write == false и 
  элемент не найден, возвращается NULL.
*/
{
	validate_index($i);

	$level = 0;
	
	$max_values  = array(0xFF, 0xFFFF, 0xFFFFFF, 0xFFFFFFFF);
	$max = $max_values[$level];
	$mask = array(0xFF, 0xFF00, 0xFF0000, 0xFF000000);
	
	$go_up = $i > $max;	

	while ($go_up && $i > $max || !$go_up && $level > 0) {
		if ($go_up) {
    		$level++;
			if (ASSERTION && $level > 3) throw new Exception('too big index');
			$max = $max_values[$level];
    	} 
		else {
    		$pos += (($i & $mask[$level]) >> (8 * $level)) * 4;
    		$level--;
    	}

		# printf("force_get_list_item_addr: go_up=%s level: %d pos: %s, i: $i, max: %X\n", $go_up, $level, $pos, $max);

		$next_pos = read_int(4, $fh, SEEK_SET, $pos);
		
		if ($force_write) {
			if ($next_pos) {
				$pos = $next_pos;
				$go_up = $go_up && $i > $max;			
				continue;
			}
			$next_pos = write_buf(pack('x1024'), $fh, SEEK_END, 0);
			write_N($next_pos, $fh, SEEK_SET, $pos);
		}
		else {
			if (!$next_pos) return NULL;
		}
		$pos = $next_pos;
		$go_up = $go_up && $i > $max;		
	}
	
	$pos += ($i & 0xFF) * 4;

	return $pos;	
}

/*+*/
function mk_new_hash_rec($data, $fh, $hash_ptr_addr) 
/*-*/	
{
	/*
	my $data = shift;
	defined($data) && &$is_valid_index($data) 
		|| ref($data) eq 'HASH' 
		|| confess Dumper($data) . ' ';
	my $fh = shift || die;
	my $hash_ptr_addr = shift;
	defined($hash_ptr_addr) || die;
	*/
	
    $index = NULL;
	
	if (!isset($data)) 
		throw new Exception();
	elseif (gettype($data) == 'array') {
		if (!count($data)) return;
	}
	else {
		validate_index($data);
		$index = $data;
		$data = array(
			$index => prepare_field($index, array() )
		);
	}
	
	# if (TRACE_WRITE) printf("mk_new_hash_rec start. \$hash_ptr_addr=0x%x, data=%s\n", $hash_ptr_addr, var_export($data, TRUE));	
	
	// читаем точку входа в хэш - получаем адрес терминальной записи в хэше
	$node_ptr = NULL;
	$last_rec_ptr = read_hash_entry($fh, $hash_ptr_addr);
	# if (TRACE_WRITE) printf("mk_new_hash_rec(0x%X): read_hash_entry, last_rec_addr=0x%X\n", $hash_ptr_addr, $last_rec_addr);

	// если есть записи, читаем rec_desc 
	if (
		$last_rec_ptr && 
		((read_int(1, $fh, SEEK_SET, $last_rec_ptr) & FIELD_QT_READ_MASK) == 0)
	) {
		$node_ptr = $last_rec_ptr;
		$last_rec_ptr = read_int(4, $fh, SEEK_CUR, 1024);
		
	}	
	
	# записываем запись, в конец файла
   	$result = fseek($fh, 0, SEEK_END);
	if ($result == -1) throw new Exception();
   	$ptr = ftell($fh);
	
	$ref = ($last_rec_ptr) ? $ptr - $last_rec_ptr : 0;
	list($ref_buf, $ref_size) = pack_int($ref);
	$rec_desc = $ref_size << REF_SIZE_SHIFT; 	
	
	# if (TRACE_WRITE) printf("mk_new_hash_rec: prepare ref=%Xh ref_size=%s ptr=%Xh last_rec_ptr=%Xh\n", $ref, $ref_size, $ptr, $last_rec_ptr);
	
	$list_ids = array();
	foreach($data as $key => $value) 
			if ($key > 0) $list_ids[] = $key;
	ksort($list_ids);		
	
	# записываем список идентификаторов > 0
	if (count($list_ids)) {
		$rec_desc_buf = pack('C', $rec_desc);
    	$list_entry_buf = pack('x1024');
    	$last_rec_addr_buf = pack('N', $ptr);

    	$buf = implode('', array(
			$rec_desc_buf,
    		$list_entry_buf, 
    		$last_rec_addr_buf,
    		$ref_buf,
    	)); 
       	write_buf($buf, $fh, SEEK_SET, $ptr);
    	if (ASSERTION  && $node_ptr) throw new Exception();
		
       	write_N($ptr, $fh, SEEK_SET, $hash_ptr_addr);

    	$list_entry_pos = $ptr + 1;
    	if (isset($index)) {
    		return force_get_list_item_addr($fh, $list_entry_pos, $index);
    	} else {
           	foreach ($list_ids as $id) {
           		$field =& $data[$id]; 
           		$sub_hash_ptr_addr = force_get_list_item_addr($fh, $list_entry_pos, $id);
           		if (ASSERTION && $field['type'] != HASH_TYPE) throw new Exception();
         		mk_new_hash_rec($field['value'], $fh, $sub_hash_ptr_addr);
           	}
    	}
		foreach($list_ids as $id)
			unset($data[$id]);
    	mk_new_hash_rec($data, $fh, $hash_ptr_addr);		
	}
	else {
		# записываем список идентификаторов <= 0
		$hash_ids = array();
		foreach($data as $key => $value) 
			if ($key <= 0) $hash_ids[] = $key;
		ksort($hash_ids);
		
       	$field_qt = count($hash_ids);
       	$field_qt_is_few = ($field_qt < FIELD_QT_READ_MASK);
    	$field_qt_buf = $field_qt_is_few ? '' : pack('C', $field_qt - 1);
		
    	$rec_desc |= ($field_qt_is_few ? $field_qt : FIELD_QT_READ_MASK);
		$rec_desc_buf = pack('C', $rec_desc);
		
		$field_defs_buf = '';			
		$short_values_buf = '';
		$long_values_buf = '';
		foreach ($hash_ids as $key) {
			$field_defs_buf .= pack('C', -$key) . $data[$key]['def'];
			$short_values_buf .= $data[$key]['short_value_buf']; 
			
			if (array_key_exists('long_value_buf', $data[$key]))
				$long_values_buf .= $data[$key]['long_value_buf'];
		}
		
    	$buf = implode(array(
    		$rec_desc_buf,
    		$field_qt_buf, 
    		$field_defs_buf, 
    		$ref_buf,
    		$short_values_buf,
    		$long_values_buf,
    	)); 
       	write_buf($buf, $fh, SEEK_SET, $ptr);
       	if ($node_ptr) $hash_ptr_addr = $node_ptr + 1 + 1024;
		
		// обновляем точку входа в хэш
       	write_N($ptr, $fh, SEEK_SET, $hash_ptr_addr);		
		
		if (TRACE_WRITE) 
			printf("mk_new_hash_rec(hash_ptr_addr=0x%X rec_ptr=0x%X, size=%s bytes):
				rec_desc_buf='%s'
				field_qt_buf='%s'
				field_defs_buf='%s'
				ref_buf='%s'
				short_values_buf='%s'
				long_values_buf='%s'\n",
				$hash_ptr_addr,
				$ptr,
				strlen($buf),
				$rec_desc_buf, 
				$field_qt_buf,
				$field_defs_buf,
				$ref_buf,
				$short_values_buf,
				$long_values_buf
				);				
		
       	$short_value_buf_base = 
			$ptr 
			+ strlen($buf)
			- strlen($short_values_buf)
			- strlen($long_values_buf)
		;

        $sub_hash_ptr_addr = $short_value_buf_base;
  		if (isset($index)) return $sub_hash_ptr_addr;
  			
       	foreach ($hash_ids as $id) {
       		$field = $data[$id];
			
      		if ($field['type'] == HASH_TYPE) 
				mk_new_hash_rec($field['value'], $fh, $sub_hash_ptr_addr);

       		$sub_hash_ptr_addr += $field['SSS'];
       	}		
	}
}

/*+ =h3 */
function prepare_fields(&$data) 
/*-*/
{
	if (gettype($data) != 'array') throw new Exception('array expected');
	
	foreach ($data as $id => $value) {
		validate_index($id);
		$data[$id] = prepare_field($id, $value);
		if ($data[$id]['type'] == HASH_TYPE) 
			prepare_fields($data[$id]['value']);
	}
	
}

/*+ =h3 */
function prepare_field($id, $value) 
/*-*/
{
	$value_type = gettype($value);
	
	if ( ($id > 0) && ($value_type != 'array'))
		return array(
			'value' => array( 0 => $value ), 
			'type' => HASH_TYPE,
			'short_value_buf' => pack('x4'),
			'SSS' => 4,
			'def' => pack('C', HASH_TYPE),
		);
	
	$field = array( 'value' => $value );

   	if ($value_type == 'NULL') {
   		$field['type'] = NULL_TYPE;
		$field['short_value_buf'] = '';
		$field['SSS'] = 0;
   	} 
	elseif ($value_type == 'array') {
   		$field['type'] = HASH_TYPE;
		$field['short_value_buf'] = pack('x4');
		$field['SSS'] = 4;		
   	} 
	elseif (($value_type == 'string') or ($value_type == 'integer')) {
		// пробуем упаковать в integer, если не получается, то в строку.
		if ( ($value_type == 'integer') || ($value == (string)((int) $data))) {
			$field['type'] = INTEGER_TYPE;
			$field['short_value'] = (int) $value;
		}
		else {
			$field['type'] = STRING_TYPE;
			$field['short_value'] = strlen($value);			
			$field['long_value_buf'] = $value;		
		}
		list($field['short_value_buf'], $field['SSS']) = pack_int($field['short_value']);
	}
	else { // "boolean" "double" "object" "resource" "unknown type"
		throw new Exception("not supported: $value_type");
	}
	
   	$field['def'] = pack('C', $field['type'] | $field['SSS']);

   	return $field; 	
}

/*+ =h3 */
function pack_int($value, $buf_len = NULL)
/*-*/	
{
	# $value &= 0xFFFFFFFF;
	
	if (isset($buf_len)) {
		if (!($buf_len >= 0 && $buf_len <= 4)) throw new Exception();
	} elseif ($value & 0xFF000000) {
		$buf_len = 4;
	} elseif ($value & 0x00FF0000) {
		$buf_len = 3;
	} elseif ($value & 0x0000FF00) {
		$buf_len = 2;
	} elseif ($value & 0x000000FF) {
		$buf_len = 1;
	} else {
		$buf_len = 0;
	}
	
	$buf;
	if ($buf_len == 0) {
		$buf = '';
	} elseif ($buf_len == 1) {
		$buf = pack('C', $value);
	} elseif ($buf_len == 2) {
		$buf = pack('n', $value);
	} elseif ($buf_len == 3) {
		$buf = pack('C', $value >> 16) . pack('n', $value & 0xFFFF);
	} else {
		$buf = pack('N', $value);
	}	
	
	return array($buf, $buf_len);
}

/*+ =h3*/
function write_buf($buffer, $fh, $whence, $pos) 
/*-*/
{
	
	if (!isset($buffer)) $buffer = '';
	
	$flag = fseek($fh, $pos, $whence);
	if ($flag != 0) 
		throw new Exception("ERR: failed to seek(\$fh: $fh, \$pos: 0x%X == $pos, \$whence: $whence)");
	
	$pos = ftell($fh);
	
	$len = strlen($buffer);
	$flag = fwrite($fh, $buffer, $len);
	if ($flag == FALSE) 
		throw new Exception("ERR: failed to fwrite(\$fh: $fh, \$pos: 0x%X == $pos, \$whence: $whence)");	
	
	return $pos;
}

		
/*+ =h3*/
function write_N($N, $fh, $whence, $pos) 
/*-*/
{
	write_buf(pack('N', $N), $fh, $whence, $pos);
	return $N;
}

/*+ =h3 */
function unpack_int($buf_len, $buf) 
/*-*/
{
	if ($buf_len > 4)
		throw new Exception("ERR: unpack_int does not support \$buf_len == $buf_len case");
	if ($buf_len == 0) {
		$result = 0;
	} elseif ($buf_len == 1) {
		$result = ord($buf[0]); 
		//$array = unpack('C', $buf); $result = $array[1];
	} elseif ($buf_len == 2) {
		$result = (ord($buf[0]) * 0x100 ) + ord($buf[1]);
		//$array = unpack('n', $buf); $result = $array[1]; 
	} elseif ($buf_len == 3) {
		$array = unpack('Cval1/nval2', $buf);
        $result = ($array['val1'] << 16) | $array['val2'];		
	} elseif ($buf_len == 4) {
		$array = unpack('N', $buf); 
		$result = $array[1];
		//$result = (((ord($buf[0]) * 256 ) + ord($buf[1])) * 256 + ord($buf[2])) * 256 + ord($buf[3]);
	} 
	return $result;
}

/*+ =h3 */
function read_buf($buf_len, $fh, $whence, $pos) 
/*-
read_buf - чтение буфера из файла.

Параметры: 

  =li $buf_len - размер буфера 

  =li $fh - указатель файла

  =li $whence, $pos,  - тип смещения и смещение(см. FileSysem Functions/fseek)

*/
{
	if ($buf_len == 0)
		// throw new Exception('$buf_len: ' . $buf_len);
		return '';
		
    if ($whence != SEEK_CUR || $pos != 0) 
		if (fseek($fh, $pos, $whence))
			throw new Exception(sprintf("ERR: failed to fseek(\$fh: $fh, \$pos: 0x%X == $pos, \$whence: $whence)", $pos));
		
	if (($buf = fread($fh, $buf_len)) === FALSE)
		throw new Exception(sprintf("ERR: failed to fread(\$fh, $buf_len) at pos 0x%X", ftell($fh)));
	return $buf;
}

/*+ =h3*/
function read_int($buf_len, $fh, $whence, $pos) 
/*-*/	
{
	return !$buf_len ? 0 : unpack_int($buf_len, read_buf($buf_len, $fh, $whence, $pos));
}

/*+ =h3 */
function read_hash_entry($fh, $hash_ptr_addr = 4) 
/*-*/
{
	$hash_entry_ptr = read_int(4, $fh, SEEK_SET, $hash_ptr_addr);
	return $hash_entry_ptr;
}

/*+ =h3 */
function read_rec_desc($fh, $ptr) 
/*-
	Читает поле описания записи. Возвращает структуру вида 

	=pre
	array(
		'FIELD_QT_BITS' => ...,  # количество полей в записи
		'REF_SIZE_BITS' => ...   # кол-во байт (0-4) на хранение смещения предыдущей записи относительно текущей записи
	);	
*/
{
	$rec_desc = read_int(1, $fh, SEEK_SET, $ptr);
	
	$result = array(
		'FIELD_QT_BITS' => $rec_desc & FIELD_QT_READ_MASK,
		'REF_SIZE_BITS' => ($rec_desc & REF_SIZE_READ_MASK) >> REF_SIZE_SHIFT
	);
	
    if (ASSERTION) {
	   if (!($rec_desc['REF_SIZE_BITS'] >= 0)) throw new Exception();
	   if (!($rec_desc['REF_SIZE_BITS'] <= 4)) throw new Exception();
    }	
		
	return $result;
}

/*+ =h3 */
function read_hash($fh, $hash_entry_ptr, $index_list)
/*-*/	
{
	if (gettype($index_list) != 'array') 
		throw new Exception('array expected');
	
	$ptr = $hash_entry_ptr;
if (TRACE) printf("============\nread_hash: \$ptr: 0x%X\n", $ptr);	
	
	if (!$ptr) return array();
	
    $index = array_shift($index_list);
	if (!is_null($index))
		$single_search = count($index_list) ? SINGLE_SEARCH_FOR_SUBHASH : SINGLE_SEARCH_FOR_SCALAR;
   		
	$is_first_round = 1;
   	$prev_rec_ptr = $ptr;
	$result = array();
	
	# обход записей хэша
    while ($ptr = $prev_rec_ptr) {
		if (TRACE) printf("read record: \$ptr 0x%X\n", $ptr);	
		if (TRACE) printf("\$is_first_round: %d, \$single_search: %d, \$index: %d \n", $is_first_round, $single_search, $index);
		
		# чтение rec_desc
		$rec_desc = read_rec_desc($fh, $ptr);
        $field_qt = $rec_desc['FIELD_QT_BITS'];
        $ref_size = $rec_desc['REF_SIZE_BITS'];
		
		# если первая запись и текущая запись является узловой
		if ($is_first_round && ($rec_desc['FIELD_QT_BITS'] == 0)) {

        	$list_entry_pos = ftell($fh);
			
			if (TRACE) printf("\$list_entry_pos: 0x%X, \$single_search: %d, \$index: %d\n", $list_entry_pos, $single_search, $index);	

			if ($single_search && $index > 0) {
				$list_item_addr = force_get_list_item_addr($fh, $list_entry_pos, $index, false);
				if (is_null($list_item_addr)) return NULL;

				if (TRACE) printf("\$list_item_addr: 0x%X\n", $list_item_addr);	
				
				$list_item_hash_entry_ptr = read_hash_entry($fh, $list_item_addr);
       			return read_hash($fh, $list_item_hash_entry_ptr, $index_list);
       		} 
			# первая запись в хэше, текущая запись является узловой, выбор всех элементов.
			elseif (!$single_search) {
				
				$pos = $list_entry_pos;
				$index_base = 0;
				$ptrs = NULL;
				$i = NULL;
				
   				$stack = array();
				array_push($stack, array());
   				$level = 0;
				
				if (TRACE) printf("\$pos: 0x%X\n", $pos);	
				
     			while(count($stack)) {
					
     				if (is_null($ptrs)) {
						if (TRACE) printf("read list section[0x%X]\n", $pos);
						# считываем элементы секции списка
						$array = unpack('N256', read_buf(1024, $fh, SEEK_SET, $pos)); 
         				$ptrs = array_values($array);
         				$i = count($stack) == 1 ? 1 : 0;
         			} else 
         				$i++;						
					
					# if (TRACE) printf("check list item[0x%X]: ptrs[%d]=0x%X, level=$level, count(\$stack)=%s\n", $pos, $i, $ptrs[$i], $level, count($stack));
					
      				if ($i > 0xFF) {
      					array_pop($stack);
      					if (count($stack)) {
      						$top = $stack[count($stack) - 1];
							$pos = $top['pos'];
							$index_base = $top['index_base'];
							$ptrs = $top['ptrs'];
							$i = $top['i'];
      					} elseif ($ptrs[0] && $level < 3) { # && $level < 3 -- предохранитель
							$pos = $ptrs[0];
							$index_base = 0; 
							$ptrs = NULL; 
							$i = NULL;
            				array_push($stack, array());
      						$level += 1;
      					}
      				} 
					elseif ($ptrs[$i]) {
            			if ($level - count($stack) + 1 == 0) {
							$result[$index_base + $i] = read_hash($fh, $ptrs[$i], $index_list);
            			} else {
							# переход к "нижней" секции
      						$top =& $stack[count($stack) - 1];
							$top['pos'] = $pos;
							$top['index_base'] = $index_base;
							$top['ptrs'] = $ptrs;
							$top['i'] = $i;
							
      						$pos = $ptrs[$i]; 
							$index_base = ($index_base + $i) << 8; 
							$ptrs = NULL;
							$i = NULL;
            				array_push($stack, array());
            			}
      				} 
         		}
			} 

			$is_first_round = 0;
          	$prev_rec_ptr = read_int(4, $fh, SEEK_SET, $list_entry_pos + 1024);
			if (TRACE) printf("\$prev_rec_ptr: 0x%X\n", $prev_rec_ptr);	
		} 
		elseif (!$is_first_round && !$field_qt) {
            $ref = !$ref_size ? 0 : read_int($ref_size, $fh, SEEK_CUR, 1024 + 4);
           	$prev_rec_ptr = !$ref ? 0 : $ptr - $ref;
			if (TRACE) printf("\$prev_rec_ptr: 0x%X\n", $prev_rec_ptr);	
		} 
		else {
			
			if ($is_first_round) {
				if ($single_search && ($index > 0)) return NULL;
				$is_first_round = 0;
			} 

            if ($field_qt == FIELD_QT_READ_MASK) 
				$field_qt = read_int(1, $fh, SEEK_CUR, 0) + 1;

			$field_defs_len = $field_qt * 2;


			if (TRACE) printf("\$field_defs_len: %d\n", $field_defs_len);				
			if (DUMP) $dump_field_def_pos = ftell($fh) - 1;
			
			# чтение FIELD_DEFS и REF
			$buf = read_buf($field_defs_len + $ref_size, $fh, SEEK_CUR, 0);
			$field_defs = unpack("C$field_defs_len", $buf);
			
			$fields = array();
			$hash_ids = array();
			
			$SSS_sum = 0;
           	for ($i = 1; $i <= $field_defs_len; $i += 2) {
           		$id = - $field_defs[$i];
           		$def =& $field_defs[$i + 1];
				
           		$type = ($def & STRINT_TYPE_MASK);
				if (!$type) $type = $def;
           		
				$SSS = ($def & SSS_READ_MASK) >> SSS_SHIFT;
				$SSS_sum += $SSS;
				$field = array('type' => $type, 'SSS' => $SSS, 'idx'=>($i-1)/2);
				
           		if ($type == STRING_TYPE) 
					$field['RRR'] = ($def & RRR_READ_MASK) >> RRR_SHIFT;
				
				$fields[$id] = $field;
           		$hash_ids[] = $id;
				if ($id == $index && $single_search) { 
					$index_field_type = $fields[$index]['type'];
					if (
						$single_search == SINGLE_SEARCH_FOR_SUBHASH && 
						$index_field_type != HASH_TYPE
						||
						$index_field_type == NULL_TYPE
					) return NULL;
				} elseif ($type == NULL_TYPE && !$single_search && !array_key_exists($id, $result))
					$result[$id] = NULL;
				
			}
			
			# парсинг REF
			$ref = $ref_size ? unpack_int($ref_size, substr($buf, -$ref_size, $ref_size)) : 0;
           	$prev_rec_ptr = $ref ? $ptr - $ref : 0;
			
        	$skip_len = 0;
			$back_len = 0;
			
			$short_values_buf = read_buf($SSS_sum, $fh, SEEK_CUR, 0);

			if (TRACE) printf("\$SSS_sum: %d, strlen(\$short_values_buf): %d\n", $SSS_sum, strlen($short_values_buf));				

			# обход ключей хэша
			$sids = array();  
			$hids = array();  
        	foreach ($hash_ids as $id) {
				$field =& $fields[$id];
				$type =& $field['type']; 
				$SSS =& $field['SSS'];
				
       		    $dont_skip_this_field = 
       		    	$single_search && (
       		    		$index == $id
       		    		||
        		    	$index < $id 
        		    		&& $index_field_type == STRING_TYPE 
        		    		&& $type == STRING_TYPE
       		    	)
       		    	|| 
       		    	!$single_search && ($type == STRING_TYPE || !array_key_exists($id, $result))
       		    ;

if (TRACE) printf("\$id: %d, \$type: %d, \$SSS: %d, \$dont_skip_this_field: %d\n", $id, $type, $SSS, $dont_skip_this_field);				

       		    if (!$dont_skip_this_field) {

        			$skip_len += $SSS;
if (TRACE) printf("\$id: %d, \$skip_len: %d\n", $id, $skip_len);				

        		} else {
if (TRACE) printf("\$skip_len: %d, \$SSS: %d, strlen(substr(\$short_values_buf, \$skip_len, \$SSS)): %d\n", $skip_len, $SSS, strlen(substr($short_values_buf, $skip_len, $SSS)));				
					$short_value = unpack_int($SSS, substr($short_values_buf, $back_len + $skip_len, $SSS));
					$back_len += $skip_len + $SSS;
        			$skip_len = 0;
					
if (TRACE) printf("\$short_value: 0x%X\n", $short_value);				
            		if ( $type == INTEGER_TYPE ) {
    		    		if ($single_search) return $short_value;
						$field['value'] = $short_value;
        		    } elseif ($type == STRING_TYPE) { 
						$field['short_value'] = $short_value;
            			$sids[] = $id;
        		    } elseif ($type == HASH_TYPE) {
if (TRACE) printf("\$field['value_hash_entry_ptr']: 0x%X\n", $short_value);				
						$field['value_hash_entry_ptr'] = $short_value;
						$hids[] = $id;
					} else throw new Exception("unexpected type $type");
					
					if ($single_search && $index == $id) break;
    		    }
        	}

			# обход коротких значений
        	$skip_len = 0;
			if ($sids) 
			foreach ($sids as $id) {
        		$field =& $fields[$id];
				$short_value =& $field['short_value'];

if (TRACE) printf("sids: \$id: %d; \$short_value: %d\n", $id, $short_value);				

       		    if (
       		    	$single_search && $index == $id
       		    	|| 
       		    	!$single_search && !array_key_exists($id, $result)
       		    ) {
if (TRACE) printf("sids: \$short_value: %d, \$skip_len: %d\n", $short_value, $skip_len);				
        			$value = read_buf($short_value, $fh, SEEK_CUR, $skip_len);
        			$skip_len = 0;
						
        			$RRR =& $field['RRR'];
        			if ($RRR) {
						$RRR_buf = substr($value, -$RRR, $RRR);
        				$tail_len = unpack_int(strlen($RRR_buf), $RRR_buf);
						if ($tail_len) $value = substr($value, 0, -$tail_len);
        			}

        	   		if ($single_search) return $value;
        	   		$field['value'] = $value;
       		    }
				else {
        			$skip_len += $short_value;
					if (TRACE) printf("sids: \$skip_len: %d\n", $skip_len);				
				}
        	}

        	if ($hids) 
           	foreach ($hids as $id) {
         		$field =& $fields[$id]; 
       			$field['value'] = read_hash($fh, $field['value_hash_entry_ptr'], $index_list);
       			if ($single_search) return $field['value'];
           	}
        	
     		foreach ($hash_ids as $id) 
				if (!array_key_exists($id, $result)) 
					$result[$id] = $fields[$id]['value'];
		} 
    }
	
	return $single_search ? NULL : $result;
}

/*+ =h3 */
function iterate_list($callback, $rec, $start_from, $desc, $fh, $hash_entry_ptr, $index_list) 
/*-*/
{
	$ptr = $hash_entry_ptr;
//if (TRACE) print("============ get_list_entry_pos\n");	

	while ($ptr) {
//if (TRACE) printf("\$ptr: 0x%X, count(\$index_list): %d\n", $ptr, count($index_list));	
		if (count($index_list)) {
			$search_subhash = 1;
			$index = array_shift($index_list);
		} else {	
			$search_subhash = 0;
			$index = NULL;
		}
			
		$is_first_round = 1;
		$prev_rec_ptr = $ptr;

		while ($ptr = $prev_rec_ptr) {
			$rec_desc = read_int(1, $fh, SEEK_SET, $ptr);

			$field_qt = $rec_desc & FIELD_QT_READ_MASK;
			$ref_size = ($rec_desc & REF_SIZE_READ_MASK) >> REF_SIZE_SHIFT;
//if (TRACE) printf("\$rec_desc: 0x%X, \$search_subhash: %d, \$index: %d\n", $rec_desc, $search_subhash, $index);	

//if (TRACE) printf("\$is_first_round: %d, \$field_qt: %d\n", $is_first_round, $field_qt);	
			if ($is_first_round && !$field_qt) {

				$list_entry_pos = ftell($fh);

//if (TRACE) printf("\$list_entry_pos: 0x%X, \$search_subhash: %d, \$index: %d\n", $list_entry_pos, $search_subhash, $index);	

				if (!$search_subhash) {
//if (TRACE) printf("BINGO: \$list_entry_pos: 0x%X\n", $list_entry_pos);	
					
					$pos = $list_entry_pos;
//if (TRACE) printf("\$start_from: 0x%X\n", $start_from);
					$level = 0;
					$max = 0xFF;
					$mask = 0xFF;

					$stack = array();
					//$stack[] = array('pos' => $pos + 4, 'level' => $level);
					//$next_pos;
					//$go_up = 1;//$go_up = $start_from > $max;

//if (TRACE) printf("\$pos: 0x%X, \$start_from: %d, \$level: %d; \$max: 0x%X, \$mask: 0x%X, \$next_pos: 0x%X, \$go_up: %d\n", $pos, $start_from, $level, $max,$mask, $next_pos, $go_up);	

if (DEBUG) print('<pre>');
 		
					if ($desc) {	
						while ($pos) {
							
							if ($start_from > $max) {
								$stack[] = array('pos' => $pos + 4, 'level' => $level);
								$level++;
								$mask <<= 8;
								$max = ($max << 8) | 0xFF;
							} else {
								$start = ($start_from & $mask) >> (8 * $level);
								if (count($stack)) 
									$top =& $stack[count($stack) - 1]; 
								$is_topper = !count($stack) || $level > $top['level'];
								$stack[] = array(
									'pos' => $pos + ($is_topper ? 4 : 0), 
									'level' => $level, 
									'start' => $start, 
									'index_base' => $is_topper ? 0 : ($top['index_base'] + $top['start']) << 8
								); 
								if (!$level) break;
								$pos += $start * 4;
								$mask >>= 8;
								$level--;
							}

							$pos = read_int(4, $fh, SEEK_SET, $pos);
						}


//if (DEBUG) print_r($stack);			
						while (count($stack)) {
							$item = array_pop($stack);
							$delta = count($stack) && $stack[count($stack) - 1]['level'] > $item['level'] ? 1 : 0;
							$level =& $item['level'];
							if (!array_key_exists('start', $item)) 
								$item['start'] = 255;
							elseif ($level) 
								$item['start']--;
//if (DEBUG) printf("\$item['start']: " . $item['start'] . ", \$delta: $delta, \$level: $level; \$item['start'] >= (\$delta ? 0 : 1): %d\n", $item['start'] >= ($delta ? 0 : 1));								
							if ($item['start'] < ($delta ? 0 : 1)) continue;
							
							if (!array_key_exists('ptrs', $item)) {
								$qt = $item['start'] + $delta;
								$item['ptrs'] = unpack("N$qt", read_buf($qt * 4, $fh, SEEK_SET, $item['pos']));
//if (DEBUG) if ($level) print_r($item);									
							}
							$ptrs =& $item['ptrs'];
							$index_base =& $item['index_base'];
//if (DEBUG) printf("\$item['start'] + \$delta: %d, \$index_base: %d\n", $item['start'] + $delta, $index_base);								
							for ($i = $item['start'] + $delta; $i >= 1; $i--) {
//if (DEBUG) if ($level) printf("\$ptrs[$i]: 0x%X\n", $ptrs[$i]);
								if (!$ptrs[$i]) continue;
								if ($level) {
									$item['start'] = $i - $delta;
//if (DEBUG) if ($level) printf("\$item['start']: %d\n", $item['start']);
									$stack[] = $item;
									$stack[] = array(
										'pos' => $ptrs[$i], 
										'level' => $item['level'] - 1, 
										'index_base' => ($index_base + $item['start']) << 8
									);
									break;
								} else {
									$rec->entry_ptr = $ptrs[$i];
									if ($callback($index_base + $i - $delta, $rec) === FALSE) return TRUE;
								}
							}
//if (DEBUG) if (!$level) printf('%d - %d' . "\n", $index_base + count($ptrs) - $delta, $index_base + 1 - $delta);									
						}
					} else {
						while ($pos) {
							
							if ($start_from > $max) {
								//$stack[] = array('pos' => $pos + 4, 'level' => $level);
								$level++;
								$mask <<= 8;
								$max = ($max << 8) | 0xFF;
								$pos = read_int(4, $fh, SEEK_SET, $pos);
							} else {
								$start = ($start_from & $mask) >> (8 * $level);
								if (count($stack)) $top =& $stack[count($stack) - 1]; 
								//$is_topper = !count($stack) || $level > $top['level'];
								$stack[] = array(
									//'pos' => $pos + ($is_topper ? 4 : 0), 
									'ptrs' => unpack('N256', read_buf(1024, $fh, SEEK_SET, $pos)),
									'level' => $level, 
									'start' => $start, 
									'index_base' => !count($stack) ? 0 : ($top['index_base'] + $top['start']) << 8
								); 
								if (!$level) break;
								$pos = $stack[count($stack) - 1]['ptrs'][$start + 1]; //$pos += $start * 4;
								$mask >>= 8;
								$level--;
							}

						}
						if ($start_from > $max) return TRUE;
						
//if (DEBUG) print_r($stack);			
						while (count($stack)) {
							$item = array_pop($stack);
							//$delta = count($stack) && $stack[count($stack) - 1]['level'] > $item['level'] ? 1 : 0;
							$level =& $item['level'];
							if (!array_key_exists('start', $item)) 
								$item['start'] = 0;
							elseif ($level) 
								$item['start']++;
//if (DEBUG) printf("\$item['start']: " . $item['start'] . ", \$level: $level; \$item['start'] >= 0: %d\n", $item['start'] >= 0));								
							if ($item['start'] > 255) continue;
							
							/*
							if (!array_key_exists('ptrs', $item)) {
								$qt = $item['start'] + $delta;
								$item['ptrs'] = unpack("N$qt", read_buf($qt * 4, $fh, SEEK_SET, $item['pos']));
if (DEBUG) if ($level) print_r($item);									
							}
							*/
							$ptrs =& $item['ptrs'];
							$index_base =& $item['index_base'];
//if (DEBUG) printf("\$item['start']: %d, \$index_base: %d\n", $item['start'], $index_base);								
							for ($i = $item['start'] + 1; $i <= 256; $i++) {
//if (DEBUG) if ($level) printf("\$ptrs[$i]: 0x%X\n", $ptrs[$i]);
								if (!$ptrs[$i]) continue;
								if ($level) {
									$item['start'] = $i - 1;
//if (DEBUG) printf("\$item['start']: %d\n", $item['start']);
									$stack[] = $item;
									$stack[] = array(
										'ptrs' => unpack('N256', read_buf(1024, $fh, SEEK_SET, $ptrs[$i])),
										'level' => $item['level'] - 1, 
										'index_base' => ($index_base + $item['start']) << 8
									);
//if (DEBUG) print_r($stack);			
									break;
								} else {
									$rec->entry_ptr = $ptrs[$i];
									if ($callback($index_base + $i - 1, $rec) === FALSE) return TRUE;
								}
							}
//if (DEBUG) if (!$level) printf('%d - %d' . "\n", $index_base + $item['start'], $index_base + count($ptrs) - 1);									
//if (DEBUG) if (!count($stack)) print_r($item);
							if (count($stack)) continue;
							if (!$ptrs[1]) break;
							$stack[] = array(
								'ptrs' => unpack('N256', read_buf(1024, $fh, SEEK_SET, $ptrs[1])),
								'level' => $item['level'] + 1, 
								'index_base' => 0,
								'start' => 0
							);
//if (DEBUG) print_r($stack);			
							
						}
					}
					
					return TRUE;
					
				} elseif ($index > 0) {

					$pos = $list_entry_pos;
					$i = $index;

					$level = 0;
					$max = 0xFF;
					$mask = 0xFF;

					$next_pos;
					$go_up = $i > $max;

if (TRACE) printf("\$pos: 0x%X, \$i: %d, \$level: %d; \$max: 0x%X, \$mask: 0x%X, \$next_pos: 0x%X, \$go_up: %d\n", $pos, $i, $level, $max,$mask, $next_pos, $go_up);	

					while ($go_up && $i > $max || !$go_up && $mask != 0xFF) {
						
						if ($go_up) {
							$level++;
							$mask <<= 8;
							$max = ($max << 8) | 0xFF;
						} else {
							$pos += (($i & $mask) >> (8 * $level)) * 4;
							$mask >>= 8;
							$level--;
						}

						$next_pos = read_int(4, $fh, SEEK_SET, $pos);
if (TRACE) printf("\$pos: 0x%X, \$i: %d, \$level: %d; \$max: 0x%X, \$mask: 0x%X, \$next_pos: 0x%X, \$go_up: %d\n", $pos, $i, $level, $max,$mask, $next_pos, $go_up);	
						if (!$next_pos) return FALSE;

						$pos = $next_pos;
						$go_up = $go_up && $i > $max;
					}

					$list_item_addr = $pos + ($i & 0xFF) * 4;

if (TRACE) printf("\$list_item_addr: 0x%X\n", $list_item_addr);	
					$ptr = read_int(4, $fh, SEEK_SET, $list_item_addr);
					break;

				}  else {

					$is_first_round = 0;
					$prev_rec_ptr = read_int(4, $fh, SEEK_SET, $list_entry_pos + 1024);
				}
				
if (TRACE) printf("\$prev_rec_ptr: 0x%X\n", $prev_rec_ptr);	

			} elseif (!$is_first_round && !$field_qt) {
			
				$ref = !$ref_size ? 0 : read_int($ref_size, $fh, SEEK_CUR, 1024 + 4);
				$prev_rec_ptr = !$ref ? 0 : $ptr - $ref;
if (TRACE) printf("\$prev_rec_ptr: 0x%X\n", $prev_rec_ptr);	

			} else {
			
				if ($is_first_round) {
					if (!$search_subhash) return FALSE;
					$is_first_round = 0;
				} 
				
				if (!$search_subhash) throw new Exception('ASSERTION');
				
				if ($field_qt == 31) // 0b11111 
					$field_qt = read_int(1, $fh, SEEK_CUR, 0) + 1;

				$field_defs_len = $field_qt * 2;
				$buf = read_buf($field_defs_len + $ref_size, $fh, SEEK_CUR, 0);
				$field_defs = unpack("C$field_defs_len", $buf);
							
				$skip_len = 0;
				$ptr = NULL;
				for ($i = 1; $i <= $field_defs_len; $i += 2) {
					$def =& $field_defs[$i + 1];
					
					if ($index != -$field_defs[$i]) {
						$skip_len += ($def & SSS_READ_MASK) >> SSS_SHIFT;
					} else {
						if ($def != HASH_TYPE) return FALSE;
						$ptr = read_int(4, $fh, SEEK_CUR, $skip_len);
						break;
					}
				}
				if (!is_null($ptr)) break;
				
				$ref = !$ref_size ? 0 : unpack_int($ref_size, substr($buf, -$ref_size, $ref_size));
				$prev_rec_ptr = !$ref ? 0 : $ptr - $ref;
			} 
		}
	}
	return FALSE;
}

function is_valid_index($index) {
	return 
		($index == (string)((int) $index))
		&& ($index >= -255)
		&& ($index <= 0x80000000);
}

function validate_index($index) {
	if (!is_valid_index($index)) 
		throw new Exception('bad index: ' . var_export($index, TRUE));
}

?>
