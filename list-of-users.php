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

// Pagination
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

$count_sql = "SELECT COUNT(*) AS total FROM users";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$sql = "SELECT id, name, email, phone, address, role, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $records_per_page);
$stmt->execute();
$result = $stmt->get_result();

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
    <title>List of Users | Admin</title>
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
                                    <h4 class="card-title">List of Users</h4>
                                </div>
                            <div class="card-body">
                                
                                <div class="table-responsive">
                                    <table class="table table-centered table-striped table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Sr. No.</th>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Address</th>
                                                <th>Role</th>
                                                <th>Registration Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($result->num_rows > 0): ?>
                                                <?php $serial = $offset + 1; ?>
                                                <?php while ($user = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= $serial++ ?></td>
                                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="text-muted">-</span>' ?></td>
                                                    <td class="address-cell" title="<?= htmlspecialchars($user['address']) ?>">
                                                        <?= !empty($user['address']) ? htmlspecialchars($user['address']) : '<span class="text-muted">-</span>' ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $roleClass = 'badge-other';
                                                        if ($user['role'] === 'admin') {
                                                            $roleClass = 'badge-admin';
                                                        } elseif ($user['role'] === 'user') {
                                                            $roleClass = 'badge-user';
                                                        }
                                                        ?>
                                                        <span class="role-badge <?= $roleClass ?>">
                                                            <?= htmlspecialchars($user['role']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">No user records found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation example">
    <ul class="pagination justify-content-center mb-0">
        <!-- Previous Button -->
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $page > 1 ? '?page=' . ($page - 1) : 'javascript:void(0);' ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>

        <!-- Page Numbers -->
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <!-- Next Button -->
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= $page < $total_pages ? '?page=' . ($page + 1) : 'javascript:void(0);' ?>" aria-label="Next">
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
            </div>

            <?php include 'footer.php'; ?>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>