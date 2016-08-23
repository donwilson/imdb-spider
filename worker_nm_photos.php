<?php
	require_once(__DIR__ ."/common.php");
	
	function imdb_pull_url($url) {
		$ch = curl_init();
		
		curl_setopt_array($ch, array(
			// bool
			CURLOPT_AUTOREFERER		=> true,
			CURLOPT_HEADER			=> false,
			CURLOPT_RETURNTRANSFER	=> true,
			
			// int
			CURLOPT_TIMEOUT			=> 30,
			
			// string
			CURLOPT_REFERER			=> "http://www.google.com/search?q=z943jikzfd&ref=23u9rsjkef&skey=". md5($url),
			CURLOPT_URL				=> $url,
			CURLOPT_USERAGENT		=> "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/22.0.1207.1 Safari/537.1",
		));
		
		$contents = curl_exec($ch);
		
		curl_close($ch);
		
		return $contents;
	}
	
	function clean_image_url($url) {
		$url = trim($url);
		$urls = explode("/", $url);
		$filename = array_pop($urls);
		$bleh = explode(".", $filename);
		return implode("/", $urls) ."/". array_shift($bleh) .".". array_pop($bleh);
	}
	
	function clean_imdb_image_url($image_url) {
		$cleaned_url = clean_image_url($image_url);
		
		$image_id = false;
		$image_key = false;
		
		$bleh = explode("/", $cleaned_url);
		$bleh2 = array_pop($bleh);
		$bleh3 = explode(".", $bleh2);
		
		$base64_encoded = array_shift($bleh3);
		$base64 = explode("^A", base64_decode($base64_encoded));
		
		if(!empty($base64[1]) && !empty($base64[4])) {
			$image_id = $base64[1];
			$image_key = $base64[4];
		}
		
		return array(
			'url'		=> $cleaned_url,
			'imdb_id'	=> $image_id,
			'imdb_key'	=> $image_key,
		);
	}
	
	function kill_imdb_key($imdb_key) {
		print "Killing '". $imdb_key ."'...". PHP_EOL;
		
		@mysql_query("UPDATE `entity` SET `media_id` = '0' WHERE `seoid` = '". mysql_real_escape_string($imdb_key) ."'") or print(__LINE__ ."[". $imdb_key ."]: ". mysql_error() . PHP_EOL);
	}
	
	function pull_imdb_key_photo($imdb_key) {
		print "Pulling '". $imdb_key ."'... ";
		
		if(!preg_match("#^nm[0-9]{7}$#si", $imdb_key)) {
			print "imdb_key '". $imdb_key ."' malformed.\n";
			
			return false;
		}
		
		$result = @mysql_query("SELECT `id`, `title` FROM `entity` WHERE `seoid` = '". mysql_real_escape_string($imdb_key) ."'") or print(__LINE__ .": ". mysql_error() . PHP_EOL);
		
		if(@mysql_num_rows($result) <= 0) {
			print "entity [seoid=". $imdb_key ."] not found.". PHP_EOL;
			
			return false;
		}
		
		$entity = mysql_fetch_assoc($result);
		
		unset($result);
		
		
		$contents = imdb_pull_url("http://m.imdb.com/name/". $imdb_key ."/");
		
		if(empty($contents) || !preg_match("#<meta\s*property=\"og\:image\"\s*content=\"([^\"]*)\"\s*\/>#si", $contents, $match)) {
			kill_imdb_key($imdb_key);
			
			return false;
		}
		
		
		
		if(empty($match[1]) || (false !== strpos($match[1], "imdb-share-logo"))) {
			kill_imdb_key($imdb_key);
			
			return true;
		}
		
		
		$image_data = clean_imdb_image_url($match[1]);
		
		if(empty($image_data['url'])) {
			kill_imdb_key($imdb_key);
			
			return true;
		}
		
		
		// find media
		$result = @mysql_query("SELECT `id` FROM `media` WHERE `source_url_hash` = '". mysql_real_escape_string(md5($image_data['url'])) ."'") or print(__LINE__ .": ". mysql_error() . PHP_EOL);
		
		if(@mysql_num_rows($result) > 0) {
			$db_data = @mysql_fetch_assoc($result);
			$media_id = $db_data['id'];
		} else {
			mysql_query("
				INSERT INTO `media`
				SET
					`status` = '0',
					`date_created` = NOW(),
					`type` = 'image',
					`description` = '". mysql_real_escape_string($entity['title']) ."',
					`source_type` = 'imdb.com',
					`source_raw` = '". mysql_real_escape_string(json_encode(array(
						'source'	=> "imdb.com",
						'caption'	=> $entity['title'],
						'copyright'	=> date("Y") ." IMDb.com",
						'url'		=> $image_data['url'],
						'imdb_id'	=> $image_data['imdb_id'],
						'imdb_key'	=> $image_data['imdb_key'],
					))) ."',
					`source_url` = '". mysql_real_escape_string($image_data['url']) ."',
					`source_url_hash` = '". mysql_real_escape_string(md5($image_data['url'])) ."'
			") or print(__LINE__ .": ". mysql_error() . PHP_EOL);
			
			$media_id = @mysql_insert_id();
		}
		
		if(empty($media_id)) {
			print "Media not found + unable to be created [". $image_url ."]". PHP_EOL;
			
			kill_imdb_key($imdb_key);
			
			return true;
		}
		
		$result = @mysql_query("SELECT `entity_id` FROM `entity_media` WHERE `entity_id` = '". mysql_real_escape_string($entity['id']) ."' AND `media_id` = '". mysql_real_escape_string($media_id) ."'") or print(__LINE__ .": ". mysql_error() . PHP_EOL);
		
		if(@mysql_num_rows($result) <= 0) {
			// link entity to media
			@mysql_query("
				INSERT INTO `entity_media`
				SET
					`entity_id` = '". mysql_real_escape_string($entity['id']) ."',
					`media_id` = '". mysql_real_escape_string($media_id) ."'
			") or print(__LINE__ .": ". mysql_error() . PHP_EOL);
		}
		
		@mysql_query("
			UPDATE `entity`
			SET
				`media_id` = '". mysql_real_escape_string($media_id) ."',
				`date_updated` = NOW()
			WHERE
				`id` = '". mysql_real_escape_string($entity['id']) ."'
		") or print(__LINE__ .": ". mysql_error() . PHP_EOL);
		
		print "set as ". $media_id ."...". PHP_EOL;
		
		return true;
	}
	
	$pheanstalk->watch( $job_tube_person_photos );
	
	while($job = $pheanstalk->reserve()) {
		$imdb_key = $job->getData();
		
		// delete job right away
		try { $pheanstalk->delete($job); } catch(Exception $e) {  }
		
		$pull_status = pull_imdb_key_photo( $imdb_key );
		
		// arbitrary wait
		if(true === $pull_status) {
			sleep(2);
		}
		
		unset($pull_status, $imdb_key, $job, $e);
	}
	
	die("done". PHP_EOL);