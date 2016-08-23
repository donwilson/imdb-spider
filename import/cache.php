<?php
	class jungle_cache {
		static $cache = array();
		static $mysql = false;
		
		static function __callStatic($type, $args) {
			if(!self::$mysql) {
				self::$mysql = mysql_connect("localhost", "", "") or die(mysql_error(self::$mysql));
				mysql_select_db("jungle", self::$mysql) or die(mysql_error(self::$mysql));
			}
			
			if(!isset(self::$cache[ $type ])) {
				self::$cache[ $type ] = array();
			}
			
			if(!isset(self::$cache[ $type ][ $args[0] ])) {
				self::$cache[ $type ][ $args[0] ] = self::{"get_". $type}($args[0]);
			}
			
			return self::$cache[ $type ][ $args[0] ];
		}
		
		
		// get/set by type
		static function get_meta($key) {
			$key = trim(strtolower(trim(preg_replace(array("#[^A-Za-z0-9\_\:]+#si", "#[\:]{2,}#si"), ":", $key))), ":");
			$result = @mysql_query("SELECT `id` FROM `meta` WHERE `key` = '". mysql_real_escape_string($key, self::$mysql) ."'", self::$mysql) or die(mysql_error(self::$mysql));
			
			if(@mysql_num_rows($result) > 0) {
				$meta = @mysql_fetch_assoc($result);
				
				return $meta['id'];
			} else {
				@mysql_query("
					INSERT INTO `meta`
					SET
						`parent_id`	= NULL,
						`key`		= '". mysql_real_escape_string($key, self::$mysql) ."',
						`multiple`	= '0',
						`notes`		= NULL
				", self::$mysql) or die(mysql_error(self::$mysql));
				
				return @mysql_insert_id(self::$mysql);
			}
		}
		
		static function get_connection($key) {
			$key = trim(strtolower(trim(preg_replace(array("#[^A-Za-z0-9\_]+#si", "#[\.]{2,}#si"), ".", $key))), ".");
			$result = @mysql_query("SELECT `id` FROM `connection` WHERE `seoid` = '". mysql_real_escape_string($key, self::$mysql) ."'", self::$mysql) or die(mysql_error(self::$mysql));
			
			if(@mysql_num_rows($result) > 0) {
				$connection = @mysql_fetch_assoc($result);
				
				return $connection['id'];
			} else {
				@mysql_query("
					INSERT INTO `connection`
					SET
						`parent_id`	= NULL,
						`key`		= '". mysql_real_escape_string($key, self::$mysql) ."',
						`multiple`	= '0',
						`notes`		= NULL
				", self::$mysql) or die(mysql_error(self::$mysql));
				
				return @mysql_insert_id(self::$mysql);
			}
		}
	}
