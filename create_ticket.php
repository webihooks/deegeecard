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

// Fetch user details
$sql = "SELECT name, email FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $user_email);
$stmt->fetch();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $department = trim($_POST['department']);
    $priority = trim($_POST['priority']);
    $message = trim($_POST['message']);
    
    // Validate inputs
    if (empty($subject) || empty($department) || empty($priority) || empty($message)) {
        $error_message = "All fields are required.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert ticket into database
            $sql = "INSERT INTO tickets (user_id, subject, department, priority, message) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $user_id, $subject, $department, $priority, $message);
            $stmt->execute();
            $ticket_id = $conn->insert_id;
            $stmt->close();
            
            // Handle file uploads if any
            if (!empty($_FILES['attachments']['name'][0])) {
                $uploadDir = 'uploads/tickets/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                    'application/vnd.ms-excel' => 'xls',
                    'text/csv' => 'csv',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
                ];
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    $fileName = $_FILES['attachments']['name'][$key];
                    $fileTmp = $_FILES['attachments']['tmp_name'][$key];
                    $fileType = $_FILES['attachments']['type'][$key];
                    $fileSize = $_FILES['attachments']['size'][$key];
                    $fileError = $_FILES['attachments']['error'][$key];
                    
                    // Check for upload errors
                    if ($fileError !== UPLOAD_ERR_OK) {
                        throw new Exception("Error uploading file: $fileName - " . $this->getUploadError($fileError));
                    }
                    
                    // Validate file type
                    if (!array_key_exists($fileType, $allowedTypes)) {
                        throw new Exception("Invalid file type: $fileName. Only JPG, JPEG, PNG, PDF, XLS, CSV are allowed.");
                    }
                    
                    // Validate file size (max 5MB)
                    if ($fileSize > 5242880) {
                        throw new Exception("File too large: $fileName. Maximum size is 5MB.");
                    }
                    
                    // Generate unique filename with original extension
                    $fileExt = $allowedTypes[$fileType];
                    $newFileName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.]/', '', $fileName);
                    $uploadPath = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmp, $uploadPath)) {
                        $sql = "INSERT INTO ticket_attachments (ticket_id, file_name, file_path, file_type, file_size) 
                                VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("isssi", $ticket_id, $fileName, $uploadPath, $fileType, $fileSize);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        throw new Exception("Failed to upload file: $fileName");
                    }
                }
            }
            
            $conn->commit();
            $success_message = "Ticket #$ticket_id created successfully!";
            // Clear form fields
            $subject = $department = $priority = $message = '';
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

