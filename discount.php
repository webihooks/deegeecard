<?php
// Start the session
session_start();

// Include the database connection file
require 'db_connection.php';

// Check if the user is logged in
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

// Fetch existing discount for this user (only one allowed)
$discount = null;
$sql = "SELECT id, min_cart_value, discount_in_percent, discount_in_flat, image_path 
        FROM discount 
        WHERE user_id = ? 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $discount = $result->fetch_assoc();
}
$stmt->close();

// Process form submission for adding/editing discount
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_discount'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $min_cart_value = (float)$_POST['min_cart_value'];
        $discount_in_percent = !empty($_POST['discount_in_percent']) ? (float)$_POST['discount_in_percent'] : NULL;
        $discount_in_flat = !empty($_POST['discount_in_flat']) ? (float)$_POST['discount_in_flat'] : NULL;
        
        // Validate input
        $errors = [];
        if ($min_cart_value <= 0) {
            $errors[] = "Minimum cart value must be greater than 0.";
        }
        
        if ($discount_in_percent === NULL && $discount_in_flat === NULL) {
            $errors[] = "You must specify either a percentage discount or a flat discount.";
        } elseif ($discount_in_percent !== NULL && $discount_in_flat !== NULL) {
            $errors[] = "Please select only one discount type (percentage OR flat).";
        }
        
        if ($discount_in_percent !== NULL && ($discount_in_percent <= 0 || $discount_in_percent > 100)) {
            $errors[] = "Percentage discount must be between 0 and 100.";
        }
        
        if ($discount_in_flat !== NULL && $discount_in_flat <= 0) {
            $errors[] = "Flat discount must be greater than 0.";
        }
        
        if (empty($errors)) {
            if ($id > 0) {
                // Update existing discount
                $sql = "UPDATE discount SET 
                        min_cart_value = ?, 
                        discount_in_percent = ?, 
                        discount_in_flat = ?, 
                        updated_at = NOW() 
                        WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dddii", $min_cart_value, $discount_in_percent, $discount_in_flat, $id, $user_id);
            } else {
                // Check if discount already exists
                if ($discount !== null) {
                    $message = "You can only have one discount condition. Please edit the existing one.";
                    $message_type = "danger";
                } else {
                    // Insert new discount
                    $sql = "INSERT INTO discount 
                            (user_id, min_cart_value, discount_in_percent, discount_in_flat, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iddd", $user_id, $min_cart_value, $discount_in_percent, $discount_in_flat);
                }
            }
            
            if (isset($stmt) && $stmt->execute()) {
                $message = "Discount saved successfully!";
                // Refresh the page
                header("Location: discount.php");
                exit();
            } elseif (!isset($stmt)) {
                // Error message already set above
            } else {
                $message = "Error saving discount: " . $conn->error;
                $message_type = "danger";
            }
            
            if (isset($stmt)) {
                $stmt->close();
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = "danger";
        }
    } elseif (isset($_POST['delete_discount'])) {
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM discount WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Discount deleted successfully!";
            // Refresh the page
            header("Location: discount.php");
            exit();
        } else {
            $message = "Error deleting discount: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Discount Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php echo ($role === 'admin') ? include 'admin_menu.php' : include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Discount Management</h4>
                                <button class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                                    <?php echo $discount ? 'Edit Discount' : 'Add Discount'; ?>
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$discount): ?>
                                    <div class="alert alert-info">
                                        No discount configured yet. Click "Add Discount" to create one.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Min Cart Value</th>
                                                    <th>Discount</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>₹<?php echo number_format($discount['min_cart_value'], 2); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($discount['discount_in_percent'] !== NULL) {
                                                            echo number_format($discount['discount_in_percent'], 2) . '%';
                                                        } else {
                                                            echo '₹' . number_format($discount['discount_in_flat'], 2);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning edit-discount" 
                                                                data-id="<?php echo $discount['id']; ?>"
                                                                data-min-cart="<?php echo $discount['min_cart_value']; ?>"
                                                                data-percent="<?php echo $discount['discount_in_percent']; ?>"
                                                                data-flat="<?php echo $discount['discount_in_flat']; ?>">
                                                            Edit
                                                        </button>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="id" value="<?php echo $discount['id']; ?>">
                                                            <button type="submit" name="delete_discount" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this discount?');">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add/Edit Discount Modal -->
    <div class="modal fade" id="addDiscountModal" tabindex="-1" aria-labelledby="addDiscountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDiscountModalLabel"><?php echo $discount ? 'Edit Discount' : 'Add Discount'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="discount.php" id="discountForm">
                    <input type="hidden" name="id" id="discountId" value="<?php echo $discount ? $discount['id'] : 0; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="min_cart_value" class="form-label">Minimum Cart Value (₹)</label>
                            <input type="number" class="form-control" id="min_cart_value" 
                                   name="min_cart_value" step="0.01" min="0.01" 
                                   value="<?php echo $discount ? $discount['min_cart_value'] : ''; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Discount Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="discount_type" 
                                       id="percentDiscount" value="percent" 
                                       <?php echo ($discount && $discount['discount_in_percent'] !== NULL) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="percentDiscount">
                                    Percentage Discount
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="discount_type" 
                                       id="flatDiscount" value="flat"
                                       <?php echo ($discount && $discount['discount_in_flat'] !== NULL) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="flatDiscount">
                                    Flat Discount
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="percentDiscountGroup" style="<?php echo ($discount && $discount['discount_in_flat'] !== NULL) ? 'display:none;' : ''; ?>">
                            <label for="discount_in_percent" class="form-label">Discount Percentage (%)</label>
                            <input type="number" class="form-control" id="discount_in_percent" 
                                   name="discount_in_percent" step="0.01" min="0" max="100"
                                   value="<?php echo $discount ? $discount['discount_in_percent'] : ''; ?>">
                        </div>
                        
                        <div class="mb-3" id="flatDiscountGroup" style="<?php echo ($discount && $discount['discount_in_flat'] !== NULL) ? '' : 'display:none;'; ?>">
                            <label for="discount_in_flat" class="form-label">Flat Discount Amount (₹)</label>
                            <input type="number" class="form-control" id="discount_in_flat" 
                                   name="discount_in_flat" step="0.01" min="0.01"
                                   value="<?php echo $discount ? $discount['discount_in_flat'] : ''; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_discount" class="btn btn-primary">Save Discount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle between percentage and flat discount fields
            $('input[name="discount_type"]').change(function() {
                if ($(this).val() === 'percent') {
                    $('#percentDiscountGroup').show();
                    $('#flatDiscountGroup').hide();
                    $('#discount_in_percent').attr('required', 'required');
                    $('#discount_in_flat').removeAttr('required');
                } else {
                    $('#percentDiscountGroup').hide();
                    $('#flatDiscountGroup').show();
                    $('#discount_in_percent').removeAttr('required');
                    $('#discount_in_flat').attr('required', 'required');
                }
            });
            
            // Edit discount button handler
            $('.edit-discount').click(function() {
                var id = $(this).data('id');
                var minCart = $(this).data('min-cart');
                var percent = $(this).data('percent');
                var flat = $(this).data('flat');
                
                $('#discountId').val(id);
                $('#min_cart_value').val(minCart);
                
                if (percent !== null) {
                    $('input[name="discount_type"][value="percent"]').prop('checked', true);
                    $('#discount_in_percent').val(percent);
                    $('#percentDiscountGroup').show();
                    $('#flatDiscountGroup').hide();
                } else {
                    $('input[name="discount_type"][value="flat"]').prop('checked', true);
                    $('#discount_in_flat').val(flat);
                    $('#percentDiscountGroup').hide();
                    $('#flatDiscountGroup').show();
                }
                
                $('#addDiscountModalLabel').text('Edit Discount');
                $('#addDiscountModal').modal('show');
            });
            
            // Form validation
            $('#discountForm').validate({
                rules: {
                    min_cart_value: {
                        required: true,
                        min: 0.01
                    },
                    discount_in_percent: {
                        required: function() {
                            return $('input[name="discount_type"]:checked').val() === 'percent';
                        },
                        min: 0,
                        max: 100
                    },
                    discount_in_flat: {
                        required: function() {
                            return $('input[name="discount_type"]:checked').val() === 'flat';
                        },
                        min: 0.01
                    }
                },
                messages: {
                    min_cart_value: {
                        required: "Please enter minimum cart value",
                        min: "Minimum cart value must be greater than 0"
                    },
                    discount_in_percent: {
                        required: "Please enter discount percentage",
                        min: "Discount percentage cannot be negative",
                        max: "Discount percentage cannot exceed 100%"
                    },
                    discount_in_flat: {
                        required: "Please enter flat discount amount",
                        min: "Flat discount must be greater than 0"
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