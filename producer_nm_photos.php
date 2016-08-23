<?php
	require_once(__DIR__ ."/common.php");
	
	$num_jobs = 0;
	$result = @mysql_query("SELECT `seoid` FROM `entity` WHERE `type` = 'person' AND `media_id` IS NULL", $mysql);
	
	if(@mysql_num_rows($result) > 0) {
		while($imdb_row = @mysql_fetch_assoc($result)) {
			$pheanstalk->useTube( $job_tube_person_photos )->put( $imdb_row['seoid'] );
			
			$num_jobs++;
		}
	}
	
	print "Inserted ". number_format($num_jobs) ." new jobs.". PHP_EOL;
	
	die("done". PHP_EOL);