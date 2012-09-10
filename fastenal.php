<?php
	global $datafilename;
	global $cookiefilename;

	include("LIBs/LIB_parse.php");
	include("LIBs/LIB_http.php");
	include("fastenal_form.php");
	
	function fastenal_escapeDataFile($data_filename) {
		//	$filearray = file("fastenal-handtools-20120728.csv");
		$filearray = file($data_filename);
		for ($i = 0; $i < count($filearray); $i++) {
			//		echo "[$i]".$filearray[$i];
			$matchnum = preg_match('/(\d){4}-(\d){2}-(\d){2}T(\d){2}:(\d){2}:(\d){2}[+-](\d){2}:(\d){2}/', $filearray[$i]);
			if ($matchnum == 0 && $matchnum == false) {
				//			echo "[$i]".$filearray[$i];
				for ($j = 1; ;$j++) {
					$matchnum = preg_match('/(\d){4}-(\d){2}-(\d){2}T(\d){2}:(\d){2}:(\d){2}[+-](\d){2}:(\d){2}/', $filearray[$i + $j]);
					if ($matchnum > 0)
						break;
				}
				$tmp = implode(array_slice($filearray, $i, $j+1));
				$tmp = preg_replace('/[\n|\r\n]/', ' ', $tmp);
				$tmp = preg_replace('/[ ]+/', ' ', $tmp);
				$tmp = trim($tmp);
				$tmp = $tmp."\r\n";
				//			echo ">>>>".$tmp."\r\n";
				array_splice($filearray, $i, $j+1, $tmp);
			}
		}
		file_put_contents($data_filename, $filearray);
	}
	
	function fastenal_getCategoryPath($detail_page) {
		$divs = parse_array($detail_page, '<div ID="breadcrumbs">', '</div>');
		$category_path = urldecode(strip_tags($divs[0]));
		$category_path = str_replace("&gt;", ">", $category_path);
		$category_path = str_replace("&amp;", "&", $category_path);
		$category_path = preg_replace('/[\n|\r\n]/', ' ', $category_path);
		$category_path = preg_replace('/,/', ' ', $category_path);
		$category_path = preg_replace('/[ ]+/', ' ', $category_path);
//		$category_path = "\"".$category_path."\"";
		return $category_path;
	}
	
	function fastenal_getHandtoolsItemDetailInfo(&$handtool) {
		$sku= $handtool['SKU'];
		$target="http://www.fastenal.com/web/products/detail.ex?sku=".$sku;
		$return_array = http_get($target, "");
		$page = tidy_html($return_array['FILE']);
//		file_put_contents("test.txt", $page);
		$handtool["CategoryPath"] = fastenal_getCategoryPath($page);
		$tables = parse_array($page, "<table", "</table>");
		for ($i = 0; $i < count($tables); $i++) {
			if (get_attribute($tables[$i], "class") == 'details') {
				$trs = parse_array($tables[$i], "<tr", "</tr>");
				$handtool['WEIGHT'] = "";
				$handtool['SIZE'] = "";
				for ($j = 0; $j < count($trs); $j++) {
					if (stristr($trs[$j], 'Product Weight')) {
						preg_match('/[\d\.]+/', strip_tags($trs[$j]), $matches);
						$weight = $matches[0];
						//	                    echo $weight."\r\n";
						$handtool['WEIGHT'] = $weight;
					}
					if (stristr($trs[$j], 'Key Size:')) {
						$tds = parse_array($trs[$j], "<td", "</td>");
						$size = trim(strip_tags($tds[0]));
						//	                    echo $size."\r\n";
						$handtool['SIZE'] = str_replace(",", " ", $size);
					}
				}
	
			}
		}
// 		file_put_contents("error-dump.txt", $page);
		$forms = parse_array($page, "<form", "</form>");
//		var_dump($forms);
		for ($i = 0; $i < count($forms); $i++) {
			$formname = get_attribute($forms[$i], "name");
			if ($formname == "ProductAddForm") {
// 				echo "found form 'ProductAddForm'===>\n";
// 				echo $forms[$i]."\n";
				$inputs = parse_array($forms[$i],"<input", ">");
				for ($j = 0; $j < count($inputs); $j++) {
					if (stristr($inputs[$j], 'productDetailId')) {
						$handtool['productDetailId'] = get_attribute($inputs[$j], "value");
					}
					if (stristr($inputs[$j], 'productId')) {
						$handtool['productId'] = get_attribute($inputs[$j], "value");
					}
					if (stristr($inputs[$j], 'addCartQty')){
						$handtool['addCartQty'] = get_attribute($inputs[$j], "name");
					}
				}
				break;
			}
		}
	}
	
	function fastenal_getHandtoolsPages($pagination_table) {
		$trs = parse_array($pagination_table, "<tr", "/tr>");
		$tds = parse_array($trs[0], "<td", "</td>");
		preg_match_all('/\d+/', trim(strip_tags($tds[0])), $matchs);
		$links = parse_array($tds[1], "<a", "</a>");
//		echo $links[0];
		$handtools_pages['PAGELINK'] = return_between($links[0], "HREF='", "'", EXCL);	
		$handtools_pages['CURRPAGE'] = $matchs[0][0];
		$handtools_pages['MAXPAGES'] = $matchs[0][1];
		return $handtools_pages;
	}
	
	function fastenal_getHandtoolSkuTitle($td_description) {
	    if (stristr($td_description, 'onlineUnavailable') || stristr($td_description, 'onlineDelay'))
	        $onlineAvailable = false;
	    else 
	        $onlineAvailable = true;
	    $divs = parse_array($td_description, "<div", "</div>");
	    $title = trim(strip_tags($divs[0]));
	    preg_match('/Sku: [A-Z\d\-]+/',$td_description, $matches);
	    $sku = str_replace("Sku: ", "", $matches[0]);
//		$sku = $matches[0];
	    return array('SKU'=>$sku, "TITLE"=>$title, "ONLINEAVAILABLE"=>$onlineAvailable);
	}
	
	function fastenal_getHandtoolPrice($td_price) {
		    preg_match('/\$[\d.]+/',trim(strip_tags($td_price)), $matches);
//		    var_dump($matches[0]);
		    return str_replace("$", "", $matches[0]);
	}
	
	function fastenal_getHandtoolsItems($results_table, &$handtools, &$handtools_num) {	    	    
	    $tbody = return_between($results_table, "<tbody>", "</tbody>", EXCL);
	    $trs = parse_array($tbody, "<tr", "</tr>");
	    for ($i = 0; $i < count($trs); $i++) {
	       $flag = false;
	       $tds = parse_array($trs[$i], "<td", "</td>");
	       for ($j = 0; $j < count($tds); $j++) {
	           if (get_attribute($tds[$j], "class") == "price") {
	               $price = fastenal_getHandtoolPrice($tds[$j]);
	               $flag = true;
	           }
	           else if (get_attribute($tds[$j], "class") == "description") {
	               $handtool = fastenal_getHandtoolSkuTitle($tds[$j]);
	               $flag = true;  
	           }           
	       } 
	       if ($flag) {
	 	       $handtools[$handtools_num]= array("PRICE" =>$price, "SKU"=>$handtool['SKU'], "TITLE"=>str_replace(","," ",$handtool['TITLE']),
	 	               "ONLINEAVAILABLE" =>$handtool['ONLINEAVAILABLE']);	 	       
	 	       $handtools_num += 1;
	 	       
	       }
	    }
	}

	function fastenal_getHandtoolsResultsTable($page_no) {
	   $page_link = 'http://www.fastenal.com/web/search/product/_/Navigation?searchterm=&sortby=webrank&sortdir=descending&searchmode=&pageno=2&refine=~%7Ccategoryl1:%22600241%20Tools%209and%20Equipment%22%7C~%20~%7Ccategoryl2:%22600268%20Hand%20Tools%22%7C~';
	   $target = preg_replace('/pageno=\d+/', "pageno=".$page_no, $page_link);
	   $return_array = http_get($target, $page_link);
	   if (strlen($return_array['ERROR']) != 0) {
		echo "ERROR: fetching ".$target."\n";
	   	echo $return_array['ERROR']."\n";
	   	exit;
	   }
	   
	   
/******	   
	   $tables = parse_array(tidy_html($return_array['FILE']), "<table", "</table>");
//	   file_put_contents("page".$page_no.".htm", tidy_html($return_array['FILE']));
	   for ($i = 0; $i < count($tables); $i++) {
	       $table_head = return_between($tables[$i], "<table", ">", INCL);
	       if (stristr($table_head, "id"))
	           $table_id = get_attribute($table_head, "id");
	       else if (stristr($table_head, "class"))
	           $table_id = get_attribute($table_head, "class");
	       else
	           $table_id = "";
	       if ($table_id == "searchResults")
	           return $tables[$i];
	   }
********/
	   $file = tidy_html($return_array['FILE']);
	   $results_tbl_start_pos = 0;
	   $results_tbl_start_pos = stripos($file, '<table id="searchResults"');
	   if ($results_tbl_start_pos === false) {
	   		return false;
	   	//	echo "searchResults not found!\n";
	   } else {
	   	//	echo "searchResults found at $results_tbl_start_pos!\n";
	   	do {
	   		$begin_pos = $results_tbl_start_pos + 1;
	   		$table_start = stripos($file, '<table', $begin_pos);
	   		$table_end = stripos($file, '</table>', $begin_pos);
	   		if ($table_start === false || $table_start > $table_end) {
	   			break;
	   		} else {
	   			//			echo "$table_start, $table_end, nested table found.\n";
	   			$file = substr_replace($file, '', $table_start, $table_end - $table_start + strlen('</table>'));
	   		}
	   	} while (true);
	   	return substr($file, $results_tbl_start_pos, $table_end - $results_tbl_start_pos + strlen('</table>'));
	   }
	}
	
	function fastenal_processHandtools($handtools, $page_no, $file_head = false) {
		global $datafilename;
		for ($i = 0; $i < count($handtools); $i++) {
			$start_time = microtime(true);
			echo "PAGE#".$page_no."/#".($i+1)."> Fastenal.com: downloading details info of SKU:".$handtools[$i]['SKU']." ......";
			fastenal_getHandtoolsItemDetailInfo($handtools[$i]);
			if ($handtools[$i]['ONLINEAVAILABLE'])
				$handtools[$i]['INVENTORY'] = fastenal_getOnlineInventory($handtools[$i]);
			else
				$handtools[$i]['INVENTORY'] = "n/a";
			$handtools[$i]['LocalTime'] = date('c');
			$end_time = microtime(true);
			$time = $end_time - $start_time;
			echo "done.($time seconds)\n";
		}
		
		$filename = "fastenal-handtools-page$page_no.csv";
		if (file_exists($filename))
			unlink($filename);		
		$fp = fopen($filename, 'a+');
		if ($file_head) fwrite($fp, "Fastenal Part No. (SKU),Item description,Category Path,Wholesale Price,Size(inch),Product Weight(lbs),Inventory,Date & Time\r\n");
		for ($i = 0; $i < count($handtools); $i++) {
			$outstring = '"'.$handtools[$i]['SKU'].'"'.",".$handtools[$i]['TITLE'].",".$handtools[$i]['CategoryPath'].",".$handtools[$i]['PRICE'].",".$handtools[$i]['SIZE'].",".$handtools[$i]['WEIGHT'].",".$handtools[$i]['INVENTORY'].",".$handtools[$i]['LocalTime']."\r\n";
			//	    echo $outstring;
			fwrite($fp, $outstring);
		}
		fclose($fp);		
//		fastenal_escapeDataFile($datafilenam);
	}
