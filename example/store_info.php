<style>
pre{
	background-color:#F9F9F9;
	border:2px dashed #D0D0D0;
	color:#002166;
	overflow:auto;
}
</style>
<?php

include '../src/onigi.php';

$onigi = new Onigi(array(
	'appId' => '2',
	'secret' => '48dkJHDKJ8JDK37458948395jd'
));

$user = $onigi->getUser();

if(!empty($user)){
	
	echo "<h1>Get Store Info</h1>";
	$orders = array();
	
	try
	{
		$orders = $onigi->api('/me');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($orders);
	echo '</pre>';
}
