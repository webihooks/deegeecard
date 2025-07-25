<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Process form submission for creating a new coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_coupon'])) {
    $coupon_code = trim($_POST['coupon_code']);
    $discount_type = $_POST['discount_type'];
    $discount_value = (float)$_POST['discount_value'];
    $min_cart_value = (float)$_POST['min_order'];
    $max_discount = (float)$_POST['max_discount'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $usage_limit = (int)$_POST['usage_limit'];
    $coupon_name = !empty($_POST['coupon_name']) ? $_POST['coupon_name'] : null;
    $description = !empty($_POST['description']) ? $_POST['description'] : null;
    
    // Validate input
    $errors = [];
    
    // Date validation
    if (!DateTime::createFromFormat('Y-m-d', $start_date)) {
        $errors[] = "Invalid start date format. Please use YYYY-MM-DD.";
    }
    
    if (!DateTime::createFromFormat('Y-m-d', $end_date)) {
        $errors[] = "Invalid end date format. Please use YYYY-MM-DD.";
    }
    
    if (empty($coupon_code)) {
        $errors[] = "Coupon code is required.";
    }
    
    if ($discount_value <= 0) {
        $errors[] = "Discount value must be greater than 0.";
    }
    
    if ($discount_type === 'percent' && ($discount_value < 1 || $discount_value > 100)) {
        $errors[] = "Percentage discount must be between 1 and 100.";
    }
    
    if ($min_cart_value < 0) {
        $errors[] = "Minimum order cannot be negative.";
    }
    
    if ($max_discount < 0) {
        $errors[] = "Maximum discount cannot be negative.";
    }
    
    if (strtotime($start_date) > strtotime($end_date)) {
        $errors[] = "End date must be after start date.";
    }
    
    if ($usage_limit < 0) {
        $errors[] = "Usage limit cannot be negative.";
    }
    
    if (empty($errors)) {
        // Check if coupon code already exists
        $sql_check = "SELECT id FROM coupons WHERE coupon_code = ? AND user_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $coupon_code, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $message = "Coupon code already exists. Please choose a different one.";
            $message_type = "danger";
        } else {
            // Insert new coupon with proper date handling
            $sql = "INSERT INTO coupons (
                user_id, 
                coupon_code, 
                coupon_name,
                description,
                discount_type, 
                discount_value, 
                min_cart_value, 
                max_discount, 
                valid_from, 
                valid_to, 
                usage_limit,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            
            // Convert discount type to match enum values
            $db_discount_type = ($discount_type === 'percentage') ? 'percent' : 'flat';
            
            // Ensure dates are in correct format
            $formatted_start = date('Y-m-d H:i:s', strtotime($start_date));
            $formatted_end = date('Y-m-d H:i:s', strtotime($end_date));
            
            // Handle max discount - set to NULL if not applicable
            $db_max_discount = ($discount_type === 'percent' && $max_discount > 0) ? $max_discount : NULL;
            
            $stmt->bind_param("issssdddssi", 
                $user_id,
                $coupon_code,
                $coupon_name,
                $description,
                $db_discount_type,
                $discount_value,
                $min_cart_value,
                $db_max_discount,
                $formatted_start,
                $formatted_end,
                $usage_limit
            );
            
            if ($stmt->execute()) {
                $message = "Coupon created successfully!";
            } else {
                $message = "Error creating coupon: " . $conn->error;
                $message_type = "danger";
            }
            $stmt->close();
        }
        $stmt_check->close();
    } else {
        $message = implode("<br>", $errors);
        $message_type = "danger";
    }
}

// Process coupon deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    
    $sql_check = "SELECT id FROM coupons WHERE id = ? AND user_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $coupon_id, $user_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $sql = "DELETE FROM coupons WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $coupon_id);
        
        if ($stmt->execute()) {
            $message = "Coupon deleted successfully!";
        } else {
            $message = "Error deleting coupon: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    } else {
        $message = "Coupon not found or you don't have permission to delete it.";
        $message_type = "danger";
    }
    $stmt_check->close();
}

