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
$success_message = '';
$error_message = '';
$is_edit_mode = false;
$business_data = null;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Check if we're editing an existing business
if (isset($_GET['edit'])) {
    $business_id = $_GET['edit'];
    
    // Verify the business belongs to the current user
    $sql = "SELECT * FROM business_info WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $business_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $business_data = $result->fetch_assoc();
        $is_edit_mode = true;
    } else {
        $error_message = "Business not found or you don't have permission to edit it.";
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = $_POST['business_name'] ?? '';
    $business_description = $_POST['business_description'] ?? '';
    $business_address = $_POST['business_address'] ?? '';
    $google_direction = $_POST['google_direction'] ?? '';
    $designation = $_POST['designation'] ?? '';
    $business_id = $_POST['business_id'] ?? null;

    // Validate required fields
    if (empty($business_name) || empty($business_address)) {
        $error_message = "Business Name and Address are required fields.";
    } else {
        if ($is_edit_mode && $business_id) {
            // Update existing business
            $sql = "UPDATE business_info SET 
                    business_name = ?, 
                    business_description = ?, 
                    business_address = ?, 
                    google_direction = ?,
                    designation = ?,
                    updated_at = NOW()
                    WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $business_name, $business_description, $business_address, $google_direction, $designation, $business_id, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Business information updated successfully!";
                // Refresh business data
                $business_data = [
                    'business_name' => $business_name,
                    'business_description' => $business_description,
                    'business_address' => $business_address,
                    'google_direction' => $google_direction,
                    'designation' => $designation,
                    'id' => $business_id
                ];
            } else {
                $error_message = "Error updating business information: " . $conn->error;
            }
        } else {
            // Insert new business
            $sql = "INSERT INTO business_info (user_id, business_name, business_description, business_address, google_direction, designation) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssss", $user_id, $business_name, $business_description, $business_address, $google_direction, $designation);
            
            if ($stmt->execute()) {
                $success_message = "Business information added successfully!";
                // Clear form fields
                $_POST = array();
                $business_data = null;
                $is_edit_mode = false;
            } else {
                $error_message = "Error adding business information: " . $conn->error;
            }
        }
        
        $stmt->close();
    }
}

// Fetch all businesses for the current user to display in a list
$businesses = [];
$sql = "SELECT id, business_name, business_address, designation FROM business_info WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Business</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
</head>

<body>

    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Business</h4>
                            </div>
                            <div class="card-body">
                                <h4 class="header-title mb-3"><?php echo $is_edit_mode ? 'Edit' : 'Add'; ?> Business Information</h4>
                                
                                <?php if ($success_message): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="business.php<?php echo $is_edit_mode ? '?edit=' . $business_data['id'] : ''; ?>">
                                    <?php if ($is_edit_mode): ?>
                                        <input type="hidden" name="business_id" value="<?php echo $business_data['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="business_name" class="form-label">Business Name *</label>
                                        <input type="text" class="form-control" id="business_name" name="business_name" 
                                               value="<?php echo htmlspecialchars($business_data['business_name'] ?? $_POST['business_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="designation" class="form-label">Designation</label>
                                        <input type="text" class="form-control" id="designation" name="designation" 
                                               value="<?php echo htmlspecialchars($business_data['designation'] ?? $_POST['designation'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="business_description" class="form-label">Business Description</label>
                                        <textarea class="form-control" id="business_description" name="business_description" 
                                                  rows="3"><?php echo htmlspecialchars($business_data['business_description'] ?? $_POST['business_description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="business_address" class="form-label">Business Address *</label>
                                        <textarea class="form-control" id="business_address" name="business_address" 
                                                  rows="3" required><?php echo htmlspecialchars($business_data['business_address'] ?? $_POST['business_address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="google_direction" class="form-label">Google Maps Direction Link</label>
                                        <input type="text" class="form-control" id="google_direction" name="google_direction" 
                                               value="<?php echo htmlspecialchars($business_data['google_direction'] ?? $_POST['google_direction'] ?? ''); ?>">
                                        <small class="text-muted">Example: https://goo.gl/maps/...</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary"><?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Business Information</button>
                                    
                                    <?php if ($is_edit_mode): ?>
                                        <a href="business.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <?php if (!empty($businesses)): ?>
                        <div class="card mt-4">
                            <div class="card-body">
                                <h4 class="header-title mb-3">Your Businesses</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Business Name</th>
                                                <th>Designation</th>
                                                <th>Address</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($businesses as $business): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($business['business_name']); ?></td>
                                                <td><?php echo htmlspecialchars($business['designation']); ?></td>
                                                <td><?php echo htmlspecialchars($business['business_address']); ?></td>
                                                <td>
                                                    <a href="business.php?edit=<?php echo $business['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Basic form validation
        $(document).ready(function() {
            $('form').validate({
                rules: {
                    business_name: "required",
                    business_address: "required"
                },
                messages: {
                    business_name: "Please enter your business name",
                    business_address: "Please enter your business address"
                },
                errorElement: "div",
                errorPlacement: function(error, element) {
                    error.addClass("invalid-feedback");
                    error.insertAfter(element);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                }
            });
        });
    </script>

</body>
</html>