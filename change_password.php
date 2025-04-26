<?php
// Start the session
session_start();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['password_error'] = "All fields are required.";
        header("Location: profile.php");
        exit();
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['password_error'] = "New passwords do not match.";
        header("Location: profile.php");
        exit();
    }

    if (strlen($new_password) < 6) {
        $_SESSION['password_error'] = "Password must be at least 6 characters long.";
        header("Location: profile.php");
        exit();
    }

    // Get current password hash from database
    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    // Verify current password
    if (!password_verify($current_password, $hashed_password)) {
        $_SESSION['password_error'] = "Current password is incorrect.";
        header("Location: profile.php");
        exit();
    }

    // Hash the new password
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password in database
    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);

    if ($update_stmt->execute()) {
        $_SESSION['password_success'] = "Password changed successfully!";
    } else {
        $_SESSION['password_error'] = "Failed to change password. Please try again.";
    }

    $update_stmt->close();
    $conn->close();

    header("Location: profile.php");
    exit();
} else {
    // If not a POST request, redirect to profile
    header("Location: profile.php");
    exit();
}
?>