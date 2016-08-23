<?php
	die;
	
	set_time_limit(0);
	
	@mysql_connect("localhost", "", "") or die(mysql_error());
	@mysql_select_db("imdb") or die(mysql_error());
	
	@mysql_query("SET character_set_client = 'UTF8'") or die(mysql_error());
	@mysql_query("SET character_set_results = 'UTF8'") or die(mysql_error());
	@mysql_query("SET character_set_connection = 'UTF8'") or die(mysql_error());
	
	// SELECT * FROM `entity` WHERE `title` REGEXP '&#x[^ ]' LIMIT 30
	
	$per_step = 100;
	$step = 0;
	
	do {
		$result = @mysql_query("
			SELECT `id`, `type`, `title_cleaned`
			FROM `entity`
			WHERE `title_cleaned` REGEXP ' \\\([0-9]{4}\\/'
			LIMIT ". ($per_step * $step) .", ". $per_step
		) or die(__LINE__ .": ". mysql_error());
		
		$num_found = @mysql_num_rows($result);
		$step++;
		
		if(!empty($num_found)) {
			while($imdb = @mysql_fetch_assoc($result)) {
				$title_cleaned = $imdb['title_cleaned'];
				
				// (YYYY[/I+])
				$title_cleaned = preg_replace("#\s+\([1|2]\d{3}\/?[I|V|X]{0,}\)#si", "", $title_cleaned);
				$title_cleaned = trim($title_cleaned);
				
				
				print "title_cleaned[old] = ". $imdb['title_cleaned'] ."\n";
				print "title_cleaned[new] = ". $title_cleaned ."\n\n";
				
				mysql_query("
					UPDATE `entity`
					SET
						`title_cleaned` = '". mysql_real_escape_string($title_cleaned) ."'
					WHERE
						`id` = '". mysql_real_escape_string($imdb['id']) ."'
				") or print(mysql_error() ."\n");
				
				//print "old title: ". htmlentities($imdb['title_cleaned'], ENT_QUOTES, 'UTF-8') ."<br />\n";
				//print "new title: ". html_entity_decode($imdb['title_cleaned'], ENT_QUOTES, 'UTF-8') ."<br />\n";
				//print "<br />\n";
			}
		}
	} while(!empty($num_found));