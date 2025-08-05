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
$tag_data = null;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

// Handle form submission for add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag_id = isset($_POST['tag_id']) ? $_POST['tag_id'] : null;
    $tag = trim($_POST['tag']);

    // Validate inputs
    if (empty($tag)) {
        $error_message = "Tag is required.";
    } else {
        if ($tag_id) {
            // Update existing tag
            $sql = "UPDATE tags SET tag = ? WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $tag, $tag_id, $user_id);
        } else {
            // Add new tag
            $sql = "INSERT INTO tags (user_id, tag) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $tag);
        }

        if ($stmt->execute()) {
            $success_message = $tag_id ? "Tag updated successfully!" : "Tag added successfully!";
        } else {
            $error_message = "Error saving tag: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $tag_id = $_GET['edit'];
    $sql = "SELECT * FROM tags WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tag_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tag_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($tag_data) {
        $is_edit_mode = true;
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $tag_id = $_GET['delete'];
    
    // Delete the tag
    $sql = "DELETE FROM tags WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tag_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Tag deleted successfully!";
    } else {
        $error_message = "Error deleting tag: " . $conn->error;
    }
    $stmt->close();
}

// Fetch all tags for the current user
$sql = "SELECT * FROM tags WHERE user_id = ? ORDER BY id ASC";  // Changed to ORDER BY id ASC
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Tags Management</title>
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
                                <h4 class="card-title">Tags</h4>
                            </div>
                            <div class="card-body">
                                <h4 class="card-title"><?php echo $is_edit_mode ? 'Edit Tag' : 'Add New Tag'; ?></h4>
                                <form id="tagForm" method="POST" action="tags.php">
                                    <input type="hidden" name="tag_id" value="<?php echo $is_edit_mode ? $tag_data['id'] : ''; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="tag" class="form-label">Tag *</label>
                                        <input type="text" class="form-control" id="tag" name="tag" required 
                                            value="<?php echo $is_edit_mode ? htmlspecialchars($tag_data['tag']) : ''; ?>">
                                        <small class="text-muted">Enter a descriptive tag to categorize your content</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $is_edit_mode ? 'Update' : 'Save'; ?> Tag
                                    </button>
                                    <?php if ($is_edit_mode): ?>
                                        <a href="tags.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h4 class="card-title">Your Tags</h4>
                            </div>

<div class="card-body">
    <?php if (empty($tags)): ?>
        <p>No tags found. Add your first tag above.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tag</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?php echo $tag['id']; ?></td>
                            <td><?php echo htmlspecialchars($tag['tag']); ?></td>
                            <td>
                                <a href="tags.php?edit=<?php echo $tag['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="tags.php?delete=<?php echo $tag['id']; ?>" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this tag?')">Delete</a>
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
            // Form validation
            $("#tagForm").validate({
                rules: {
                    tag: {
                        required: true,
                        minlength: 2,
                        maxlength: 50
                    }
                },
                messages: {
                    tag: {
                        required: "Please enter a tag",
                        minlength: "Tag must be at least 2 characters long",
                        maxlength: "Tag cannot be longer than 50 characters"
                    }
                }
            });
        });
    </script>
</body>
</html>