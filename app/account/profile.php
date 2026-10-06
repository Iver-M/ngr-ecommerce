<?php
// --- SETUP AND POST ACTION HANDLING ---
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/db.php';

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";
$page = isset($_GET['page']) && in_array($_GET['page'], ['details', 'addresses', 'password', 'orders', 'returns', 'cancellations']) ? $_GET['page'] : 'details';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- Action: Update User Details ---
    if ($action === 'update_details') {
        $first_name = trim($_POST['first_name']);
        $last_name  = trim($_POST['last_name']);
        $contact    = trim($_POST['contact']);
        if (!ctype_digit($contact) || strlen($contact) != 11) {
            $_SESSION['error'] = "Contact number must be exactly 11 digits.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, contact = ? WHERE id = ?");
            if ($stmt->execute([$first_name, $last_name, $contact, $user_id])) {
                $_SESSION['success'] = "Profile details updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update profile details.";
            }
        }
        header("Location: profile.php?page=details");
        exit;
    }

    // --- Action: Change Password ---
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_hash = $stmt->fetchColumn();

        // --- **** MODIFICATION: Added new password validation rules **** ---
        if (!password_verify($current_password, $user_hash)) {
            $_SESSION['error'] = "Your current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
             $_SESSION['error'] = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $_SESSION['error'] = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $_SESSION['error'] = "Password must contain at least one number.";
        } elseif (!preg_match('/[\W_]/', $new_password)) { // \W is any non-word character, _ is for underscore
            $_SESSION['error'] = "Password must contain at least one special character.";
        } else {
            // All checks passed
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$new_hash, $user_id])) {
                $_SESSION['success'] = "Password has been changed successfully.";
            } else {
                $_SESSION['error'] = "Failed to update password.";
            }
        }
        // --- **** END MODIFICATION **** ---
        
        header("Location: profile.php?page=password");
        exit;
    }

    // --- Action: Update Profile Picture ---
    if ($action === 'update_picture' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);
        if (in_array($file_type, $allowed_types)) {
            $filename = uniqid() . '-' . basename($_FILES['profile_picture']['name']);
            $upload_path = 'uploads/profile_pictures/' . $filename;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $old_pic = $stmt->fetchColumn();
                if ($old_pic && file_exists('uploads/profile_pictures/' . $old_pic) && $old_pic !== 'default.png') {
                    unlink('uploads/profile_pictures/' . $old_pic);
                }
                $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->execute([$filename, $user_id]);
                $_SESSION['success'] = "Profile picture updated.";
            } else {
                $_SESSION['error'] = "Failed to upload the image.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }
        header("Location: profile.php?page=details");
        exit;
    }

    // --- Action: Manage Address (Add/Edit) ---
    if ($action === 'manage_address') {
        $address_id = $_POST['address_id'] ?? null;
        $recipient_name = trim($_POST['recipient_name']);
        $phone = trim($_POST['phone']);
        $region = trim($_POST['region_text']);
        $province = trim($_POST['province_text']);
        $city = trim($_POST['city_text']);
        $barangay = trim($_POST['barangay_text']);
        $postal_code = trim($_POST['postal_code']);
        $street_address = trim($_POST['street_address']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if ($is_default == 1) {
            $stmt_unset = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt_unset->execute([$user_id]);
        }

        if ($address_id) {
            $sql = "UPDATE user_addresses SET recipient_name=?, phone=?, region=?, province=?, city=?, barangay=?, postal_code=?, street_address=?, is_default=? WHERE address_id = ? AND user_id = ?";
            $params = [$recipient_name, $phone, $region, $province, $city, $barangay, $postal_code, $street_address, $is_default, $address_id, $user_id];
        } else {
            $sql = "INSERT INTO user_addresses (user_id, recipient_name, phone, region, province, city, barangay, postal_code, street_address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$user_id, $recipient_name, $phone, $region, $province, $city, $barangay, $postal_code, $street_address, $is_default];
        }
        
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $_SESSION['success'] = "Address saved successfully.";
        } else {
            $_SESSION['error'] = "Failed to save address.";
        }
        header("Location: profile.php?page=addresses");
        exit;
    }

    // --- Action: Set Default Address ---
    if ($action === 'set_default') {
        $address_id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            $pdo->commit();
            $_SESSION['success'] = "Default address updated.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Failed to update default address.";
        }
        header("Location: profile.php?page=addresses");
        exit;
    }
    
    // --- Action: Delete Address ---
    if ($action === 'delete_address') {
        $address_id = $_POST['address_id'];
        $stmt_check = $pdo->prepare("SELECT is_default FROM user_addresses WHERE address_id = ? AND user_id = ?");
        $stmt_check->execute([$address_id, $user_id]);
        $was_default = $stmt_check->fetchColumn();

        $stmt_delete = $pdo->prepare("DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?");
        if ($stmt_delete->execute([$address_id, $user_id])) {
            if ($was_default) {
                $stmt_new_default = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE user_id = ? ORDER BY address_id ASC LIMIT 1");
                $stmt_new_default->execute([$user_id]);
            }
            $_SESSION['success'] = "Address removed successfully.";
        } else {
            $_SESSION['error'] = "Failed to remove address.";
        }
        header("Location: profile.php?page=addresses");
        exit;
    }
}

// --- RENDER FUNCTIONS FOR EACH SECTION ---

