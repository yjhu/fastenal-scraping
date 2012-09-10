<?php
require_once 'fastenal_db.php';
require_once 'fastenal_form.php';
require_once 'fastenal_page.php';
require_once 'LIBs\LIB_http.php';
require_once 'LIBs\LIB_parse.php';
require_once 'simple_html_dom.php';

define('WORKER_DEBUG', true);

function update_products_fullinfo($dbh, $products_tblname, $sku, $title, $weight, $size, $price, $inventory, $attr_keys, $attr_vals) {
	$sql = "SELECT * FROM $products_tblname WHERE sku like '$sku'";
	$pdo_stmt = $dbh->query($sql);
	foreach(range(0, $pdo_stmt->columnCount() - 1) as $column_index) {
		$meta[] = $pdo_stmt->getColumnMeta($column_index);
	}
	$vstr = '';
	for($i = 0; $i < count($attr_keys); $i++) {
		foreach ($meta as $m) {
			if (strcmp($m['name'], $attr_keys[$i]) == 0) {
				$tmp1 = $m['name'];
				$tmp2 = $attr_vals[$i];
				$tmp2 = str_replace('"', '\"', $tmp2);
				$tmp2 = str_replace("'", "\'", $tmp2);
				$tmp2 = "'".$tmp2."'";
				$tmp3 = "$tmp1=$tmp2, ";
				$vstr = $vstr.''.$tmp3;
			}
		}
	}
	if ($inventory >= 0)
		$sql = "UPDATE $products_tblname SET $vstr scrapped_flag=true,weight='$weight', size='$size',wholesale_price='$price',inventory='$inventory' where sku like '$sku'";
	else 
		$sql = "UPDATE $products_tblname SET $vstr scrapped_flag=false,weight='$weight', size='$size',wholesale_price='$price',inventory='$inventory' where sku like '$sku'";
	if (false !== $dbh->query($sql)) {
		save_prices($dbh, $sku, $price);
		if ($inventory >= 0)
			save_inventories($dbh, $sku, $inventory);
	} else {
		echo "Error: $sql\r\n";
		exit;
	}
// 	$sql = "UPDATE $products_tblname SET $vstr weight=:weight, ".
// 		"size=:size, scrapped_flag=true, wholesale_price=:wholesale_price, inventory=:inventory ".
// 		"WHERE sku like ':sku'";
// 	$stmt = $dbh->prepare($sql);
// 	$stmt->bindParam(':weight', $weight);
// 	$stmt->bindParam(':size', $size);
// 	$stmt->bindParam(':wholesale_price', $price);
// 	$stmt->bindParam(':inventory', $inventory);
// 	$stmt->bindParam(':sku', $sku);
// 	if ($stmt->execute()) {
// 		save_prices($dbh, $sku, $price);
// 		save_inventories($dbh, $sku, $inventory);
// 	}
}

global $cookiefilename;
global $proxy;
global $proxy_type;
global $proxy_userpwd;

ignore_user_abort(true);
set_time_limit(0);

$worker_id = $argv[1];
$cookiefilename = $argv[2];
$proxy = $argv[3];
$proxy_userpwd = $argv[4];
$proxy_type = $argv[5];
$category_id = $argv[6];
$sku = $argv[7];

$sem4 = sem_get('products_pagescrapping_lock');

try {
	$dbh = fastenal_connectDB();
	$sql = 'SELECT * FROM categories WHERE id = '.$category_id;
	$rows = $dbh->query($sql);
	if ($rows === false || $rows->rowCount() == 0) exit;
	$row = $rows->fetch();
	if ($row === false) exit;

	echo date('c').": Worker #".$worker_id." is running with ".$cookiefilename." on ".$row['title'].'#sku'.$sku.".\r\n";

	$title = $row['title'];
	$url = $row['url'];
	$ref_url = $row['ref_url'];
	$page_url = $row['page_url'];
	$products_num = $row['products_num'];
	$tblname_base = $row['products_tbl_name'];
	$multiple_pages_flag = $row['valid_flag'];

	$products_tblname = 'products_'.$tblname_base;
	$pages_tblname = 'pages_'.$tblname_base;

	if (!isset($products_tblname) || strlen($products_tblname) == 0 || $tblname_base != md5($title))
		exit;

	$target="http://www.fastenal.com/web/products/detail.ex?sku=".$sku;
	$response = http_get($target, $url);
	if (strlen($response['ERROR']) != 0) {
		echo "Error: ".$response['ERROR'].".\n";
		echo "When http_get($target, $url).\n";
		exit;
	} else {
		echo "done with downloading $target.\r\n";
	}
	$html = str_get_html(tidy_html($response['FILE']));
//	file_put_contents('detailscrapping_worker-dump.txt', $html);
	
	$attr_keys = get_attrkeys_from_detail_page($html);
	$attr_vals = get_attrvals_from_detail_page($html);
	$price = get_price_from_detail_page($html);
	$inventory = get_inventory_from_detail_page($html);
	if ($inventory < 0)
		file_put_contents('detailscrapping_worker-dump.txt', tidy_html($response['FILE']));
	$weight = 'null';
	$size = 'null';
	for ($i = 0; $i < count($attr_keys); $i++) {
		if (stristr('attr_product_weight', $attr_keys[$i])) {
			preg_match('/[\d\.]+/', $attr_vals[$i], $matches);
			$weight = $matches[0];
		}
		if (stristr('_size', $attr_keys[$i]))
			$size = $attr_vals[$i];
	}
	update_products_fullinfo($dbh, $products_tblname, $sku, $title, $weight, $size, $price, $inventory, $attr_keys, $attr_vals);
} catch (Exception $e) {
	die("Error: " . $e->getMessage());
}