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
$delivery_charges_sql = "SELECT delivery_charge, free_delivery_minimum FROM delivery_charges WHERE user_id = ? LIMIT 1";
$delivery_charges_stmt = $conn->prepare($delivery_charges_sql);

if ($delivery_charges_stmt) {
    if (!$delivery_charges_stmt->execute([$user_id])) {
        error_log("Delivery charges query execution failed: " . implode(" ", $delivery_charges_stmt->errorInfo()));
    }
    $delivery_charges = $delivery_charges_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Set default values if no record found
    if (!$delivery_charges) {
        $delivery_charges = [
            'delivery_charge' => 0,
            'free_delivery_minimum' => 0
        ];
        error_log("No delivery charges found for user_id: $user_id, using defaults");
    }
    
    // Debug output
    error_log("Delivery charges for user $user_id: " . print_r($delivery_charges, true));
} else {
    error_log("Failed to prepare delivery charges SQL statement: " . implode(" ", $conn->errorInfo()));
    $delivery_charges = [
        'delivery_charge' => 0,
        'free_delivery_minimum' => 0
    ];
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



// Get coupon configuration for this user
$coupon_sql = "SELECT COUNT(*) as has_coupons FROM coupons WHERE user_id = ?";
$coupon_stmt = $conn->prepare($coupon_sql);
if ($coupon_stmt) {
    $coupon_stmt->execute([$user_id]);
    $coupon_data = $coupon_stmt->fetch(PDO::FETCH_ASSOC);
    $has_coupons = $coupon_data['has_coupons'] > 0;
} else {
    error_log("Failed to prepare coupon SQL statement.");
    $has_coupons = false;
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
// Dont remove require_once 'includes/navigation.php';
// Dont remove require_once 'includes/profile_header.php';
// Dont remove require_once 'includes/business_info.php';
// Dont remove require_once 'includes/offer_popup.php';
// Dont remove require_once 'includes/products.php';
// Dont remove require_once 'includes/services.php';
// Dont remove require_once 'includes/gallery.php';
// Dont remove require_once 'includes/ratings.php';
// Dont remove require_once 'includes/bank_details.php';
// Dont remove require_once 'includes/qr_codes.php';
// Dont remove require_once 'includes/share_section.php';
// Dont remove require_once 'includes/footer.php';

// Close connection
// It's good practice to close the connection when it's no longer needed
$conn = null;
?>


















<!-- products.php -->
<div class="products">
 <h6>Products</h6>
 <div class="mb-3">
    <div class="input-group">
       <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
       <button class="btn btn-outline-secondary" type="button" id="clearSearch">
       <i class="bi bi-x"></i>
       </button>
    </div>
 </div>
 
 <?php if ($delivery_active || $dining_active): ?>
 <!-- Shopping Cart Sidebar -->
 <div class="cart-sidebar">
    <div class="cart-header">
       <h5>Your Cart</h5>
       <button class="btn-close" onclick="closeCart()"></button>
    </div>

















<!-- Cart Section (shown initially) -->
<div class="cart_group" id="cartGroup">
    <div class="cart-items" id="cartItems"></div>
    
    <div class="cart-total-details">
        <div class="cart-subtotal">
            Subtotal: ₹<span id="cartSubtotal">0.00</span>
        </div>
        
        <!-- Discount Section -->
        <div class="cart-discount" id="discountSection" style="display: none;">
            Discount: -₹<span id="discountAmount">0.00</span>
            (<span id="discountType"></span>)
        </div>
        
        <!-- Coupon Section -->
        <div class="cart-coupon" id="couponSection" style="display: none;"></div>
        
        <?php if ($gst_percent > 0): ?>
        <div class="cart-gst-charges">
            GST (<?= $gst_percent ?>%): ₹<span id="gstCharges">0.00</span>
        </div>
        <?php endif; ?>
        
        <?php if ($delivery_active && isset($delivery_charges)): ?>
        <div class="cart-delivery-charges">
            Delivery: <span id="deliveryChargeText">₹0.00</span>
        </div>
        <?php endif; ?>
        
        <div class="cart-total">
            Total: ₹<span id="cartTotal">0.00</span>
        </div>
    </div>

    
    <!-- View Cart Button -->
    <button class="btn btn-outline-secondary mb-3 w-100" id="viewCartBtn" style="display: none;">
        <i class="bi bi-cart"></i> View Cart
    </button>
</div>

<!-- Order Type Buttons -->
<?php if ($delivery_active || $dining_active): ?>
<div class="order-type-buttons mb-3">
    <?php if ($delivery_active): ?>
    <button class="btn btn-outline-primary w-50" id="deliveryBtn">
        <i class="bi bi-truck"></i> Delivery
    </button>
    <?php endif; ?>
    <?php if ($dining_active): ?>
    <button class="btn btn-outline-primary w-50" id="dinningBtn">
        <i class="bi bi-cup-hot"></i> Dining
    </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Customer Details Section (hidden initially) -->
<div id="customerDetailsSection" style="display: none;">


<!-- Add this near your cart button -->
<?php if ($has_coupons && ($delivery_active || $dining_active)): ?>
<div class="coupon-form mb-3" id="couponForm">
    <h6>Have a coupon code?</h6>
    <div class="input-group">
        <input type="text" class="form-control" id="couponCode" placeholder="Enter coupon code">
        <button class="btn btn-outline-secondary" type="button" id="applyCouponBtn">Apply</button>
    </div>
    <small class="text-success mt-1" id="couponMessage" style="display: none;"></small>
    <small class="text-danger mt-1" id="couponError" style="display: none;"></small>
</div>
<?php endif; ?>


    
    
    <?php if ($dining_active): ?>
    <div class="customer-details dinning-details" id="diningDetails" style="display: none;">
       <h6>Dinning Information</h6>
       <div class="mb-1 col-full">
          <label for="tableNumber" class="form-label">Table No.*</label>
          <select class="form-control" id="tableNumber" required>
            <option value="">Select Table</option>
            <?php for ($i = 1; $i <= $table_count; $i++): ?>
                <option value="<?= $i ?>">Table <?= $i ?></option>
            <?php endfor; ?>
          </select>
       </div>
       <div class="mb-1 col-half">
          <label for="dinningName" class="form-label">Name*</label>
          <input type="text" class="form-control" id="dinningName" placeholder="Your name" required>
       </div>
       <div class="mb-1 col-half">
            <label for="dinningPhone" class="form-label">Phone*</label>
            <input type="tel" class="form-control" id="dinningPhone" placeholder="Your phone number" 
                   pattern="[0-9]{10}" title="Please enter exactly 10 digits" required
                   oninput="validatePhoneNumber(this)">
        </div>
       <!-- Add Order Notes for Dining -->
       <div class="mb-1 col-full">
          <label for="dinningNotes" class="form-label">Order Notes</label>
          <textarea class="form-control" id="dinningNotes" rows="2" placeholder="Any special instructions"></textarea>
       </div>
    </div>
    <?php endif; ?>

    <?php if ($delivery_active): ?>
    <div class="customer-details delivery-details" id="deliveryDetails" style="display: none;">


        









       <h6>Delivery Information</h6>
       <div class="mb-1 col-half">
          <label for="customerName" class="form-label">Name*</label>
          <input type="text" class="form-control" id="customerName" placeholder="Your name" required>
       </div>
       <div class="mb-1 col-half">
            <label for="customerPhone" class="form-label">Phone*</label>
            <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" 
                   pattern="[0-9]{10}" title="Please enter exactly 10 digits" required
                   oninput="validatePhoneNumber(this)">
        </div>
       <div class="mb-1 col-full">
          <label for="customerAddress" class="form-label">Address*</label>
          <textarea class="form-control" id="customerAddress" rows="2" placeholder="Delivery address" required></textarea>
       </div>
       <div class="mb-1 col-full">
          <label for="customerNotes" class="form-label">Order Notes</label>
          <textarea class="form-control" id="customerNotes" rows="2" placeholder="Any special instructions"></textarea>
       </div>
    </div>
    <?php endif; ?>

    <div class="cart-footer">
        <button class="btn btn-success w-100" id="placeOrderBtn">Place Order</button>
        <button class="btn btn-success w-100 mt-2" onclick="placeOrderOnWhatsApp()" style="display:none;">
            <i class="bi bi-whatsapp"></i> Place Order via WhatsApp
        </button>
    </div>
</div>






<script>
// Fade Animation Functions
function fadeIn(element, callback) {
    element.style.display = 'block';
    // Force reflow to enable transition
    void element.offsetHeight;
    element.classList.add('fade-in');
    element.classList.remove('fade-out');
    
    setTimeout(() => {
        if (callback) callback();
    }, 300);
}

function fadeOut(element, callback) {
    element.classList.add('fade-out');
    element.classList.remove('fade-in');
    
    setTimeout(() => {
        element.style.display = 'none';
        if (callback) callback();
    }, 300);
}

// Initialize elements with fade classes
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = [
        document.getElementById('cartItems'),
        document.getElementById('customerDetailsSection'),
        document.getElementById('deliveryDetails'),
        document.getElementById('diningDetails')
    ].filter(el => el);
    
    fadeElements.forEach(el => {
        el.classList.add('fade-element');
        if (el.style.display !== 'none') {
            el.classList.add('fade-in');
        }
    });
});

