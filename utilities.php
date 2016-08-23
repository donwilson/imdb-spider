<?php
	if(!function_exists("mkurl")) {
		function mkurl($string, $separator="-") {
			$string = str_replace(array("'", "’", "‘"), "", $string);   // non word separator characters
			
			$string = preg_replace('~[^\\pL\d]+~u', $separator, $string);
			
			// normalize
			$normalize = array(
				'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ä' => 'A', 'Æ' => 'AE', 'Ç' => 'C', 
				'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'Eth', 
				'Ñ' => 'N', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 
				'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 
				'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ä' => 'a', 'æ' => 'ae', 'ç' => 'c', 
				'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'eth', 
				'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø'=>'o', 
				'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 
				'ß' => 'sz', 'þ' => 'thorn', 'ÿ' => 'y',
			);
			
			//$string = strtr($string, $normalize);
			$new_string = "";
			$string_clear = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
			
			for ($i = 0; $i < strlen($string_clear); $i++) {
				$ch1 = $string_clear[$i];
				$ch2 = mb_substr($string, $i, 1);
				
				$new_string .= $ch1=='?'?$ch2:$ch1;
			}
			
			$string = $new_string;
			
			// lowercase
			$string = mb_strtolower($string);
			
			// remove unwanted characters
			//$string = preg_replace('~[^-\w]+~', '', $string);
			
			$string = preg_replace("#". preg_quote($separator, "#") ."{2,}#si", $separator, $string);
			$string = strtolower(trim($string, $separator));
			
			if(strlen($string) <= 0) {
				return false;
			}
			
			return $string;
		}
	}
	
	if(!function_exists("print_pre")) {
		function print_pre($var) {
			if(is_bool($var)) {
				$var = "(print_pre: BOOLEAN) ". ($var?"TRUE":"FALSE");
			} elseif(is_array($var) || is_object($var)) {
				$var = print_r($var, 1);
			}
			
			print "<pre>". htmlentities($var, ENT_COMPAT, mb_detect_encoding($var)) ."</pre>\n";
		}
	}
	
	if(!function_exists("get_entity_meta")) {
		function get_entity_meta($entity_id, $meta_key=false) {
			global $link;
			
			if(empty($entity_id)) {
				return false;
			}
			
			if((false === $meta_key) || (strlen($meta_key) <= 0)) {
				$meta = array();
				
				$result = @mysql_query("SELECT `meta_key`, `value` FROM `entity_meta` WHERE `entity_id` = '". mysql_real_escape_string($entity_id, $link) ."'", $link);
				
				if(@mysql_num_rows($result) > 0) {
					while($value = @mysql_fetch_assoc($result)) {
						$meta[$value['meta_key']] = $value['value'];
					}
				}
				
				return $meta;
			}
			
			
			$result = @mysql_query("SELECT `value` FROM `entity_meta` WHERE `entity_id` = '". mysql_real_escape_string($entity_id, $link) ."' AND `meta_key` = '". mysql_real_escape_string($meta_key, $link) ."'", $link);
			
			if(@mysql_num_rows($result) > 0) {
				$value = @mysql_fetch_assoc($result);
				
				return $value['value'];
			}
			
			return false;
		}
	}
	
	if(!function_exists("update_entity_meta")) {
		function update_entity_meta($entity_id, $meta_key, $value) {
			global $link;
			
			if(empty($entity_id) || (strlen($meta_key) <= 0)) {
				return false;
			}
			
			$result = @mysql_query("SELECT `id` FROM `entity_meta` WHERE `entity_id` = '". mysql_real_escape_string($entity_id, $link) ."' AND `meta_key` = '". mysql_real_escape_string($meta_key, $link) ."'", $link);
			
			if(@mysql_num_rows($result) > 0) {
				$existing = @mysql_fetch_assoc($result);
				
				return @mysql_query("UPDATE `entity_meta` SET `value` = '". mysql_real_escape_String($value, $link) ."' WHERE `id` = '". mysql_real_escape_string($existing['id'], $link) ."'", $link);
			}
			
			return @mysql_query("INSERT INTO `entity_meta` SET `entity_id` = '". mysql_real_escape_string($entity_id, $link) ."', `meta_key` = '". mysql_real_escape_string($meta_key, $link) ."', `value` = '". mysql_real_escape_string($value, $link) ."'", $link);
		}
	}
	
	if(!function_exists("insert_entity_meta")) {
		function insert_entity_meta($entity_id, $meta_key, $value) {
			global $link;
			
			if(empty($entity_id) || (strlen($meta_key) <= 0)) {
				return false;
			}
			
			return @mysql_query("INSERT INTO `entity_meta` SET `entity_id` = '". mysql_real_escape_string($entity_id, $link) ."', `meta_key` = '". mysql_real_escape_string($meta_key, $link) ."', `value` = '". mysql_real_escape_string($value, $link) ."'", $link);
		}
	}
	
	if(!function_exists("delete_entity_meta")) {
		function delete_entity_meta($entity_id, $meta_key) {
			global $link;
			
			if(empty($entity_id) || (strlen($meta_key) <= 0)) {
				return false;
			}
			
			return @mysql_query("DELETE FROM `entity_meta` WHERE `entity_id` = '". mysql_real_escape_string($entity_id, $link) ."' AND `meta_key` = '". mysql_real_escape_string($meta_key, $link) ."'", $link);
		}
	}