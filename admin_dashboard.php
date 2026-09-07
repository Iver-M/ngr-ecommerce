<?php
session_start();
require "config/db.php";

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Helper function to log admin actions
function log_admin_action($action, $details = null) {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['user_name'] ?? 'Admin';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $action, $details, $ip_address]);
}

/**
 * Helper function to log detailed data changes to the audit trail.
 *
 * @param string $action 'INSERT', 'UPDATE', 'DELETE'
 * @param string $table_name The name of the table being changed
 * @param int $record_id The ID of the row being changed
 * @param array $changes An associative array ['field_name' => ['old' => old_val, 'new' => new_val]]
 */
function log_audit_trail($action, $table_name, $record_id, $changes = []) {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['user_name'] ?? ($_SESSION['admin_email'] ?? 'Admin'); // Use email if name not set

    if (empty($changes) || $record_id === null) {
        return; // Don't log if no changes or no record ID
    }

    $stmt = $pdo->prepare("
        INSERT INTO audit_log
            (admin_user_id, admin_username, action, table_name, record_id, field_name, old_value, new_value)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        foreach ($changes as $field_name => $values) {
            // Ensure values array structure is correct
            $old_val_raw = $values['old'] ?? null;
            $new_val_raw = $values['new'] ?? null;

            $old_val = is_array($old_val_raw) || is_object($old_val_raw) ? json_encode($old_val_raw) : $old_val_raw;
            $new_val = is_array($new_val_raw) || is_object($new_val_raw) ? json_encode($new_val_raw) : $new_val_raw;

            // Convert nulls to actual NULL for DB consistency
            $old_val = ($old_val === null) ? null : (string)$old_val;
            $new_val = ($new_val === null) ? null : (string)$new_val;

            // Don't log if nothing actually changed
            if ($old_val !== $new_val) {
                 $stmt->execute([
                    $user_id,
                    $username,
                    $action,
                    $table_name,
                    $record_id,
                    $field_name,
                    $old_val,
                    $new_val
                ]);
            }
        }
    } catch (Exception $e) {
        // Optional: Log this error to a file, but don't stop the user's action
        error_log("Failed to write to audit_log: " . $e->getMessage());
    }
}

// ===============================
// MAIN ACTION HANDLER
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

