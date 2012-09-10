<?php
require_once 'fastenal_db.php';

define('DISPATCHER_DEBUG', true);

global $unscrapped_products;
function fork_worker($worker_id, $cookie, $proxy, $category_id, $sku) {
	global $unscrapped_products;

	$worker_cmdstr = 'c:\wamp\bin\php\php5.4.3\php.exe products_detailscrapping_worker.php';
	$worker_cmdstr .= ' '.$worker_id;
	$worker_cmdstr .= ' '.$cookie;
	$worker_cmdstr .= ' '.$proxy['SERVER_PORT'];
	$worker_cmdstr .= ' '.$proxy['USER_PWD'];
	$worker_cmdstr .= ' '.$proxy['TYPE'];
	$worker_cmdstr .= ' '.$category_id;
	$worker_cmdstr .= ' '.$sku;

	// 	$descriptorspec = array(
	// 			0 => STDIN,
	// 			1 => array('file', "dump\products_detailscrapping_worker#$worker_id-dump.txt", 'a'),
	// 			2 => array('file', "dump\products_detailscrapping_worker#$worker_id-dump.txt", 'a')
	// 	);
	$descriptorspec = array(
			0 => STDIN,
			1 => STDOUT,
			2 => STDERR
	);

	if (DISPATCHER_DEBUG) {
		echo 'forking another worker process: '.$worker_cmdstr.PHP_EOL;
		echo "Total unscrapped products: ".count($unscrapped_products).PHP_EOL;
	}

	return proc_open($worker_cmdstr, $descriptorspec, $pipes);
}

ignore_user_abort(true);
set_time_limit(0);

// cookies
$cookies = array();
$d = dir('cookies');
while (false !== ($e = $d->read())) {
	if (stristr($e, 'cookie') && stristr($e, '.txt'))
		$cookies[] = 'cookies'."\\".$e;
}
$d->close();

