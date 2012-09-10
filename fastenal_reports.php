<?php
require_once 'fastenal_db.php';

function category2filename($category) {
	$titles = explode('>', $category);
	$data_filename = array_pop($titles);
	$data_filename = str_replace('&', 'and', $data_filename);
	$data_filename = str_replace(',', ' ', $data_filename);
	$data_filename = preg_replace('/\s+/', '-', $data_filename);
	$data_filename = preg_replace('/[\/\\\]+/', '-', $data_filename);
	$data_filename = strtolower($data_filename);
	return trim($data_filename);
}

function getScrappedTimestr($dbh, $sku) {
	$sql = "SELECT UNIX_TIMESTAMP(scrapped_ts) FROM inventories WHERE sku LIKE '$sku' ORDER BY scrapped_ts DESC LIMIT 1";
	$rows = $dbh->query($sql);
	if ($rows === false || $rows->rowCount() != 1) {
		echo "Error: when accessing inventories for sku#$sku.\r\n";
		exit;
	}
	$row = $rows->fetch();
	return date('Y-m-d H:i:s T I', (int)$row[0]);
}

function escape_comma($inputstr) {
	$inputstr = str_replace("/'", "'", $inputstr);
	$inputstr = str_replace('/"', '"', $inputstr);
	return str_replace(',',' ', $inputstr);
}

function getSizeCollumnIdx($rows) {
	foreach(range(0, $rows->columnCount() - 1) as $column_index) {
		$metas[] = $rows->getColumnMeta($column_index);
	}
	for ($i = 0; $i < count($metas); $i++) {
		if (stristr($metas[$i]['name'], 'attr_') && stristr($metas[$i]['name'], 'size')) 
			return $i;
	}
	return false;	
}

$main_categories = array (
// 		'Products>Fasteners',
// 		'Products>Power Transmission & Motors',
// 		'Products>Safety',
// 		'Products>Tools & Equipment',
// 		'Products>Cutting Tools & Metalworking',
// 		'Products>Material Handling, Storage, & Packaging',
		'Products>Electrical',
		'Products>Plumbing'
// 		'Products>Hydraulics & Pneumatics',
// 		'Products>Abrasives',
// 		'Products>Fleet & Automotive',
// 		'Products>Raw Materials',
// 		'Products>Lifting and Rigging',
// 		'Products>Welding',
// 		'Products>Chemicals & Paints',
// 		'Products>Janitorial',
// 		'Products>Office Products & Furniture',
// 		'Products>HVAC',
// 		'Products>Mil-Spec'
);

date_default_timezone_set('America/Los_Angeles');

$dirname = date('Y-m-d');
$dirname = 'data\\'.$dirname;
if (false == is_dir($dirname)) {
	mkdir($dirname);
}

try {
	$dbh = fastenal_connectDB();
	foreach($main_categories as $category) {
		$data_filename = $dirname.'\\'.category2filename($category).'_'.date('Y-m-d').'.csv';
		echo "Saving $category to $data_filename\r\n";
		
		$sql = "SELECT * FROM categories WHERE title like '%$category%' and is_leaf=true and scrapped_flag=true";
		$rows = $dbh->query($sql);
		if (false === $rows || $rows->rowCount() == 0) {
			echo "Error[SQL]: $sql\r\n";
			exit;
		}
		
		$fp = fopen($data_filename, 'w+');
		$header = 'Scrapping Time, Category Path, Fastenal SKU Number,Description,Price($),Size,Product Weight(lb),Inventory';
		fwrite($fp, $header."\r\n");
		
		foreach ($rows as $row) {
			$products_tblname = 'products_'.$row['products_tbl_name'];
			$sql = "SELECT * FROM $products_tblname WHERE scrapped_flag=true";
			$products_rows = $dbh->query($sql);
			if (false === $products_rows) {
				echo "Error[SQL]: $sql\r\n";
				exit;
			}
			echo "Saving ".$row['title']." [".$products_rows->rowCount()."]\r\n";
			$size_idx = getSizeCollumnIdx($products_rows);
			foreach ($products_rows as $products_row) {
				$line = getScrappedTimestr($dbh, $products_row['sku']).','.escape_comma($row['title']).',';
				$line .="'".$products_row['sku']."',".escape_comma($products_row['title']).','.escape_comma($products_row['wholesale_price']).',';
				if (false === $size_idx)
					$line .= escape_comma($products_row['size']).',';
				else 
					$line .= escape_comma($products_row[$size_idx]).',';
				$line .= escape_comma($products_row['weight']).','.escape_comma($products_row['inventory']);
				fwrite($fp, $line."\r\n");
			}
		}
			
		fclose($fp);
	}
} catch (Exception $e) {
	die("Error: " . $e->getMessage());
}