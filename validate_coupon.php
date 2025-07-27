<?php
header('Content-Type: application/json');
require_once 'config/db_connection.php';

$input = json_decode(file_get_contents('php://input'), true); // Remove the extra parenthesis here
$user_id = $input['user_id'] ?? 0;
$coupon_code = $input['coupon_code'] ?? '';
$cart_subtotal = $input['cart_subtotal'] ?? 0;

// Validate input
if (empty($coupon_code)) { // Added missing parenthesis here
    echo json_encode(['success' => false, 'message' => 'Coupon code is required']);
    exit;
}

try {
    // Check if coupon exists and is valid
    $stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE user_id = ? 
        AND coupon_code = ?
        AND start_date <= CURDATE() 
        AND (end_date >= CURDATE() OR end_date IS NULL)
        AND is_active = 1
    ");
    $stmt->execute([$user_id, $coupon_code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
        exit;
    }

    // 2. Validate discount value (THIS IS WHERE YOU ADD THE VALIDATION)
	$discountValue = (float)$coupon['discount_value'];
	if (!is_numeric($discountValue)) {
	    echo json_encode([
	        'success' => false,
	        'message' => 'Invalid coupon value'
	    ]);
	    exit;
	}

    // Check minimum cart value if applicable
    if ($coupon['min_cart_value'] > 0 && $cart_subtotal < $coupon['min_cart_value']) {
        $needed = $coupon['min_cart_value'] - $cart_subtotal;
        echo json_encode([
            'success' => false, 
            'message' => 'Add ₹' . number_format($needed, 2) . ' more to use this coupon'
        ]);
        exit;
    }

    // Check usage limits
    $usage_stmt = $conn->prepare("
        SELECT COUNT(*) as usage_count 
        FROM coupon_redemptions 
        WHERE coupon_id = ? AND user_id = ?
    ");
    $usage_stmt->execute([$coupon['id'], $user_id]);
    $usage = $usage_stmt->fetch(PDO::FETCH_ASSOC);

    if ($coupon['usage_limit'] > 0 && $usage['usage_count'] >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'message' => 'This coupon has reached its usage limit']);
        exit;
    }

    // Prepare response
    $response = [
	    'success' => true,
	    'message' => 'Coupon applied successfully',
	    'discount_type' => $coupon['discount_type'], // 'percentage' or 'flat'
	    'discount_value' => $coupon['discount_value'],
	    'coupon_code' => $coupon['coupon_code'] // Make sure to include this
	];
	echo json_encode($response);

} catch (PDOException $e) {
    error_log("Coupon validation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}