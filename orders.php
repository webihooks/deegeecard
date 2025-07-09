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
$message_type = 'success';

// Fetch user details
$sql = "SELECT name, email, phone, address, role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $email, $phone, $address, $role);
$stmt->fetch();
$stmt->close();

// Fetch business information
$business_sql = "SELECT business_name, business_address FROM business_info WHERE user_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $user_id);
$business_stmt->execute();
$business_stmt->bind_result($business_name, $business_address);
$business_stmt->fetch();
$business_stmt->close();

// If no business info found, set defaults
if (empty($business_name)) {
    $business_name = "Your Restaurant";
    $business_address = "123 Restaurant Street, City";
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    // Validate the user owns this order
    $check_sql = "SELECT user_id FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            $message = "Order status updated successfully!";
        } else {
            $message = "Error updating order status: " . $conn->error;
            $message_type = "danger";
        }
        $update_stmt->close();
    } else {
        $message = "You don't have permission to update this order.";
        $message_type = "danger";
    }
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    
    // Validate the user owns this order
    $check_sql = "SELECT user_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_stmt->bind_result($order_user_id, $current_status);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($order_user_id == $user_id) {
        // Only allow cancellation if order is not already completed/delivered/cancelled
        if (in_array($current_status, ['pending', 'confirmed', 'preparing'])) {
            $update_sql = "UPDATE orders SET status = 'cancelled' WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $order_id);
            
            if ($update_stmt->execute()) {
                $message = "Order cancelled successfully!";
            } else {
                $message = "Error cancelling order: " . $conn->error;
                $message_type = "danger";
            }
            $update_stmt->close();
        } else {
            $message = "Order cannot be cancelled at this stage.";
            $message_type = "danger";
        }
    } else {
        $message = "You don't have permission to cancel this order.";
        $message_type = "danger";
    }
}

// Handle date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

// Validate dates
if (!strtotime($from_date)) $from_date = date('Y-m-d');
if (!strtotime($to_date)) $to_date = date('Y-m-d');

// Ensure to_date is not before from_date
if (strtotime($to_date) < strtotime($from_date)) {
    $to_date = $from_date;
}

// Fetch all orders for this user with date filter and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Prepare date filter conditions
$date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";

// Get total count of orders
$count_sql = "SELECT COUNT(*) FROM orders o WHERE o.user_id = ? $date_condition";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("iss", $user_id, $from_date, $to_date);
$count_stmt->execute();
$count_stmt->bind_result($total_orders);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_orders / $per_page);

// Fetch orders with items
$orders = [];
$orders_sql = "SELECT 
    o.order_id, 
    o.customer_name, 
    o.customer_phone, 
    o.order_type, 
    o.delivery_address, 
    o.table_number, 
    o.status, 
    o.subtotal, 
    o.discount_amount, 
    o.discount_type, 
    o.gst_amount, 
    o.delivery_charge, 
    o.total_amount, 
    o.created_at,
    COUNT(oi.item_id) as item_count
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
WHERE o.user_id = ? $date_condition
GROUP BY o.order_id
ORDER BY o.created_at DESC
LIMIT ? OFFSET ?";

$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("issii", $user_id, $from_date, $to_date, $per_page, $offset);
$orders_stmt->execute();
$result = $orders_stmt->get_result();

