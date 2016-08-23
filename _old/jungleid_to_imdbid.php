<?php
	die;
	
	set_time_limit(0);
	ini_set('memory_limit', "1024M");
	
	require(dirname(__FILE__) ."/../config.php");
	
	$em_meta_key = "external_db_imdb";
	
	print "Pulling pool of movies... ";
	
	$result = @mysql_query("SELECT `id`, `title`, `released` FROM `entertainment_movie` WHERE `entertainment_movie`.`tmp_imdbids_done` = '0' AND NOT EXISTS (SELECT * FROM `entertainment_movie_meta` WHERE entertainment_movie_meta.entertainment_movie = entertainment_movie.id AND entertainment_movie_meta.key = '". mysql_escape_string($em_meta_key) ."')");
	$update_movie_ids = array();
	
	if(!mysql_num_rows($result)) {
		print "nothing to do...";
		
		die;
	}
	
	print "done! Running...\n\n";
	
	/*
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1A543a Safari/419.3");
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	*/
	
	while($movie = @mysql_fetch_assoc($result)) {
		$released = $movie['released'];
		$movie['released'] = "";
		
		if(preg_match("#^\[(.+?)\]$#si", $released)) {
			
			$releaseds = json_decode($released);
			$released = str_replace("-", " ", current($releaseds));
		}
		
		if(preg_match("#([0-9]{4})#si", $released, $matched)) {
			$movie['released'] = $matched[1];
		}
		
		print "Running '". $movie['title'] ."' (". $movie['released'] .") ...";
		
		//$url = "https://www.google.com/search?q=site%3Aimdb.com+". urlencode($movie['title'] ." ". $movie['released']);
		$url = "http://www.imdbapi.com/?i=&t=". urlencode($movie['title']) . (!empty($movie['released'])?"&y=". $movie['released']:"");
		
		//curl_setopt($ch, CURLOPT_URL, $url);
		//$contents = curl_exec($ch);
		
		$contents = file_get_contents($url);
		
		if(empty($contents)) {
			print "Weird error...\n\n";
		}
		
		$return = json_decode($contents, true);
		
		if(!empty($return['imdbID'])) {
			//print $movie['title'] . (!empty($movie['released'])?" (". $movie['released'] .")":"") ." = ". ($movie['imdbid'] !== false?$movie['imdbid']:"FALSE!!!!!!!!!") ."<br /><br />\n";
			
			print "found ". $return['imdbID'] ." ";
			
			@mysql_query("
				INSERT INTO `entertainment_movie_meta`
				SET
					 `entertainment_movie` = '". mysql_escape_string($movie['id']) ."'
					,`key` = '". mysql_escape_string($em_meta_key) ."'
					,`value` = '". mysql_escape_string($return['imdbID']) ."'
			");
		}
		
		@mysql_query("
			INSERT INTO `imdbapi`
			SET
				 `imdbid` = '". mysql_escape_string(@$return['imdbID']) ."'
				,`imdbapi_url` = '". mysql_escape_string($url) ."'
				,`response` = '". mysql_escape_string(@$return['Response']) ."'
				,`body` = '". mysql_escape_string($contents) ."'
				,`timestamp` = UNIX_TIMESTAMP()
		");
		
		@mysql_query("UPDATE `entertainment_movie` SET `tmp_imdbids_done` = '1' WHERE `id` = '". mysql_escape_string($movie['id']) ."'");
		
		print "done.\n";
	}
	
	print "\n";
	
	//curl_close($ch);