// Modified Event Listeners with Fade Animation
document.addEventListener('DOMContentLoaded', function() {
    const cartItems = document.getElementById('cartItems');
    const customerDetailsSection = document.getElementById('customerDetailsSection');
    const viewCartBtn = document.getElementById('viewCartBtn');
    
    // Initialize View Cart button as hidden (already set in HTML)
    viewCartBtn.classList.add('fade-element');
    
    <?php if ($delivery_active): ?>
    document.getElementById('deliveryBtn').addEventListener('click', function() {
        fadeOut(cartItems, function() {
            fadeIn(customerDetailsSection);
            fadeIn(document.getElementById('deliveryDetails'));
            <?php if ($dining_active): ?>
            fadeOut(document.getElementById('diningDetails'));
            <?php endif; ?>
        });
        
        // Show the View Cart button when switching to delivery
        fadeIn(viewCartBtn);
        
        this.classList.add('active');
        <?php if ($dining_active): ?>
        document.getElementById('dinningBtn').classList.remove('active');
        <?php endif; ?>
        
        localStorage.setItem('selectedOrderType', 'delivery');
    });
    <?php endif; ?>
    
    <?php if ($dining_active): ?>
    document.getElementById('dinningBtn').addEventListener('click', function() {
        fadeOut(cartItems, function() {
            fadeIn(customerDetailsSection);
            fadeIn(document.getElementById('diningDetails'));
            <?php if ($delivery_active): ?>
            fadeOut(document.getElementById('deliveryDetails'));
            <?php endif; ?>
        });
        
        // Show the View Cart button when switching to dining
        fadeIn(viewCartBtn);
        
        this.classList.add('active');
        <?php if ($delivery_active): ?>
        document.getElementById('deliveryBtn').classList.remove('active');
        <?php endif; ?>
        
        localStorage.setItem('selectedOrderType', 'dining');
    });
    <?php endif; ?>
    
    // View Cart button with fade animation
    viewCartBtn.addEventListener('click', function() {
        fadeOut(customerDetailsSection, function() {
            fadeIn(cartItems);
        });
        
        // Hide the View Cart button when viewing cart
        fadeOut(viewCartBtn);
    });
    
    // Restore selected order type
    const selectedOrderType = localStorage.getItem('selectedOrderType');
    if (selectedOrderType === 'delivery' && <?= $delivery_active ? 'true' : 'false' ?>) {
        document.getElementById('deliveryBtn').classList.add('active');
        // Show View Cart button if coming from saved delivery state
        fadeIn(viewCartBtn);
    } else if (selectedOrderType === 'dining' && <?= $dining_active ? 'true' : 'false' ?>) {
        document.getElementById('dinningBtn').classList.add('active');
        // Show View Cart button if coming from saved dining state
        fadeIn(viewCartBtn);
    }
    // No else needed since button is hidden by default
});
</script>








 </div>
 <?php endif; ?>

 <div class="row" id="productsContainer">
    <?php if (!empty($products)): ?>
    <?php foreach ($products as $product): ?>
    <div class="col-sm-12 product-item" 
       data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>"
       data-desc="<?= htmlspecialchars(strtolower($product['description'])) ?>">
       <div class="card product-card">
          



            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
                <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-primary fw-bold">₹<?= number_format($product['price']) ?></span>
                    <span class="badge bg-<?= ($product['quantity'] > 0) ? 'success' : 'danger' ?>" style="display: none;">
                        <?= ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock' ?>
                    </span>
                </div>
                <?php if ($product['quantity'] > 0): ?>
                <small class="text-muted">Quantity: <?= $product['quantity'] ?></small>
                <?php endif; ?>
                <?php if ($product['quantity'] > 0 && ($delivery_active || $dining_active)): ?>
                <div class="mt-3 cart_btn_group <?= empty($product['image_path']) ? 'top' : '' ?>">
                    <button class="btn btn-primary w-100 add-to-cart" 
                       data-id="<?= htmlspecialchars($product['product_name']) ?>"
                       data-name="<?= htmlspecialchars($product['product_name']) ?>"
                       data-price="<?= $product['price'] ?>"
                       data-max="<?= $product['quantity'] ?>"
                       data-image="<?= htmlspecialchars($product['image_path']) ?>"> 
                    <i class="bi bi-cart-plus"></i> Add
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($product['image_path'])): ?>
            <div class="img-group">
                <img src="<?= htmlspecialchars($product['image_path']) ?>" 
                class="card-img-top product-img" 
                alt="<?= htmlspecialchars($product['product_name']) ?>"
                onerror="this.style.display='none'">
            </div>
            <?php endif; ?>


            <script>
                document.querySelectorAll('.product-img').forEach(img => {
                  img.addEventListener('error', function() {
                    // Find the closest parent `.product-card`, then navigate to `.card-body .cart_btn_group`
                    const productCard = this.closest('.product-card');
                    if (productCard) {
                      const cartBtnGroup = productCard.querySelector('.card-body .cart_btn_group');
                      if (cartBtnGroup) {
                        cartBtnGroup.classList.add('top'); // Add the "top" class
                      }
                    }
                  });
                });
            </script>



       </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="col-12">
       <div class="alert alert-info">No products available yet.</div>
    </div>
    <?php endif; ?>
 </div>
 
 <?php if ($delivery_active || $dining_active): ?>
