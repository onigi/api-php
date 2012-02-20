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
	'appId' => '2',
	'secret' => '48dkJHDKJ8JDK37458948395jd'
));

$user = $onigi->getUser();

if(!empty($user)){
	
	echo "<h1>List All Order</h1>";
	$orders = array();
	
	try
	{
		$orders = $onigi->api('/orders');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($orders);
	echo '</pre>';
  
	
	echo "<h1>List Pending Order</h1>";
	$pendingOrders = array();
  
	try
	{
		$pendingOrders = $onigi->api('/orders',array(
			'order_status' => 'pending'
		));
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($pendingOrders);
	echo '</pre>';
	
	echo "<h1>Show Order ID 1266</h1>";
	$order = array();
  
	try
	{
		$order = $onigi->api('/orders/1266');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($order);
	echo '</pre>';
	
	
	echo "<h1>Aceept Order ID 1266</h1>";
	
	try
	{
		$result = $onigi->api('/orders/accept/1266','POST');
	}
	catch (OnigiApiException $exc)
	{
		echo $exc->getMessage();
	}
	echo '<pre>';
	print_r($result);
	echo '</pre>';
  
  

}
