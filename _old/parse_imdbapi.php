<?php
	die;
	
	set_time_limit(0);
	
	mysql_connect("localhost", "", "");
	mysql_select_db("jungle");
	
	$result = @mysql_query("SELECT `imdbid`, `body` FROM `imdbapi` WHERE `response` != 'False'");
	
	while($imdbapi = @mysql_fetch_assoc($result)) {
		
		$array = json_decode($imdbapi['body'], true);
		
		if(!empty($array)) {
			foreach($array as $key => $value) {
				@mysql_query("
					INSERT INTO `meta_imdb`
					SET
						 `entity` = '". mysql_escape_string($imdbapi['imdbid']) ."'
						,`attribute` = '". mysql_escape_string(strtolower($key)) ."'
						,`value` = '". mysql_escape_string(trim($value)) ."'
				");
			}
		}
		
	}
