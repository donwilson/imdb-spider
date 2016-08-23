<?php
	$meta_types = array(
		"business:admissions",
		"business:budget",
		"business:copyright_holder",
		"business:filming_dates",
		"business:gross",
		"business:opening_weekend",
		"business:production_dates",
		"business:rentals",
		"business:weekend_gross",
		"detail:birth_name",
		"detail:date_of_birth",
		"detail:date_of_death",
		"detail:episode_number",
		"detail:height",
		"detail:mini_biography",
		"detail:nickname",
		"detail:original_air_date",
		"detail:personal_quotes",
		"detail:salary",
		"detail:season_number",
		"detail:spouse",
		"detail:trade_mark",
		"detail:trivia",
		"detail:where_are_they_now",
		"external_id:boxofficemojo",
		"external_id:imcdb:movie",
		"external_id:joblo",
		"external_id:twitter",
		"external_id:url:",
		"external_id:wikipedia:id",
		"external_id:wikipedia:title",
		"keywords",
		"release:canada",
		"release:usa",
	);
	
	mysql_connect("localhost", "", "");
	mysql_select_db("imdb");
	
	print "<ul style=\"position: fixed; top: 0; left: 0; width: 200px; list-style: none; margin: 0; padding: 10px;\">\n";
	$last_base = false;
	
	foreach($meta_types as $meta_type) {
		$bases = explode(":", $meta_type);
		$this_base = array_shift($bases);
		
		if(false !== $last_base && $last_base !== $this_base) {
			print "<li>&nbsp;</li>\n";
		}
		
		$last_base = $this_base;
		
		print "<li><a href=\"#". $meta_type ."\">". $meta_type ."</a></li>\n";
	}
	
	print "</ul>\n";
	
	print "<div style=\"margin-left: 220px;\">\n";
	
	foreach($meta_types as $meta_type) {
		print "<a name=\"". $meta_type ."\"></a>\n";
		print "<h2>". $meta_type ."</h2>\n";
		
		$result = @mysql_query("SELECT * FROM `entity_meta` WHERE `meta_key` = '". mysql_real_escape_string($meta_type) ."' LIMIT 5");
		
		if(@mysql_num_rows($result) > 0) {
			while($meta = @mysql_fetch_assoc($result)) {
				print "<div style=\"background-color: #c0c0c0; padding: 5px;\">". htmlentities($meta['value']) ."</div>";
				
				json_decode($meta['value'], true);
				
				if(!is_numeric($meta['value']) && (json_last_error() === JSON_ERROR_NONE)) {
					print "<div style=\"background-color: #f0f0f0; padding: 5px;\"><pre style=\"margin: 0; padding: 0;\">". htmlentities( print_r(json_decode($meta['value'], true), true) ) ."</pre></div>";
				}
				
				print "<br />\n";
			}
		} else {
			print "<p><i>No examples found...</i></p>\n";
		}
		
		print "<hr />\n";
	}
	
	print "</div>\n";