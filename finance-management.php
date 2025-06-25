<?php
// Start the session
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Get user role
$role_sql = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_sql);
if ($role_stmt) {
    $role_stmt->bind_param("i", $user_id);
    $role_stmt->execute();
    $role_stmt->bind_result($role);
    $role_stmt->fetch();
    $role_stmt->close();
} else {
    $error_message = "Error preparing role query: " . $conn->error;
    $role = ''; // Default or handle error
}

// Fetch categories for dropdown
$categories = [];
$category_sql = "SELECT id, name FROM dg_categories ORDER BY name";
$category_result = $conn->query($category_sql);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
} else {
    $error_message = "Error fetching categories: " . $conn->error;
}

// Handle finance form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_transaction'])) {
        $amount = floatval($_POST['amount']);
        $category_id = intval($_POST['category_id']);
        $description = trim($_POST['description']);
        $type = $_POST['transaction_type'];
        $date = $_POST['date'];

        // Basic validation
        if ($amount <= 0) {
            $error_message = "Amount must be greater than zero";
        } elseif (!array_key_exists($category_id, $categories)) {
            $error_message = "Invalid category selected";
        } else {
            // Insert transaction
            $insert_sql = "INSERT INTO dg_transactions
                            (user_id, amount, category_id, description, type, date, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);

            if ($stmt) {
                $stmt->bind_param("idssss", $user_id, $amount, $category_id, $description, $type, $date);

                if ($stmt->execute()) {
                    $success_message = "Transaction added successfully!";
                    // Clear form values
                    $_POST = array();
                } else {
                    $error_message = "Error adding transaction: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error preparing insert query: " . $conn->error;
            }
        }
    }
}

// Fetch transactions with category names and user info
$transactions = [];
$transaction_sql = "SELECT t.id, t.amount, c.name AS category,
                   t.description, t.type, t.date, t.user_id, u.name AS user_name
                   FROM dg_transactions t
                   LEFT JOIN dg_categories c ON t.category_id = c.id
                   LEFT JOIN users u ON t.user_id = u.id";

// Add WHERE clause only for non-admin users
if ($role !== 'admin') {
    $transaction_sql .= " WHERE t.user_id = ?";
}

$transaction_sql .= " ORDER BY t.date DESC, t.created_at DESC";

$transaction_stmt = $conn->prepare($transaction_sql);
if ($transaction_stmt) {
    if ($role !== 'admin') {
        $transaction_stmt->bind_param("i", $user_id);
    }
    $transaction_stmt->execute();
    $result = $transaction_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $transaction_stmt->close();
} else {
    $error_message = "Error preparing transaction history query: " . $conn->error;
}

// Calculate totals
$income_total = 0;
$expense_total = 0;

if ($role === 'admin') {
    // For admin, calculate totals for all transactions
    $totals_sql = "SELECT 
                  SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                  SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                  FROM dg_transactions";
    $totals_result = $conn->query($totals_sql);
    if ($totals_row = $totals_result->fetch_assoc()) {
        $income_total = $totals_row['income'] ?? 0;
        $expense_total = $totals_row['expense'] ?? 0;
    }
} else {
    // For regular users, calculate only their transactions
    foreach ($transactions as $transaction) {
        if ($transaction['type'] === 'income') {
            $income_total += $transaction['amount'];
        } else {
            $expense_total += $transaction['amount'];
        }
    }
}

$balance = $income_total - $expense_total;

