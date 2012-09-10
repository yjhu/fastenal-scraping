<?php
require_once 'fastenal_db.php';
require_once 'fastenal_form.php';
require_once 'fastenal_page.php';
require_once 'LIBs\LIB_http.php';
require_once 'LIBs\LIB_parse.php';
require_once 'simple_html_dom.php';

define('WORKER_DEBUG', true);

function create_products_tbl($dbh, $tblname, $attributes) {
	if (WORKER_DEBUG)
		echo "Entering create_products_tbl()......\r\n";
	$attribute_fields = '';
	foreach ($attributes as $a) {
		$attribute_fields .= "$a char(100),";
	}
	$sql = "create table $tblname (".
			'sku char(50) not null primary key,'.
			'title char(255),'.
			'weight float,'.
			'size char(255), '.
			$attribute_fields.
			'scrapped_flag bool default false,'.
			'wholesale_price float,'.
			'inventory int, '.
			'scrapped_ts timestamp not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'.
			')';
	if (WORKER_DEBUG)
		echo "SQL: $sql\r\n";
	$results = $dbh->query($sql);
	if ($results === false) {
		echo "Error: when creating table $tblname".PHP_EOL;
//		exit;
	}
}

function create_pages_tbl($dbh, $tblname, $pages_num) {
	$sql = "create table $tblname (".
			'page_num int,'.
			'scrapped_flag bool,'.
			'scrapper_id int'.
			')';
	$dbh->query($sql);
	for ($i = 2; $i <= $pages_num; $i++) {
		$sql = "INSERT INTO $tblname VALUES ($i, false, -1)";
		$dbh->query($sql);
	}
}

function save_tblname_to_category($dbh, $category_id, $tblname_base, $valid_flag) {
	$stmt = $dbh->prepare("UPDATE categories SET valid_flag=:valid_flag, products_tbl_name=:products_tbl_name WHERE id=:id");
	$stmt->bindParam(':valid_flag', $valid_flag);
	$stmt->bindParam(':products_tbl_name', $tblname_base);
	$stmt->bindParam(':id', $category_id);
	$stmt->execute();
}

function scrapping_first_page($dbh, $products_tblname, $results_html_tbl) {
	$td_skus = $results_html_tbl->find('td.sku');
	$td_descs = $results_html_tbl->find('td.description');
	$skus = array();
	$titles = array();
	foreach ($td_skus as $sku) {
		preg_match('/sku: [A-Z\d\-]+/i',$sku->plaintext, $matches);
		$skus[] = str_ireplace("sku: ", "", $matches[0]);
	}
	// 	var_dump($skus);
	foreach ($td_descs as $desc) {
		$titles[] = preg_replace('/\s+/', ' ', $desc->first_child()->first_child()->plaintext);
	}
	// 	var_dump($titles);

	if (count($skus) != count($titles)) {
		echo "Error: $results_html_tbl->outertext.\r\n";
		exit;
	}

	for ($i = 0; $i < count($skus); $i++) {
		$stmt = $dbh->prepare("INSERT INTO $products_tblname (sku, title) VALUES (:sku, :title)");
		$stmt->bindParam(':sku', $skus[$i]);
		$stmt->bindParam(':title', $titles[$i]);
		$stmt->execute();
	}
}

function save_products_fullinfo($dbh, $products_tblname, $sku, $title, $weight, $size, $price, $inventory, $attr_vals) {
	$sql = "SELECT * from $products_tblname WHERE sku like '$sku'";
	if (WORKER_DEBUG)
		echo $sql.PHP_EOL;
	$rows = $dbh->query($sql);
	if ($rows === false || $rows->rowCount() == 0) {
		$vsql = '';
		for($i = 0; $i < count($attr_vals); $i++) {
			$vsql .="'".$attr_vals[$i]."', ";
		}
		$sql = "INSERT INTO $products_tblname VALUES ('$sku', '$title', $weight, $size, $vsql true, $price, $inventory)";
		if (WORKER_DEBUG)
			echo $sql.PHP_EOL;
		$dbh->query($sql);
	} else {
		$stmt = $dbh->prepare("UPDATE $products_tblname SET scrapped_flag = true, wholesale_price=:wholesale_price, inventory=:inventory WHERE sku like ':sku'");
		$stmt->bindParam(':wholesale_price', $price);
		$stmt->bindParam(':inventory', $inventory);
		$stmt->bindParam(':sku', $sku);
		$stmt->execute();		
	}
	
	save_prices($dbh, $sku, $price);
	save_inventories($dbh, $sku, $inventory);
}


