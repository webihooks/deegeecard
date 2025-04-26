<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name);
$stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_qr'])) {
        // Add new QR code
        $mobile_number = trim($_POST['mobile_number']);
        $payment_type = trim($_POST['payment_type']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if (empty($mobile_number)) {
            $error_message = "Mobile number is required";
        } elseif (empty($payment_type)) {
            $error_message = "Payment type is required";
        } else {
            // Handle file upload
            if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/qrcodes/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = uniqid() . '_' . basename($_FILES['qr_code']['name']);
                $target_file = $upload_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                
                // Validate image
                $check = getimagesize($_FILES['qr_code']['tmp_name']);
                if ($check === false) {
                    $error_message = "File is not an image.";
                } elseif ($_FILES['qr_code']['size'] > 2000000) {
                    $error_message = "Sorry, your file is too large. Max 2MB allowed.";
                } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
                    $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                } elseif (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target_file)) {
                    // If this is being set as default, unset any existing defaults
                    if ($is_default) {
                        $conn->query("UPDATE qrcode_details SET is_default = FALSE WHERE user_id = $user_id");
                    }
                    
                    // Insert new record
                    $sql = "INSERT INTO qrcode_details (user_id, mobile_number, upload_qr_code, payment_type, is_default) 
                            VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isssi", $user_id, $mobile_number, $file_name, $payment_type, $is_default);
                    
                    if ($stmt->execute()) {
                        $success_message = "QR Code added successfully!";
                    } else {
                        $error_message = "Error saving QR Code: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error_message = "Please select a QR code image to upload.";
            }
        }
    } elseif (isset($_POST['set_default'])) {
        // Set default QR code
        $qr_id = intval($_POST['qr_id']);
        $conn->begin_transaction();
        
        try {
            // First unset any existing defaults
            $conn->query("UPDATE qrcode_details SET is_default = FALSE WHERE user_id = $user_id");
            
            // Set the new default
            $stmt = $conn->prepare("UPDATE qrcode_details SET is_default = TRUE WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $qr_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $success_message = "Default QR code updated successfully!";
            } else {
                $error_message = "Failed to update default QR code.";
            }
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating default QR code: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_qr'])) {
        // Delete QR code
        $qr_id = intval($_POST['qr_id']);
        
        // First get the filename to delete the physical file
        $stmt = $conn->prepare("SELECT upload_qr_code FROM qrcode_details WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $qr_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($filename);
        $stmt->fetch();
        $stmt->close();
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM qrcode_details WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $qr_id, $user_id);
        
        if ($stmt->execute()) {
            // Delete the physical file
            if ($filename && file_exists("uploads/qrcodes/$filename")) {
                unlink("uploads/qrcodes/$filename");
            }
            $success_message = "QR code deleted successfully!";
        } else {
            $error_message = "Error deleting QR code: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all QR codes for this user
$qr_codes = [];
$stmt = $conn->prepare("SELECT id, mobile_number, upload_qr_code, payment_type, is_default FROM qrcode_details WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $qr_codes[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>QR Code Details</title>
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
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">QR Code</h4>
                            </div>
                            <div class="card-body">
                                
                                <form id="qrCodeForm" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="mobile_number" class="form-label">Mobile Number*</label>
                                            <input type="text" class="form-control" id="mobile_number" name="mobile_number" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="payment_type" class="form-label">Payment Type*</label>
                                            <select class="form-control" id="payment_type" name="payment_type" required>
                                                <option value="">Select Payment Type</option>
                                                <option value="UPI">UPI</option>
                                                <option value="Paytm">Paytm</option>
                                                <option value="PhonePe">PhonePe</option>
                                                <option value="Google Pay">Google Pay</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="qr_code" class="form-label">QR Code Image*</label>
                                        <input type="file" class="form-control" id="qr_code" name="qr_code" accept="image/*" required>
                                        <small class="text-muted">Upload a clear image (JPG, PNG, max 2MB)</small>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_default" name="is_default">
                                        <label class="form-check-label" for="is_default">Set as default payment method</label>
                                    </div>
                                    
                                    <button type="submit" name="add_qr" class="btn btn-primary">Add QR Code</button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">Your QR Code</h4>
                            </div>
                            <div class="card-body">
                                
                                <?php if (empty($qr_codes)): ?>
                                    <div class="alert alert-info">No QR codes added yet.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>QR Code</th>
                                                    <th>Mobile Number</th>
                                                    <th>Payment Type</th>
                                                    <th>Default</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($qr_codes as $qr): ?>
                                                    <tr>
                                                        <td>
                                                            <img src="uploads/qrcodes/<?php echo htmlspecialchars($qr['upload_qr_code']); ?>" 
                                                                 alt="QR Code" 
                                                                 style="max-width: 80px; max-height: 80px;"
                                                                 class="img-thumbnail">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($qr['mobile_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($qr['payment_type']); ?></td>
                                                        <td>
                                                            <?php if ($qr['is_default']): ?>
                                                                <span class="badge bg-success">Default</span>
                                                            <?php else: ?>
                                                                <form method="post" style="display:inline;">
                                                                    <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                                    <button type="submit" name="set_default" class="btn btn-sm btn-outline-primary">
                                                                        Set Default
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this QR code?');" style="display:inline;">
                                                                <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                                <button type="submit" name="delete_qr" class="btn btn-sm btn-danger">
                                                                    Delete
                                                                </button>
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

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            $("#qrCodeForm").validate({
                rules: {
                    mobile_number: {
                        required: true,
                        minlength: 10,
                        maxlength: 15
                    },
                    payment_type: {
                        required: true
                    },
                    qr_code: {
                        required: true
                    }
                },
                messages: {
                    mobile_number: {
                        required: "Please enter mobile number",
                        minlength: "Mobile number must be at least 10 digits",
                        maxlength: "Mobile number cannot exceed 15 digits"
                    },
                    payment_type: {
                        required: "Please select payment type"
                    },
                    qr_code: {
                        required: "Please select a QR code image"
                    }
                }
            });
        });
    </script>
</body>
</html>