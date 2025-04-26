<?php
   require 'config/db_connection.php';
   require 'functions/profile_functions.php';
   
   
   
   // Validate profile URL
   if (!isset($_GET['profile_url'])) {
       header("HTTP/1.0 400 Bad Request");
       die("Profile URL is required");
   }
   
   $profile_url = $_GET['profile_url'];
   
   // Get user ID from profile URL
   $profile_data = getUserByProfileUrl($conn, $profile_url);
   if (!$profile_data) {
       header("Location: page-not-found.php");
       exit();
   }
   
   $user_id = $profile_data['user_id'];
   
   // Get all profile data
   $user = getUserById($conn, $user_id);
   if (!$user) {
       die("User not found");
   }
   
   $business_info = getBusinessInfo($conn, $user_id);
   $photos = getProfilePhotos($conn, $user_id);
   $social_link = getSocialLinks($conn, $user_id);
   $products = getProducts($conn, $user_id);
   $services = getServices($conn, $user_id);
   $gallery = getGallery($conn, $user_id);
   $ratings = getRatings($conn, $user_id);
   $bank_details = getBankDetails($conn, $user_id);
   $qr_codes = getQrCodes($conn, $user_id);
   
   // Handle rating submission
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
       $rating_data = [
           'reviewer_name' => $_POST['reviewer_name'] ?? '',
           'reviewer_email' => $_POST['reviewer_email'] ?? '',
           'reviewer_phone' => $_POST['reviewer_phone'] ?? '',
           'rating' => intval($_POST['rating'] ?? 0),
           'feedback' => $_POST['feedback'] ?? ''
       ];
       
       if (submitRating($conn, $user_id, $rating_data)) {
           header("Location: ?profile_url=$profile_url");
           exit();
       }
   }
   
   // Include HTML components
   require 'includes/header.php';
   require 'includes/navigation.php';
   require 'includes/profile_header.php';
   require 'includes/business_info.php';
   require 'includes/products.php';
   require 'includes/services.php';
   require 'includes/gallery.php';
   require 'includes/ratings.php';
   require 'includes/bank_details.php';
   require 'includes/qr_codes.php';
   require 'includes/share_section.php';
   require 'includes/footer.php';
   
   // Close connection
   $conn = null;
   ?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
      <title><?= htmlspecialchars($user['name']) ?> | <?= htmlspecialchars($business_info['business_name'] ?? '') ?></title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
      <link href="assets/css/main.css" rel="stylesheet">
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
                 burgerMenu?.classList.remove('show');
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
   </head>
   <body class="restaurant">
      <div class="main">
      
