<?php
	set_time_limit(0);
	
	require_once(__DIR__ ."/common.php");
	
	$pheanstalk->watch( $job_tube );
	
	while($job = $pheanstalk->reserve()) {
		print "Emptying #". $job->getId() .": ". $job->getData() ."... ";
		$pheanstalk->delete($job);
		print "done.". PHP_EOL;
	}
	
	die("done". PHP_EOL);