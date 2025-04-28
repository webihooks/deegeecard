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
$user_stmt->bind_result($role, $user_name);
$user_stmt->fetch();
$user_stmt->close();

if ($role !== 'admin') {
    header("Location: index.php");
    exit();
}

// Pagination
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Get total number of subscriptions
$count_sql = "SELECT COUNT(*) AS total FROM subscriptions";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch subscriptions with users
$sub_sql = "SELECT s.subscription_id, u.name, u.email, s.start_date, s.end_date, s.status, s.payment_method, s.auto_renewal
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC
            LIMIT ?, ?";
$sub_stmt = $conn->prepare($sub_sql);
$sub_stmt->bind_param("ii", $offset, $records_per_page);
$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>List of Subscribers | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <script src="assets/js/config.js"></script>
</head>
<body>
    <div class="wrapper">
        <?php include 'toolbar.php'; ?>
        <?php include 'admin_menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">List of Subscribers</h4>
                            </div>
                            <div class="card-body">

                                <div class="table-responsive">
                                    <table class="table table-centered table-striped table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Sr. No.</th>
                                                <th>Subscription ID</th>
                                                <th>User Name</th>
                                                <th>Email</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                                <th>Payment Method</th>
                                                <th>Auto Renewal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
    <?php 
    if ($sub_result->num_rows > 0): 
        $sr_no = $offset + 1; // start Sr. No. based on page
        while ($row = $sub_result->fetch_assoc()): 
    ?>
        <tr>
            <td><?php echo $sr_no++; ?></td>
            <td><?php echo htmlspecialchars($row['subscription_id']); ?></td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
            <td><?php echo htmlspecialchars($row['end_date']); ?></td>
            <td>
                <?php if ($row['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                <?php elseif ($row['status'] === 'expired'): ?>
                    <span class="badge bg-danger">Expired</span>
                <?php else: ?>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($row['status']); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
            <td><?php echo $row['auto_renewal'] ? 'Yes' : 'No'; ?></td>
        </tr>
    <?php 
        endwhile; 
    else: 
    ?>
        <tr>
            <td colspan="9" class="text-center">No subscriptions found.</td>
        </tr>
    <?php endif; ?>
</tbody>

                                    </table>
                                </div>

                                <!-- Pagination -->
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>

                            </div> <!-- card-body -->
                        </div> <!-- card -->
                    </div> <!-- col-xl-12 -->
                </div> <!-- row -->
            </div> <!-- container -->

            <?php include 'footer.php'; ?>
        </div> <!-- page-content -->
    </div> <!-- wrapper -->

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