// --- Action: Update Page Content ---
    if ($_POST['action'] === 'update_page') {
        $page_id = $_POST['page_id'];
        $content = $_POST['content'];
        $title = $_POST['title'];

        // Get old data for audit
        $stmt_old = $pdo->prepare("SELECT title, content FROM pages WHERE id = ?");
        $stmt_old->execute([$page_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE pages SET title = ?, content = ? WHERE id = ?");
        if ($stmt->execute([$title, $content, $page_id])) {
            $_SESSION['admin_success'] = "Page content updated successfully.";
            log_admin_action('CMS Page Update', "Updated content for page ID #$page_id.");
            // Log to audit trail
            $changes = [
                'title' => ['old' => $old_data['title'] ?? null, 'new' => $title],
                'content' => ['old' => $old_data['content'] ?? null, 'new' => $content]
            ];
            log_audit_trail('UPDATE', 'pages', $page_id, $changes);
        } else {
            $_SESSION['admin_error'] = "Failed to update page content.";
        }
        header("Location: admin_dashboard.php?view=pages");
        exit;
    }
    
    // --- Action: Add FAQ ---
    // --- Action: Add FAQ ---
    if ($_POST['action'] === 'add_faq') {
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $display_order = (int)$_POST['display_order'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, display_order, is_active) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$question, $answer, $display_order, $is_active])) {
            $faq_id = $pdo->lastInsertId();
            $_SESSION['admin_success'] = "New FAQ added successfully.";
            log_admin_action('FAQ Added', "Added FAQ: $question");
            // Log to audit trail
            $changes = [
                'question' => ['old' => null, 'new' => $question],
                'answer' => ['old' => null, 'new' => $answer],
                'display_order' => ['old' => null, 'new' => $display_order],
                'is_active' => ['old' => null, 'new' => $is_active]
            ];
            log_audit_trail('INSERT', 'faqs', $faq_id, $changes);
        } else {
            $_SESSION['admin_error'] = "Failed to add FAQ.";
        }
        header("Location: admin_dashboard.php?view=faqs");
        exit;
    }
  // --- Action: Update FAQ ---
    if ($_POST['action'] === 'update_faq') {
        $faq_id = $_POST['faq_id'];
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $display_order = (int)$_POST['display_order'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Get old data for audit
        $stmt_old = $pdo->prepare("SELECT question, answer, display_order, is_active FROM faqs WHERE id = ?");
        $stmt_old->execute([$faq_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE faqs SET question=?, answer=?, display_order=?, is_active=? WHERE id=?");
        if ($stmt->execute([$question, $answer, $display_order, $is_active, $faq_id])) {
            $_SESSION['admin_success'] = "FAQ updated successfully.";
            log_admin_action('FAQ Updated', "Updated FAQ ID #$faq_id.");
            // Log to audit trail
            $changes = [
                'question' => ['old' => $old_data['question'] ?? null, 'new' => $question],
                'answer' => ['old' => $old_data['answer'] ?? null, 'new' => $answer],
                'display_order' => ['old' => $old_data['display_order'] ?? null, 'new' => $display_order],
                'is_active' => ['old' => $old_data['is_active'] ?? null, 'new' => $is_active]
            ];
            log_audit_trail('UPDATE', 'faqs', $faq_id, $changes);
        } else {
            $_SESSION['admin_error'] = "Failed to update FAQ.";
        }
        header("Location: admin_dashboard.php?view=faqs");
        exit;
    }
   // --- Action: Delete FAQ ---
    if ($_POST['action'] === 'delete_faq') {
        $faq_id = $_POST['faq_id'];

        // Get old data for audit
        $stmt_old = $pdo->prepare("SELECT question FROM faqs WHERE id = ?");
        $stmt_old->execute([$faq_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
        if ($stmt->execute([$faq_id])) {
            $_SESSION['admin_success'] = "FAQ deleted successfully.";
            log_admin_action('FAQ Deleted', "Deleted FAQ ID #$faq_id.");
            // Log to audit trail
            $changes = ['<DELETED_FAQ>' => ['old' => $old_data['question'] ?? "ID $faq_id", 'new' => null]];
            log_audit_trail('DELETE', 'faqs', $faq_id, $changes);
        } else {
            $_SESSION['admin_error'] = "Failed to delete FAQ.";
        }
        header("Location: admin_dashboard.php?view=faqs");
        exit;
    }

    // --- Action: Add Promotion ---
   // --- Action: Add Promotion ---
    if ($_POST['action'] === 'add_promotion') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $link_url = trim($_POST['link_url']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = (int)$_POST['display_order'];

        $image_path = uploadImage('image', null, 'promotions/');

        if ($image_path) {
            $stmt = $pdo->prepare("INSERT INTO promotions (title, description, image_path, link_url, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $description, $image_path, $link_url, $is_active, $display_order])) {
                $promo_id = $pdo->lastInsertId();
                $_SESSION['admin_success'] = "New promotion added successfully.";
                log_admin_action('Promotion Added', "Added promotion: $title");
                // Log to audit trail
                $changes = [
                    'title' => ['old' => null, 'new' => $title],
                    'description' => ['old' => null, 'new' => $description],
                    'image_path' => ['old' => null, 'new' => $image_path],
                    'link_url' => ['old' => null, 'new' => $link_url],
                    'is_active' => ['old' => null, 'new' => $is_active],
                    'display_order' => ['old' => null, 'new' => $display_order]
                ];
                log_audit_trail('INSERT', 'promotions', $promo_id, $changes);
            } else {
                $_SESSION['admin_error'] = "Failed to add promotion.";
            }
        } else {
            $_SESSION['admin_error'] = "Failed to upload promotion image.";
        }
        header("Location: admin_dashboard.php?view=promotions");
        exit;
    }
    // --- Action: Update Promotion ---
  // --- Action: Update Promotion ---
    if ($_POST['action'] === 'update_promotion') {
        $promo_id = $_POST['promo_id'];
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $link_url = trim($_POST['link_url']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = (int)$_POST['display_order'];

        // Get old data for audit
        $stmt_old_data = $pdo->prepare("SELECT title, description, image_path, link_url, is_active, display_order FROM promotions WHERE id = ?");
        $stmt_old_data->execute([$promo_id]);
        $old_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);
        $old_image = $old_data['image_path'] ?? null;

        $image_path = uploadImage('image', $old_image, 'promotions/');

        $stmt = $pdo->prepare("UPDATE promotions SET title=?, description=?, image_path=?, link_url=?, is_active=?, display_order=? WHERE id=?");
        if ($stmt->execute([$title, $description, $image_path, $link_url, $is_active, $display_order, $promo_id])) {
            $_SESSION['admin_success'] = "Promotion updated successfully.";
            log_admin_action('Promotion Updated', "Updated promotion ID #$promo_id.");
            // Log to audit trail
            $changes = [
                'title' => ['old' => $old_data['title'], 'new' => $title],
                'description' => ['old' => $old_data['description'], 'new' => $description],
                'image_path' => ['old' => $old_data['image_path'], 'new' => $image_path],
                'link_url' => ['old' => $old_data['link_url'], 'new' => $link_url],
                'is_active' => ['old' => $old_data['is_active'], 'new' => $is_active],
                'display_order' => ['old' => $old_data['display_order'], 'new' => $display_order]
            ];
            log_audit_trail('UPDATE', 'promotions', $promo_id, $changes);
        } else {
            $_SESSION['admin_error'] = "Failed to update promotion.";
        }
        header("Location: admin_dashboard.php?view=promotions");
        exit;
    }

    // --- Action: Delete Promotion ---
  // --- Action: Delete Promotion ---
    if ($_POST['action'] === 'delete_promotion') {
        $promo_id = $_POST['promo_id'];

        // Get old data for audit
        $stmt_old = $pdo->prepare("SELECT title, image_path FROM promotions WHERE id = ?");
        $stmt_old->execute([$promo_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $image_to_delete = $old_data['image_path'] ?? null;

        if ($image_to_delete && file_exists('uploads/promotions/' . $image_to_delete)) {
            unlink('uploads/promotions/' . $image_to_delete);
        }

        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
        if ($stmt->execute([$promo_id])) {
            $_SESSION['admin_success'] = "Promotion deleted successfully.";
            log_admin_action('Promotion Deleted', "Deleted promotion ID #$promo_id.");
            // Log to audit trail
            $changes = ['<DELETED_PROMO>' => ['old' => $old_data['title'] ?? "ID $promo_id", 'new' => null]];
            log_audit_trail('DELETE', 'promotions', $promo_id, $changes);
        } else {
            $_SESSION['admin_error'] = "Failed to delete promotion.";
        }
        header("Location: admin_dashboard.php?view=promotions");
        exit;
    }
    // --- Action: Add Voucher ---
   // --- Action: Add Voucher ---
    if ($_POST['action'] === 'add_voucher') {
        $code = trim(strtoupper(filter_input(INPUT_POST, 'code', FILTER_SANITIZE_SPECIAL_CHARS)));
        $discount_type = $_POST['discount_type'] === 'percent' || $_POST['discount_type'] === 'fixed' ? $_POST['discount_type'] : null;
        $discount_value = filter_input(INPUT_POST, 'discount_value', FILTER_VALIDATE_FLOAT);
        $expiry_date_input = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_SPECIAL_CHARS);
        $expiry_date = !empty($expiry_date_input) ? $expiry_date_input : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $max_uses_customer = filter_input(INPUT_POST, 'max_uses_customer', FILTER_VALIDATE_INT);
        $max_uses_customer = ($max_uses_customer !== null && $max_uses_customer > 0) ? $max_uses_customer : 1;

        if ($code && $discount_type && $discount_value !== null) {
            try {
                // Assumes 'vouchers' table exists
                $stmt = $pdo->prepare("INSERT INTO vouchers (code, discount_type, discount_value, expiry_date, is_active, max_uses_customer) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $discount_type, $discount_value, $expiry_date, $is_active, $max_uses_customer]);
                $voucher_id = $pdo->lastInsertId();
                $_SESSION['admin_success'] = "New voucher created successfully.";
                log_admin_action('Voucher Added', "Added voucher: $code");
                // Log to audit trail
                $changes = [
                    'code' => ['old' => null, 'new' => $code],
                    'discount_type' => ['old' => null, 'new' => $discount_type],
                    'discount_value' => ['old' => null, 'new' => $discount_value],
                    'expiry_date' => ['old' => null, 'new' => $expiry_date],
                    'is_active' => ['old' => null, 'new' => $is_active],
                    'max_uses_customer' => ['old' => null, 'new' => $max_uses_customer]
                ];
                log_audit_trail('INSERT', 'vouchers', $voucher_id, $changes);
            } catch (PDOException $e) { 
                if ($e->errorInfo[1] == 1062) { // Duplicate entry
                    $_SESSION['admin_error'] = "Failed to create voucher. The code '$code' already exists.";
                } else {
                    $_SESSION['admin_error'] = "Database error creating voucher: " . $e->getMessage();
                }
            } catch (Exception $e) {
                $_SESSION['admin_error'] = "Error creating voucher: " . $e->getMessage();
            }
        } else {
             $_SESSION['admin_error'] = "Invalid data provided for voucher creation (Code, Type, and Value are required).";
        }
        header("Location: admin_dashboard.php?view=promotions"); // Redirect to combined page
        exit;
    }

    // --- Action: Update Voucher ---
   // --- Action: Update Voucher ---
    if ($_POST['action'] === 'update_voucher') {
        $voucher_id = filter_input(INPUT_POST, 'voucher_id', FILTER_VALIDATE_INT);
        $code = trim(strtoupper(filter_input(INPUT_POST, 'code', FILTER_SANITIZE_SPECIAL_CHARS)));
        $discount_type = $_POST['discount_type'] === 'percent' || $_POST['discount_type'] === 'fixed' ? $_POST['discount_type'] : null;
        $discount_value = filter_input(INPUT_POST, 'discount_value', FILTER_VALIDATE_FLOAT);
        $expiry_date_input = filter_input(INPUT_POST, 'expiry_date', FILTER_SANITIZE_SPECIAL_CHARS);
        $expiry_date = !empty($expiry_date_input) ? $expiry_date_input : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $max_uses_customer = filter_input(INPUT_POST, 'max_uses_customer', FILTER_VALIDATE_INT);
        $max_uses_customer = ($max_uses_customer !== null && $max_uses_customer > 0) ? $max_uses_customer : 1;

        if ($voucher_id && $code && $discount_type && $discount_value !== null) {
            try {
                // Get old data for audit
                $stmt_old = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
                $stmt_old->execute([$voucher_id]);
                $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("UPDATE vouchers SET code=?, discount_type=?, discount_value=?, expiry_date=?, is_active=?, max_uses_customer=? WHERE id=?");
                $stmt->execute([$code, $discount_type, $discount_value, $expiry_date, $is_active, $max_uses_customer, $voucher_id]);
                $_SESSION['admin_success'] = "Voucher updated successfully.";
                log_admin_action('Voucher Updated', "Updated voucher ID #$voucher_id.");
                // Log to audit trail
                $changes = [
                    'code' => ['old' => $old_data['code'], 'new' => $code],
                    'discount_type' => ['old' => $old_data['discount_type'], 'new' => $discount_type],
                    'discount_value' => ['old' => $old_data['discount_value'], 'new' => $discount_value],
                    'expiry_date' => ['old' => $old_data['expiry_date'], 'new' => $expiry_date],
                    'is_active' => ['old' => $old_data['is_active'], 'new' => $is_active],
                    'max_uses_customer' => ['old' => $old_data['max_uses_customer'], 'new' => $max_uses_customer]
                ];
                log_audit_trail('UPDATE', 'vouchers', $voucher_id, $changes);
            } catch (PDOException $e) {
                 if ($e->errorInfo[1] == 1062) {
                      $_SESSION['admin_error'] = "Failed to update voucher. The code '$code' may already be in use by another voucher.";
                 } else {
                      $_SESSION['admin_error'] = "Database error updating voucher: " . $e->getMessage();
                 }
            } catch (Exception $e) {
                 $_SESSION['admin_error'] = "Error updating voucher: " . $e->getMessage();
            }
        } else {
            $_SESSION['admin_error'] = "Invalid data provided for voucher update.";
        }
        header("Location: admin_dashboard.php?view=promotions"); // Redirect to combined page
        exit;
    }

   
   // --- Action: Delete Voucher ---
    if ($_POST['action'] === 'delete_voucher') {
        $voucher_id = filter_input(INPUT_POST, 'voucher_id', FILTER_VALIDATE_INT);
        
        if ($voucher_id) {
            try {
                // Get old data for audit
                $stmt_old = $pdo->prepare("SELECT code FROM vouchers WHERE id = ?");
                $stmt_old->execute([$voucher_id]);
                $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

                $pdo->beginTransaction();
                // Assumes 'user_voucher_usage' table exists
                $stmt_usage = $pdo->prepare("DELETE FROM user_voucher_usage WHERE voucher_id = ?");
                $stmt_usage->execute([$voucher_id]);
                
                $stmt = $pdo->prepare("DELETE FROM vouchers WHERE id = ?");
                $deleted = $stmt->execute([$voucher_id]);
                
                if ($deleted && $stmt->rowCount() > 0) {
                    $pdo->commit();
                    $_SESSION['admin_success'] = "Voucher and its usage history deleted successfully.";
                    log_admin_action('Voucher Deleted', "Deleted voucher ID #$voucher_id.");
                    // Log to audit trail
                    $changes = ['<DELETED_VOUCHER>' => ['old' => $old_data['code'] ?? "ID $voucher_id", 'new' => null]];
                    log_audit_trail('DELETE', 'vouchers', $voucher_id, $changes);
                } else {
                    $pdo->rollBack();
                     $_SESSION['admin_error'] = "Failed to delete voucher. It might have already been deleted.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['admin_error'] = "Failed to delete voucher: " . $e->getMessage();
            }
        } else {
            $_SESSION['admin_error'] = "Invalid Voucher ID.";
        }
        header("Location: admin_dashboard.php?view=promotions"); // Redirect to combined page
        exit;
    }


    // --- Action: Process Cancellation Request ---
    if ($_POST['action'] === 'process_cancellation') {
        $order_item_id = $_POST['order_item_id'];
        $decision = $_POST['decision'];
        try {
            $pdo->beginTransaction();

            $stmt_info = $pdo->prepare("SELECT o.user_id, o.order_id FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE oi.id = ?");
            $stmt_info->execute([$order_item_id]);
            $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

            $new_status = ($decision === 'approve') ? 'Cancelled' : 'Confirmed';
            
            $stmt = $pdo->prepare("UPDATE order_items SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_item_id]);

            if ($decision === 'approve') {
                $stmt_item = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE id = ?");
                $stmt_item->execute([$order_item_id]);
                $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

                if ($item) {
                    $stmt_restock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    $stmt_restock->execute([$item['quantity'], $item['product_id']]);
                }
            }
            
            if ($info) {
                $message = "Your cancellation request for an item in order #{$info['order_id']} has been " . ($decision === 'approve' ? 'approved.' : 'rejected.');
                $link = "profile.php?page=orders";
                $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                $stmt_notify->execute([$info['user_id'], $message, $link]);
            }

            $pdo->commit();
            $_SESSION['admin_success'] = "Cancellation request has been processed.";
            log_admin_action('Cancellation Processed', "Request for order item #$order_item_id was {$decision}d.");

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to process cancellation: " . $e->getMessage();
        }
        header("Location: admin_dashboard.php?view=cancellations");
        exit;
    }

    // --- Action: Process Return Request (Approve/Reject) ---
    if ($_POST['action'] === 'process_return') {
        $return_id = $_POST['return_id'];
        $decision = $_POST['status']; 
        $admin_remarks = trim($_POST['admin_remarks']);
    
        try {
            $pdo->beginTransaction();
    
            $stmt_info = $pdo->prepare("
                SELECT r.user_id, r.order_id, r.order_item_id, r.reason, r.quantity as return_quantity, oi.product_id, oi.quantity as ordered_quantity
                FROM return_requests r
                JOIN order_items oi ON r.order_item_id = oi.id
                WHERE r.return_id = ?
            ");
            $stmt_info->execute([$return_id]);
            $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
    
            if (!$info) {
                throw new Exception("Return request not found.");
            }
    
            $was_restocked = false;
            $final_status = $decision;
    
            if ($decision === 'Approved') {
                $final_status = 'Refunded';
    
                $stmt_update_item = $pdo->prepare("UPDATE order_items SET status = 'Returned' WHERE id = ?");
                $stmt_update_item->execute([$info['order_item_id']]);
    
                $auto_restock_reasons = ["Wrong item received", "Wrong item delivered"];
                if (in_array($info['reason'], $auto_restock_reasons)) {
                    $stmt_restock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    $stmt_restock->execute([$info['return_quantity'], $info['product_id']]);
                    $was_restocked = true;
                    $admin_remarks = trim($admin_remarks . " (auto-restocked)");
                } else {
                    $admin_remarks = trim($admin_remarks . " (completed)");
                }
            }
    
            $stmt_update_return = $pdo->prepare("UPDATE return_requests SET status = ?, admin_remarks = ?, resolved_at = NOW(), was_restocked = ? WHERE return_id = ?");
            $stmt_update_return->execute([$final_status, $admin_remarks, $was_restocked, $return_id]);
            
            $message = "Your return request for an item in order #{$info['order_id']} has been updated to: {$final_status}.";
            $link = "profile.php?page=orders";
            $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
            $stmt_notify->execute([$info['user_id'], $message, $link]);
            
            $pdo->commit();
            $_SESSION['admin_success'] = "Return request has been finalized.";
            log_admin_action('Return Request Finalized', "Return ID #$return_id set to '$final_status'. Restocked: " . ($was_restock ? 'Yes' : 'No'));
    
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to process return request: " . $e->getMessage();
        }
        header("Location: admin_dashboard.php?view=returns");
        exit;
    }

   // --- Action: Update Order Status ---
    if ($_POST['action'] === 'update_order_status') {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['status'];
        $remarks = trim($_POST['remarks']) ?? '';
        try {
            $pdo->beginTransaction();

            // Get old status for audit
            $stmt_user = $pdo->prepare("SELECT user_id, status FROM orders WHERE order_id = ?");
            $stmt_user->execute([$order_id]);
            $order_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $order_user_id = $order_data['user_id'] ?? null;
            $old_status = $order_data['status'] ?? 'unknown';

            $stmt_update_order = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt_update_order->execute([$new_status, $order_id]);
            $stmt_history = $pdo->prepare("INSERT INTO order_status_history (order_id, status, remarks) VALUES (?, ?, ?)");
            $stmt_history->execute([$order_id, $new_status, $remarks]);

            if ($order_user_id) {
                $message = "Your order #{$order_id} status has been updated to '{$new_status}'.";
                $link = "profile.php?page=orders";
                $stmt_notify = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                $stmt_notify->execute([$order_user_id, $message, $link]);
            }

            if ($new_status === 'Cancelled') {
                $stmt_items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $stmt_items->execute([$order_id]);
                $items_to_restock = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                $stmt_restock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                foreach ($items_to_restock as $item) {
                    $stmt_restock->execute([$item['quantity'], $item['product_id']]);
                }
            }
            $pdo->commit();
            $_SESSION['admin_success'] = "Order #$order_id status updated to '$new_status'.";
            log_admin_action('Order Status Update', "Order #$order_id status changed to '$new_status'.");
            // Log to audit trail
            $changes = [
                'status' => ['old' => $old_status, 'new' => $new_status]
            ];
            log_audit_trail('UPDATE', 'orders', $order_id, $changes);
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to update order status. Error: " . $e->getMessage();
        }
        header("Location: admin_dashboard.php?view=orders");
        exit;
    }


  // --- Action: Suspend/Unsuspend User ---
    if ($_POST['action'] === 'suspend_user') {
        $user_id_to_update = $_POST['user_id'];
        $new_status = $_POST['status']; 

        if ($user_id_to_update == $_SESSION['user_id']) {
            $_SESSION['admin_error'] = "You cannot suspend your own account.";
        } else {
            // Get old status for audit
            $stmt_old = $pdo->prepare("SELECT status FROM users WHERE id = ?");
            $stmt_old->execute([$user_id_to_update]);
            $old_status = $stmt_old->fetchColumn();

            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id_to_update]);
            $_SESSION['admin_success'] = "User status updated to '$new_status'.";
            log_admin_action('User Status Change', "Set user ID #$user_id_to_update to '$new_status'.");
            
            // Log to audit trail
            $changes = ['status' => ['old' => $old_status, 'new' => $new_status]];
            log_audit_trail('UPDATE', 'users', $user_id_to_update, $changes);
        }
        header("Location: admin_dashboard.php?view=users");
        exit;
    }
   // --- Action: Edit User Details ---
    if ($_POST['action'] === 'edit_user') {
        $user_id = $_POST['user_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);

        try {
            // Get old data for audit
            $stmt_old = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $stmt_old->execute([$user_id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $user_id]);
            $_SESSION['admin_success'] = "User details for ID #$user_id have been updated.";
            log_admin_action('User Details Updated', "Updated details for user ID #$user_id.");

            // Log to audit trail
            $changes = [
                'first_name' => ['old' => $old_data['first_name'], 'new' => $first_name],
                'last_name'  => ['old' => $old_data['last_name'], 'new' => $last_name],
                'email'      => ['old' => $old_data['email'], 'new' => $email]
            ];
            log_audit_trail('UPDATE', 'users', $user_id, $changes);

        } catch (Exception $e) {
            $_SESSION['admin_error'] = "Failed to update user details: " . $e->getMessage();
        }
        header("Location: admin_dashboard.php?view=users");
        exit;
    }
   // --- Action: Add New Admin ---
    if ($_POST['action'] === 'add_admin') {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        if (strlen($password) < 8) {
            $_SESSION['admin_error'] = "Password must be at least 8 characters long.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetch()) {
                $_SESSION['admin_error'] = "An account with this email already exists.";
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
                $stmt_insert->execute([$first_name, $last_name, $email, $hashed_password]);
                $new_admin_id = $pdo->lastInsertId(); // Get the new ID

                $_SESSION['admin_success'] = "New admin account created successfully.";
                log_admin_action('Admin Added', "New admin created: $email.");

                // Log to audit trail
                $changes = [
                    'first_name' => ['old' => null, 'new' => $first_name],
                    'last_name'  => ['old' => null, 'new' => $last_name],
                    'email'      => ['old' => null, 'new' => $email],
                    'role'       => ['old' => null, 'new' => 'admin']
                ];
                log_audit_trail('INSERT', 'users', $new_admin_id, $changes);
            }
        }
        header("Location: admin_dashboard.php?view=users");
        exit;
    }

   // --- Action: Update User Role ---
    if ($_POST['action'] === 'update_user_role') {
        $user_id_to_update = $_POST['user_id'];
        $new_role = $_POST['role'];
        
        if ($user_id_to_update == $_SESSION['user_id'] && $new_role !== 'admin') {
            $_SESSION['admin_error'] = "You cannot remove your own admin status.";
        } else {
            // Get old role for audit
            $stmt_old = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt_old->execute([$user_id_to_update]);
            $old_role = $stmt_old->fetchColumn();

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id_to_update]);
            $_SESSION['admin_success'] = "User role updated successfully.";
            log_admin_action('User Role Change', "User ID #$user_id_to_update role changed to '$new_role'.");
            
            // Log to audit trail
            $changes = ['role' => ['old' => $old_role, 'new' => $new_role]];
            log_audit_trail('UPDATE', 'users', $user_id_to_update, $changes);
        }
        header("Location: admin_dashboard.php?view=users");
        exit;
    }

   // --- Action: Update Stock (Quick Update) ---
    if ($_POST['action'] === 'update_stock') {
        $product_id = $_POST['product_id'];
        $new_stock = $_POST['stock'];
        
        // Get old stock for audit
        $stmt_old = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt_old->execute([$product_id]);
        $old_stock = $stmt_old->fetchColumn();

        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Log to audit trail
        $changes = ['stock' => ['old' => $old_stock, 'new' => $new_stock]];
        log_audit_trail('UPDATE', 'products', $product_id, $changes);

        if ($new_stock <= 0) {
            $stmt_status = $pdo->prepare("UPDATE products SET status = 'out of stock' WHERE id = ? AND status != 'inactive'");
            $stmt_status->execute([$product_id]);
        }
        $_SESSION['admin_success'] = "Stock for product #$product_id updated.";
        header("Location: admin_dashboard.php?view=inventory");
        exit;
    }
}

// --- PRODUCT AND USER ACTIONS ---
if (isset($_POST['add_product'])) {
    $status = $_POST['status'];
    if ((int)$_POST['stock'] <= 0) {
        $status = 'out of stock';
    }
    $image = uploadImage('image');
    $image1 = uploadImage('image1');
    $image2 = uploadImage('image2');
    $image3 = uploadImage('image3');
    $image4 = uploadImage('image4');

    $stmt = $pdo->prepare("INSERT INTO products (name, details, specifications, warranty, company, price, stock, status, category_id, image, image1, image2, image3, image4, views) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$_POST['name'], $_POST['details'], $_POST['specifications'], $_POST['warranty'], $_POST['company'], $_POST['price'], $_POST['stock'], $status, $_POST['category_id'], $image, $image1, $image2, $image3, $image4]);
    $new_product_id = $pdo->lastInsertId();

    log_admin_action('Product Added', "Added product: {$_POST['name']}.");
    
    // Log to audit trail
    $changes = [
        'name' => ['old' => null, 'new' => $_POST['name']],
        'price' => ['old' => null, 'new' => $_POST['price']],
        'stock' => ['old' => null, 'new' => $_POST['stock']],
        'status' => ['old' => null, 'new' => $status],
        'category_id' => ['old' => null, 'new' => $_POST['category_id']]
    ];
    log_audit_trail('INSERT', 'products', $new_product_id, $changes);
    
    header("Location: admin_dashboard.php?view=products");
    exit;
}
if (isset($_POST['update_product'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check for new images
    $image = uploadImage('image', $old['image'] ?? null);
    $image1 = uploadImage('image1', $old['image1'] ?? null);
    $image2 = uploadImage('image2', $old['image2'] ?? null);
    $image3 = uploadImage('image3', $old['image3'] ?? null);
    $image4 = uploadImage('image4', $old['image4'] ?? null);
    
    // Calculate new stock and status
    $stock = (int)$_POST['stock'];
    $status = $_POST['status'];
    if ($stock <= 0) {
        $status = 'out of stock';
    } elseif ($stock > 0 && $old['status'] === 'out of stock' && $status !== 'inactive') {
        $status = 'active';
    }

    // Build changes array *before* updating
    $changes = [
        'name' => ['old' => $old['name'], 'new' => $_POST['name']],
        'details' => ['old' => $old['details'], 'new' => $_POST['details']],
        'specifications' => ['old' => $old['specifications'], 'new' => $_POST['specifications']],
        'warranty' => ['old' => $old['warranty'], 'new' => $_POST['warranty']],
        'company' => ['old' => $old['company'], 'new' => $_POST['company']],
        'price' => ['old' => $old['price'], 'new' => $_POST['price']],
        'stock' => ['old' => $old['stock'], 'new' => $stock],
        'status' => ['old' => $old['status'], 'new' => $status],
        'category_id' => ['old' => $old['category_id'], 'new' => $_POST['category_id']],
        'image' => ['old' => $old['image'], 'new' => $image],
        'image1' => ['old' => $old['image1'], 'new' => $image1],
        'image2' => ['old' => $old['image2'], 'new' => $image2],
        'image3' => ['old' => $old['image3'], 'new' => $image3],
        'image4' => ['old' => $old['image4'], 'new' => $image4],
    ];

    $stmt = $pdo->prepare("UPDATE products SET name=?, details=?, specifications=?, warranty=?, company=?, price=?, stock=?, status=?, category_id=?, image=?, image1=?, image2=?, image3=?, image4=? WHERE id=?");
    $stmt->execute([$_POST['name'], $_POST['details'], $_POST['specifications'], $_POST['warranty'], $_POST['company'], $_POST['price'], $stock, $status, $_POST['category_id'], $image, $image1, $image2, $image3, $image4, $id]);
    
    log_admin_action('Product Updated', "Updated product ID #$id.");
    // Log all changes to the audit trail
    log_audit_trail('UPDATE', 'products', $id, $changes);
    
    header("Location: admin_dashboard.php?view=products");
    exit;
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("UPDATE products SET is_deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
    log_admin_action('Product Archived', "Archived product ID #$id.");
    
    // Log to audit trail
    $changes = ['is_deleted' => ['old' => 0, 'new' => 1]];
    log_audit_trail('UPDATE', 'products', $id, $changes);
    
    header("Location: admin_dashboard.php?view=products");
    exit;
}

// --- HELPER FUNCTIONS ---
function fetch_category_tree(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $category_map = [];
    foreach ($categories as $category) {
        $category['children'] = [];
        $category_map[$category['id']] = $category;
    }
    $tree = [];
    foreach ($category_map as $id => &$category) {
        if ($category['parent_id']) {
            $category_map[$category['parent_id']]['children'][] = &$category;
        } else {
            $tree[] = &$category;
        }
    }
    return $tree;
}
function render_category_options(array $categories, int $level = 0, ?int $selected_id = null) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;↳&nbsp;', $level);
    foreach ($categories as $category) {
        $selected_attr = ($category['id'] == $selected_id) ? 'selected' : '';
        echo "<option value='{$category['id']}' {$selected_attr}>{$indent}" . htmlspecialchars($category['name']) . "</option>";
        if (!empty($category['children'])) {
            render_category_options($category['children'], $level + 1, $selected_id);
        }
    }
}
function uploadImage($fileInputName, $oldImage = null, $target_sub_dir = 'images/') {
    $target_dir = "uploads/" . $target_sub_dir;
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    if (!empty($_FILES[$fileInputName]['name'])) {
        $file_name = time() . "" . preg_replace('/[^A-Za-z0-9\.\-_]/', '', basename($_FILES[$fileInputName]["name"]));
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_file)) {
            if ($oldImage && file_exists($target_dir . $oldImage)) {
                @unlink($target_dir . $oldImage);
            }
            return $file_name;
        }
    }
    return $oldImage;
}

// ===============================
// --- NEW MASKING FUNCTIONS ---
// ===============================

/**
 * Masks a string, showing the first and last character.
 * e.g., "John" -> "J**n", "Doe" -> "D*e"
 */
function mask_name($string) {
    if (empty($string)) return '';
    $len = mb_strlen($string); // Use mb_strlen for multi-byte characters
    if ($len <= 2) {
        return mb_substr($string, 0, 1) . '*';
    }
    // Shows first char, masks the middle, shows last char
    return mb_substr($string, 0, 1) . str_repeat('*', $len - 2) . mb_substr($string, -1);
}

/**
 * Masks an email address.
 * e.g., "user@gmail.com" -> "u**r@g***l.com"
 */
function mask_email($email) {
    if (empty($email) || !str_contains($email, '@')) return mask_name($email);
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) return mask_name($email); // Handle invalid format
    
    $username = $parts[0];
    $domain = $parts[1];
    
    $masked_username = mask_name($username);
    
    // Mask domain, but keep TLD
    $domain_parts = explode('.', $domain);
    if (count($domain_parts) < 2) {
         // Handle domain without TLD? e.g. user@localhost
         return $masked_username . '@' . mask_name($domain);
    }
    
    $tld = array_pop($domain_parts); // Get 'com'
    $domain_name = implode('.', $domain_parts); // Get 'gmail' or 'sub.domain'
    
    $masked_domain_name = mask_name($domain_name);
    
    return $masked_username . '@' . $masked_domain_name . '.' . $tld;
}
// ===============================
// DATA FETCHING FOR VIEWS
// ===============================
$view = $_GET['view'] ?? 'dashboard';
$full_order_statuses = ['Order Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
define('LOW_STOCK_THRESHOLD', 10);

if ($view === 'dashboard') {
    // --- ANALYTICS DATA ---
    // Key Metrics
    $total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'Delivered'")->fetchColumn();
    $total_sales = $pdo->query("SELECT COUNT(order_id) FROM orders WHERE status = 'Delivered'")->fetchColumn();
    $pending_orders = $pdo->query("SELECT COUNT(order_id) FROM orders WHERE status IN ('Order Placed', 'Processing')")->fetchColumn();
    $total_customers = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'user'")->fetchColumn();

    // Sales data for the last 7 days for the chart
    $sales_data_query = $pdo->query("
        SELECT 
            DATE(order_date) as day, 
            SUM(total_amount) as daily_revenue 
        FROM orders 
        WHERE order_date >= CURDATE() - INTERVAL 7 DAY AND status = 'Delivered'
        GROUP BY DATE(order_date) 
        ORDER BY day ASC
    ");
    $sales_data_raw = $sales_data_query->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare data for Chart.js
    $chart_labels = [];
    $chart_values = [];
    $period = new DatePeriod(
        new DateTime('-6 days'),
        new DateInterval('P1D'),
        new DateTime('+1 day')
    );
    $sales_map = array_column($sales_data_raw, 'daily_revenue', 'day');
    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        $chart_labels[] = $date->format('M d');
        $chart_values[] = $sales_map[$day] ?? 0;
    }
    $sales_chart_labels = json_encode($chart_labels);
    $sales_chart_data = json_encode($chart_values);

    // Top 5 Best-Selling Products
    $top_products_stmt = $pdo->query("
        SELECT p.name, SUM(oi.quantity) as total_sold
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        GROUP BY oi.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 Spending Customers
    $top_customers_stmt = $pdo->query("
        SELECT u.first_name, u.last_name, SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.status = 'Delivered'
        GROUP BY o.user_id
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $top_customers = $top_customers_stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'analytics') {
    $period = $_GET['period'] ?? '7day';
    $date_condition = "";
    switch ($period) {
        case 'today':
            $date_condition = "AND DATE(o.order_date) = CURDATE()";
            break;
        case '30day':
            $date_condition = "AND o.order_date >= CURDATE() - INTERVAL 30 DAY";
            break;
        case 'year':
            $date_condition = "AND YEAR(o.order_date) = YEAR(CURDATE())";
            break;
        case '7day':
        default:
            $date_condition = "AND o.order_date >= CURDATE() - INTERVAL 7 DAY";
            break;
    }

    // Detailed Analytics
    $analytics_query = "SELECT 
        COUNT(o.order_id) as total_orders, 
        SUM(o.total_amount) as total_revenue, 
        AVG(o.total_amount) as avg_order_value 
        FROM orders o WHERE o.status = 'Delivered' $date_condition";
    $analytics_data = $pdo->query($analytics_query)->fetch(PDO::FETCH_ASSOC);

    // Returned Products Analytics
    $return_analytics_query = "
        SELECT 
            rr.return_id,
            rr.reason,
            rr.was_restocked,
            oi.product_name,
            oi.price,
            rr.quantity
        FROM return_requests rr
        JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE rr.status = 'Refunded'
    ";
    $returned_items = $pdo->query($return_analytics_query)->fetchAll(PDO::FETCH_ASSOC);
    
    $potential_revenue_lost = 0;
    $unsellable_revenue_lost = 0;
    foreach($returned_items as $item) {
        $item_value = $item['price'] * $item['quantity'];
        $potential_revenue_lost += $item_value;
        if (!$item['was_restocked']) {
            $unsellable_revenue_lost += $item_value;
        }
    }

    // --- NEW CHART DATA ---

    // Sales by Category (Pie Chart)
    $category_sales_stmt = $pdo->query("
        SELECT c.name as category_name, SUM(oi.price * oi.quantity) as category_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status = 'Delivered'
        GROUP BY c.id
        ORDER BY category_revenue DESC
    ");
    $category_sales_data = $category_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $pie_chart_labels = json_encode(array_column($category_sales_data, 'category_name'));
    $pie_chart_data = json_encode(array_column($category_sales_data, 'category_revenue'));

    // --- Monthly Revenue vs. Loss Comparison (Line Graph) ---
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $monthly_revenue_data = array_fill(0, 12, 0);
    $monthly_potential_loss_data = array_fill(0, 12, 0);
    $monthly_direct_loss_data = array_fill(0, 12, 0);

    // 1. Get Monthly Revenue (from delivered orders)
    $monthly_sales_stmt = $pdo->query("
        SELECT MONTH(order_date) as month_num, SUM(total_amount) as monthly_revenue
        FROM orders
        WHERE status = 'Delivered' AND YEAR(order_date) = YEAR(CURDATE())
        GROUP BY MONTH(order_date)
    ");
    foreach ($monthly_sales_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthly_revenue_data[$row['month_num'] - 1] = $row['monthly_revenue'];
    }

    // 2. Get Monthly Potential Revenue Lost (All Refunded Items)
    $potential_loss_stmt = $pdo->query("
        SELECT MONTH(r.resolved_at) as month_num, SUM(oi.price * r.quantity) as potential_loss
        FROM return_requests r
        JOIN order_items oi ON r.order_item_id = oi.id
        WHERE r.status = 'Refunded' AND YEAR(r.resolved_at) = YEAR(CURDATE())
        GROUP BY MONTH(r.resolved_at)
    ");
    foreach ($potential_loss_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthly_potential_loss_data[$row['month_num'] - 1] = $row['potential_loss'];
    }
    
    // 3. Get Monthly Direct Loss (Unsellable Refunded Items)
    $direct_loss_stmt = $pdo->query("
        SELECT MONTH(r.resolved_at) as month_num, SUM(oi.price * r.quantity) as direct_loss
        FROM return_requests r
        JOIN order_items oi ON r.order_item_id = oi.id
        WHERE r.status = 'Refunded' AND (r.was_restocked = 0 OR r.was_restocked IS NULL)
        AND YEAR(r.resolved_at) = YEAR(CURDATE())
        GROUP BY MONTH(r.resolved_at)
    ");
    foreach ($direct_loss_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $monthly_direct_loss_data[$row['month_num'] - 1] = $row['direct_loss'];
    }

    // Encode all data for Chart.js
    $comparison_chart_labels = json_encode($months);
    $comparison_revenue_data = json_encode(array_values($monthly_revenue_data));
    $comparison_potential_loss_data = json_encode(array_values($monthly_potential_loss_data));
    $comparison_direct_loss_data = json_encode(array_values($monthly_direct_loss_data));
    // --- END Monthly Comparison ---

    $monthly_sales_data = $monthly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    $bar_chart_labels = json_encode(array_column($monthly_sales_data, 'month_name'));
    $bar_chart_data = json_encode(array_column($monthly_sales_data, 'monthly_revenue'));

    // Detailed Sales Report (Table)
    $detailed_sales_stmt = $pdo->query("
        SELECT 
            p.name as product_name,
            c.name as category_name,
            oi.quantity,
            oi.price,
            (oi.quantity * oi.price) as total_value,
            o.order_date
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE o.status = 'Delivered'
        ORDER BY o.order_date DESC
    ");
    $detailed_sales = $detailed_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
}


if ($view === 'orders') {
    $filter_status = $_GET['status'] ?? '';
    $orders_query = "SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON o.user_id = u.id";
    $params_orders = [];
    if (!empty($filter_status)) {
        $orders_query .= " WHERE o.status = ?";
        $params_orders[] = $filter_status;
    }
    $orders_query .= " ORDER BY o.order_date DESC";
    $stmt = $pdo->prepare($orders_query);
    $stmt->execute($params_orders);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $order_ids = array_column($orders, 'order_id');
    $order_items = [];
    if (!empty($order_ids)) {
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        $stmt_items = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id IN ($placeholders)");
        $stmt_items->execute($order_ids);
        $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_items as $item) {
            $order_items[$item['order_id']][] = $item;
        }
    }
}

if ($view === 'products') {
    $category_tree = fetch_category_tree($pdo);
    $query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 0";
    $params = [];
    if (!empty($_GET['search'])) { $query .= " AND p.name LIKE ?"; $params[] = "%".$_GET['search']."%"; }
    if (!empty($_GET['category'])) { $query .= " AND c.name LIKE ?"; $params[] = "%".$_GET['category']."%"; }
    $query .= " ORDER BY p.id DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'inventory') {
    $stmt = $pdo->query("SELECT id, name, stock, status FROM products WHERE is_deleted = 0 ORDER BY stock ASC");
    $inventory_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'users') {
    if (isset($_GET['delete_user'])) {
        $user_id_to_delete = $_GET['delete_user'];
        if ($user_id_to_delete == $_SESSION['user_id']) {
            $_SESSION['admin_error'] = "You cannot delete your own account.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$user_id_to_delete]);
            $_SESSION['admin_success'] = "User account deleted successfully.";
            log_admin_action('User Deleted', "Permanently deleted user ID #$user_id_to_delete.");
        }
        header("Location: admin_dashboard.php?view=users");
        exit;
    }
    $stmt_users = $pdo->query("SELECT id, first_name, last_name, email, role, status FROM users WHERE role = 'user' ORDER BY id DESC");
    $users_customers = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
    $stmt_admins = $pdo->query("SELECT id, first_name, last_name, email, role, status FROM users WHERE role = 'admin' ORDER BY id DESC");
    $users_admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'cancellations') {
    // 1. Fetch PENDING requests
    $stmt_pending = $pdo->query("
        SELECT oi.id as order_item_id, oi.quantity, oi.order_id, o.order_date, p.name as product_name, u.first_name, u.last_name, oi.cancellation_reason
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.order_id
        JOIN users u ON o.user_id = u.id
        WHERE oi.status = 'Cancellation Requested'
        ORDER BY o.order_date DESC
    ");
    $cancellation_requests_pending = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch COMPLETED cancellations
    $stmt_completed = $pdo->query("
        SELECT oi.id as order_item_id, oi.quantity, oi.order_id, o.order_date, p.name as product_name, u.first_name, u.last_name, oi.cancellation_reason
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.order_id
        JOIN users u ON o.user_id = u.id
        WHERE oi.status = 'Cancelled'
        ORDER BY o.order_date DESC
    ");
    $cancellation_requests_completed = $stmt_completed->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'returns') {
    $initial_return_statuses = ['Approved', 'Rejected'];
    
    // Fetch pending returns
    $stmt_pending = $pdo->query("
        SELECT 
            r.return_id, r.order_id, r.reason, r.requested_at, r.status, r.notes, r.admin_remarks,
            r.quantity,
            oi.product_name, oi.price,
            u.first_name, u.last_name,
            m.file_name as proof_video
        FROM return_requests r
        JOIN order_items oi ON r.order_item_id = oi.id
        JOIN users u ON r.user_id = u.id
        LEFT JOIN return_request_media m ON r.return_id = m.return_id
        WHERE r.status = 'Pending'
        ORDER BY r.requested_at DESC
    ");
    $pending_returns = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

    // Fetch completed returns (Rejected, Refunded)
    $stmt_completed = $pdo->query("
        SELECT 
            r.return_id, r.order_id, r.reason, r.requested_at, r.resolved_at, r.status, r.was_restocked, r.admin_remarks,
            r.quantity,
            oi.product_name, oi.price,
            u.first_name, u.last_name
        FROM return_requests r
        JOIN order_items oi ON r.order_item_id = oi.id
        JOIN users u ON r.user_id = u.id
        WHERE r.status IN ('Rejected', 'Refunded')
        ORDER BY r.resolved_at DESC
    ");
    $completed_returns = $stmt_completed->fetchAll(PDO::FETCH_ASSOC);
}


if ($view === 'logs') {
    $stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 100"); // Limit to recent 100 logs
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'audit_trail') {
    $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY timestamp DESC LIMIT 200"); // Limit to recent 200 changes
    $audit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'pages') {
    $stmt_pages = $pdo->query("SELECT * FROM pages ORDER BY title");
    $pages = $stmt_pages->fetchAll(PDO::FETCH_ASSOC);
}

if ($view === 'faqs') {
    $stmt_faqs = $pdo->query("SELECT * FROM faqs ORDER BY display_order ASC");
    $faqs = $stmt_faqs->fetchAll(PDO::FETCH_ASSOC);
}


if ($view === 'promotions') {
    // Fetch Promotions (Banners)
    $stmt = $pdo->query("SELECT * FROM promotions ORDER BY display_order ASC, created_at DESC");
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ALSO fetch Vouchers on the same page
    $stmt_vouchers = $pdo->query("
        SELECT v.*, COUNT(uvu.id) as total_uses 
        FROM vouchers v 
        LEFT JOIN user_voucher_usage uvu ON v.id = uvu.voucher_id 
        GROUP BY v.id 
        ORDER BY v.is_active DESC, v.created_at DESC
    ");
    $vouchers = $stmt_vouchers->fetchAll(PDO::FETCH_ASSOC);
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tiny.cloud/1/23smie9oj1xrtqtg0d9k12ivv00jlec69fcq4w7b2mzqa3eb/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '.tinymce-editor',
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount code',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | code | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
      });
    </script>
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; color: #fff; padding: 20px; }
        .sidebar a { color: #adb5bd; display: block; padding: 10px; border-radius: 5px; text-decoration: none; }
        .sidebar a:hover, .sidebar a.active { background-color: #495057; color: #fff; }
        .content { padding: 20px; }
        .card { border-radius: 12px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); }
        .stat-card { text-align: center; }
        .stat-card .display-4 { font-weight: 700; }
        .nav-card { text-decoration: none; color: inherit; display: block; transition: transform 0.2s; }
        .nav-card:hover { transform: translateY(-5px); text-decoration: none; color: inherit; }
        tr.collapse-row td { padding: 0; }
        .order-row { cursor: pointer; }
        .order-details-card { background-color: #f8f9fa; border: none; }
        .order-item-list img { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; }
        .transaction-id { font-family: monospace; font-size: 0.9em; color: #6c757d; }
        .stock-update-form input { width: 80px; }
        .low-stock-row { background-color: #fff3f3 !important; }
        .low-stock-row td:nth-child(3) .badge { background-color: #dc3545 !important; color: white !important; }
        .col-transition { transition: all 0.35s ease-in-out; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 sidebar d-none d-md-block">
            <h4 class="mb-4">Admin Panel</h4>
            <a href="admin_dashboard.php?view=dashboard" class="<?= $view==='dashboard'?'active':'' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="admin_dashboard.php?view=analytics" class="<?= $view==='analytics'?'active':'' ?>"><i class="bi bi-bar-chart-line-fill me-2"></i>Analytics</a>
            <a href="admin_dashboard.php?view=orders" class="<?= $view==='orders'?'active':'' ?>"><i class="bi bi-box-seam me-2"></i>Orders</a>
            <a href="admin_dashboard.php?view=products" class="<?= $view==='products'?'active':'' ?>"><i class="bi bi-box me-2"></i>Products</a>
            <a href="admin_dashboard.php?view=inventory" class="<?= $view==='inventory'?'active':'' ?>"><i class="bi bi-clipboard-data me-2"></i>Inventory</a>
            
<a href="admin_dashboard.php?view=logs" class="<?= $view==='logs'?'active':'' ?>"><i class="bi bi-journals me-2"></i>Logs</a>
            <a href="admin_dashboard.php?view=audit_trail" class="<?= $view==='audit_trail'?'active':'' ?>"><i class="bi bi-clipboard-pulse me-2"></i>Audit Trail</a>

            <hr>
            <small class="ms-3 text-muted">REQUESTS</small>
            <a href="admin_dashboard.php?view=cancellations" class="<?= $view==='cancellations'?'active':'' ?>"><i class="bi bi-x-circle me-2"></i>Cancellations</a>
             <a href="admin_dashboard.php?view=returns" class="<?= $view==='returns'?'active':'' ?>"><i class="bi bi-arrow-return-left me-2"></i>Returns</a>
            <hr>
             <small class="ms-3 text-muted">CMS & Marketing</small>
            <a href="admin_dashboard.php?view=faqs" class="nav-link <?= $view==='faqs'?'active':'' ?>"><i class="bi bi-question-circle me-2"></i>Manage FAQs</a>
            <a href="admin_dashboard.php?view=promotions" class="nav-link <?= $view==='promotions'?'active':'' ?>">
                 <i class="bi bi-megaphone me-2"></i> Promotions & Vouchers
            </a>
            <hr>
            <a href="admin_dashboard.php?view=users" class="<?= $view==='users'?'active':'' ?>"><i class="bi bi-people me-2"></i>Users</a>
            <a href="logout.php" class="text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </nav>
        <main class="col-md-10 ms-sm-auto content">
            
            <?php if (isset($_SESSION['admin_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['admin_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
                <?php unset($_SESSION['admin_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= htmlspecialchars($_SESSION['admin_error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
                <?php unset($_SESSION['admin_error']); ?>
            <?php endif; ?>

            <?php if ($view === 'dashboard'): ?>
                <h2 class="mb-4">Dashboard & Analytics</h2>
                
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Revenue</h5>
                                <p class="display-4">₱<?= number_format($total_revenue ?? 0, 2) ?></p>
                                <small>From Delivered Orders</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales</h5>
                                <p class="display-4"><?= $total_sales ?? 0 ?></p>
                                <small>Completed Transactions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Pending Orders</h5>
                                <p class="display-4"><?= $pending_orders ?? 0 ?></p>
                                <small>Awaiting Processing</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Customers</h5>
                                <p class="display-4"><?= $total_customers ?? 0 ?></p>
                                <small>Registered Users</small>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="mb-3">Quick Navigation</h3>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <a href="admin_dashboard.php?view=orders" class="nav-card">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="bi bi-box-seam" style="font-size: 3rem;"></i>
                                    <h4 class="card-title mt-2">Manage Orders</h4>
                                    <p class="card-text">View, update, and process all customer orders.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="admin_dashboard.php?view=products" class="nav-card">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="bi bi-box" style="font-size: 3rem;"></i>
                                    <h4 class="card-title mt-2">Manage Products</h4>
                                    <p class="card-text">Add new products and edit existing inventory items.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                     <div class="col-md-4">
                        <a href="admin_dashboard.php?view=users" class="nav-card">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="bi bi-people" style="font-size: 3rem;"></i>
                                    <h4 class="card-title mt-2">Manage Users</h4>
                                    <p class="card-text">View customer accounts and manage admin users.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-dark text-white">Weekly Sales Revenue</div>
                            <div class="card-body">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">Best-Selling Products</div>
                            <ul class="list-group list-group-flush">
                                <?php if (empty($top_products)): ?>
                                    <li class="list-group-item">No sales data yet.</li>
                                <?php else: ?>
                                    <?php foreach($top_products as $product): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($product['name']) ?>
                                        <span class="badge bg-primary rounded-pill"><?= $product['total_sold'] ?> sold</span>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="card">
                            <div class="card-header bg-dark text-white">Top Customers</div>
                             <ul class="list-group list-group-flush">
                                <?php if (empty($top_customers)): ?>
                                    <li class="list-group-item">No customer data yet.</li>
                                <?php else: ?>
                                    <?php foreach($top_customers as $customer): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                        <span class="badge bg-success rounded-pill">₱<?= number_format($customer['total_spent'], 2) ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

           <?php elseif ($view === 'analytics'): ?>
    <h2 class="mb-4">Detailed Analytics & Reports</h2>

    <div class="card mb-4">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span>Sales Performance</span>
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="view" value="analytics">
                <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="today" <?= ($period == 'today') ? 'selected' : '' ?>>Today</option>
                    <option value="7day" <?= ($period == '7day') ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30day" <?= ($period == '30day') ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="year" <?= ($period == 'year') ? 'selected' : '' ?>>This Year</option>
                </select>
            </form>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4">
                    <h4>Total Revenue</h4>
                    <h2 class="text-success">₱<?= number_format($analytics_data['total_revenue'] ?? 0, 2) ?></h2>
                </div>
                <div class="col-md-4">
                    <h4>Total Orders</h4>
                    <h2 class="text-info"><?= $analytics_data['total_orders'] ?? 0 ?></h2>
                </div>
                <div class="col-md-4">
                    <h4>Average Order Value</h4>
                    <h2 class="text-primary">₱<?= number_format($analytics_data['avg_order_value'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            Returned Products Revenue Loss Report
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <h4>Potential Revenue Lost</h4>
                        <p class="fs-4">₱<?= number_format($potential_revenue_lost, 2) ?></p>
                        <small>This is the total value of all items that have been successfully returned and refunded.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-danger">
                        <h4>Direct Loss from Unsellable Returns</h4>
                        <p class="fs-4">₱<?= number_format($unsellable_revenue_lost, 2) ?></p>
                        <small>This is the value of returned items that were NOT restocked, representing a direct loss.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-dark text-white">Detailed Sales Report</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Order Date</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($detailed_sales)): ?>
                            <tr><td colspan="6" class="text-center text-muted p-4">No sales data for the selected period.</td></tr>
                        <?php else: ?>
                            <?php foreach($detailed_sales as $sale): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($sale['order_date'])) ?></td>
                                <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                <td><?= htmlspecialchars($sale['category_name'] ?? 'N/A') ?></td>
                                <td><?= $sale['quantity'] ?></td>
                                <td>₱<?= number_format($sale['price'], 2) ?></td>
                                <td>₱<?= number_format($sale['total_value'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-dark text-white">Monthly Revenue vs. Loss (<?= date('Y') ?>)</div>
                <div class="card-body"><canvas id="monthlySalesChart"></canvas></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-dark text-white">Sales by Category</div>
                <div class="card-body"><canvas id="categoryPieChart"></canvas></div>
            </div>
        </div>
    </div>

                <div class="card">
                    <div class="card-header bg-dark text-white">Detailed Sales Report</div>
                    <div class="card-body">
                         <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order Date</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($detailed_sales)): ?>
                                        <tr><td colspan="6" class="text-center text-muted p-4">No sales data for the selected period.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($detailed_sales as $sale): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($sale['order_date'])) ?></td>
                                            <td><?= htmlspecialchars($sale['product_name']) ?></td>
                                            <td><?= htmlspecialchars($sale['category_name'] ?? 'N/A') ?></td>
                                            <td><?= $sale['quantity'] ?></td>
                                            <td>₱<?= number_format($sale['price'], 2) ?></td>
                                            <td>₱<?= number_format($sale['total_value'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


            <?php elseif ($view === 'orders'): ?>
                <h2 class="mb-4">Manage Orders</h2>
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <span>Order List</span>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="view" value="orders">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <?php foreach($full_order_statuses as $status): ?><option value="<?= $status ?>" <?= ($filter_status == $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
                            </select>
                            <a href="admin_dashboard.php?view=orders" class="btn btn-sm btn-secondary">Reset</a>
                        </form>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>ID</th><th>Date</th><th>Customer</th><th>Total</th><th>Payment Status</th><th>Order Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr><td colspan="6" class="text-center text-muted p-4">No orders found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): 
                                        $payment_paid = (!empty($order['transaction_id']) && $order['transaction_id'] !== 'PENDING_NULL');
                                    ?>
                                        <tr class="order-row" data-bs-toggle="collapse" data-bs-target="#order-details-<?= $order['order_id'] ?>">
                                            <td>#<?= $order['order_id'] ?></td>
                                            <td><?= date('M d, Y, g:i A', strtotime($order['order_date'])) ?></td>
                                            <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                            <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                            <td><?php if ($payment_paid): ?><span class="badge bg-success">Paid</span><?php else: ?><span class="badge bg-warning text-dark">Unpaid</span><?php endif; ?></td>
                                            <td><span class="badge bg-info"><?= htmlspecialchars($order['status']) ?></span></td>
                                        </tr>
                                        <tr class="collapse-row">
                                            <td colspan="6">
                                                <div id="order-details-<?= $order['order_id'] ?>" class="collapse">
                                                    <div class="card card-body order-details-card m-2">
                                                        <div class="row g-4">
                                                            <div class="col-md-6 col-lg-4">
                                                                <h5>Order Items</h5>
                                                                <div class="order-item-list">
                                                                    <?php foreach ($order_items[$order['order_id']] ?? [] as $item): ?><div class="d-flex align-items-center mb-2"><img src="uploads/images/<?= htmlspecialchars($item['image']) ?>"><div class="ms-3"><strong><?= htmlspecialchars($item['product_name']) ?></strong><br><small class="text-muted">Qty: <?= $item['quantity'] ?> @ ₱<?= number_format($item['price'], 2) ?></small></div><div class="ms-auto fw-bold">₱<?= number_format($item['quantity'] * $item['price'], 2) ?></div></div><?php endforeach; ?>
                                                                </div>
                                                                <hr>
                                                                <p><strong>Subtotal:</strong> <span class="float-end">₱<?= number_format($order['total_amount'] - $order['shipping_cost'], 2) ?></span></p>
                                                                <p><strong>Shipping:</strong> <span class="float-end">₱<?= number_format($order['shipping_cost'], 2) ?></span></p>
                                                                <p class="fs-5"><strong>Total:</strong> <span class="float-end fw-bold">₱<?= number_format($order['total_amount'], 2) ?></span></p>
                                                            </div>
                                                            <div class="col-md-6 col-lg-4">
                                                                <h5>Shipping & Payment</h5>
                                                                <p class="mb-1"><strong>Contact:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                                                                <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($order['shipping_address']) ?></p>
                                                                <hr>
                                                                <p class="mb-1"><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                                                                <p class="mb-1"><strong>Payment Status:</strong> <?php if ($payment_paid): ?><span class="badge bg-success">Paid</span><?php else: ?><span class="badge bg-warning text-dark">Unpaid</span><?php endif; ?></p>
                                                                <p class="mb-1"><strong>Transaction ID:</strong> <br><span class="transaction-id"><?= $payment_paid ? htmlspecialchars($order['transaction_id']) : 'N/A' ?></span></p>
                                                            </div>
                                                            <div class="col-md-12 col-lg-4">
                                                                <h5>Actions</h5>
                                                                <?php
                                                                    $available_statuses = [];
                                                                    $button_text = "Update Status";
                                                                    switch ($order['status']) {
                                                                        case 'Order Placed': $available_statuses = ['Processing', 'Cancelled']; $button_text = "Confirm Order"; break;
                                                                        case 'Processing': $available_statuses = ['Shipped', 'Cancelled']; break;
                                                                        case 'Shipped': $available_statuses = ['Delivered']; break;
                                                                    }
                                                                ?>
                                                                <?php if (!empty($available_statuses)): ?>
                                                                    <form method="POST" class="mb-3">
                                                                        <input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                                        <div class="mb-3"><label class="form-label">Update Status</label><select name="status" class="form-select" required><?php foreach ($available_statuses as $status): ?><option value="<?= $status ?>"><?= $status ?></option><?php endforeach; ?></select></div>
                                                                        <div class="mb-3"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2" placeholder="e.g., Tracking number..."></textarea></div>
                                                                        <button type="submit" class="btn btn-primary w-100"><?= $button_text ?></button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <div class="alert alert-light text-center"><h5 class="alert-heading">Order Finalized</h5><p class="mb-0 small">This order is <?= strtolower($order['status']) ?> and cannot be updated further.</p></div>
                                                                <?php endif; ?>
                                                                <a href="print_invoice.php?order_id=<?= $order['order_id'] ?>" target="_blank" class="btn btn-secondary w-100">Print Invoice / Packing Slip</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($view === 'inventory'): ?>
                <h2 class="mb-4">Inventory Management</h2>
                <div class="card">
                    <div class="card-header bg-dark text-white">Stock Levels <span class="badge bg-danger ms-2">Low Stock Threshold: <= <?= LOW_STOCK_THRESHOLD ?></span></div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>ID</th><th>Product Name</th><th>Current Stock</th><th>Status</th><th class="text-end">Quick Update</th></tr></thead>
                            <tbody>
                                <?php foreach ($inventory_products as $p): 
                                    $is_low_stock = $p['stock'] <= LOW_STOCK_THRESHOLD;
                                ?>
                                <tr class="<?= $is_low_stock ? 'low-stock-row' : '' ?>">
                                    <td><?= $p['id'] ?></td><td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?php if ($is_low_stock): ?><span class="badge bg-danger me-2">Low</span><?php endif; ?><?= $p['stock'] ?></td>
                                    <td>
                                        <?php $status_class = 'bg-secondary'; if ($p['status'] === 'active') $status_class = 'bg-success'; if ($p['status'] === 'inactive') $status_class = 'bg-warning text-dark'; if ($p['status'] === 'out of stock') $status_class = 'bg-danger'; ?>
                                        <span class="badge <?= $status_class ?>"><?= htmlspecialchars($p['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline-flex align-items-center gap-2 stock-update-form">
                                            <input type="hidden" name="action" value="update_stock"><input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="number" name="stock" class="form-control form-control-sm" value="<?= $p['stock'] ?>" required>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                 <?php elseif ($view === 'logs'): ?>
            <h2 class="mb-4">System Activity Logs</h2>
            <div class="card">
                <div class="card-header bg-dark text-white">Recent Activity</div>
                <div class="card-body p-0">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Username</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5" class="text-center text-muted p-4">No logs found.</td></tr>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                                    <td><?= htmlspecialchars($log['username'] ?? 'Guest/System') ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td><?= htmlspecialchars($log['details']) ?></td>
                                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($view === 'audit_trail'): ?>
    <h2 class="mb-4">Data Audit Trail</h2>
    <div class="card">
        <div class="card-header bg-dark text-white">Recent Data Changes (Newest First)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Record</th>
                            <th>Field</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($audit_logs)): ?>
                            <tr><td colspan="7" class="text-center text-muted p-4">No audit data found.</td></tr>
                        <?php else: ?>
                            <?php foreach($audit_logs as $log): ?>
                            <tr>
                                <td><small><?= date('M d, Y, g:i A', strtotime($log['timestamp'])) ?></small></td>
                                <td><?= htmlspecialchars($log['admin_username'] ?? 'N/A') ?></td>
                                <td>
                                    <?php $action_class = 'bg-info'; if($log['action'] == 'INSERT') $action_class = 'bg-success'; if($log['action'] == 'DELETE') $action_class = 'bg-danger'; ?>
                                    <span class="badge <?= $action_class ?>"><?= htmlspecialchars($log['action']) ?></span>
                                </td>
                                <td><small><b><?= htmlspecialchars($log['table_name']) ?></b> #<?= htmlspecialchars($log['record_id']) ?></small></td>
                                <td><code><?= htmlspecialchars($log['field_name']) ?></code></td>
                                <td><small><?= htmlspecialchars(mb_strimwidth($log['old_value'] ?? '', 0, 100, "...")) ?></small></td>
                                <td><small><?= htmlspecialchars(mb_strimwidth($log['new_value'] ?? '', 0, 100, "...")) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

            
<?php elseif ($view === 'cancellations'): ?>
    <h2 class="mb-4">Pending Cancellation Requests</h2>
    <div class="card mb-4"> <!-- Added mb-4 for spacing -->
        <div class="card-header bg-dark text-white">Pending Approval</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Order ID</th><th>Product</th><th>Qty</th><th>Customer</th><th>Reason</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($cancellation_requests_pending)): ?>
                            <tr><td colspan="6" class="text-center text-muted p-4">No pending cancellation requests.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cancellation_requests_pending as $req): ?>
                            <tr>
                                <td>#<?= $req['order_id'] ?></td>
                                <td><?= htmlspecialchars($req['product_name']) ?></td>
                                <td><?= $req['quantity'] ?></td>
                                <td><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></td>
                                <td><?= htmlspecialchars($req['cancellation_reason'] ?? 'N/A') ?></td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="process_cancellation">
                                        <input type="hidden" name="order_item_id" value="<?= $req['order_item_id'] ?>">
                                        <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm">Approve</button>
                                        <button type="submit" name="decision" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- NEW: Completed Cancellations Table -->
    <h2 class="mb-4">Cancellation History</h2>
    <div class="card">
        <div class="card-header bg-secondary text-white">Completed Cancellations</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Customer</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cancellation_requests_completed)): ?>
                            <tr><td colspan="5" class="text-center text-muted p-4">No completed cancellations found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cancellation_requests_completed as $req): ?>
                            <tr>
                                <td>#<?= $req['order_id'] ?></td>
                                <td><?= htmlspecialchars($req['product_name']) ?></td>
                                <td><?= $req['quantity'] ?></td>
                                <td><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></td>
                                <td><?= htmlspecialchars($req['cancellation_reason'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

            <?php elseif ($view === 'returns'): ?>
                <h2 class="mb-4">Return/Refund Requests</h2>
                <!-- PENDING REQUESTS -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">Pending Approval</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Order ID</th><th>Product</th><th>Qty</th><th>Return Value</th><th>Customer</th><th>Reason</th><th>Requested On</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (empty($pending_returns)): ?>
                                    <tr><td colspan="8" class="text-center text-muted p-4">No pending return requests found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_returns as $req): ?>
                                    <tr><td>#<?= $req['order_id'] ?></td><td><?= htmlspecialchars($req['product_name']) ?></td><td><?= $req['quantity'] ?></td><td>₱<?= number_format($req['quantity'] * $req['price'], 2) ?></td><td><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></td><td><?= htmlspecialchars($req['reason']) ?></td><td><?= date('M d, Y, g:i A', strtotime($req['requested_at'])) ?></td><td><button class="btn btn-sm btn-secondary" data-bs-toggle="collapse" data-bs-target="#return-<?= $req['return_id'] ?>">Manage</button></td></tr>
                                    <tr class="collapse-row">
                                        <td colspan="8">
                                            <div class="collapse" id="return-<?= $req['return_id'] ?>">
                                                <div class="card card-body m-2">
                                                    <div class="row">
                                                        <div class="col-md-6"><h6>Customer Notes:</h6><p class="small bg-light p-2 rounded"><?= !empty($req['notes']) ? nl2br(htmlspecialchars($req['notes'])) : '<em>No notes.</em>' ?></p></div>
                                                        <div class="col-md-6"><h6>Proof Video:</h6><?php if (!empty($req['proof_video'])): ?><a href="uploads/return_videos/<?= htmlspecialchars($req['proof_video']) ?>" target="_blank" class="btn btn-info btn-sm">View Proof Video</a><?php else: ?><p class="text-muted small"><em>No video.</em></p><?php endif; ?></div>
                                                    </div>
                                                    <hr>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="process_return"><input type="hidden" name="return_id" value="<?= $req['return_id'] ?>">
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3"><label class="form-label">Update Status</label><select name="status" class="form-select"><?php foreach($initial_return_statuses as $status): ?><option value="<?= $status ?>"><?= $status ?></option><?php endforeach; ?></select></div>
                                                            <div class="col-md-6 mb-3"><label class="form-label">Admin Remarks</label><input type="text" name="admin_remarks" class="form-control" placeholder="e.g., Return approved, item will be restocked..." value="<?= htmlspecialchars($req['admin_remarks']) ?>"></div>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Process Request</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- COMPLETED/CLOSED RETURNS -->
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">Completed & Closed Returns</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Return ID</th>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Return Value</th>
                                        <th>Reason</th>
                                        <th>Dates</th>
                                        <th>Final Status</th>
                                        <th>Restocked?</th>
                                        <th>Admin Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($completed_returns)): ?>
                                        <tr><td colspan="10" class="text-center text-muted p-4">No completed returns found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($completed_returns as $req): ?>
                                        <tr>
                                            <td>#<?= $req['return_id'] ?></td>
                                            <td>#<?= $req['order_id'] ?></td>
                                            <td><?= htmlspecialchars($req['product_name']) ?></td>
                                            <td><?= $req['quantity'] ?></td>
                                            <td>₱<?= number_format($req['quantity'] * $req['price'], 2) ?></td>
                                            <td><small><?= htmlspecialchars($req['reason']) ?></small></td>
                                            <td>
                                                <small>
                                                    Requested: <?= date('M d, Y, g:i A', strtotime($req['requested_at'])) ?><br>
                                                    Resolved: <?= date('M d, Y, g:i A', strtotime($req['resolved_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($req['status'] === 'Refunded'): ?>
                                                    <span class="badge bg-success"><?= htmlspecialchars($req['status']) ?></span>
                                                <?php else: // Rejected ?>
                                                    <span class="badge bg-danger"><?= htmlspecialchars($req['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($req['status'] === 'Refunded'): ?>
                                                    <?= isset($req['was_restocked']) && $req['was_restocked'] ? '<span class="badge bg-info">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                                <?php else: // Rejected items are never restocked ?>
                                                    <span class="badge bg-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= htmlspecialchars($req['admin_remarks'] ?? 'N/A') ?></small></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            
            <?php elseif ($view === 'products'): ?>
                <h2 class="mb-4">Manage Products</h2>
                <div class="accordion mb-3" id="productAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingAdd"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="false" aria-controls="collapseAdd">Add Product</button></h2>
                    <div id="collapseAdd" class="accordion-collapse collapse" aria-labelledby="headingAdd" data-bs-parent="#productAccordion">
                      <div class="accordion-body"><form method="POST" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="add_product" value="1"><div class="col-md-6"><input type="text" name="name" placeholder="Product Name" required class="form-control"></div><div class="col-md-6"><input type="number" step="0.01" name="price" placeholder="Price" required class="form-control"></div><div class="col-12"><textarea name="details" placeholder="Details" class="form-control"></textarea></div><div class="col-12"><textarea name="specifications" placeholder="Specifications" class="form-control"></textarea></div><div class="col-md-6"><input type="text" name="warranty" placeholder="Warranty" class="form-control"></div><div class="col-md-6"><input type="text" name="company" placeholder="Company" class="form-control"></div><div class="col-md-4"><input type="number" name="stock" placeholder="Stock Quantity" required class="form-control" value="0"></div><div class="col-md-4"><select name="status" class="form-select" required><option value="active">Active</option><option value="inactive">Inactive</option><option value="out of stock">Out of Stock</option></select></div><div class="col-md-4"><select name="category_id" class="form-select" required><option value="">Select a Category</option><?php render_category_options($category_tree); ?></select></div><div class="col-md-6"><label>Main Image</label><input type="file" name="image" class="form-control" accept="image/*"></div><div class="col-md-6"><label>Additional Images</label><input type="file" name="image1" class="form-control mb-2" accept="image/*"><input type="file" name="image2" class="form-control mb-2" accept="image/*"><input type="file" name="image3" class="form-control mb-2" accept="image/*"><input type="file" name="image4" class="form-control mb-2" accept="image/*"></div><div class="col-12"><button type="submit" class="btn btn-success">Add Product</button></div></form></div>
                    </div>
                  </div>
                </div>
                <div class="card">
                  <div class="card-header bg-dark text-white">Product List</div>
                  <div class="card-body">
                    <form method="GET" class="row g-2 mb-3"><input type="hidden" name="view" value="products"><div class="col-md-5"><input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-control" placeholder="Search by product name"></div><div class="col-md-4"><input type="text" name="category" value="<?= htmlspecialchars($_GET['category'] ?? '') ?>" class="form-control" placeholder="Filter by category name"></div><div class="col-md-3 d-flex"><button type="submit" class="btn btn-primary me-2">Search</button><a href="admin_dashboard.php?view=products" class="btn btn-secondary">Reset</a></div></form>
                    <table class="table table-hover align-middle">
                      <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th>Category</th><th>Image</th><th>Actions</th></tr></thead>
                      <tbody>
                        <?php if ($products): foreach ($products as $p): ?>
                          <tr>
                            <td><?= $p['id'] ?></td><td><?= htmlspecialchars($p['name']) ?></td><td>₱<?= number_format($p['price'], 2) ?></td><td><?= $p['stock'] ?></td>
                            <td>
                                <?php $status_class = 'bg-secondary'; if ($p['status'] === 'active') $status_class = 'bg-success'; if ($p['status'] === 'inactive') $status_class = 'bg-warning text-dark'; if ($p['status'] === 'out of stock') $status_class = 'bg-danger';?>
                                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($p['status']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?php if ($p['image']): ?><img src="uploads/images/<?= htmlspecialchars($p['image']) ?>" width="60"><?php endif; ?></td>
                            <td><button class="btn btn-warning btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProduct<?= $p['id'] ?>">Edit</button><a href="admin_dashboard.php?delete=<?= $p['id'] ?>&view=products" class="btn btn-danger btn-sm" onclick="return confirm('Archive this product?')">Delete</a></td>
                          </tr>
                          <tr class="collapse-row">
                            <td colspan="8" style="padding: 0;"><div id="collapseProduct<?= $p['id'] ?>" class="collapse"><div class="accordion-body"><form method="POST" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="update_product" value="1"><input type="hidden" name="id" value="<?= $p['id'] ?>"><div class="col-md-6"><input type="text" name="name" required class="form-control" value="<?= htmlspecialchars($p['name']) ?>"></div><div class="col-md-6"><input type="number" step="0.01" name="price" required class="form-control" value="<?= htmlspecialchars($p['price']) ?>"></div><div class="col-12"><textarea name="details" class="form-control"><?= htmlspecialchars($p['details']) ?></textarea></div><div class="col-12"><textarea name="specifications" class="form-control"><?= htmlspecialchars($p['specifications']) ?></textarea></div><div class="col-md-6"><input type="text" name="warranty" class="form-control" value="<?= htmlspecialchars($p['warranty']) ?>"></div><div class="col-md-6"><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($p['company']) ?>"></div><div class="col-md-4"><label class="form-label">Stock</label><input type="number" name="stock" required class="form-control" value="<?= htmlspecialchars($p['stock']) ?>"></div><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select" required><option value="active" <?= $p['status'] == 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $p['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="out of stock" <?= $p['status'] == 'out of stock' ? 'selected' : '' ?>>Out of Stock</option></select></div><div class="col-md-4"><label class="form-label">Category</label><select name="category_id" class="form-select" required><option value="">Select Category</option><?php render_category_options($category_tree, 0, $p['category_id']); ?></select></div><div class="col-md-6"><label>Main Image</label><input type="file" name="image" class="form-control"><small class="text-muted">Current: <?= htmlspecialchars($p['image']) ?></small></div><div class="col-md-6"><label>Additional Images</label><input type="file" name="image1" class="form-control mb-1"><input type="file" name="image2" class="form-control mb-1"><input type="file" name="image3" class="form-control mb-1"><input type="file" name="image4" class="form-control"></div><div class="col-12"><button type="submit" class="btn btn-primary">Save Changes</button> <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#collapseProduct<?= $p['id'] ?>">Cancel</button></div></form></div></div></td>
                          </tr>
                        <?php endforeach; else: ?>
                          <tr><td colspan="8" class="text-center text-muted">No products found</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

            <?php elseif ($view === 'users'): ?>
                <h2 class="mb-4">User Management</h2>
                <div class="row">
                    <div class="col-lg-12 col-transition" id="userTablesContainer">
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">Customer Accounts</div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php if(empty($users_customers)): ?>
                                            <tr><td colspan="5" class="text-center text-muted p-3">No customer accounts found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($users_customers as $u): ?>
                                            <tr>
                                                <td><?= $u['id'] ?></td>
                                                <td><?= htmlspecialchars(mask_name($u['first_name'])." ".mask_name($u['last_name'])) ?></td>
                                                <td>
                                                    <?php if($u['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Suspended</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-warning btn-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?= $u['id'] ?>" data-fname="<?= htmlspecialchars($u['first_name']) ?>" data-lname="<?= htmlspecialchars($u['last_name']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>">Edit</button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="suspend_user">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <?php if($u['status'] === 'active'): ?>
                                                            <input type="hidden" name="status" value="suspended">
                                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Suspend this user account?')">Suspend</button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="status" value="active">
                                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Re-activate this user account?')">Unsuspend</button>
                                                        <?php endif; ?>
                                                    </form>
                                                    <a href="admin_dashboard.php?delete_user=<?= $u['id'] ?>&view=users" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete this user?')">Delete</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span>Admin Accounts</span>
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addAdminFormCollapse" aria-expanded="false">Add New Admin</button>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($users_admins as $u): ?>
                                        <tr>
                                            <td><?= $u['id'] ?></td>
                                            <td><?= htmlspecialchars(mask_name($u['first_name'])." ".mask_name($u['last_name'])) ?></td>
                                            <td><?= htmlspecialchars(mask_email($u['email'])) ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($u['role']) ?></span></td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?= $u['id'] ?>" data-fname="<?= htmlspecialchars($u['first_name']) ?>" data-lname="<?= htmlspecialchars($u['last_name']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>">Edit</button>
                                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_user_role"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="role" value="user">
                                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Demote this admin to a Customer?')">Demote</button>
                                                </form>
                                                <a href="admin_dashboard.php?delete_user=<?= $u['id'] ?>&view=users" class="btn btn-danger btn-sm" onclick="return confirm('Delete this admin account?')">Delete</a>
                                                <?php else: ?><small class="text-muted">(Your Account)</small><?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-transition">
                        <div class="collapse" id="addAdminFormCollapse">
                            <div class="card">
                                <div class="card-header bg-dark text-white">Add New Admin</div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_admin">
                                        <div class="mb-3"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                                        <button type="submit" class="btn btn-primary w-100">Create Admin Account</button>
                                        <button type="button" class="btn btn-secondary w-100 mt-2" data-bs-toggle="collapse" data-bs-target="#addAdminFormCollapse">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($view === 'faqs'): ?>
                <h2 class="mb-4">Manage FAQs</h2>
                <div class="accordion mb-3" id="faqAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingAddFaq"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddFaq" aria-expanded="false" aria-controls="collapseAddFaq">Add New FAQ</button></h2>
                    <div id="collapseAddFaq" class="accordion-collapse collapse" aria-labelledby="headingAddFaq" data-bs-parent="#faqAccordion">
                      <div class="accordion-body">
                          <form method="POST">
                              <input type="hidden" name="action" value="add_faq">
                              <div class="mb-3"><label class="form-label">Question</label><input type="text" name="question" class="form-control" required></div>
                              <div class="mb-3"><label class="form-label">Answer</label><textarea name="answer" class="form-control tinymce-editor" rows="5"></textarea></div>
                              <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" name="display_order" class="form-control" value="0">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheckFaq" checked>
                                        <label class="form-check-label" for="isActiveCheckFaq">Active?</label>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">Add FAQ</button>
                                </div>
                              </div>
                          </form>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card">
                    <div class="card-header bg-dark text-white">Current FAQs</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Order</th><th>Question</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if(empty($faqs)): ?>
                                    <tr><td colspan="4" class="text-center p-4">No FAQs found.</td></tr>
                                <?php else: foreach($faqs as $faq): ?>
                                <tr>
                                    <td><?= $faq['display_order'] ?></td>
                                    <td><?= htmlspecialchars($faq['question']) ?></td>
                                    <td><span class="badge <?= $faq['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $faq['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editFaqModal-<?= $faq['id'] ?>">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                            <input type="hidden" name="action" value="delete_faq">
                                            <input type="hidden" name="faq_id" value="<?= $faq['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                 <!-- Edit FAQ Modal -->
                                <div class="modal fade" id="editFaqModal-<?= $faq['id'] ?>" tabindex="-1" aria-labelledby="editFaqModalLabel-<?= $faq['id'] ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="editFaqModalLabel-<?= $faq['id'] ?>">Edit FAQ</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form method="POST">
                                        <div class="modal-body">
                                          <input type="hidden" name="action" value="update_faq">
                                          <input type="hidden" name="faq_id" value="<?= $faq['id'] ?>">
                                          <div class="mb-3"><label class="form-label">Question</label><input type="text" name="question" class="form-control" value="<?= htmlspecialchars($faq['question']) ?>" required></div>
                                          <div class="mb-3"><label class="form-label">Answer</label><textarea name="answer" class="form-control tinymce-editor" rows="5"><?= htmlspecialchars($faq['answer']) ?></textarea></div>
                                          <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Display Order</label>
                                                <input type="number" name="display_order" class="form-control" value="<?= $faq['display_order'] ?>">
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheckFaq-<?= $faq['id'] ?>" <?= $faq['is_active'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="isActiveCheckFaq-<?= $faq['id'] ?>">Active?</label>
                                                </div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                          <button type="submit" class="btn btn-primary">Save changes</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($view === 'promotions'): ?>
                <h2 class="mb-4">Manage Promotions</h2>
                 <div class="accordion mb-3" id="promoAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingAdd"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="false" aria-controls="collapseAdd">Add New Promotion</button></h2>
                    <div id="collapseAdd" class="accordion-collapse collapse" aria-labelledby="headingAdd" data-bs-parent="#promoAccordion">
                      <div class="accordion-body">
                          <form method="POST" enctype="multipart/form-data" class="row g-3">
                              <input type="hidden" name="action" value="add_promotion">
                              <div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                              <div class="col-md-6"><label class="form-label">Link URL</label><input type="url" name="link_url" class="form-control" placeholder="https://example.com/product/123"></div>
                              <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                              <div class="col-md-6"><label class="form-label">Banner Image</label><input type="file" name="image" class="form-control" accept="image/*" required></div>
                              <div class="col-md-3"><label class="form-label">Display Order</label><input type="number" name="display_order" class="form-control" value="0"></div>
                              <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheck"><label class="form-check-label" for="isActiveCheck">Active?</label></div></div>
                              <div class="col-12"><button type="submit" class="btn btn-success">Add Promotion</button></div>
                          </form>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card">
                    <div class="card-header bg-dark text-white">Current Promotions</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Order</th><th>Image</th><th>Title</th><th>Status</th><th>Link</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if(empty($promotions)): ?>
                                    <tr><td colspan="6" class="text-center p-4">No promotions found.</td></tr>
                                <?php else: foreach($promotions as $promo): ?>
                                <tr>
                                    <td><?= $promo['display_order'] ?></td>
                                    <td><img src="uploads/promotions/<?= htmlspecialchars($promo['image_path']) ?>" width="150" alt=""></td>
                                    <td><?= htmlspecialchars($promo['title']) ?></td>
                                    <td><span class="badge <?= $promo['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $promo['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td><a href="<?= htmlspecialchars($promo['link_url']) ?>" target="_blank">Link</a></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPromoModal-<?= $promo['id'] ?>">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this promotion?');">
                                            <input type="hidden" name="action" value="delete_promotion">
                                            <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                 <!-- Edit Promo Modal -->
                                <div class="modal fade" id="editPromoModal-<?= $promo['id'] ?>" tabindex="-1" aria-labelledby="editPromoModalLabel-<?= $promo['id'] ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="editPromoModalLabel-<?= $promo['id'] ?>">Edit Promotion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body">
                                          <input type="hidden" name="action" value="update_promotion">
                                          <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                                           <div class="row g-3">
                                              <div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($promo['title']) ?>" required></div>
                                              <div class="col-md-6"><label class="form-label">Link URL</label><input type="url" name="link_url" class="form-control" value="<?= htmlspecialchars($promo['link_url']) ?>" placeholder="https://example.com/product/123"></div>
                                              <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($promo['description']) ?></textarea></div>
                                              <div class="col-md-6"><label class="form-label">Banner Image (optional)</label><input type="file" name="image" class="form-control" accept="image/*"><small>Current: <?= htmlspecialchars($promo['image_path']) ?></small></div>
                                              <div class="col-md-3"><label class="form-label">Display Order</label><input type="number" name="display_order" class="form-control" value="<?= $promo['display_order'] ?>"></div>
                                              <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheck-<?= $promo['id'] ?>" <?= $promo['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="isActiveCheck-<?= $promo['id'] ?>">Active?</label></div></div>
                                           </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                          <button type="submit" class="btn btn-primary">Save changes</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Vouchers Section -->
                <hr class="my-5">
                <h2 class="mb-4">Manage Vouchers</h2>
                <div class="accordion mb-3" id="voucherAccordion"> <div class="accordion-item"> <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddVoucher">Add New Voucher</button></h2> <div id="collapseAddVoucher" class="accordion-collapse collapse" data-bs-parent="#voucherAccordion"> <div class="accordion-body"> <form method="POST"> <input type="hidden" name="action" value="add_voucher"> <div class="row g-3"> <div class="col-md-4"><label class="form-label">Voucher Code</label><input type="text" name="code" class="form-control" placeholder="e.g., SALE10" required></div> <div class="col-md-4"><label class="form-label">Discount Type</label><select name="discount_type" class="form-select" required><option value="percent">Percentage (%)</option><option value="fixed">Fixed Amount (₱)</option></select></div> <div class="col-md-4"><label class="form-label">Discount Value</label><input type="number" name="discount_value" class="form-control" step="0.01" placeholder="e.g., 10 or 100.00" required></div> <div class="col-md-4"><label class="form-label">Expiry Date (Optional)</label><input type="date" name="expiry_date" class="form-control"></div> <div class="col-md-4"><label class="form-label">Uses Per Customer</label><input type="number" name="max_uses_customer" class="form-control" value="1" min="1"></div> <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="isVoucherActiveCheck" checked><label class="form-check-label" for="isVoucherActiveCheck">Active</label></div></div> <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-success w-100">Create</button></div> </div> </form> </div> </div> </div> </div>
                
                <div class="card">
                    <div class="card-header">Current Vouchers</div>
                    <div class="card-body p-0">
                         <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Code</th><th>Type</th><th>Value</th><th>Expires</th><th>Status</th><th>Uses/Customer</th><th>Total Uses</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if(empty($vouchers)): ?> <tr><td colspan="8" class="text-center p-4 text-muted">No vouchers found.</td></tr>
                                    <?php else: foreach($vouchers as $voucher): ?>
                                    <tr> <td><code class="fw-bold fs-6"><?= htmlspecialchars($voucher['code']) ?></code></td> <td><?= ucfirst($voucher['discount_type']) ?></td> <td><?= $voucher['discount_type'] === 'percent' ? $voucher['discount_value'] . '%' : '₱' . number_format($voucher['discount_value'], 2) ?></td> <td><?= $voucher['expiry_date'] ? date('M j, Y', strtotime($voucher['expiry_date'])) : '<span class="text-muted">Never</span>' ?></td> <td><span class="badge <?= $voucher['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $voucher['is_active'] ? 'Active' : 'Inactive' ?></span></td> <td><?= $voucher['max_uses_customer'] ?></td> <td><?= $voucher['total_uses'] ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editVoucherModal" data-id="<?= $voucher['id'] ?>" data-code="<?= htmlspecialchars($voucher['code']) ?>" data-type="<?= $voucher['discount_type'] ?>" data-value="<?= $voucher['discount_value'] ?>" data-expiry="<?= $voucher['expiry_date'] ?>" data-uses-customer="<?= $voucher['max_uses_customer'] ?>" data-active="<?= $voucher['is_active'] ?>"><i class="bi bi-pencil-square"></i></button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('DELETE this voucher? This also deletes usage history and cannot be undone.');"> <input type="hidden" name="action" value="delete_voucher"> <input type="hidden" name="voucher_id" value="<?= $voucher['id'] ?>"> <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button> </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($view === 'products'): ?>
                <h2 class="mb-4">Manage Products</h2>
                <div class="accordion mb-3" id="productAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingAdd"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="false" aria-controls="collapseAdd">Add Product</button></h2>
                    <div id="collapseAdd" class="accordion-collapse collapse" aria-labelledby="headingAdd" data-bs-parent="#productAccordion">
                      <div class="accordion-body"><form method="POST" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="add_product" value="1"><div class="col-md-6"><input type="text" name="name" placeholder="Product Name" required class="form-control"></div><div class="col-md-6"><input type="number" step="0.01" name="price" placeholder="Price" required class="form-control"></div><div class="col-12"><textarea name="details" placeholder="Details" class="form-control"></textarea></div><div class="col-12"><textarea name="specifications" placeholder="Specifications" class="form-control"></textarea></div><div class="col-md-6"><input type="text" name="warranty" placeholder="Warranty" class="form-control"></div><div class="col-md-6"><input type="text" name="company" placeholder="Company" class="form-control"></div><div class="col-md-4"><input type="number" name="stock" placeholder="Stock Quantity" required class="form-control" value="0"></div><div class="col-md-4"><select name="status" class="form-select" required><option value="active">Active</option><option value="inactive">Inactive</option><option value="out of stock">Out of Stock</option></select></div><div class="col-md-4"><select name="category_id" class="form-select" required><option value="">Select a Category</option><?php render_category_options($category_tree); ?></select></div><div class="col-md-6"><label>Main Image</label><input type="file" name="image" class="form-control" accept="image/*"></div><div class="col-md-6"><label>Additional Images</label><input type="file" name="image1" class="form-control mb-2" accept="image/*"><input type="file" name="image2" class="form-control mb-2" accept="image/*"><input type="file" name="image3" class="form-control mb-2" accept="image/*"><input type="file" name="image4" class="form-control mb-2" accept="image/*"></div><div class="col-12"><button type="submit" class="btn btn-success">Add Product</button></div></form></div>
                    </div>
                  </div>
                </div>
                <div class="card">
                  <div class="card-header bg-dark text-white">Product List</div>
                  <div class="card-body">
                    <form method="GET" class="row g-2 mb-3"><input type="hidden" name="view" value="products"><div class="col-md-5"><input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-control" placeholder="Search by product name"></div><div class="col-md-4"><input type="text" name="category" value="<?= htmlspecialchars($_GET['category'] ?? '') ?>" class="form-control" placeholder="Filter by category name"></div><div class="col-md-3 d-flex"><button type="submit" class="btn btn-primary me-2">Search</button><a href="admin_dashboard.php?view=products" class="btn btn-secondary">Reset</a></div></form>
                    <table class="table table-hover align-middle">
                      <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th>Category</th><th>Image</th><th>Actions</th></tr></thead>
                      <tbody>
                        <?php if ($products): foreach ($products as $p): ?>
                          <tr>
                            <td><?= $p['id'] ?></td><td><?= htmlspecialchars($p['name']) ?></td><td>₱<?= number_format($p['price'], 2) ?></td><td><?= $p['stock'] ?></td>
                            <td>
                                <?php $status_class = 'bg-secondary'; if ($p['status'] === 'active') $status_class = 'bg-success'; if ($p['status'] === 'inactive') $status_class = 'bg-warning text-dark'; if ($p['status'] === 'out of stock') $status_class = 'bg-danger';?>
                                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($p['status']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?php if ($p['image']): ?><img src="uploads/images/<?= htmlspecialchars($p['image']) ?>" width="60"><?php endif; ?></td>
                            <td><button class="btn btn-warning btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProduct<?= $p['id'] ?>">Edit</button><a href="admin_dashboard.php?delete=<?= $p['id'] ?>&view=products" class="btn btn-danger btn-sm" onclick="return confirm('Archive this product?')">Delete</a></td>
                          </tr>
                          <tr class="collapse-row">
                            <td colspan="8" style="padding: 0;"><div id="collapseProduct<?= $p['id'] ?>" class="collapse"><div class="accordion-body"><form method="POST" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="update_product" value="1"><input type="hidden" name="id" value="<?= $p['id'] ?>"><div class="col-md-6"><input type="text" name="name" required class="form-control" value="<?= htmlspecialchars($p['name']) ?>"></div><div class="col-md-6"><input type="number" step="0.01" name="price" required class="form-control" value="<?= htmlspecialchars($p['price']) ?>"></div><div class="col-12"><textarea name="details" class="form-control"><?= htmlspecialchars($p['details']) ?></textarea></div><div class="col-12"><textarea name="specifications" class="form-control"><?= htmlspecialchars($p['specifications']) ?></textarea></div><div class="col-md-6"><input type="text" name="warranty" class="form-control" value="<?= htmlspecialchars($p['warranty']) ?>"></div><div class="col-md-6"><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($p['company']) ?>"></div><div class="col-md-4"><label class="form-label">Stock</label><input type="number" name="stock" required class="form-control" value="<?= htmlspecialchars($p['stock']) ?>"></div><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select" required><option value="active" <?= $p['status'] == 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $p['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="out of stock" <?= $p['status'] == 'out of stock' ? 'selected' : '' ?>>Out of Stock</option></select></div><div class="col-md-4"><label class="form-label">Category</label><select name="category_id" class="form-select" required><option value="">Select Category</option><?php render_category_options($category_tree, 0, $p['category_id']); ?></select></div><div class="col-md-6"><label>Main Image</label><input type="file" name="image" class="form-control"><small class="text-muted">Current: <?= htmlspecialchars($p['image']) ?></small></div><div class="col-md-6"><label>Additional Images</label><input type="file" name="image1" class="form-control mb-1"><input type="file" name="image2" class="form-control mb-1"><input type="file" name="image3" class="form-control mb-1"><input type="file" name="image4" class="form-control"></div><div class="col-12"><button type="submit" class="btn btn-primary">Save Changes</button> <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#collapseProduct<?= $p['id'] ?>">Cancel</button></div></form></div></div></td>
                          </tr>
                        <?php endforeach; else: ?>
                          <tr><td colspan="8" class="text-center text-muted">No products found</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

            <?php elseif ($view === 'users'): ?>
                <h2 class="mb-4">User Management</h2>
                <div class="row">
                    <div class="col-lg-12 col-transition" id="userTablesContainer">
                        <div class="card mb-4">
                            <div class="card-header bg-dark text-white">Customer Accounts</div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php if(empty($users_customers)): ?>
                                            <tr><td colspan="5" class="text-center text-muted p-3">No customer accounts found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($users_customers as $u): ?>
                                            <tr>
                                                <td><?= $u['id'] ?></td>
                                              <td><?= htmlspecialchars(mask_name($u['first_name'])." ".mask_name($u['last_name'])) ?></td>
                                                <td><?= htmlspecialchars(mask_email($u['email'])) ?></td>
                                                <td>
                                                    <?php if($u['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Suspended</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-warning btn-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?= $u['id'] ?>" data-fname="<?= htmlspecialchars($u['first_name']) ?>" data-lname="<?= htmlspecialchars($u['last_name']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>">Edit</button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="suspend_user">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <?php if($u['status'] === 'active'): ?>
                                                            <input type="hidden" name="status" value="suspended">
                                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Suspend this user account?')">Suspend</button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="status" value="active">
                                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Re-activate this user account?')">Unsuspend</button>
                                                        <?php endif; ?>
                                                    </form>
                                                    <a href="admin_dashboard.php?delete_user=<?= $u['id'] ?>&view=users" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete this user?')">Delete</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span>Admin Accounts</span>
                                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addAdminFormCollapse" aria-expanded="false">Add New Admin</button>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($users_admins as $u): ?>
                                        <tr>
                                            <td><?= $u['id'] ?></td>
                                            <td><?= htmlspecialchars(mask_name($u['first_name'])." ".mask_name($u['last_name'])) ?></td>
                                            <td><?= htmlspecialchars(mask_email($u['email'])) ?></td>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($u['role']) ?></span></td>
                                            <td>
                                                <button type="button" class="btn btn-warning btn-sm edit-user-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?= $u['id'] ?>" data-fname="<?= htmlspecialchars($u['first_name']) ?>" data-lname="<?= htmlspecialchars($u['last_name']) ?>" data-email="<?= htmlspecialchars($u['email']) ?>">Edit</button>
                                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_user_role"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="role" value="user">
                                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Demote this admin to a Customer?')">Demote</button>
                                                </form>
                                                <a href="admin_dashboard.php?delete_user=<?= $u['id'] ?>&view=users" class="btn btn-danger btn-sm" onclick="return confirm('Delete this admin account?')">Delete</a>
                                                <?php else: ?><small class="text-muted">(Your Account)</small><?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-transition">
                        <div class="collapse" id="addAdminFormCollapse">
                            <div class="card">
                                <div class="card-header bg-dark text-white">Add New Admin</div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_admin">
                                        <div class="mb-3"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                                        <button type="submit" class="btn btn-primary w-100">Create Admin Account</button>
                                        <button type="button" class="btn btn-secondary w-100 mt-2" data-bs-toggle="collapse" data-bs-target="#addAdminFormCollapse">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            
            <?php elseif ($view === 'faqs'): ?>
                <h2 class="mb-4">Manage FAQs</h2>
                <div class="accordion mb-3" id="faqAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingAddFaq"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddFaq" aria-expanded="false" aria-controls="collapseAddFaq">Add New FAQ</button></h2>
                    <div id="collapseAddFaq" class="accordion-collapse collapse" aria-labelledby="headingAddFaq" data-bs-parent="#faqAccordion">
                      <div class="accordion-body">
                          <form method="POST">
                              <input type="hidden" name="action" value="add_faq">
                              <div class="mb-3"><label class="form-label">Question</label><input type="text" name="question" class="form-control" required></div>
                              <div class="mb-3"><label class="form-label">Answer</label><textarea name="answer" class="form-control tinymce-editor" rows="5"></textarea></div>
                              <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" name="display_order" class="form-control" value="0">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheckFaq" checked>
                                        <label class="form-check-label" for="isActiveCheckFaq">Active?</label>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">Add FAQ</button>
                                </div>
                              </div>
                          </form>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card">
                    <div class="card-header bg-dark text-white">Current FAQs</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Order</th><th>Question</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if(empty($faqs)): ?>
                                    <tr><td colspan="4" class="text-center p-4">No FAQs found.</td></tr>
                                <?php else: foreach($faqs as $faq): ?>
                                <tr>
                                    <td><?= $faq['display_order'] ?></td>
                                    <td><?= htmlspecialchars($faq['question']) ?></td>
                                    <td><span class="badge <?= $faq['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $faq['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editFaqModal-<?= $faq['id'] ?>">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                            <input type="hidden" name="action" value="delete_faq">
                                            <input type="hidden" name="faq_id" value="<?= $faq['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                 <!-- Edit FAQ Modal -->
                                <div class="modal fade" id="editFaqModal-<?= $faq['id'] ?>" tabindex="-1" aria-labelledby="editFaqModalLabel-<?= $faq['id'] ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="editFaqModalLabel-<?= $faq['id'] ?>">Edit FAQ</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form method="POST">
                                        <div class="modal-body">
                                          <input type="hidden" name="action" value="update_faq">
                                          <input type="hidden" name="faq_id" value="<?= $faq['id'] ?>">
                                          <div class="mb-3"><label class="form-label">Question</label><input type="text" name="question" class="form-control" value="<?= htmlspecialchars($faq['question']) ?>" required></div>
                                          <div class="mb-3"><label class="form-label">Answer</label><textarea name="answer" class="form-control tinymce-editor" rows="5"><?= htmlspecialchars($faq['answer']) ?></textarea></div>
                                          <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Display Order</label>
                                                <input type="number" name="display_order" class="form-control" value="<?= $faq['display_order'] ?>">
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheckFaq-<?= $faq['id'] ?>" <?= $faq['is_active'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="isActiveCheckFaq-<?= $faq['id'] ?>">Active?</label>
                                                </div>
                                            </div>
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                          <button type="submit" class="btn btn-primary">Save changes</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($view === 'promotions'): ?>
                <h2 class="mb-4">Manage Promotions</h2>
                 <div class="accordion mb-3" id="promoAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingAdd"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="false" aria-controls="collapseAdd">Add New Promotion</button></h2>
                    <div id="collapseAdd" class="accordion-collapse collapse" aria-labelledby="headingAdd" data-bs-parent="#promoAccordion">
                      <div class="accordion-body">
                          <form method="POST" enctype="multipart/form-data" class="row g-3">
                              <input type="hidden" name="action" value="add_promotion">
                              <div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                              <div class="col-md-6"><label class="form-label">Link URL</label><input type="url" name="link_url" class="form-control" placeholder="https://example.com/product/123"></div>
                              <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                              <div class="col-md-6"><label class="form-label">Banner Image</label><input type="file" name="image" class="form-control" accept="image/*" required></div>
                              <div class="col-md-3"><label class="form-label">Display Order</label><input type="number" name="display_order" class="form-control" value="0"></div>
                              <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheck"><label class="form-check-label" for="isActiveCheck">Active?</label></div></div>
                              <div class="col-12"><button type="submit" class="btn btn-success">Add Promotion</button></div>
                          </form>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card">
                    <div class="card-header bg-dark text-white">Current Promotions</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Order</th><th>Image</th><th>Title</th><th>Status</th><th>Link</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if(empty($promotions)): ?>
                                    <tr><td colspan="6" class="text-center p-4">No promotions found.</td></tr>
                                <?php else: foreach($promotions as $promo): ?>
                                <tr>
                                    <td><?= $promo['display_order'] ?></td>
                                    <td><img src="uploads/promotions/<?= htmlspecialchars($promo['image_path']) ?>" width="150" alt=""></td>
                                    <td><?= htmlspecialchars($promo['title']) ?></td>
                                    <td><span class="badge <?= $promo['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $promo['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td><a href="<?= htmlspecialchars($promo['link_url']) ?>" target="_blank">Link</a></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPromoModal-<?= $promo['id'] ?>">Edit</button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this promotion?');">
                                            <input type="hidden" name="action" value="delete_promotion">
                                            <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                 <!-- Edit Promo Modal -->
                                <div class="modal fade" id="editPromoModal-<?= $promo['id'] ?>" tabindex="-1" aria-labelledby="editPromoModalLabel-<?= $promo['id'] ?>" aria-hidden="true">
                                  <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="editPromoModalLabel-<?= $promo['id'] ?>">Edit Promotion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                      <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body">
                                          <input type="hidden" name="action" value="update_promotion">
                                          <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                                           <div class="row g-3">
                                              <div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="<?= htmlspecialchars($promo['title']) ?>" required></div>
                                              <div class="col-md-6"><label class="form-label">Link URL</label><input type="url" name="link_url" class="form-control" value="<?= htmlspecialchars($promo['link_url']) ?>" placeholder="https://example.com/product/123"></div>
                                              <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($promo['description']) ?></textarea></div>
                                              <div class="col-md-6"><label class="form-label">Banner Image (optional)</label><input type="file" name="image" class="form-control" accept="image/*"><small>Current: <?= htmlspecialchars($promo['image_path']) ?></small></div>
                                              <div class="col-md-3"><label class="form-label">Display Order</label><input type="number" name="display_order" class="form-control" value="<?= $promo['display_order'] ?>"></div>
                                              <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActiveCheck-<?= $promo['id'] ?>" <?= $promo['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="isActiveCheck-<?= $promo['id'] ?>">Active?</label></div></div>
                                           </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                          <button type="submit" class="btn btn-primary">Save changes</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                

            <?php else: ?>
                <!-- This is the default case, which includes 'dashboard' or any other view -->
                <!-- Make sure the 'pages' and 'promotions' blocks are inside this if/else structure. -->
                <h2 class="mb-4">Error</h2>
                <p>The requested view '<?= htmlspecialchars($view) ?>' could not be found.</p>

            <?php endif; ?>
        </main>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Edit User Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="mb-3">
                <label for="edit_first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
            </div>
            <div class="mb-3">
                <label for="edit_last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
            </div>
            <div class="mb-3">
                <label for="edit_email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="edit_email" name="email" required>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logic for dynamic user management layout
    const addAdminFormCollapse = document.getElementById('addAdminFormCollapse');
    const userTablesContainer = document.getElementById('userTablesContainer');

    if (addAdminFormCollapse && userTablesContainer) {
        addAdminFormCollapse.addEventListener('show.bs.collapse', function () {
            userTablesContainer.classList.remove('col-lg-12');
            userTablesContainer.classList.add('col-lg-8');
        });

        addAdminFormCollapse.addEventListener('hide.bs.collapse', function () {
            userTablesContainer.classList.remove('col-lg-8');
            userTablesContainer.classList.add('col-lg-12');
        });
    }

    // Logic for Edit User Modal
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            
            const userId = button.getAttribute('data-userid');
            const fname = button.getAttribute('data-fname');
            const lname = button.getAttribute('data-lname');
            const email = button.getAttribute('data-email');

            const modalTitle = editUserModal.querySelector('.modal-title');
            const userIdInput = editUserModal.querySelector('#edit_user_id');
            const fnameInput = editUserModal.querySelector('#edit_first_name');
            const lnameInput = editUserModal.querySelector('#edit_last_name');
            const emailInput = editUserModal.querySelector('#edit_email');

            modalTitle.textContent = 'Edit User: ' + fname + ' ' + lname;
            userIdInput.value = userId;
            fnameInput.value = fname;
            lnameInput.value = lname;
            emailInput.value = email;
        });
    }

    // Populate Edit Voucher Modal
    const editVoucherModal = document.getElementById('editVoucherModal');
    if (editVoucherModal) {
        editVoucherModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const code = button.getAttribute('data-code');
            const type = button.getAttribute('data-type');
            const value = button.getAttribute('data-value');
            const expiry = button.getAttribute('data-expiry');
            const usesCustomer = button.getAttribute('data-uses-customer');
            const active = button.getAttribute('data-active');

            const modalTitle = editVoucherModal.querySelector('.modal-title');
            modalTitle.textContent = 'Edit Voucher: ' + code;

            editVoucherModal.querySelector('#edit_voucher_id').value = id;
            editVoucherModal.querySelector('#edit_code').value = code;
            editVoucherModal.querySelector('#edit_discount_type').value = type;
            editVoucherModal.querySelector('#edit_discount_value').value = value;
            editVoucherModal.querySelector('#edit_expiry_date').value = expiry;
            editVoucherModal.querySelector('#edit_max_uses_customer').value = usesCustomer;
            editVoucherModal.querySelector('#edit_is_active').checked = (active == '1');
        });
    }

    // Chart.js implementation for Sales Chart
    const salesChartCanvas = document.getElementById('salesChart');
    if (salesChartCanvas) {
        new Chart(salesChartCanvas, {
            type: 'bar',
            data: {
                labels: <?= $sales_chart_labels ?? '[]' ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= $sales_chart_data ?? '[]' ?>,
                    backgroundColor: 'rgba(23, 162, 184, 0.7)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '₱' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // --- NEW CHARTS FOR ANALYTICS PAGE ---

    // Pie Chart for Sales by Category
    const categoryPieChartCanvas = document.getElementById('categoryPieChart');
    if(categoryPieChartCanvas) {
        new Chart(categoryPieChartCanvas, {
            type: 'pie',
            data: {
                labels: <?= $pie_chart_labels ?? '[]' ?>,
                datasets: [{
                    data: <?= $pie_chart_data ?? '[]' ?>,
                    backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                         callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += '₱' + context.parsed.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // Line Chart for Monthly Revenue vs. Loss Comparison
    const monthlySalesChartCanvas = document.getElementById('monthlySalesChart');
    if(monthlySalesChartCanvas) {
        new Chart(monthlySalesChartCanvas, {
            type: 'line', // Changed from 'bar' to 'line'
            data: {
                labels: <?= $comparison_chart_labels ?? '[]' ?>,
                datasets: [
                    {
                        label: 'Total Revenue',
                        data: <?= $comparison_revenue_data ?? '[]' ?>,
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        fill: true,
                        tension: 0.1
                    },
                    {
                        label: 'Potential Loss (Refunds)',
                        data: <?= $comparison_potential_loss_data ?? '[]' ?>,
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        borderColor: 'rgba(253, 126, 20, 1)',
                        fill: true,
                        tension: 0.1
                    },
                    {
                        label: 'Direct Loss (Unsellable)',
                        data: <?= $comparison_direct_loss_data ?? '[]' ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        fill: true,
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '₱' + value.toLocaleString(); }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    label += '₱' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });
    }


});
</script>
</body>
</html>

