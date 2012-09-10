<?php
require_once 'fastenal_db.php';

define('DISPATCHER_DEBUG', true);

function fork_worker($worker_id, $cookie, $category_id) {
	$worker_cmdstr = 'c:\wamp\bin\php\php5.4.3\php.exe products_tblcreating_worker.php';
	$worker_cmdstr .= ' '.$worker_id;
	$worker_cmdstr .= ' '.$cookie;
	$worker_cmdstr .= ' '.$category_id;

// 	$descriptorspec = array(
// 			0 => STDIN,
// 			1 => array('file', "dump\products_tblcreating_worker#$worker_id-dump.txt", 'a'),
// 			2 => array('file', "dump\products_tblcreating_worker#$worker_id-dump.txt", 'a')
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

ignore_user_abort(true);
set_time_limit(0);

// get the cookie files
$cookies = array();
$d = dir('cookies');
while (false !== ($e = $d->read())) {
	if (stristr($e, 'cookie') && stristr($e, '.txt'))
		$cookies[] = 'cookies'."\\".$e;
}
$d->close();

$worker_num = count($cookies);
$workers = array();

try {
	$dbh = fastenal_connectDB();
	$sql = 'SELECT * FROM categories WHERE is_leaf=true and scrapped_flag=true and products_tbl_name is null';
	$rows = $dbh->query($sql);
	if ($rows === false || $rows->rowCount() == 0) exit;
	if ($rows->rowCount() < $worker_num)
		$worker_num = $rows->rowCount();
	for ($i = 0; $i < $worker_num; $i++) {
		$row = $rows->fetch();
		$workers[] = fork_worker($i, $cookies[$i], $row['id']);
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
				$row = $rows->fetch();
				if ($row === false) {
					unset($workers[$i]);
					$break_flag = true;
					break;
				}
				$workers[$i] = fork_worker($i, $cookies[$i], $row['id']);
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