<?php
require 'config/db_connection.php';
require 'functions/profile_functions.php';

// Validate profile URL
if (!isset($_GET['profile_url'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Profile URL is required");
}

$profile_url = $_GET['profile_url'];

// Get user ID from profile URL
$profile_data = getUserByProfileUrl($conn, $profile_url);
if (!$profile_data) {
    header("Location: page-not-found.php");
    exit();
}

$user_id = $profile_data['user_id'];

// Check for active subscription
$subscription_sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()";
$subscription_stmt = $conn->prepare($subscription_sql);
$subscription_stmt->execute([$user_id]);
$active_subscription = $subscription_stmt->fetch(PDO::FETCH_ASSOC);

$show_subscription_popup = !$active_subscription;

// Get all profile data
$user = getUserById($conn, $user_id);
if (!$user) {
    die("User not found");
}

// Fetch theme data using PDO
$theme_sql = "SELECT primary_color, secondary_color FROM theme WHERE user_id = ?";
$theme_stmt = $conn->prepare($theme_sql);
$theme_stmt->execute([$user_id]);
$theme_data = $theme_stmt->fetch(PDO::FETCH_ASSOC);

// Set default colors if no theme exists
$primary_color = $theme_data['primary_color'] ?? '#4e73df';
$secondary_color = $theme_data['secondary_color'] ?? '#858796';

// Get other profile data
$business_info = getBusinessInfo($conn, $user_id);
$photos = getProfilePhotos($conn, $user_id);
$social_link = getSocialLinks($conn, $user_id);
$products = getProducts($conn, $user_id);
$services = getServices($conn, $user_id);
$gallery = getGallery($conn, $user_id);
$ratings = getRatings($conn, $user_id);
$bank_details = getBankDetails($conn, $user_id);
$qr_codes = getQrCodes($conn, $user_id);

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating_data = [
        'reviewer_name' => $_POST['reviewer_name'] ?? '',
        'reviewer_email' => $_POST['reviewer_email'] ?? '',
        'reviewer_phone' => $_POST['reviewer_phone'] ?? '',
        'rating' => intval($_POST['rating'] ?? 0),
        'feedback' => $_POST['feedback'] ?? ''
    ];
    
    if (submitRating($conn, $user_id, $rating_data)) {
        header("Location: ?profile_url=$profile_url");
        exit();
    }
}

// Include HTML components
require 'includes/header.php';
require 'includes/navigation.php';
require 'includes/profile_header.php';
require 'includes/business_info.php';
require 'includes/products.php';
require 'includes/services.php';
require 'includes/gallery.php';
require 'includes/ratings.php';
require 'includes/bank_details.php';
require 'includes/qr_codes.php';
require 'includes/share_section.php';
require 'includes/footer.php';

// Close connection
$conn = null;
?>

