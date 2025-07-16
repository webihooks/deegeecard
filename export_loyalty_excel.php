<?php
require 'db_connection.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}
$user_id = $_SESSION['user_id'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Loyalty Cards");

// Headers
$sheet->fromArray(['Card Number', 'Name', 'Mobile', 'Address', 'Created At'], NULL, 'A1');

// Data
$sql = "SELECT card_number, full_name, mobile, address, created_at FROM loyalty_cards WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rowIndex = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->fromArray(array_values($row), NULL, "A$rowIndex");
    $rowIndex++;
}

// Output
$filename = "loyalty_cards_" . date("Ymd_His") . ".xlsx";
header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
