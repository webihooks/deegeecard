<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
session_start();
require 'config.php';
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user details
$user_name = '';
$sql_name = "SELECT name FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $stmt_name->bind_result($user_name);
    $stmt_name->fetch();
    $stmt_name->close();
}

// First, check if user has an active subscription
$subscription_sql = "SELECT status FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1";
$subscription_stmt = $conn->prepare($subscription_sql);
$subscription_stmt->bind_param("i", $user_id);
$subscription_stmt->execute();
$subscription_stmt->store_result();
$has_active_subscription = ($subscription_stmt->num_rows > 0);
$subscription_stmt->close();

// Fetch current subscription
$current_subscription = null;
$sql_subscription = "SELECT s.package_id, s.start_date, s.end_date, s.status, p.name as package_name, p.price 
                    FROM subscriptions s
                    JOIN packages p ON s.package_id = p.id
                    WHERE s.user_id = ? AND s.status = 'active'";
$stmt_sub = $conn->prepare($sql_subscription);
if ($stmt_sub) {
    $stmt_sub->bind_param("i", $user_id);
    $stmt_sub->execute();
    $result = $stmt_sub->get_result();
    $current_subscription = $result->fetch_assoc();
    $stmt_sub->close();
}

// Fetch available packages
$packages = [];
$current_package_id = $current_subscription['package_id'] ?? null;

// If user has Delivery (1) or Dining (2) package, show their current package and Premium as upgrade
if ($current_package_id == 1 || $current_package_id == 2) {
    // Get current package (show even if inactive)
    $sql_current = "SELECT id, name, price, description, duration FROM packages WHERE id = ?";
    $stmt_current = $conn->prepare($sql_current);
    $stmt_current->bind_param("i", $current_package_id);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();
    if ($row = $result_current->fetch_assoc()) {
        $packages[] = $row;
    }
    $stmt_current->close();
    
    // Get Premium package (only if active)
    $sql_premium = "SELECT id, name, price, description, duration FROM packages WHERE id = 3 AND status = 'active'";
    $result_premium = $conn->query($sql_premium);
    if ($row = $result_premium->fetch_assoc()) {
        $packages[] = $row;
    }
} 
// If user has Premium package (3), show only Premium package
elseif ($current_package_id == 3) {
    $sql_premium = "SELECT id, name, price, description, duration FROM packages WHERE id = 3";
    $result_premium = $conn->query($sql_premium);
    if ($row = $result_premium->fetch_assoc()) {
        $packages[] = $row;
    }
}
else {
    // Show all active packages for users with no subscription
    $sql_packages = "SELECT id, name, price, description, duration FROM packages WHERE status = 'active'";
    $result_packages = $conn->query($sql_packages);
    if ($result_packages) {
        while ($row = $result_packages->fetch_assoc()) {
            $packages[] = $row;
        }
    }
}

// Fetch active addons
$addons = [];
$sql_addons = "SELECT * FROM addons WHERE status = 1 ORDER BY created_at DESC";
$result_addons = $conn->query($sql_addons);
if ($result_addons) {
    while ($row = $result_addons->fetch_assoc()) {
        $addons[] = $row;
    }
}

// Handle subscription cancel
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_subscription'])) {
    $sql = "UPDATE subscriptions SET status = 'canceled', end_date = NOW() 
            WHERE user_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Subscription canceled successfully";
        $current_subscription = null;
        $stmt->close();
        header("Location: subscription.php");
        exit();
    } else {
        $error = "Error canceling subscription: " . $conn->error;
        $stmt->close();
    }
}

// Determine trial status before including menu
$is_trial = false;
$trial_end = null;

$sql_trial = "SELECT is_trial, trial_end FROM users WHERE id = ?";
$stmt_trial = $conn->prepare($sql_trial);
if ($stmt_trial) {
    $stmt_trial->bind_param("i", $user_id);
    $stmt_trial->execute();
    $stmt_trial->bind_result($is_trial_db, $trial_end_db);
    if ($stmt_trial->fetch()) {
        $is_trial = (bool)$is_trial_db;
        $trial_end = $trial_end_db;
    }
    $stmt_trial->close();
}

