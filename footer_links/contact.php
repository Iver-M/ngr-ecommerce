<?php
session_start();
require __DIR__ . '/../config/db.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $order_id = !empty($_POST['order_id']) ? $_POST['order_id'] : null;

    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO support_tickets (user_id, name, email, subject, message, order_id) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$user_id, $name, $email, $subject, $message, $order_id]);
            $success_message = "Your message has been sent! Our support team will get back to you shortly.";
        } catch (Exception $e) {
            $error_message = "Sorry, there was an error sending your message. Please try again later.";
        }
    } else {
        $error_message = "Please fill out all required fields.";
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .contact-form-container {
        background: #2a2a2a;
        color: #e0e0e0;
        padding: 2rem;
        border-radius: 0.5rem;
    }
    .form-label { color: #ccc; }
    .form-control { background-color: #1e1e1e; color: #fff; border-color: #444; }
    .form-control:focus { background-color: #1e1e1e; color: #fff; border-color: #fd7e14; box-shadow: 0 0 0 0.25rem rgba(253, 126, 20, 0.25); }
    .btn-orange { background-color: #fd7e14; border-color: #fd7e14; color: #fff; }
</style>

<div class="container my-5" style="padding-top: 80px;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="contact-form-container">
                <h2 class="text-center mb-4">Contact Support</h2>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?= $success_message ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>

                <?php if (!$success_message): // Hide form on success ?>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="order_id" class="form-label">Order ID (Optional)</label>
                        <input type="text" class="form-control" id="order_id" name="order_id" placeholder="e.g., 123">
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-orange btn-lg">Send Message</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>