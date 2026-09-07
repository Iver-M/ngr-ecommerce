</div> <!-- This closes the .container mt-4 from header.php -->

<footer class="footer" id="footer">
  <div class="footer-container">
    <div class="footer-col">
      <h3>About Us</h3>
      <p>We provide high-quality gym equipment and premium supplements to support your fitness journey.</p>
      <a class="footer-brand" href="index.php">
        <img src="uploads/images/logo/NGLogo.png" alt="NewGroundsLogo" height="150">
      </a>
    </div>
    <div class="footer-col">
      <h3>Customer Service</h3>
      <ul>
        <li><a href="faq.php">FAQ</a></li>
        <!-- UPDATED: This now opens the contact modal -->
        <li><a href="#" data-bs-toggle="modal" data-bs-target="#contactModal">Contact Us</a></li>
        <li><a href="profile.php?page=orders">Order Tracking</a></li>
        <li><a href="profile.php?page=addresses">My Account</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h3>Follow Us</h3>
      <p>Stay connected with us on social media for the latest updates, offers, and fitness tips.</p>
      <div class="social-links">
          <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
      </div>
    </div>
  </div>
  <div class="footer-bot">
    &copy; <?= date('Y') ?> NewGrounds - All Rights Reserved.
  </div>
</footer>

<!-- NEW: CONTACT FORM MODAL -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-black text-light">
            <form method="POST" action="submit_ticket.php">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="contactModalLabel">Contact Support</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-white">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal-name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="modal-name" name="name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal-email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="modal-email" name="email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal-subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="modal-subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal-order_id" class="form-label">Order ID (Optional)</label>
                        <input type="text" class="form-control" id="modal-order_id" name="order_id" placeholder="e.g., 123">
                    </div>
                    <div class="mb-3">
                        <label for="modal-message" class="form-label">Message</label>
                        <textarea class="form-control" id="modal-message" name="message" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-bg-outline-orange">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/script.js"></script>
<?php
// Check if the user is logged in and their details are in the session
if (isset($_SESSION['user_id']) && isset($_SESSION['user_name']) && isset($_SESSION['user_email'])): 
?>
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {};
        Tawk_API.visitor = {
            name: '<?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES) ?>',
            email: '<?= htmlspecialchars($_SESSION['user_email'], ENT_QUOTES) ?>'
        };
    </script>
<?php endif; ?>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/68e4742a36ce7e19507db697/1j6u6bi7o';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->  

</body>
</html>