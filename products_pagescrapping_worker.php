<?php
require_once 'fastenal_db.php';
require_once 'fastenal_form.php';
require_once 'LIBs\LIB_http.php';
require_once 'LIBs\LIB_parse.php';
require_once 'simple_html_dom.php';

define('WORKER_DEBUG', true);

if ( !function_exists('sem_get') ) {
	function sem_get($key) {
		return fopen(__FILE__.'.sem.'.$key, 'w+');
	}
	function sem_acquire($sem_id) {
		return flock($sem_id, LOCK_EX);
	}
	function sem_release($sem_id) {
		return flock($sem_id, LOCK_UN);
	}
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
		$sql = "SELECT * from $products_tblname WHERE sku like '$skus[$i]'";
		if (WORKER_DEBUG)
			echo $sql.PHP_EOL;
		$rows = $dbh->query($sql);
		if ($rows === false || $rows->rowCount() == 0) {
			$stmt = $dbh->prepare("INSERT INTO $products_tblname (sku, title, scrapped_flag) VALUES (:sku, :title, false)");
			$stmt->bindParam(':sku', $skus[$i]);
			$stmt->bindParam(':title', $titles[$i]);
			$stmt->execute();
		}
	}
}


function save_pages_flag($dbh, $pages_tblname, $page_num, $worker_id) {
	$stmt = $dbh->prepare("UPDATE $pages_tblname SET scrapped_flag=true, scrapper_id=:scrapper_id WHERE page_num=:page_num");
	$stmt->bindParam(':scrapper_id', $worker_id);
	$stmt->bindParam(':page_num', $page_num);
	$stmt->execute();
}

ignore_user_abort(true);
set_time_limit(0);

$worker_id = $argv[1];
$cookie = $argv[2];
$category_id = $argv[3];
$page_num = $argv[4];
$sem4 = sem_get('products_pagescrapping_lock');

global $cookiefilename;
global $proxy;
global $proxy_type;
global $proxy_userpwd;

$cookiefilename = $cookie;
$proxy = '64.191.90.8:32662';
$proxy_userpwd = 'pp-maybemacho:6jzkiasr';
$proxy_type = CURLPROXY_HTTP;

try {
	$dbh = fastenal_connectDB();
	$sql = 'SELECT * FROM categories WHERE id = '.$category_id;
	$rows = $dbh->query($sql);
	if ($rows === false || $rows->rowCount() == 0) exit;
	$row = $rows->fetch();
	if ($row === false) exit;

	echo date('c').": Worker #".$worker_id." is running with ".$cookie." on ".$row['title'].'#page'.$page_num.".\r\n";

	$title = $row['title'];
	$url = $row['url'];
	$ref_url = $row['ref_url'];
	$page_url = $row['page_url'];
	$products_num = $row['products_num'];
	$tblname_base = $row['products_tbl_name'];
	$multiple_pages_flag = $row['valid_flag'];

	$products_tblname = 'products_'.$tblname_base;
	$pages_tblname = 'pages_'.$tblname_base;
	$page_url = preg_replace('/pageno=\d+/', "pageno=".$page_num, $page_url);
	$prev_page_url = preg_replace('/pageno=\d+/', "pageno=".($page_num + 1), $page_url);
	
	if (!isset($products_tblname) || strlen($products_tblname) == 0 || $tblname_base != md5($title) || !$multiple_pages_flag)
		exit;

	$response = http_get($page_url, $prev_page_url);
	if (strlen($response['ERROR']) != 0) {
		echo "Error: ".$response['ERROR'].".\n";
		echo "When http_get($url, $ref_url).\n";
		exit;
	} else {
		echo "done with downloading $url.\r\n";
	}
	$html = str_get_html(tidy_html($response['FILE']));
	$tables = $html->find('table#searchResults');
	if (count($tables) > 0) {
		scrapping_first_page($dbh, $products_tblname, $tables[0]);
		save_pages_flag($dbh, $pages_tblname, $page_num, $worker_id);
	} else
		save_pages_flag($dbh, $pages_tblname, $page_num, -1);
} catch (Exception $e) {
	die("Error: " . $e->getMessage());
}