ignore_user_abort(true);
set_time_limit(0);

$worker_id = $argv[1];
$cookie = $argv[2];
$category_id = $argv[3];
$sem4 = sem_get('products_tblcreating_lock');

global $cookiefilename;
$cookiefilename = $cookie;

try {
	$dbh = fastenal_connectDB();
	$sql = 'SELECT * FROM categories WHERE id = '.$category_id;
	$rows = $dbh->query($sql);
	if ($rows === false || $rows->rowCount() == 0) exit;
	$row = $rows->fetch();
	if ($row === false) exit;

	echo date('c').": Worker #".$worker_id." is running with ".$cookie." on ".$row['title'].".\r\n";

	$title = $row['title'];
	$url = $row['url'];
	$ref_url = $row['ref_url'];
	$page_url = $row['page_url'];
	$products_num = $row['products_num'];
	$products_tbl_name = $row['products_tbl_name'];

	if (isset($products_tbl_name) && strlen($products_tbl_name) > 0 && $products_tbl_name == md5($title))
		exit;

	$response = http_get($url, $ref_url);
	if (strlen($response['ERROR']) != 0) {
		echo "Error: ".$response['ERROR'].".\n";
		echo "When http_get($url, $ref_url).\n";
		exit;
	} else {
		echo "done with downloading $url.\r\n";
	}
	$html = str_get_html(tidy_html($response['FILE']));

	$tblname_base = md5($title);
	$products_tblname = 'products_'.$tblname_base;
	$pages_tblname = 'pages_'.$tblname_base;
	$valid_flag = false;

	if ($products_num == 0) {	// redirected to product detail page;
		$attr_keys = get_attrkeys_from_detail_page($html);
		$attr_vals = get_attrvals_from_detail_page($html);
		$sku = get_sku_from_detail_page($html);
		$title = get_title_from_detail_page($html);
		$price = get_price_from_detail_page($html);
		$inventory = get_inventory_from_detail_page($html);
		$weight = 'null';
		$size = 'null';
		for ($i = 0; $i < count($attr_keys); $i++) {
			if (stristr('attr_product_weight', $attr_keys[$i]))
				$weight = $attr_vals[$i];
			if (stristr('attr_key_size', $attr_keys[$i]))
				$size = $attr_vals[$i];
		}
		
		create_products_tbl($dbh, $products_tblname, $attr_keys);
		save_products_fullinfo($dbh, $products_tblname, $sku, $title, $weight, $size, $price, $inventory, $attr_vals);
		save_tblname_to_category($dbh, $category_id, $tblname_base, $valid_flag);
	} else {	// mutiple page list
		if ($products_num > 10) {
			create_pages_tbl($dbh, $pages_tblname, (int)ceil($products_num/10));
			$valid_flag = true;
		}
		$attributes = $html->find('div#product-dimensions h3.refinement-header');
		$attrs = array();
		foreach ($attributes as $a) {
			$attrs[] = productsattr2dbcollumn($a->plaintext);
		}		
		create_products_tbl($dbh, $products_tblname, $attrs);		
		save_tblname_to_category($dbh, $category_id, $tblname_base, $valid_flag);

		$tables = $html->find('table#searchResults');
		if (count($tables) == 0) exit;
		scrapping_first_page($dbh, $products_tblname, $tables[0]);
	}






} catch (Exception $e) {
	die("Error: " . $e->getMessage());
}
