<?php
	set_time_limit(0);
	ini_set('memory_limit', "1024M");
	
	if(function_exists('mb_internal_encoding')): mb_internal_encoding("UTF-8"); endif;
	
	@mysql_connect("localhost", "", "");
	@mysql_select_db("imdb");
	
	@mysql_query("SET character_set_client = 'UTF8'") or die(mysql_error());
	@mysql_query("SET character_set_results = 'UTF8'") or die(mysql_error());
	@mysql_query("SET character_set_connection = 'UTF8'") or die(mysql_error());
	
	$result = @mysql_query("SELECT `id`, `seoid`, `title`, `type` FROM `entity` WHERE `title_raw` = '' AND `title` != '-2'") or die(mysql_error());
	
	while($entry = @mysql_fetch_assoc($result)) {
		$new_title = $entry['title'];
		$new_title = html_entity_decode($new_title, ENT_QUOTES | ENT_HTML401, 'UTF-8');
		$new_title = preg_replace("#\(\s*T?VG?\s*\)#si", "", $new_title);
		$new_title = preg_replace("#\(\s*[0-9\?]{4}\s*\/?\s*[IVX]*\s*\)#si", "", $new_title);
		$new_title = trim($new_title);
		
		$new_type = $entry['type'];
		
		if(("movie" === $new_type) && preg_match("#\(\s*(T?VG?)\s*\)#si", $entry['title'], $match)) {
			switch(strtoupper(trim($match[1]))) {
				case 'V':
					$new_type = "video_short";
				break;
				case 'TV':
					$new_type = "movie_tv";
				break;
				case 'VG':
					$new_type = "video_game";
				break;
			}
		}
		
		/*
		print PHP_EOL;
		print "imdb_key = ". $entry['seoid'] . PHP_EOL;
		print "title_raw = ". $entry['title'] . PHP_EOL;
		print "title = ". $new_title . PHP_EOL;
		print "type = ". $new_type . PHP_EOL;
		print PHP_EOL;
		*/
		
		@mysql_query("
			UPDATE `entity`
			SET
				`type` = '". mysql_real_escape_string($new_type) ."',
				`title` = '". mysql_real_escape_string($new_title) ."',
				`title_raw` = '". mysql_real_escape_string($entry['title']) ."'
			WHERE
				`id` = '". mysql_real_escape_string($entry['id']) ."'
			") or print(__LINE__ ."/". mysql_errno() .": ". mysql_error() . PHP_EOL);
	}