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

// First, update any expired subscriptions
$current_date = date('Y-m-d');
$update_sql = "UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND end_date < ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("s", $current_date);
$update_stmt->execute();
$update_stmt->close();

// Get filter parameters
$name_filter = isset($_GET['name']) ? trim($_GET['name']) : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active'; // Default to active

// Pagination
$records_per_page = 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Base SQL query - Only show users with role 'user'
$base_sql = "FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN profile_url_details p ON u.id = p.user_id
            WHERE u.role = 'user'"; // Changed to filter by role

// Add filters to query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($name_filter)) {
    $where_conditions[] = "u.name LIKE ?";
    $params[] = '%' . $name_filter . '%';
    $types .= 's';
}

if (!empty($month_filter)) {
    $where_conditions[] = "DATE_FORMAT(s.start_date, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= 's';
}

// Status filter
if ($status_filter === 'active') {
    $where_conditions[] = "s.status = 'active'";
} elseif ($status_filter === 'expired') {
    $where_conditions[] = "s.status = 'expired'";
} elseif ($status_filter === 'all') {
    // No additional condition needed, show all statuses
}

if (!empty($where_conditions)) {
    $base_sql .= " AND " . implode(" AND ", $where_conditions);
}

// Get total number of subscriptions with filters
$count_sql = "SELECT COUNT(*) AS total " . $base_sql;
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$count_stmt->close();

// Fetch subscriptions with users and profile_url
$sub_sql = "SELECT s.subscription_id, u.id as user_id, u.name, u.email, 
                   DATE(s.start_date) as start_date, 
                   DATE(s.end_date) as end_date, 
                   s.status, s.subscription_type as package_name,
                   p.profile_url " . $base_sql . " 
            ORDER BY s.created_at DESC
            LIMIT ?, ?";
$sub_stmt = $conn->prepare($sub_sql);

// Add pagination parameters
$params[] = $offset;
$params[] = $records_per_page;
$types .= 'ii';

if (!empty($types)) {
    $sub_stmt->bind_param($types, ...$params);
}

$sub_stmt->execute();
$sub_result = $sub_stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Subscribers Management | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
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
                                <h4 class="card-title">Subscribers Management</h4>
                                <p class="text-muted mb-0">Showing <?php echo $records_per_page; ?> records per page</p>
                            </div>
                            <div class="card-body">
                                <!-- Filter Form -->
                                <form method="get" action="" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="name">Filter by Name:</label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo htmlspecialchars($name_filter); ?>" 
                                                       placeholder="Enter user name">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="month">Filter by Month:</label>
                                                <input type="month" class="form-control" id="month" name="month" 
                                                       value="<?php echo htmlspecialchars($month_filter); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="status">Filter by Status:</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                                <a href="?" class="btn btn-secondary ml-2">Reset</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-striped table-hover">

<thead class="thead-light">
    <tr>
        <th>Sr. No.</th>
        <th>User ID</th>
        <th>User Name</th>
        <th>Package</th>  <!-- Add this column -->
        <th>Start Date</th>
        <th>End Date</th>
        <th>Status</th>
    </tr>
</thead>
<tbody>
    <?php 
    if ($sub_result->num_rows > 0): 
        $sr_no = $offset + 1;
        while ($row = $sub_result->fetch_assoc()): 
            $is_expired = $row['status'] === 'expired';
            $status_class = $is_expired ? 'bg-warning' : 'bg-success';
            $status_text = $is_expired ? 'Expired' : 'Active';
    ?>
        <tr>
            <td><?php echo $sr_no++; ?></td>
            <td><?php echo htmlspecialchars($row['user_id']); ?></td>
            <td>
                <?php if (!empty($row['profile_url'])): ?>
                    <a href="<?php echo htmlspecialchars($row['profile_url']); ?>" target="_blank"><?php echo htmlspecialchars($row['name']); ?></a>
                <?php else: ?>
                    <?php echo htmlspecialchars($row['name']); ?>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['package_name'] ?? 'N/A'); ?></td>  <!-- Add this cell -->
            <td><?php echo htmlspecialchars($row['start_date']); ?></td>
            <td><?php echo htmlspecialchars($row['end_date']); ?></td>
            <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
        </tr>
    <?php 
        endwhile; 
    else: 
    ?>
        <tr>
            <td colspan="7" class="text-center">No subscriptions found matching your criteria.</td>  <!-- Update colspan to 7 -->
        </tr>
    <?php endif; ?>
</tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" 
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>" 
                                               aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" 
                                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" 
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>" 
                                               aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize month picker
        flatpickr("#month", {
            dateFormat: "Y-m",
            defaultDate: "<?php echo htmlspecialchars($month_filter); ?>"
        });
    </script>
</body>
</html>