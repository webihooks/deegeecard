<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_sql = "SELECT role, name FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($role, $logged_in_name);
$user_stmt->fetch();
$user_stmt->close();

if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// QR Code Generation
require 'vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (isset($_POST['submit_qr'])) {
    $text = $_POST['qr_text'] ?? '';
    
    if (!empty($text)) {
        // Create QR code
        $qrCode = new QrCode($text);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        // Check if we should open in new tab
        if (isset($_POST['new_tab'])) {
            // Direct output to browser
            header('Content-Type: '.$result->getMimeType());
            echo $result->getString();
            exit;
        }
    }
}

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>QR Code | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
    <script>
        function generateQRInNewTab(event) {
            event.preventDefault();
            const form = event.target;
            const qrText = form.qr_text.value;
            
            if (qrText) {
                // Create a hidden form for new tab submission
                const hiddenForm = document.createElement('form');
                hiddenForm.method = 'post';
                hiddenForm.action = '';
                hiddenForm.target = '';
                
                const textInput = document.createElement('input');
                textInput.type = 'hidden';
                textInput.name = 'qr_text';
                textInput.value = qrText;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'submit_qr';
                submitInput.value = '1';
                
                const newTabInput = document.createElement('input');
                newTabInput.type = 'hidden';
                newTabInput.name = 'new_tab';
                newTabInput.value = '1';
                
                hiddenForm.appendChild(textInput);
                hiddenForm.appendChild(submitInput);
                hiddenForm.appendChild(newTabInput);
                document.body.appendChild(hiddenForm);
                hiddenForm.submit();
                document.body.removeChild(hiddenForm);
                
                // Also submit normally for inline display
                form.submit();
            }
        }
    </script>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-9">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">QR Code Generator</h4>
                            </div>
                            <div class="card-body">
                                <form method="post" onsubmit="generateQRInNewTab(event)">
                                    <div class="form-group mb-2">
                                        <label class="form-label" for="qr_text">Enter text or URL</label>
                                        <input type="text" class="form-control" id="qr_text" name="qr_text" placeholder="Enter text or URL" required>
                                    </div>
                                    <button type="submit" name="submit_qr" class="btn btn-primary">
                                        <i class="ri-qr-code-line me-1"></i> Generate QR Code
                                    </button>
                                    <a href="qr.php" class="btn btn-secondary">Reset</a>
                                </form>
                                
                                <?php if (isset($_POST['qr_text']) && !empty($_POST['qr_text']) && !isset($_POST['new_tab'])): ?>
                                <div class="mt-4">
                                    <h5 class="mb-2">Generated QR Code</h5>
                                    <div class="d-flex align-items-center">
                                        <div class="me-4">
                                            <img src="data:image/png;base64,<?php 
                                                $qrCode = new QrCode($_POST['qr_text']);
                                                $writer = new PngWriter();
                                                $result = $writer->write($qrCode);
                                                echo base64_encode($result->getString());
                                            ?>" alt="QR Code" class="img-thumbnail" style="max-width: 200px;">
                                        </div>
                                        <div>
                                            <p class="mb-1"><strong>Content:</strong> <?php echo htmlspecialchars($_POST['qr_text']); ?></p>
                                            <a href="data:image/png;base64,<?php 
                                                echo base64_encode($result->getString());
                                            ?>" download="qrcode.png" class="btn btn-sm btn-outline-primary mt-2">
                                                <i class="ri-download-line me-1"></i> Download QR Code
                                            </a>
                                        </div>
                                    </div>
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
</body>
</html>