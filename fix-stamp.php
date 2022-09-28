#!/usr/bin/env php
<?php

require_once __DIR__.'/vendor/autoload.php';

date_default_timezone_set('UTC');

$db = new \TDC\PDO\SQLite('/media/data2/twitter/db.sqlite', [\PDO::SQLITE_ATTR_OPEN_FLAGS => \PDO::SQLITE_OPEN_READONLY]);

$sel = $db->prepexec("SELECT tweet_data FROM twitter_tweets WHERE tweet_id = ?");

$datef = new \DateTime('now');
$datef->setTimezone(new DateTimeZone('Europe/Berlin'));

while ($line = fgets(STDIN)) {
	if (!preg_match('~^<s id="\d+" tweet="(\d+)" ~', $line, $m)) {
		echo $line;
		continue;
	}

	$id = intval($m[1]);
	if ($id > 29700859247) {
		echo $line;
		continue;
	}

	$sel->execute([$id]);
	$row = $sel->fetch();
	if (!$row) {
		fprintf(STDERR, "Not found %s\n", $id);
		echo $line;
		continue;
	}
	$t = json_decode($row['tweet_data'], true);
	if (empty($t)) {
		$row['tweet_data'] = stripcslashes($row['tweet_data']);
		$t = json_decode($row['tweet_data'], true);
	}
	if (empty($t)) {
		fprintf(STDERR, "Bad data %s\n", $id);
		echo $line;
		continue;
	}

	$utc = strtotime($t['created_at']);
	$stamp = date('Y-m-d H:i:s', $utc);
	$datef->setTimestamp($utc);
	$lstamp = $datef->format('Y-m-d H:i:s T');

	echo "<s id=\"{$t['id']}\" tweet=\"{$t['id']}\" lang=\"{$t['lang']}\" user=\"{$t['user']}\" stamp=\"{$stamp}\" lstamp=\"{$lstamp}\">\n";

	fprintf(STDERR, "Fixed %s => %s\n", $id, $stamp);
}
