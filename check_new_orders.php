<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];
$last_order_id = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Validate dates
if (!strtotime($from_date)) $from_date = date('Y-m-d');
if (!strtotime($to_date)) $to_date = date('Y-m-d');

// Get new orders since last_order_id within the date range
$sql = "SELECT order_id FROM orders 
        WHERE user_id = ? 
        AND order_id > ? 
        AND DATE(created_at) BETWEEN ? AND ?
        ORDER BY order_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiss", $user_id, $last_order_id, $from_date, $to_date);
$stmt->execute();
$result = $stmt->get_result();

$new_orders = [];
while ($row = $result->fetch_assoc()) {
    $new_orders[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'new_orders' => $new_orders,
    'current_filter_from' => $from_date,
    'current_filter_to' => $to_date
]);
?>