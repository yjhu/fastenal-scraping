<?php
ignore_user_abort(true);
set_time_limit(0);
//date_default_timezone_set('America/Phoenix');
$fp = fopen('worker_lock', 'w');
flock($fp, LOCK_EX | LOCK_NB);
echo "Worker #".$argv[1]." is running.".PHP_EOL;
flock($fp, LOCK_UN);
fclose($fp);
@unlink('worker_lock');
sleep(rand(1,1));