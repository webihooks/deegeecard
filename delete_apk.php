<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current file path
$sql = "SELECT file_path FROM user_apks WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($filePath);
$stmt->fetch();
$stmt->close();

// Delete from database
$sql = "DELETE FROM user_apks WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Delete file
if (!empty($filePath) && file_exists($filePath)) {
    unlink($filePath);
}

$_SESSION['message'] = "APK deleted successfully.";
header("Location: upload_apk.php");
exit();
?>