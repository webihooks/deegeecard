<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($user['name']) ?> | <?= htmlspecialchars($business_info['business_name'] ?? '') ?></title>

    <meta name="description" content="<?= htmlspecialchars($business_info['business_description']) ?>">
    <link rel="icon" type="image/png" href="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>">

    <!-- Open Graph Tags (for social media sharing) -->
    <meta property="og:title" content="<?= htmlspecialchars($business_info['business_name'] ?? '') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($business_info['business_description']) ?>">
    <meta property="og:image" content="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>">
    <meta property="og:type" content="restaurant">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($business_info['business_name'] ?? '') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($business_info['business_description']) ?>">
    <meta name="twitter:image" content="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css?<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css?<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-81W5S4MMGY"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-81W5S4MMGY');
    </script>


    <link href="assets/css/main.css?<?php echo time(); ?>" rel="stylesheet">
    <script>
        window.addEventListener('scroll', function() {
            const coverPhoto = document.querySelector('.cover_photo');
            const profilePhoto = document.querySelector('.profile_photo');
            const burgerMenu = document.querySelector('.burger-menu');
            
            if (window.scrollY > 50) {
                coverPhoto?.classList.add('small');
                profilePhoto?.classList.add('small', 'with-burger');
                burgerMenu?.classList.add('show');
            } else {
                coverPhoto?.classList.remove('small');
                profilePhoto?.classList.remove('small', 'with-burger');
                // burgerMenu?.classList.remove('show');
            }
        });
        
        function sendProductEnquiry(productName, productPrice, productDescription) {
            const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
            let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";
            
            if (phoneNumber) {
                const message = `Product Enquiry:\n\n*Product Name:* ${productName}\n*Price:* ₹${productPrice}\n*Description:* ${productDescription}\n\nI'm interested in this product. Please provide more details.`;
                window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
            }
        }
        
        function sendServiceEnquiry(serviceName, servicePrice, serviceDescription, serviceDuration) {
            const whatsappLink = "<?= $social_link['whatsapp'] ?? '' ?>";
            let phoneNumber = whatsappLink.match(/wa\.me\/(\d+)/)?.[1] || "<?= $user['phone'] ?? '' ?>";
            
            if (phoneNumber) {
                const message = `Service Enquiry:\n\n*Service Name:* ${serviceName}\n*Price:* ₹${servicePrice}\n*Duration:* ${serviceDuration}\n*Description:* ${serviceDescription}\n\nI'm interested in this service. Please provide more details.`;
                window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
            }
        }
        
        function scrollToSection(sectionClass) {
            const section = document.querySelector(`.${sectionClass}`);
            if (section) {
                const burger = document.querySelector('.burger-menu');
                const overlay = document.getElementById('menuOverlay');
                burger?.classList.remove('change');
                overlay?.classList.remove('active');
                
                const offsetPosition = section.getBoundingClientRect().top + window.pageYOffset - 100;
                window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
            }
        }
        
        function toggleMenu() {
            const burger = document.querySelector('.burger-menu');
            const overlay = document.getElementById('menuOverlay');
            burger?.classList.toggle('change');
            overlay?.classList.toggle('active');
        }
        
        function showQrModal(paymentType, imageSrc) {
            document.getElementById('qrModalTitle').textContent = paymentType + ' QR Code';
            document.getElementById('modalQrImage').src = imageSrc;
            document.getElementById('payNowLink').href = 'upi://pay?pa=' + encodeURIComponent('<?= $qr_codes[0]["mobile_number"] ?? "" ?>');
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        }
        
        // Product search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('productSearch');
            const clearSearch = document.getElementById('clearSearch');
            const productItems = document.querySelectorAll('.product-item');
            
            searchInput?.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                productItems.forEach(item => {
                    const matches = item.getAttribute('data-name').includes(searchTerm) || 
                                  item.getAttribute('data-desc').includes(searchTerm);
                    item.style.display = matches ? 'block' : 'none';
                });
            });
            
            clearSearch?.addEventListener('click', function() {
                searchInput.value = '';
                productItems.forEach(item => item.style.display = 'block');
            });
        });

    </script>

    <style>
        :root {
            --primary-color: <?= $primary_color ?>;
            --secondary-color: <?= $secondary_color ?>;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .border-primary {
            border-color: var(--primary-color) !important;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .social_networks li a {
            background: var(--primary-color) !important;
        }
        .btn-success {
            background: var(--primary-color) !important;
        }
        body {
            background-color: var(--secondary-color);
        }
        .burger-menu {
            background: var(--secondary-color);
        }
        .discount-card {
          background: var(--secondary-color);
        }
        .offer_popup .btn-close-black {
            background: var(--secondary-color);
        }
        .btn:hover, .btn-check:checked + .btn, .btn.active, .btn.show, .btn:first-child:active, :not(.btn-check) + .btn:active {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        /* Loader Styles */
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }
        
        .loader {
            width: 48px;
            height: 48px;
            border: 5px solid <?php echo $primary_color; ?>;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }
        
        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        body.loading {
            overflow: hidden;
            height: 100vh;
        }
        .bouncing-loader {
            display: flex;
            gap: 8px;
        }
        .bouncing-loader div {
            width: 12px;
            height: 12px;
            background: <?php echo $primary_color; ?>;
            border-radius: 50%;
            animation: bounce 0.6s infinite alternate;
        }
        .bouncing-loader div:nth-child(2) {
            animation-delay: 0.2s;
        }
        .bouncing-loader div:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes bounce {
            to { transform: translateY(-12px); }
        }
        .tag-btn.active {
            background: <?= $primary_color ?>;
            color: white;
            border-color: <?= $primary_color ?>;
        }
        .rating-input .form-check-input:checked + .form-check-label {
            background-color: <?php echo $primary_color; ?>;
            color: #fff;
        }
        a {
            color: <?php echo $primary_color; ?>;
        }
    </style>
</head>
<body class="restaurant">

<!-- Loader -->
<!-- <div class="loader-container" id="loader">
    <div class="bouncing-loader">
        <div></div>
        <div></div>
        <div></div>
    </div>
</div> -->
<!-- <script>
    // Hide loader when page is fully loaded
    window.addEventListener('load', function() {
        const loader = document.getElementById('loader');
        // Add fade out effect
        loader.style.opacity = '0';
        // Remove loader after fade out completes
        setTimeout(() => {
            loader.style.display = 'none';
        }, 500); // Match this with the CSS transition duration
    });

    // Optional: Show loader when navigating away
    window.addEventListener('beforeunload', function() {
        document.getElementById('loader').style.display = 'flex';
        document.getElementById('loader').style.opacity = '1';
    });

    // Add loading class to body immediately
    document.body.classList.add('loading');
    
    window.addEventListener('load', function() {
        const loader = document.getElementById('loader');
        loader.style.opacity = '0';
        setTimeout(() => {
            loader.style.display = 'none';
            document.body.classList.remove('loading');
        }, 500);
    });
</script> -->


<?php if ($show_subscription_popup): ?>
<!-- Overlay -->
<div class="overlay" id="subscriptionOverlay"></div>

<!-- Subscription Popup -->
<div class="subscription-popup" id="subscriptionPopup">
    <!-- <button type="button" class="btn-close" onclick="closeSubscriptionPopup()"></button> -->
    <h3>Subscription Expired</h3>
    <p>You don't have any active subscription. Please subscribe to continue using our services.</p>
    <button class="btn btn-primary" onclick="redirectToSubscription()">Subscribe Now</button>
</div>

<script>
    // Show the popup when page loads
    window.onload = function() {
        document.getElementById('subscriptionOverlay').style.display = 'block';
        document.getElementById('subscriptionPopup').style.display = 'block';
    };
    
    function closeSubscriptionPopup() {
        document.getElementById('subscriptionOverlay').style.display = 'none';
        document.getElementById('subscriptionPopup').style.display = 'none';
    }
    
    function redirectToSubscription() {
        // Replace with your actual subscription page URL
        window.location.href = 'login.php';
    }
</script>
<?php endif; ?>

    <div class="main">

