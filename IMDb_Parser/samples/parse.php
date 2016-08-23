<html>
<head>
<style type="text/css">
	body { background: #000; color: #f0f0f0; }
	a { color: #d0d0d0; }
</style>
</head>
<body>
<form method="get" action="<?=getenv("PHP_SELF");?>">
	IMDb Key: <input type="text" name="imdb" value="<?=(isset($_REQUEST['imdb'])?$_REQUEST['imdb']:"");?>" /> <input type="submit" value="Parse" />
</form>

<table border="1" cellpadding="3"><tr><td colspan="2"><b>Examples:</b></td></tr>
<tr><td align="right">Movie:</td><td><a href="?imdb=tt0322259">2 Fast 2 Furious</a></td></tr>
<tr><td align="right">Person:</td><td><a href="?imdb=nm0004695">Jessica Alba</a></td></tr>
<tr><td align="right">Character:</td><td><a href="?imdb=ch0001354">Spider-Man</a></td></tr>
<tr><td align="right">Company:</td><td><a href="?imdb=co0249290">Marvel Animation [us]</a></td></tr>
</table>

<br />

<?php
	require_once("/var/www/nodes/jungle.dev/dev/nice_print_r.php");
	require_once(__DIR__ ."/../classes/IMDb_Parser/IMDb_Parser.php");
	
	global $imdb_key_types;
	
	$imdb_key_types = array(
		'tt'	=> "Title",
		'ch'	=> "Character",
		'nm'	=> "Name",
		'co'	=> "Company",
	);
	
	if(!empty($_REQUEST['imdb']) && preg_match("#^(". implode("|", array_keys($imdb_key_types)) .")([0-9]{7})$#si", $_REQUEST['imdb'], $imdb_id_match)) {
		$imdb_parser = new IMDb_Parser_IMDb_Parser($imdb_id_match[1] . $imdb_id_match[2]);
		
		$parsed = $imdb_parser->parse();
		
		new dBug($parsed);
	}
