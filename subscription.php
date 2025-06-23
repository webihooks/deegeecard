<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Fetch available packages including description and duration WHERE status is active
$packages = [];
$sql_packages = "SELECT id, name, price, description, duration FROM packages WHERE status = 'active'";
$result_packages = $conn->query($sql_packages);
if ($result_packages) {
    while ($row = $result_packages->fetch_assoc()) {
        $packages[] = $row;
    }
}

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

<script>
$(document).ready(function() {
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
                    "key": "<?php echo RAZORPAY_KEY_ID; ?>", // Ensure your Razorpay key is correct
                    "amount": packagePrice,
                    "currency": "INR",
                    "name": "DeeGeeCard",
                    "description": packageName,
                    "order_id": JSON.parse(order).order_id,
                    "handler": function (response) {
                        // This is where the AJAX call to process the subscription happens
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
                                alert(data); // You can replace this with a success message on the page itself
                                location.reload(); // Optional: refresh the page to show the updated subscription
                            },
                            error: function(err) {
                                alert('Payment processing failed. Please try again or contact support.');
                                console.error(err); // Log the error to the console for debugging
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
});
</script>

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
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Subscription Management</h4>
                            </div>
                            <div class="card-body">
                                <?php if ($message): ?>
                                    <div class="alert alert-success"><?php echo $message; ?></div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>

                                <?php if ($current_subscription): ?>
                                    <div class="current-subscription mb-4">
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
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        You don't have an active subscription.
                                    </div>
                                <?php endif; ?>

                                <div class="available-packages">
                                    <h5>Available Subscription Plans</h5>
                                    <div class="row">
                                        <?php foreach ($packages as $package): ?>
                                            <div class="col-md-4 mb-4">
                                                <div class="card">
                                                    <div class="card-header">
                                                        <h5 class="text-center"><?php echo htmlspecialchars($package['name']); ?></h5>
                                                    </div>
                                                    <div class="card-body text-center">
                                                        <h3>₹<?php echo number_format($package['price']); ?></h3>
                                                        <p><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                                                        <button class="btn btn-primary subscribe-btn" 
                                                            data-package-id="<?php echo $package['id']; ?>"
                                                            data-package-name="<?php echo htmlspecialchars($package['name']); ?>"
                                                            data-package-price="<?php echo $package['price']; ?>">
                                                            Subscribe Now
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>