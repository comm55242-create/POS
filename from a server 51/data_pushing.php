<?php
include('C:\xampp\htdocs\inv\data.php');
include('C:\xampp\htdocs\inv\postfolder\connection.php');


//Invoice Start

	$url = $apiurl."lastsynchedid.php?type=invoice&store_code=".$store_code;

	// echo $url;
	$ch = curl_init($url);

	// Set cURL options
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
	    "Content-Type: application/json",
	]);
	$response = curl_exec($ch);

	$data = json_decode($response, true);

	if(is_numeric($data) ){


		$invPushReq = $bdd->prepare('SELECT * FROM invoices WHERE id > ?');
		$invPushReq->execute(array(htmlspecialchars($data)));
		$dataToPush = $invPushReq->fetchAll();

		echo 'Invoice '.count($dataToPush);

		$invpushurl = $apiurl."storedataentry.php";

		$invpushdata = [
		    "action" => 'invoiceentry',
		    'data' => $dataToPush
		];

		$invpushpayload = json_encode($invpushdata);

		$invpushch = curl_init($invpushurl);

		curl_setopt($invpushch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($invpushch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($invpushch, CURLOPT_POSTFIELDS, $invpushpayload);
		// curl_setopt($ch, CURLOPT_USERPWD, 'rainAdmin:wAqGR_$z]N&J4e=w');   
		curl_setopt($invpushch, CURLOPT_HTTPHEADER, [
		    "Content-Type: application/json"
		]);

		$response = curl_exec($invpushch);

		curl_close($invpushch);
	}
//Invoice End

//Alert Start

	$alerturl = $apiurl."lastsynchedid.php?type=alert&store_code=".$store_code;

	// echo $url;
	$alertch = curl_init($alerturl);

	// Set cURL options
	curl_setopt($alertch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($alertch, CURLOPT_HTTPHEADER, [
	    "Content-Type: application/json",
	]);
	$alertresponse = curl_exec($alertch);

	$alertdata = json_decode($alertresponse, true);

	if(is_numeric($alertdata) ){

		$alertPushReq = $bdd->prepare('SELECT * FROM alerts WHERE a_id > ?');
		$alertPushReq->execute(array(htmlspecialchars($alertdata)));
		$alertDataToPush = $alertPushReq->fetchAll();

		echo 'Alert '.count($alertDataToPush);

		$alertpushurl = $apiurl."storedataentry.php";

		$alertpushdata = [
		    "action" => 'alertentry',
		    'data' => $alertDataToPush
		];

		$alertpushpayload = json_encode($alertpushdata);

		$alertpushch = curl_init($alertpushurl);

		curl_setopt($alertpushch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($alertpushch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($alertpushch, CURLOPT_POSTFIELDS, $alertpushpayload);
		// curl_setopt($ch, CURLOPT_USERPWD, 'rainAdmin:wAqGR_$z]N&J4e=w');   
		curl_setopt($alertpushch, CURLOPT_HTTPHEADER, [
		    "Content-Type: application/json"
		]);

		$alertpushresponse = curl_exec($alertpushch);

		curl_close($alertpushch);
	}
//Alert End

//Invoice Manager Start

	$urlM = $apiurl."lastsynchedid.php?type=invoice_manager&store_code=".$store_code;

	// echo $url;
	$chM = curl_init($urlM);

	// Set cURL options
	curl_setopt($chM, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($chM, CURLOPT_HTTPHEADER, [
	    "Content-Type: application/json",
	]);
	$responseM = curl_exec($chM);

	$dataM = json_decode($responseM, true);

	if(is_numeric($dataM) ){


		$invMPushReq = $bdd->prepare('SELECT * FROM invoices_manager WHERE id > ?');
		$invMPushReq->execute(array(htmlspecialchars($dataM)));
		$dataMToPush = $invMPushReq->fetchAll();

		echo 'Invoice manager '.count($dataMToPush);

		$invMpushurl = $apiurl."storedataentry.php";

		$invMpushdata = [
		    "action" => 'invoiceMentry',
		    'data' => $dataMToPush
		];

		$invMpushpayload = json_encode($invMpushdata);

		$invMpushch = curl_init($invMpushurl);

		curl_setopt($invMpushch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($invMpushch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($invMpushch, CURLOPT_POSTFIELDS, $invMpushpayload);
		// curl_setopt($ch, CURLOPT_USERPWD, 'rainAdmin:wAqGR_$z]N&J4e=w');   
		curl_setopt($invMpushch, CURLOPT_HTTPHEADER, [
		    "Content-Type: application/json"
		]);

		$responseM = curl_exec($invMpushch);

		print_r($responseM);

		curl_close($invMpushch);
	}
//Invoice Manager End