function getUploadError($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    return $errors[$errorCode] ?? 'Unknown upload error';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Create Support Ticket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.materialdesignicons.com/5.4.55/css/materialdesignicons.min.css" rel="stylesheet">
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
                                <h4 class="card-title">Create Support Ticket</h4>
                            </div>

                            <div class="card-body">
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                                <?php endif; ?>
                                
                                <form id="ticketForm" method="POST" action="" enctype="multipart/form-data">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label">Your Name</label>
                                            <input type="text" class="form-control" id="name" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Your Email</label>
                                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="department" class="form-label">Department</label>
                                            <select class="form-select" id="department" name="department" required>
                                                <option value="">Select Department</option>
                                                <option value="Technical" <?php echo (isset($department) && $department == 'Technical') ? 'selected' : ''; ?>>Technical</option>
                                                <option value="Billing" <?php echo (isset($department) && $department == 'Billing') ? 'selected' : ''; ?>>Billing</option>
                                                <option value="Sales" <?php echo (isset($department) && $department == 'Sales') ? 'selected' : ''; ?>>Sales</option>
                                                <option value="General" <?php echo (isset($department) && $department == 'General') ? 'selected' : ''; ?>>General</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="priority" class="form-label">Priority</label>
                                            <select class="form-select" id="priority" name="priority" required>
                                                <option value="">Select Priority</option>
                                                <option value="Low" <?php echo (isset($priority) && $priority == 'Low') ? 'selected' : ''; ?>>Low</option>
                                                <option value="Medium" <?php echo (isset($priority) && $priority == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                                <option value="High" <?php echo (isset($priority) && $priority == 'High') ? 'selected' : ''; ?>>High</option>
                                                <option value="Urgent" <?php echo (isset($priority) && $priority == 'Urgent') ? 'selected' : ''; ?>>Urgent</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="attachments" class="form-label">Attachments</label>
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                                               accept=".jpg,.jpeg,.png,.pdf,.xls,.csv,.xlsx">
                                        <div class="form-text">Maximum 5MB per file. Allowed types: JPG, JPEG, PNG, PDF, XLS, CSV</div>
                                        <div id="filePreview" class="mt-2"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary">Submit Ticket</button>
                                        <button type="reset" class="btn btn-secondary ms-2" id="resetButton">Reset</button>
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

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        $(document).ready(function() {
            // Store the selected files
            let selectedFiles = [];
            
            // File preview handler
            $('#attachments').change(function() {
                const files = Array.from(this.files);
                selectedFiles = [...selectedFiles, ...files];
                updateFilePreview();
            });
            
            // Function to update file preview
            function updateFilePreview() {
                $('#filePreview').empty();
                
                if (selectedFiles.length > 0) {
                    const list = $('<ul class="list-group"></ul>');
                    
                    selectedFiles.forEach((file, index) => {
                        const item = $(`
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <span class="file-icon me-2"></span>
                                    <span class="file-name">${file.name}</span>
                                    <span class="file-size ms-2 text-muted">(${formatFileSize(file.size)})</span>
                                </div>
                                <div>
                                    <span class="badge bg-primary rounded-pill me-2">${getFileType(file.name)}</span>
                                    <button type="button" class="btn btn-sm btn-danger remove-file" data-index="${index}">
                                        <i class="mdi mdi-close"></i>
                                    </button>
                                </div>
                            </li>
                        `);
                        
                        // Set appropriate icon based on file type
                        const icon = item.find('.file-icon');
                        if (file.type.includes('image')) {
                            icon.html('<i class="mdi mdi-image"></i>');
                        } else if (file.type.includes('pdf')) {
                            icon.html('<i class="mdi mdi-file-pdf"></i>');
                        } else if (file.type.includes('excel') || file.type.includes('spreadsheet')) {
                            icon.html('<i class="mdi mdi-file-excel"></i>');
                        } else if (file.type.includes('csv')) {
                            icon.html('<i class="mdi mdi-file-delimited"></i>');
                        } else {
                            icon.html('<i class="mdi mdi-file"></i>');
                        }
                        
                        list.append(item);
                    });
                    
                    $('#filePreview').append(list);
                    
                    // Update the file input with remaining files
                    updateFileInput();
                } else {
                    // Clear the file input completely if no files left
                    $('#attachments').val('');
                }
            }
            
            // Function to remove a file
            $(document).on('click', '.remove-file', function() {
                const index = $(this).data('index');
                selectedFiles.splice(index, 1);
                updateFilePreview();
            });
            
            // Function to update the file input with remaining files
            function updateFileInput() {
                const dataTransfer = new DataTransfer();
                selectedFiles.forEach(file => {
                    dataTransfer.items.add(file);
                });
                $('#attachments')[0].files = dataTransfer.files;
            }
            
            // Reset handler to clear selected files
            $('#resetButton').click(function() {
                selectedFiles = [];
                $('#filePreview').empty();
                $('#attachments').val(''); // Clear the file input path
            });
            
            // Form validation
            $("#ticketForm").validate({
                rules: {
                    subject: "required",
                    department: "required",
                    priority: "required",
                    message: "required",
                    "attachments[]": {
                        accept: "image/jpeg,image/jpg,image/png,application/pdf,application/vnd.ms-excel,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                        filesize: 5242880 // 5MB in bytes
                    }
                },
                messages: {
                    subject: "Please enter a subject",
                    department: "Please select a department",
                    priority: "Please select a priority level",
                    message: "Please enter your message",
                    "attachments[]": {
                        accept: "Only JPG, JPEG, PNG, PDF, XLS, CSV files are allowed",
                        filesize: "File size must be less than 5MB"
                    }
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
                    $(element).removeClass("is-invalid").addClass("is-valid");
                }
            });
            
            // Custom method for file size validation
            $.validator.addMethod('filesize', function(value, element, param) {
                if (element.files.length === 0) return true;
                
                for (var i = 0; i < element.files.length; i++) {
                    if (element.files[i].size > param) {
                        return false;
                    }
                }
                return true;
            });
            
            // Helper functions
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            function getFileType(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                const types = {
                    'jpg': 'JPG',
                    'jpeg': 'JPEG',
                    'png': 'PNG',
                    'pdf': 'PDF',
                    'xls': 'XLS',
                    'csv': 'CSV',
                    'xlsx': 'XLSX'
                };
                return types[ext] || ext.toUpperCase();
            }
        });
    </script>
</body>
</html>