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

// Column headers - updated to include id, user_id, and image_path
fputcsv($output, ['ID', 'User ID', 'Product Name', 'Description', 'Price', 'Quantity', 'Image Path']);

$sql = "SELECT id, user_id, product_name, description, price, quantity, image_path FROM products WHERE user_id = ? ORDER BY product_name";
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