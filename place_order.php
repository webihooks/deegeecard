<?php
error_log("Received order data: " . print_r($input, true));
header('Content-Type: application/json');
require_once 'config/db_connection.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    $conn->beginTransaction();

    // 1. First, insert the order
    $orderSql = "INSERT INTO orders (
        user_id, 
        order_type, 
        customer_name, 
        customer_phone, 
        delivery_address, 
        table_number, 
        order_notes, 
        subtotal, 
        discount_amount, 
        gst_amount, 
        delivery_charge, 
        total_amount,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    // Calculate order totals
    $subtotal = array_reduce($input['items'], function($sum, $item) {
        return $sum + ($item['price'] * $item['quantity']);
    }, 0);
    
    $discountAmount = $input['discount_amount'] ?? 0;
    $amountAfterDiscount = $subtotal - $discountAmount;
    $gstAmount = ($amountAfterDiscount * $input['gst_percent']) / 100;
    $deliveryCharge = ($input['order_type'] === 'delivery' && 
                      ($input['free_delivery_min'] == 0 || $amountAfterDiscount < $input['free_delivery_min'])) 
                      ? $input['delivery_charge'] : 0;
    $total = $amountAfterDiscount + $gstAmount + $deliveryCharge;
    
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->execute([
        $input['user_id'],
        $input['order_type'],
        $input['customer_name'],
        $input['customer_phone'],
        $input['delivery_address'],
        $input['table_number'],
        $input['order_notes'],
        $subtotal,
        $discountAmount,
        $gstAmount,
        $deliveryCharge,
        $total
    ]);
    
    $orderId = $conn->lastInsertId();
    
    // 2. Insert order items
    $itemSql = "INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)";
    $itemStmt = $conn->prepare($itemSql);
    
    foreach ($input['items'] as $item) {
        $itemStmt->execute([
            $orderId,
            $item['name'],
            $item['price'],
            $item['quantity']
        ]);
    }
    
    // 3. Record coupon redemption if coupon was used
    if (!empty($input['coupon_data']) && !empty($input['customer_phone'])) {
        try {
            // 1. Get coupon details
            $couponStmt = $conn->prepare("
                SELECT id FROM coupons 
                WHERE user_id = ? AND coupon_code = ?
            ");
            $couponStmt->execute([
                $input['user_id'],
                $input['coupon_data']['code']
            ]);
            $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

            if ($coupon) {
                // 2. Insert redemption record
                $redemptionStmt = $conn->prepare("
                    INSERT INTO coupon_redemptions (
                        coupon_id,
                        user_id,
                        customer_phone,
                        order_id,
                        discount_amount
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                $success = $redemptionStmt->execute([
                    $coupon['id'],
                    $input['user_id'],
                    $input['customer_phone'],
                    $orderId,
                    $input['discount_amount']
                ]);
                
                if (!$success) {
                    error_log("Redemption failed: " . print_r($redemptionStmt->errorInfo(), true));
                }
            }
        } catch (PDOException $e) {
            error_log("Coupon redemption error: " . $e->getMessage());
            // Don't fail the whole order because of redemption error
        }
    }



    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'trigger_whatsapp' => true, // Set based on your business logic
        'clear_cart' => true // Add this flag to indicate cart should be cleared
    ]);

    exit(); // Always exit after JSON response
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Order placement error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to place order. Please try again.'
    ]);
}



