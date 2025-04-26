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

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name);
$stmt->fetch();
$stmt->close();

// Handle CSV import
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        
        // Check if file is CSV
        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($file_ext != 'csv') {
            $error_message = "Please upload a valid CSV file.";
        } else {
            // Open the file
            if (($handle = fopen($file, "r")) !== FALSE) {
                $conn->begin_transaction();
                
                try {
                    $insert_stmt = $conn->prepare("INSERT INTO products (user_id, product_name, description, price, quantity) VALUES (?, ?, ?, ?, ?)");
                    $insert_stmt->bind_param("issdi", $user_id, $product_name, $description, $price, $quantity);
                    
                    $row = 0;
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        // Skip header row
                        if ($row == 0) {
                            $row++;
                            continue;
                        }
                        
                        // Validate data
                        if (count($data) >= 4) {
                            $product_name = trim($data[0]);
                            $description = trim($data[1]);
                            $price = floatval($data[2]);
                            $quantity = intval($data[3]);
                            
                            // Basic validation
                            if (!empty($product_name) && $price >= 0 && $quantity >= 0) {
                                $insert_stmt->execute();
                            }
                        }
                        $row++;
                    }
                    
                    $conn->commit();
                    $success_message = "Successfully imported " . ($row - 1) . " products!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error importing products: " . $e->getMessage();
                }
                
                fclose($handle);
                $insert_stmt->close();
            } else {
                $error_message = "Could not open the uploaded file.";
            }
        }
    } else {
        $error_message = "Please select a CSV file to upload.";
    }
}

// Handle sample CSV download
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sample_products.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['Product Name', 'Description', 'Price', 'Quantity']);
    
    // Write sample data
    fputcsv($output, ['Sample Product', 'This is a sample product', '19.99', '100']);
    fputcsv($output, ['Another Product', 'Another description', '29.50', '50']);
    fputcsv($output, ['Premium Product', 'High quality item', '99.99', '25']);
    
    fclose($output);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Products Management</title>
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
                            <div class="card-body">
                                <h4 class="header-title mb-4">Import Products from CSV</h4>
                                
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form action="" method="post" enctype="multipart/form-data">
                                    <div class="form-group mb-3">
                                        <label for="csv_file">CSV File</label>
                                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                        <small class="form-text text-muted">
                                            CSV format: product_name, description, price, quantity
                                        </small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="import_csv" class="btn btn-primary">Import Products</button>
                                        <a href="?download_sample=1" class="btn btn-outline-secondary">Download Sample CSV</a>
                                    </div>
                                </form>
                                
                                <div class="mt-4">
                                    <h5>CSV File Format:</h5>
                                    <p>Your CSV file should have the following columns in order:</p>
                                    <ol>
                                        <li>Product Name (required)</li>
                                        <li>Description</li>
                                        <li>Price (numeric, required)</li>
                                        <li>Quantity (integer, required)</li>
                                    </ol>
                                    <p>Example:</p>
                                    <pre>
Product Name,Description,Price,Quantity
"Sample Product","This is a sample product",19.99,100
"Another Product","Another description",29.50,50
                                    </pre>
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
            $('form').validate({
                rules: {
                    csv_file: {
                        required: true,
                        extension: "csv"
                    }
                },
                messages: {
                    csv_file: {
                        required: "Please select a CSV file",
                        extension: "Please upload a valid CSV file"
                    }
                }
            });
        });
    </script>
</body>
</html>