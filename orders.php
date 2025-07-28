<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Asia/Kolkata'); // for Indian Standard Time

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

// Get selected date from request or default to today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

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

// Fetch all orders for this user with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get total count of orders for selected date
$count_sql = "SELECT COUNT(*) FROM orders WHERE user_id = ? AND DATE(created_at) = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("is", $user_id, $selected_date);
$count_stmt->execute();
$count_stmt->bind_result($total_orders);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_orders / $per_page);

// Fetch orders with items for selected date
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
    o.order_notes,
    COUNT(oi.item_id) as item_count
FROM orders o
LEFT JOIN order_items oi ON o.order_id = oi.order_id
WHERE o.user_id = ? AND DATE(o.created_at) = ?
GROUP BY o.order_id
ORDER BY o.created_at DESC
LIMIT ? OFFSET ?";

$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("isii", $user_id, $selected_date, $per_page, $offset);
$orders_stmt->execute();
$result = $orders_stmt->get_result();
$orders = [];

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
    <link href="assets/css/style.css?<?php echo time(); ?>" rel="stylesheet" />
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
                                    <div class="float-end">
                                        <form method="GET" class="d-inline-flex">
                                            <input type="date" name="date" class="form-control me-2" 
                                                   value="<?php echo htmlspecialchars($selected_date); ?>" 
                                                   max="<?php echo date('Y-m-d'); ?>">
                                            <button type="submit" class="btn btn-primary">View Orders</button>
                                        </form>
                                        <button type="button" id="fullscreenToggle" class="btn btn-primary ms-2">Fullscreen</button>
                                    </div>
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($message)): ?>
                                    <div class="alert alert-<?php echo $message_type; ?>">
                                        <?php echo htmlspecialchars($message); ?>
                                    </div>
                                <?php endif; ?>

                                <h5 class="mb-3">Orders for <?php echo date('F j, Y', strtotime($selected_date)); ?></h5>

                                <?php if (empty($orders)): ?>
                                    <div class="alert alert-info">
                                        No orders found for <?php echo date('F j, Y', strtotime($selected_date)); ?>.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive mobile_table">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Sr. No.</th>
                                                    <th>Order ID</th>
                                                    <th>Time</th>
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
                                                        <td data-label="Sr. No."><?php echo $index + 1 + $offset; ?></td>
                                                        <td data-label="Order ID">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                                        <td data-label="Time"><?php echo date('h:i A', strtotime($order['created_at'])); ?></td>
                                                        <td data-label="Customer"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                        <td data-label="Type">
                                                            <?php 
                                                            if ($order['order_type'] === 'dining') {
                                                                echo 'Dining - Table ' . htmlspecialchars($order['table_number']);
                                                            } else {
                                                                echo ucfirst(htmlspecialchars($order['order_type']));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td data-label="Items"><?php echo htmlspecialchars($order['item_count']); ?></td>
                                                        <td data-label="Total">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td data-label="Status">
                                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $order['status'])); ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td data-label="Actions">
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
                                                        <a class="page-link" href="?date=<?php echo $selected_date; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                                            <span aria-hidden="true">&laquo;</span>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?date=<?php echo $selected_date; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?date=<?php echo $selected_date; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
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
                            <div id="modalOrderNotesContainer">
                                <h6>Order Notes</h6>
                                <p id="modalOrderNotes"></p>
                            </div>
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
                                <option value="preparing">Preparing</option>
                                <option value="out_for_delivery">Out for Delivery</option>
                                <option value="delivered">Delivered</option>
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
            if (window.POLLING_CONFIG.isReloading) return;
            window.POLLING_CONFIG.isReloading = true;
            
            const alert = $(`
                <div class="alert alert-danger alert-dismissible fade show alert-fixed" role="alert">
                    <strong>Error:</strong> ${message}
                </div>
            `).appendTo('body');
            
            setTimeout(() => {
                alert.alert('close');
                window.location.href = 'orders.php?date=<?php echo $selected_date; ?>';
            }, 2000);
        }

        function updateOrderModal(order) {
            // Basic info
            $('#modalOrderId').text(order.order_id);
            $('#modalCustomerName').text(order.customer_name || 'Not specified');
            $('#modalCustomerPhone').text(order.customer_phone || 'Not specified');
            
            // Order type specifics
            if (order.order_type === 'delivery') {
                $('#modalDeliveryAddress').show().find('#modalAddressText').text(order.delivery_address || 'Not specified');
                $('#modalTableNumber').hide();
            } else {
                $('#modalDeliveryAddress').hide();
                $('#modalTableNumber').show().find('#modalTableText').text(order.table_number || 'Not specified');
            }
            
            // Order summary
            $('#modalOrderType').text(formatOrderType(order));
            $('#modalOrderDate').text(new Date(order.created_at).toLocaleString());
            
            // Status
            const statusBadge = $('#modalOrderStatus');
            statusBadge.text(formatStatus(order.status))
                .removeClass().addClass('status-badge status-' + order.status.toLowerCase());
            
            // Items
            renderOrderItems(order.items || []);

            // Order notes
            const $notesContainer = $('#modalOrderNotesContainer');
            const $notesText = $('#modalOrderNotes');
            
            if (order.order_notes) {
                $notesContainer.show();
                $notesText.text(order.order_notes);
            } else {
                $notesContainer.hide();
            }
            
            // Financials
            updateFinancials(order);
            
            // Form fields
            $('#modalFormOrderId').val(order.order_id);
            $('#modalCancelOrderId').val(order.order_id);
            $('#modalStatusSelect').val(order.status);
            
            // Action buttons
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
            
            // Toggle and set discount
            const discountAmount = parseFloat(order.discount_amount || 0);
            $('#modalDiscountRow').toggle(discountAmount > 0);
            if (discountAmount > 0) {
                $('#modalDiscountAmount').text(discountAmount.toFixed(2));
                $('#modalDiscountType').text(order.discount_type || 'Discount');
            }
            
            // Toggle and set GST
            const gstAmount = parseFloat(order.gst_amount || 0);
            $('#modalGstRow').toggle(gstAmount > 0);
            if (gstAmount > 0) $('#modalGstAmount').text(gstAmount.toFixed(2));
            
            // Toggle and set delivery
            const deliveryCharge = parseFloat(order.delivery_charge || 0);
            $('#modalDeliveryRow').toggle(deliveryCharge > 0);
            if (deliveryCharge > 0) $('#modalDeliveryCharge').text(deliveryCharge.toFixed(2));
            
            // Total
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
            // const originalText = button.html();
            // button.html('<i class="bi bi-arrow-repeat spin"></i> Processing...').prop('disabled', true);
            
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
                    window.showToast(`Order ${action.replace('_', ' ')} successful!`, 'success');
                },
                error: function(xhr) {
                    showErrorAndReload(`Action failed: ${xhr.responseText || 'Unknown error'}`);
                },
                complete: function() {
                    if (!window.POLLING_CONFIG.isReloading) {
                        button.html(originalText).prop('disabled', false);
                    window.location.href = 'orders.php?date=<?php echo $selected_date; ?>';
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
        function formatOrderType(order) {
            if (!order.order_type) return 'Unknown type';
            return order.order_type === 'dining' 
                ? `Dining (Table ${order.table_number || 'N/A'})` 
                : order.order_type.charAt(0).toUpperCase() + order.order_type.slice(1);
        }

        function formatStatus(status) {
            return status ? status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ') : 'Unknown';
        }

        // ============== PDF Generation ==============
        $('#downloadBillBtn').click(function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Header
            doc.setFont('helvetica', 'bold');
            doc.text('<?php echo addslashes($business_name); ?>', 105, 10, { align: 'center' });
            
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text('<?php echo addslashes($business_address); ?>', 105, 15, { align: 'center' });
            
            // Order info
            doc.text(`Order #${$('#modalOrderId').text()}`, 14, 25);
            doc.text(`Date: ${$('#modalOrderDate').text()}`, 14, 30);
            
            // Items table
            const items = [];
            $('#modalOrderItems tr').each(function() {
                const cols = $(this).find('td');
                if (cols.length === 4) {
                    items.push([cols.eq(0).text(), cols.eq(1).text(), cols.eq(2).text(), cols.eq(3).text()]);
                }
            });
            
            doc.autoTable({
                startY: 40,
                head: [['Item', 'Price', 'Qty', 'Total']],
                body: items,
                margin: { left: 14 },
                styles: { fontSize: 9 }
            });
            
            // Save PDF
            doc.save(`Order_${$('#modalOrderId').text()}.pdf`);
        });

        // ============== Fullscreen Toggle ==============
        const toggleBtn = document.getElementById('fullscreenToggle');

        function isFullscreen() {
            return document.fullscreenElement || 
                   document.webkitFullscreenElement || 
                   document.msFullscreenElement;
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

        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (isFullscreen()) {
                exitFullscreen();
            } else {
                enterFullscreen();
            }
        });

        // Update button label based on fullscreen change
        document.addEventListener('fullscreenchange', updateButtonLabel);
        document.addEventListener('webkitfullscreenchange', updateButtonLabel);
        document.addEventListener('msfullscreenchange', updateButtonLabel);

        function updateButtonLabel() {
            toggleBtn.textContent = isFullscreen() ? 'Exit Fullscreen' : 'Fullscreen';
        }

        // ============== Initialize ==============
        bindOrderHandlers();
    });
    </script>
</body>
</html>