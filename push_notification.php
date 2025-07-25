<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require 'db_connection.php';

$user_id= $_GET['user_id'];
// Query to get the last order's created_at time
$result = $conn->query("SELECT created_at FROM orders WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        "last_order_time" => $row['created_at']
    ]);
} else {
    echo json_encode([
        "last_order_time" => null
    ]);
}
?>