<div class="cart-button-container" style="display: none;">
    <button class="btn btn-primary cart-button" onclick="toggleCart()">
        <span class="cart-count">0 item added</span>
        <span class="small discount-message" style="display: none;"></span>
    </button>
</div>
<?php endif; ?>
</div>








<script>
// Coupon
    // Add these variables at the top with your other cart variables
let appliedCoupon = null;
let couponDiscount = 0;

// Add this event listener for the apply coupon button
document.getElementById('applyCouponBtn')?.addEventListener('click', applyCoupon);




/**
 * Applies a coupon code to the current order with full validation
 * Handles all error cases and provides detailed feedback
 */
async function applyCoupon() {
    // 1. Get UI elements
    const couponCodeInput = document.getElementById('couponCode');
    const couponCode = couponCodeInput.value.trim();
    const phoneInput = document.getElementById('customerPhone') || document.getElementById('dinningPhone');
    const phoneNumber = phoneInput?.value || '';
    const applyBtn = document.getElementById('applyCouponBtn');
    
    try {
        // 2. Clear previous messages and states
        document.getElementById('couponError').style.display = 'none';
        document.getElementById('couponMessage').style.display = 'none';
        couponCodeInput.classList.remove('is-invalid');
        
        // 3. Validate basic inputs
        if (!couponCode) {
            throw new Error('Please enter a coupon code');
        }

        // 4. Calculate current cart total
        const currentTotal = calculateCartTotal();
        if (currentTotal <= 0) {
            throw new Error('Your cart is empty');
        }

        // 5. Show loading state
        applyBtn.disabled = true;
        applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Applying...';

        // 6. Prepare request data with validation
        const requestData = {
            coupon_code: couponCode,
            phone_number: phoneNumber,
            cart_total: currentTotal,
            user_id: <?= $user_id ?>,
            timestamp: new Date().toISOString()
        };

        // 7. Make API request with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
        
        const response = await fetch('validate_coupon.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(requestData),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);

        // 8. Handle HTTP errors
        if (!response.ok) {
            let errorDetails;
            try {
                errorDetails = await response.json();
            } catch (e) {
                errorDetails = { message: `HTTP Error: ${response.status}` };
            }
            
            console.error('Coupon API Error:', {
                status: response.status,
                error: errorDetails,
                request: requestData
            });
            
            throw new Error(errorDetails.message || 'Coupon service unavailable');
        }

        // 9. Parse and validate response
        const data = await response.json();
        if (!data || typeof data.success === 'undefined') {
            throw new Error('Invalid coupon service response');
        }

        // 10. Handle coupon rejection
        if (!data.success) {
            console.warn('Coupon rejected:', {
                reason: data.message,
                coupon: couponCode,
                cartTotal: currentTotal
            });
            
            throw new Error(data.message || 'This coupon cannot be applied');
        }

        // 11. Success - store coupon data
        window.appliedCoupon = {
            id: data.coupon_id,
            code: data.coupon_code,
            name: data.coupon_name || data.coupon_code,
            discount: data.discount_amount,
            phone: phoneNumber,
            appliedAt: new Date(),
            minCartValue: data.min_cart_value || 0
        };
        window.couponDiscount = data.discount_amount;

        // 12. Update UI
        document.getElementById('couponMessage').textContent = 
            `Coupon "${data.coupon_name || data.coupon_code}" applied successfully!`;
        document.getElementById('couponMessage').style.display = 'block';
        
        couponCodeInput.readOnly = true;
        couponCodeInput.classList.add('is-valid');
        
        // 13. Update cart and show success
        updateCartUI();
        showToast('Discount applied!', 'success');

        // 14. Log success
        console.log('Coupon applied successfully:', {
            coupon: window.appliedCoupon,
            cart: cart
        });

    } catch (error) {
        // 15. Handle all errors consistently
        console.error('Coupon application failed:', {
            error: error.message,
            stack: error.stack,
            time: new Date().toISOString()
        });
        
        // 16. Show user-friendly error
        const errorElement = document.getElementById('couponError');
        errorElement.textContent = error.message || 'Failed to apply coupon';
        errorElement.style.display = 'block';
        
        couponCodeInput.classList.add('is-invalid');
        
        // 17. Scroll to error if needed
        errorElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

    } finally {
        // 18. Reset button state
        applyBtn.disabled = false;
        applyBtn.innerHTML = 'Apply';
    }
}

// Helper function to calculate cart total for coupon validation
function calculateCartTotalForCoupon() {
    let subtotal = 0;
    
    // Sum all item prices
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    
    // Apply existing discounts (except coupons)
    if (discountAmount > 0) {
        subtotal -= discountAmount;
    }
    
    return subtotal;
}






/**
 * Records coupon usage after successful order placement
 * @param {string} orderId - The ID of the placed order
 */
async function recordCouponUsage(orderId) {
    if (!window.appliedCoupon) return;

    try {
        // Verify phone number hasn't changed since coupon application
        const currentPhone = document.getElementById('customerPhone')?.value || 
                           document.getElementById('dinningPhone')?.value;
        
        if (currentPhone !== window.appliedCoupon.phone_number) {
            console.warn('Phone number changed after coupon application');
            return;
        }

        const response = await fetch('record_coupon_usage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                coupon_id: window.appliedCoupon.coupon_id,
                phone_number: currentPhone,
                order_id: orderId,
                discount_amount: window.appliedCoupon.discount_amount,
                cart_total: calculateCartTotal()
            })
        });

        if (!response.ok) {
            throw new Error('Failed to record coupon usage');
        }

        const result = await response.json();
        if (!result.success) {
            console.error('Coupon usage not recorded:', result.message);
        }

    } catch (error) {
        console.error('Error recording coupon usage:', error);
        // Don't show error to user - order was still successful
    }
}

