<?php
/**
 * Global SMS helper using Dialog PHP library
 * - Uses official SendSMSImpl / TokenBody / SendTextBody classes
 * - Automatically logs to sms_log
 */

require_once __DIR__ . '/db.php'; // your DB connection

// ONLY include the main library file.
// DO NOT include token_body.php / send_text_body.php separately.
require_once __DIR__ . '/PHP-SMS-API-lib-main/send_sms_impl.php';

class SMS_Helper
{
    // Your eSMS username (MUST be 9477xxxxxxx) and password
    private string $username = 'laepuser';   // TODO: change
    private string $password = 'Lndadnep@114'; // TODO: change

    // Sender mask (as configured in Dialog eSMS)
    private string $sourceAddress = 'EPLandAdmin';

    private SendSMSImpl $impl;

    public function __construct()
    {
        $this->impl = new SendSMSImpl();
    }

    /**
     * Global function:
     *  - $lease_id: lease_id from your system (can be 0 if not applicable)
     *  - $mobile: Sri Lankan mobile (07XXXXXXXX / 947XXXXXXXX)
     *  - $message: SMS text
     *
     * Returns array:
     * [
     *   'success'  => bool,
     *   'status'   => 'success' | 'failed',
     *   'comment'  => string,
     *   'errCode'  => int|null,
     *   'campaign' => string|null
     * ]
     */
    public function sendSMS(int $lease_id, string $mobile, string $message): array
    {
        global $con;

        try {
            // --------------------------------
            // 1. Get Access Token
            // --------------------------------
            $tokenBody = new TokenBody();
            $tokenBody->setUsername($this->username);
            $tokenBody->setPassword($this->password);

            $tokenResponse = $this->impl->getToken($tokenBody);

            $tokenStatus  = $tokenResponse->getStatus();
            $tokenComment = $tokenResponse->getComment();
            $tokenErrCode = $tokenResponse->getErrCode();
            $token        = $tokenResponse->getToken();

            if ($tokenStatus !== 'success' || !$token) {
                // Log failed token attempt (optional: no sms_log row here)
                return [
                    'success'  => false,
                    'status'   => $tokenStatus,
                    'comment'  => 'Token error: ' . $tokenComment,
                    'errCode'  => $tokenErrCode,
                    'campaign' => null
                ];
            }

            // --------------------------------
            // 2. Prepare SMS body
            // --------------------------------

            // Normalise mobile
            $mobile = preg_replace('/\D/', '', $mobile); // keep digits only
            // if starts with 0, remove it
            if (str_starts_with($mobile, '0')) {
                $mobile = substr($mobile, 1); // 7xxxxxxxx
            }
            // if starts with 94, remove 94
            if (str_starts_with($mobile, '94')) {
                $mobile = substr($mobile, 2); // 7xxxxxxxx
            }
            // At this point, Dialog library expects "7xxxxxxx"
            // It will add country code internally.

            $sendTextBody = new SendTextBody();
            $sendTextBody->setSourceAddress($this->sourceAddress);
            $sendTextBody->setMessage($message);
            $sendTextBody->setTransactionId((string)time()); // simple unique id

            // Set recipients array
            $sendTextBody->setMsisdn(
                $this->impl->setMsisdns([$mobile])
            );

            // --------------------------------
            // 3. Send SMS
            // --------------------------------
            $smsResponse = $this->impl->sendText($sendTextBody, $token);

            $smsStatus   = $smsResponse->getStatus();    // 'success' or 'failed'
            $smsComment  = $smsResponse->getComment();   // text
            $smsErrCode  = $smsResponse->getErrCode();   // int|null

            $campaignId  = null;
            $data        = $smsResponse->getData();
            if ($data && method_exists($data, 'getCampaignId')) {
                $campaignId = $data->getCampaignId();
            }

            // --------------------------------
            // 4. Insert into sms_log
            // --------------------------------
            $sql = "INSERT INTO sms_log 
                        (lease_id, mobile_number, sms_type, sms_text, sent_status, delivery_status, status)
                    VALUES (?, ?, 'SYSTEM', ?, ?, ?, 1)";

            $stmt = $con->prepare($sql);
            $sent_status     = $smsStatus;
            $delivery_status = $campaignId ?? '';

            $stmt->bind_param("issss", $lease_id, $mobile, $message, $sent_status, $delivery_status);
            $stmt->execute();

            return [
                'success'  => ($smsStatus === 'success'),
                'status'   => $smsStatus,
                'comment'  => $smsComment,
                'errCode'  => $smsErrCode,
                'campaign' => $campaignId
            ];
        } catch (\Throwable $e) {
            // Serious error (network, class loading etc.)
            return [
                'success'  => false,
                'status'   => 'failed',
                'comment'  => 'Exception: ' . $e->getMessage(),
                'errCode'  => null,
                'campaign' => null
            ];
        }
    }
}
