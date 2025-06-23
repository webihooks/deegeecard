
<div class="burger-menu show" onclick="toggleMenu()">
    <div class="bar1"></div>
    <div class="bar2"></div>
    <div class="bar3"></div>
</div>

<div id="menuOverlay" class="menu-overlay">
    
    <?php if (!empty($business_info)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('business_details')">Business</a>
    <?php endif; ?>
    

    <?php if (!empty($products)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('products')">Products</a>
    <?php endif; ?>


    <?php if (!empty($services)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('services')">Services</a>
    <?php endif; ?>
    

    <?php if (!empty($gallery)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('gallery')">Photo Gallery</a>
    <?php endif; ?>

    
    <?php if (!empty($ratings)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('display_ratings')">Customer Reviews</a>
    <?php endif; ?>


    <?php if (!empty($bank_details)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('bank_details')">Bank Accounts</a>
    <?php endif; ?>

    
    <?php if (!empty($qr_codes)): ?>
            <a href="javascript:void(0)" onclick="scrollToSection('qr_code_details')">Pay with QR</a>
    <?php endif; ?>

    
      



</div>