/**
 * Removes applied coupon and updates cart
 */
function removeCoupon() {
    if (!window.appliedCoupon) return;

    // Clear coupon data
    window.appliedCoupon = null;
    window.couponDiscount = 0;
    
    // Reset coupon form
    const couponCodeInput = document.getElementById('couponCode');
    couponCodeInput.value = '';
    couponCodeInput.readOnly = false;
    couponCodeInput.focus();
    
    // Hide messages
    document.getElementById('couponMessage').style.display = 'none';
    document.getElementById('couponError').style.display = 'none';
    
    // Refresh cart
    updateCartUI();
    
    // Show confirmation
    showToast('Coupon removed', 'info');
}

/**
 * Shows coupon error message
 * @param {string} message - The error message to display
 */
function showCouponError(message) {
    const errorElement = document.getElementById('couponError');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    document.getElementById('couponMessage').style.display = 'none';
    
    // Scroll to error if needed
    errorElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}




function showCouponError(message) {
    const errorElement = document.getElementById('couponError');
    errorElement.textContent = message;
    errorElement.style.display = 'block';
    document.getElementById('couponMessage').style.display = 'none';
}

function calculateCartTotal() {
    // Calculate subtotal
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    
    // Apply existing discount if any
    subtotal -= discountAmount;
    
    // Add GST if applicable
    if (<?= $gst_percent ?? 0 ?> > 0) {
        subtotal += (subtotal * <?= $gst_percent ?? 0 ?>) / 100;
    }
    
    // Add delivery charges if applicable
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    // In your updateCartUI() function, modify the delivery charge display:
    if (isDelivery && <?= isset($delivery_charges['delivery_charge']) ? 'true' : 'false' ?>) {
        const deliveryCharge = <?= $delivery_charges['delivery_charge'] ?? 0 ?>;
        const freeDeliveryMin = <?= $delivery_charges['free_delivery_minimum'] ?? 0 ?>;
        const amountAfterDiscount = subtotal - discountAmount;
        
        let deliveryText = '';
        if (freeDeliveryMin > 0 && amountAfterDiscount >= freeDeliveryMin) {
            deliveryText = 'FREE (Order above ₹' + formatNumber(freeDeliveryMin) + ')';
        } else {
            deliveryText = '₹' + formatNumber(deliveryCharge);
            if (freeDeliveryMin > 0) {
                const needed = freeDeliveryMin - amountAfterDiscount;
                deliveryText += ' (Add ₹' + formatNumber(needed) + ' more for free delivery)';
            }
        }
        
        document.getElementById('deliveryChargeText').innerHTML = deliveryText;
        document.querySelector('.cart-delivery-charges').style.display = 'block';
    } else {
        document.querySelector('.cart-delivery-charges').style.display = 'none';
    }
    
    return subtotal;
}
// Coupon








// Detect store name from URL
const storeName = window.location.pathname.split('/')[1] || 'default';
const cartKey = `cart_${storeName}`;

let cart = [];
let discountAmount = 0;
let discountType = '';

// Initialize cart from localStorage
if (localStorage.getItem(cartKey)) {
    cart = JSON.parse(localStorage.getItem(cartKey));
    updateCartUI();
}


function formatNumber(num) {
    num = typeof num === 'string' ? parseFloat(num) : num;
    return num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
}

function validatePhoneNumber(input) {
    // Remove any non-digit characters
    input.value = input.value.replace(/\D/g, '');
    
    // Trim to 10 digits if longer
    if (input.value.length > 10) {
        input.value = input.value.substring(0, 10);
    }
    
    // Check validity and show error if needed
    if (input.value.length !== 10 && input.value.length > 0) {
        input.setCustomValidity('Phone number must be exactly 10 digits');
    } else {
        input.setCustomValidity('');
    }
}


// Add to cart button click handler
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
        const product = {
            id: this.dataset.id,
            name: this.dataset.name,
            price: parseFloat(this.dataset.price),
            max: parseInt(this.dataset.max),
            quantity: 1,
            image_path: this.dataset.image
        };

        const existingItem = cart.find(item => item.id === product.id);

        if (existingItem) {
            if (existingItem.quantity < existingItem.max) {
                existingItem.quantity++;
                // showToast(`${product.name} quantity increased to ${existingItem.quantity}`);
            } else {
                // showToast(`Maximum quantity reached for ${product.name}`, true);
                return;
            }
        } else {
            cart.push(product);
            // showToast(`${product.name} added to cart`);
            // Add pulse animation to cart button
            document.querySelector('.cart-button').classList.add('cart-item-added');
            setTimeout(() => {
                document.querySelector('.cart-button').classList.remove('cart-item-added');
            }, 500);
        }

        saveCart();
        updateCartUI();
        
        // Show cart button container if it's hidden
        const cartButtonContainer = document.querySelector('.cart-button-container');
        if (cartButtonContainer && cartButtonContainer.style.display === 'none') {
            cartButtonContainer.style.display = 'block';
        }
        
    });
});





// Function to show toast notification
function showToast(message, isError = false) {
    const toastElement = document.getElementById('cartToast');
    const toastMessage = document.getElementById('toastMessage');
    
    toastMessage.textContent = message;
    
    // Change style if it's an error message
    if (isError) {
        toastElement.querySelector('.toast-header').classList.remove('bg-primary');
        toastElement.querySelector('.toast-header').classList.add('bg-danger');
    } else {
        toastElement.querySelector('.toast-header').classList.remove('bg-danger');
        toastElement.querySelector('.toast-header').classList.add('bg-primary');
    }
    
    // Initialize and show the toast
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Auto-hide after 2 seconds
    setTimeout(() => {
        toast.hide();
    }, 2000);
}

// Save cart to localStorage
function saveCart() {
    localStorage.setItem(cartKey, JSON.stringify(cart));
}





