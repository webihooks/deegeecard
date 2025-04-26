<?php

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user data
$query = "SELECT name, email, phone, address FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if user exists
if ($result->num_rows === 0) {
    die("User not found");
}

$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>


<?php echo htmlspecialchars($user['name']); ?>
<?php echo htmlspecialchars($user['email']); ?>
<?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?>
<?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?>