/**	
	$page1_start_time = microtime(true);
	echo "Fastenal.com: downloading page #1 of hand tools ......BEGIN\n";
	
	$handtools_num = 0;
	$handtools = array();
	$target = "http://www.fastenal.com/web/search/product/_/Navigation&refine=~%7Ccategoryl1:%22600241%20Tools%209and%20Equipment%22%7C~%20~%7Ccategoryl2:%22600268%20Hand%20Tools%22%7C~";
	$return_array = http_get($target, $ref="http://www.fastenal.com/web/home.ex");
	if (strlen($return_array['ERROR']) != 0) {
		echo "ERROR: fatching ".$target."\n";
	   	echo $return_array['ERROR']."\n";
	   	exit;
	}
	$handtools_homepage = tidy_html($return_array['FILE']);
//	file_put_contents("page1.htm", $handtools_homepage);
	
	$tables = parse_array($handtools_homepage, "<table", "</table>");
	for ($i = 0; $i < count($tables); $i++) {
	   $table_head = return_between($tables[$i], "<table", ">", INCL);
	   if (stristr($table_head, "id"))
	   		$table_id = get_attribute($table_head, "id");
	   else if (stristr($table_head, "class"))
	        $table_id = get_attribute($table_head, "class");
	   else
	        $table_id = "";
	   if (!isset($pagination_table) && $table_id == "pagination")
	       $pagination_table = $tables[$i];
	   if (!isset($results_table) && $table_id == "searchResults")
	       $results_table = $tables[$i];
	}
	
	if (!isset($pagination_table)) echo "couldn't find pagination table.\n";
	if (!isset($results_table)) echo "couldn't find results table.\n";
	if (!isset($pagination_table) || !isset($results_table))
		exit;
	
	$handtools_pages = fastenal_getHandtoolsPages($pagination_table);
	fastenal_getHandtoolsItems($results_table, $handtools, $handtools_num);
	fastenal_processHandtools($handtools, 1, true);
	$handtools_num = 0;
	$handtools = array();
	$page1_end_time = microtime(true);
	$page1_time = $page1_end_time - $page1_start_time;
	echo "=====================(END: $page1_time seconds.)==================================\n";
	**/	
	if (count($argv) != 5) {
		echo "Usage: php fastenal.php start_page pages maxpages cookie.txt.\n";
		exit;
	} else {
		$start_page = $argv[1];
		$end_page = $start_page + $argv[2];
		$max_pages = $argv[3];
		$cookiefile = $argv[4];
		if ($start_page > $max_pages) {
			echo "Error: Start_page $start_page (> $max_pages) impossible!\n";
			exit;
		}
		if (($end_page - 1) > $max_pages)
			$end_page = $max_pages + 1;
	}

//	$datafilename = "fastenal-handtools-".date("Ymd")."-page".$start_page."to".($end_page - 1).".csv";
	date_default_timezone_set('America/Phoenix'); // US western timezone
	$datafilename = "fastenal-handtools.csv";
	$cookiefilename = $cookiefile;
	
	$start_time = microtime(true);
	for ($page = $start_page; $page < $end_page; $page++) {
		$handtools = array();
		$handtools_num = 0;
		$page_start_time = microtime(true);
	    echo "Fastenal.com: downloading page #".$page." of hand tools ......BEGIN\n";
	    fastenal_getHandtoolsItems(fastenal_getHandtoolsResultsTable($page), $handtools, $handtools_num);
	    fastenal_processHandtools($handtools, $page);
	    $page_end_time = microtime(true);
	    $page_time = $page_end_time - $page_start_time;
	    echo "=====================(END: PAGE $page -- $page_time seconds.)==================================\n";
	}
	$end_time = microtime(true);
	$total_time = $end_time - $start_time;
	echo "The script had been run for $total_time seconds to finish.\n"
?>
