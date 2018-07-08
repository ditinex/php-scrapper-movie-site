<!DOCTYPE html>
<html>
<head>
	<title>Download</title>
	<style>
		body{
			margin:0;
			padding:0;
		}
	</style>
</head>
<body>
	Downloading please wait ................
<?php
if(isset($_GET['url']) && !empty($_GET['url'])){
	$url = $_GET['url'];
	if(!empty($_GET['title']))
		$title = $_GET['title'];
	else
		$title = strtotime("now");

	$filename = $title.'-spicyemotion.net.mkv';
	@ob_end_clean();
	header_remove(); // Remove any previously set header
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Content-Transfer-Encoding: binary');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	ob_clean();
	flush();
	readfile($url);
	exit;
}
?>
</body>
</html>