<?php
	die;
	
	include("../config.php");
	
	set_time_limit(0);
	
	$result = @mysql_query("SELECT `id`, `imdbapi_url` FROM `imdbapi`");
	
	while($imdbapi = @mysql_fetch_assoc($result)) {
		$url = parse_url($imdbapi['imdbapi_url']);
		parse_str($url['query'], $params);
		
		
		@mysql_query("UPDATE `imdbapi` SET `movie_title` = '". mysql_escape_string(@$params['t']) ."', `movie_year` = '". mysql_escape_string($params['y']) ."' WHERE `id` = '". mysql_escape_string($imdbapi['id']) ."'") or die(mysql_error());
	}
