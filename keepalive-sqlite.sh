#!/bin/bash
while true
do
	find . -type f -name 'twitter*log' | sort -r | sed 1,10d | xargs -r rm -fv
	DATE=`date -u '+%Y%m%d-%H%M%S'`
	time timeout -k 13h 12h php stream-sqlite.php 2>&1 | tee twitter-${DATE}.log
	echo "Died, sleeping..."
	sleep 3m
done
