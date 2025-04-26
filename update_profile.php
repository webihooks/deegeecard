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
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    // Validate inputs
    $errors = [];

    if (empty($name)) {
        $errors[] = "Name is required.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = "Phone number must be 10-15 digits.";
    }

    if (empty($address)) {
        $errors[] = "Address is required.";
    }

    // Check if email already exists for another user
    $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $errors[] = "Email already exists for another user.";
    }
    $stmt->close();

    // If no errors, update the profile
    if (empty($errors)) {
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $email, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Profile updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_msg'] = implode("<br>", $errors);
    }

    $conn->close();
    
    // Redirect back to profile page
    header("Location: profile.php");
    exit();
} else {
    // If not a POST request, redirect to profile page
    header("Location: profile.php");
    exit();
}
?>