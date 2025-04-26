<?php
// Database connection
require 'db_connection.php';

// Get business name from URL
$business_name = isset($_GET['business_name']) ? trim($_GET['business_name']) : '';

if (empty($business_name)) {
    // Fallback to ID if needed
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
} else {
    // Look up user ID by business name
    $lookup_query = "SELECT u.id 
                    FROM users u
                    JOIN business_info b ON u.id = b.user_id
                    WHERE b.business_name = ?";
    $stmt = $conn->prepare($lookup_query);
    $stmt->bind_param("s", $business_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("Business not found");
    }
    
    $row = $result->fetch_assoc();
    $user_id = $row['id'];
    $stmt->close();
}

// Rest of your existing code remains the same...
// Continue with fetching user data using $user_id


// Fetch business info
$business_query = "SELECT business_name, business_description, business_address, google_direction, designation 
                  FROM business_info WHERE user_id = ?";
$stmt = $conn->prepare($business_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$business_result = $stmt->get_result();
$business_info = $business_result->num_rows > 0 ? $business_result->fetch_assoc() : null;
$stmt->close();

// Fetch profile and cover photos
$photos_query = "SELECT profile_photo, cover_photo FROM profile_cover_photo WHERE user_id = ?";
$stmt = $conn->prepare($photos_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$photos_result = $stmt->get_result();
$photos = $photos_result->num_rows > 0 ? $photos_result->fetch_assoc() : null;
$stmt->close();

// Fetch Social Link
$social_query = "SELECT facebook, instagram, whatsapp, linkedin, youtube, telegram 
                  FROM social_link WHERE user_id = ?";
$stmt = $conn->prepare($social_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$social_result = $stmt->get_result();
$social_link = $social_result->num_rows > 0 ? $social_result->fetch_assoc() : null;
$stmt->close();

// Fetch Products
$products_query = "SELECT product_name, description, price, quantity, image_path FROM products WHERE user_id = ?";
$stmt = $conn->prepare($products_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$products_result = $stmt->get_result();
$products = [];
if ($products_result->num_rows > 0) {
    while ($row = $products_result->fetch_assoc()) {
        $products[] = $row;
    }
}
$stmt->close();

// Fetch Services
$services_query = "SELECT service_name, description, price, duration, image_path FROM services WHERE user_id = ?";
$stmt = $conn->prepare($services_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
if ($services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }
}
$stmt->close();


// Fetch Photo Gallery
$gallery_query = "SELECT filename, photo_gallery_path, title, description FROM photo_gallery WHERE user_id = ?";
$stmt = $conn->prepare($gallery_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$gallery_result = $stmt->get_result();
$gallery = [];
if ($gallery_result->num_rows > 0) {
    while ($row = $gallery_result->fetch_assoc()) {
        $gallery[] = $row;
    }
}
$stmt->close();


// Fetch Ratings
$ratings_query = "SELECT reviewer_name, rating, feedback, created_at FROM ratings WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($ratings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratings_result = $stmt->get_result();
$ratings = [];
if ($ratings_result->num_rows > 0) {
    while ($row = $ratings_result->fetch_assoc()) {
        $ratings[] = $row;
    }
}
$stmt->close();

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $reviewer_name = $_POST['reviewer_name'] ?? '';
    $reviewer_email = $_POST['reviewer_email'] ?? '';
    $reviewer_phone = $_POST['reviewer_phone'] ?? '';
    $rating = intval($_POST['rating'] ?? 0);
    $feedback = $_POST['feedback'] ?? '';
    
    if (!empty($reviewer_name) && $rating >= 1 && $rating <= 5) {
        $insert_query = "INSERT INTO ratings (user_id, reviewer_name, reviewer_email, reviewer_phone, rating, feedback) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("isssis", $user_id, $reviewer_name, $reviewer_email, $reviewer_phone, $rating, $feedback);
        $stmt->execute();
        $stmt->close();
        
        // Refresh to show the new rating
        header("Location: ?id=$user_id");
        exit();
    }
}


// Fetch Ratings - Modified to only show ratings 3, 4, or 5
$ratings_query = "SELECT reviewer_name, rating, feedback, created_at FROM ratings WHERE user_id = ? AND rating IN (3, 4, 5) ORDER BY created_at DESC";
$stmt = $conn->prepare($ratings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ratings_result = $stmt->get_result();
$ratings = [];
if ($ratings_result->num_rows > 0) {
    while ($row = $ratings_result->fetch_assoc()) {
        $ratings[] = $row;
    }
}
$stmt->close();


// Fetch Bank Details
$bank_query = "SELECT account_name, bank_name, account_number, account_type, ifsc_code 
              FROM bank_details WHERE user_id = ?";
$stmt = $conn->prepare($bank_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bank_result = $stmt->get_result();
$bank_details = [];
if ($bank_result->num_rows > 0) {
    while ($row = $bank_result->fetch_assoc()) {
        $bank_details[] = $row;
    }
}
$stmt->close();



$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
<title><?php echo htmlspecialchars($user['name']); ?> | <?php echo htmlspecialchars($business_info['business_name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link href="assets/css/main.css" rel="stylesheet">
</head>
<body>


                



<div class="main">

    <!-- Cover -->
    <div class="cover_photo">
        <?php if (!empty($photos['cover_photo'])): ?>
        <img src="uploads/cover/<?php echo htmlspecialchars($photos['cover_photo']); ?>" class="img-fluid" alt="Cover Photo">
        <?php else: ?>
        <?php endif; ?>
    </div>

    <!-- Profile Photo -->
    <div class="profile_photo">
        <?php if (!empty($photos['profile_photo'])): ?>
        <img src="uploads/profile/<?php echo htmlspecialchars($photos['profile_photo']); ?>" class="img-fluid" alt="Profile Photo">
        <?php else: ?>
        <?php endif; ?>
    </div>

    <!-- Profile Name and Detials -->
    <div class="personal_info">
        <h1><?php echo htmlspecialchars($user['name']); ?></h1>

        <div class="designation">
            <?php if ($business_info): ?>
            <?php if (!empty($business_info['designation'])): ?>
            <h2><?php echo htmlspecialchars($business_info['designation']); ?></h2>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <ul class="social_networks">
            <li>
                <a href="<?php echo htmlspecialchars($social_link['facebook']); ?>" target="_blank"><i class="bi bi-facebook"></i></a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['instagram']); ?>" target="_blank"><i class="bi bi-instagram"></i></a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['whatsapp']); ?>" target="_blank"><i class="bi bi-whatsapp"></i></a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['linkedin']); ?>" target="_blank"><i class="bi bi-linkedin"></i></a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['youtube']); ?>" target="_blank"><i class="bi bi-youtube"></i></a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['telegram']); ?>" target="_blank"><i class="bi bi-telegram"></i></a>
            </li>
        </ul>
        
        <ul class="personal_contact mt-4">
            <li>
                <a href="tel:<?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?>">
                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($social_link['whatsapp']); ?>">
                    <i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($social_link['whatsapp']); ?>
                </a>
            </li>
            <li>
                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($business_info['google_direction']); ?>" target="_blank">
                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($business_info['google_direction']); ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Business Details -->
    <div class="business_details">
        <h6>Business</h6>
        <?php if ($business_info): ?>
        <h2><?php echo htmlspecialchars($business_info['business_name']); ?></h2>
        <p><?php echo htmlspecialchars($business_info['business_description']); ?></p>
        <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($business_info['business_address']); ?></p>
        <?php endif; ?>
    </div>

    <!-- Products -->
    <div class="products">
        <h6>Products</h6>
        <div class="row">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-sm-12">
                        <div class="card product-card">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200?text=No+Image" class="card-img-top product-img" alt="No Image Available">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-primary fw-bold">₹<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="badge bg-<?php echo ($product['quantity'] > 0) ? 'success' : 'danger'; ?>">
                                        <?php echo ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </div>
                                <?php if ($product['quantity'] > 0): ?>
                                    <small class="text-muted">Quantity: <?php echo $product['quantity']; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No products available yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Services -->
    <div class="services">
        <h6>Services</h6>
        <div class="row">
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $service): ?>
                    <div class="col-sm-12">
                        <div class="card service-card">
                            <?php if (!empty($service['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($service['image_path']); ?>" class="card-img-top service-img" alt="<?php echo htmlspecialchars($service['service_name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x200?text=No+Image" class="card-img-top service-img" alt="No Image Available">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($service['service_name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($service['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-primary fw-bold">₹<?php echo number_format($service['price'], 2); ?></span>
                                    <span class="badge duration-badge">
                                        <i class="bi bi-clock"></i> <?php echo htmlspecialchars($service['duration']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No services available yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gallery  -->
   <div class="gallery">
        <h6>Photo Gallery</h6>
        <div class="row">
        <?php if (!empty($gallery)): ?>
            <?php foreach ($gallery as $photo): ?>
                <div class="col-sm-12">
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($photo['photo_gallery_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($photo['title']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($photo['title']); ?></h5>
                            <p class="card-text">
                                <?php echo htmlspecialchars($photo['description']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">No photos in the gallery yet.</div>
                    </div>
                <?php endif; ?>
        </div>
    </div>

    <!-- Display Ratings -->
    <div class="display_ratings">
        <h6>Customer Reviews</h6>
        
        <?php if (!empty($ratings)): ?>
            <div class="row">
                <?php foreach ($ratings as $review): ?>
                    <div class="col-sm-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h5 class="card-title"><?php echo htmlspecialchars($review['reviewer_name']); ?></h5>
                                    <div class="star-rating">
                                        <?php echo str_repeat('★', $review['rating']); ?><?php echo str_repeat('☆', 5 - $review['rating']); ?>
                                    </div>
                                </div>
                                <p class="card-text"><?php echo htmlspecialchars($review['feedback']); ?></p>
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No reviews yet.</div>
        <?php endif; ?>
    </div>

    <!-- Rating Form -->
    <div class="rating card">
    <h6>Leave a Review</h6>
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="reviewer_name" class="form-label">Your Name*</label>
                    <input type="text" class="form-control" id="reviewer_name" name="reviewer_name" required>
                </div>
                <div class="col-md-4">
                    <label for="reviewer_email" class="form-label">Your Email</label>
                    <input type="email" class="form-control" id="reviewer_email" name="reviewer_email">
                </div>
                <div class="col-md-4">
                    <label for="reviewer_phone" class="form-label">Your Phone</label>
                    <input type="tel" class="form-control" id="reviewer_phone" name="reviewer_phone" pattern="[0-9]{10,15}" title="Phone number (10-15 digits)">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Rating*</label>
                <div class="rating-input">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating1" value="1" required>
                        <label class="form-check-label" for="rating1">1 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating2" value="2">
                        <label class="form-check-label" for="rating2">2 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating3" value="3">
                        <label class="form-check-label" for="rating3">3 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating4" value="4">
                        <label class="form-check-label" for="rating4">4 ★</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="rating" id="rating5" value="5">
                        <label class="form-check-label" for="rating5">5 ★</label>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="feedback" class="form-label">Your Feedback</label>
                <textarea class="form-control" id="feedback" name="feedback" rows="3"></textarea>
            </div>
            
            <button type="submit" name="submit_rating" class="btn btn-primary">Submit Review</button>
        </form>
    </div>


    <!-- Bank Details -->
    <div class="bank_details">
        <h6>Bank Accounts</h6>
        
        <?php if (!empty($bank_details)): ?>
            <div class="row">
                <?php foreach ($bank_details as $account): ?>
                    <div class="col-sm-12 mb-2">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($account['account_name']); ?></h5>
                                <div class="bank-details">
                                    <p><strong>Bank:</strong> <?php echo htmlspecialchars($account['bank_name']); ?></p>
                                    <p><strong>Account Number:</strong> <?php echo htmlspecialchars($account['account_number']); ?></p>
                                    <p><strong>Account Type:</strong> <?php echo htmlspecialchars($account['account_type']); ?></p>
                                    <p><strong>IFSC Code:</strong> <?php echo htmlspecialchars($account['ifsc_code']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No bank accounts registered yet.</div>
        <?php endif; ?>
    </div>





</div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>