function updateCartUI() {
    // 1. Get all necessary DOM elements
    const cartItemsContainer = document.getElementById('cartItems');
    const cartSubtotalElement = document.getElementById('cartSubtotal');
    const discountAmountElement = document.getElementById('discountAmount');
    const discountTypeElement = document.getElementById('discountType');
    const discountSection = document.getElementById('discountSection');
    const couponSection = document.getElementById('couponSection');
    const gstChargesElement = document.getElementById('gstCharges');
    const deliveryChargeTextElement = document.getElementById('deliveryChargeText');
    const cartTotalElement = document.getElementById('cartTotal');
    const cartCountElement = document.querySelector('.cart-count');
    const discountMessageElement = document.querySelector('.cart-button .discount-message');
    const viewCartBtn = document.getElementById('viewCartBtn');
    const cartButtonContainer = document.querySelector('.cart-button-container');

    // 2. Clear existing empty message if present
    const existingEmptyMsg = cartItemsContainer.querySelector('.empty-cart-message');
    if (existingEmptyMsg) existingEmptyMsg.remove();

    // 3. Handle empty cart case
    if (cart.length === 0) {
        const emptyCartMsg = document.createElement('div');
        emptyCartMsg.className = 'empty-cart-message text-center py-4';
        emptyCartMsg.innerHTML = `
            <i class="bi bi-cart-x fs-1 text-muted"></i>
            <p class="mt-2">Your cart is empty</p>
            <button class="btn btn-sm btn-outline-primary" onclick="closeCart()">
                Continue Shopping
            </button>
        `;
        cartItemsContainer.appendChild(emptyCartMsg);

        // Hide all cart-related elements
        [cartSubtotalElement, discountSection, couponSection, gstChargesElement, 
         deliveryChargeTextElement, cartTotalElement, discountMessageElement, 
         viewCartBtn].forEach(el => {
            if (el && el.parentElement) el.parentElement.style.display = 'none';
        });

        // Hide cart button if empty
        if (cartButtonContainer) cartButtonContainer.style.display = 'none';
        
        return;
    }

    // 4. Show cart button container if hidden
    if (cartButtonContainer) {
        cartButtonContainer.style.display = 'block';
    }

    // 5. Clear existing cart items
    cartItemsContainer.innerHTML = '';

    // 6. Calculate subtotal and render cart items
    let subtotal = 0;
    cart.forEach((item, index) => {
        subtotal += item.price * item.quantity;

        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item mb-3';
        itemElement.innerHTML = `
            <div class="cart-item-info d-flex justify-content-between">
                <div>
                    <h6 class="mb-1">${item.name}</h6>
                    <small class="text-muted">₹${formatNumber(item.price)} × ${item.quantity}</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold">₹${formatNumber(item.price * item.quantity)}</div>
                    <div class="cart-item-controls mt-1">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" 
                                onclick="updateQuantity(${index}, -1)">
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number" class="form-control form-control-sm d-inline-block text-center mx-1" 
                               value="${item.quantity}" min="1" max="${item.max}"
                               style="width: 50px;"
                               onchange="updateQuantityInput(${index}, this.value)">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" 
                                onclick="updateQuantity(${index}, 1)">
                            <i class="bi bi-plus"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger py-0 px-2 ms-2" 
                                onclick="removeFromCart(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        cartItemsContainer.appendChild(itemElement);
    });

    // 7. Update subtotal display
    cartSubtotalElement.textContent = formatNumber(subtotal);
    if (cartSubtotalElement.parentElement) {
        cartSubtotalElement.parentElement.style.display = 'block';
    }

    // 8. Calculate and display automatic discounts
    let discountAmount = 0;
    let discountType = '';
    
    <?php if (!empty($discounts)): ?>
        const discounts = <?= json_encode($discounts) ?>;
        let applicableDiscount = null;
        
        // Find the highest applicable discount
        for (let i = discounts.length - 1; i >= 0; i--) {
            if (subtotal >= discounts[i].min_cart_value) {
                applicableDiscount = discounts[i];
                break;
            }
        }

        if (applicableDiscount) {
            if (applicableDiscount.discount_in_percent > 0) {
                discountAmount = (subtotal * applicableDiscount.discount_in_percent) / 100;
                discountType = `${applicableDiscount.discount_in_percent}% discount`;
            } else if (applicableDiscount.discount_in_flat > 0) {
                discountAmount = parseFloat(applicableDiscount.discount_in_flat);
                discountType = `Flat ₹${formatNumber(applicableDiscount.discount_in_flat)} OFF`;
            }

            // Ensure discount doesn't exceed subtotal
            discountAmount = Math.min(discountAmount, subtotal);

            // Update discount display
            if (discountAmount > 0) {
                discountAmountElement.textContent = formatNumber(discountAmount);
                discountTypeElement.textContent = discountType;
                discountSection.style.display = 'block';
                
                // Show discount message in cart button
                if (discountMessageElement) {
                    discountMessageElement.innerHTML = `<i class="bi bi-tag-fill"></i> ${discountType} applied!`;
                    discountMessageElement.style.display = 'block';
                }
            }
        } else if (discountMessageElement && discounts.length > 0) {
            // Show message about potential discount
            const minDiscount = discounts[0].min_cart_value;
            const needed = minDiscount - subtotal;
            if (needed > 0) {
                discountMessageElement.innerHTML = `<i class="bi bi-tag"></i> Add ₹${formatNumber(needed)} more for discount`;
                discountMessageElement.style.display = 'block';
            }
        }
    <?php endif; ?>

    // 9. Apply coupon discount if available
    let amountAfterDiscount = Math.max(0, subtotal - discountAmount);
    let total = amountAfterDiscount;
    
    if (window.appliedCoupon && window.couponDiscount) {
        // Apply coupon discount
        const couponDiscountAmount = Math.min(window.couponDiscount, amountAfterDiscount);
        total -= couponDiscountAmount;
        
        // Update coupon display
        if (couponSection) {
            couponSection.style.display = 'block';
            
            // Determine what to display as coupon name
            const couponDisplayName = window.appliedCoupon.coupon_name || 
                                   window.appliedCoupon.name || 
                                   window.appliedCoupon.coupon_code || 
                                   'Coupon';
            
            couponSection.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-tag-fill text-success"></i>
                        ${couponDisplayName}: 
                        <span class="text-success">-₹${formatNumber(couponDiscountAmount)}</span>
                    </span>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeCoupon()">
                        <i class="bi bi-x"></i> Remove
                    </button>
                </div>
            `;
        }
    } else if (couponSection) {
        couponSection.style.display = 'none';
    }

    // 10. Calculate and display GST
    <?php if ($gst_percent > 0): ?>
        const gstAmount = (total * <?= $gst_percent ?>) / 100;
        gstChargesElement.textContent = formatNumber(gstAmount);
        total += gstAmount;
        if (gstChargesElement.parentElement) {
            gstChargesElement.parentElement.style.display = 'block';
        }
    <?php endif; ?>

    // 11. Calculate and display delivery charges
    <?php if ($delivery_active && isset($delivery_charges)): ?>
        const deliveryCharge = <?= $delivery_charges['delivery_charge'] ?? 0 ?>;
        const freeDeliveryMin = <?= $delivery_charges['free_delivery_minimum'] ?? 0 ?>;
        let actualDeliveryCharge = 0;
        let deliveryText = '';
        
        if (freeDeliveryMin > 0 && amountAfterDiscount >= freeDeliveryMin) {
            deliveryText = 'FREE';
            if (deliveryCharge > 0) {
                deliveryText += ` (Order above ₹${formatNumber(freeDeliveryMin)})`;
            }
        } else {
            actualDeliveryCharge = deliveryCharge;
            deliveryText = `₹${formatNumber(deliveryCharge)}`;
            if (freeDeliveryMin > 0) {
                const needed = freeDeliveryMin - amountAfterDiscount;
                deliveryText += ` (Add ₹${formatNumber(needed)} more for free delivery)`;
            }
        }
        
        deliveryChargeTextElement.innerHTML = deliveryText;
        total += actualDeliveryCharge;
        if (deliveryChargeTextElement.parentElement) {
            deliveryChargeTextElement.parentElement.style.display = 'block';
        }
    <?php endif; ?>

    // 12. Update total and item count
    cartTotalElement.textContent = formatNumber(total);
    const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCountElement.textContent = `${itemCount} ${itemCount === 1 ? 'item' : 'items'}`;

    // 13. Show view cart button if in details mode
    const customerDetailsSection = document.getElementById('customerDetailsSection');
    if (customerDetailsSection && customerDetailsSection.style.display !== 'none') {
        if (viewCartBtn) viewCartBtn.style.display = 'block';
    }
}

