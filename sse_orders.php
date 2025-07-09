<?php
require 'db_connection.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for Nginx

$user_id = $_SESSION['user_id'];
$last_order_id = isset($_GET['last_order_id']) ? (int)$_GET['last_order_id'] : 0;

function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Send initial heartbeat
sendSSE(['type' => 'heartbeat', 'message' => 'Connection established']);

while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        break;
    }
    
    // Get the latest order
    $sql = "SELECT o.order_id, o.customer_name, o.total_amount, o.created_at 
            FROM orders o
            WHERE o.user_id = ? AND o.order_id > ?
            ORDER BY o.order_id DESC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $last_order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $last_order_id = $row['order_id'];
        
        sendSSE([
            'type' => 'new_order',
            'order_id' => $row['order_id'],
            'customer_name' => $row['customer_name'],
            'total_amount' => $row['total_amount'],
            'created_at' => $row['created_at'],
            'message' => 'New order received'
        ]);
    }
    
    // Send heartbeat every 15 seconds
    if (time() % 15 == 0) {
        sendSSE(['type' => 'heartbeat', 'message' => 'Connection alive']);
    }
    
    sleep(1); // Check every second
}

$stmt->close();
$conn->close();
?>