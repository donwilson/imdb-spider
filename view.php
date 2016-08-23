<form method="get" action="view.php">
	<input type="text" name="query"<?=((isset($_REQUEST['query']) && strlen(trim($_REQUEST['query'])) > 0)?" value=\"". htmlentities(strtolower(trim($_REQUEST['query'])), ENT_COMPAT, "UTF-8") ."\"":"");?> placeholder="Search IMDb Crawl" /> <input type="submit" value="Search" />
</form>

<?php
	require_once(__DIR__ ."/common.php");
	require_once(realpath(__DIR__ ."/../../") ."/nice_print_r.php");
	
	$link = @mysql_connect(DB_HOST, DB_USER, DB_PASS) or die(mysql_error() . PHP_EOL);
	@mysql_select_db(DB_NAME, $link) or die(mysql_error($link) . PHP_EOL);
	
	@mysql_query("SET character_set_client = 'UTF8'", $link) or die(mysql_error($link));
	@mysql_query("SET character_set_results = 'UTF8'", $link) or die(mysql_error($link));
	@mysql_query("SET character_set_connection = 'UTF8'", $link) or die(mysql_error($link));
	
	if(!empty($_REQUEST['query'])) {
		if(preg_match("#^([nm|tt|ch|co]+)([0-9]{1,8})$#si", trim(strtolower($_REQUEST['query'])))) {
			print "Searching by seoid...<br />\n";
			$result = @mysql_query("SELECT * FROM `entity` WHERE `seoid` = '". mysql_real_escape_string(strtolower(trim($_REQUEST['query'])), $link) ."'", $link);
		} else {
			print "Searching by LIKE...<br />\n";
			$result = @mysql_query("SELECT * FROM `entity` WHERE `title` LIKE '%". mysql_real_escape_string($_REQUEST['query'], $link) ."%'", $link);
		}
		
		if(@mysql_num_rows($result) > 0) {
			while($entity = @mysql_fetch_assoc($result)) {
				$meta = array();
				$connections = array();
				$media = array();
				
				$result_meta = @mysql_query("SELECT * FROM `entity_meta` WHERE `entity_id` = '". mysql_real_escape_string($entity['id'], $link) ."'", $link);
				$result_connections = @mysql_query("SELECT * FROM `entity_connection` WHERE `entity_from` = '". mysql_real_escape_string($entity['seoid'], $link) ."' OR `entity_to` = '". mysql_real_escape_string($entity['seoid'], $link) ."'", $link);
				$result_media = @mysql_query("SELECT media.* FROM `entity_media` LEFT JOIN `media` ON media.id = entity_media.media_id WHERE `entity_id` = '". mysql_real_escape_string($entity['id'], $link) ."'", $link);
				
				print "<h2>". $entity['title'] ."</h2>\n";
				
				print "<pre>entity = ". print_r($entity, true) ."\n";
				
				if(mysql_num_rows($result_meta) > 0) {
					print "<h3>entity_meta</h3>\n";
					$metas = array();
					
					while($meta = @mysql_fetch_assoc($result_meta)) {
						json_decode($meta['value']);
						
						if(json_last_error() === JSON_ERROR_NONE) {
							$metas[ $meta['meta_key'] ][] = json_decode($meta['value'], true);
							
							//print htmlentities(print_r(json_decode($meta['value'], true), true), ENT_COMPAT, "UTF-8") ."\n";
						} else {
							//print htmlentities($meta['value'], ENT_COMPAT, "UTF-8") ."\n";
							$metas[ $meta['meta_key'] ][] = $meta['value'];
						}
					}
					
					new dBug($metas);
					
					unset($metas);
				} else {
					print "<p><i>Empty...</i></p>";
				}
				
				if(mysql_num_rows($result_connections) > 0) {
					print "<h3>entity_connections</h3>\n";
					
					while($connection = @mysql_fetch_assoc($result_connections)) {
						print "\n";
						print "Type: ". $connection['connection'] ."\n";
						print "From: <a href=\"?query=". $connection['entity_from'] ."\"". ($connection['entity_from'] !== $entity['seoid']?" style=\"font-weight: bold;\"":"") .">". $connection['entity_from'] ."</a>\n";
						print "To: <a href=\"?query=". $connection['entity_to'] ."\"". ($connection['entity_to'] !== $entity['seoid']?" style=\"font-weight: bold;\"":"") .">". $connection['entity_from'] ."</a>\n";
						
						if(strlen($connection['value']) > 0) {
							json_decode($connection['value']);
							
							if(json_last_error() === JSON_ERROR_NONE) {
								print htmlentities(print_r(json_decode($connection['value'], true), true), ENT_COMPAT, "UTF-8");
							} else {
								print htmlentities($connection['value'], ENT_COMPAT, "UTF-8") ."\n";
							}
						}
					}
				}
				
				if(mysql_num_rows($result_media) > 0) {
					print "<h3>media</h3>\n";
					
					while($media = @mysql_fetch_assoc($result_media)) {
						print print_r($media, true) ."\n";
					}
				}
				
				print "</pre>\n";
				
				print "<hr />\n";
			}
		} else {
			print "<i>Nothing found for '". htmlentities($_REQUEST['query']) ."'</i>\n";
			print "<hr />\n";
		}
	}
?>