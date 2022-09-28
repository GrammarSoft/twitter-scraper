#!/usr/bin/env php
<?php

require_once __DIR__.'/vendor/autoload.php';

date_default_timezone_set('UTC');

// From https://github.com/client9/snowflake2time
function utc2snowflake($stamp) {
	bcscale(0);
	return bcmul(bcsub(bcmul($stamp, 1000), '1288834974657'), '4194304');
}

function snowflake2utc($sf) {
	bcscale(0);
	return bcdiv(bcadd(bcdiv($sf, '4194304'), '1288834974657'), '1000');
}

/*
$maxid = utc2snowflake(time()-86400);
$tid = 0;
for ($stamp = time() ; $tid === 0 ; $stamp -= 86400) {
	$date = date('Ymd', $stamp).'.txt';
	$fs = trim(shell_exec("find /media/data1/dumped/twitter/ -type f -name '$date'"));
	if (empty($fs)) {
		echo "No files for $date\n";
		continue;
	}
	$fs = explode("\n", $fs);
	foreach ($fs as $f) {
		$tail = shell_exec("tail -n 20 '$f' | grep id= | grep tweet= | tail -n 1");
		if (preg_match('~tweet="(\d+)"~', $tail, $m)) {
			$tid = max($tid, intval($m[1]));
		}
	}
}

echo "Will fetch between $tid and $maxid\n";
*/

$db = new \TDC\PDO\SQLite('db.sqlite');

$db2 = new \TDC\PDO\SQLite('db.sqlite');
$GLOBALS['sel_m'] = $db2->prepare("SELECT media_data FROM twitter_media WHERE media_id = ?");

function fetch_media($mid) {
	$GLOBALS['sel_m']->execute([$mid]);
	$media = json_decode($GLOBALS['sel_m']->fetchColumn(0), true);
	return $media;
}

// utc2snowflake: bcmul(bcsub(bcmul($stamp, 1000), '1288834974657'), '4194304');
// 1145482148967350272 is 2019-07-01 00:00:00 UTC
// 1200927488209846272 is 2019-12-01 00:00:00 UTC
// WHERE tweet_id >= 1200927488209846272

//$stm = $db->prepexec("SELECT tweet_data FROM twitter_tweets WHERE tweet_id > :tid AND tweet_id <= :max ORDER BY tweet_id ASC", ['tid' => $tid, 'max' => $maxid]);
$stm = $db->prepexec("SELECT tweet_data FROM twitter_tweets ORDER BY tweet_id ASC");

$datef = new \DateTime('now');
$datef->setTimezone(new DateTimeZone('Europe/Berlin'));

$i = 0;
for ( ; $row = $stm->fetch() ; ++$i) {
	$t = json_decode($row['tweet_data'], true);
	if (empty($t)) {
		$row['tweet_data'] = stripcslashes($row['tweet_data']);
		$t = json_decode($row['tweet_data'], true);
	}
	if (empty($t)) {
		continue;
	}

	if (!empty($t['retweeted_status'])) {
		continue;
	}

	$txt = '';
	if (!empty($t['extended_tweet']['full_text'])) {
		$txt = $t['extended_tweet']['full_text'];
	}
	else if (!empty($t['full_text'])) {
		$txt = $t['full_text'];
	}
	else {
		$txt = $t['text'];
	}

	if (!empty($t['entities']['urls'])) {
		foreach ($t['entities']['urls'] as $u) {
			$txt = str_replace($u['url'], $u['expanded_url'], $txt);
		}
	}
	if (!empty($t['entities']['media'])) {
		foreach ($t['entities']['media'] as $u) {
			if (!is_array($u)) {
				$u = fetch_media($u);
			}
			$txt = str_replace($u['url'], $u['media_url_https'] ?? $u['media_url'], $txt);
		}
	}
	if (!empty($t['extended_tweet']['entities']['urls'])) {
		foreach ($t['extended_tweet']['entities']['urls'] as $u) {
			$txt = str_replace($u['url'], $u['expanded_url'], $txt);
		}
	}
	if (!empty($t['extended_tweet']['entities']['media'])) {
		foreach ($t['extended_tweet']['entities']['media'] as $u) {
			if (!is_array($u)) {
				$u = fetch_media($u);
			}
			$txt = str_replace($u['url'], $u['media_url_https'] ?? $u['media_url'], $txt);
		}
	}

	if (empty($t['lang'])) {
		$t['lang'] = 'xx';
	}

	$utc = snowflake2utc($t['id']);
	if ($t['id'] <= 29700859247) {
		$utc = strtotime($t['created_at']);
	}
	$stamp = date('Y-m-d H:i:s', $utc);
	$datef->setTimestamp($utc);
	$lstamp = $datef->format('Y-m-d H:i:s T');

	$out = '';
	$out .= "<s id=\"{$t['id']}\" tweet=\"{$t['id']}\" lang=\"{$t['lang']}\" user=\"{$t['user']}\" stamp=\"{$stamp}\" lstamp=\"{$lstamp}\">\n";
	$out .= $txt;
	$out .= "\n</s>\n\n";

	$date = $datef->format('Ymd');
	$year = substr($date, 0, 4);

	if (!is_dir("/media/data1/dumped/twitter/{$t['lang']}/{$year}")) {
		shell_exec("mkdir -p /media/data1/dumped/twitter/{$t['lang']}/{$year}/");
	}
	file_put_contents("/media/data1/dumped/twitter/{$t['lang']}/{$year}/{$date}.txt", $out, FILE_APPEND);

	if ($i % 1000 == 0) {
		fprintf(STDERR, "Dumped $i ...\r");
	}
}
fprintf(STDERR, "Dumped $i ...\n");
