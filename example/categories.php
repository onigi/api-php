<style>
pre{
	background-color:#F9F9F9;
	border:2px dashed #D0D0D0;
	color:#002166;
	max-height:150px;
	overflow:auto;
}
</style>
<?php

include '../src/onigi.php';

$onigi = new Onigi(array(
	'appId' => '3',
	'secret' => 'KLDOIUKehu45546dfeh789354389LKJdsfsDHOIE903748kdfk'
));

$user = $onigi->getUser();

if(!empty($user)){
	
	echo "<h1>List All Categories</h1>";
	$categories = array();
	
	try
	{
		$categories = $onigi->api('/categories');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($categories);
	echo '</pre>';

}
