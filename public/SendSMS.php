<?php

require '../lib/way2sms-api.php';
require '../lib/160by2-api/160by2-new-api.php';


$account = $_REQUEST['acc'] ? $_REQUEST['acc'] : 0;


echo '<PRE>'; var_dump($_REQUEST);echo '</PRE>';

if ( isset($_REQUEST['user']) && isset($_REQUEST['pass']) && isset($_REQUEST['mobno']) && isset($_REQUEST['msg'])) {
	$user = $_REQUEST['user'];
	$pass = $_REQUEST['pass'];
	$mobNo = $_REQUEST['mobno'];
	$message = $_REQUEST['msg'];
	
	$res=0;
	if($account == 0){
		$res = sendWay2SMS($user, $pass, $mobNo, $message);
	}
	else if($account == 1){
		$res = sendSMS160by2($user, $pass, $mobNo, $message);
	}
	echo '<PRE>'; var_dump($res);echo '</PRE>';
}

?>