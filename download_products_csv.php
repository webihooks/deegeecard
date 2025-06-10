<?php
session_start();
require 'db_connection.php';

// Authentication and admin check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_sql = "SELECT role FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if ($user['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch all products
$sql = "SELECT mp.id, mp.product_name, mp.description, mp.image_path, mp.status, 
               u.name as user_name, mp.created_at 
        FROM master_products mp 
        JOIN users u ON mp.user_id = u.id 
        ORDER BY mp.product_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the CSV column headers
fputcsv($output, [
    'ID', 
    'Product Name', 
    'Description', 
    'Image URL', 
    'Status', 
    'Added By', 
    'Created At'
]);

// Loop through the products and write them to CSV
foreach ($products as $product) {
    fputcsv($output, [
        $product['id'],
        $product['product_name'],
        $product['description'],
        $product['image_path'],
        $product['status'],
        $product['user_name'],
        $product['created_at']
    ]);
}

fclose($output);
exit();
?>