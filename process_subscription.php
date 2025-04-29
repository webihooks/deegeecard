<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'vendor/autoload.php';
require 'config.php';
require 'db_connection.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

$user_id = $_SESSION['user_id'];

// Razorpay API init
$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

try {
    // Verify the payment signature
    $attributes = [
        'razorpay_order_id' => $_POST['razorpay_order_id'],
        'razorpay_payment_id' => $_POST['razorpay_payment_id'],
        'razorpay_signature' => $_POST['razorpay_signature']
    ];
    $api->utility->verifyPaymentSignature($attributes);

    $package_id = intval($_POST['package_id']);
    $payment_id = $_POST['razorpay_payment_id'];
    $order_id = $_POST['razorpay_order_id'];
    $payment_date = date('Y-m-d H:i:s');

    // Fetch package price
    $stmt_price = $conn->prepare("SELECT price FROM packages WHERE id = ?");
    if (!$stmt_price) {
        throw new Exception("Package price query preparation failed: " . $conn->error);
    }
    $stmt_price->bind_param("i", $package_id);
    $stmt_price->execute();
    $stmt_price->bind_result($amount);
    if (!$stmt_price->fetch()) {
        throw new Exception("Package not found.");
    }
    $stmt_price->close();

    // Define subscription period
    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime('+1 month')); // 1 month validity

    // Insert into subscriptions table (no payment_method)
    $stmt_sub = $conn->prepare("INSERT INTO subscriptions (user_id, package_id, start_date, end_date, status, payment_id) VALUES (?, ?, ?, ?, 'active', ?)");
    if (!$stmt_sub) {
        throw new Exception("Subscription insert query preparation failed: " . $conn->error);
    }
    $stmt_sub->bind_param("iisss", $user_id, $package_id, $start_date, $end_date, $payment_id);
    $stmt_sub->execute();
    $stmt_sub->close();

    echo "Subscription activated successfully!";

} catch (SignatureVerificationError $e) {
    echo "Signature verification failed: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
