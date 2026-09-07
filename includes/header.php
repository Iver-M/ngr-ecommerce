<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- NEW: SESSION TIMEOUT LOGIC ---
define('SESSION_TIMEOUT_SECONDS', 1800); // 1800 seconds = 30 minutes

if (isset($_SESSION['user_id'])) { // Check if user is logged in
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
        // Last activity was more than 30 minutes ago
        session_unset();     // Unset $_SESSION variable for the run-time
        session_destroy();   // Destroy session data in storage
        header("Location: login.php?reason=session_expired"); // Redirect to login page
        exit();
    }
    $_SESSION['last_activity'] = time(); // Update last activity time stamp on each page load
}
// --- END OF SESSION TIMEOUT LOGIC ---
// Connect to DB if not already connected
if (!isset($pdo)) {
    require_once 'config/db.php';
}

$cart_item_count = 0;
$unread_notifications_count = 0;
$notifications = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // 1. Get Cart Item Count
    $stmt_cart = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt_cart->execute([$user_id]);
    $cart_item_count = $stmt_cart->fetchColumn() ?: 0;

    // 2. Get Unread Notifications Count
    $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_unread->execute([$user_id]);
    $unread_notifications_count = $stmt_unread->fetchColumn();

    // 3. Get Recent Notifications for Display
    $stmt_notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_notifs->execute([$user_id]);
    $notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. Get User Profile Picture ---
    $stmt_user = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $_SESSION['profile_picture'] = $user_data['profile_picture'] ?? 'default.png';

}

// Helper function to create "time ago" format
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gym Equipment Store</title>
    <link rel="icon" type="image/png" href="uploads/images/logo/NGLogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="./assets/style1.css" rel="stylesheet">
    <link href="./assets/profile_style.css" rel="stylesheet">
    <link href="./assets/products.css" rel="stylesheet">
    <style>
        /* NEW: Added root variables from your CSS */
        :root { 
            --custom-orange: #fd7e14;
            --border-color: #333;
        }

        .icon-wrapper { position: relative; display: inline-block; }
        .icon-badge { position: absolute; top: -8px; right: -8px; padding: 3px 6px; border-radius: 50%; background-color: red; color: white; font-size: 10px; line-height: 1; }
        .notification-dropdown { width: 320px; padding: 0; }
        .notification-item { display: block; padding: 0.75rem 1rem; border-bottom: 1px solid #dee2e6; text-decoration: none; color: #212529; opacity: 50%; white-space: normal; }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background-color: #fd7e14; color: white; font-weight: bold; opacity: 100%; }
        .notification-item.unread { background-color: #fd7e14; color: #000; font-weight: bold; opacity: 100%; }
        .notification-item.unread .time { color: #333; }
        .notification-item .time { font-size: 0.75em; color: #6c757d; }

        /* NEW: Style for nav profile pic, based on your CSS */
        .profile-picture-nav {
            width: 40px; /* Set to reasonable size */
            height: 40px; /* Set to reasonable size */
            border-radius: 50%; /* From your CSS */
            object-fit: cover;
            border: 1px solid linear-gradient(-45deg, #ff5c1c 100%, yellow 100%); /* Thinner border for small icon */
             box-shadow: 0 2px 8px rgba(253, 126, 20, 0.3); /* Softer shadow */
            margin-right: 0.5rem; /* Spacing */
        }
    </style>
</head>
<body>
    <nav id="navbar" class="nav" style="z-index: 1080;">
        <a class="navbar-brand" href="index.php">
            <img src="uploads/images/logo/NGLogo.png" alt="logo" class="nav-logo" height=40>
            <span>NewGrounds</span>
        </a>
        <ul>
            <li><a class="nav-link" href="index.php">Home</a></li>
			<li><a class="nav-link" href="https://ngr.infinityfree.me/index.php#about">About</a></li>            <li><a class="nav-link" href="products.php">Shop</a></li>
            <li><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#contactModal" style=>Contact</a></li>
        </ul> 
        <div class="login-link d-flex align-items-center">
            <?php if(isset($_SESSION['user_id'])): ?>
                
                <div class="username-container d-flex align-items-center me-3">
                    
                    <img src="uploads/profile_pictures/<?= htmlspecialchars($_SESSION['profile_picture'] ?? 'default.png') ?>" 
                         alt="Profile Picture" 
                         class="profile-picture-nav" 
                         onerror="this.src='uploads/profile_pictures/default.png'">
                    <span class="username"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">View Profile</a>
                        <a href="wishlist.php" class="dropdown-item">Wishlist</a>
                        <a href="logout.php" class="dropdown-item">Logout</a>
                    </div>
                </div>

                <div class="dropdown me-3">
                    <a href="#" class="btn icon-wrapper" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell" style="font-size: 18px;"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="icon-badge" id="notification-badge"><?= $unread_notifications_count ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                        <?php if (empty($notifications)): ?>
                            <li><p class="text-center text-muted p-3 mb-0">No new notifications.</p></li>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <li>
                                    <a class="notification-item <?= !$notif['is_read'] ? 'unread' : '' ?>" href="#" data-id="<?= $notif['id'] ?>" data-link="<?= htmlspecialchars($notif['link']) ?>">
                                        <div><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="time"><?= time_ago($notif['created_at']) ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider my-0"></li>
                        <li><a class="dropdown-item text-center small py-2" href="#">View all notifications</a></li>
                    </ul>
                </div>
                
                <a class="btn icon-wrapper" href="cart.php">
                    <i class="fas fa-shopping-cart" style="font-size: 18px;"></i>
                    <?php if ($cart_item_count > 0): ?>
                        <span class="icon-badge"><?= $cart_item_count ?></span>
                    <?php endif; ?>
                </a>

            <?php else: ?>
                <a class="btn" href="login.php?redirect=cart"><i class="fas fa-shopping-cart" style="font-size: 18px;"></i></a>
                <a class="btn" href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>
<div class="container mt-4">

    <?php if (isset($_SESSION['message'])): ?>
    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <h5 class="mb-3"><?= htmlspecialchars($_SESSION['message']); ?></h5>
                    <button type="button" class="btn btn-orange" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            var modal = new bootstrap.Modal(document.getElementById("actionModal"));
            modal.show();
        });
    </script>
    <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const notificationItems = document.querySelectorAll('.notification-item');

    notificationItems.forEach(item => {
        item.addEventListener('click', function(event) {
            event.preventDefault();
            const notificationId = this.getAttribute('data-id');
            const destinationLink = this.getAttribute('data-link');
            fetch('mark_one_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Notification ' + notificationId + ' marked as read.');
                }
                window.location.href = destinationLink;
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = destinationLink;
            });
        });
    });
});
</script>