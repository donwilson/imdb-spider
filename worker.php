<?php
	set_time_limit(0);
	
	require_once(__DIR__ ."/common.php");
	require_once(__DIR__ ."/IMDb_Parser/classes/IMDb_Parser/IMDb_Parser.php");
	
	function should_skip_imdb_key($imdb_key) {
		return in_array(substr($imdb_key, 0, 2), array("ch", "co"));
	}
	
	function pull_imdb_key($imdb_key) {
		global $memcache, $mysql, $pheanstalk, $job_tube;
		
		$imdb_key = IMDb_Parser_IMDb_Parser::correct_imdb_key($imdb_key);
		
		// imdb_key match correct imdb key schema?
		if(false === $imdb_key) {
			print "[ERROR: imdb_key incorrectible]". PHP_EOL;
			return false;
		}
		
		// have we already pulled this imdb key before?
		if(false !== $memcache->get($imdb_key)) {
			print "[ERROR: imdb_key cache exists]". PHP_EOL;
			$memcache->delete("beanstalkd:". $imdb_key);
			return false;
		}
		
		// is there already another worker processing this item?
		if(false !== $memcache->get("active:". $imdb_key)) {
			$active_job_release_at = $memcache->get("active:". $imdb_key);
			
			if(!empty($active_job_release_at) && ($active_job_release_at > time())) {
				// previous active job has not been released yet, move to next job
				print "[ERROR: imdb_key still active]". PHP_EOL;
				return false;
			}
			
			$memcache->delete("active:". $imdb_key);
		}
		
		if(should_skip_imdb_key($imdb_key)) {
			// skip characters and companies
			$memcache->set($imdb_key, "-1", 0, 0);
			$memcache->delete("beanstalkd:". $imdb_key);
			
			print "[ERROR: imdb_key skipped]". PHP_EOL;
			return false;
		}
		
		// stay connected to mysql
		if(!@mysql_ping($mysql)) {
			@mysql_close($mysql);
			
			$mysql = @mysql_connect(DB_HOST, DB_USER, DB_PASS) or die("[ERROR: MySQL (". __LINE__ ."): ". mysql_error());
			@mysql_select_db(DB_NAME, $mysql) or die("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
		}
		
		
		// make sure this imdb key isn't already in the database
		$result = @mysql_query("
			SELECT `id`, `title`
			FROM `entity`
			WHERE
				`seoid` = '". mysql_real_escape_string($imdb_key, $mysql) ."'
		", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
		
		if(@mysql_num_rows($result) > 0) {
			$existing = @mysql_fetch_assoc($result);
			
			if("" !== trim($existing['title'])) {
				$memcache->set($imdb_key, $existing['id'], 0, 0);
				$memcache->delete("beanstalkd:". $imdb_key);
				$memcache->delete("active:". $imdb_key);
				
				print "[ERROR: imdb_key db exists]". PHP_EOL;
				
				return false;
			} else {
				$new_entity_id = $existing['id'];
				
				// set as being worked on now
				$memcache->set("active:". $imdb_key, time() + (60 * 60 * 1), 0, (60 * 60 * 1));
			}
		} else {
			@mysql_query("
				INSERT INTO `entity`
				SET
					`seoid`	= '". mysql_real_escape_string($imdb_key) ."',
					`title`	= '',
					`date_created`	= NOW(),
					`date_updated`	= NOW()
			", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
			
			$new_entity_id = @mysql_insert_id($mysql);
		}
		
		
		
		
		print "Working on #". $imdb_key ."...";
		
		
		// Process imdb_key ...
		$imdb_parser = new IMDb_Parser_IMDb_Parser($imdb_key);
		$imdb_data = $imdb_parser->parse();
		
		
		if(!empty($imdb_data[':redirect_imdb_key'])) {
			print "[REDIRECT: ". $imdb_key ." => ". $imdb_data[':redirect_imdb_key'] ."]". PHP_EOL;
			
			// IMDb key provided redirects to another key
			@mysql_query("
				UPDATE `entity`
				SET
					`title`			= '-2',
					`excerpt`		= '". mysql_real_escape_string("REDIRECT=". $imdb_data[':redirect_imdb_key']) ."',
					`date_updated`	= NOW()
				WHERE
					`id`	= '". mysql_real_escape_string($new_entity_id) ."'
			", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
			
			$memcache->set($imdb_key, $new_entity_id, 0, 0);
			$memcache->delete("beanstalkd:". $imdb_key);
			$memcache->delete("active:". $imdb_key);
			
			return true;
		}
		
		
		
		if(!isset($imdb_data['title']) || ("" === $imdb_data['title'])) {
			print "[ERROR: IMDB_Parser error: empty title]". PHP_EOL;
			
			// set err'd entity.title as '-1'
			@mysql_query("
				UPDATE `entity`
				SET
					`title`			= '-1',
					`date_updated`	= NOW()
				WHERE
					`id`	= '". mysql_real_escape_string($new_entity_id) ."'
			", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
			
			$memcache->delete("beanstalkd:". $imdb_key);
			$memcache->delete("active:". $imdb_key);
			
			return false;
		}
		
		
		//print_r($imdb_data);
		
		if(!empty($new_entity_id)) {
			// Process data
			@mysql_query("
				UPDATE `entity`
				SET
					`type`			= '". mysql_real_escape_string($imdb_data[':type']) ."',
					`title`			= '". mysql_real_escape_string($imdb_data['title']) ."',
					`date_updated`	= NOW()
				WHERE
					`id`	= '". mysql_real_escape_string($new_entity_id) ."'
			", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
			
			// Attached IMDB Keys
			if(!empty($imdb_data[':attached'])) {
				foreach($imdb_data[':attached'] as $attached_imdb_key) {
					if(($attached_imdb_key !== $imdb_key) && (false === $memcache->get($attached_imdb_key)) && (false === $memcache->get("beanstalkd:". $attached_imdb_key))) {
						$pheanstalk->useTube( $job_tube )->put( $attached_imdb_key );
						$memcache->set("beanstalkd:". $attached_imdb_key, "1");
					}
				}
				
				unset($imdb_data[':attached']);
			}
			
			
			if(("episode" === $imdb_data[':type']) && !empty($imdb_data[':parent'])) {
				@mysql_query("
					INSERT INTO `entity_connection`
					SET
						`connection`	= '". mysql_real_escape_string("tv-show-episode", $mysql) ."',
						`entity_from`	= '". mysql_real_escape_string($imdb_data[':parent'], $mysql) ."',
						`entity_to`		= '". mysql_real_escape_string($imdb_key, $mysql) ."'
				", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
			}
			
			
			
			
			// Keywords
			if(!empty($imdb_data['keywords'])) {
				@mysql_query("
					INSERT INTO `entity_meta`
					SET
						`entity_id`	= '". mysql_real_escape_string($new_entity_id, $mysql) ."',
						`meta_key`	= '". mysql_real_escape_string("keywords", $mysql) ."',
						`value`		= '". mysql_real_escape_string( json_encode($imdb_data['keywords']), $mysql) ."'
				", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
				
				unset($imdb_data['keywords']);
			}
			
			
			
			// Cast
			if(!empty($imdb_data['cast'])) {
				foreach($imdb_data['cast'] as $role => $cast) {
					if(empty($cast)) {
						continue;
					}
					
					foreach($cast as $cast_entry) {
						if(empty($cast_entry['person'][':imdb'])) {
							continue;
						}
						
						$value = array();
						
						if(!empty($cast_entry['characters'])) {
							$value['characters'] = $cast_entry['characters'];
						}
						
						if(!empty($cast_entry['roles'])) {
							$value['rows'] = $cast_entry['roles'];
						}
						
						@mysql_query("
							INSERT INTO `entity_connection`
							SET
								`connection`	= '". mysql_real_escape_string("cast-". str_replace(array(" ", "_"), "-", $role), $mysql) ."',
								`entity_from`	= '". mysql_real_escape_string($imdb_key, $mysql) ."',
								`entity_to`		= '". mysql_real_escape_string($cast_entry['person'][':imdb'], $mysql) ."',
								`value`			= ". (!empty($value)?"'". mysql_real_escape_string(json_encode($value), $mysql) ."'":"NULL") ."
						", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
						
						if(($imdb_key !== $cast_entry['person'][':imdb']) && (false === $memcache->get($cast_entry['person'][':imdb'])) && (false === $memcache->get("beanstalkd:". $cast_entry['person'][':imdb']))) {
							$pheanstalk->useTube( $job_tube )->put( $cast_entry['person'][':imdb'] );
							$memcache->set("beanstalkd:". $cast_entry['person'][':imdb'], "1");
						}
					}
				}
			}
			
			
			// Companies
			if(!empty($imdb_data['companies'])) {
				foreach($imdb_data['companies'] as $company_role => $companies) {
					if(!empty($companies)) {
						@mysql_query("
							INSERT INTO `entity_meta`
							SET
								`entity_id`	= '". mysql_real_escape_string($new_entity_id, $mysql) ."',
								`meta_key`	= '". mysql_real_escape_string("companies:". $company_role, $mysql) ."',
								`value`		= '". mysql_real_escape_string( json_encode($companies), $mysql) ."'
						", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
					}
				}
			}
			
			
			
			// Automated connection -- ['imdb_data_key' => "meta_key_prefix"]
			
			$automated_connections = array(
				'cast'			=> "cast",
				'companies'		=> "company",
			);
			
			foreach($automated_connections as $data_key => $meta_key_prefix) {
				if(!empty($imdb_data[ $data_key ])) {
					foreach($imdb_data[ $data_key ] as $id_key => $id_value) {
						if(empty($id_key) || empty($id_value)) {
							continue;
						}
						
						@mysql_query("
							INSERT INTO `entity_meta`
							SET
								`entity_id`	= '". mysql_real_escape_string($new_entity_id, $mysql) ."',
								`meta_key`	= '". mysql_real_escape_string($meta_key_prefix .":". $id_key, $mysql) ."',
								`value`		= '". mysql_real_escape_string( (is_array($id_value)?json_encode($id_value):$id_value), $mysql) ."'
						", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
					}
					
					unset($id_key, $id_value);
					unset($imdb_data[ $data_key ]);
				}
			}
			
			
			
			
			// Automated meta -- ['imdb_data_key' => "meta_key_prefix"]
			
			$automated_meta = array(
				'external_ids'	=> "external_id",
				'details'		=> "detail",
				'releases'		=> "release",
				'business'		=> "business",
			);
			
			foreach($automated_meta as $data_key => $meta_key_prefix) {
				if(!empty($imdb_data[ $data_key ])) {
					foreach($imdb_data[ $data_key ] as $id_key => $id_value) {
						if(empty($id_key) || empty($id_value)) {
							continue;
						}
						
						@mysql_query("
							INSERT INTO `entity_meta`
							SET
								`entity_id`	= '". mysql_real_escape_string($new_entity_id, $mysql) ."',
								`meta_key`	= '". mysql_real_escape_string($meta_key_prefix .":". $id_key, $mysql) ."',
								`value`		= '". mysql_real_escape_string( (is_array($id_value)?json_encode($id_value):$id_value), $mysql) ."'
						", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
					}
					
					unset($id_key, $id_value);
					unset($imdb_data[ $data_key ]);
				}
			}
			
			
			
			
			// Store media
			
			if(!empty($imdb_data['media'])) {
				foreach($imdb_data['media'] as $media) {
					if(empty($media['url'])) {
						continue;
					}
					
					// Check if media exists first
					$result = @mysql_query("
						SELECT `id`
						FROM `media`
						WHERE
							`source_url_hash` = '". mysql_real_escape_string(md5($media['url']), $mysql) ."'
					", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
					
					if(@mysql_num_rows($result) > 0) {
						$new_media = @mysql_fetch_assoc($result);
						
						if(!empty($new_media['id'])) {
							$new_media_id = $new_media['id'];
						}
					} else {
						@mysql_query("
							INSERT INTO `media`
							SET
								`date_created`		= NOW(),
								`type`				= 'image',
								`description`		= ". (!empty($media['caption'])?"'". mysql_real_escape_string($media['caption'], $mysql) ."'":"NULL") .",
								`source_type`		= '". mysql_real_escape_string("imdb.com", $mysql) ."',
								`source_raw`		= '". mysql_real_escape_string(json_encode($media), $mysql) ."',
								`source_url`		= '". mysql_real_escape_string($media['url'], $mysql) ."',
								`source_url_hash`	= '". mysql_real_escape_string(md5($media['url']), $mysql) ."'
						", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
						
						$new_media_id = @mysql_insert_id($mysql);
					}
					
					if(!empty($new_media_id)) {
						// Store media connection
						@mysql_query("
							INSERT INTO `entity_media`
							SET
								`entity_id` = '". mysql_real_escape_string($new_entity_id, $mysql) ."',
								`media_id` = '". mysql_real_escape_string($new_media_id, $mysql) ."'
						", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
						
						if(!empty($imdb_data['poster']) && ($imdb_data['poster'] === $media['url'])) {
							// Set primary media id for entity
							@mysql_query("
								UPDATE `entity`
								SET
									`media_id` = '". mysql_real_escape_string($new_media_id, $mysql) ."'
								WHERE
									`id` = '". mysql_real_escape_string($new_entity_id, $mysql) ."'
							", $mysql) or print("[ERROR: MySQL (". __LINE__ ."): ". mysql_error($mysql));
							
							unset($imdb_data['poster']);
						}
					}
					
					unset($new_media_id);
				}
				
				unset($media);
				unset($imdb_data['media']);
			}
			
			
			// Store in cache
			$memcache->set($imdb_key, $new_entity_id, 0, 0);
			$memcache->delete("beanstalkd:". $imdb_key);
			$memcache->delete("active:". $imdb_key);
		}
		
		print " done!". PHP_EOL;
		
		return true;
	}
	
	
	$pheanstalk->watch( $job_tube );
	
	while($job = $pheanstalk->reserve()) {
		print "Processing #". $job->getId() ."... ";
		
		$imdb_key = $job->getData();
		
		print "(key: ". $imdb_key .") ";
		
		$pull_status = pull_imdb_key( $imdb_key );
		
		print "done!". PHP_EOL;
		
		try { $pheanstalk->delete($job); } catch(Exception $e) {  }
		
		// arbitrary wait
		if(true === $pull_status) {
			sleep(2);
		}
		
		unset($pull_status, $imdb_key, $job, $e);
	}
	
	die("done". PHP_EOL);