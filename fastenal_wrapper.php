<?php
if (count($argv) > 1) {
	$initial_page = (int)$argv[1];
} else 
	$initial_page = 1;
if (count($argv) > 2) {
	$end_page = (int)$argv[2];
} else
	$end_page = 887;
$start_page = $initial_page;
// $data_filename = "fastenal-handtools.csv";
// if (file_exists($data_filename)) {
// 	$filearray = file($data_filename);
// 	$start_page = $initial_page + (int)(count($filearray) / 10);
// }

while ($start_page <= $end_page) {
	$pages = $end_page - $start_page + 1;
	passthru("php fastenal.php $start_page $pages $end_page cookie.txt");
	for ($page = $start_page; $page <= $end_page; $page++) {
		$data_filename = "fastenal-handtools-page$page.csv";
		if (!file_exists($data_filename)) {
			$start_page = $page;
			break;
		} 
	}
	if ($page > $end_page) break;
// 	$filearray = file($data_filename);
// 	$start_page = $initial_page + (int)(count($filearray) / 10);
}
?>
