<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db_connection.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.0 405 Method Not Allowed");
    die("Only POST requests are allowed");
}

// Get raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!$data || !isset($data['user_id']) || !isset($data['order_type']) || !isset($data['items'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Invalid request data");
}

try {
    $conn->beginTransaction();
    
    // Calculate totals
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Apply discount if exists
    $discount_amount = $data['discount_amount'] ?? 0;
    $discount_type = $data['discount_type'] ?? null;
    
    // Calculate GST
    $gst_percent = $data['gst_percent'] ?? 0;
    $gst_amount = ($subtotal - $discount_amount) * ($gst_percent / 100);
    
    // Calculate delivery charge
    $delivery_charge = 0;
    if ($data['order_type'] === 'delivery') {
        $free_delivery_min = $data['free_delivery_min'] ?? 0;
        if ($subtotal - $discount_amount < $free_delivery_min) {
            $delivery_charge = $data['delivery_charge'] ?? 0;
        }
    }
    
    $total_amount = $subtotal - $discount_amount + $gst_amount + $delivery_charge;
    
    // Insert into orders table
    $order_sql = "INSERT INTO orders (
        user_id, 
        customer_name, 
        customer_phone, 
        order_type, 
        delivery_address, 
        table_number, 
        order_notes, 
        subtotal, 
        discount_amount, 
        discount_type, 
        gst_amount, 
        delivery_charge, 
        total_amount, 
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->execute([
        $data['user_id'],
        $data['customer_name'],
        $data['customer_phone'],
        $data['order_type'],
        $data['order_type'] === 'delivery' ? $data['delivery_address'] : null,
        $data['order_type'] === 'dining' ? $data['table_number'] : null,
        $data['order_notes'] ?? null,
        $subtotal,
        $discount_amount,
        $discount_type,
        $gst_amount,
        $delivery_charge,
        $total_amount,
        'pending' // Initial status
    ]);
    
    $order_id = $conn->lastInsertId();
    
    // Insert order items
    $item_sql = "INSERT INTO order_items (
        order_id, 
        user_id,
        product_name, 
        price, 
        quantity
    ) VALUES (?, ?, ?, ?, ?)";
    
    $item_stmt = $conn->prepare($item_sql);
    
    foreach ($data['items'] as $item) {
        $item_stmt->execute([
            $order_id,
            $data['user_id'],
            $item['name'],
            $item['price'],
            $item['quantity']
        ]);
    }
    
    $conn->commit();
    
    // Return success response
    header('Content-Type: application/json');
    // In place_order.php, modify the success response:
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Order placed successfully',
        'trigger_whatsapp' => true // Add this flag
    ]);
    
} catch (PDOException $e) {
    $conn->rollBack();
    header("HTTP/1.0 500 Internal Server Error");
    die("Database error: " . $e->getMessage());
}