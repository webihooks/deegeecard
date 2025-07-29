<?php
// Start the session to access user data
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata'); // for Indian Standard Time

require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's role
$role = '';
$user_info_sql = "SELECT role FROM users WHERE id = ?";
$user_info_stmt = $conn->prepare($user_info_sql);

if ($user_info_stmt) {
    $user_info_stmt->bind_param("i", $user_id);
    $user_info_stmt->execute();
    $user_info_stmt->bind_result($role);
    $user_info_stmt->fetch();
    $user_info_stmt->close();
}

// Get filter parameters from GET request
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
$follow_up_filter = isset($_GET['follow_up_filter']) ? trim($_GET['follow_up_filter']) : '';
$owner_filter = isset($_GET['owner_filter']) ? (int)$_GET['owner_filter'] : -1;
$sales_person_filter = isset($_GET['sales_person_filter']) ? (int)$_GET['sales_person_filter'] : ($role === 'admin' ? 0 : $user_id);
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : 'in process';

// Build WHERE clause (same as in your main page)
$where_clauses = [];
$params = [];
$param_types = '';

// For non-admin users, restrict to their own records
if ($role !== 'admin') {
    $where_clauses[] = "user_id = ?";
    $params[] = $user_id;
    $param_types .= 'i';
} 
// For admin users, apply sales person filter if selected
elseif ($sales_person_filter > 0) {
    $where_clauses[] = "user_id = ?";
    $params[] = $sales_person_filter;
    $param_types .= 'i';
}

// Add other filters
if (!empty($search_query)) {
    $where_clauses[] = "(restaurant_name LIKE ? OR 
                        contacted_person LIKE ? OR 
                        phone LIKE ? OR 
                        decision_maker_name LIKE ? OR 
                        decision_maker_phone LIKE ? OR 
                        CONCAT(street, ' ', city, ' ', state, ' ', location) LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, array_fill(0, 6, $search_param));
    $param_types .= str_repeat('s', 6);
}

if (!empty($date_filter)) {
    $where_clauses[] = "record_date = ?";
    $params[] = $date_filter;
    $param_types .= 's';
}

if (!empty($follow_up_filter)) {
    $where_clauses[] = "follow_up_date = ?";
    $params[] = $follow_up_filter;
    $param_types .= 's';
}

if ($owner_filter >= 0) {
    $where_clauses[] = "owner_available = ?";
    $params[] = $owner_filter;
    $param_types .= 'i';
}

// Status filter
if (empty($_GET['status_filter'])) {
    $where_clauses[] = "status = ?";
    $params[] = 'in process';
    $param_types .= 's';
} elseif (!empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

// Determine order based on whether date filter is applied
$order_by = !empty($date_filter) ? "record_date DESC, time_stamp DESC" : "follow_up_date ASC";

// Fetch records
$fetch_sales_sql = "SELECT 
    id, user_id, user_name, record_date, time_stamp, 
    restaurant_name, contacted_person, phone, 
    decision_maker_name, decision_maker_phone, 
    location, street, city, state, 
    postal_code, country, follow_up_date, 
    package_price, remark, owner_available, status,
    CONCAT(street, ' ', city, ' ', state, ' ', location) AS full_address
    FROM sales_track 
    $where_sql
    ORDER BY $order_by";

$fetch_sales_stmt = $conn->prepare($fetch_sales_sql);

if ($fetch_sales_stmt) {
    if (!empty($params)) {
        $fetch_sales_stmt->bind_param($param_types, ...$params);
    }
    
    $fetch_sales_stmt->execute();
    $result = $fetch_sales_stmt->get_result();
    $sales_data = [];
    while ($row = $result->fetch_assoc()) {
        $sales_data[] = $row;
    }
    $fetch_sales_stmt->close();
}

$conn->close();

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sales_records_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV headers
$headers = [
    'ID', 'User ID', 'User Name', 'Date', 'Time', 
    'Restaurant Name', 'Contact Person', 'Phone',
    'Decision Maker', 'Decision Maker Phone',
    'Location', 'Street', 'City', 'State',
    'Postal Code', 'Country', 'Follow Up Date',
    'Package Price', 'Owner Available', 'Status',
    'Full Address', 'Remarks'
];
fputcsv($output, $headers);

// Write data rows
foreach ($sales_data as $row) {
    $csv_row = [
        $row['id'],
        $row['user_id'],
        $row['user_name'],
        $row['record_date'],
        date('h:i A', strtotime($row['time_stamp'])),
        $row['restaurant_name'],
        $row['contacted_person'],
        $row['phone'],
        $row['decision_maker_name'],
        $row['decision_maker_phone'],
        $row['location'],
        $row['street'],
        $row['city'],
        $row['state'],
        $row['postal_code'],
        $row['country'],
        $row['follow_up_date'],
        $row['package_price'],
        $row['owner_available'] ? 'Yes' : 'No',
        ucfirst($row['status']),
        $row['full_address'],
        str_replace("\n", " | ", $row['remark']) // Replace newlines with pipes for CSV
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
exit();