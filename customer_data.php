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

// Set header to UTF-8 to handle special characters
header('Content-Type: text/html; charset=utf-8');

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

// Set connection charset to UTF-8
$conn->set_charset("utf8mb4");

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
$limit = 5000; // records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Count total unique customers from both tables
$count_sql = "SELECT (SELECT COUNT(*) FROM (
                SELECT customer_name, customer_phone, delivery_address
                FROM orders
                WHERE user_id = $user_id $search_condition
                GROUP BY customer_name, customer_phone, delivery_address
              ) AS orders_customers) + 
              (SELECT COUNT(*) FROM customer_data 
               WHERE user_id = $user_id $search_condition) AS total";
$count_result = $conn->query($count_sql);
$total_records = ($count_result && $row = $count_result->fetch_assoc()) ? (int)$row['total'] : 0;
$total_pages = ceil($total_records / $limit);

/**
 * Clean and validate customer names
 */
function cleanCustomerName($name) {
    // Trim whitespace
    $name = trim($name);
    
    // Remove any non-printable characters
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    
    // Replace common problematic patterns
    $name = str_replace(['#ERROR!', '""', "''", '***', '---', '...', ',,,'], '', $name);
    
    // If name is empty after cleaning, set to "Unknown"
    if (empty($name) || ctype_punct($name)) {
        return 'Unknown';
    }
    
    return $name;
}

// Fetch paginated customer data from both tables with UNION
$customer_data = [];
$sql = "(SELECT customer_name, customer_phone, delivery_address, 'order' as source, MAX(created_at) as updated_at
        FROM orders 
        WHERE user_id = $user_id $search_condition
        GROUP BY customer_name, customer_phone, delivery_address)
        
        UNION
        
        (SELECT customer_name, customer_phone, delivery_address, 'customer_data' as source, updated_at
         FROM customer_data 
         WHERE user_id = $user_id $search_condition)
         
        ORDER BY updated_at DESC
        LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Clean and validate customer names
        $row['customer_name'] = cleanCustomerName($row['customer_name']);
        $customer_data[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Customer Data</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
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
                                <div class="float-end">
                                    <a href="import_customer_data.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-upload me-1"></i> Import Data
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <div class="float-end">
                                            <form method="GET" class="d-flex">
                                                <input type="text" name="search" class="form-control me-2"
                                                       placeholder="Search by name, phone, or address"
                                                       value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
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
                                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
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
                    <th>
                        Phone Number
                        <br>
                        <div class="btn-group mt-1">
                            <button class="btn btn-sm btn-outline-primary copy-page-phones" 
                                    title="Copy all phone numbers from this page">
                                <i class="fas fa-copy me-1"></i> Copy Page wise
                            </button>
                            <button class="btn btn-sm btn-outline-secondary copy-all-phones" 
                                    title="Copy all phone numbers from all pages">
                                <i class="fas fa-copy me-1"></i> Copy All Numbers
                            </button>
                        </div>
                    </th>
                    <th>Delivery Address</th>
                    <th>Source</th>
                    <th style="display: none;">Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sr_no = $offset + 1;
                foreach ($customer_data as $customer): ?>
                    <tr>
                        <td><?php echo $sr_no++; ?></td>
                        <td><?php echo htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="phone-number"><?php echo htmlspecialchars($customer['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php 
                            $address = trim($customer['delivery_address']);
                            echo empty($address) || strtoupper($address) === 'NA' 
                                ? 'N/A' 
                                : htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $customer['source'] === 'order' ? 'primary' : 'success'; ?>">
                                <?php echo ucfirst($customer['source']); ?>
                            </span>
                        </td>
                        <td style="display: none;">
                            <?php 
                            if (isset($customer['updated_at'])) {
                                echo date('d M Y H:i', strtotime($customer['updated_at']));
                            } else {
                                echo 'N/A';
                            }
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
            
            // Copy page phone numbers
            $('.copy-page-phones').click(function() {
                let phoneNumbers = [];
                $('table tbody tr').each(function() {
                    const phone = $(this).find('td:eq(2)').text().trim();
                    if (phone && phone !== 'N/A') {
                        phoneNumbers.push(phone);
                    }
                });
                
                if (phoneNumbers.length > 0) {
                    const textToCopy = phoneNumbers.join('\n');
                    copyToClipboard(textToCopy);
                    alert('Copied ' + phoneNumbers.length + ' phone numbers from this page!');
                } else {
                    alert('No phone numbers found on this page.');
                }
            });
            
            // Copy all phone numbers (requires AJAX to fetch all records)
            $('.copy-all-phones').click(function() {
                // Show loading indicator
                const originalText = $(this).html();
                $(this).html('<i class="fas fa-spinner fa-spin"></i> Loading...');
                $(this).prop('disabled', true);
                
                // Fetch all phone numbers via AJAX
                $.ajax({
                    url: 'get_all_phones.php',
                    type: 'GET',
                    data: {
                        search: '<?php echo isset($search) ? addslashes($search) : ''; ?>'
                    },
                    success: function(response) {
                        $('.copy-all-phones').html(originalText);
                        $('.copy-all-phones').prop('disabled', false);
                        
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (data.success && data.phones && data.phones.length > 0) {
                                const textToCopy = data.phones.join('\n');
                                copyToClipboard(textToCopy);
                                alert('Copied ' + data.phones.length + ' phone numbers from all pages!');
                            } else {
                                alert('No phone numbers found. ' + (data.message || ''));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', response);
                            alert('Error processing response. Please check console for details.');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.copy-all-phones').html(originalText);
                        $('.copy-all-phones').prop('disabled', false);
                        console.error('AJAX Error:', status, error);
                        alert('Error fetching phone numbers. Please try again. Error: ' + error);
                    }
                });
            });
            
            // Helper function to copy text to clipboard
            function copyToClipboard(text) {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            
        });
    </script>
</body>
</html>