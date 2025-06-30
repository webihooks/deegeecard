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

// Fetch current table count
$stmt = $conn->prepare("SELECT id, table_count FROM dining_tables WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($record_id, $table_count);
$record_exists = $stmt->fetch();
$stmt->close();

// Initialize dining and delivery settings
$dining_active = 0;
$delivery_active = 0;

// Check if dining_and_delivery table exists
$table_exists = false;
$result = $conn->query("SHOW TABLES LIKE 'dining_and_delivery'");
if ($result->num_rows > 0) {
    $table_exists = true;
}

// Fetch dining and delivery settings if table exists
if ($table_exists) {
    $stmt = $conn->prepare("SELECT dining_active, delivery_active FROM dining_and_delivery WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($dining_active, $delivery_active);
        $dining_delivery_exists = $stmt->fetch();
        $stmt->close();
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_table_count = filter_var($_POST['table_count'], FILTER_SANITIZE_NUMBER_INT);
    $dining_active = isset($_POST['dining_active']) ? 1 : 0;
    $delivery_active = isset($_POST['delivery_active']) ? 1 : 0;

    if ($new_table_count < 0 || $new_table_count > 50) {
        $message = "Table count must be between 0 and 50.";
        $message_type = 'danger';
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Handle dining tables
            if ($record_exists) {
                $update = $conn->prepare("UPDATE dining_tables SET table_count = ?, updated_at = NOW() WHERE user_id = ?");
                $update->bind_param("ii", $new_table_count, $user_id);
                $update->execute();
                $update->close();
            } else {
                $insert = $conn->prepare("INSERT INTO dining_tables (user_id, table_count, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $insert->bind_param("ii", $user_id, $new_table_count);
                $insert->execute();
                $insert->close();
            }
            
            // Create dining_and_delivery table if it doesn't exist
            if (!$table_exists) {
                $create_table = "CREATE TABLE IF NOT EXISTS dining_and_delivery (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    dining_active TINYINT(1) NOT NULL DEFAULT 0,
                    delivery_active TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY (user_id)
                )";
                $conn->query($create_table);
                $table_exists = true;
            }
            
            // Handle dining and delivery settings
            if ($table_exists) {
                // Check if record exists
                $check = $conn->prepare("SELECT id FROM dining_and_delivery WHERE user_id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $record_exists_dd = $check->fetch();
                $check->close();
                
                if ($record_exists_dd) {
                    $update = $conn->prepare("UPDATE dining_and_delivery SET dining_active = ?, delivery_active = ?, updated_at = NOW() WHERE user_id = ?");
                    $update->bind_param("iii", $dining_active, $delivery_active, $user_id);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $conn->prepare("INSERT INTO dining_and_delivery (user_id, dining_active, delivery_active, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                    $insert->bind_param("iii", $user_id, $dining_active, $delivery_active);
                    $insert->execute();
                    $insert->close();
                }
            }
            
            $conn->commit();
            $message = "Settings saved successfully.";
            $message_type = 'success';
            $table_count = $new_table_count;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error saving settings: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Dining Table</title>
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
        <?php
        if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php'; // default menu for other roles
        }
        ?>
        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Dining Table & Service Options</h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" id="diningTableForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="table_count" class="form-label">Number of Tables</label>
                                            <input type="number" name="table_count" id="table_count" class="form-control"
                                                value="<?php echo isset($table_count) ? htmlspecialchars($table_count) : ''; ?>" min="0" max="50" required />
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Service Options</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="dining_active" id="dining_active" 
                                                    <?php echo isset($dining_active) && $dining_active ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="dining_active">
                                                    Dining Service
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="delivery_active" id="delivery_active"
                                                    <?php echo isset($delivery_active) && $delivery_active ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="delivery_active">
                                                    Delivery Service
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="submit" class="btn btn-primary">Save</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $("#diningTableForm").validate({
                rules: {
                    table_count: {
                        required: true,
                        number: true,
                        min: 0,
                        max: 50
                    }
                },
                messages: {
                    table_count: {
                        required: "Please enter number of tables.",
                        number: "Please enter a valid number.",
                        min: "Minimum 0 tables required.",
                        max: "Maximum 50 tables allowed."
                    }
                }
            });
        });
    </script>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>