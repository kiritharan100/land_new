<?php 
require_once __DIR__ . '/sms_helper.php';

$sms = new SMS_Helper();

$sms_type = 'Test SMS'; // user-defined SMS type tag

$result = $sms->sendSMS(
    $lease_id = 123,           // or 0
    $mobile   = '0770888501',
    $message  = 'Test SMS from eSMS API',
    $sms_type
);

 

if ($result['success']) {
    echo "SMS sent and logged!";
} else {
    echo "Failed: " . $result['comment'];
}