<?php
session_start();
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Set connection charset to UTF-8
$conn->set_charset("utf8mb4");

// Search condition
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (customer_name LIKE '%$search%' OR 
                          customer_phone LIKE '%$search%' OR 
                          delivery_address LIKE '%$search%')";
}

// Clean phone number function
function cleanPhoneNumber($phone) {
    $phone = trim($phone);
    if (empty($phone) || strtoupper($phone) === 'NA') {
        return null;
    }
    return $phone;
}

// Fetch all phone numbers from both tables for the specific user
$sql = "(SELECT DISTINCT customer_phone
        FROM orders 
        WHERE user_id = $user_id $search_condition)
        
        UNION
        
        (SELECT DISTINCT customer_phone
         FROM customer_data 
         WHERE user_id = $user_id $search_condition)";

$result = $conn->query($sql);
$phones = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $phone = cleanPhoneNumber($row['customer_phone']);
        if (!empty($phone)) {
            $phones[] = $phone;
        }
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'phones' => $phones,
    'count' => count($phones),
    'user_id' => $user_id, // For debugging
    'search' => $search // For debugging
]);
?>