while ($order = $result->fetch_assoc()) {
    // Get order items
    $items_sql = "SELECT product_name, price, quantity FROM order_items WHERE order_id = ?";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order['order_id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $order['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();
    
    $orders[] = $order;
}
$orders_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Order Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="assets/css/vendor.min.css" rel="stylesheet" />
    <link href="assets/css/icons.min.css" rel="stylesheet" />
    <link href="assets/css/app.min.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="assets/js/config.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
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
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Order Management
                                    <button id="fullscreenToggle" class="btn btn-primary btn-block fr">Enter Fullscreen</button>
                                </h4>
                            </div>
                            <div class="card-body">
                                <!-- Date Filter Form -->
                                <form method="GET" action="orders.php" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label for="from_date" class="form-label">From Date</label>
                                            <input type="date" class="form-control" id="from_date" name="from_date" 
                                                   value="<?php echo htmlspecialchars($from_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="to_date" class="form-label">To Date</label>
                                            <input type="date" class="form-control" id="to_date" name="to_date" 
                                                   value="<?php echo htmlspecialchars($to_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary">Filter</button>
                                            <button type="button" id="resetFilter" class="btn btn-secondary ms-2">Today</button>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end justify-content-end">
                                            <span class="text-muted">
                                                Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders
                                            </span>
                                        </div>
                                    </div>
                                </form>

                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($orders)): ?>
                                    <div class="alert alert-info">
                                        No orders found for the selected date range.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Sr. No.</th>
                                                    <th>Order ID</th>
                                                    <th>Date</th>
                                                    <th>Customer</th>
                                                    <th>Type</th>
                                                    <th>Items</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orders as $index => $order): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1 + $offset; ?></td>
                                                        <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                                        <td><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                        <td>
                                                            <?php 
                                                            if ($order['order_type'] === 'dining') {
                                                                echo 'Dining - Table ' . htmlspecialchars($order['table_number']);
                                                            } else {
                                                                echo ucfirst(htmlspecialchars($order['order_type']));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($order['item_count']); ?></td>
                                                        <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $order['status'])); ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary view-order" 
                                                                    data-order-id="<?php echo $order['order_id']; ?>"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#orderModal">
                                                                <i class="bi bi-eye"></i> View
                                                            </button>
                                                            <?php if (in_array($order['status'], ['pending', 'confirmed', 'preparing'])): ?>
                                                                <button class="btn btn-sm btn-danger cancel-order" 
                                                                        data-order-id="<?php echo $order['order_id']; ?>">
                                                                    <i class="bi bi-x-circle"></i> Cancel
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation">
                                            <ul class="pagination justify-content-center mt-3">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?page=<?php echo $i; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>" aria-label="Next">
                                                            <span aria-hidden="true">&raquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade order-modal" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalLabel">Order Details #<span id="modalOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Customer Information</h6>
                            <p><strong>Name:</strong> <span id="modalCustomerName"></span></p>
                            <p><strong>Phone:</strong> <span id="modalCustomerPhone"></span></p>
                            <p id="modalDeliveryAddress"><strong>Address:</strong> <span id="modalAddressText"></span></p>
                            <p id="modalTableNumber"><strong>Table Number:</strong> <span id="modalTableText"></span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Order Summary</h6>
                            <p><strong>Order Type:</strong> <span id="modalOrderType"></span></p>
                            <p><strong>Order Date:</strong> <span id="modalOrderDate"></span></p>
                            <p><strong>Status:</strong> <span id="modalOrderStatus" class="status-badge"></span></p>
                        </div>
                    </div>
                    
                    <h6>Order Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm order-items-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="modalOrderItems">
                                <!-- Items will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Subtotal:</strong></td>
                                    <td>₹<span id="modalSubtotal"></span></td>
                                </tr>
                                <tr id="modalDiscountRow">
                                    <td><strong>Discount:</strong></td>
                                    <td>-₹<span id="modalDiscountAmount"></span> (<span id="modalDiscountType"></span>)</td>
                                </tr>
                                <tr id="modalGstRow">
                                    <td><strong>GST:</strong></td>
                                    <td>₹<span id="modalGstAmount"></span></td>
                                </tr>
                                <tr id="modalDeliveryRow">
                                    <td><strong>Delivery Charge:</strong></td>
                                    <td>₹<span id="modalDeliveryCharge"></span></td>
                                </tr>
                                <tr class="table-active">
                                    <td><strong>Total Amount:</strong></td>
                                    <td><strong>₹<span id="modalTotalAmount"></span></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="orders.php" class="d-inline" id="statusUpdateForm">
                        <input type="hidden" name="order_id" id="modalFormOrderId">
                        <div class="input-group">
                            <select class="form-select" name="new_status" id="modalStatusSelect">
                                <option value="confirmed">Confirmed</option>
                                <!-- Dont remove comment <option value="preparing">Preparing</option>
                                <option value="out_for_delivery">Out for Delivery</option>
                                <option value="delivered">Delivered</option> -->
                                <option value="completed">Completed</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </div>
                    </form>

                    <!-- <button type="button" class="btn btn-success" id="downloadBillBtn">
                        <i class="bi bi-file-earmark-pdf"></i> Download Bill
                    </button> -->
                    
                    <form method="POST" action="orders.php" class="d-inline ms-2" id="cancelOrderForm">
                        <input type="hidden" name="order_id" id="modalCancelOrderId">
                        <button type="submit" name="cancel_order" class="btn btn-danger" style="display:none;">
                            <i class="bi bi-x-circle"></i> Cancel Order
                        </button>
                    </form>
                    
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/vendor.js"></script>
    <script src="assets/js/app.js"></script>
    
