<footer class="footer">
   <div class="container-fluid">
       <div class="row">
           <div class="col-12 text-center">
               <script>document.write(new Date().getFullYear())</script> &copy; DEEGEECARD.
           </div>
       </div>
   </div>
</footer>
<?php
                                if (isset($_SESSION['android_logout_button'])) {
                                    echo '<script>document.addEventListener("DOMContentLoaded", function () {
  const menu = document.querySelector(".main-nav");
  const toggleBtn = document.querySelector(".button-toggle-menu");

  // Toggle menu on button click
  toggleBtn.addEventListener("click", function (event) {
    event.stopPropagation(); // Prevents click from bubbling to document
    if (menu.style.marginLeft === "0px") {
      menu.style.marginLeft = "-280px"; // Hide menu
    } else {
      menu.style.marginLeft = "0px"; // Show menu
    }
  });

  // Close menu when clicking outside
  document.addEventListener("click", function (event) {
    if (!menu.contains(event.target) && !toggleBtn.contains(event.target)) {
      menu.style.marginLeft = "-280px";
    }
  });
});
</script>';
}
?>


