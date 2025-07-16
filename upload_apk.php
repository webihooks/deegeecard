<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch user name
$sql_name = "SELECT name FROM users WHERE id = ?";
$stmt_name = $conn->prepare($sql_name);
if ($stmt_name === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($user_name);
$stmt_name->fetch();
$stmt_name->close();

// Handle APK file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['apk_file'])) {
    // Check if user already has an APK
    $check_sql = "SELECT id, file_path FROM user_apks WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $error = "You can only have one APK file. Please delete your existing APK first.";
    } else {
        $uploadDir = 'downloads/' . $user_id . '/';
        
        // Create user directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = basename($_FILES['apk_file']['name']);
        $filePath = $uploadDir . $fileName;
        $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Validate file is APK
        if ($fileType != 'apk') {
            $error = "Only APK files are allowed.";
        } elseif ($_FILES['apk_file']['size'] > 100 * 1024 * 1024) { // 100MB limit
            $error = "File size must be less than 100MB.";
        } elseif (move_uploaded_file($_FILES['apk_file']['tmp_name'], $filePath)) {
            // Save to database
            $sql = "INSERT INTO user_apks (user_id, file_name, file_path, upload_date) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $fileName, $filePath);
            
            if (!$stmt->execute()) {
                $error = "Error saving file info: " . $conn->error;
                // Remove the uploaded file if DB failed
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            } else {
                $message = "APK uploaded successfully!";
            }
            $stmt->close();
        } else {
            $error = "Error uploading file.";
        }
    }
    $check_stmt->close();
}

// Get user's APK (will return only one or none)
$currentApk = null;
$sql_apk = "SELECT file_name, file_path, upload_date FROM user_apks WHERE user_id = ? LIMIT 1";
$stmt_apk = $conn->prepare($sql_apk);
if ($stmt_apk === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt_apk->bind_param("i", $user_id);
$stmt_apk->execute();
$result = $stmt_apk->get_result();
if ($result->num_rows > 0) {
    $currentApk = $result->fetch_assoc();
}
$stmt_apk->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>APK Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                                <h4 class="card-title">APK Upload</h4>
                                <p class="text-muted mb-0">You can upload only one APK file (max 100MB)</p>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-success"><?php echo $message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <?php if ($currentApk): ?>
                                    <div class="alert alert-info mb-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Current APK:</strong> 
                                                <?php echo htmlspecialchars($currentApk['file_name']); ?>
                                                <small class="text-muted">
                                                    (uploaded <?php echo date('M j, Y H:i', strtotime($currentApk['upload_date'])); ?>)
                                                </small>
                                            </div>
                                            <div>
                                                <a href="<?php echo htmlspecialchars($currentApk['file_path']); ?>" 
                                                   class="btn btn-sm btn-success me-2" download>
                                                    Download
                                                </a>
                                                <a href="delete_apk.php" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete your APK?')">
                                                    Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" action="upload_apk.php" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="apk_file" class="form-label">Select APK File</label>
                                            <input class="form-control" type="file" id="apk_file" name="apk_file" accept=".apk" required>
                                            <div class="form-text">Maximum file size: 100MB. Only .apk files allowed.</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Upload APK</button>
                                    </form>
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
</body>
</html>