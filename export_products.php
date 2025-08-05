<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=products.csv');

$output = fopen('php://output', 'w');

// Column headers - includes Tag ID but not Tag Name
fputcsv($output, [
    'ID', 
    'User ID', 
    'Product Name', 
    'Description', 
    'Price', 
    'Quantity', 
    'Image Path',
    'Tag ID'
]);

// Query to get products sorted by ID in ascending order
$sql = "SELECT 
            p.id, 
            p.user_id, 
            p.product_name, 
            p.description, 
            p.price, 
            p.quantity, 
            p.image_path,
            p.tag_id
        FROM products p
        WHERE p.user_id = ? 
        ORDER BY p.id ASC";  // Changed to sort by ID ascending

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
$stmt->close();
$conn->close();
exit();
?>