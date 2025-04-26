<?php
session_start();
require 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/users/' . $user_id . '/';

// Create user's upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$errors = [];
$max_file_size = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate file upload
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } else {
        $file = $_FILES['photo'];
        
        // Validate file size
        if ($file['size'] > $max_file_size) {
            $errors[] = 'File size exceeds maximum limit of 5MB.';
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = 'Only JPG, PNG, GIF, and WebP images are allowed.';
        }
        
        // Generate unique filename
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . strtolower($file_ext);
        $destination = $upload_dir . $filename;
        $web_path = $upload_dir . $filename; // Removed leading slash here
        
        // Get title and description
        $title = !empty($_POST['title']) ? trim($_POST['title']) : null;
        $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
        
        if (empty($errors)) {
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO photo_gallery 
                                      (user_id, filename, photo_gallery_path, title, description) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $filename, $web_path, $title, $description);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Photo uploaded successfully!';
                    header("Location: photo-gallery.php");
                    exit();
                } else {
                    // Delete the uploaded file if DB insert fails
                    unlink($destination);
                    $errors[] = 'Database error: ' . $conn->error;
                }
            } else {
                $errors[] = 'Failed to move uploaded file.';
            }
        }
    }
}

// If we get here, there was an error
$_SESSION['errors'] = $errors;
header("Location: photo-gallery.php");
exit();
?>