// proxies
$proxies = array(
		// https://www.proxysolutions.net/ USA-Host 1xIP unlimited USD7.00 monthly
		array('SERVER_PORT'=>'64.191.90.8:32662', 'USER_PWD' => 'pp-maybemacho:6jzkiasr', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 30),
		// https://www.proxysolutions.net/ Canada-Host 1xIP unlimited USD12.00 monthly
		array('SERVER_PORT'=>'74.82.194.66:26526', 'USER_PWD' => 'pp-epoxyloll:sign@wavy', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 30),
		
		// http://www.ukproxyserver.co.uk/ 2xIPs 30gb GBP8.99 monthly
		array('SERVER_PORT'=>'72.51.35.99:80', 'USER_PWD' => 'yajun_hu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 10),
		array('SERVER_PORT'=>'72.51.35.99:8080', 'USER_PWD' => 'yajun_hu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 10),
		array('SERVER_PORT'=>'72.51.35.99:36673', 'USER_PWD' => 'yajun_hu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 10),
		//		array('SERVER_PORT'=>'66.109.21.138:8080', 'USER_PWD' => 'yajun_hu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 5),
		array('SERVER_PORT'=>'66.109.21.138:80', 'USER_PWD' => 'yajun_hu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 10),
		array('SERVER_PORT'=>'66.109.21.138:36673', 'USER_PWD' => 'yajun_hu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 10)

// 		// http://proxybonanza.com/ 70xIPs 15gb USD21.99 monthly
// 		array('SERVER_PORT'=>'65.49.1.24:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.40:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.41:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.42:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.43:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.44:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.59:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.38:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.57:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.32:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.45:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.46:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.47:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.48:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.37:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.49:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.50:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.51:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.52:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.53:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.54:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.55:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'65.49.1.56:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),

// 		array('SERVER_PORT'=>'67.90.43.7:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),

// 		array('SERVER_PORT'=>'67.106.134.14:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.21:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.15:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.19:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.16:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.17:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.20:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.18:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.44:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.62:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.61:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.60:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.50:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.43:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.56:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.49:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.51:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.57:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.52:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.58:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.54:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.53:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'67.106.134.55:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),

// 		array('SERVER_PORT'=>'208.72.119.59:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.60:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.61:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.62:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.20:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.19:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.18:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.17:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.16:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.15:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.14:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.13:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.12:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.11:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.10:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.9:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.8:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.7:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.52:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.53:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.54:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.55:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1),
// 		array('SERVER_PORT'=>'208.72.119.28:60099', 'USER_PWD' => 'yajunhu:hyj001', 'TYPE'=>CURLPROXY_HTTP, 'MAX_SESSIONS' => 1)
);

$worker_params = array();
$break_flag = false;
$j = 0;
foreach($proxies as $proxy) {
	if ($break_flag) break;
	for ($i = 0; $i < $proxy['MAX_SESSIONS']; $i++) {
		if ($j >= count($cookies)) {
			break; $break_flag = true;
		}
		$worker_params[] = array('PROXY' => $proxy, 'COOKIE' => $cookies[$j++]);
	}
}
$worker_num = count($worker_params);

$main_categories = array (
		'Products>Fasteners',
		'Products>Power Transmission & Motors',
		'Products>Safety',
		'Products>Tools & Equipment',
		'Products>Cutting Tools & Metalworking',
		'Products>Material Handling, Storage, & Packaging',		
		'Products>Electrical',
		'Products>Plumbing',		
		'Products>Hydraulics & Pneumatics',
		'Products>Abrasives',
		'Products>Fleet & Automotive',
		'Products>Raw Materials',
		'Products>Lifting and Rigging',
		'Products>Welding',
		'Products>Chemicals & Paints',
		'Products>Janitorial',
		'Products>Office Products & Furniture',
		'Products>HVAC',
		'Products>Mil-Spec'
);

foreach ($main_categories as $main_category) {
	do {
		$workers = array();
		$unscrapped_products = array();
		$worker_num = count($worker_params);

		try {
			$dbh = fastenal_connectDB();
			$sql = 'SELECT * FROM categories '.
					'WHERE is_leaf=true and scrapped_flag=true '.
					'and products_tbl_name is not null '.
					"and title like '%$main_category%'";
			$categories_rows = $dbh->query($sql);
			if ($categories_rows === false || $categories_rows->rowCount() == 0) exit;
			while(false !== ($categories_row = $categories_rows->fetch())) {
				$products_tblname = 'products_'.$categories_row['products_tbl_name'];
				$sql = "SELECT * FROM $products_tblname WHERE scrapped_flag=false or scrapped_flag is null";
				$products_rows = $dbh->query($sql);
				if (false !== $products_rows && $products_rows->rowCount() > 0) {
					while (false != ($products_row = $products_rows->fetch())) {
						$unscrapped_products[] = array('categories_id' => $categories_row['id'], 'sku' => $products_row['sku']);
					}
				}
				unset($products_rows);
			}
			unset($categories_rows);

			if (DISPATCHER_DEBUG)
				echo "$main_category unscrapped products: ".count($unscrapped_products).PHP_EOL;
			if (count($unscrapped_products) == 0) break;

			if (count($unscrapped_products) < $worker_num)
				$worker_num = count($unscrapped_products);
			for ($i = 0; $i < $worker_num; $i++) {
				$next_unscrapped_product = array_shift($unscrapped_products);
				$workers[] = fork_worker($i, $worker_params[$i]['COOKIE'], $worker_params[$i]['PROXY'], $next_unscrapped_product['categories_id'], $next_unscrapped_product['sku']);
			}

			$break_flag = false;
			do {
				sleep(10);
				for ($i = 0; $i < $worker_num; $i++) {
					$status = proc_get_status($workers[$i]);
					if ($status['running'] == false) {
						if (DISPATCHER_DEBUG)
							echo "Worker #$i has terminated.".PHP_EOL;
						proc_close($workers[$i]);
						$next_unscrapped_product = array_shift($unscrapped_products);
						if ($next_unscrapped_product === null) {
							unset($workers[$i]);
							$break_flag = true;
							break;
						}
						$workers[$i] = fork_worker($i, $worker_params[$i]['COOKIE'], $worker_params[$i]['PROXY'], $next_unscrapped_product['categories_id'], $next_unscrapped_product['sku']);
					}
				}
			} while (!$break_flag);

			if (DISPATCHER_DEBUG)
				echo "All records have been processed.".PHP_EOL;

			do {
				if (count($workers) == 0)
					break;
				foreach ($workers as $k => $v) {
					$status = proc_get_status($v);
					if ($status['running'] == false) {
						if (DISPATCHER_DEBUG)
							echo "Worker #$k has terminated.".PHP_EOL;
						proc_close($v);
						unset($workers[$k]);
					} else {
						if (DISPATCHER_DEBUG)
							echo "Worker #$k is still running.".PHP_EOL;
					}
				}
				echo '=========================================='.PHP_EOL;
				sleep(10);
			} while (true);
		} catch (Exception $e) {
			die("Error: " . $e->getMessage());
		}
	} while(true);
}