<script>
$(document).ready(function() {
    // ============== Initialize Data ==============
    let ordersData = <?php echo json_encode($orders); ?>;
    const POLL_INTERVAL = 10000; // 10 seconds
    let lastOrderId = <?php echo !empty($orders) ? max(array_column($orders, 'order_id')) : 0; ?>;
    let pollingActive = true;
    let isReloading = false;

    // ============== Audio Notification ==============
    let notificationAudio = null;
    
    try {
        notificationAudio = new Audio('assets/sounds/notification.mp3');
        notificationAudio.volume = 1;
        notificationAudio.load();
    } catch (e) {
        console.error('Audio initialization failed:', e);
    }

    function playNotification() {
        if (!notificationAudio) return;
        
        try {
            notificationAudio.currentTime = 0;
            notificationAudio.play().catch(e => {
                console.log('Audio play blocked:', e);
                $(document).one('click', function() {
                    notificationAudio.play().catch(console.error);
                });
            });
        } catch (e) {
            console.error('Audio playback error:', e);
        }
    }

    // ============== Polling Functions ==============
    function checkForNewOrders() {
        // Only check for new orders if we're on the first page
        if (!pollingActive || isReloading || <?php echo $page; ?> !== 1) {
            return;
        }

        console.log('[Poll] Checking for orders > ID:', lastOrderId);
        
        $.ajax({
            url: 'check_new_orders.php',
            type: 'GET',
            data: { 
                last_order_id: lastOrderId,
                from_date: '<?php echo $from_date; ?>',
                to_date: '<?php echo $to_date; ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    console.error('Poll error:', response.error);
                    return;
                }
                
                if (response.current_filter_from === '<?php echo $from_date; ?>' && 
                    response.current_filter_to === '<?php echo $to_date; ?>') {
                    
                    if (response.new_orders?.length > 0) {
                        console.log('[Poll] New orders:', response.new_orders);
                        lastOrderId = Math.max(lastOrderId, ...response.new_orders.map(o => o.order_id));
                        
                        playNotification();
                        showToast(`New ${response.new_orders.length > 1 ? 'orders' : 'order'} received!`, 'success');
                        
                        if (<?php echo $page; ?> === 1) {
                            refreshOrders();
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Poll failed:', status, error);
            },
            complete: function() {
                if (pollingActive && !isReloading) {
                    setTimeout(checkForNewOrders, POLL_INTERVAL);
                }
            }
        });
    }

    function refreshOrders() {
        if (isReloading) return;
        isReloading = true;
        
        $.ajax({
            url: 'orders.php',
            type: 'GET',
            data: { 
                refresh: true,
                from_date: '<?php echo $from_date; ?>',
                to_date: '<?php echo $to_date; ?>',
                page: <?php echo $page; ?>
            },
            success: function(response) {
                if (<?php echo $page; ?> === 1) {
                    $('.table-responsive').html($(response).find('.table-responsive').html());
                    
                    const scriptContent = $(response).filter('script').html();
                    const match = scriptContent.match(/ordersData\s*=\s*(\[.*?\])/);
                    
                    if (match?.[1]) {
                        try {
                            const newData = JSON.parse(match[1]);
                            ordersData.splice(0, ordersData.length, ...newData);
                            console.log('[Refresh] Orders data updated');
                            
                            if (newData.length > 0) {
                                lastOrderId = Math.max(lastOrderId, ...newData.map(o => o.order_id));
                            }
                        } catch(e) {
                            console.error('Data parse error:', e);
                        }
                    }
                    
                    bindOrderHandlers();
                }
            },
            error: function() {
                console.error('Refresh failed');
            },
            complete: function() {
                isReloading = false;
            }
        });
    }

    // ============== Order Management ==============
    function bindOrderHandlers() {
        $('.view-order').off('click').on('click', viewOrderHandler);
        $('.cancel-order').off('click').on('click', cancelOrderHandler);
    }

    function viewOrderHandler() {
        const orderId = $(this).data('order-id');
        const order = ordersData.find(o => o.order_id == orderId);
        
        if (!order) {
            console.error('Order not found:', orderId, 'in:', ordersData);
            showErrorAndReload('Order not loaded. Reloading...');
            return;
        }
        
        updateOrderModal(order);
    }

    function showErrorAndReload(message) {
        if (isReloading) return;
        isReloading = true;
        
        const alert = $(`
            <div class="alert alert-danger alert-dismissible fade show alert-fixed" role="alert">
                <strong>Error:</strong> ${message}
            </div>
        `).appendTo('body');
        
        setTimeout(() => {
            alert.alert('close');
            window.location.href = 'orders.php?from_date=<?php echo $from_date; ?>&to_date=<?php echo $to_date; ?>';
        }, 2000);
    }

    function updateOrderModal(order) {
        $('#modalOrderId').text(order.order_id);
        $('#modalCustomerName').text(order.customer_name || 'Not specified');
        $('#modalCustomerPhone').text(order.customer_phone || 'Not specified');
        
        if (order.order_type === 'delivery') {
            $('#modalDeliveryAddress').show().find('#modalAddressText').text(order.delivery_address || 'Not specified');
            $('#modalTableNumber').hide();
        } else {
            $('#modalDeliveryAddress').hide();
            $('#modalTableNumber').show().find('#modalTableText').text(order.table_number || 'Not specified');
        }
        
        $('#modalOrderType').text(formatOrderType(order));
        $('#modalOrderDate').text(new Date(order.created_at).toLocaleString());
        
        const statusBadge = $('#modalOrderStatus');
        statusBadge.text(formatStatus(order.status))
            .removeClass().addClass('status-badge status-' + order.status.toLowerCase());
        
        renderOrderItems(order.items || []);
        updateFinancials(order);
        
        $('#modalFormOrderId').val(order.order_id);
        $('#modalCancelOrderId').val(order.order_id);
        $('#modalStatusSelect').val(order.status);
        
        const showActions = ['pending', 'confirmed', 'preparing'].includes(order.status);
        $('#statusUpdateForm, #cancelOrderForm').toggle(showActions);
    }

    function renderOrderItems(items) {
        const $container = $('#modalOrderItems').empty();
        
        if (items.length === 0) {
            $container.append('<tr><td colspan="4" class="text-center">No items found</td></tr>');
            return;
        }
        
        items.forEach(item => {
            const total = (parseFloat(item.price || 0) * parseInt(item.quantity || 0)).toFixed(2);
            $container.append(`
                <tr>
                    <td>${item.product_name || 'Unnamed'}</td>
                    <td>₹${parseFloat(item.price || 0).toFixed(2)}</td>
                    <td>${item.quantity}</td>
                    <td>₹${total}</td>
                </tr>
            `);
        });
    }

    function updateFinancials(order) {
        $('#modalSubtotal').text(parseFloat(order.subtotal || 0).toFixed(2));
        
        const discountAmount = parseFloat(order.discount_amount || 0);
        $('#modalDiscountRow').toggle(discountAmount > 0);
        if (discountAmount > 0) {
            $('#modalDiscountAmount').text(discountAmount.toFixed(2));
            $('#modalDiscountType').text(order.discount_type || 'Discount');
        }
        
        const gstAmount = parseFloat(order.gst_amount || 0);
        $('#modalGstRow').toggle(gstAmount > 0);
        if (gstAmount > 0) $('#modalGstAmount').text(gstAmount.toFixed(2));
        
        const deliveryCharge = parseFloat(order.delivery_charge || 0);
        $('#modalDeliveryRow').toggle(deliveryCharge > 0);
        if (deliveryCharge > 0) $('#modalDeliveryCharge').text(deliveryCharge.toFixed(2));
        
        $('#modalTotalAmount').text(parseFloat(order.total_amount || 0).toFixed(2));
    }

    // ============== Order Actions ==============
    function cancelOrderHandler(e) {
        e.preventDefault();
        const orderId = $(this).data('order-id');
        
        if (confirm('Are you sure you want to cancel this order?')) {
            processOrderAction({
                action: 'cancel_order',
                order_id: orderId,
                button: $(this),
                success: () => updateOrderStatusUI(orderId, 'cancelled')
            });
        }
    }

    $('#cancelOrderForm').submit(function(e) {
        e.preventDefault();
        cancelOrderHandler(e);
    });

    $('#statusUpdateForm').submit(function(e) {
        e.preventDefault();
        processOrderAction({
            action: 'update_status',
            order_id: $('#modalFormOrderId').val(),
            new_status: $('#modalStatusSelect').val(),
            button: $(this).find('button[type="submit"]'),
            success: () => updateOrderStatusUI($('#modalFormOrderId').val(), $('#modalStatusSelect').val())
        });
    });

    function processOrderAction({action, order_id, new_status, button, success}) {
        const originalText = button.html();
        button.html('<i class="bi bi-arrow-repeat spin"></i> Processing...').prop('disabled', true);
        
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: { 
                [action]: true,
                order_id,
                ...(new_status && {new_status})
            },
            success: function() {
                success();
                $('#orderModal').modal('hide');
                showToast(`Order ${action.replace('_', ' ')} successful!`, 'success');
            },
            error: function(xhr) {
                showErrorAndReload(`Action failed: ${xhr.responseText || 'Unknown error'}`);
            },
            complete: function() {
                if (!isReloading) {
                    button.html(originalText).prop('disabled', false);
                }
            }
        });
    }

    function updateOrderStatusUI(orderId, newStatus) {
        const statusText = formatStatus(newStatus);
        const $badge = $(`tr:has(button[data-order-id="${orderId}"]) .status-badge`);
        
        $badge.text(statusText)
            .removeClass()
            .addClass(`status-badge status-${newStatus.toLowerCase()}`);
        
        $(`.cancel-order[data-order-id="${orderId}"]`)
            .toggle(['pending', 'confirmed', 'preparing'].includes(newStatus));
    }

    // ============== UI Helpers ==============
    function showToast(message, type = 'success') {
        $('.toast-container').remove();
        
        if ($('.toast-container').length === 0) {
            $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3"></div>');
        }
        
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `).appendTo('.toast-container');
        
        const toastInstance = new bootstrap.Toast(toast[0], {
            autohide: true,
            delay: 3000
        });
        toastInstance.show();
    }

    function formatOrderType(order) {
        if (!order.order_type) return 'Unknown type';
        return order.order_type === 'dining' 
            ? `Dining (Table ${order.table_number || 'N/A'})` 
            : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
    }

    function formatStatus(status) {
        return status ? status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ') : 'Unknown';
    }

    // ============== Initialize ==============
    checkForNewOrders();
    bindOrderHandlers();

    $(window).on('blur', () => pollingActive = false)
             .on('focus', () => { pollingActive = true; checkForNewOrders(); });

    $('head').append(`
        <style>
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .bi-arrow-repeat.spin { animation: spin 1s linear infinite; }
            
            .alert-fixed {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 9999;
                min-width: 300px;
                text-align: center;
            }
            
            .toast-container {
                z-index: 9999;
            }
            
            .status-badge {
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 0.8em;
                font-weight: 500;
                display: inline-block;
            }
            .status-pending { background-color: #ffc107; color: #000; }
            .status-confirmed { background-color: #17a2b8; color: #fff; }
            .status-preparing { background-color: #fd7e14; color: #fff; }
            .status-completed { background-color: #28a745; color: #fff; }
            .status-cancelled { background-color: #dc3545; color: #fff; }
            .status-delivered { background-color: #6f42c1; color: #fff; }
        </style>
    `);
});

const toggleBtn = document.getElementById('fullscreenToggle');

function isFullscreen() {
    return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
}

function enterFullscreen() {
    const elem = document.documentElement;
    if (elem.requestFullscreen) {
        elem.requestFullscreen();
    } else if (elem.webkitRequestFullscreen) {
        elem.webkitRequestFullscreen();
    } else if (elem.msRequestFullscreen) {
        elem.msRequestFullscreen();
    }
}

function exitFullscreen() {
    if (document.exitFullscreen) {
        document.exitFullscreen();
    } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
    } else if (document.msExitFullscreen) {
        document.msExitFullscreen();
    }
}

toggleBtn.addEventListener('click', () => {
    if (isFullscreen()) {
        exitFullscreen();
    } else {
        enterFullscreen();
    }
});

document.addEventListener('fullscreenchange', updateButtonLabel);
document.addEventListener('webkitfullscreenchange', updateButtonLabel);
document.addEventListener('msfullscreenchange', updateButtonLabel);

function updateButtonLabel() {
    toggleBtn.textContent = isFullscreen() ? 'Exit Fullscreen' : 'Enter Fullscreen';
}
</script>



</body>
</html>