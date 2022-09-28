#!/usr/bin/env php
<?php

if (!file_exists($argv[1])) {
	echo "No such file {$argv[1]}!\n";
	exit(1);
}

$d = file_get_contents($argv[1]);
$d = trim($d);
$d = preg_split('~(?<=\n</s>\n\n)~', $d, -1, PREG_SPLIT_NO_EMPTY);
$cnt_a = count($d);

natsort($d);
$d = array_unique($d);
$cnt_b = count($d);

if ($cnt_a - $cnt_b > 0) {
	$d = implode('', $d);
	file_put_contents($argv[1], $d);
	fprintf(STDERR, "%s: %s\n", $argv[1], $cnt_a - $cnt_b);
}

/*
$ts = [];
$t = '';
$id = 0;
$cnt = 0;

$fh = fopen($argv[1], 'rb');
while ($l = fgets($fh)) {
	if (preg_match('~^<s.* tweet="(\d+)"~', $l, $m)) {
		$id = intval($m[1]);
		++$cnt;
		echo "{$cnt}\r";
	}

	$t .= $l;
	if (substr($l, 0, 4) === '</s>') {
		$ts[$id] = $t;
		$t = '';
	}
}
fclose($fh);

ksort($ts);
echo "{$cnt} => ".count($ts)." \n";
*/
