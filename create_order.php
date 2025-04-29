<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';

use Razorpay\Api\Api;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

if (!isset($_POST['amount'])) {
    http_response_code(400);
    echo "Amount missing";
    exit();
}

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

$orderData = [
    'receipt'         => uniqid('rcptid_'),
    'amount'          => $_POST['amount'], // amount in paise
    'currency'        => 'INR',
    'payment_capture' => 1
];

$order = $api->order->create($orderData);

echo json_encode(['order_id' => $order['id']]);
