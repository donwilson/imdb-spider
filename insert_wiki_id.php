<?php
	die;   // disabled - last run: 201303201219CST - notes: ~2000 entries not found because either foreign characters (likely links to foreign wiki) or links to missing or useless disambig pages
	
	set_time_limit(0);
	
	global $imdb, $wikipedia;
	
	$imdb = @mysql_connect("localhost", "", "") or die(mysql_error($imdb));
	@mysql_select_db("imdb", $imdb) or die(mysql_error($imdb));
	
	$wikipedia = @mysql_connect("localhost", "", "", true) or die(mysql_error($wikipedia));
	@mysql_select_db("dump_wikipedia_20130301", $wikipedia) or die(mysql_error($wikipedia));
	
	
	$result = @mysql_query("
		SELECT *
		FROM `entity_meta`
		WHERE
			entity_meta.meta_key = 'external_id:wikipedia:title'
			AND entity_meta.entity_id NOT IN (SELECT `entity_id` FROM `entity_meta` WHERE `meta_key` = 'external_id:wikipedia:id' OR `meta_key` = 'external_id:wikipedia:not_exist')
	", $imdb) or die(mysql_error($imdb));
	
	if(@mysql_num_rows($result) <= 0) {
		die("nothing to do...");
	}
	
	function pullWikipediaByHash($hash) {
		global $wikipedia;
		
		if(empty($hash)) {
			return false;
		}
		
		if(!preg_match("#^[a-f0-9]{32}$#si", $hash)) {
			$hash = md5($hash);
		}
		
		$result = @mysql_query("SELECT * FROM `articles` WHERE `title_hash` = '". mysql_real_escape_string($hash, $wikipedia) ."'", $wikipedia) or die(mysql_error($wikipedia));
		
		if(@mysql_num_rows($result) <= 0) {
			return false;
		}
		
		$article = @mysql_fetch_assoc($result);
		
		if(!empty($article['redirect_to'])) {
			return pullWikipediaByHash($article['redirect_to']);
		}
		
		return $article;
	}
	
	while($imdb_row = @mysql_fetch_assoc($result)) {
		print "Pulling '". $imdb_row['value'] ."'... ";
		
		// find wikipedia article
		$wiki = pullWikipediaByHash( md5($imdb_row['value']) );
		
		if(!empty($wiki['wiki_id'])) {
			// found it!
			print "found! Setting to '". $wiki['wiki_id'] ."'... ";
			
			@mysql_query("
				INSERT INTO `entity_meta`
				SET
					`entity_id` = '". mysql_real_escape_string($imdb_row['entity_id'], $imdb) ."',
					`meta_key`	= 'external_id:wikipedia:id',
					`value`		= '". mysql_real_escape_string($wiki['wiki_id'], $imdb) ."'
			", $imdb);
		}/* else {
			// no article found, flag it
			print "not found! Flagging... ";
			
			@mysql_query("
				INSERT INTO `entity_meta`
				SET
					`entity_id` = '". mysql_real_escape_string($imdb_row['entity_id'], $imdb) ."',
					`meta_key`	= 'external_id:wikipedia:not_exist',
					`value`		= NOW()
			", $imdb);
		}*/
		
		print "done!". PHP_EOL;
	}