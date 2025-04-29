<?php
require('vendor/autoload.php');
require('config.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $attributes = [
        'razorpay_order_id' => $_POST['razorpay_order_id'],
        'razorpay_payment_id' => $_POST['razorpay_payment_id'],
        'razorpay_signature' => $_POST['razorpay_signature']
    ];

    try {
        $api->utility->verifyPaymentSignature($attributes);
        echo "Payment Successful!";
    } catch (SignatureVerificationError $e) {
        echo "Payment Verification Failed: " . $e->getMessage();
    }
}
?>
