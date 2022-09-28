#!/usr/bin/env php
<?php

require_once __DIR__.'/vendor/autoload.php';

$GLOBALS['-cache-media'] = [];
$GLOBALS['-cache-users'] = [];
$GLOBALS['-cache-tweets'] = [];

function store_media($media) {
	if (!empty($GLOBALS['-cache-media'][$media['id']])) {
		return false;
	}

	$data = json_encode($media, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	$GLOBALS['media_ins']->execute([$media['id'], $data]);

	$GLOBALS['-cache-media'][$media['id']] = true;

	return true;
}

function store_user($user) {
	if (!empty($GLOBALS['-cache-users'][$user['id']])) {
		return false;
	}

	$data = json_encode($user, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	$GLOBALS['user_ins']->execute([$user['id'], $data, $data]);

	$GLOBALS['-cache-users'][$user['id']] = true;

	return true;
}

function store_tweet($tweet) {
	if (!empty($GLOBALS['-cache-tweets'][$tweet['id']])) {
		return false;
	}

	store_user($tweet['user']);
	$tweet['user'] = $tweet['user']['id'];

	if (!empty($tweet['retweeted_status']) ) {
		store_tweet($tweet['retweeted_status']);
		$tweet['retweeted_status'] = $tweet['retweeted_status']['id'];
	}

	if (!empty($tweet['quoted_status']) ) {
		store_tweet($tweet['quoted_status']);
		$tweet['quoted_status'] = $tweet['quoted_status']['id'];
	}

	if (!empty($tweet['entities']['media'])) {
		foreach ($tweet['entities']['media'] as $k => $m) {
			store_media($m);
			$tweet['entities']['media'][$k] = $m['id'];
		}
	}

	if (!empty($tweet['extended_entities']['media'])) {
		foreach ($tweet['extended_entities']['media'] as $k => $m) {
			store_media($m);
			$tweet['extended_entities']['media'][$k] = $m['id'];
		}
	}

	$data = json_encode($tweet, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	$GLOBALS['tweet_ins']->execute([$tweet['id'], $tweet['user'], $data]);

	if (!empty($tweet['in_reply_to_status_id'])) {
		$GLOBALS['tree_ins']->execute([$tweet['id'], $tweet['in_reply_to_status_id']]);
	}

	$GLOBALS['-cache-tweets'][$tweet['id']] = true;

	return true;
}

$db = new \TDC\PDO\SQLite(__DIR__.'/db.sqlite');
$db->exec("PRAGMA auto_vacuum = INCREMENTAL");
$db->exec("PRAGMA case_sensitive_like = ON");
$db->exec("PRAGMA foreign_keys = ON");
$db->exec("PRAGMA journal_mode = WAL");
$db->exec("PRAGMA locking_mode = NORMAL");
$db->exec("PRAGMA synchronous = NORMAL");
$db->exec("PRAGMA threads = 4");
$db->exec("PRAGMA trusted_schema = OFF");

$media_ins = $db->prepare("INSERT INTO twitter_media (media_id, media_data) VALUES (?, ?) ON CONFLICT(media_id) DO NOTHING");
$user_ins = $db->prepare("INSERT INTO twitter_users (user_id, user_data) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET user_data = ?");
$tweet_ins = $db->prepare("INSERT INTO twitter_tweets (tweet_id, user_id, tweet_data) VALUES (?, ?, ?) ON CONFLICT(tweet_id) DO NOTHING");
$tree_ins = $db->prepare("INSERT INTO twitter_tree (tweet_id, tweet_parent) VALUES (?, ?) ON CONFLICT(tweet_id,tweet_parent) DO NOTHING");

require_once __DIR__.'/keys.php';

$GLOBALS['count'] = 0;
$langs = ['da' => 0, 'de' => 0];
$terms = [];
foreach (array_keys($langs) as $lang) {
	$qs = explode("\n", trim(file_get_contents(__DIR__."/search-terms-{$lang}.txt")));
	foreach ($qs as $q) {
		$q = trim($q);
		if (empty($q)) {
			continue;
		}
		$terms[] = $q;
	}
}

sort($terms);
$terms = array_unique($terms);

$stream = \Spatie\TwitterStreamingApi\PublicStream::create($keys['oauth_access_token'], $keys['oauth_access_token_secret'], $keys['consumer_key'], $keys['consumer_secret']);

$stream->setLocale(implode(',', array_keys($langs)));

$stream->whenHears($terms, function(array $t) {
	if (empty($t['id'])) {
		echo "Skip: ".var_export($t, true)."\n";
		return;
	}
	if (empty($t['lang'])) {
		echo "Skip {$t['id']}: ".var_export($t, true)."\n";
		return;
	}
	/*
	if (!isset($GLOBALS['langs'][$t['lang']])) {
		echo "Skip {$t['lang']} {$t['id']}\n";
		return;
	}
	//*/

	foreach (['-cache-media', '-cache-users', '-cache-tweets'] as $i) {
		if (count($GLOBALS[$i]) > 10000) {
			echo "Clear cache $i\n";
			$GLOBALS[$i] = [];
		}
	}

	++$GLOBALS['langs'][$t['lang']];
	echo "Store {$t['lang']} {$t['id']} {$GLOBALS['langs'][$t['lang']]}\n";

	++$GLOBALS['count'];
	if ($GLOBALS['count'] % 100 == 0) {
		echo "Commit {$GLOBALS['count']}\n";
		$GLOBALS['db']->commit();
		$GLOBALS['db']->beginTransaction();
	}
    store_tweet($t);
});

$GLOBALS['db']->beginTransaction();
$stream->startListening();
$GLOBALS['db']->commit();
