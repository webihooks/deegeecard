<?php
session_start();
require 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_SESSION['user_id'])) {
    $id = intval($_POST['id']);
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM loyalty_cards WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    http_response_code(200);
    exit;
}
http_response_code(400);
exit;
