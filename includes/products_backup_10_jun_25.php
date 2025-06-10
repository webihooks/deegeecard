<!-- products -->
<div class="products">
 <h6>Products</h6>
 <div class="mb-3">
    <div class="input-group">
       <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
       <button class="btn btn-outline-secondary" type="button" id="clearSearch">
       <i class="bi bi-x"></i>
       </button>
    </div>
 </div>
 <!-- Shopping Cart Sidebar -->
 <div class="cart-sidebar">
    <div class="cart-header">
       <h5>Your Cart</h5>
       <button class="btn-close" onclick="closeCart()"></button>
    </div>
    <div class="cart-items" id="cartItems">
       <!-- Cart items will be displayed here -->
    </div>
    <div class="customer-details">
       <h6>Customer Information</h6>
       <div class="mb-1 col-half">
          <label for="customerName" class="form-label">Name*</label>
          <input type="text" class="form-control" id="customerName" placeholder="Your name" required>
       </div>
       <div class="mb-1 col-half">
          <label for="customerPhone" class="form-label">Phone*</label>
          <input type="tel" class="form-control" id="customerPhone" placeholder="Your phone number" required>
       </div>
       <div class="mb-1 col-full">
          <label for="customerAddress" class="form-label">Address*</label>
          <textarea class="form-control" id="customerAddress" rows="2" placeholder="Delivery address" required></textarea>
       </div>
       <div class="mb-1 col-full">
          <label for="customerNotes" class="form-label">Order Notes</label>
          <textarea class="form-control" id="customerNotes" rows="2" placeholder="Any special instructions"></textarea>
       </div>
    </div>
    <div class="cart-footer">
       <div class="cart-total">
          Total: ₹<span id="cartTotal">0.00</span>
       </div>
       <button class="btn btn-success w-100" onclick="placeOrderOnWhatsApp()">
       <i class="bi bi-whatsapp"></i> Place Order
       </button>
    </div>
 </div>
 <div class="row" id="productsContainer">
    <?php if (!empty($products)): ?>
    <?php foreach ($products as $product): ?>
    <div class="col-sm-12 product-item" 
       data-name="<?= htmlspecialchars(strtolower($product['product_name'])) ?>"
       data-desc="<?= htmlspecialchars(strtolower($product['description'])) ?>">
       <div class="card product-card">
          <img src="<?= !empty($product['image_path']) ? htmlspecialchars($product['image_path']) : 'images/no-image.jpg' ?>" 
             class="card-img-top product-img" 
             alt="<?= htmlspecialchars($product['product_name']) ?>">
          <div class="card-body">
             <h5 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h5>
             <p class="card-text"><?= htmlspecialchars($product['description']) ?></p>
             <div class="d-flex justify-content-between align-items-center">
                <span class="text-primary fw-bold">₹<?= number_format($product['price']) ?></span>
                <span class="badge bg-<?= ($product['quantity'] > 0) ? 'success' : 'danger' ?>">
                <?= ($product['quantity'] > 0) ? 'In Stock' : 'Out of Stock' ?>
                </span>
             </div>
             <?php if ($product['quantity'] > 0): ?>
             <small class="text-muted">Quantity: <?= $product['quantity'] ?></small>
             <?php endif; ?>
             <div class="mt-3">
                <button class="btn btn-primary w-100 add-to-cart" 
                   data-id="<?= htmlspecialchars($product['product_name']) ?>"
                   data-name="<?= htmlspecialchars($product['product_name']) ?>"
                   data-price="<?= $product['price'] ?>"
                   data-max="<?= $product['quantity'] ?>"
                   data-image="<?= htmlspecialchars($product['image_path']) ?>"> 
                <i class="bi bi-cart-plus"></i> Add to Cart
                </button>
             </div>
          </div>
       </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="col-12">
       <div class="alert alert-info">No products available yet.</div>
    </div>
    <?php endif; ?>
 </div>
 <!-- Cart Button -->
 <div class="cart-button-container">
    <button class="btn btn-primary cart-button" onclick="toggleCart()">
    <i class="bi bi-cart"></i> 
    <span class="cart-count">0</span>
    </button>
 </div>
