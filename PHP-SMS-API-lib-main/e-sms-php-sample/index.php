<!DOCTYPE html>
<html>
	
<head>
	<title>
		How to call PHP function
		on the click of a Button ?
	</title>
</head>

<body style="text-align:center;">
	
	<h1 style="color:cornflowerblue;">
		E-SMS
	</h1>
	
	<h4>
		Please Click button to send SMS
	</h4>
	
	<?php

    require('<YOUR_RELATIVE_PATH>/send-sms-php/send_sms_impl.php');

		if(array_key_exists('button1', $_POST)) {
			button1();
		}
		function button1() {

            $sendSmsImpl = new SendSMSImpl();

            #get access token
            $tokenBody = new TokenBody();
            $tokenBody->setUsername('<YOUR_USERNAME>');
            $tokenBody->setPassword('<YOUR_PASSWORD>');
            $token = $sendSmsImpl->getToken($tokenBody)->getToken();

            #configure message
            $sendTextBody = new SendTextBody();
            $sendTextBody->setSourceAddress('<YOUR_SOURCE_ADDRESS>');
            $sendTextBody->setMsisdn($sendSmsImpl->setMsisdns(array('<MSISDN1>','<MSISDN2>')));

            #random number generate for transaction id
            $t=time();

            #send message
            $sendTextBody->setTransactionId($t);
            $sendTextBody->setMessage('<YOUR_MESSAGE>');
            $transactionBody = new TransactionBody();
            $transactionBody->setTransactionId($t+'<RANDOM_NUMBER>');
            $response = $sendSmsImpl->sendText($sendTextBody, $token)->getData()->getUserId();

            echo $response;
		}
	?>

	<form method="post">
		<input type="submit" name="button1"
				class="button" value="Send SMS" />
	</form>
</body>

</html>
