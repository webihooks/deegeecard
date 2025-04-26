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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $facebook = filter_input(INPUT_POST, 'facebook', FILTER_SANITIZE_URL);
    $instagram = filter_input(INPUT_POST, 'instagram', FILTER_SANITIZE_URL);
    $whatsapp = filter_input(INPUT_POST, 'whatsapp', FILTER_SANITIZE_URL);
    $linkedin = filter_input(INPUT_POST, 'linkedin', FILTER_SANITIZE_URL);
    $youtube = filter_input(INPUT_POST, 'youtube', FILTER_SANITIZE_URL);
    $telegram = filter_input(INPUT_POST, 'telegram', FILTER_SANITIZE_URL);

    // Check if the user already has social links
    $check_sql = "SELECT id FROM social_link WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        // Update existing record
        $sql = "UPDATE social_link SET 
                facebook = ?, 
                instagram = ?, 
                whatsapp = ?, 
                linkedin = ?, 
                youtube = ?, 
                telegram = ? 
                WHERE user_id = ?";
    } else {
        // Insert new record
        $sql = "INSERT INTO social_link (
                facebook, 
                instagram, 
                whatsapp, 
                linkedin, 
                youtube, 
                telegram, 
                user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    }
    
    $stmt = $conn->prepare($sql);
    
    if ($check_stmt->num_rows > 0) {
        $stmt->bind_param("ssssssi", $facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram, $user_id);
    } else {
        $stmt->bind_param("ssssssi", $facebook, $instagram, $whatsapp, $linkedin, $youtube, $telegram, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Social links updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating social links: " . $conn->error;
    }
    
    $stmt->close();
    $check_stmt->close();
    $conn->close();
    
    header("Location: social.php");
    exit();
}
?>