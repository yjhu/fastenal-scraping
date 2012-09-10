<?php

function escape_comma($line){
	$segs = explode(',', $line);
	$segs_no = count($segs);
	if ($segs_no > 8) {
		for ($i = 0; $i < $segs_no - 8; $i++) {
			$segs[1] .= $segs[2 + $i];
			unset($segs[2 + $i]);
		}	
	}
	return implode(',', $segs);
}

if (count($argv) != 2) {
	echo "Usage: php $argv[0] filename-to-segemented\r\n";
	exit;
}

$linearray = file($argv[1]);
if (count($linearray) > 65536) {
	echo "$argv[1] has ".count($linearray)." lines, which is too big for one .csv file, so let's segement it into less than 65536 records per segment.\r\n";
}

$part_no = (int)ceil((count($linearray) - 1) / 65535);
for ($p = 1; $p < $part_no + 1; $p++) {
	$filename = $argv[1];
	if ($part_no > 1)
		$filename = preg_replace('/.csv$/', "_part$p.csv", $filename);
	echo "Saving $filename\r\n";
	$fp = fopen($filename, 'w+');
	fwrite($fp, $linearray[0]);
	for ($r = 0; $r < 65535; $r++) {
		$line_no = 1 + ($p - 1)*65535 + $r;
		if ($line_no >= count($linearray)) break;
		fwrite($fp, escape_comma($linearray[$line_no]));
	}
	fclose($fp);
}