function render_details_section($user) { ?>
    <div class="profile-content-box">
        <div class="box-header">
            <h3><i class="fas fa-user-circle"></i> My Account Details</h3>
            <button id="edit-details-btn" class="edit-btn">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        </div>
        <div class="box-body">
            <div id="details-view" class="user-details-grid">
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-user"></i></div>
                    <div class="detail-content">
                        <label>First Name</label>
                        <p><?= htmlspecialchars($user['first_name']) ?></p>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-user"></i></div>
                    <div class="detail-content">
                        <label>Last Name</label>
                        <p><?= htmlspecialchars($user['last_name']) ?></p>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-phone"></i></div>
                    <div class="detail-content">
                        <label>Contact Number</label>
                        <p><?= htmlspecialchars($user['contact']) ?></p>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-envelope"></i></div>
                    <div class="detail-content">
                        <label>Email Address</label>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
            </div>
            <form id="details-edit-form" method="POST" action="profile.php?page=details" class="d-none enhanced-form">
                <input type="hidden" name="action" value="update_details">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-phone"></i> Contact Number</label>
                    <input type="text" name="contact" class="form-control" maxlength="11" value="<?= htmlspecialchars($user['contact']) ?>" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-orange"><i class="fas fa-save"></i> Save Changes</button>
                    <button type="button" id="cancel-edit-btn" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
<?php }

function render_password_section() { ?>
    <div class="profile-content-box">
        <div class="box-header">
            <h3><i class="fas fa-lock"></i> Change Password</h3>
        </div>
        <div class="box-body">
            <form method="POST" action="profile.php?page=password" class="enhanced-form password-form">
                <input type="hidden" name="action" value="change_password">
                <div class="password-info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <h4>Password Requirements</h4>
                        <ul id="password-reqs" class="password-req-list">
                            <li id="req-length">Minimum 8 characters</li>
                            <li id="req-case">One uppercase letter</li>
                            <li id="req-number">One number</li>
                            <li id="req-special">One special character</li>
                            <li id="req-match">Passwords must match</li>
                        </ul>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-key"></i> Current Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="current_password" class="form-control" required>
                        <span class="toggle-password"><i class="fas fa-eye"></i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="new_password" class="form-control" id="new_password_input" required>
                        <span class="toggle-password"><i class="fas fa-eye"></i></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> Confirm New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="confirm_password" class="form-control" id="confirm_password_input" required>
                        <span class="toggle-password"><i class="fas fa-eye"></i></span>
                    </div>
                </div>
                <button type="submit" class="btn btn-orange"><i class="fas fa-check"></i> Update Password</button>
            </form>
        </div>
    </div>
<?php }

