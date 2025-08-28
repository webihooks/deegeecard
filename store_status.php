<?php
// store_status.php - Debug version
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config/db_connection.php';

header('Content-Type: application/json');

try {
    if (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        echo json_encode(['debug' => 'User ID received', 'user_id' => $user_id]);
    } else {
        echo json_encode(['error' => 'No user_id provided']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>