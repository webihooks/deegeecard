<?php
session_start();
require 'config.php';
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

$user_id = $_SESSION['user_id'];
$package_id = $_POST['package_id'] ?? null;

if (!$package_id) {
    http_response_code(400);
    echo "Invalid package selected.";
    exit();
}

// Get package details
$sql = "SELECT name, duration FROM packages WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $package_id);
$stmt->execute();
$stmt->bind_result($package_name, $duration);
if (!$stmt->fetch()) {
    echo "Package not found.";
    exit();
}
$stmt->close();

// Optional: Verify Razorpay payment here using Razorpay API (not shown for brevity)

// Cancel any existing active subscription
$sql_cancel = "UPDATE subscriptions SET status = 'canceled', end_date = NOW() WHERE user_id = ? AND status = 'active'";
$stmt_cancel = $conn->prepare($sql_cancel);
$stmt_cancel->bind_param("i", $user_id);
$stmt_cancel->execute();
$stmt_cancel->close();

// Insert new subscription
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime("+$duration days"));
$subscription_type = $package_name; // e.g., Basic Package, etc.

$sql_insert = "INSERT INTO subscriptions (user_id, package_id, start_date, end_date, status, subscription_type) 
               VALUES (?, ?, ?, ?, 'active', ?)";
$stmt_insert = $conn->prepare($sql_insert);
$stmt_insert->bind_param("iisss", $user_id, $package_id, $start_date, $end_date, $subscription_type);

if ($stmt_insert->execute()) {
    echo "Subscription successful!";
} else {
    echo "Error processing subscription: " . $stmt_insert->error;
}

$stmt_insert->close();
$conn->close();
?>