// Fetch user name
$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_name);
    $stmt->fetch();
    $stmt->close();
} else {
    $error_message = "Error preparing user name query: " . $conn->error;
    $user_name = 'User';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8" />
      <title>Finance Management</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link href="assets/css/vendor.min.css" rel="stylesheet" type="text/css" />
      <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
      <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
      <link href="assets/css/style.css" rel="stylesheet" type="text/css" />
      <script src="assets/js/config.js"></script>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   </head>
   <body>
      <div class="wrapper">
         <?php include 'toolbar.php'; ?>
         <?php
            if ($role === 'admin') {
                include 'admin_menu.php';
            } else {
                include 'menu.php';
            }
            ?>
         <div class="page-content">
            <div class="container">
               <div class="row">
                  <div class="col-xl-12">
                     <div class="card">
                        <div class="card-header">
                           <h4 class="card-title">Finance Management</h4>
                        </div>
                        <div class="card-body">
                           <?php if (!empty($success_message)): ?>
                           <div class="alert alert-success"><?php echo $success_message; ?></div>
                           <?php endif; ?>
                           <?php if (!empty($error_message)): ?>
                           <div class="alert alert-danger"><?php echo $error_message; ?></div>
                           <?php endif; ?>
                           <div class="row mb-4">
                              <div class="col-md-4">
                                 <div class="card bg-success text-white">
                                    <div class="card-body">
                                       <h5 class="card-title">Income</h5>
                                       <h2 class="card-text">₹<?php echo number_format($income_total); ?></h2>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-4">
                                 <div class="card bg-danger text-white">
                                    <div class="card-body">
                                       <h5 class="card-title">Expenses</h5>
                                       <h2 class="card-text">₹<?php echo number_format($expense_total); ?></h2>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-4">
                                 <div class="card bg-primary text-white">
                                    <div class="card-body">
                                       <h5 class="card-title">Balance</h5>
                                       <h2 class="card-text">₹<?php echo number_format($balance); ?></h2>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <div class="row">
                              <div class="col-md-12">
                                 <div class="card">
                                    <div class="card-header">
                                       <h5 class="card-title">Add Transaction</h5>
                                    </div>
                                    <div class="card-body">
                                       <form method="POST" action="finance-management.php">
                                          <div class="row">
                                             <div class="col-md-3 mb-3">
                                                <label for="transaction_type" class="form-label">Type</label>
                                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                                   <option value="income">Income</option>
                                                   <option value="expense">Expense</option>
                                                </select>
                                             </div>
                                             <div class="col-md-3 mb-3">
                                                <label for="amount" class="form-label">Amount</label>
                                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                                             </div>
                                             <div class="col-md-3 mb-3">
                                                <label for="category_id" class="form-label">Category</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                   <option value="">Select Category</option>
                                                   <?php foreach ($categories as $id => $name): ?>
                                                   <option value="<?php echo $id; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $id) ? 'selected' : ''; ?>>
                                                      <?php echo htmlspecialchars($name); ?>
                                                   </option>
                                                   <?php endforeach; ?>
                                                </select>
                                             </div>
                                             <div class="col-md-3 mb-3">
                                                <label for="date" class="form-label">Date</label>
                                                <input type="date" class="form-control" id="date" name="date" required value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : date('Y-m-d'); ?>">
                                             </div>
                                          </div>
                                          <div class="row">
                                             <div class="col-md-12 mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="2"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                             </div>
                                          </div>
                                          <button type="submit" name="add_transaction" class="btn btn-primary">Add Transaction</button>
                                       </form>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-md-12">
                                 <div class="card">
                                    <div class="card-header">
                                       <h5 class="card-title">Transaction History</h5>
                                    </div>
                                    <div class="card-body">
                                       <div class="table-responsive">
                                          <table class="table table-striped">
                                             <thead>
                                                <tr>
                                                   <th>Date</th>
                                                   <th>Type</th>
                                                   <th>Category</th>
                                                   <th>Description</th>
                                                   <th>Amount</th>
                                                   <?php if ($role === 'admin'): ?>
                                                   <th>User</th>
                                                   <?php endif; ?>
                                                </tr>
                                             </thead>
                                             <tbody>
                                                <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                   <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                                   <td>
                                                      <span class="badge bg-<?php echo $transaction['type'] === 'income' ? 'success' : 'danger'; ?>">
                                                      <?php echo ucfirst($transaction['type']); ?>
                                                      </span>
                                                   </td>
                                                   <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                                   <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                   <td class="<?php echo $transaction['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                      <?php echo ($transaction['type'] === 'income' ? '+' : '-') . number_format($transaction['amount'], 2); ?>
                                                   </td>
                                                   <?php if ($role === 'admin'): ?>
                                                   <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                                                   <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($transactions)): ?>
                                                <tr>
                                                   <td colspan="<?php echo $role === 'admin' ? '6' : '5'; ?>" class="text-center">No transactions found</td>
                                                </tr>
                                                <?php endif; ?>
                                             </tbody>
                                          </table>
                                       </div>
                                    </div>
                                 </div>
                              </div>
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
      <script>
         // Initialize form validation
         $(document).ready(function() {
             $("form").validate({
                 rules: {
                     amount: {
                         required: true,
                         number: true,
                         min: 0.01
                     },
                     category_id: {
                         required: true
                     },
                     date: {
                         required: true,
                         date: true
                     }
                 },
                 messages: {
                     amount: {
                         required: "Please enter an amount",
                         number: "Please enter a valid number",
                         min: "Amount must be greater than zero"
                     },
                     category_id: {
                         required: "Please select a category"
                     },
                     date: {
                         required: "Please select a date",
                         date: "Please enter a valid date"
                     }
                 }
             });
         });
      </script>
   </body>
</html>