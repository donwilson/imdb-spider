<?php
	if(function_exists('mb_internal_encoding')): mb_internal_encoding("UTF-8"); endif;
	
	if(!defined('DB_HOST')):			define('DB_HOST',			"localhost");					endif;
	if(!defined('DB_USER')):			define('DB_USER',			"");						endif;
	if(!defined('DB_PASS')):			define('DB_PASS',			"");						endif;
	if(!defined('DB_NAME')):			define('DB_NAME',			"imdb");						endif;
	
	if(!defined('BEANSTALKD_HOST')):	define('BEANSTALKD_HOST',	"127.0.0.1");					endif;
	if(!defined('BEANSTALKD_PORT')):	define('BEANSTALKD_PORT',	"11300");						endif;
	
	if(!defined('DS')):					define('DS',				DIRECTORY_SEPARATOR);			endif;
	if(!defined('DIR_BASE')):			define('DIR_BASE',			__DIR__ . DS);					endif;
	if(!defined('DIR_CDN')):			define('DIR_CDN',			DIR_BASE ."..". DS ."cdn". DS);	endif;
	
	require_once(__DIR__ ."/utilities.php");
	require_once("/var/www/nodes/jungle.dev/includes/pheanstalk/pheanstalk_init.php");
	
	if(!class_exists("Pheanstalk_Pheanstalk")) {
		die("Pheanstalk class not found");
	}
	
	global $pheanstalk;
	$pheanstalk = new Pheanstalk_Pheanstalk(BEANSTALKD_HOST, BEANSTALKD_PORT);
	
	global $job_tube, $job_tube_person_photos;
	$job_tube = md5("jungledb:spider:imdb:by_key");
	$job_tube_person_photos = md5("jungledb:spider:imdb:person_photos:by_key");
	
	global $memcache;
	$memcache = new Memcache;
	
	$memcache->addServer("127.0.0.1", 11211);
	$memcache->addServer("192.168.0.5", 11211);
	
	global $mysql;
	$mysql = @mysql_connect(DB_HOST, DB_USER, DB_PASS) or die(mysql_error() . PHP_EOL);
	@mysql_select_db(DB_NAME, $mysql) or die(mysql_error($mysql) . PHP_EOL);
	
	@mysql_query("SET character_set_client = 'UTF8'", $mysql) or die(mysql_error($mysql));
	@mysql_query("SET character_set_results = 'UTF8'", $mysql) or die(mysql_error($mysql));
	@mysql_query("SET character_set_connection = 'UTF8'", $mysql) or die(mysql_error($mysql));