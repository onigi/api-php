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
	
	echo "<h1>List All Products</h1>";
	$orders = array();
	
	try
	{
		$products = $onigi->api('/products');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($products);
	echo '</pre>';
  
  echo "<h1>List Specific Product</h1>";
	$orders = array();
	
	try
	{
		$product = $onigi->api('/products/5600');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($product);
	echo '</pre>';
  
}