<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_products_table = "products_" . $user_id;

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=products.csv');

$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, [
    'ID', 
    'Product Name', 
    'Description', 
    'Price', 
    'Quantity', 
    'Image Path',
    'Tag ID'
]);

// Query to get products from the user-specific table
$sql = "SELECT 
            p.id, 
            p.product_name, 
            p.description, 
            p.price, 
            p.quantity, 
            p.image_path,
            p.tag_id
        FROM $user_products_table p
        LEFT JOIN tags t ON p.tag_id = t.id
        ORDER BY p.id ASC";

$stmt = $conn->prepare($sql);
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