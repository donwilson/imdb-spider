<?php
	class IMDb_Parser_IMDb_Parser {
		private $debug		= false;
		
		private $imdb_key_types = array(
			'tt'	=> "Title",
			'ch'	=> "Character",
			'nm'	=> "Name",
			'co'	=> "Company",
		);
		
		private $cargo		= array();
		
		private $imdb_key	= "";
		private $imdb_type	= "";
		private $imdb_id	= "";
		
		private $imdb_redirect_key	= false;
		
		public function __construct($key) {
			$this->imdb_key = $key;
		}
		
		public function parse() {
			/*
			$this->cargo[':imdb'] = array(
				'key'	=> $this->imdb_key(),
				'type'	=> $this->imdb_type(),
				'id'	=> $this->imdb_id(),
			);
			*/
			
			$this->cargo[':imdb']		= $this->imdb_key();
			$this->cargo[':type']		= false;	// set at top so it stays near the top of the cargo
			$this->cargo[':attached']	= array();
			
			switch($this->imdb_type()) {
				case 'tt':
					// movie/tv show/episode
					$this->do_movie_details();
					$this->do_movie_keywords();
					$this->do_movie_releases();
					$this->do_movie_business();
				//	$this->do_awards();
					$this->do_external_ids();
					$this->do_media_images();
					
					// ->cargo[':type'] is set in ->do_movie_details()
				break;
				
				case 'nm':
					// person
					$this->do_person_biography();
					$this->do_person_filmography();
					$this->do_awards();
					$this->do_external_ids();
					$this->do_media_images();
					
					$this->cargo[':type'] = "person";
				break;
				
				case 'ch':
					// character
					//$this->do_character_biography();
					// imdb doesn't do these for characters:
					//$this->do_external_ids();
					//$this->do_media_images();
					
					$this->cargo[':type'] = "character";
				break;
				
				case 'co':
					// company
					// imdb doesn't do this for companies:
					//$this->do_external_ids();
					//$this->do_media_images();
					
					$this->cargo[':type'] = "company";
				break;
			}
			
			
			if(!empty($this->imdb_redirect_key)) {
				$this->cargo[':redirect_imdb_key'] = $this->imdb_redirect_key;
			}
			
			
			if(!empty($this->cargo[':attached'])) {
				$this->cargo[':attached'] = array_unique($this->cargo[':attached']);
			} else {
				unset($this->cargo[':attached']);
			}
			
			return $this->cargo;
		}
		
		
		/*
			         __  _ ___ __  _          
			  __  __/ /_(_) (_) /_(_)__  _____
			 / / / / __/ / / / __/ / _ \/ ___/
			/ /_/ / /_/ / / / /_/ /  __(__  ) 
			\__,_/\__/_/_/_/\__/_/\___/____/  
			                                  
		*/
		
		function enable_debug() {
			$this->debug = true;
		}
		
		function disable_debug() {
			$this->debug = false;
		}
		
		function imdb_key() {
			return $this->imdb_key;
		}
		
		function imdb_type() {
			if(empty($this->imdb_type)) {
				$this->imdb_type = substr($this->imdb_key(), 0, 2);
			}
			
			return $this->imdb_type;
		}
		
		function imdb_type_url() {
			if(empty($this->imdb_type_url)) {
				switch($this->imdb_type()) {
					case 'tt':
						$this->imdb_type_url = "title";
					break;
					case 'nm':
						$this->imdb_type_url = "name";
					break;
					case 'co':
						$this->imdb_type_url = "company";
					break;
					case 'ch':
						$this->imdb_type_url = "character";
					break;
					default:
						$this->imdb_type_url = false;
				}
			}
			
			return $this->imdb_type_url;
		}
		
		function imdb_id() {
			if(empty($this->imdb_id)) {
				$this->imdb_id = ltrim(substr($this->imdb_key(), 2), "0");
			}
			
			return $this->imdb_id;
		}
		
		private function clean_image_url($url) {
			$urls = explode("/", $url);
			$filename = array_pop($urls);
			$bleh = explode(".", $filename);
			return implode("/", $urls) ."/". array_shift($bleh) .".". array_pop($bleh);
		}
		
		private function add_media($url, $category=false, $type=false, $extra=array()) {
			if(empty($url) || !preg_match("#^https?\:\/\/#si", $url)) {
				return;
			}
			
			if(!isset($this->cargo[':media'])) {
				$this->cargo[':media'] = array();
			}
			
			// clean images
			if(preg_match("#\.(jpe?g|gif|bmp|png)$#si", $url)) {
				$url = $this->clean_image_url($url);
			}
			
			$new_media = array(
				'url'	=> $url,
			);
			
			if(!empty($category)) {
				$new_media['category'] = $category;
			}
			
			if(!empty($type)) {
				$new_media['type'] = $type;
			}
			
			if(!empty($extra)) {
				$new_media = array_merge($new_media, $extra);
			}
			
			$this->cargo[':media'][] = $new_media;
		}
		
		private function pull_url($url, $source="source") {
			$ch = curl_init();
			
			curl_setopt_array($ch, array(
				// bool
				CURLOPT_AUTOREFERER		=> true,
				CURLOPT_HEADER			=> false,
				CURLOPT_RETURNTRANSFER	=> true,
				
				// int
				CURLOPT_TIMEOUT			=> 30,
				
				// string
				CURLOPT_REFERER			=> "http://www.google.com/search?q=z943jikzfd&ref=23u9rsjkef&skey=". md5($url),
				CURLOPT_URL				=> $url,
				CURLOPT_USERAGENT		=> "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/22.0.1207.1 Safari/537.1",
			));
			
			$contents = curl_exec($ch);
			
			curl_close($ch);
			
			if(true === $this->debug) {
				$this->add_media($url, $source, "html");
			}
			
			return $contents;
		}
		
		private function parse_external_id_url($url, $title=false) {
			// skip seemingly non-english or sites
			if(!preg_match("#\.(?:com|org|net)\/#si", $url)) {
				return;
			}
			
			// Wikipedia
			if(preg_match("#\.wikipedia\.org\/wiki\/([^\?\#]+)#si", $url, $match)) {
				return array(
					'type'	=> "wikipedia:title",							// wikipedia.title
					'value'	=> str_replace("_", " ", urldecode($match[1])),	// Grindhouse_%28film%29
				);
			}
			
			// BoxOfficeMojo
			if(preg_match("#boxofficemojo\.com(?:.+?)id=([A-Za-z0-9\-\_]+?)\.html?#si", $url, $match)) {
				return array(
					'type'	=> "boxofficemojo",
					'value'	=> $match[1],
				);
			}
			
			// JoBlo
			if(preg_match("#joblo\.com(?:.+?)id=([0-9]+)#si", $url, $match)) {
				return array(
					'type'	=> "joblo",
					'value'	=> $match[1],
				);
			}
			
			// IMCDB
			if(preg_match("#imcdb\.org\/([a-z]+?)\.php\?id=([0-9]+)#si", $url, $match)) {
				return array(
					'type'	=> "imcdb:". $match[1],
					'value'	=> $match[2],
				);
			}
			
			// couldn't determine
			return array(
				'type'	=> "url:",
				'value'	=> preg_replace("#^https?\:\/\/(www\.)?#si", "", $url),
				'multi'	=> true,	// this value is meant to be stored into an array of values
			);
		}
		
		private function compact_to_slug($string, $separator="_") {
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
			
			return $string;
		}
		
		private function clean_html($raw_html) {
			// specific IMDb encoding conversion
			
			$clean_html = html_entity_decode($raw_html, ENT_COMPAT, 'UTF-8');
			
			if(preg_match("#\&([A-Za-z0-9\#]+?);#si", $clean_html)) {
				$clean_html = preg_replace_callback("/(&#[0-9]+;)/", function($m) {
					return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
				}, $clean_html);
			}
			
			if(preg_match("#\&([A-Za-z0-9\#]+?);#si", $clean_html)) {
				$clean_html = htmlspecialchars_decode($clean_html);
			}
			
			if(preg_match("#\&([A-Za-z0-9\#]+?);#si", $clean_html)) {
				// forceful conversion
				$special_chars = array(
					'&#92;'		=> "'",		'&#x5C;'	=> "'",		'&#39;'		=> "'",		'&#x27;'	=> "'",
					'&nbsp;'	=> " ",		'&#160;'	=> " ",		'&#xA0;'	=> " ",		'&#32;'		=> " ",		'&#x20;'	=> " ",
					'&#33;'		=> "!",		'&#x21;'	=> "!",
					'&#35;'		=> "#",		'&#x23;'	=> "#",
					'&#36;'		=> "$",		'&#x24;'	=> "$",
					'&#37;'		=> "%",		'&#x25;'	=> "%",
					'&#x28;'	=> "(",		'&#40;'		=> "(",
					'&#x29;'	=> ")",		'&#41;'		=> ")",
					'&#x2A;'	=> "*",		'&#42;'		=> "*",
					'&#x2B;'	=> "+",		'&#43;'		=> "+",
					'&#x2C;'	=> ",",		'&#44;'		=> ",",
					'&#x2D;'	=> "-",		'&#45;'		=> "-",
					'&#x2E;'	=> ".",		'&#46;'		=> ".",
					'&#x2F;'	=> "/",		'&#47;'		=> "/",
					'&#x30;'	=> "0",		'&#48;'		=> "0",
					'&#x31;'	=> "1",		'&#49;'		=> "1",
					'&#x32;'	=> "2",		'&#50;'		=> "2",
					'&#x33;'	=> "3",		'&#51;'		=> "3",
					'&#x34;'	=> "4",		'&#52;'		=> "4",
					'&#x35;'	=> "5",		'&#53;'		=> "5",
					'&#x36;'	=> "6",		'&#54;'		=> "6",
					'&#x37;'	=> "7",		'&#55;'		=> "7",
					'&#x38;'	=> "8",		'&#56;'		=> "8",
					'&#x39;'	=> "9",		'&#57;'		=> "9",
					'&#x3A;'	=> ":",		'&#58;'		=> ":",
					'&#x3B;'	=> ";",		'&#59;'		=> ";",
					'&#61;'		=> "=",		'&#x3D;'	=> "=",
					'&#63;'		=> "?",		'&#x3F;'	=> "?",
					'&#64;'		=> "@",		'&#x40;'	=> "@",
					'&#65;'		=> "A",		'&#x41;'	=> "A",
					'&#66;'		=> "B",		'&#x42;'	=> "B",
					'&#67;'		=> "C",		'&#x43;'	=> "C",
					'&#68;'		=> "D",		'&#x44;'	=> "D",
					'&#69;'		=> "E",		'&#x45;'	=> "E",
					'&#70;'		=> "F",		'&#x46;'	=> "F",
					'&#71;'		=> "G",		'&#x47;'	=> "G",
					'&#72;'		=> "H",		'&#x48;'	=> "H",
					'&#73;'		=> "I",		'&#x49;'	=> "I",
					'&#74;'		=> "J",		'&#x4A;'	=> "J",
					'&#75;'		=> "K",		'&#x4B;'	=> "K",
					'&#76;'		=> "L",		'&#x4C;'	=> "L",
					'&#77;'		=> "M",		'&#x4D;'	=> "M",
					'&#78;'		=> "N",		'&#x4E;'	=> "N",
					'&#79;'		=> "O",		'&#x4F;'	=> "O",
					'&#80;'		=> "P",		'&#x50;'	=> "P",
					'&#81;'		=> "Q",		'&#x51;'	=> "Q",
					'&#82;'		=> "R",		'&#x52;'	=> "R",
					'&#83;'		=> "S",		'&#x53;'	=> "S",
					'&#84;'		=> "T",		'&#x54;'	=> "T",
					'&#85;'		=> "U",		'&#x55;'	=> "U",
					'&#86;'		=> "V",		'&#x56;'	=> "V",
					'&#87;'		=> "W",		'&#x57;'	=> "W",
					'&#88;'		=> "X",		'&#x58;'	=> "X",
					'&#89;'		=> "Y",		'&#x59;'	=> "Y",
					'&#90;'		=> "Z",		'&#x5A;'	=> "Z",
					'&#91;'		=> "[",		'&#x5B;'	=> "[",
					'&#93;'		=> "]",		'&#x5D;'	=> "]",
					'&#94;'		=> "^",		'&#x5E;'	=> "^",
					'&#95;'		=> "_",		'&#x5F;'	=> "_",
					'&#96;'		=> "`",		'&#x60;'	=> "`",
					'&#97;'		=> "a",		'&#x61;'	=> "a",
					'&#98;'		=> "b",		'&#x62;'	=> "b",
					'&#99;'		=> "c",		'&#x63;'	=> "c",
					'&#100;'	=> "d",		'&#x64;'	=> "d",
					'&#101;'	=> "e",		'&#x65;'	=> "e",
					'&#102;'	=> "f",		'&#x66;'	=> "f",
					'&#103;'	=> "g",		'&#x67;'	=> "g",
					'&#104;'	=> "h",		'&#x68;'	=> "h",
					'&#105;'	=> "i",		'&#x69;'	=> "i",
					'&#106;'	=> "j",		'&#x6A;'	=> "j",
					'&#107;'	=> "k",		'&#x6B;'	=> "k",
					'&#108;'	=> "l",		'&#x6C;'	=> "l",
					'&#109;'	=> "m",		'&#x6D;'	=> "m",
					'&#110;'	=> "n",		'&#x6E;'	=> "n",
					'&#111;'	=> "o",		'&#x6F;'	=> "o",
					'&#112;'	=> "p",		'&#x70;'	=> "p",
					'&#113;'	=> "q",		'&#x71;'	=> "q",
					'&#114;'	=> "r",		'&#x72;'	=> "r",
					'&#115;'	=> "s",		'&#x73;'	=> "s",
					'&#116;'	=> "t",		'&#x74;'	=> "t",
					'&#117;'	=> "u",		'&#x75;'	=> "u",
					'&#118;'	=> "v",		'&#x76;'	=> "v",
					'&#119;'	=> "w",		'&#x77;'	=> "w",
					'&#120;'	=> "x",		'&#x78;'	=> "x",
					'&#121;'	=> "y",		'&#x79;'	=> "y",
					'&#122;'	=> "z",		'&#x7A;'	=> "z",
					'&#123;'	=> "{",		'&#x7B;'	=> "{",
					'&#124;'	=> "|",		'&#x7C;'	=> "|",
					'&#125;'	=> "}",		'&#x7D;'	=> "}",
					'&#180;'	=> "´",		'&#xB4;'	=> "´",
					'&#127;'	=> "",		'&#x7F;'	=> "",
					'&#128;'	=> "€",		'&#x80;'	=> "€",
					'&#131;'	=> "ƒ",		'&#x83;'	=> "ƒ",
					'&#133;'	=> "…",		'&#x85;'	=> "…",
					'&#136;'	=> "ˆ",		'&#x88;'	=> "ˆ",
					'&#138;'	=> "Š",		'&#x8A;'	=> "Š",
					'&#140;'	=> "Œ",		'&#x8C;'	=> "Œ",
					'&#149;'	=> "•",		'&#x95;'	=> "•",
					'&#154;'	=> "š",		'&#x9A;'	=> "š",
					'&rsaquo;'	=> "›",		'&#155;'	=> "›",
					'&#156;'	=> "œ",		'&#x9C;'	=> "œ",
					'&#158;'	=> "ž",		'&#x9E;'	=> "ž",
					'&#142;'	=> "Ž",		'&#x8E;'	=> "Ž",
					'&quot;'	=> '"',		'&#34;'		=> '"',		'&#x22;'	=> '"',
					'&amp;'		=> "&",		'&#38;'		=> "&",		'&#x26;'	=> "&",
					'&lt;'		=> "<",		'&#60;'		=> "<",		'&#x3C;'	=> "<",
					'&gt;'		=> ">",		'&#62;'		=> ">",		'&#x3E;'	=> ">",
					'&tilde;'	=> "~",		'&#126;'	=> "~",		'&#x7E;'	=> "~",
					'&sbquo;'	=> "‚",		'&#130;'	=> "‚",		'&#x82;'	=> "‚",
					'&dbquo;'	=> "„",		'&#132;'	=> "„",		'&#x84;'	=> "„",
					'&dagger;'	=> "†",		'&#134;'	=> "†",		'&#x86;'	=> "†",
					'&Dagger;'	=> "‡",		'&#135;'	=> "‡",		'&#x87;'	=> "‡",
					'&permil;'	=> "‰",		'&#137;'	=> "‰",		'&#x89;'	=> "‰",
					'&lsaquo;'	=> "‹",		'&#139;'	=> "‹",		'&#x8B;'	=> "‹",
					'&lsquo;'	=> "‘",		'&#145;'	=> "‘",		'&#x91;'	=> "‘",
					'&rsquo;'	=> "’",		'&#146;'	=> "’",		'&#x92;'	=> "’",
					'&ldquo;'	=> "“",		'&#147;'	=> "“",		'&#x93;'	=> "“",
					'&rdquo;'	=> "”",		'&#148;'	=> "”",		'&#x94;'	=> "”",
					'&ndash;'	=> "–",		'&#150;'	=> "–",		'&#x96;'	=> "–",
					'&mdash;'	=> "—",		'&#151;'	=> "—",		'&#x97;'	=> "—",
					'&tilde;'	=> "˜",		'&#152;'	=> "˜",		'&#x98;'	=> "˜",
					'&trade;'	=> "™",		'&#153;'	=> "™",		'&#x99;'	=> "™",
					'&Yuml;'	=> "Ÿ",		'&#159;'	=> "Ÿ",		'&#x9F;'	=> "Ÿ",
					'&iexcl;'	=> "¡",		'&#161;'	=> "¡",		'&#xA1;'	=> "¡",
					'&cent;'	=> "¢",		'&#162;'	=> "¢",		'&#xA2;'	=> "¢",
					'&pound;'	=> "£",		'&#163;'	=> "£",		'&#xA3;'	=> "£",
					'&curren;'	=> "¤",		'&#164;'	=> "¤",		'&#xA4;'	=> "¤",
					'&yen;'		=> "¥",		'&#165;'	=> "¥",		'&#xA5;'	=> "¥",
					'&brvbar;'	=> "¦",		'&#166;'	=> "¦",		'&#xA6;'	=> "¦",
					'&sect;'	=> "§",		'&#167;'	=> "§",		'&#xA7;'	=> "§",
					'&uml;'		=> "¨",		'&#168;'	=> "¨",		'&#xA8;'	=> "¨",
					'&copy;'	=> "©",		'&#169;'	=> "©",		'&#xA9;'	=> "©",
					'&ordf;'	=> "ª",		'&#170;'	=> "ª",		'&#xAA;'	=> "ª",
					'&laquo;'	=> "«",		'&#171;'	=> "«",		'&#xAB;'	=> "«",
					'&not;'		=> "¬",		'&#172;'	=> "¬",		'&#xAC;'	=> "¬",
					'&shy;'		=> " ",		'&#173;'	=> " ",		'&#xAD;'	=> " ",
					'&reg;'		=> "®",		'&#174;'	=> "®",		'&#xAE;'	=> "®",
					'&macr;'	=> "¯",		'&#175;'	=> "¯",		'&#xAF;'	=> "¯",
					'&deg;'		=> "°",		'&#176;'	=> "°",		'&#xB0;'	=> "°",
					'&plusmn;'	=> "±",		'&#177;'	=> "±",		'&#xB1;'	=> "±",
					'&sup2;'	=> "²",		'&#178;'	=> "²",		'&#xB2;'	=> "²",
					'&sup3;'	=> "³",		'&#179;'	=> "³",		'&#xB3;'	=> "³",
					'&micro;'	=> "µ",		'&#181;'	=> "µ",		'&#xB5;'	=> "µ",
					'&para;'	=> "¶",		'&#182;'	=> "¶",		'&#xB6;'	=> "¶",
					'&middot;'	=> "·",		'&#183;'	=> "·",		'&#xB7;'	=> "·",
					'&cedil;'	=> "¸",		'&#184;'	=> "¸",		'&#xB8;'	=> "¸",
					'&sup1;'	=> "¹",		'&#185;'	=> "¹",		'&#xB9;'	=> "¹",
					'&ordm;'	=> "º",		'&#186;'	=> "º",		'&#xBA;'	=> "º",
					'&raquo;'	=> "»",		'&#187;'	=> "»",		'&#xBB;'	=> "»",
					'&frac14;'	=> "¼",		'&#188;'	=> "¼",		'&#xBC;'	=> "¼",
					'&frac12;'	=> "½",		'&#189;'	=> "½",		'&#xBD;'	=> "½",
					'&frac34;'	=> "¾",		'&#190;'	=> "¾",		'&#xBE;'	=> "¾",
					'&iquest;'	=> "¿",		'&#191;'	=> "¿",		'&#xBF;'	=> "¿",
					'&Agrave;'	=> "À",		'&#192;'	=> "À",		'&#xC0;'	=> "À",
					'&Aacute;'	=> "Á",		'&#193;'	=> "Á",		'&#xC1;'	=> "Á",
					'&Acirc;'	=> "Â",		'&#194;'	=> "Â",		'&#xC2;'	=> "Â",
					'&Atilde;'	=> "Ã",		'&#195;'	=> "Ã",		'&#xC3;'	=> "Ã",
					'&Auml;'	=> "Ä",		'&#196;'	=> "Ä",		'&#xC4;'	=> "Ä",
					'&Aring;'	=> "Å",		'&#197;'	=> "Å",		'&#xC5;'	=> "Å",
					'&AElig;'	=> "Æ",		'&#198;'	=> "Æ",		'&#xC6;'	=> "Æ",
					'&Ccedil;'	=> "Ç",		'&#199;'	=> "Ç",		'&#xC7;'	=> "Ç",
					'&Egrave;'	=> "È",		'&#200;'	=> "È",		'&#xC8;'	=> "È",
					'&Eacute;'	=> "É",		'&#201;'	=> "É",		'&#xC9;'	=> "É",
					'&Ecirc;'	=> "Ê",		'&#202;'	=> "Ê",		'&#xCA;'	=> "Ê",
					'&Euml;'	=> "Ë",		'&#203;'	=> "Ë",		'&#xCB;'	=> "Ë",
					'&Igrave;'	=> "Ì",		'&#204;'	=> "Ì",		'&#xCC;'	=> "Ì",
					'&Iacute;'	=> "Í",		'&#205;'	=> "Í",		'&#xCD;'	=> "Í",
					'&Icirc;'	=> "Î",		'&#206;'	=> "Î",		'&#xCE;'	=> "Î",
					'&Iuml;'	=> "Ï",		'&#207;'	=> "Ï",		'&#xCF;'	=> "Ï",
					'&ETH;'		=> "Ð",		'&#208;'	=> "Ð",		'&#xD0;'	=> "Ð",
					'&Ntilde;'	=> "Ñ",		'&#209;'	=> "Ñ",		'&#xD1;'	=> "Ñ",
					'&Ograve;'	=> "Ò",		'&#210;'	=> "Ò",		'&#xD2;'	=> "Ò",
					'&Oacute;'	=> "Ó",		'&#211;'	=> "Ó",		'&#xD3;'	=> "Ó",
					'&Ocirc;'	=> "Ô",		'&#212;'	=> "Ô",		'&#xD4;'	=> "Ô",
					'&Otilde;'	=> "Õ",		'&#213;'	=> "Õ",		'&#xD5;'	=> "Õ",
					'&Ouml;'	=> "Ö",		'&#214;'	=> "Ö",		'&#xD6;'	=> "Ö",
					'&times;'	=> "×",		'&#215;'	=> "×",		'&#xD7;'	=> "×",
					'&Oslash;'	=> "Ø",		'&#216;'	=> "Ø",		'&#xD8;'	=> "Ø",
					'&Ugrave;'	=> "Ù",		'&#217;'	=> "Ù",		'&#xD9;'	=> "Ù",
					'&Uacute;'	=> "Ú",		'&#218;'	=> "Ú",		'&#xDA;'	=> "Ú",
					'&Ucirc;'	=> "Û",		'&#219;'	=> "Û",		'&#xDB;'	=> "Û",
					'&Uuml;'	=> "Ü",		'&#220;'	=> "Ü",		'&#xDC;'	=> "Ü",
					'&Yacute;'	=> "Ý",		'&#221;'	=> "Ý",		'&#xDD;'	=> "Ý",
					'&THORN;'	=> "Þ",		'&#222;'	=> "Þ",		'&#xDE;'	=> "Þ",
					'&szlig;'	=> "ß",		'&#223;'	=> "ß",		'&#xDF;'	=> "ß",
					'&agrave;'	=> "à",		'&#224;'	=> "à",		'&#xE0;'	=> "à",
					'&aacute;'	=> "á",		'&#225;'	=> "á",		'&#xE1;'	=> "á",
					'&acirc;'	=> "â",		'&#226;'	=> "â",		'&#xE2;'	=> "â",
					'&atilde;'	=> "ã",		'&#227;'	=> "ã",		'&#xE3;'	=> "ã",
					'&auml;'	=> "ä",		'&#228;'	=> "ä",		'&#xE4;'	=> "ä",
					'&aring;'	=> "å",		'&#229;'	=> "å",		'&#xE5;'	=> "å",
					'&aelig;'	=> "æ",		'&#230;'	=> "æ",		'&#xE6;'	=> "æ",
					'&ccedil;'	=> "ç",		'&#231;'	=> "ç",		'&#xE7;'	=> "ç",
					'&egrave;'	=> "è",		'&#232;'	=> "è",		'&#xE8;'	=> "è",
					'&eacute;'	=> "é",		'&#233;'	=> "é",		'&#xE9;'	=> "é",
					'&ecirc;'	=> "ê",		'&#234;'	=> "ê",		'&#xEA;'	=> "ê",
					'&euml;'	=> "ë",		'&#235;'	=> "ë",		'&#xEB;'	=> "ë",
					'&igrave;'	=> "ì",		'&#236;'	=> "ì",		'&#xEC;'	=> "ì",
					'&iacute;'	=> "í",		'&#237;'	=> "í",		'&#xED;'	=> "í",
					'&icirc;'	=> "î",		'&#238;'	=> "î",		'&#xEE;'	=> "î",
					'&iuml;'	=> "ï",		'&#239;'	=> "ï",		'&#xEF;'	=> "ï",
					'&eth;'		=> "ð",		'&#240;'	=> "ð",		'&#xF0;'	=> "ð",
					'&ntilde;'	=> "ñ",		'&#241;'	=> "ñ",		'&#xF1;'	=> "ñ",
					'&ograve;'	=> "ò",		'&#242;'	=> "ò",		'&#xF2;'	=> "ò",
					'&oacute;'	=> "ó",		'&#243;'	=> "ó",		'&#xF3;'	=> "ó",
					'&ocirc;'	=> "ô",		'&#244;'	=> "ô",		'&#xF4;'	=> "ô",
					'&otilde;'	=> "õ",		'&#245;'	=> "õ",		'&#xF5;'	=> "õ",
					'&ouml;'	=> "ö",		'&#246;'	=> "ö",		'&#xF6;'	=> "ö",
					'&divide;'	=> "÷",		'&#247;'	=> "÷",		'&#xF7;'	=> "÷",
					'&oslash;'	=> "ø",		'&#248;'	=> "ø",		'&#xF8;'	=> "ø",
					'&ugrave;'	=> "ù",		'&#249;'	=> "ù",		'&#xF9;'	=> "ù",
					'&uacute;'	=> "ú",		'&#250;'	=> "ú",		'&#xFA;'	=> "ú",
					'&ucirc;'	=> "û",		'&#251;'	=> "û",		'&#xFB;'	=> "û",
					'&uuml;'	=> "ü",		'&#252;'	=> "ü",		'&#xFC;'	=> "ü",
					'&yacute;'	=> "ý",		'&#253;'	=> "ý",		'&#xFD;'	=> "ý",
					'&thorn;'	=> "þ",		'&#254;'	=> "þ",		'&#xFE;'	=> "þ",
					'&yuml;'	=> "ÿ",		'&#255;'	=> "ÿ",		'&#xFF;'	=> "ÿ",
					
					// unknown characters
					'&#129;'	=> "",		'&#x81;'	=> "",		'&#144;'	=> "",		'&#x90;'	=> "",
					'&#143;'	=> "",		'&#x8F;'	=> "",		'&#157;'	=> "",		'&#x9D;'	=> "",
					'&#141;'	=> "",		'&#x8D;'	=> "",
				);
				
				$clean_html = strtr($clean_html, $special_chars);
			}
			
			return $clean_html;
		}
		
		private function extract_inline_notes(&$string) {
			if(preg_match_all("#\(([^\)]+?)\)#si", $string, $match)) {
				$notes = array();
				
				foreach($match[1] as $note) {
					// skip imdb-specific notes
					if(preg_match("#imdb#si", $note)) {
						continue;
					}
					
					$notes[] = trim( $this->clean_html($note) );
				}
				
				$string = trim(preg_replace("#\s*?\(([^\)]+?)\)#si", "", $string));
				
				return $notes;
			}
			
			return false;
		}
		
		static function correct_imdb_key($raw_key) {
			// nm123 -> nm0000123
			
			if(empty($raw_key) || !preg_match("#^([a-z]{2})([0-9]{1,7})$#si", $raw_key, $match)) {
				return false;
			}
			
			return strtolower(trim($match[1])) . str_pad($match[2], 7, "0", STR_PAD_LEFT);
		}
		
		
		/*
			  __/|_
			 |    /
			/_ __| 
			 |/    
			       
		*/
		
		// * - Images
		private function do_media_images() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			// http://m.imdb.com/name/json/nm0001803/mediaindex?photoId=MV5BMTU3MzMxMjYzNF5BMl5BanBnXkFtZTcwODQ4ODk0Nw&width=1920
			// http://m.imdb.com/name/json/nm0001803/mediaindex?photoId=&width=1920
			
			$contents_url = "http://m.imdb.com/". $this->imdb_type_url() ."/json/". $this->imdb_key() ."/mediaindex?photoId=&width=9999";
			$contents = $this->pull_url($contents_url, "source:media");
			
			if(empty($contents)) {
				return;
			}
			
			$media = json_decode($contents, true);
			
			if(JSON_ERROR_NONE !== json_last_error()) {
				return;
			}
			
			if(empty($media['photos'])) {
				return;
			}
			
			$images = array();
			
			foreach($media['photos'] as $photo) {
				if(empty($photo['url'])) {
					continue;
				}
				
				$photo['url'] = $this->clean_image_url($photo['url']);
				
				//if(empty($photo['width'])) {
					unset($photo['width']);
				//}
				
				//if(empty($photo['height'])) {
					unset($photo['height']);
				//}
				
				$images[] = $photo;
			}
			
			/*
			// old method:
			$contents_url = "http://m.imdb.com/". $this->imdb_type_url() ."/". $this->imdb_key() ."/mediaindex";
			$contents = $this->pull_url($contents_url, "source:media");
			
			if(empty($contents)) {
				return;
			}
			
			if(!preg_match_all("#\?photoId=(?:[^\"]+?)\">\s*<img src=\"([^\"]+?)\"#si", $contents, $matches)) {
				return;
			}
			
			$images = array();
			
			foreach(array_keys($matches[0]) as $mkey) {
				$image_url = trim($matches[1][ $mkey ], " \"");
				
				if(empty($image_url)) {
					continue;
				}
				
				//$image_url = preg_replace("#\._(?:.+?)_\.(jpe?g|gif|png)$#si", ".\\1", $image_url);
				$image_url = $this->clean_image_url($image_url);
				
				if(!in_array($image_url, $images)) {
					$images[] = $image_url;
				}
			}
			*/
			
			if(empty($images)) {
				return;
			}
			
			foreach(array_keys($images) as $key) {
				$bleh = explode("/", $images[$key]['url']);
				$bleh2 = array_pop($bleh);
				$bleh3 = explode(".", $bleh2);
				
				$base64_encoded = array_shift($bleh3);
				$base64 = explode("^A", base64_decode($base64_encoded));
				
				if(!empty($base64)) {
					$images[$key]['imdb_id'] = $base64[1];
					$images[$key]['imdb_key'] = $base64[4];
				}
			}
			
			$this->cargo['media'] = $images;
		}
		
		// * - External IDs
		private function do_external_ids() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			// http://www.imdb.com/title/tt0462322/miscsites
			
			$contents_url = "http://www.imdb.com/". $this->imdb_type_url() ."/". $this->imdb_key() ."/miscsites";
			$contents = $this->pull_url($contents_url, "source:external_ids");
			
			$external_ids = array();
			
			// release dates per country
			if(preg_match_all("#<li>\s*<a href=\"([^\"]+?)\"(?:\s+rel=\"nofollow\")?>([^<]+?)</a>\s*</li>#si", $contents, $matches)) {
				foreach(array_keys($matches[0]) as $mkey) {
					$external_url = $matches[1][ $mkey ];
					$external_title = trim( $this->clean_html($matches[2][ $mkey ]) );
					
					$external_id = $this->parse_external_id_url($external_url, $external_title);
					
					if(!empty($external_id)) {
						if(!empty($external_id['multi'])) {
							if(!isset($external_ids[ $external_id['type'] ])) {
								$external_ids[ $external_id['type'] ] = array();
							}
							
							$external_ids[ $external_id['type'] ][] = $external_id['value'];
						} else {
							// overwrites any previous value
							$external_ids[ $external_id['type'] ] = $external_id['value'];
						}
					}
				}
			}
			
			if(!empty($external_ids)) {
				if(!isset($this->cargo['external_ids'])) {
					$this->cargo['external_ids'] = array();
				}
				
				$this->cargo['external_ids'] = array_merge($this->cargo['external_ids'], $external_ids);
			}
		}
		
		// * - Awards
		private function do_awards() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			// http://www.imdb.com/title/tt0462322/awards
			
			$contents_url = "http://www.imdb.com/". $this->imdb_type_url() ."/". $this->imdb_key() ."/awards";
			$contents = $this->pull_url($contents_url, "source:awards");
			
			$ceremonies = array();
			
			// get all ceremonies
			if(!preg_match("#<div id=\"tn15content\">(.+?)<\/table>#si", $contents, $container_match)) {
				return;
			}
			
			if(!preg_match("#<table(.+?)<\/table>#si", $container_match[0], $table)) {
				return;
			}
			
			$award_shows_raw = preg_split("#<tr>\s*<td(?:[^>]*)?colspan=\"4\"(?:[^>]*)?>\s*&nbsp;\s*<\/td>\s*<\/tr>#si", $table[0], -1, PREG_SPLIT_NO_EMPTY);
			array_pop($award_shows_raw);
			
			if(empty($award_shows_raw)) {
				return;
			}
			
			foreach($award_shows_raw as $award_show_raw) {
				$ceremony = array();
				
				if(preg_match("#<big>\s*<a href=\"\/Sections\/Awards\/([^\/]+?)\/?\"(?:[^>]*)?>([^<]+?)<\/a>\s*</big>#si", $award_show_raw, $match)) {
					$ceremony[':imdb'] = trim($match[1], "/");
					$ceremony['title'] = trim(htmlentities($match[2]));
				}
				
				$ceremony['nominations'] = array();
				
				// http://www.imdb.com/title/tt0081505/awards
				
				// ugh...
				
				if(!empty($ceremony[':imdb']) && !empty($ceremony['title']) && !empty($ceremony['nominations'])) {
					$ceremonies[] = $ceremony;
				}
			}
			
			
			
			
			if(!empty($ceremonies)) {
				$this->cargo['awards'] = $ceremonies;
			}
		}
		
		
		
		/*
			  _______ __  __         
			 /_  __(_) /_/ /__  _____
			  / / / / __/ / _ \/ ___/
			 / / / / /_/ /  __(__  ) 
			/_/ /_/\__/_/\___/____/  
		*/
		
		// Movies - Details
		private function do_movie_details() {
			// http://www.imdb.com/title/tt0462322/combined
			
			$contents_url = "http://www.imdb.com/title/". $this->imdb_key() ."/combined";
			$contents = $this->pull_url($contents_url, "source:movie:biography");
			
			// IMDb key links to somewhere else?
			if(preg_match("#<H1>\s*Moved Permanently\s*<\/H1>\s+The document has moved\s+<A HREF=\"http\:\/\/(?:www\.)?imdb\.com\/title/tt([0-9]+)\/(?:combined\/?)?\">\s*here\s*<\/A>#si", $contents, $match)) {
				$this->imdb_redirect_key = $this->correct_imdb_key("tt". str_pad(ltrim($match[1], "0"), 7, "0", STR_PAD_LEFT));
				
				return;
			}
			
			
			// Everything okay?
			if(preg_match("#<title>\s*404 Error\s+\-\s+IMDb\s*<\/title>#si", $contents)) {
				return false;
			}
			
			
			// Title
			if(preg_match("#<title>([^<]+)<\/title>#si", $contents, $match)) {
				$this->cargo['title'] = trim($match[1]);
			}
			
			
			// Type of Title (not on /combined :()
			//if(preg_match("#<meta\s+property=\'og\:type\'\s+content=\"video\.([^\"]+)\"\s*\/>#si", $contents, $match)) {
			//	$this->cargo[':type'] = $this->compact_to_slug($match[1]);
			//} else {
			//	$this->cargo[':type'] = $this->compact_to_slug("movie");
			//}
			
			$details = (isset($this->cargo['details'])?$this->cargo['details']:array());
			
			if(preg_match("#<div class=\"info\">\s+<h5>\s*Original Air Date\:\s*</h5>\s+<div class=\"info\-content\">(.+?)<\/div>#si", $contents, $match)) {
				// TV Show Episode
				$this->cargo[':type'] = $this->compact_to_slug("episode");
				
				$match[1] = trim($match[1]);
				
				// Original Air Date
				if(preg_match("#([0-9]{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+([0-9]{4})#si", $match[1], $date_match)) {
					$details[ $this->compact_to_slug("Original Air Date") ] = date("Y-m-d", strtotime($date_match[2] ." ". $date_match[1] ." ". $date_match[3]));
				}
				
				// Season + Episode numbers
				if(preg_match("#\(\s*Season\s+([0-9]+)\s*(?:\,\s+Episode\s+([0-9]+))#si", $match[1], $season_match)) {
					$details[ $this->compact_to_slug("Season Number") ] = ltrim(trim($season_match[1]), "0");
					$details[ $this->compact_to_slug("Episode Number") ] = ltrim(trim($season_match[2]), "0");
				}
			} elseif(preg_match("#<div class=\"info\">\s+<h5>\s*Seasons?\:\s*</h5>\s+<div class=\"info\-content\">(.+?)<\/div>#si", $contents, $match)) {
				// TV Show
				$this->cargo[':type'] = $this->compact_to_slug("tv_show");
				
				//$num_seasons = 0;
				//
				//if(preg_match_all("#<a\s+href=\"episodes\?season=([0-9]+)\"#si", $match[1], $seasons_match)) {
				//	$num_seasons = count($seasons_match[1]);
				//}
				//
				//if($num_seasons > 0) {
				//	$this->do_tv_seasons($num_seasons);
				//}
			} else {
				// Movie
				$this->cargo[':type'] = $this->compact_to_slug("movie");
			}
			
			
			
			
			// Special logic for TV Show Episodes
			if($this->compact_to_slug("episode") === $this->cargo[':type']) {
				// Parent TV Show
				if(preg_match("#<div class=\"info\">\s+<h5>\s*TV Series\:\s*</h5>\s+<div class=\"info\-content\">\s*<a\s+href=\"\/title\/([A-Za-z0-9]+)\/?\"#si", $contents, $show_match)) {
					$parent_imdb_key = $this->correct_imdb_key(trim($show_match[1], "/"));
					
					if(!empty($parent_imdb_key)) {
						$this->cargo[':parent'] = $parent_imdb_key;
						
						$imdb_data[':attached'][] = $parent_imdb_key;
					}
				}
				
				// Cleanup Title
				$title = $this->clean_html($this->cargo['title']);
				$title = preg_replace("#\s+\([0-9]{2,4}\)\s*$#si", "", $title);
				$title = preg_replace("#^\s*\"[^\"]+\"\s+#si", "", $title);
				$this->cargo['title'] = trim($title);
			}
			
			
			// Special logic for TV Shows
			if($this->compact_to_slug("tv_show") === $this->cargo[':type']) {
				// Cleanup Title
				$title = $this->clean_html($this->cargo['title']);
				$title = preg_replace("#\s+\([0-9]{2,4}\)\s*$#si", "", $title);
				$title = trim($title, " \t\n\"");
				$this->cargo['title'] = trim($title);
			}
			
			
			// Special logic for Movies
			if($this->compact_to_slug("movie") === $this->cargo[':type']) {
				// Cleanup Title
				$title = $this->clean_html($this->cargo['title']);
				$title = preg_replace("#\s+\-\s+IMDb\s*$#si", "", $title);
				$title = preg_replace("#\s+\([0-9]{2,4}\)\s*$#si", "", $title);
				$this->cargo['title'] = trim($title);
			}
			
			
			// Save details
			if(!empty($details)) {
				$this->cargo['details'] = $details;
			}
			
			
			
			// Poster
			if(preg_match("#<link rel=\"image_src\" href=\"([^\"]+?)\"#si", $contents, $match)) {
				if(!preg_match("#imdb\-share\-logo\.png$#si", $match[1])) {
					$this->cargo['poster'] = $this->clean_image_url($match[1]);
				}
			}
			
			
			$this->cargo['cast'] = array();
			
			// Cast
			
			// Actors
			preg_match("#<h3>Cast</h3>&nbsp;<small style=\"position\: relative; bottom\: 1px\">\s+\(([^\)]+?)\)</small>#si", $contents, $match);
			preg_match("#<table class=\"cast\">(.+?)</table>#si", $contents, $match_cast);
			
			if(!empty($match_cast[1])) {
				preg_match_all("#<tr class=\"(?:even|odd)\">(.+?)</tr>#si", $match_cast[0], $match_cast_rows);
				
				if(!empty($match_cast_rows[0])) {
					$cast = array();
					
					foreach($match_cast_rows[1] as $cast_row) {
						
						$cast_row = preg_replace(
							array(
								"#\s+(?:onClick|title|alt|border|width|height|style)=\"(?:[^\"]+?)\"#si",
								"#<br(?:\s*/)?>#si",
								"#<td class=\"ddd\">([^<]+?)</td>#si",
							),
							array(
								"",
								"",
								"",
							),
							$cast_row
						);
						
						preg_match("#<td class=\"hs\">(.+?)</td>#si", $cast_row, $headshot_html);
						preg_match("#<td class=\"nm\">(.+?)</td>#si", $cast_row, $person_html);
						preg_match("#<td class=\"char\">(.+?)</td>#si", $cast_row, $character_html);
						
						// \s*<td(?:[^>]*?)>\s*(.+?)\s*</td>\s*<td(?:[^>]*?)>\s*(.+?)\s*</td>\s*
						
						if(!empty($person_html[1]) && !empty($character_html[1])) {
							// reassign
							$person_html = $person_html[1];
							$character_html = $character_html[1];
							
							// extract person info
							$person = array();
							
							if(preg_match("#href=\"\/(?:[^\/]+?)\/(nm|tt|co|ch)([0-9]{7})\/?\"(?:[^>]*)?>([^<]+?)</a>#si", $person_html, $match)) {
								/*
								$person[':imdb'] = array(
									'imdb_key'	=> $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT),
									'imdb_type'	=> $match[1],
									'imdb_id'	=> ltrim($match[2], "0"),
								);
								*/
								$person[':imdb'] = $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT);
								$person['name'] = trim($this->clean_html($match[3]));
							} else {
								$person['name'] = trim($this->clean_html($person_html));
							}
							
							$person_notes = $this->extract_inline_notes($person['name']);
							
							if(!empty($person_notes)) {
								$person['notes'] = $person_notes;
							}
							
							
							// character(s) info
							$characters = array();
							
							// skip over non character dividers
							
							$character_html = preg_replace("#>([^\(]+)?\(([^\)]*?)</a>([^\)]*?)\)#si", ">\\1(\\2\\3\\4\\5)</a>", $character_html);
							$character_html = preg_replace("#\(([^\)]+?)\/#si", "(\\1##JNGDIV##", $character_html);
							
							// divide up by IMDb's splitter character '/'
							$chars = preg_split("#\s+?\/\s+?#si", $character_html);
							
							if(!empty($chars)) {
								foreach($chars as $char) {
									$char = str_replace("##JNGDIV##", "/", $char);
									$character = array();
									
									if(preg_match("#href=\"\/(?:[^\/]+?)\/(nm|tt|co|ch)([0-9]{7})\/?\"(?:[^>]*)?>([^<]+?)</a>#si", $char, $match)) {
										// todo: save notes with (?:\s+?\(([^\)]+?)\))?
										/*
										$character[':imdb']	= array(
											'imdb_key'	=> $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT),
											'imdb_type'	=> $match[1],
											'imdb_id'	=> ltrim($match[2], "0"),
										);
										*/
										
										$character[':imdb'] = $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT);
										$character['name'] = trim($this->clean_html($match[3]));
									} else {
										$character = array(
											'name'	=> trim($this->clean_html($char)),
										);
									}
									
									$character_notes = $this->extract_inline_notes($character['name']);
									
									if(!empty($character_notes)) {
										$character['notes'] = $character_notes;
									}
									
									$characters[] = $character;
								}
							}
							
							
							// save
							$cast[] = array(
								'person'		=> $person,
								'characters'	=> $characters,
							);
							
							continue;
						}
						
						$cast[] = $cast_row;
					}
				}
				
				if(!empty($cast)) {
					$this->cargo['cast']['actors'] = $cast;
				}
			}
			
			
			// Movies - Directors, Writers, Producers, etc.
			if(preg_match_all("#<h5><a class=\"glossary\" name=\"([^\"]+?)\" href=\"(?:[^\"]+?)\">([^<]+?)</a></h5></td></tr>(.+?)></table>#si", $contents, $matches)) {
				foreach($matches[0] as $ckey => $ctable) {
					$cast_type_key = $matches[1][ $ckey ];
					$cast_type_name = $matches[2][ $ckey ];
					
					$cast = array();
					
					$cast_raw_html = $matches[3][ $ckey ];
					
					if(preg_match_all("#<tr>\s*<td(?:[^>]+?)>(.+?)</td>\s*<td(?:[^>]+?)>(?:[^<]+?)</td>\s*<td(?:[^>]+?)>(.+?)</td>\s*</tr>#si", $cast_raw_html, $cast_entry)) {
						foreach($cast_entry[0] as $ce_key => $nil) {
							// extract person info
							$person = array();
							
							$person_html = $cast_entry[1][ $ce_key ];
							$roles_html = $cast_entry[2][ $ce_key ];
							
							if(empty($person_html)) {
								continue;
							}
							
							if(preg_match("#href=\"\/(?:[^\/]+?)\/(nm|tt|co|ch)([0-9]{7})\/?\"(?:[^>]*)?>([^<]+?)</a>#si", $person_html, $match)) {
								/*
								$person[':imdb'] = array(
									'imdb_key'	=> $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT),
									'imdb_type'	=> $match[1],
									'imdb_id'	=> ltrim($match[2], "0"),
								);
								*/
								
								$person[':imdb'] = $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT);
								$person['name'] = trim($this->clean_html($match[3]));
							} else {
								$person['name'] = trim($this->clean_html($person_html));
							}
							
							$person_notes = $this->extract_inline_notes($person['name']);
							
							if(!empty($person_notes)) {
								$person['notes'] = $person_notes;
							}
							
							
							$roles = array();
							
							// skip over non character dividers
							
							$roles_html = preg_replace("#>([^\(]+)?\(([^\)]*?)</a>([^\)]*?)\)#si", ">\\1(\\2\\3\\4\\5)</a>", $roles_html);
							$roles_html = preg_replace("#\(([^\)]+?)\/#si", "(\\1##JNGDIV##", $roles_html);
							
							// divide up by IMDb's splitter character '/'
							$jobs = preg_split("#\s+?\/\s+?#si", $roles_html);
							
							if(!empty($jobs)) {
								foreach($jobs as $job) {
									$job = str_replace("##JNGDIV##", "/", $job);
									$role = array();
									
									$role_notes = $this->extract_inline_notes($job);
									
									if(preg_match("#href=\"(?:[^\"]+?)\"(?:[^>]*)?>([^<]+?)</a>#si", $job, $match)) {
										$role['role'] = trim($this->clean_html($match[1]));
									} else {
										$role['role'] = trim($this->clean_html($job));
									}
									
									if(empty($role['role'])) {
										unset($role['role']);
									}
									
									if(!empty($role_notes)) {
										$role['notes'] = $role_notes;
									}
									
									if(!empty($role)) {
										$roles[] = $role;
									}
								}
							}
							
							
							// save
							$cast_save = array(
								'person'	=> $person,
							);
							
							if(!empty($roles)) {
								$cast_save['roles'] = $roles;
							}
							
							$cast[] = $cast_save;
						}
					}
					
					if(!empty($cast)) {
						$this->cargo['cast'][ $matches[1][ $ckey ] ] = $cast;
					}
				}
			}
			
			
			// Movies - Company Credits
			
			if(preg_match_all("#<b class=\"blackcatheader\">(.+?)</b>\s*<ul(?:[^>]*?)?>(.+?)</ul>#si", $contents, $matches)) {
				
				$co_credits = array();
				
				foreach($matches[0] as $co_cr_key => $nil) {
					$credits_type = $this->compact_to_slug($matches[1][ $co_cr_key ]);
					$credits_html = $matches[2][ $co_cr_key ];
					$co_credit = array();
					
					if(preg_match_all("#<li(?:[^>]*?)?>(.+?)</li>#si", $credits_html, $matches_credit)) {
						foreach($matches_credit[0] as $ckey => $nil) {
							$credit_html = $matches_credit[1][ $ckey ];
							$credit = array();
							
							$credit_notes = $this->extract_inline_notes($credit_html);
							
							$credit_html = trim($credit_html);
							
							if(preg_match("#&nbsp;&nbsp;(.+?)$#si", $credit_html, $match)) {
								$credit['credit'] = trim($match[1]);
								
								$credit_html = preg_replace("#&nbsp;&nbsp;(.+?)$#si", "", $credit_html);
							}
							
							if(preg_match("#href=\"\/(?:[^\/]+?)\/(nm|tt|co|ch)([0-9]{7})\/?\"(?:[^>]*)?>([^<]+?)</a>#si", $credit_html, $match)) {
								/*
								$credit[':imdb'] = array(
									'imdb_key'	=> $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT),
									'imdb_type'	=> $match[1],
									'imdb_id'	=> ltrim($match[2], "0"),
								);
								*/
								
								$credit[':imdb'] = $match[1] . str_pad($match[2], 7, "0", STR_PAD_LEFT);
								$credit['company'] = trim($this->clean_html($match[3]));
							} else {
								$credit['company'] = trim($this->clean_html($credit_html));
							}
							
							if(!empty($credit_notes)) {
								$credit['notes'] = $credit_notes;
							}
							
							if(!empty($credit)) {
								$co_credit[] = $credit;
							}
						}
						
						if(!empty($co_credit)) {
							$co_credits[ $credits_type ] = $co_credit;
						}
					}
				}
				
				if(!empty($co_credits)) {
					$this->cargo['companies'] = $co_credits;
				}
			}
		}
		
		// Movies - Keywords
		private function do_movie_keywords() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			// http://www.imdb.com/title/tt0462322/keywords
			
			$contents_url = "http://www.imdb.com/title/". $this->imdb_key() ."/keywords";
			$contents = $this->pull_url($contents_url, "source:movie:keywords");
			
			if(preg_match_all("#<a href=\"\/keyword\/([A-Za-z0-9\-]+?)\/\">([^<]+?)</a>#si", $contents, $matches)) {
				$keywords = array();
				
				foreach(array_keys($matches[0]) as $mkey) {
					$keywords[ $this->compact_to_slug($matches[1][ $mkey ], "-") ] = trim($matches[2][ $mkey ]);
				}
				
				if(!empty($keywords)) {
					$this->cargo['keywords'] = $keywords;
				}
			}
		}
		
		// Movies - Releases
		private function do_movie_releases() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			// http://www.imdb.com/title/tt0462322/releaseinfo
			
			$contents_url = "http://www.imdb.com/title/". $this->imdb_key() ."/releaseinfo";
			$contents = $this->pull_url($contents_url, "source:movie:releases");
			
			$countries = array();
			
			// release dates per country
			if(preg_match("#<tr>\s*<th(?:[^>]*?)>\s*Country\s*</th>\s*<th(?:[^>]*?)>\s*Date\s*</th>\s*</tr>\s*(.+?)</table>#si", $contents, $match)) {
				$releases_html = $match[1];
				
				if(preg_match_all("#<tr>(.+?)</tr>#si", $releases_html, $matches)) {
					foreach($matches[1] as $release_html) {
						preg_match("#href=\"\/calendar\/\?region=([A-Z]+?)\"(?:[^>]*)?>([^<]+?)</a>#", $release_html, $match_country);
						preg_match("#href=\"\/date\/([0-9]{1,2})\-([0-9]{1,2})\/?\"#", $release_html, $match_date);
						preg_match("#href=\"\/year\/([0-9]{4})\/?#", $release_html, $match_year);
						
						if(empty($match_country[0]) || empty($match_date[0]) || empty($match_year[0])) {
							continue;
						}
						
						$countries[ $this->compact_to_slug($match_country[2]) ] = array(
							'country'		=> $match_country[2],
							'country_code'	=> $match_country[1],
							'date'			=> $match_year[1] ."-". str_pad($match_date[1], 2, "0", STR_PAD_LEFT) ."-". str_pad($match_date[2], 2, "0", STR_PAD_LEFT),
						);
					}
				}
			}
			
			
			// also known as
			if(preg_match("#<a name=\"akas\">Also Known As \(AKA\)\s*</a>\s*</h5>\s*<table(?:[^>]+?)?>(.+?)</table>#si", $contents, $match)) {
				if(preg_match_all("#<tr(?:[^>]+?)?>\s*<td(?:[^>]+?)?>([^<]+?)</td>\s*<td(?:[^>]+?)?>(.+?)</td>#si", $match[1], $matches)) {
					foreach(array_keys($matches[0]) as $mkey) {
						$aka = $this->clean_html( $matches[1][$mkey] );
						//$country = $matches[2][$mkey];
						$country_each = explode(" / ", $matches[2][$mkey]);
						
						if(empty($country_each)) {
							continue;
						}
						
						foreach($country_each as $country) {
							$this->extract_inline_notes($country);
							
							if((strlen($country) > 0) && (strlen($aka) > 0)) {
								if(!isset($countries[ $this->compact_to_slug($country) ])) {
									$countries[ $this->compact_to_slug($country) ] = array();
								}
								
								if(!isset($countries[ $this->compact_to_slug($country) ]['alternative_names'])) {
									$countries[ $this->compact_to_slug($country) ]['alternative_names'] = array();
								}
								
								$countries[ $this->compact_to_slug($country) ]['alternative_names'][] = $aka;
							}
						}
					}
				}
			}
			
			if(!empty($countries)) {
				$this->cargo['releases'] = $countries;
			}
		}
		
		// Movies - Business
		private function do_movie_business() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			$contents_url = "http://www.imdb.com/title/". $this->imdb_key() ."/business";
			$contents = $this->pull_url($contents_url, "source:movie:releases");
			
			$business = array();
			
			if(!preg_match("#<\/div>\s+<h5>(.+)<hr\s*\/?>#si", $contents, $match)) {
				return;
			}
			
			$business_html = "<h5>". $match[1];
			
			$business_divided = preg_split("#<h5>([^<]+?)</h5>#si", $business_html, -1, PREG_SPLIT_DELIM_CAPTURE);
			array_shift($business_divided);
			
			if(empty($business_divided)) {
				return;
			}
			
			foreach($business_divided as $bd_key => $bd_value) {
				if(($bd_key % 2) == 0) {
					continue;
				}
				
				$business_section_key = $this->compact_to_slug($business_divided[ $bd_key - 1 ]);
				
				$bd_value = explode("<br/>", $bd_value);
				
				foreach($bd_value as $bdk => $bdv) {
					$bdv = $this->clean_html( $bdv );
					$bdv = strip_tags($bdv);
					$bdv = trim($bdv);
					
					if(strlen($bdv) <= 0) {
						unset($bd_value[ $bdk ]);
						
						continue;
					}
					
					$bd_value[ $bdk ] = $bdv;
				}
				
				if(in_array($business_section_key, array("budget", "rentals")) && (count($bd_value) == 1)) {
					$bd_value = array_shift($bd_value);
				}
				
				if(!empty($bd_value)) {
					$business[ $business_section_key ] = $bd_value;
				}
			}
			
			if(!empty($business)) {
				$this->cargo['business'] = $business;
			}
		}
		
		
		/*
			    ____                   __   
			   / __ \___  ____  ____  / /__ 
			  / /_/ / _ \/ __ \/ __ \/ / _ \
			 / ____/  __/ /_/ / /_/ / /  __/
			/_/    \___/\____/ .___/_/\___/ 
			                /_/             
		*/
		
		// Person - Biography
		private function do_person_biography() {
			// http://www.imdb.com/name/nm0004695/bio
			
			$contents_url = "http://www.imdb.com/name/". $this->imdb_key() ."/bio";
			$contents = $this->pull_url($contents_url, "source:person:biography");
			
			// IMDb key links to somewhere else?
			if(preg_match("#<H1>\s*Moved Permanently\s*<\/H1>\s+The document has moved\s+<A HREF=\"http\:\/\/(?:www\.)?imdb\.com\/name/nm([0-9]+)\/(?:bio\/?)?\">\s*here\s*<\/A>#si", $contents, $match)) {
				$this->imdb_redirect_key = $this->correct_imdb_key("nm". str_pad(ltrim($match[1], "0"), 7, "0", STR_PAD_LEFT));
				
				return;
			}
			
			
			
			// Find name
			if(preg_match("#<title>(.+?)\s+\-\s+Biography\s*<\/title>#si", $contents, $match)) {
				$title = trim($match[1]);
				$title = preg_replace("#\s+\(([^\)]*)\)\s*$#si", "", $title);
				$title = trim($title, " ()-");
				
				if(strlen($title) > 0) {
					$this->cargo['title'] = $title;
				}
			}
			
			
			// Poster
			if(preg_match("#<a\s+name=['|\"]headshot['|\"]\s+href=['|\"](?:.+?)['|\"]\s*>\s*<img\s*border=['|\"]0['|\"]\s+src=['|\"](.+?)['|\"]\s*#si", $contents, $match)) {
				if(!preg_match("#imdb\-share\-logo\.png$#si", $match[1]) && !preg_match("#nophoto\.jpg$#si", $match[1])) {
					$this->cargo['poster'] = $this->clean_image_url($match[1]);
				}
			}
			
			
			// Details
			
			if(!preg_match("#<div id=\"tn15content\">(.+?)<\/div>#si", $contents, $match)) {
				return;
			}
			
			$details_html = str_replace("\r", "", $match[1]);
			
			$details_cleanup = array(
				"#<a name=\"([^\"]*)\"><\/a>#si" => "",
				"#<br\s*?\/?>#si" => "<br>",
				"#<\!\-\-(.+?)\-\->#si" => "",
				"#<script(.+?)<\/script>#si" => "",
				"#<div(.+?)$#si" => "",
				"#<a href=\"\/(name|title|character|company)\/(nm|tt|ch|co)([0-9]+)\/?\"(?:[^>]*)?>(.+?)<\/a>#si" => "[imdb_\\1=\\2\\3]\\4[/\\1]",
				"#<a href=\"\/(date|search)\/([^\"]+?)\">([^<]+?)<\/a>#si" => "\\3",
			);
			
			
			//$details_html = preg_replace(array_keys($details_cleanup), array_values($details_cleanup), $details_html);
			foreach($details_cleanup as $regex => $with) {
				$details_html = preg_replace($regex, $with, $details_html);
			}
			
			$details_divided = preg_split("#<h5>([^<]+?)</h5>#si", $details_html, -1, PREG_SPLIT_DELIM_CAPTURE);
			
			if(empty($details_divided)) {
				continue;
			}
			
			array_shift($details_divided);
			
			$details = array();
			foreach(array_keys($details_divided) as $key) {
				if(($key % 2) === 1) {
					continue;
				}
				
				$detail_title = trim($details_divided[ $key ]);
				$detail_key = $this->compact_to_slug( $detail_title );
				$detail_html = $details_divided[ (1 + $key) ];
				
				// skip specific sections
				if(preg_match("#wikipedia_bio#si", $detail_html)) {
					continue;
				}
				
				
				
				// cleanup inner html
				$detail_html = preg_replace("#\s*(<br\s*\/?>\s*){0,}\s*$#si", "", $detail_html);
				//$detail_html = html_entity_decode($detail_html);
				//$detail_html = preg_replace_callback("#\&\#x([0-9]+)\;#si", function($match) {
				//	//return chr($match[1]);
				//}, $detail_html);
				$detail_html = $this->clean_html($detail_html);
				$detail_html = trim($detail_html);
				
				// Parse detail information
				$detail_html = $this->person_parse_detail($detail_key, $detail_html);
				
				if($detail_html !== false) {
					//$details[ $detail_key ] = array(
					//	'title'		=> $detail_title,
					//	'content'	=> $detail_html,
					//);
					
					$details[ $detail_key ] = $detail_html;
				}
			}
			
			if(!empty($details)) {
				$this->cargo['details'] = $details;
			}
		}
		
		// Person - Convert Detail Body [internal]
		private function person_parse_detail($key, $content) {
			switch($key) {
				// nickname
				case 'nickname':
					// list
					$content_split = preg_split("#\s*<br>\s*#si", $content, -1, PREG_SPLIT_NO_EMPTY);
					
					if(!empty($content_split)) {
						return array("type" => "list", "rows" => $content_split);
					}
				break;
				
				case 'height':
					if(preg_match("#\(([0-9\.]+)\s*m\)#si", $content, $match)) {
						return array(
							'type'	=> "measurement",
							'scale'	=> "cm",
							'value'	=> ($match[1] * 100),
						);
					}
					
					if(preg_match("#([0-9]+)\s*\'\s+([0-9\.]+)\s*\"#si", $content, $match)) {
						return array(
							'type'	=> "measurement",
							'scale'	=> "cm",
							'value'	=> ( ($match[1] * 12 * 2.54) + ($match[2] * 2.54) ),
						);
					}
					
					return $content;
				break;
				
				case 'trivia':
				case 'personal_quotes':
				case 'trade_mark':
					// list
					if(preg_match_all("#<p>(.+?)<\/p>#si", $content, $matches)) {
						return array("type" => "list", "rows" => $matches[1]);
					}
				break;
				
				case 'where_are_they_now':
					// list (special instructions)
					if(preg_match_all("#<p>(.+?)<\/p>#si", $content, $matches)) {
						$rows = array();
						
						foreach($matches[1] as $row) {
							if(preg_match("#^\s*\((January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})\)\s*(.+)$#si", $row, $match)) {
								$rows[] = array(
									'date'		=> trim($match[1] ." ". $match[2], " ()"),
									'content'	=> trim($match[3], " ()"),
								);
							} else {
								$rows[] = $row;
							}
						}
						
						return array("type" => "list", "rows" => $rows);
					}
				break;
				
				case 'mini_biography':
					if(preg_match("#\s+<b>IMDb Mini Biography By\:\s*<\/b>\s+<a(?:.+)href=\"([^\"]+)\"(?:.*)>(.+?)<\/a>(?:.*)$#si", $content, $match, PREG_OFFSET_CAPTURE)) {
						$cleaned_content = substr($content, 0, $match[0][1]);
						$author = trim($match[2][0]);
						
						$cleaned_content = implode(PHP_EOL . PHP_EOL, preg_split("#<\/p>\s*<p>#si", $cleaned_content));
						$cleaned_content = preg_replace("#^\s*<p>\s*#si", "", $cleaned_content);
						$cleaned_content = preg_replace("#\s*<\/p>\s*$#si", "", $cleaned_content);
						
						return array(
							'type'		=> "essay",
							'content'	=> $cleaned_content,
							'author'	=> $author
						);
					}
					
					return array(
						'type'		=> "essay",
						'content'	=> $content
					);
				break;
			}
			
			if(preg_match("#<table(?:.*?)>(.+?)<\/table>#si", $content, $match)) {
				if(preg_match_all("#<tr>(.+?)<\/tr>#si", $match[1], $matches)) {
					$rows = array();
					
					foreach(array_keys($matches[0]) as $row_key) {
						if(preg_match_all("#<td(?:[^>]*?)>(.+?)<\/td>#si", $matches[1][$row_key], $column_matches)) {
							$rows[] = array_map("trim", $column_matches[1]);
						}
					}
					
					if(!empty($rows)) {
						if($key == "salary") {
							foreach($rows as $row_key => $row_value) {
								$job = $row_value[0];
								$amount = $row_value[1];
								$notes = "";
								
								if(preg_match("#\\$([0-9,\.]+)\s*(.*)$#si", $amount, $match)) {
									$amount = "$". trim($match[1], " ,$.");
									$notes = trim($match[2], " +().");
								}
								
								$rows[ $row_key ] = array(
									'job'		=> $job,
									'amount'	=> $amount,
								);
								
								if(strlen($notes) > 0) {
									$rows[ $row_key ]['notes'] = $notes;
								}
							}
						}
						
						return array("type" => "table", "rows" => $rows);
					}
				}
			}
			
			// errors in parsing data above = return content
			return $content;
		}
		
		// Person - Filmography + Main Details
		private function do_person_filmography() {
			if(!empty($this->imdb_redirect_key)) {
				return;
			}
			
			
			// http://www.imdb.com/name/nm0004695/maindetails
			
			$contents_url = "http://www.imdb.com/name/". $this->imdb_key() ."/maindetails";
			$contents = $this->pull_url($contents_url, "source:person:filmography");
			
			if(preg_match("#\/social\/twitter\.html\#([^\"]+)\"\s*>\s*<\/iframe>#si", $contents, $match)) {
				$this->cargo['external_ids']['twitter'] = trim($match[1], "#\"");
			}
			
			if(!preg_match("#<div\s+id=\"filmography\"(?:[^>]*)>(.+)<div class=\"article\"(?:[^>]*)>#si", $contents, $match)) {
				return;
			}
			
			if(preg_match_all("#\/title\/(tt[0-9]+)\/?\"#si", $match[1], $matches)) {
				// found job positions
				$attached_titles = array();
				
				foreach($matches[1] as $title_id) {
					$title_id = $this->correct_imdb_key($title_id);
					
					if(!empty($title_id)) {
						$this->cargo[':attached'][] = $title_id;
					}
				}
			}
		}
		
		
		
		/*
			   ________                          __                
			  / ____/ /_  ____ __________ ______/ /____  __________
			 / /   / __ \/ __ `/ ___/ __ `/ ___/ __/ _ \/ ___/ ___/
			/ /___/ / / / /_/ / /  / /_/ / /__/ /_/  __/ /  (__  ) 
			\____/_/ /_/\__,_/_/   \__,_/\___/\__/\___/_/  /____/  
                                                                                                          
		*/
		
		// 
		private function do_character_biography() {
			// http://www.imdb.com/character/ch0133781/
			
			$contents_url = "http://www.imdb.com/character/". $this->imdb_key() ."/";
			$contents = $this->pull_url($contents_url, "source:character:biography");
			
			// IMDb key links to somewhere else?
			if(preg_match("#<H1>\s*Moved Permanently\s*<\/H1>\s+The document has moved\s+<A HREF=\"http\:\/\/(?:www\.)?imdb\.com\/character/ch([0-9]+)\/(?:bio\/?)?\">\s*here\s*<\/A>#si", $contents, $match)) {
				$this->imdb_redirect_key = "ch". str_pad(ltrim($match[1], "0"), 7, "0", STR_PAD_LEFT);
				
				return;
			}
			
			
			// Find name
			if(preg_match("#<title>(.+?)\s+\(Character\)\s*</title>#si", $contents, $match)) {
				$title = trim($match[1]);
				$title = preg_replace("#\s+\(([^\)]*)\)\s*$#si", "", $title);
				
				$this->cargo['title'] = trim($title, " ()-");
			}
			
			// Poster
			if(preg_match("#<link rel=\"image_src\" href=\"([^\"]+?)\"#si", $contents, $match)) {
				if(!preg_match("#imdb\-share\-logo\.png$#si", $match[1])) {
					$this->cargo['poster'] = $this->clean_image_url($match[1]);
				}
			}
			
			// Alternative Names
			if(!preg_match("#<h5>\s*Alternate Names\:\s*<\/h5>\s*<div class=\"info\-content\">(.+?)<\/div>\s*<\/div>#si", $contents, $match)) {
				return;
			}
			
			$alt_names_html = trim(str_replace("\r", "", $match[1]));
			
			$alt_names = preg_split("#\s+\/\s+#si", $alt_names_html, -1, PREG_SPLIT_NO_EMPTY);
			
			if(!empty($alt_names)) {
				$this->cargo['details'][ $this->compact_to_slug("Alternate Names") ] = $alt_names;
			}
		}
		
		
		/*
			   ______                                  _          
			  / ____/___  ____ ___  ____  ____ _____  (_)__  _____
			 / /   / __ \/ __ `__ \/ __ \/ __ `/ __ \/ / _ \/ ___/
			/ /___/ /_/ / / / / / / /_/ / /_/ / / / / /  __(__  ) 
			\____/\____/_/ /_/ /_/ .___/\__,_/_/ /_/_/\___/____/  
			                    /_/                               
		*/
		
		// 
		private function do_company_() {
			// 
			
			$contents_url = "http://www.imdb.com/". $this->imdb_key() ."/";
			$contents = $this->pull_url($contents_url, "source:character:");
		}
	}
