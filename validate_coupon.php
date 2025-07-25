<?php
require_once 'config/db_connection.php';

header('Content-Type: application/json');

try {
    // Get input data with error handling
    $input = file_get_contents('php://input');
    if ($input === false) {
        throw new Exception('Failed to read input data');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    $couponCode = $data['coupon_code'] ?? '';
    $phoneNumber = $data['phone_number'] ?? '';
    $cartTotal = floatval($data['cart_total'] ?? 0);
    $userId = intval($data['user_id'] ?? 0);

    // Validate required fields
    if (empty($couponCode)) {
        throw new Exception('Coupon code is required');
    }

    if ($userId <= 0) {
        throw new Exception('Invalid user ID');
    }

    // Get coupon details with PDO error handling
    $couponQuery = "SELECT * FROM coupons 
                    WHERE user_id = :user_id 
                    AND coupon_code = :coupon_code
                    AND is_active = 1
                    AND (valid_from IS NULL OR valid_from <= NOW())
                    AND (valid_to IS NULL OR valid_to >= NOW())";
    
    $stmt = $conn->prepare($couponQuery);
    if (!$stmt) {
        throw new Exception('Failed to prepare coupon query: ' . implode(' ', $conn->errorInfo()));
    }

    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':coupon_code', $couponCode, PDO::PARAM_STR);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute coupon query: ' . implode(' ', $stmt->errorInfo()));
    }

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        throw new Exception('Invalid coupon code or expired');
    }

    // Check minimum cart value
    if ($cartTotal < $coupon['min_cart_value']) {
        $needed = $coupon['min_cart_value'] - $cartTotal;
        throw new Exception('Add ₹' . number_format($needed, 2) . ' more to use this coupon');
    }

    // Check usage limits
    if ($coupon['usage_limit'] > 0) {
        $usageQuery = "SELECT COUNT(*) as usage_count FROM coupon_usage 
                      WHERE coupon_id = :coupon_id";
        $usageStmt = $conn->prepare($usageQuery);
        if (!$usageStmt) {
            throw new Exception('Failed to prepare usage query');
        }
        
        $usageStmt->bindParam(':coupon_id', $coupon['id'], PDO::PARAM_INT);
        
        if (!$usageStmt->execute()) {
            throw new Exception('Failed to execute usage query');
        }
        
        $usageCount = $usageStmt->fetchColumn();
        if ($usageCount >= $coupon['usage_limit']) {
            throw new Exception('Coupon usage limit reached');
        }
    }

    // Check usage per customer
    if (!empty($phoneNumber) && $coupon['usage_per_customer'] > 0) {
        $customerUsageQuery = "SELECT COUNT(*) as customer_usage FROM coupon_usage 
                              WHERE coupon_id = :coupon_id AND phone_number = :phone_number";
        $customerUsageStmt = $conn->prepare($customerUsageQuery);
        if (!$customerUsageStmt) {
            throw new Exception('Failed to prepare customer usage query');
        }
        
        $customerUsageStmt->bindParam(':coupon_id', $coupon['id'], PDO::PARAM_INT);
        $customerUsageStmt->bindParam(':phone_number', $phoneNumber, PDO::PARAM_STR);
        
        if (!$customerUsageStmt->execute()) {
            throw new Exception('Failed to execute customer usage query');
        }
        
        $customerUsage = $customerUsageStmt->fetchColumn();
        if ($customerUsage >= $coupon['usage_per_customer']) {
            throw new Exception('You have already used this coupon');
        }
    }

    // Calculate discount amount
    $discountAmount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discountAmount = ($cartTotal * $coupon['discount_value']) / 100;
        
        // Apply max discount if set
        if ($coupon['max_discount'] > 0 && $discountAmount > $coupon['max_discount']) {
            $discountAmount = $coupon['max_discount'];
        }
    } else {
        $discountAmount = $coupon['discount_value'];
    }

    // Ensure discount doesn't exceed cart total
    $discountAmount = min($discountAmount, $cartTotal);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Coupon applied successfully',
        'coupon_id' => $coupon['id'],
        'coupon_code' => $coupon['coupon_code'],
        'coupon_name' => $coupon['coupon_name'] ?? $coupon['coupon_code'],
        'discount_amount' => $discountAmount
    ]);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Coupon validation error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}