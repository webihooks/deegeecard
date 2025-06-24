<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
$primary_color = $theme_data['primary_color'] ?? '#000000';
$secondary_color = $theme_data['secondary_color'] ?? '#ffffff';

// Get delivery charges including free delivery minimum
$delivery_charges_sql = "SELECT delivery_charge, free_delivery_minimum FROM delivery_charges WHERE user_id = ?";
$delivery_charges_stmt = $conn->prepare($delivery_charges_sql);
$delivery_charges_stmt->execute([$user_id]);
$delivery_charges = $delivery_charges_stmt->fetch(PDO::FETCH_ASSOC);

// Get GST charge
$gst_sql = "SELECT gst_percent FROM gst_charge WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$gst_stmt = $conn->prepare($gst_sql);
$gst_stmt->execute([$user_id]);
$gst_data = $gst_stmt->fetch(PDO::FETCH_ASSOC);
$gst_percent = $gst_data['gst_percent'] ?? 0;

// Get discounts for this user
$discounts_sql = "SELECT * FROM discount WHERE user_id = ? ORDER BY min_cart_value ASC";
$discounts_stmt = $conn->prepare($discounts_sql);
$discounts_stmt->execute([$user_id]);
$discounts = $discounts_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Fetch dining tables count
$table_sql = "SELECT table_count FROM dining_tables WHERE user_id = ?";
$table_stmt = $conn->prepare($table_sql);
$table_stmt->execute([$user_id]);
$table_data = $table_stmt->fetch(PDO::FETCH_ASSOC);
$table_count = $table_data['table_count'] ?? 0;

// Check dining and delivery status
$dining_delivery_sql = "SELECT dining_active, delivery_active FROM dining_and_delivery WHERE user_id = ?";
$dining_delivery_stmt = $conn->prepare($dining_delivery_sql);
$dining_delivery_stmt->execute([$user_id]);
$dining_delivery_data = $dining_delivery_stmt->fetch(PDO::FETCH_ASSOC);

$dining_active = $dining_delivery_data['dining_active'] ?? 0;
$delivery_active = $dining_delivery_data['delivery_active'] ?? 0;

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
