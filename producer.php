<?php
	require_once(__DIR__ ."/common.php");
	
	$num_jobs = 0;
	$result = @mysql_query("SELECT `seoid` FROM `entity` WHERE `title` = ''", $mysql);
	
	if(@mysql_num_rows($result) > 0) {
		while($imdb_row = @mysql_fetch_assoc($result)) {
			$pheanstalk->useTube( $job_tube )->put( $imdb_row['seoid'] );
			
			$num_jobs++;
		}
	} else {
		// Pull all movie ids from news area
		
		$news = file_get_contents("http://www.imdb.com/news/movie");
		
		if(preg_match_all("#href=\"\/(?:name|title)\/(nm|tt)([0-9]+)\/?\"#si", $news, $matches)) {
			foreach(array_keys($matches[0]) as $m_key) {
				$imdb_key = trim($matches[1][ $m_key ], "/\"") . trim($matches[2][ $m_key ], "/\"");
				
				if(!empty($imdb_key) && preg_match("#^[tt|nm|co|ch][0-9]{2,7}$#si", $imdb_key)) {
					$pheanstalk->useTube( $job_tube )->put( $imdb_key );
					
					$num_jobs++;
				}
			}
		}
	}
	
	print "Inserted ". number_format($num_jobs) ." new jobs.". PHP_EOL;
	
	die("done". PHP_EOL);