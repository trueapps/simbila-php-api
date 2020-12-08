<?php
	require('../Simbila.php');
	require('../lib/Simbila_Exception.php');
	require('../lib/Simbila_AdapterInterface.php');
	require('../lib/Simbila_CurlAdapter.php');
	require('../lib/Simbila_Http_AdapterInterface.php');
	require('../lib/Simbila_Http_NativeAdapter.php');
	require('../lib/Simbila_Response.php');
	require('../lib/Simbila_Response_Exception.php');
	
	
	$simbila = new Simbila('tomas@tomashnilica.com','THAPI', 'lunchdrive','19');
	
	/*create new billing*/
	$data = array(
		'fy_name' => 'TheNewClient',
		'fy_email' => 'newclient@simbila.com',
		'plan_text' => 'Subscription X',
		'plan_text' => 'Subscription X',
		'plan_price' => '99',
		'plan_vat'	=> '21',
		'plan_pcs' => '3',
		'r_lang' => 'cs',
		);
	
	$ret = $simbila->createBilling($data);
	echo $ret->response();
	
	echo "<hr>";
	
	/*check the billing existence*/
	$ret = $simbila->billingStatus();
	echo $ret->response();
	$res = $ret->obj();
	echo "The company name: " . $res['fy_name'];
	
	echo "<hr>";
	
	
	//update Firm data and subscription  price and amount
	$data = array(
		'fy_name' => 'TheNewClientChanged',
		'plan_price'=>'109',
		'plan_items'=>'2',
		);
	$ret = $simbila->updateBilling($data);
	echo $ret->response();

	echo "<hr>";
	
	
	/*check the billing existence*/
	$ret = $simbila->billingStatus();
	echo $ret->response();
	$res = $ret->obj();
	echo "The company name: " . $res['fy_name'];
	
	echo "<hr>";
	
	
	echo "Billing Invoices:<br>";
	/*return list of invoices*/
	$ret = $simbila->billingInvoices();
	$res = $ret->obj();
	echo "working with the result:";
	foreach($res as $inv) {
		echo "<a href='".$inv['link']."'>".$inv['i_number']."</a><br/>";
	}

	echo "<hr>";
	
	echo "All account Invoices:<br>";
	/*return list of invoices*/
	$ret = $simbila->invoices();
	$res = $ret->obj();
	echo "working with the result:";
	foreach($res as $inv) {
		echo "<a href='".$inv['link']."'>".$inv['i_number']."</a><br/>";
		$lastID = $inv['i_id'];
	}
	
	echo "<hr>";


	echo "Last invoice details:<br>";
	$ret = $simbila->invoice($lastID);
	$res = $ret->obj();
	echo "<a href='".$res['link']."'>".$res['i_number']."</a><br/>";
	echo "<hr>";


	echo "All account Clients:<br>";
	/*return list of clients*/
	$ret = $simbila->clients();
	$res = $ret->obj();
	echo "working with the result:";
	foreach($res as $inv) {
		echo $inv['fy_id'] . " is client " . $inv['fy_name'];
		$lastID = $inv['fy_id'];
	}
	
	echo "<hr>";


	echo "Last client details:<br>";
	$ret = $simbila->client($lastID);
	$res = $ret->obj();
	echo $inv['fy_id'] . " is client " . $inv['fy_name'];
	echo "<hr>";
	

	echo "All Recurring Invoices:<br>";
	/*return list of rinvoices*/
	$ret = $simbila->rinvoices();
	$res = $ret->obj();
	echo "working with the result:";
	foreach($res as $inv) {
		echo $inv['r_id'] . ", ";
		$lastID = $inv['r_id'];
	}
	
	echo "<hr>";


	echo "Last rinvoice details:<br>";
	$ret = $simbila->rinvoice($lastID);
	$res = $ret->obj();
	echo $inv['r_id'] . " is client " . $inv['r_firm'];
	echo "<hr>";


	/*delete the client*/
	$ret = $simbila->deleteBilling();
	echo $ret->response();	
	
	echo "<hr>";

