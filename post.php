<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure these paths are correct relative to where this script is executed
require_once 'config/db_connection.php';
require_once 'functions/profile_functions.php';

// Validate profile URL
if (!isset($_GET['profile_url'])) {
    header("HTTP/1.0 400 Bad Request");
    die("Profile URL is required");
}

$profile_url = $_GET['profile_url'];

// Get user ID from profile URL
// Assuming getUserByProfileUrl handles the PDO connection and returns an array or null
$profile_data = getUserByProfileUrl($conn, $profile_url);
if (!$profile_data) {
    // It's generally better to redirect to a user-friendly 404 page
    header("Location: page-not-found.php");
    exit();
}

$user_id = $profile_data['user_id'];

// Check for active subscription
$subscription_sql = "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()";
$subscription_stmt = $conn->prepare($subscription_sql);
if ($subscription_stmt) {
    $subscription_stmt->execute([$user_id]);
    $active_subscription = $subscription_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Handle prepare error, though it's less common for a hardcoded query
    error_log("Failed to prepare subscription SQL statement.");
    $active_subscription = false; // Assume no active subscription if query fails
}

$show_subscription_popup = !$active_subscription;

// Get all profile data
$user = getUserById($conn, $user_id);
if (!$user) {
    // This could happen if profile_data was found but getUserById fails for some reason
    die("User not found");
}

// Fetch theme data using PDO
$theme_sql = "SELECT primary_color, secondary_color FROM theme WHERE user_id = ?";
$theme_stmt = $conn->prepare($theme_sql);
if ($theme_stmt) {
    $theme_stmt->execute([$user_id]);
    $theme_data = $theme_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare theme SQL statement.");
    $theme_data = []; // Empty array to prevent errors if fetch fails
}

// Set default colors if no theme exists
$primary_color = $theme_data['primary_color'] ?? '#000000';
$secondary_color = $theme_data['secondary_color'] ?? '#ffffff';

// Get delivery charges including free delivery minimum
$delivery_charges_sql = "SELECT delivery_charge, free_delivery_minimum FROM delivery_charges WHERE user_id = ?";
$delivery_charges_stmt = $conn->prepare($delivery_charges_sql);
if ($delivery_charges_stmt) {
    $delivery_charges_stmt->execute([$user_id]);
    $delivery_charges = $delivery_charges_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare delivery charges SQL statement.");
    $delivery_charges = ['delivery_charge' => 0, 'free_delivery_minimum' => 0]; // Default values
}


// Get GST charge
$gst_sql = "SELECT gst_percent FROM gst_charge WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$gst_stmt = $conn->prepare($gst_sql);
if ($gst_stmt) {
    $gst_stmt->execute([$user_id]);
    $gst_data = $gst_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare GST charge SQL statement.");
    $gst_data = ['gst_percent' => 0]; // Default value
}
$gst_percent = $gst_data['gst_percent'] ?? 0;

// --- START OF THE MODIFIED SECTION FOR DISCOUNTS ---
// Get discounts for this user, selecting only the requested columns
$discounts_sql = "SELECT min_cart_value, discount_in_percent, discount_in_flat, image_path FROM discount WHERE user_id = ? ORDER BY min_cart_value ASC";
$discounts_stmt = $conn->prepare($discounts_sql);
if ($discounts_stmt) {
    $discounts_stmt->execute([$user_id]);
    $discounts = $discounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare discount SQL statement.");
    $discounts = []; // Initialize as empty array if query fails
}
// --- END OF THE MODIFIED SECTION FOR DISCOUNTS ---


// Get other profile data (assuming these functions are in profile_functions.php and handle PDO)
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
        // Redirect to prevent form resubmission
        header("Location: ?profile_url=" . urlencode($profile_url));
        exit();
    } else {
        // Handle rating submission error (e.g., display a message)
        echo "<script>alert('Failed to submit rating. Please try again.');</script>";
    }
}

// Fetch dining tables count
$table_sql = "SELECT table_count FROM dining_tables WHERE user_id = ?";
$table_stmt = $conn->prepare($table_sql);
if ($table_stmt) {
    $table_stmt->execute([$user_id]);
    $table_data = $table_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare dining tables SQL statement.");
    $table_data = ['table_count' => 0]; // Default value
}
$table_count = $table_data['table_count'] ?? 0;

// Check dining and delivery status
$dining_delivery_sql = "SELECT dining_active, delivery_active FROM dining_and_delivery WHERE user_id = ?";
$dining_delivery_stmt = $conn->prepare($dining_delivery_sql);
if ($dining_delivery_stmt) {
    $dining_delivery_stmt->execute([$user_id]);
    $dining_delivery_data = $dining_delivery_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    error_log("Failed to prepare dining and delivery SQL statement.");
    $dining_delivery_data = ['dining_active' => 0, 'delivery_active' => 0]; // Default values
}

$dining_active = $dining_delivery_data['dining_active'] ?? 0;
$delivery_active = $dining_delivery_data['delivery_active'] ?? 0;



// Include HTML components
// These files will have access to all the variables defined above (e.g., $user, $discounts, $primary_color, etc.)
require_once 'includes/header.php';
require_once 'includes/navigation.php';
require_once 'includes/profile_header.php';
require_once 'includes/business_info.php';
require_once 'includes/offer_popup.php';
require_once 'includes/products.php';
require_once 'includes/services.php';
require_once 'includes/gallery.php';
require_once 'includes/ratings.php';
require_once 'includes/bank_details.php';
require_once 'includes/qr_codes.php';
require_once 'includes/share_section.php';
require_once 'includes/footer.php';

// Close connection
// It's good practice to close the connection when it's no longer needed
$conn = null;
?>