// Helper function to format numbers
function formatNumber(num) {
    num = typeof num === 'string' ? parseFloat(num) : num;
    return num.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}






// Helper function to format numbers
function formatNumber(num) {
    return parseFloat(num).toFixed(2);
}








function removeCoupon() {
    // Clear coupon data
    window.appliedCoupon = null;
    window.couponDiscount = 0;
    
    // Reset coupon form
    const couponCodeInput = document.getElementById('couponCode');
    if (couponCodeInput) {
        couponCodeInput.value = '';
        couponCodeInput.readOnly = false;
    }
    
    // Hide messages
    document.getElementById('couponMessage').style.display = 'none';
    document.getElementById('couponError').style.display = 'none';
    
    // Hide coupon display in cart
    const couponSection = document.getElementById('couponSection');
    if (couponSection) couponSection.style.display = 'none';
    
    // Refresh cart
    updateCartUI();
}







// Update quantity with buttons
function updateQuantity(index, change) {
    const item = cart[index];
    const newQuantity = item.quantity + change;

    if (newQuantity < 1) {
        removeFromCart(index);
        return;
    }

    if (newQuantity > item.max) {
        alert('Maximum quantity reached for this product');
        return;
    }

    item.quantity = newQuantity;
    saveCart();
    updateCartUI();
}

// Update quantity via input field
function updateQuantityInput(index, value) {
    const item = cart[index];
    const newQuantity = parseInt(value);

    if (isNaN(newQuantity) || newQuantity < 1) {
        item.quantity = 1;
    } else if (newQuantity > item.max) {
        alert('Maximum quantity reached for this product');
        item.quantity = item.max;
    } else {
        item.quantity = newQuantity;
    }

    saveCart();
    updateCartUI();
}

// Remove item from cart
function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    updateCartUI();
    
    // Hide cart button container if no items left
    if (cart.length === 0) {
        const cartButtonContainer = document.querySelector('.cart-button-container');
        if (cartButtonContainer) {
            cartButtonContainer.style.display = 'none';
        }
    }
}

// Cart toggle controls
function toggleCart() {
    document.querySelector('.cart-sidebar').classList.toggle('open');
}

function showCart() {
    document.querySelector('.cart-sidebar').classList.add('open');
}

function closeCart() {
    document.querySelector('.cart-sidebar').classList.remove('open');
}

// Order type toggle functionality
<?php if ($delivery_active && $dining_active): ?>
document.getElementById('deliveryBtn').addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('dinningBtn').classList.remove('active');
    document.querySelector('.delivery-details').style.display = 'block';
    document.querySelector('.dinning-details').style.display = 'none';
    updateCartUI();
});

document.getElementById('dinningBtn').addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('deliveryBtn').classList.remove('active');
    document.querySelector('.dinning-details').style.display = 'block';
    document.querySelector('.delivery-details').style.display = 'none';
    updateCartUI();
});
<?php endif; ?>










