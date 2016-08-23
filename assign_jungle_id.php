<?php
	set_time_limit(0);
	
	
	$mysql_imdb = @mysql_connect("localhost", "", "") or die(__LINE__ .": ". mysql_error());
	@mysql_select_db("imdb", $mysql_imdb) or die(mysql_error($mysql_imdb));
	
	@mysql_query("SET character_set_client = 'UTF8'", $mysql_imdb) or die(mysql_error($mysql_imdb));
	@mysql_query("SET character_set_results = 'UTF8'", $mysql_imdb) or die(mysql_error($mysql_imdb));
	@mysql_query("SET character_set_connection = 'UTF8'", $mysql_imdb) or die(mysql_error($mysql_imdb));
	
	
	$mysql_jungle = @mysql_connect("localhost", "", "") or die(__LINE__ .": ". mysql_error());
	@mysql_select_db("jungle", $mysql_jungle) or die(mysql_error($mysql_jungle));
	
	@mysql_query("SET character_set_client = 'UTF8'", $mysql_jungle) or die(mysql_error($mysql_jungle));
	@mysql_query("SET character_set_results = 'UTF8'", $mysql_jungle) or die(mysql_error($mysql_jungle));
	@mysql_query("SET character_set_connection = 'UTF8'", $mysql_jungle) or die(mysql_error($mysql_jungle));
	
	