PRAGMA journal_mode = delete;
PRAGMA page_size = 65536;
VACUUM;

PRAGMA auto_vacuum = INCREMENTAL;
PRAGMA case_sensitive_like = ON;
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA locking_mode = EXCLUSIVE;
PRAGMA synchronous = NORMAL;
PRAGMA threads = 4;
PRAGMA trusted_schema = OFF;

CREATE TABLE twitter_media (
	media_id INTEGER NOT NULL,
	media_data TEXT NOT NULL,
	PRIMARY KEY (media_id)
) WITHOUT ROWID;

CREATE TABLE twitter_tree (
	tweet_id INTEGER NOT NULL,
	tweet_parent INTEGER NOT NULL,
	tweet_root INTEGER NOT NULL DEFAULT 0,
	PRIMARY KEY (tweet_id,tweet_parent)
) WITHOUT ROWID;

CREATE TABLE twitter_tweets (
	tweet_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	tweet_data TEXT NOT NULL,
	PRIMARY KEY (tweet_id),
	CONSTRAINT twitter_tweets_ibfk_1 FOREIGN KEY (user_id) REFERENCES twitter_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) WITHOUT ROWID;

CREATE TABLE twitter_users (
	user_id INTEGER NOT NULL,
	user_data TEXT NOT NULL,
	PRIMARY KEY (user_id)
) WITHOUT ROWID;
