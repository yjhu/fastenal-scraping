<?php
ignore_user_abort(true);
set_time_limit(0);

$descriptorspec = array(
		0 => STDIN,
		1 => STDOUT,  
		2 => STDERR 
);

$worker_processes = array();
for ($i = 0; $i < 100; $i++) {
	$worker_processes[] = proc_open('c:\wamp\bin\php\php5.4.3\php.exe worker.php '.($i+1), $descriptorspec, $pipes);
}
do {
	if (count($worker_processes) == 0)
		break;
	foreach ($worker_processes as $k => $v) {
		$status = proc_get_status($v);
		if ($status['running'] == false) {
			echo "Worker #".($k + 1)." PID#".$status['pid']." has terminated.".PHP_EOL;
//			var_dump($status);
			proc_close($v);
			unset($worker_processes[$k]);
//			break;
		}
	}
} while (true);

