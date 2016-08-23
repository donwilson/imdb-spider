<form method="get" action="search.php">
	Search for: <input type="text" name="q" value="<?=(isset($_REQUEST['q'])?$_REQUEST['q']:"");?>" /> <input type="submit" value="Search" />
</form>

<hr />

<?php
	if(isset($_REQUEST['q'])) {
		$url = "https://www.google.com/search?q=site%3Aimdb.com+". urlencode($_REQUEST['q']);
		$imdb_key = false;
		
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1A543a Safari/419.3");
		//curl_setopt($ch, CURLOPT_, );
		
		$contents = curl_exec($ch);
		curl_close($ch);
		
		//print htmlentities($contents);
		
		preg_match_all("#<p>(.+?)</p>#si", $contents, $matches);
		
		print "<pre>". htmlentities( print_r($matches,1) ) ."</pre>\n";
		
		foreach($matches[0] as $k => $u) {
			if($imdb_key === false) {
				if(preg_match("#imdb\.com/title/tt([0-9]+)#si", $matches[1][$k], $imdb_key_match)) {
					// found it!
					$imdb_key = "tt". $imdb_key_match[1];
				}
			}
		}
		
		print "imdb_key = ". ($imdb_key !== false?$imdb_key:"FALSE!!!!!!!!!");
		
		$imdb_key = false;
	}
	
	