<?php
header('Content-Type: application/json');
require_once 'config/db_connection.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Function to send error response
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    // Get and validate input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON input');
    }

    // Required fields validation
    $requiredFields = ['user_id', 'order_type', 'customer_name', 'customer_phone', 'items'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            sendError("Missing required field: $field");
        }
    }

    // Validate items array
    if (!is_array($input['items']) || count($input['items']) === 0) {
        sendError('Order must contain at least one item');
    }

    // Validate phone number
    if (!preg_match('/^[0-9]{10}$/', $input['customer_phone'])) {
        sendError('Invalid phone number format (10 digits required)');
    }

    // Additional validation based on order type
    if ($input['order_type'] === 'delivery') {
        if (empty($input['delivery_address'])) {
            sendError('Delivery address is required for delivery orders');
        }
    } elseif ($input['order_type'] === 'dining') {
        if (empty($input['table_number'])) {
            sendError('Table number is required for dining orders');
        }
    } else {
        sendError('Invalid order type');
    }

    // Start database transaction
    $conn->beginTransaction();

    // 1. Insert order header - matches your exact orders table structure
    $orderQuery = "INSERT INTO orders (
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
        status,
        created_at
    ) VALUES (
        :user_id, 
        :customer_name, 
        :customer_phone, 
        :order_type, 
        :delivery_address, 
        :table_number, 
        :order_notes,
        :subtotal,
        :discount_amount,
        :discount_type,
        :gst_amount,
        :delivery_charge,
        :total_amount,
        'pending',
        NOW()
    )";

    // Calculate order totals
    $subtotal = 0;
    foreach ($input['items'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

    $discountAmount = $input['discount_amount'] ?? 0;
    $amountAfterDiscount = max(0, $subtotal - $discountAmount);
    
    // Calculate GST
    $gstPercent = $input['gst_percent'] ?? 0;
    $gstAmount = $gstPercent > 0 ? ($amountAfterDiscount * $gstPercent / 100) : 0;
    
    // Calculate delivery charge
    $deliveryCharge = 0;
    if ($input['order_type'] === 'delivery') {
        $freeDeliveryMin = $input['free_delivery_min'] ?? 0;
        $deliveryChargeAmount = $input['delivery_charge'] ?? 0;
        
        if ($freeDeliveryMin <= 0 || $amountAfterDiscount < $freeDeliveryMin) {
            $deliveryCharge = $deliveryChargeAmount;
        }
    }

    $totalAmount = $amountAfterDiscount + $gstAmount + $deliveryCharge;

    $stmt = $conn->prepare($orderQuery);
    $stmt->execute([
        ':user_id' => $input['user_id'],
        ':customer_name' => $input['customer_name'],
        ':customer_phone' => $input['customer_phone'],
        ':order_type' => $input['order_type'],
        ':delivery_address' => $input['delivery_address'] ?? null,
        ':table_number' => $input['table_number'] ?? null,
        ':order_notes' => $input['order_notes'] ?? null,
        ':subtotal' => $subtotal,
        ':discount_amount' => $discountAmount,
        ':discount_type' => $input['discount_type'] ?? null,
        ':gst_amount' => $gstAmount,
        ':delivery_charge' => $deliveryCharge,
        ':total_amount' => $totalAmount
    ]);

    $orderId = $conn->lastInsertId();

    // 2. Insert order items - modified to match your order_items table
    $itemsQuery = "INSERT INTO order_items (
        order_id, 
        product_name, 
        price, 
        quantity
    ) VALUES (
        :order_id, 
        :product_name, 
        :price, 
        :quantity
    )";

    $stmt = $conn->prepare($itemsQuery);
    
    foreach ($input['items'] as $item) {
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_name' => $item['name'],
            ':price' => $item['price'],
            ':quantity' => $item['quantity']
        ]);
    }

    // 3. Record coupon usage if applicable
    if (!empty($input['coupon_code'])) {
        $couponQuery = "INSERT INTO coupon_usage (
            coupon_id,
            coupon_code,
            order_id,
            user_id,
            customer_phone,
            discount_amount,
            created_at
        ) VALUES (
            :coupon_id,
            :coupon_code,
            :order_id,
            :user_id,
            :customer_phone,
            :discount_amount,
            NOW()
        )";

        $stmt = $conn->prepare($couponQuery);
        $stmt->execute([
            ':coupon_id' => $input['coupon_id'] ?? null,
            ':coupon_code' => $input['coupon_code'],
            ':order_id' => $orderId,
            ':user_id' => $input['user_id'],
            ':customer_phone' => $input['customer_phone'],
            ':discount_amount' => $discountAmount
        ]);
    }

    // Commit transaction
    $conn->commit();

    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $orderId,
        'trigger_whatsapp' => true,
        'order_summary' => [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'gst_amount' => $gstAmount,
            'delivery_charge' => $deliveryCharge,
            'total_amount' => $totalAmount
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    sendError('Database error: ' . $e->getMessage(), 500);
    
} catch (Exception $e) {
    // Rollback transaction on other errors
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    sendError($e->getMessage());
}
?>