function render_addresses_section($addresses) { ?>
    <div class="profile-content-box">
        <div class="box-header">
            <h3><i class="fas fa-map-marker-alt"></i> My Addresses</h3>
            <button class="btn btn-sm btn-orange" data-bs-toggle="modal" data-bs-target="#addressModal">
                <i class="fas fa-plus"></i> Add New Address
            </button>
        </div>
        <div class="box-body">
            <?php if (empty($addresses)): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marked-alt"></i>
                    <h4>No Addresses Yet</h4>
                    <p>Add your first delivery address to get started</p>
                    <button class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addressModal">
                        <i class="fas fa-plus"></i> Add Address
                    </button>
                </div>
            <?php else: ?>
                <div class="addresses-grid">
                    <?php foreach($addresses as $address): 
                        $full_address = htmlspecialchars($address['street_address']) . ', ' . htmlspecialchars($address['barangay']) . ', ' . htmlspecialchars($address['city']) . ', ' . htmlspecialchars($address['province']) . ' ' . htmlspecialchars($address['postal_code']);
                    ?>
                    <div class="address-card-enhanced <?= $address['is_default'] ? 'default-address' : '' ?>">
                        <?php if($address['is_default']): ?>
                            <div class="default-badge"><i class="fas fa-star"></i> Default</div>
                        <?php endif; ?>
                        <div class="address-header">
                            <i class="fas fa-user-circle"></i>
                            <h4><?= htmlspecialchars($address['recipient_name']) ?></h4>
                        </div>
                        <div class="address-body">
                            <div class="address-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <p><?= $full_address ?></p>
                            </div>
                            <div class="address-detail">
                                <i class="fas fa-phone"></i>
                                <p><?= htmlspecialchars($address['phone']) ?></p>
                            </div>
                        </div>
                        <div class="address-actions">
                            <?php if(!$address['is_default']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="set_default">
                                    <input type="hidden" name="address_id" value="<?= $address['address_id'] ?>">
                                    <button type="submit" class="btn-action btn-set-default">
                                        <i class="fas fa-star"></i> Set Default
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn-action btn-edit edit-address-btn" data-bs-toggle="modal" data-bs-target="#addressModal" data-id="<?= $address['address_id'] ?>" data-recipient-name="<?= htmlspecialchars($address['recipient_name']) ?>" data-phone="<?= htmlspecialchars($address['phone']) ?>" data-region="<?= htmlspecialchars($address['region']) ?>" data-province="<?= htmlspecialchars($address['province']) ?>" data-city="<?= htmlspecialchars($address['city']) ?>" data-barangay="<?= htmlspecialchars($address['barangay']) ?>" data-postal-code="<?= htmlspecialchars($address['postal_code']) ?>" data-street-address="<?= htmlspecialchars($address['street_address']) ?>" data-default="<?= $address['is_default'] ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" class="d-inline remove-address-form">
                                <input type="hidden" name="action" value="delete_address">
                                <input type="hidden" name="address_id" value="<?= $address['address_id'] ?>">
                                <button type="submit" class="btn-action btn-delete">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php }

function render_orders_section($orders) { 
    $tracking_steps = ['Order Placed', 'Processing', 'Shipped', 'Delivered'];
?>
    <div class="profile-content-box">
        <div class="box-header">
            <h3><i class="fas fa-shopping-bag"></i> My Orders</h3>
        </div>
        <div class="box-body">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h4>No Orders Yet</h4>
                    <p>Start shopping and your orders will appear here</p>
                    <a href="products.php" class="btn btn-orange"><i class="fas fa-store"></i> Browse Products</a>
                </div>
            <?php else: ?>
                <?php foreach($orders as $order): 
                    $payment_status = 'Pending Payment';
                    $payment_status_class = 'status-warning';
                    if ($order['payment_method'] === 'Cash on Delivery') {
                        $payment_status = 'COD';
                        $payment_status_class = 'status-info';
                    } elseif (!empty($order['transaction_id']) && $order['transaction_id'] !== 'PENDING_NULL') {
                        $payment_status = 'Paid';
                        $payment_status_class = 'status-success';
                    }
                    $completed_statuses = array_column($order['history'], 'status');
                    $current_status_index = -1;
                    foreach($tracking_steps as $i => $step) {
                        if (in_array($step, $completed_statuses)) {
                            $current_status_index = $i;
                        }
                    }
                    $can_cancel = in_array($order['status'], ['Order Placed', 'Processing']);
                    $can_return = $order['status'] === 'Delivered';
                ?>
                <div class="order-card-enhanced">
                    <div class="order-header-enhanced">
                        <div class="order-id-section">
                            <span class="order-label">Order ID</span>
                            <span class="order-number">#<?= htmlspecialchars($order['order_id']) ?></span>
                        </div>
                        <div class="order-date-section">
                            <i class="fas fa-calendar"></i>
                            <?= date('M j, Y', strtotime($order['order_date'])) ?>
                        </div>
                        <div class="order-status-badge status-<?= strtolower($order['status']) ?>">
                            <i class="fas fa-circle"></i> <?= htmlspecialchars($order['status']) ?>
                        </div>
                    </div>
                    
                    <div class="order-tracking-enhanced">
                        <div class="tracking-progress">
                            <?php foreach ($tracking_steps as $index => $step): 
                                $is_completed = $index <= $current_status_index;
                                $is_active = $index === $current_status_index;
                                $history_entry = null;
                                foreach($order['history'] as $h) { if ($h['status'] === $step) { $history_entry = $h; break; } }
                            ?>
                            <div class="tracking-step <?= $is_completed ? 'completed' : '' ?> <?= $is_active ? 'active' : '' ?>">
                                <div class="step-marker">
                                    <?php if($is_completed): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        <span><?= $index + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="step-content">
                                    <h5><?= $step ?></h5>
                                    <?php if ($is_completed && $history_entry): ?>
                                        <p class="step-date"><?= date('M d, g:i A', strtotime($history_entry['created_at'])) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if($index < count($tracking_steps) - 1): ?>
                                    <div class="step-line <?= $is_completed ? 'completed' : '' ?>"></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="order-items-section">
                        <?php foreach($order['items'] as $item): 
                            $item_category_type = 'equipment';
                            if (isset($item['parent_category_name']) && stripos($item['parent_category_name'], 'supplement') !== false) {
                                $item_category_type = 'supplement';
                            }
                            $item_status_class = 'status-default';
                            if ($item['status'] === 'Cancellation Requested' || $item['status'] === 'Return Requested') $item_status_class = 'status-warning';
                            if ($item['status'] === 'Cancelled' || $item['status'] === 'Returned') $item_status_class = 'status-danger';
                        ?>
                        <div class="order-item-enhanced">
                            <div class="item-image">
                                <img src="uploads/images/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                            </div>
                            <div class="item-details-enhanced">
                                <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                <p class="item-price">₱<?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></p>
                                <span class="item-status <?= $item_status_class ?>"><?= htmlspecialchars($item['status']) ?></span>
                            </div>
                            <div class="item-actions-enhanced">
                                <div class="item-total">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                                
                                <?php if ($can_cancel && $item['status'] === 'Confirmed'): ?>
                                    <button type="button" class="btn-item-action btn-cancel cancel-item-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#cancelItemModal"
                                        data-item-id="<?= $item['id'] ?>"
                                        data-item-name="<?= htmlspecialchars($item['product_name']) ?>">
                                        <i class="fas fa-times-circle"></i> Cancel
                                    </button>
                                <?php elseif ($can_return && $item['status'] === 'Confirmed'): ?>
                                    <button type="button" class="btn-item-action btn-return return-item-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#returnRequestModal"
                                            data-item-id="<?= $item['id'] ?>"
                                            data-item-name="<?= htmlspecialchars($item['product_name']) ?>"
                                            data-item-quantity="<?= $item['quantity'] ?>"
                                            data-item-category="<?= $item_category_type ?>">
                                        <i class="fas fa-undo"></i> Return
                                    </button>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-footer-enhanced">
                        <div class="payment-details">
                            <div class="payment-row">
                                <span><i class="fas fa-credit-card"></i> Payment Method</span>
                                <strong><?= htmlspecialchars($order['payment_method']) ?></strong>
                            </div>
                            <div class="payment-row">
                                <span><i class="fas fa-info-circle"></i> Payment Status</span>
                                <span class="payment-badge <?= $payment_status_class ?>"><?= $payment_status ?></span>
                            </div>
                            <?php if (!empty($order['transaction_id']) && $order['transaction_id'] !== 'PENDING_NULL'): ?>
                            <div class="payment-row">
                                <span><i class="fas fa-hashtag"></i> Transaction ID</span>
                                <code><?= htmlspecialchars($order['transaction_id']) ?></code>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="order-total-section">
                            <span>Total Amount</span>
                            <h3>₱<?= number_format($order['total_amount'], 2) ?></h3>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php }

function render_returns_section($returns) { ?>
    <div class="profile-content-box">
        <div class="box-header">
            <h3><i class="fas fa-undo-alt"></i> My Returns</h3>
        </div>
        <div class="box-body">
            <?php if (empty($returns)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h4>No Return Requests</h4>
                    <p>You haven't requested any returns yet</p>
                </div>
            <?php else: ?>
                <div class="returns-grid">
                    <?php foreach($returns as $return): 
                        $status_class = 'status-default';
                        if ($return['status'] === 'Pending') $status_class = 'status-warning';
                        if ($return['status'] === 'Approved' || $return['status'] === 'Item Received') $status_class = 'status-info';
                        if ($return['status'] === 'Refunded') $status_class = 'status-success';
                        if ($return['status'] === 'Rejected') $status_class = 'status-danger';
                    ?>
                    <div class="return-card-enhanced">
                        <div class="return-header">
                            <div>
                                <span class="return-id">Return #<?= htmlspecialchars($return['return_id']) ?></span>
                                <span class="order-ref">Order #<?= htmlspecialchars($return['order_id']) ?></span>
                            </div>
                            <span class="return-status <?= $status_class ?>"><?= htmlspecialchars($return['status']) ?></span>
                        </div>
                        <div class="return-body">
                            <div class="return-product">
                                <img src="uploads/images/<?= htmlspecialchars($return['image']) ?>" alt="Product">
                                <div class="return-product-info">
                                    <h4><?= htmlspecialchars($return['product_name']) ?></h4>
                                    <p><i class="fas fa-cubes"></i> Quantity: <?= htmlspecialchars($return['quantity']) ?></p>
                                    <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars($return['reason']) ?></p>
                                    <p class="return-date"><i class="fas fa-clock"></i> <?= date('M j, Y, g:i A', strtotime($return['requested_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php if(!empty($return['admin_remarks'])): ?>
                        <div class="return-footer">
                            <div class="admin-remarks">
                                <i class="fas fa-comment-dots"></i>
                                <div>
                                    <strong>Admin Remarks:</strong>
                                    <p><?= htmlspecialchars($return['admin_remarks']) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php }

function render_cancellations_section($cancellations) { ?>
     <div class="profile-content-box">
        <div class="box-header">
            <h3><i class="fas fa-ban"></i> My Cancellations</h3>
        </div>
        <div class="box-body">
            <?php if (empty($cancellations)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>No Cancellations</h4>
                    <p>You have no cancellation requests</p>
                </div>
            <?php else: ?>
                <div class="cancellations-grid">
                    <?php foreach($cancellations as $item): 
                        $status_class = 'status-default';
                        if ($item['item_status'] === 'Cancellation Requested') $status_class = 'status-warning';
                        if ($item['item_status'] === 'Cancelled') $status_class = 'status-danger';
                    ?>
                    <div class="cancellation-card-enhanced">
                        <div class="cancellation-header">
                            <div>
                                <span class="order-ref">Order #<?= htmlspecialchars($item['order_id']) ?></span>
                                <span class="order-date"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($item['order_date'])) ?></span>
                            </div>
                            <span class="cancellation-status <?= $status_class ?>"><?= htmlspecialchars($item['item_status']) ?></span>
                        </div>
                        <div class="cancellation-body">
                            <img src="uploads/images/<?= htmlspecialchars($item['image']) ?>" alt="Product">
                            <div class="cancellation-info">
                                <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                <p><i class="fas fa-cubes"></i> Quantity: <?= htmlspecialchars($item['quantity']) ?></p>
                            </div>
                            <div class="cancellation-amount">
                                <span>Amount</span>
                                <h4>₱<?= number_format($item['quantity'] * $item['price'], 2) ?></h4>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php }


// --- DATA FETCHING & PAGE SETUP ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$user_id]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
if (isset($_SESSION['success'])) { $success = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }
include __DIR__ . '/../../includes/header.php';
?>

<style>
    .password-req-list { 
        list-style-type: none; 
        padding-left: 1.2rem; 
        margin-top: 10px;
        color: #e0e0e0;
    }
    .password-req-list li { 
        transition: all 0.2s ease; 
        margin-bottom: 5px;
    }
    .password-req-list li::before { 
        content: '\f00d'; /* FontAwesome 'x' icon */
        font-family: 'Font Awesome 5 Free'; 
        font-weight: 900; 
        margin-right: 10px; 
        color: #dc3545; /* Red */
    }
    .password-req-list li.valid { 
        color: #28a745; /* Green text */
    }
    .password-req-list li.valid::before {
        content: '\f00c'; /* FontAwesome 'check' icon */
        color: #28a745; /* Green */
    }
</style>

<div class="container my-5 profile-layout" style="padding-top: 80px;">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="profile-nav-col text-center">
                <div>
                    <form id="profile-picture-form" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_picture">
                        <input type="file" name="profile_picture" id="profile-upload" class="d-none" accept="image/*">
                        <div class="profile-picture-container" onclick="document.getElementById('profile-upload').click();">
                            <img src="uploads/profile_pictures/<?= htmlspecialchars($user['profile_picture'] ?? 'default.png') ?>" alt="Profile Picture" class="profile-picture" onerror="this.src='uploads/profile_pictures/default.png'">
                            <div class="profile-picture-overlay">
                                <i class="fas fa-camera fa-2x"></i>
                            </div>
                        </div>
                    </form>
                    <h5 class="mt-2 mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <p class="text-muted small"><?= htmlspecialchars($user['email']) ?></p>
                    <div class="profile-nav-links text-start">
                        <a class="nav-heading-toggle" data-bs-toggle="collapse" href="#manageAccountCollapse" role="button" aria-expanded="true" aria-controls="manageAccountCollapse">
                            <i class="fas fa-user-cog"></i> Manage My Account
                        </a>
                        <div class="collapse show" id="manageAccountCollapse">
                            <ul class="list-unstyled">
                                <li><a href="profile.php?page=details" class="<?= $page === 'details' ? 'active' : '' ?>"><i class="fas fa-user"></i> My Profile</a></li>
                                <li><a href="profile.php?page=addresses" class="<?= $page === 'addresses' ? 'active' : '' ?>"><i class="fas fa-map-marker-alt"></i> Address Book</a></li>
                                <li><a href="profile.php?page=password" class="<?= $page === 'password' ? 'active' : '' ?>"><i class="fas fa-lock"></i> Change Password</a></li>
                            </ul>
                        </div>
                        <a class="nav-heading-toggle collapsed" data-bs-toggle="collapse" href="#myOrdersCollapse" role="button" aria-expanded="false" aria-controls="myOrdersCollapse">
                            <i class="fas fa-shopping-bag"></i> My Orders
                        </a>
                        <div class="collapse <?= in_array($page, ['orders', 'returns', 'cancellations']) ? 'show' : '' ?>" id="myOrdersCollapse">
                            <ul class="list-unstyled">
                                <li><a href="profile.php?page=orders" class="<?= $page === 'orders' ? 'active' : '' ?>"><i class="fas fa-box"></i> My Orders</a></li>
                                <li><a href="profile.php?page=returns" class="<?= $page === 'returns' ? 'active' : '' ?>"><i class="fas fa-undo"></i> My Returns</a></li>
                                <li><a href="profile.php?page=cancellations" class="<?= $page === 'cancellations' ? 'active' : '' ?>"><i class="fas fa-ban"></i> My Cancellations</a></li>
                            </ul>
                        </div>
                        <a class="nav-heading-toggle collapsed" data-bs-toggle="collapse" href="#myStuffCollapse" role="button" aria-expanded="false" aria-controls="myStuffCollapse">
                            <i class="fas fa-heart"></i> My Stuff
                        </a>
                        <div class="collapse" id="myStuffCollapse">
                            <ul class="list-unstyled">
                                <li><a href="my_reviews.php"><i class="fas fa-star"></i> My Reviews</a></li>
                                <li><a href="wishlist.php"><i class="fas fa-heart"></i> My Wishlist</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="logout-link text-start mt-auto">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        <div class="col-lg-9" id="profile-content-container">
            <?php
            if ($success): echo "<div class='alert alert-success'>".htmlspecialchars($success)."</div>"; endif;
            if ($error): echo "<div class='alert alert-danger'>".htmlspecialchars($error)."</div>"; endif;
            switch ($page) {
                case 'password': render_password_section(); break;
                case 'addresses': 
                    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC");
                    $stmt->execute([$user_id]);
                    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    render_addresses_section($addresses);
                    break;
                case 'orders':
                    $stmt_orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
                    $stmt_orders->execute([$user_id]);
                    $orders_result = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
                    $orders = [];
                    $order_ids = array_column($orders_result, 'order_id');
                    if (!empty($order_ids)) {
                        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                        $stmt_items = $pdo->prepare("
                            SELECT
                                oi.*, p.image,
                                COALESCE(parent_cat.name, cat.name) as parent_category_name
                            FROM order_items oi
                            JOIN products p ON oi.product_id = p.id
                            LEFT JOIN categories cat ON p.category_id = cat.id
                            LEFT JOIN categories parent_cat ON cat.parent_id = parent_cat.id
                            WHERE oi.order_id IN ($placeholders)
                        ");
                        $stmt_items->execute($order_ids);
                        $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                        
                        $stmt_history = $pdo->prepare("SELECT * FROM order_status_history WHERE order_id IN ($placeholders) ORDER BY created_at ASC");
                        $stmt_history->execute($order_ids);
                        $all_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

                        $items_by_order = [];
                        foreach ($all_items as $item) { $items_by_order[$item['order_id']][] = $item; }

                        $history_by_order = [];
                        foreach($all_history as $h) { $history_by_order[$h['order_id']][] = $h; }

                        foreach ($orders_result as $order) {
                            $order['items'] = $items_by_order[$order['order_id']] ?? [];
                            $order['history'] = $history_by_order[$order['order_id']] ?? [];
                            $orders[] = $order;
                        }
                    }
                    render_orders_section($orders);
                    break;
                case 'returns':
                    $stmt_returns = $pdo->prepare("
                        SELECT r.*, oi.product_name, p.image
                        FROM return_requests r
                        JOIN order_items oi ON r.order_item_id = oi.id
                        JOIN products p ON oi.product_id = p.id
                        WHERE r.user_id = ?
                        ORDER BY r.requested_at DESC
                    ");
                    $stmt_returns->execute([$user_id]);
                    $returns = $stmt_returns->fetchAll(PDO::FETCH_ASSOC);
                    render_returns_section($returns);
                    break;
                case 'cancellations':
                    $stmt_cancellations = $pdo->prepare("
                        SELECT oi.status as item_status, oi.product_name, oi.quantity, oi.price, o.order_id, o.order_date, p.image
                        FROM order_items oi
                        JOIN orders o ON oi.order_id = o.order_id
                        JOIN products p ON oi.product_id = p.id
                        WHERE o.user_id = ? AND (oi.status = 'Cancellation Requested' OR oi.status = 'Cancelled')
                        ORDER BY o.order_date DESC
                    ");
                    $stmt_cancellations->execute([$user_id]);
                    $cancellations = $stmt_cancellations->fetchAll(PDO::FETCH_ASSOC);
                    render_cancellations_section($cancellations);
                    break;
                case 'details': default: render_details_section($user); break;
            }
            ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="manage_address">
                <input type="hidden" name="address_id" id="modal_address_id">
                <input type="hidden" name="region_text" id="modal_region_text">
                <input type="hidden" name="province_text" id="modal_province_text">
                <input type="hidden" name="city_text" id="modal_city_text">
                <input type="hidden" name="barangay_text" id="modal_barangay_text">
                <div class="modal-header">
                    <h5 class="modal-title" id="addressModalLabel"><i class="fas fa-map-marker-alt"></i> Manage Address</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal_recipient_name" class="form-label"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" class="form-control" id="modal_recipient_name" name="recipient_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_phone" class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" class="form-control" id="modal_phone" name="phone" required pattern="\d{11}" title="Phone number must be exactly 11 digits." maxlength="11">
                        </div>
                        <div class="col-md-6">
                            <label for="modal_region" class="form-label">Region</label>
                            <select class="form-select" id="modal_region" name="region" required></select>
                        </div>
                        <div class="col-md-6" id="modal_province_wrapper">
                            <label for="modal_province" class="form-label">Province</label>
                            <select class="form-select" id="modal_province" name="province" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_city" class="form-label">City/Municipality</label>
                            <select class="form-select" id="modal_city" name="city" required></select>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_barangay" class="form-label">Barangay</label>
                            <select class="form-select" id="modal_barangay" name="barangay" required></select>
                        </div>
                        <div class="col-12">
                            <label for="modal_street_address" class="form-label"><i class="fas fa-home"></i> Street Address, Building, House No.</label>
                            <input type="text" class="form-control" id="modal_street_address" name="street_address" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modal_postal_code" class="form-label"><i class="fas fa-mail-bulk"></i> Postal Code</label>
                            <input type="text" class="form-control" id="modal_postal_code" name="postal_code" pattern="\d{4}" title="Postal code must be 4 digits." maxlength="4">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="modal_is_default">
                                <label class="form-check-label" for="modal_is_default">
                                    <i class="fas fa-star"></i> Set as default address
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
                    <button type="submit" class="btn btn-orange"><i class="fas fa-save"></i> Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="returnRequestModal" tabindex="-1" aria-labelledby="returnRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="submit_return_request.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="returnRequestModalLabel"><i class="fas fa-undo"></i> Request a Return</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are requesting a return for:</p>
                    <div class="alert alert-secondary">
                        <strong id="modal_item_name"></strong>
                    </div>
                    <input type="hidden" name="order_item_id" id="modal_order_item_id">
                    
                    <div class="mb-3">
                        <label for="return_quantity" class="form-label"><i class="fas fa-cubes"></i> Quantity to Return</label>
                        <input type="number" name="quantity" id="return_quantity" class="form-control" min="1" required>
                    </div>

                    <div class="mb-3" id="reason_wrapper">
                        <label for="reason" class="form-label"><i class="fas fa-info-circle"></i> Reason for Return</label>
                        <select name="reason" id="reason" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label for="proof_video" class="form-label"><i class="fas fa-video"></i> Upload Proof Video (Required)</label>
                        <input type="file" name="proof_video" id="proof_video" class="form-control" accept="video/mp4,video/quicktime" required>
                        <div class="form-text text-muted">Max 20MB. Formats: MP4, MOV</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="btn btn-orange"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelItemModal" tabindex="-1" aria-labelledby="cancelItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="request_cancellation.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelItemModalLabel">Cancel Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="color: white;">
                    <p>You are requesting to cancel:</p>
                    <div class="alert alert-secondary">
                        <strong id="cancel_item_name"></strong>
                    </div>
                    <input type="hidden" name="order_item_id" id="cancel_order_item_id">
                    
                    <div class="mb-3">
                        <label for="cancellation_reason" class="form-label">Reason for Cancellation</label>
                        <select name="cancellation_reason" id="cancellation_reason" class="form-select" required>
                            <option value="" selected disabled>Select a reason...</option>
                            <option value="Changed my mind">Changed my mind</option>
                            <option value="Ordered by mistake">Ordered by mistake</option>
                            <option value="Found a better deal">Found a better deal</option>
                            <option value="Item will not arrive on time">Item will not arrive on time</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Submit Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Details Toggle
    const editDetailsBtn = document.getElementById('edit-details-btn');
    if (editDetailsBtn) {
        const cancelEditBtn = document.getElementById('cancel-edit-btn');
        const detailsView = document.getElementById('details-view');
        const detailsEditForm = document.getElementById('details-edit-form');
        editDetailsBtn.addEventListener('click', () => {
            detailsView.classList.add('d-none');
            detailsEditForm.classList.remove('d-none');
            editDetailsBtn.classList.add('d-none');
        });
        cancelEditBtn.addEventListener('click', () => {
            detailsView.classList.remove('d-none');
            detailsEditForm.classList.add('d-none');
            editDetailsBtn.classList.remove('d-none');
        });
    }

    // Profile Picture Upload
    const profileUpload = document.getElementById('profile-upload');
    if (profileUpload) {
        profileUpload.addEventListener('change', () => document.getElementById('profile-picture-form').submit());
    }

    // Password Toggle
    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // --- **** NEW: Password Validation Feedback **** ---
    const newPassInput = document.getElementById('new_password_input');
    const confirmPassInput = document.getElementById('confirm_password_input');
    const reqs = {
        length: document.getElementById('req-length'),
        case: document.getElementById('req-case'),
        number: document.getElementById('req-number'),
        special: document.getElementById('req-special'),
        match: document.getElementById('req-match')
    };

    if (newPassInput && confirmPassInput && reqs.length) {
        const validate = () => {
            const pass = newPassInput.value;
            const confirmPass = confirmPassInput.value;

            // Regex checks
            const hasLength = pass.length >= 8;
            const hasCase = /[A-Z]/.test(pass);
            const hasNumber = /[0-9]/.test(pass);
            const hasSpecial = /[\W_]/.test(pass); // \W is non-word, _ is underscore
            const hasMatch = pass === confirmPass && pass.length > 0;

            // Update UI
            if (reqs.length) reqs.length.classList.toggle('valid', hasLength);
            if (reqs.case) reqs.case.classList.toggle('valid', hasCase);
            if (reqs.number) reqs.number.classList.toggle('valid', hasNumber);
            if (reqs.special) reqs.special.classList.toggle('valid', hasSpecial);
            if (reqs.match) reqs.match.classList.toggle('valid', hasMatch);
        };

        newPassInput.addEventListener('input', validate);
        confirmPassInput.addEventListener('input', validate);
    }
    // --- **** END NEW SCRIPT **** ---


    // Return Request Modal
    const returnModalEl = document.getElementById('returnRequestModal');
    if (returnModalEl) {
        const itemNameEl = document.getElementById('modal_item_name');
        const itemIdInput = document.getElementById('modal_order_item_id');
        const quantityInput = document.getElementById('return_quantity');
        const reasonWrapper = document.getElementById('reason_wrapper');
        const reasonSelect = document.getElementById('reason');

        const reasons = {
            supplement: ["Wrong item received", "Near expiry date", "Damaged or Defective Product"],
            equipment: ["Damaged during delivery", "Wrong item delivered", "Defective or Malfunctioning Unit"]
        };

        returnModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const itemName = button.getAttribute('data-item-name');
            const itemId = button.getAttribute('data-item-id');
            const itemQuantity = button.getAttribute('data-item-quantity');
            const itemCategory = button.getAttribute('data-item-category');

            itemNameEl.textContent = itemName;
            itemIdInput.value = itemId;
            quantityInput.value = itemQuantity;
            quantityInput.max = itemQuantity;
            
            reasonSelect.innerHTML = '';
            reasonWrapper.style.display = 'none';

            if (itemCategory && reasons[itemCategory]) {
                let optionsHtml = '<option value="" selected disabled>Select a reason...</option>';
                reasons[itemCategory].forEach(reason => {
                    optionsHtml += `<option value="${reason}">${reason}</option>`;
                });
                reasonSelect.innerHTML = optionsHtml;
                reasonWrapper.style.display = 'block';
            }
        });
    }

    // --- SCRIPT FOR CANCELLATION MODAL ---
    const cancelModalEl = document.getElementById('cancelItemModal');
    if (cancelModalEl) {
        cancelModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const itemName = button.getAttribute('data-item-name');
            const itemId = button.getAttribute('data-item-id');

            const modalTitle = cancelModalEl.querySelector('.modal-title');
            const itemNameEl = cancelModalEl.querySelector('#cancel_item_name');
            const itemIdInput = cancelModalEl.querySelector('#cancel_order_item_id');
            const reasonSelect = cancelModalEl.querySelector('#cancellation_reason');
            
            // Reset form
            modalTitle.textContent = 'Cancel Item'; // You can customize this
            itemNameEl.textContent = itemName;
            itemIdInput.value = itemId;
            reasonSelect.selectedIndex = 0;
        });
    }


    // Address Modal & API
    const addressModalEl = document.getElementById('addressModal');
    if(addressModalEl) {
        const form = addressModalEl.querySelector('form');
        const modalTitle = addressModalEl.querySelector('.modal-title');
        const fields = {
            id: form.querySelector('#modal_address_id'),
            recipient_name: form.querySelector('#modal_recipient_name'),
            phone: form.querySelector('#modal_phone'),
            region: form.querySelector('#modal_region'),
            province: form.querySelector('#modal_province'),
            city: form.querySelector('#modal_city'),
            barangay: form.querySelector('#modal_barangay'),
            street_address: form.querySelector('#modal_street_address'),
            postal_code: form.querySelector('#modal_postal_code'),
            is_default: form.querySelector('#modal_is_default')
        };
        const textFields = {
            region: form.querySelector('#modal_region_text'),
            province: form.querySelector('#modal_province_text'),
            city: form.querySelector('#modal_city_text'),
            barangay: form.querySelector('#modal_barangay_text')
        };

        // --- Address Phone/Postal Validation Script ---
        // Filter phone input for 11 digits
        fields.phone.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, ''); // Remove non-digits
            if (value.length > 11) {
                value = value.substring(0, 11); // Enforce 11-digit max
            }
            e.target.value = value;
        });

        // Filter postal code input for 4 digits
        fields.postal_code.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d]/g, ''); // Remove non-digits
            if (value.length > 4) {
                value = value.substring(0, 4); // Enforce 4-digit max
            }
            e.target.value = value;
        });
        
        const api = {
            fetchRegions: () => fetch('https://psgc.gitlab.io/api/regions/').then(res => res.json()),
            fetchProvinces: code => fetch(`https://psgc.gitlab.io/api/regions/${code}/provinces/`).then(res => res.json()),
            fetchCitiesFromRegion: code => fetch(`https://psgc.gitlab.io/api/regions/${code}/cities-municipalities/`).then(res => res.json()),
            fetchCities: code => fetch(`https://psgc.gitlab.io/api/provinces/${code}/cities-municipalities/`).then(res => res.json()),
            fetchBarangays: code => fetch(`https://psgc.gitlab.io/api/cities-municipalities/${code}/barangays/`).then(res => res.json())
        };
        
        const populateSelect = (el, data) => {
            el.innerHTML = '<option value="" selected disabled>Select...</option>';
            data.sort((a, b) => a.name.localeCompare(b.name)).forEach(item => el.add(new Option(item.name, item.code)));
        };
        
        const updateTextField = (selectEl, hiddenEl) => {
            hiddenEl.value = selectEl.selectedIndex > 0 ? selectEl.options[selectEl.selectedIndex].text : '';
        };
        
        fields.region.addEventListener('change', () => {
            updateTextField(fields.region, textFields.region);
            const provinceWrapper = document.getElementById('modal_province_wrapper');
            ['province', 'city', 'barangay'].forEach(key => {
                fields[key].innerHTML = '';
                if(textFields[key]) textFields[key].value = '';
            });
            if (fields.region.value === '130000000') {
                provinceWrapper.style.display = 'none';
                fields.province.required = false;
                api.fetchCitiesFromRegion(fields.region.value).then(data => populateSelect(fields.city, data));
            } else {
                provinceWrapper.style.display = 'block';
                fields.province.required = true;
                if(fields.region.value) api.fetchProvinces(fields.region.value).then(data => populateSelect(fields.province, data));
            }
        });
        
        fields.province.addEventListener('change', () => {
            updateTextField(fields.province, textFields.province);
            fields.city.innerHTML = '';
            fields.barangay.innerHTML = '';
            if(textFields.city) textFields.city.value = '';
            if(textFields.barangay) textFields.barangay.value = '';
            if(fields.province.value) api.fetchCities(fields.province.value).then(data => populateSelect(fields.city, data));
        });
        
        fields.city.addEventListener('change', () => {
            updateTextField(fields.city, textFields.city);
            fields.barangay.innerHTML = '';
            if(textFields.barangay) textFields.barangay.value = '';
            if(fields.city.value) api.fetchBarangays(fields.city.value).then(data => populateSelect(fields.barangay, data));
        });
        
        fields.barangay.addEventListener('change', () => updateTextField(fields.barangay, textFields.barangay));
        
        addressModalEl.addEventListener('show.bs.modal', async function (event) {
            form.reset();
            const provinceWrapper = document.getElementById('modal_province_wrapper');
            const button = event.relatedTarget;
            const isEdit = button && button.classList.contains('edit-address-btn');
            modalTitle.innerHTML = isEdit ? '<i class="fas fa-edit"></i> Edit Address' : '<i class="fas fa-plus"></i> Add New Address';
            
            const regions = await api.fetchRegions();
            populateSelect(fields.region, regions);
            
            if (isEdit) {
                const data = button.dataset;
                fields.id.value = data.id;
                fields.recipient_name.value = data.recipientName;
                fields.phone.value = data.phone;
                fields.street_address.value = data.streetAddress;
                fields.postal_code.value = data.postalCode;
                fields.is_default.checked = data.default == '1';
                
                const region = data.region ? regions.find(r => r.name.toUpperCase() === data.region.toUpperCase()) : null;
                if (region) {
                    fields.region.value = region.code;
                    updateTextField(fields.region, textFields.region);
                    
                    if(region.code === '130000000'){
                        provinceWrapper.style.display = 'none';
                        fields.province.required = false;
                        const cities = await api.fetchCitiesFromRegion(region.code);
                        populateSelect(fields.city, cities);
                        const city = data.city ? cities.find(c => c.name.toUpperCase() === data.city.toUpperCase()) : null;
                        if (city) {
                            fields.city.value = city.code;
                            updateTextField(fields.city, textFields.city);
                            const barangays = await api.fetchBarangays(city.code);
                            populateSelect(fields.barangay, barangays);
                            const barangay = data.barangay ? barangays.find(b => b.name.toUpperCase() === data.barangay.toUpperCase()) : null;
                            if (barangay) {
                                fields.barangay.value = barangay.code;
                                updateTextField(fields.barangay, textFields.barangay);
                            }
                        }
                    } else {
                        provinceWrapper.style.display = 'block';
                        fields.province.required = true;
                        const provinces = await api.fetchProvinces(region.code);
                        populateSelect(fields.province, provinces);
                        const province = data.province ? provinces.find(p => p.name.toUpperCase() === data.province.toUpperCase()) : null;
                        if(province) {
                            fields.province.value = province.code;
                            updateTextField(fields.province, textFields.province);
                            const cities = await api.fetchCities(province.code);
                            populateSelect(fields.city, cities);
                            const city = data.city ? cities.find(c => c.name.toUpperCase() === data.city.toUpperCase()) : null;
                            if(city) {
                                fields.city.value = city.code;
                                updateTextField(fields.city, textFields.city);
                                const barangays = await api.fetchBarangays(city.code);
                                populateSelect(fields.barangay, barangays);
                                const barangay = data.barangay ? barangays.find(b => b.name.toUpperCase() === data.barangay.toUpperCase()) : null;
                                if(barangay) {
                                    fields.barangay.value = barangay.code;
                                    updateTextField(fields.barangay, textFields.barangay);
                                }
                            }
                        }
                    }
                }
            } else {
                provinceWrapper.style.display = 'block';
                fields.province.required = true;
                fields.province.innerHTML = '';
                fields.city.innerHTML = '';
                fields.barangay.innerHTML = '';
            }
        });
    }

    // Remove Address Confirmation
    document.querySelectorAll('.remove-address-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this address?')) {
                e.preventDefault();
            }
        });
    });
});
</script>