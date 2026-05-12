<?php
include('C:\xampp\htdocs\inv\data.php');
include('C:\xampp\htdocs\inv\postfolder\connection.php');

$days = 1;
$date=date_create(date('Y-m-d'));
date_sub($date,date_interval_create_from_date_string($days." day"));
  
$invdate = date_format($date,"Ymd");


$url = "http://192.168.0.17/invcentral/sync/storepullingapi.php?store_code=".$store_code."&days=".$days;

$ch = curl_init($url);


curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
	    echo "cURL Error: " . curl_error($ch);
} else {

	$data = json_decode($response, true);

	$reqSelect = $bdd->prepare('SELECT count(id) as idcount FROM erpdata WHERE invdate = ?');
	$reqSelect->execute(array($invdate));
	$reqSelect = $reqSelect->fetch();


	if($reqSelect['idcount'] != count($data)){
		$reqdelete = $bdd->prepare('DELETE FROM erpdata WHERE invdate = ?');
		$reqdelete->execute(array($invdate));

		foreach ($data as $row) {
			
			$req = $bdd->prepare('INSERT INTO 
					erpdata(invno, store_code, amt, invdate, invtime, tillno, cashier, entry_time) 
					VALUES(?, ?, ?, ?, ?, ?, ?, ?)');
			$req->execute(array(
				htmlspecialchars($row['invno']),
				htmlspecialchars($row['store_code']),
				htmlspecialchars($row['amt']),
				htmlspecialchars($row['invdate']),
				htmlspecialchars($row['invtime']),
				htmlspecialchars($row['tillno']),
				htmlspecialchars($row['cashier']),
				htmlspecialchars($row['entry_time'])
			));
		}
	}
	
}

curl_close($ch);
echo 'done';
