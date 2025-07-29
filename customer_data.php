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
$message_type = '';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (customer_name LIKE '%$search%' OR 
                              customer_phone LIKE '%$search%' OR 
                              delivery_address LIKE '%$search%')";
}

// Pagination setup
$limit = 100; // records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Count total unique customers
$count_sql = "SELECT COUNT(*) as total FROM (
    SELECT customer_name, customer_phone, delivery_address
    FROM orders
    WHERE user_id = $user_id $search_condition
    GROUP BY customer_name, customer_phone, delivery_address
) AS grouped_customers";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? (int)$row['total'] : 0;
$total_pages = ceil($total_records / $limit);

// Fetch paginated customer data
$customer_data = [];
$sql = "SELECT customer_name, customer_phone, delivery_address 
        FROM orders 
        WHERE user_id = $user_id $search_condition
        GROUP BY customer_name, customer_phone, delivery_address
        ORDER BY MAX(created_at) DESC
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $customer_data[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Customer Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php if ($role === 'admin') {
            include 'admin_menu.php';
        } else {
            include 'menu.php';
        } ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Customer Data</h4>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="float-end">
                                            <form method="GET" class="d-flex">
                                                <input type="text" name="search" class="form-control me-2"
                                                       placeholder="Search by name, phone, or address"
                                                       value="<?php echo htmlspecialchars($search); ?>">
                                                <button type="submit" class="btn btn-primary">Search</button>
                                                <?php if (!empty($search)): ?>
                                                    <a href="customer_data.php" class="btn btn-secondary ms-2">Clear</a>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo $message; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($customer_data)): ?>
                                    <div class="alert alert-info">
                                        No customer data found.
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Sr. No.</th>
                                                            <th>Customer Name</th>
                                                            <th>Phone Number</th>
                                                            <th>Delivery Address</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $sr_no = $offset + 1;
                                                        foreach ($customer_data as $customer): ?>
                                                            <tr>
                                                                <td><?php echo $sr_no++; ?></td>
                                                                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($customer['customer_phone']); ?></td>
                                                                <td>
                                                                    <?php 
                                                                    echo empty(trim($customer['delivery_address'])) 
                                                                        ? 'Table Ordered' 
                                                                        : htmlspecialchars($customer['delivery_address']);
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Pagination -->
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination justify-content-center mt-1">
                                                    <?php if ($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                                Previous
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <?php if ($page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                                Next
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
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
    <script>
        // Auto-submit on Enter
        $(document).ready(function () {
            $('input[name="search"]').keypress(function (e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                    return false;
                }
            });
        });
    </script>
</body>
</html>
