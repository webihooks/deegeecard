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
    // Sanitize and trim form data
    $name = trim(htmlspecialchars($_POST['name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $phone = trim(htmlspecialchars($_POST['phone']));
    $address = trim(htmlspecialchars($_POST['address']));

    // Validate inputs
    $errors = [];

    // Name validation
    if (empty($name)) {
        $errors[] = "Name is required.";
    } elseif (strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters.";
    }

    // Email validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Phone validation (exactly 10 digits)
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }

    // Address validation (no line breaks)
    if (empty($address)) {
        $errors[] = "Address is required.";
    } elseif (preg_match('/[\r\n]/', $address)) {
        $errors[] = "Line breaks are not allowed in the address.";
    } elseif (strlen($address) < 5) {
        $errors[] = "Address must be at least 5 characters.";
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
        // Remove any remaining line breaks from address (just in case)
        $address = str_replace(["\r", "\n"], ' ', $address);
        
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $name, $email, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Profile updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating profile. Please try again.";
            // Log the error instead of showing it to users
            error_log("Profile update error: " . $stmt->error);
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