// Fetch active announcements
$announcements = [];
$sql_announcements = "SELECT title, content FROM announcements WHERE is_active = 1 ORDER BY created_at DESC";
$result_announcements = $conn->query($sql_announcements);
if ($result_announcements) {
    while ($row = $result_announcements->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Subscription Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        .addon-card {
            transition: transform 0.3s;
        }
        .addon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .addon-img {
            height: 200px;
            object-fit: cover;
        }
        .special-price {
            color: #dc3545;
            font-weight: bold;
        }
        .original-price {
            text-decoration: line-through;
            color: #6c757d;
        }
        .modal-title {
          margin-top: 0;
          color: #fff;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        
        <?php
        // Check subscription or active trial (not expired) status
        $is_active_trial = $is_trial && (strtotime($trial_end) > time());
        if ($has_active_subscription || $is_active_trial) {
            include 'menu.php';
        } else {
            include 'unsubscriber_menu.php';
        }
        ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Subscription Management</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['message'])): ?>
                                    <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>

                                <?php if ($current_subscription): ?>
                                    <div class="current-subscription mb-1">
                                        <div class="card">
                                            <div class="card-header">
                                                <h4>Your Current Subscription: <?php echo htmlspecialchars($current_subscription['package_name']); ?></h4>
                                            </div>
                                            <div class="card-body">
                                                <p>
                                                    <strong>Status:</strong> Active<br>
                                                    <strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($current_subscription['start_date'])); ?><br>
                                                    <strong>Renewal Date:</strong> <?php echo date('M d, Y', strtotime($current_subscription['end_date'])); ?>
                                                </p>
                                                <?php if ($current_package_id != 3): // Don't show cancel for Premium ?>
                                                    <form method="post">
                                                        <button type="submit" name="cancel_subscription" class="btn btn-danger" style="display:none;">Cancel Subscription</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        You don't have an active subscription.
                                    </div>
                                <?php endif; ?>

                                <div class="available-packages mb-1">
                                    <h5 class="mb-4">Available Subscription Plans</h5>
                                    <div class="row">
                                        <?php foreach ($packages as $package): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="card h-100 <?php echo ($current_package_id == $package['id']) ? 'border-primary' : ''; ?>">
                                                    <div class="card-header">
                                                        <h5 class="text-center"><?php echo htmlspecialchars($package['name']); ?></h5>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        <h3>₹<?php echo (int)$package['price']; ?></h3>
                                                        <p><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                                                        <?php if ($current_package_id != $package['id']): ?>
                                                            <button class="btn btn-primary subscribe-btn" 
                                                                data-package-id="<?php echo $package['id']; ?>"
                                                                data-package-name="<?php echo htmlspecialchars($package['name']); ?>"
                                                                data-package-price="<?php echo $package['price']; ?>">
                                                                <?php 
                                                                    if ($current_package_id && $package['id'] == 3) {
                                                                        echo "Upgrade to Premium";
                                                                    } else {
                                                                        echo "Subscribe Now";
                                                                    }
                                                                ?>
                                                            </button>
                                                        <?php else: ?>
                                                            <div class="mt-2 text-success">Your current plan</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Addons Section -->
                                <div class="available-addons">
                                    <h5 class="mb-4">Premium Addons</h5>
                                    <div class="row">
                                        <?php foreach ($addons as $addon): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="card h-100 addon-card">
                                                    <?php if (!empty($addon['image'])): ?>
                                                        <img src="<?php echo $addon['image']; ?>" class="card-img-top addon-img" alt="<?php echo htmlspecialchars($addon['name']); ?>">
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($addon['name']); ?></h5>
                                                        <p class="card-text"><?php echo htmlspecialchars($addon['description']); ?></p>
                                                        

<div class="price-section mb-3">
    <?php 
    $has_special = ($addon['special_price'] !== null && 
                  (empty($addon['valid_until']) || strtotime($addon['valid_until']) >= time()));
    ?>
    
    <?php if ($has_special): ?>
        <span class="special-price">₹<?php echo (int)$addon['special_price']; ?></span>
        <span class="original-price ms-2">₹<?php echo (int)$addon['price']; ?></span>
        <?php if ($addon['valid_until']): ?>
            <small class="text-muted d-block">Offer valid until <?php echo date('M d, Y', strtotime($addon['valid_until'])); ?></small>
        <?php endif; ?>
    <?php else: ?>
        <span class="special-price">₹<?php echo (int)$addon['price']; ?></span>
    <?php endif; ?>
</div>
                                                        <button class="btn btn-success buy-addon-btn w-100"
                                                            data-addon-id="<?php echo $addon['id']; ?>"
                                                            data-addon-name="<?php echo htmlspecialchars($addon['name']); ?>"
                                                            data-addon-price="<?php echo ($addon['special_price'] !== null && $addon['special_price'] < $addon['price']) ? $addon['special_price'] : $addon['price']; ?>">
                                                            Buy Now
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>


