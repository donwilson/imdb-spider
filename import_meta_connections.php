<?php
	die;   // nothing left to do
	
	set_time_limit(0);
	ini_set('memory_limit', "1024M");
	
	@mysql_connect("localhost", "", "") or die(mysql_error());
	@mysql_select_db("imdb") or die(mysql_error());
	
	//$meta_key = "company:distributors";   // finished
	//$meta_key = "company:other_companies";   // finished at 201205171434CST
	//$meta_key = "company:production_companies";   // finished
	//$meta_key = "company:special_effects";   // finished at 201205171310CST
	
	$connection_key = str_replace(":", "-", $meta_key);
	
	$result = @mysql_query("
		SELECT
			entity_meta.id, entity_meta.entity_id, entity_meta.value, entity.seoid as `entity_from`
		FROM `entity_meta`
		LEFT JOIN `entity` ON entity.id = entity_meta.entity_id
		WHERE
			`meta_key` = '". mysql_real_escape_string($meta_key) ."'
	") or die(mysql_error() . PHP_EOL);
	
	if(@mysql_num_rows($result) > 0) {
		while($meta = @mysql_fetch_assoc($result)) {
			if(empty($meta['entity_from'])) {
				print "Unable to find entity.seoid for entity_id:". PHP_EOL . $meta['entity_id'] . PHP_EOL . PHP_EOL;
				
				continue;
			}
			
			$meta_companies = json_decode($meta['value'], true);
			
			if(JSON_ERROR_NONE !== json_last_error()) {
				print "Unable to decode:". PHP_EOL . $meta['value'] . PHP_EOL . PHP_EOL;
				
				continue;
			}
			
			foreach($meta_companies as $meta_company) {
				$company_key = trim(strtolower(@$meta_company[':imdb']));
				$company = trim(@$meta_company['company']);
				
				unset($meta_company[':imdb'], $meta_company['company']);
				
				if(strlen($company_key) <= 0 || strlen($company) <= 0) {
					print "Company details missing:". PHP_EOL . $meta['value'] . PHP_EOL . PHP_EOL;
					
					continue;
				}
				
				@mysql_query("
					INSERT IGNORE INTO `entity`
					SET
						`type` = 'company',
						`seoid` = '". mysql_real_escape_string($company_key) ."',
						`title` = '". mysql_real_escape_string($company) ."',
						`title_raw` = '". mysql_real_escape_string($company) ."',
						`date_created` = NOW(),
						`date_updated` = NOW()
				") or print(mysql_error() . PHP_EOL);
				
				@mysql_query("
					INSERT INTO `entity_connection`
					SET
						`connection` = '". mysql_real_escape_string($connection_key) ."',
						`entity_from` = '". mysql_real_escape_string($meta['entity_from']) ."',
						`entity_to` = '". mysql_real_escape_string($company_key) ."',
						`value` = ". (!empty($meta_company)?"'". mysql_real_escape_string(json_encode($meta_company)) ."'":"NULL") ."
				") or die(mysql_error() . PHP_EOL);
				
				unset($company_key, $company, $meta_company);
			}
			
			mysql_query("DELETE FROM `entity_meta` WHERE `id` = '". mysql_real_escape_string($meta['id']) ."'") or die(mysql_error() . PHP_EOL);
			
			unset($meta_companies);
		}
	}
	
	print PHP_EOL ."done.". PHP_EOL . PHP_EOL;
