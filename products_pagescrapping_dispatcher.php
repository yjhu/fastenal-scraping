<?php
require_once 'fastenal_db.php';

define('DISPATCHER_DEBUG', true);

function fork_worker($worker_id, $cookie, $category_id, $page_num) {
	$worker_cmdstr = 'c:\wamp\bin\php\php5.4.3\php.exe products_pagescrapping_worker.php';
	$worker_cmdstr .= ' '.$worker_id;
	$worker_cmdstr .= ' '.$cookie;
	$worker_cmdstr .= ' '.$category_id;
	$worker_cmdstr .= ' '.$page_num;

	// 	$descriptorspec = array(
	// 			0 => STDIN,
	// 			1 => array('file', "dump\products_pagescrapping_worker#$worker_id-dump.txt", 'a'),
	// 			2 => array('file', "dump\products_pagescrapping_worker#$worker_id-dump.txt", 'a')
	// 	);
	$descriptorspec = array(
			0 => STDIN,
			1 => STDOUT,
			2 => STDERR
	);

	if (DISPATCHER_DEBUG)
		echo 'forking another worker process: '.$worker_cmdstr.PHP_EOL;

	return proc_open($worker_cmdstr, $descriptorspec, $pipes);
}

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

ignore_user_abort(true);
set_time_limit(0);


foreach ($main_categories as $main_category) {
// 	do {
		$worker_num = 300;
		$workers = array();
		$unscrapped_pages = array();

		try {
			echo "Processing $main_category".PHP_EOL;

			$dbh = fastenal_connectDB();
			$sql = 'SELECT * FROM categories '.
					'WHERE is_leaf=true and scrapped_flag=true '.
					'and products_tbl_name is not null and valid_flag = true '.
					"and title like '%$main_category%'";
			$categories_rows = $dbh->query($sql);
			if ($categories_rows === false || $categories_rows->rowCount() == 0) break;
			while(false !== ($categories_row = $categories_rows->fetch())) {
				$pages_tblname = 'pages_'.$categories_row['products_tbl_name'];
				$sql = "SELECT * FROM $pages_tblname WHERE scrapped_flag=false";
				$pages_rows = $dbh->query($sql);
				if (false !== $pages_rows && $pages_rows->rowCount() > 0) {
					while (false != ($pages_row = $pages_rows->fetch())) {
						$unscrapped_pages[] = array('categories_id' => $categories_row['id'], 'page_num' => $pages_row['page_num']);
					}
				}
				unset($pages_rows);
			}
			unset($categories_rows);

			echo "$main_category unscrapped pages: ".count($unscrapped_pages).PHP_EOL;
			if (count($unscrapped_pages) == 0) continue;

			if (count($unscrapped_pages) < $worker_num)
				$worker_num = count($unscrapped_pages);
			for ($i = 0; $i < $worker_num; $i++) {
				$next_unscrapped_page = array_shift($unscrapped_pages);
				$workers[] = fork_worker($i, 'cookies\cookie-proxy-01.txt', $next_unscrapped_page['categories_id'], $next_unscrapped_page['page_num']);
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
						$next_unscrapped_page = array_shift($unscrapped_pages);
						if ($next_unscrapped_page === null) {
							unset($workers[$i]);
							$break_flag = true;
							break;
						}
						$workers[$i] = fork_worker($i, 'cookies\cookie-proxy-01.txt', $next_unscrapped_page['categories_id'], $next_unscrapped_page['page_num']);
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
				sleep(10);
			} while (true);
		} catch (Exception $e) {
			die("Error: " . $e->getMessage());
		}
// 	} while (true);
}