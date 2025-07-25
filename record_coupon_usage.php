<?php
require_once 'config/db_connection.php';

if (!$conn) {
    file_put_contents('coupon_debug.log', "DB connection failed\n", FILE_APPEND);
    throw new Exception('Database connection failed');
}

header('Content-Type: application/json');

try {
    // Validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    $requiredFields = ['coupon_id', 'phone_number', 'order_id', 'discount_amount', 'cart_total', 'user_id'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate data types
    $couponId = (int)$input['coupon_id'];
    $phoneNumber = preg_replace('/[^0-9]/', '', $input['phone_number']);
    $orderId = (int)$input['order_id'];
    $discountAmount = (float)$input['discount_amount'];
    $cartTotal = (float)$input['cart_total'];
    $userId = (int)$input['user_id'];

    if (strlen($phoneNumber) < 10) {
        throw new Exception('Invalid phone number (must be at least 10 digits)');
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // 1. Record in coupon_usage table
        $usageQuery = "INSERT INTO coupon_usage 
                      (coupon_id, phone_number, order_id, used_at)
                      VALUES (:coupon_id, :phone_number, :order_id, NOW())";
        $usageStmt = $conn->prepare($usageQuery);
        $usageStmt->bindParam(':coupon_id', $couponId, PDO::PARAM_INT);
        $usageStmt->bindParam(':phone_number', $phoneNumber, PDO::PARAM_STR);
        $usageStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        
        if (!$usageStmt->execute()) {
            throw new Exception('Failed to record coupon usage: ' . implode(' ', $usageStmt->errorInfo()));
        }

        // 2. Record in coupon_redemptions table
        $redemptionQuery = "INSERT INTO coupon_redemptions
                          (coupon_id, order_id, customer_phone, discount_amount, cart_total, redeemed_at, user_id)
                          VALUES (:coupon_id, :order_id, :phone_number, :discount_amount, :cart_total, NOW(), :user_id)";
        $redemptionStmt = $conn->prepare($redemptionQuery);
        $redemptionStmt->bindParam(':coupon_id', $couponId, PDO::PARAM_INT);
        $redemptionStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $redemptionStmt->bindParam(':phone_number', $phoneNumber, PDO::PARAM_STR);
        $redemptionStmt->bindParam(':discount_amount', $discountAmount);
        $redemptionStmt->bindParam(':cart_total', $cartTotal);
        $redemptionStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        if (!$redemptionStmt->execute()) {
            throw new Exception('Failed to record coupon redemption: ' . implode(' ', $redemptionStmt->errorInfo()));
        }

        // 3. Update coupon usage count if needed
        $updateQuery = "UPDATE coupons 
                       SET times_used = times_used + 1,
                           last_used_at = NOW()
                       WHERE id = :coupon_id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':coupon_id', $couponId, PDO::PARAM_INT);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update coupon usage count: ' . implode(' ', $updateStmt->errorInfo()));
        }

        // Commit transaction
        $conn->commit();

        // Log successful redemption
        error_log("Coupon recorded successfully: CouponID=$couponId, OrderID=$orderId, Phone=$phoneNumber");

        echo json_encode([
            'success' => true,
            'message' => 'Coupon usage recorded successfully'
        ]);

    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollBack();
        throw $e; // Re-throw to outer catch block
    }

} catch (Exception $e) {
    error_log("Coupon usage error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}