async function placeOrder() {
    // Validate cart is not empty
    if (!cart || cart.length === 0) {
        showToast('Your cart is empty', 'error');
        return;
    }

    // Get order type and required elements
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn")?.classList.contains("active")' : 'false' ?>;
    const placeOrderBtn = document.getElementById('placeOrderBtn');
    if (!placeOrderBtn) {
        console.error('Place order button not found');
        return;
    }

    const phoneInput = isDelivery ? document.getElementById('customerPhone') : document.getElementById('dinningPhone');
    const nameInput = isDelivery ? document.getElementById('customerName') : document.getElementById('dinningName');
    
    if (!phoneInput || !nameInput) {
        showToast('Required form elements missing', 'error');
        return;
    }

    const originalBtnText = placeOrderBtn.innerHTML;

    try {
        // 1. VALIDATION PHASE
        const phoneNumber = phoneInput.value ? String(phoneInput.value).replace(/\D/g, '') : '';
        if (phoneNumber.length !== 10) {
            throw new Error('Please enter a valid 10-digit phone number');
        }

        const customerName = nameInput.value ? String(nameInput.value).trim() : '';
        if (!customerName || customerName.length > 100) {
            throw new Error('Please enter a valid name (max 100 characters)');
        }

        // Delivery-specific validation
        if (isDelivery) {
            const addressInput = document.getElementById('customerAddress');
            const address = addressInput?.value ? String(addressInput.value).trim() : '';
            if (!address || address.length < 10) {
                throw new Error('Please enter a complete delivery address');
            }
        } else {
            // Dining-specific validation
            const tableInput = document.getElementById('tableNumber');
            const tableNumber = tableInput?.value;
            if (!tableNumber || isNaN(tableNumber)) {
                throw new Error('Please select a valid table number');
            }
        }

        // 2. PREPARE ORDER DATA WITH SAFE ACCESSORS
        const orderData = {
            user_id: <?= $user_id ?>,
            order_type: isDelivery ? 'delivery' : 'dining',
            customer_name: customerName.substring(0, 100),
            customer_phone: phoneNumber,
            items: cart.map(item => ({
                id: item.id ? String(item.id).substring(0, 50) : '',
                name: item.name ? String(item.name).substring(0, 255) : '',
                price: item.price ? parseFloat(item.price).toFixed(2) : '0.00',
                quantity: item.quantity ? parseInt(item.quantity) : 1,
                image_path: item.image_path ? String(item.image_path).substring(0, 512) : null
            })),
            subtotal: calculateSubtotal().toFixed(2),
            discount_amount: discountAmount ? parseFloat(discountAmount).toFixed(2) : '0.00',
            discount_type: discountType ? String(discountType).substring(0, 100) : '',
            gst_percent: <?= $gst_percent ?? 0 ?>,
            delivery_charge: isDelivery ? calculateDeliveryCharge().toFixed(2) : '0.00',
            order_notes: (isDelivery 
                ? document.getElementById('customerNotes')?.value.trim() 
                : document.getElementById('dinningNotes')?.value.trim() || ''
            ).substring(0, 500),
            ...(isDelivery ? {
                delivery_address: document.getElementById('customerAddress')?.value.trim().substring(0, 500) || ''
            } : {
                table_number: document.getElementById('tableNumber')?.value || ''
            }),
            ...(window.appliedCoupon ? {
                coupon_id: window.appliedCoupon.coupon_id || 0,
                coupon_code: window.appliedCoupon.coupon_code ? String(window.appliedCoupon.coupon_code).substring(0, 50) : '',
                coupon_discount: window.couponDiscount ? parseFloat(window.couponDiscount).toFixed(2) : '0.00'
            } : {})
        };

        // 3. SHOW LOADING STATE
        placeOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Placing Order...';
        placeOrderBtn.disabled = true;

        // 4. SUBMIT ORDER
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15000);

        const response = await fetch('place_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(orderData),
            signal: controller.signal
        });

        clearTimeout(timeoutId);

        // 5. HANDLE RESPONSE
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `Order failed (HTTP ${response.status})`);
        }

        // After getting the order placement response
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Order processing failed');
        }

        // 6. RECORD COUPON USAGE (if applied) - ADD THIS SECTION
        if (result.success && result.order_id && window.appliedCoupon) {
            try {
                const couponData = {
                    coupon_id: window.appliedCoupon.coupon_id,
                    phone_number: phoneNumber,
                    order_id: result.order_id,
                    discount_amount: window.couponDiscount,
                    cart_total: calculateSubtotal(),
                    user_id: <?= $user_id ?>
                };
                
                const couponResult = await recordCouponUsage(couponData);
                if (!couponResult.success) {
                    console.warn('Coupon tracking completed with warnings:', couponResult.message);
                }
            } catch (e) {
                console.error('Coupon tracking failed, but order was placed:', e);
            }
        }

        // 7. SUCCESS HANDLING - This is your existing code
        showToast('Order placed successfully!', 'success');

        // Clear cart and reset UI
        cart = [];
        saveCart();
        updateCartUI();
        closeCart();

        // Optional post-order actions
        if (result.trigger_whatsapp) {
            setTimeout(() => {
                try {
                    placeOrderOnWhatsApp();
                } catch (e) {
                    console.error('WhatsApp trigger failed:', e);
                }
            }, 1000);
        }

        if (result.redirect_url) {
            setTimeout(() => {
                window.location.href = result.redirect_url;
            }, 2000);
        }

    } catch (error) {
        console.error('Order Error:', {
            error: error.message,
            time: new Date().toISOString()
        });

        showToast(
            error.message.includes('timeout') 
                ? 'Order timed out. Please try again.' 
                : error.message || 'Failed to place order',
            'error'
        );
    } finally {
        placeOrderBtn.innerHTML = originalBtnText;
        placeOrderBtn.disabled = false;
    }
    console.log('Checking if coupon should be recorded...');
    console.log('Order success:', result.success);
    console.log('Order ID:', result.order_id);
    console.log('Applied coupon:', window.appliedCoupon);
}

// Enhanced coupon tracking function
async function recordCouponUsage(data) {
    try {
        // Add validation
        if (!data || !data.coupon_id || !data.order_id || !data.phone_number) {
            throw new Error('Invalid coupon usage data');
        }

        const response = await fetch('record_coupon_usage.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
            timeout: 5000 // 5 second timeout
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `HTTP ${response.status}`);
        }

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Coupon tracking failed');
        }

        return result;

    } catch (error) {
        console.error('Coupon Tracking Error:', {
            error: error.message,
            couponData: data,
            time: new Date().toISOString()
        });
        
        // Don't fail the order if coupon tracking fails
        return {success: false, message: error.message};
    }
}






// Helper functions used by placeOrder()
function calculateSubtotal() {
    return cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
}





function calculateDeliveryCharge() {
    const subtotal = calculateSubtotal() - discountAmount;
    const deliveryCharge = <?= $delivery_charges['delivery_charge'] ?? 0 ?>;
    const freeDeliveryMin = <?= $delivery_charges['free_delivery_minimum'] ?? 0 ?>;
    
    // If free delivery minimum is set and cart meets it
    if (freeDeliveryMin > 0 && subtotal >= freeDeliveryMin) {
        return 0;
    }
    
    // Otherwise return the delivery charge
    return deliveryCharge;
}



/**
 * Calculates the total order amount including discounts, taxes, and delivery charges
 * @returns {number} The total amount rounded to 2 decimal places
 */
function calculateTotal() {
    try {
        const subtotal = calculateSubtotal();
        
        // Ensure amounts are valid numbers
        if (isNaN(subtotal) || isNaN(discountAmount)) {
            throw new Error('Invalid calculation values');
        }
        
        const amountAfterDiscount = Math.max(0, subtotal - discountAmount);
        const gstPercent = <?= $gst_percent ?? 0 ?>;
        const gstAmount = gstPercent > 0 ? amountAfterDiscount * (gstPercent / 100) : 0;
        const deliveryCharge = calculateDeliveryCharge();
        
        // Round to 2 decimal places to avoid floating point precision issues
        const total = parseFloat((amountAfterDiscount + gstAmount + deliveryCharge).toFixed(2));
        
        // Validate the final total
        if (total < 0) {
            console.warn('Negative total calculated. Returning 0.');
            return 0;
        }
        
        return total;
    } catch (error) {
        console.error('Error in calculateTotal:', error);
        return 0; // Fallback value
    }
}



// Toast notification function (add this to your code if you don't have it already)
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            ${type === 'success' ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-exclamation-circle-fill"></i>'}
        </div>
        <div class="toast-message">${message}</div>
        <div class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}




