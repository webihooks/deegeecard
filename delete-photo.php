<?php
session_start();
require 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if photo ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid photo ID';
    header("Location: photo-gallery.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$photo_id = (int)$_GET['id'];

try {
    // Begin transaction
    $conn->begin_transaction();

    // Get photo info (verify ownership and get file path)
    $stmt = $conn->prepare("SELECT photo_gallery_path FROM photo_gallery WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $photo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Photo not found or you don't have permission to delete it");
    }

    $photo = $result->fetch_assoc();
    $file_path = ltrim($photo['photo_gallery_path'], '/'); // Remove leading slash for filesystem path
    $absolute_path = $_SERVER['DOCUMENT_ROOT'] . $photo['photo_gallery_path'];

    // Delete from database first
    $delete_stmt = $conn->prepare("DELETE FROM photo_gallery WHERE id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $photo_id, $user_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception("Failed to delete photo from database");
    }

    // Delete the physical file
    if (file_exists($absolute_path)) {
        if (!unlink($absolute_path)) {
            throw new Exception("Failed to delete photo file");
        }
    } else {
        // File doesn't exist but we'll still count this as success
        error_log("Warning: Photo file not found at: " . $absolute_path);
    }

    // Delete the directory if it's empty
    $directory = dirname($absolute_path);
    if (is_dir($directory) && count(scandir($directory)) === 2) { // Directory is empty
        rmdir($directory);
    }

    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = 'Photo deleted successfully';
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
} finally {
    // Close statements
    if (isset($stmt)) $stmt->close();
    if (isset($delete_stmt)) $delete_stmt->close();
    
    header("Location: photo-gallery.php");
    exit();
}
?>