</div>
<script>
 // Cart functionality
 let cart = [];
 
 // Initialize cart from localStorage if available
 if (localStorage.getItem('cart')) {
     cart = JSON.parse(localStorage.getItem('cart'));
     updateCartUI();
 }
 
 // Add to cart button click handler
 document.querySelectorAll('.add-to-cart').forEach(button => {
     button.addEventListener('click', function() {
         const product = {
             id: this.dataset.id,
             name: this.dataset.name,
             price: parseFloat(this.dataset.price),
             max: parseInt(this.dataset.max),
             quantity: 1,
             image_path: this.dataset.image
         };
         
         // Check if product already in cart
         const existingItem = cart.find(item => item.id === product.id);
         
         if (existingItem) {
             if (existingItem.quantity < existingItem.max) {
                 existingItem.quantity++;
             } else {
                 alert('Maximum quantity reached for this product');
                 return;
             }
         } else {
             cart.push(product);
         }
         
         saveCart();
         updateCartUI();
         showCart(); // Show cart when adding an item
     });
 });
 
 // Save cart to localStorage
 function saveCart() {
     localStorage.setItem('cart', JSON.stringify(cart));
 }
 
 // Update cart UI
 function updateCartUI() {
 // Update cart items list
 const cartItemsContainer = document.getElementById('cartItems');
 cartItemsContainer.innerHTML = '';
 
 let total = 0;
 
 cart.forEach((item, index) => {
     total += item.price * item.quantity;
     
     const itemElement = document.createElement('div');
     itemElement.className = 'cart-item';
     
     // Get the product image (use default image if not available)
     const productImage = item.image_path ? item.image_path : 'images/no-image.jpg';
 
     itemElement.innerHTML = `
         <div class="cart-item-info d-flex">
             <img src="${productImage}" alt="${item.name}" class="cart-item-img" />
             <div class="ms-1">
                 <h6>${item.name}</h6>
                 <div>₹${item.price.toFixed(2)} x ${item.quantity}</div>
             </div>
         </div>
         <div class="cart-item-controls">
             <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${index}, -1)">
                 <i class="bi bi-dash"></i>
             </button>
             <input type="number" value="${item.quantity}" min="1" max="${item.max}" 
                    onchange="updateQuantityInput(${index}, this.value)">
             <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${index}, 1)">
                 <i class="bi bi-plus"></i>
             </button>
             <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${index})">
                 <i class="bi bi-trash"></i>
             </button>
         </div>
     `;
     
     cartItemsContainer.appendChild(itemElement);
 });
 
 // Update total
 document.getElementById('cartTotal').textContent = total.toFixed(2);
 
 // Update cart count
 const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
 document.querySelector('.cart-count').textContent = itemCount;
 }
 
 
 // Update quantity with buttons
 function updateQuantity(index, change) {
     const item = cart[index];
     const newQuantity = item.quantity + change;
     
     if (newQuantity < 1) {
         removeFromCart(index);
         return;
     }
     
     if (newQuantity > item.max) {
         alert('Maximum quantity reached for this product');
         return;
     }
     
     item.quantity = newQuantity;
     saveCart();
     updateCartUI();
 }
 
 // Update quantity with input
 function updateQuantityInput(index, value) {
     const item = cart[index];
     const newQuantity = parseInt(value);
     
     if (isNaN(newQuantity) || newQuantity < 1) {
         item.quantity = 1;
     } else if (newQuantity > item.max) {
         alert('Maximum quantity reached for this product');
         item.quantity = item.max;
     } else {
         item.quantity = newQuantity;
     }
     
     saveCart();
     updateCartUI();
 }
 
 // Remove item from cart
 function removeFromCart(index) {
     cart.splice(index, 1);
     saveCart();
     updateCartUI();
 }
 
 // Toggle cart visibility
 function toggleCart() {
     document.querySelector('.cart-sidebar').classList.toggle('open');
 }
 
 function showCart() {
     document.querySelector('.cart-sidebar').classList.add('open');
 }
 
 function closeCart() {
     document.querySelector('.cart-sidebar').classList.remove('open');
 }
 
 // Place order on WhatsApp
 function placeOrderOnWhatsApp() {
 if (cart.length === 0) {
     alert('Your cart is empty');
     return;
 }
 
 // Get customer details
 const customerName = document.getElementById('customerName').value;
 const customerPhone = document.getElementById('customerPhone').value;
 const customerAddress = document.getElementById('customerAddress').value;
 const customerNotes = document.getElementById('customerNotes').value;
 
 // Validate required fields
 if (!customerName || !customerPhone) {
     alert('Please provide your name and phone number');
     return;
 }
 
 const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
 let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";
 
 if (!phoneNumber) {
     alert('WhatsApp number not available');
     return;
 }
 
 let message = `*##### NEW ORDER #####*\n\n`;
 
 message += `--------------------------\n`;
 message += `*Order Details:*\n`;
 message += `--------------------------\n`;
 let total = 0;
 
 cart.forEach(item => {
     message += `*${item.name}*\n`;
     message += `Price: ₹${item.price.toFixed(2)}\n`;
     message += `Quantity: ${item.quantity}\n`;
     message += `Subtotal: ₹${(item.price * item.quantity).toFixed(2)}\n\n`;
     total += item.price * item.quantity;
     message += `--------------------------\n`;
 });
 
 
 message += `*Total Amount: ₹${total.toFixed(2)}*\n`;
 message += `--------------------------\n\n`;
 
 
 message += `*Customer Details:*\n`;
 message += `Name: ${customerName}\n`;
 message += `Phone: ${customerPhone}\n`;
 if (customerAddress) message += `Address: ${customerAddress}\n`;
 if (customerNotes) message += `*Notes:* ${customerNotes}\n\n`;
 
 
 
 message += `*Please confirm this order.*`;
 
 window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
 
 // Optional: Clear cart after order
 cart = [];
 saveCart();
 updateCartUI();
 closeCart();
 }
</script>