<!-- Announcements Modal -->
<div class="modal fade" id="announcementsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Announcements</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($announcements)): ?>
                    <div id="announcementsCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($announcements as $index => $announcement): ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <h5><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                    <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($announcements) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#announcementsCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#announcementsCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>No current announcements.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
    // Show announcements modal if there are announcements
    $(window).on('load', function() {
        <?php if (!empty($announcements)): ?>
            var announcementsShown = localStorage.getItem('announcementsShown');
            if (!announcementsShown) {
                $('#announcementsModal').modal('show');
                localStorage.setItem('announcementsShown', 'true');
                
                // Reset the flag after 24 hours
                setTimeout(function() {
                    localStorage.removeItem('announcementsShown');
                }, 24 * 60 * 60 * 1000);
            }
        <?php endif; ?>
    });
    
    $(document).ready(function() {
        // Subscription button handler
        $('.subscribe-btn').click(function() {
            var packageId = $(this).data('package-id');
            var packageName = $(this).data('package-name');
            var packagePrice = $(this).data('package-price') * 100; // In paise

            $.ajax({
                url: 'create_order.php',
                method: 'POST',
                data: { amount: packagePrice },
                success: function(order) {
                    var options = {
                        "key": "<?php echo RAZORPAY_KEY_ID; ?>",
                        "amount": packagePrice,
                        "currency": "INR",
                        "name": "DeeGeeCard",
                        "description": packageName,
                        "order_id": JSON.parse(order).order_id,
                        "handler": function (response) {
                            $.ajax({
                                url: 'process_subscription.php',
                                method: 'POST',
                                data: {
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature,
                                    package_id: packageId
                                },
                                success: function(data) {
                                    alert("Subscription successful!");
                                    location.reload();
                                },
                                error: function(err) {
                                    alert('Payment processing failed. Please try again or contact support.');
                                    console.error(err);
                                }
                            });
                        },
                        "theme": { "color": "#3399cc" }
                    };
                    var rzp1 = new Razorpay(options);
                    rzp1.open();
                },
                error: function(err) {
                    alert('Unable to initiate payment.');
                }
            });
        });


// In your subscription.php
$('.buy-addon-btn').click(function() {
    var btn = $(this);
    var addonId = btn.data('addon-id');
    var addonName = btn.data('addon-name');
    
    // Get the price from the displayed special price or regular price
    var priceElement = btn.closest('.card-body').find('.special-price');
    var addonPrice = parseFloat(priceElement.text().replace('₹', '').trim());
    
    // Convert to paise
    addonPrice = addonPrice * 100;
    
    // Show loading state
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');

    $.ajax({
        url: 'create_order.php',
        method: 'POST',
        data: { amount: addonPrice },
        success: function(order) {
            try {
                var orderData = JSON.parse(order);
                if (!orderData.order_id) {
                    throw new Error('Invalid order response');
                }
                
                var options = {
                    "key": "<?php echo RAZORPAY_KEY_ID; ?>",
                    "amount": addonPrice,
                    "currency": "INR",
                    "name": "DeeGeeCard",
                    "description": addonName + " Addon",
                    "order_id": orderData.order_id,
                    "handler": function (response) {
                        processAddonPayment(response, addonId, btn);
                    },
                    "modal": {
                        "ondismiss": function() {
                            btn.prop('disabled', false).html('Buy Now');
                        }
                    },
                    "theme": { "color": "#3399cc" }
                };
                var rzp1 = new Razorpay(options);
                rzp1.open();
            } catch (e) {
                console.error(e);
                alert('Error creating payment order. Please try again.');
                btn.prop('disabled', false).html('Buy Now');
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('Failed to initiate payment. Please try again later.');
            btn.prop('disabled', false).html('Buy Now');
        }
    });
});

function processAddonPayment(response, addonId, btn) {
    $.ajax({
        url: 'process_addon_purchase.php',
        method: 'POST',
        data: {
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_signature: response.razorpay_signature,
            addon_id: addonId
        },
        success: function(data) {
            try {
                var result = JSON.parse(data);
                if (result.status === 'success') {
                    alert("Addon purchased successfully!");
                    location.reload();
                } else {
                    throw new Error(result.message || 'Payment processing failed');
                }
            } catch (e) {
                console.error(e);
                alert('Payment verification failed. Please contact support.');
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('Payment processing failed. Please check your email for confirmation or contact support.');
        },
        complete: function() {
            btn.prop('disabled', false).html('Buy Now');
        }
    });
}


    });
    </script>
</body>
</html>