// Add click handler to the Place Order button
document.querySelector('.cart-footer button').addEventListener('click', placeOrder);










// Place order on WhatsApp
function placeOrderOnWhatsApp() {
    if (cart.length === 0) {
        showToast('Your cart is empty', 'error');
        return;
    }

    <?php if ($delivery_active || $dining_active): ?>
    const isDelivery = <?= $delivery_active ? 'document.getElementById("deliveryBtn").classList.contains("active")' : 'false' ?>;
    const deliveryCharge = Number(<?= json_encode($delivery_charges['delivery_charge'] ?? 0) ?>);
    const freeDeliveryMin = Number(<?= json_encode($delivery_charges['free_delivery_minimum'] ?? 0) ?>);
    const gstPercent = Number(<?= json_encode($gst_percent ?? 0) ?>);
    
    // Validate required fields
    const phoneInput = isDelivery ? document.getElementById('customerPhone') : document.getElementById('dinningPhone');
    if (!phoneInput.value || phoneInput.value.length !== 10) {
        showToast('Please enter a valid 10-digit phone number', 'error');
        phoneInput.focus();
        return;
    }

    let customerName, orderDetails;
    
    // In placeOrderOnWhatsApp()
    if (isDelivery) {
        const deliveryCharge = <?= $delivery_charges['delivery_charge'] ?? 0 ?>;
        const freeDeliveryMin = <?= $delivery_charges['free_delivery_minimum'] ?? 0 ?>;
        
        if (freeDeliveryMin > 0 && (subtotal - discountAmount) >= freeDeliveryMin) {
            message += `Delivery:       FREE (Order above ₹${freeDeliveryMin.toFixed(2)})\n`;
        } else {
            message += `Delivery:       ₹${deliveryCharge.toFixed(2)}\n`;
            if (freeDeliveryMin > 0) {
                const neededForFree = freeDeliveryMin - (subtotal - discountAmount);
                message += `(Add ₹${neededForFree.toFixed(2)} more for FREE delivery)\n`;
            }
        }
    } else {
        customerName = document.getElementById('dinningName').value;
        const customerPhone = phoneInput.value;
        const tableNumber = document.getElementById('tableNumber').value;
        const dinningNotes = document.getElementById('dinningNotes').value;
        
        if (!customerName || !tableNumber) {
            showToast('Please provide your name and table number', 'error');
            return;
        }
        
        orderDetails = `*Dining Order*\nName: ${customerName}\nPhone: ${customerPhone}\nTable No.: ${tableNumber}`;
        if (dinningNotes) orderDetails += `\nNotes: ${dinningNotes}`;
    }
    <?php else: ?>
    let customerName = 'Guest';
    let orderDetails = `*Quick Order*`;
    <?php endif; ?>

    // Get WhatsApp number safely
    const whatsappLink = <?= json_encode($social_link['whatsapp'] ?? '') ?>;
    let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || <?= json_encode($user['phone'] ?? '') ?>;

    if (!phoneNumber) {
        showToast('WhatsApp number not available for this business', 'error');
        return;
    }

    // Format order date
    const orderDate = new Date().toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    // Calculate order totals
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });

    // Business details
    const businessName = <?= json_encode(htmlspecialchars($business_info['business_name'] ?? '')) ?>;
    const businessAddress = <?= json_encode(htmlspecialchars($business_info['business_address'] ?? '')) ?>;
    const businessPhone = <?= json_encode($user['phone'] ?? '') ?>;

    // Build WhatsApp message
    let message = `*${businessName.toUpperCase()}*\n` +
                  `${businessAddress}\n` +
                  `Phone: ${businessPhone}\n\n` +
                  `Date: ${orderDate}\n` +
                  `Order Type: ${isDelivery ? 'DELIVERY' : 'DINING'}\n` +
                  `--------------------------\n` +
                  `*ITEMS ORDERED*\n` +
                  `--------------------------\n`;

    // Add cart items
    cart.forEach(item => {
        const itemTotal = (item.price * item.quantity).toFixed(2);
        message += `${item.name} x ${item.quantity}\n` +
                  `₹${item.price.toFixed(2)} x ${item.quantity} = ₹${itemTotal}\n\n`;
    });

    // Add pricing summary
    message += `--------------------------\n` +
               `Subtotal:        ₹${subtotal.toFixed(2)}\n`;
    
    if (discountAmount > 0) {
        message += `Discount:       -₹${discountAmount.toFixed(2)}\n` +
                   `(Applied ${discountType})\n`;
    }
    
    if (gstPercent > 0) {
        const gstAmount = ((subtotal - discountAmount) * gstPercent / 100).toFixed(2);
        message += `GST (${gstPercent}%):    ₹${gstAmount}\n`;
    }
    
    // In your placeOrderOnWhatsApp() function:
    if (isDelivery) {
        if (freeDeliveryMin > 0 && (subtotal - discountAmount) >= freeDeliveryMin) {
            message += `Delivery:       FREE\n` +
                       `(Order above ₹${freeDeliveryMin.toFixed(2)})\n`;
        } else {
            message += `Delivery:       ₹${Number(deliveryCharge).toFixed(2)}\n`;
            if (freeDeliveryMin > 0) {
                const neededForFree = freeDeliveryMin - (subtotal - discountAmount);
                message += `(Add ₹${neededForFree.toFixed(2)} more for FREE delivery)\n`;
            }
        }
    }

    // Calculate total
    let total = (subtotal - discountAmount) + (gstPercent > 0 ? ((subtotal - discountAmount) * gstPercent / 100) : 0);
    if (isDelivery && !isNaN(deliveryCharge)) total += Number(deliveryCharge);
    
    message += `--------------------------\n` +
               `*TOTAL:          ₹${total.toFixed(2)}*\n\n` +
               `*CUSTOMER DETAILS*\n` +
               `--------------------------\n` +
               `${orderDetails}\n\n` +
               `Please confirm this order.`;

    // Safari-compatible WhatsApp opening
    const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
    
    // Solution 1: Create and click a hidden link (most reliable for Safari)
    const link = document.createElement('a');
    link.href = whatsappUrl;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.style.display = 'none';
    document.body.appendChild(link);
    
    try {
        link.click();
    } catch (e) {
        // Fallback if click fails
        window.location.href = whatsappUrl;
    }
    
    // Clean up
    setTimeout(() => {
        document.body.removeChild(link);
    }, 1000);

    // Reset cart
    cart = [];
    saveCart();
    updateCartUI();
    closeCart();
    
    // Delay toast to ensure WhatsApp opens first
    setTimeout(() => {
        showToast('Order sent via WhatsApp!', 'success');
    }, 500);
}

</script>