// Fetch all coupons for this user
$coupons = [];
$sql = "SELECT 
            id, 
            coupon_code, 
            discount_type, 
            discount_value, 
            min_cart_value as min_order, 
            max_discount, 
            valid_from as start_date, 
            valid_to as end_date, 
            usage_limit, 
            created_at, 
            is_active as status 
        FROM coupons 
        WHERE user_id = ? 
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $coupons[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Coupon Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include ($role === 'admin') ? 'admin_menu.php' : 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Coupon Management</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Create New Coupon</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="coupon.php" id="couponForm">
                                                    <div class="mb-3">
                                                        <label for="coupon_code" class="form-label">Coupon Code *</label>
                                                        <input type="text" class="form-control" id="coupon_code" name="coupon_code" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="coupon_name" class="form-label">Coupon Name</label>
                                                        <input type="text" class="form-control" id="coupon_name" name="coupon_name">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="description" class="form-label">Description</label>
                                                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="discount_type" class="form-label">Discount Type *</label>
                                                        <select class="form-select" id="discount_type" name="discount_type" required>
                                                            <option value="percent">Percentage</option>
                                                            <option value="flat">Fixed Amount</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="discount_value" class="form-label">Discount Value *</label>
                                                        <input type="number" class="form-control" id="discount_value" name="discount_value" step="0.01" min="0.01" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="min_order" class="form-label">Minimum Order Amount</label>
                                                        <input type="number" class="form-control" id="min_order" name="min_order" step="0.01" min="0" value="0">
                                                    </div>
                                                    
                                                    <div class="mb-3" id="max_discount_container">
                                                        <label for="max_discount" class="form-label">Maximum Discount (for percentage only)</label>
                                                        <input type="number" class="form-control" id="max_discount" name="max_discount" step="0.01" min="0" value="0">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="start_date" class="form-label">Start Date *</label>
                                                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="end_date" class="form-label">End Date *</label>
                                                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="usage_limit" class="form-label">Usage Limit (0 for unlimited)</label>
                                                        <input type="number" class="form-control" id="usage_limit" name="usage_limit" min="0" value="0">
                                                    </div>
                                                    
                                                    <button type="submit" name="create_coupon" class="btn btn-primary">Create Coupon</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title">Your Coupons</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($coupons)): ?>
                                                    <p>No coupons found.</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Code</th>
                                                                    <th>Discount</th>
                                                                    <th>Valid Until</th>
                                                                    <th>Status</th>
                                                                    <th>Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($coupons as $coupon): 
                                                                    $today = date('Y-m-d');
                                                                    $status = $coupon['status'];
                                                                    
                                                                    if ($status === 0) {
                                                                        $badge_class = 'bg-secondary';
                                                                        $status_text = 'Inactive';
                                                                    } elseif ($today < $coupon['start_date']) {
                                                                        $badge_class = 'bg-info';
                                                                        $status_text = 'Upcoming';
                                                                    } elseif ($today > $coupon['end_date']) {
                                                                        $badge_class = 'bg-danger';
                                                                        $status_text = 'Expired';
                                                                    } else {
                                                                        $badge_class = 'bg-success';
                                                                        $status_text = 'Active';
                                                                    }
                                                                ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                                                                        <td>
                                                                            <?php 
                                                                                echo htmlspecialchars($coupon['discount_value']);
                                                                                echo ($coupon['discount_type'] === 'percent') ? '%' : '';
                                                                                if ($coupon['discount_type'] === 'percent' && $coupon['max_discount'] > 0) {
                                                                                    echo ' (max ' . htmlspecialchars($coupon['max_discount']) . ')';
                                                                                }
                                                                            ?>
                                                                        </td>
                                                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($coupon['end_date']))); ?></td>
                                                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span></td>
                                                                        <td>
                                                                            <form method="POST" action="coupon.php" style="display:inline;">
                                                                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                                                                <button type="submit" name="delete_coupon" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this coupon?');">Delete</button>
                                                                            </form>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
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
    <script>
        $(document).ready(function() {
            // Initialize date pickers
            flatpickr("#start_date", {
                dateFormat: "Y-m-d",
                minDate: "today"
            });
            
            flatpickr("#end_date", {
                dateFormat: "Y-m-d",
                minDate: "today"
            });
            
            // Show/hide max discount based on discount type
            $('#discount_type').change(function() {
                if ($(this).val() === 'percent') {
                    $('#max_discount_container').show();
                } else {
                    $('#max_discount_container').hide();
                }
            }).trigger('change');
            
            // Form validation
            $("#couponForm").validate({
                rules: {
                    coupon_code: {
                        required: true,
                        minlength: 4
                    },
                    discount_value: {
                        required: true,
                        min: 0.01
                    },
                    min_order: {
                        min: 0
                    },
                    max_discount: {
                        min: 0
                    },
                    start_date: {
                        required: true
                    },
                    end_date: {
                        required: true
                    },
                    usage_limit: {
                        min: 0
                    }
                },
                messages: {
                    coupon_code: {
                        required: "Please enter a coupon code",
                        minlength: "Coupon code must be at least 4 characters long"
                    },
                    discount_value: {
                        required: "Please enter a discount value",
                        min: "Discount must be greater than 0"
                    },
                    min_order: {
                        min: "Minimum order cannot be negative"
                    },
                    max_discount: {
                        min: "Maximum discount cannot be negative"
                    },
                    start_date: {
                        required: "Please select a start date"
                    },
                    end_date: {
                        required: "Please select an end date"
                    },
                    usage_limit: {
                        min: "Usage limit cannot be negative"
                    }
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.mb-3').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid').removeClass('is-valid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                }
            });
        });
    </script>
</body>
</html>