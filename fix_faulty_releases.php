<?php
	die;   // last run ~20130803
	require_once(__DIR__ ."/common.php");
	
	@mysql_select_db("jungle", $mysql);
	
	function pull_url($url) {
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
	
	function pullPage($entity_id, $imdb_key) {
		global $mysql;
		
		//http://www.imdb.com/title/tt0493464/releaseinfo
		
		$contents = pull_url("http://www.imdb.com/title/tt0493464/releaseinfo");
		
		if(empty($contents)) {
			return false;
		}
		
		$countries = array();
		
		// Releases
		if(preg_match("#<table\s*id=\"release_dates\"(?:[^>]*)>(.+?)</table>#si", $contents, $match)) {
			if(preg_match_all("#<a\s*href=\"/calendar/\?region=([A-Za-z]+)(?:[^\"]*)\"(?:[^>]*)>\s*([A-Za-z0-9\-\_ ]+)\s*</a>\s*</td>\s*<td\s*class=\"release_date\">(.+?)</td>\s*<td(?:[^>]*)>(.*?)</td>#si", $match[1], $matches)) {
				foreach(array_keys($matches[0]) as $match_key) {
					$data = array();
					
					$matches[1][ $match_key ] = trim(strtoupper($matches[1][ $match_key ]));
					$matches[2][ $match_key ] = trim($matches[2][ $match_key ]);
					$matches[3][ $match_key ] = trim(preg_replace("#\s{2,}#si", " ", strip_tags($matches[3][ $match_key ])));
					//$matches[4][ $match_key ] = trim(preg_replace("#\s{2,}#si", " ", str_replace(array("\r", "\n"), array("", " "), $matches[4][ $match_key ])));
					
					$data['country'] = $matches[2][ $match_key ];
					$data['country_code'] = $matches[1][ $match_key ];
					
					if(!empty($matches[3][ $match_key ])) {
						$country_timestamp = strtotime($matches[3][ $match_key ]);
						
						if(!empty($country_timestamp)) {
							$data['date'] = date("Y-m-d", $country_timestamp);
						}
					}
					
					$countries[] = $data;
					
					$data = null;
					unset($data);
				}
			}
		}
		
		if(!empty($countries)) {
			mysql_query("
				INSERT INTO `entity_meta`
				SET
					`entity_id` = '". mysql_real_escape_string($entity_id, $mysql) ."',
					`meta_id` = '86',
					`value` = '". mysql_real_escape_string(json_encode($countries)) ."'
			") or print(mysql_error($mysql));
		}
		
		mysql_query("
			UPDATE `tmp__imdb_faulty_releases`
			SET
				`done` = '1'
			WHERE
				`entity_id` = '". mysql_real_escape_string($entity_id, $mysql) ."'
		", $mysql);
	}
	
	$result = @mysql_query("SELECT * FROM `tmp__imdb_faulty_releases` WHERE `done` = '0'", $mysql);
	
	while($entry = @mysql_fetch_assoc($result)) {
		print "Working on ". $entry['entity_id'] ."... ";
		
		if(false === pullPage($entry['entity_id'], $entry['imdb_key'])) {
			print "Unable to pull entity #". $entry['entity_id'] ."... ";
		}
		
		print "done". PHP_EOL;
	}
