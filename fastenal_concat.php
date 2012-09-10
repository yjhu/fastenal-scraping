
<?php
	if (count($argv) != 3) {
		echo "Usage: $argv[0] start_page end_page.\n";
		exit;
	}
	$start_page = $argv[1];
	$end_page = $argv[2];
	$err_flag = false;
	for ($page = $start_page; $page <= $end_page; $page++) {
		$filename = "fastenal-handtools-page$page.csv";
		if (file_exists($filename) && count(file($filename)) == 10) 
			;
		else {
			echo "Error: page $page, CHECK IT!\n";
			$err_flag = true;
		}	
	}
	if ($err_flag) exit;
	$output_fn = "fastenal-handtools-page".$start_page."to".$end_page.".csv";
	if (file_exists($output_fn)) 
		unlink($output_fn);
	$fp = fopen($output_fn, "w");
	fwrite($fp, "Fastenal Part No. (SKU),Item description,Category Path,Wholesale Price(US\$),Size(inch),Product Weight(lbs),Inventory,Date & Time\r\n");
	for ($page = $start_page; $page <= $end_page; $page++) {
		$page_fn = "fastenal-handtools-page$page.csv";
		$file_content = file_get_contents($page_fn);
		fwrite($fp, $file_content);
	}	
	fclose($fp);
?>
