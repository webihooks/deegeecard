<?php
require_once 'config/db_connection.php';

header('Content-Type: application/json');

try {
    // Get JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['coupon_code']) || empty($data['user_id'])) {
        throw new Exception('Invalid request data');
    }
    
    // Sanitize inputs
    $coupon_code = trim($data['coupon_code']);
    $user_id = (int)$data['user_id'];
    $mobile_number = isset($data['mobile_number']) ? preg_replace('/[^0-9]/', '', $data['mobile_number']) : '';
    $cart_total = isset($data['cart_total']) ? (float)$data['cart_total'] : 0;
    
    // Basic validation
    if (!preg_match('/^[A-Z0-9-_]+$/i', $coupon_code)) {
        throw new Exception('Invalid coupon code format');
    }
    
    if ($user_id <= 0) {
        throw new Exception('Invalid user');
    }
    
    // Get coupon details with usage count
    $stmt = $conn->prepare("
        SELECT c.*, 
        (SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = c.id AND mobile_number = ?) as usage_count
        FROM coupons c
        WHERE c.coupon_code = ? 
        AND c.user_id = ?
        AND c.is_active = 1
        AND (c.valid_from IS NULL OR c.valid_from <= CURDATE())
        AND (c.valid_to IS NULL OR c.valid_to >= CURDATE())
    ");
    $stmt->execute([$mobile_number, $coupon_code, $user_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        throw new Exception('Invalid or expired coupon code');
    }
    
    // Check usage limit
    if ($coupon['usage_limit'] > 0 && $coupon['usage_count'] >= $coupon['usage_limit']) {
        throw new Exception('You have already used this coupon the maximum number of times');
    }
    
    // Check minimum cart value
    if ($coupon['min_cart_value'] > 0 && $cart_total < $coupon['min_cart_value']) {
        $needed = $coupon['min_cart_value'] - $cart_total;
        throw new Exception(sprintf(
            'Add ₹%s more to your cart to use this coupon',
            number_format($needed, 2)
        ));
    }
    
    // Record coupon usage if mobile number provided
    if (!empty($mobile_number)) {
        $stmt = $conn->prepare("
            INSERT INTO coupon_usage 
            (coupon_id, user_id, mobile_number, used_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$coupon['id'], $user_id, $mobile_number]);
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Coupon applied successfully!',
        'coupon' => [
            'id' => $coupon['id'],
            'coupon_code' => $coupon['coupon_code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => (float)$coupon['discount_value'],
            'min_cart_value' => (float)$coupon['min_cart_value'],
            'max_discount' => $coupon['max_discount'] ? (float)$coupon['max_discount'] : null,
            'valid_days' => $coupon['valid_days'] ?? null
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database error in validate_coupon: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error. Please try again.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}