<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../includes/header.php';

// --- START: HELPER FUNCTIONS FOR CATEGORY TREE ---

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

function is_branch_active(array $category, ?int $active_id): bool {
    if ($active_id === null) return false;
    if ($category['id'] == $active_id) return true;
    foreach ($category['children'] as $child) {
        if (is_branch_active($child, $active_id)) return true;
    }
    return false;
}

function render_category_list(array $categories, ?int $active_id = null, $level = 0) {
    foreach ($categories as $category) {
        $has_children = !empty($category['children']);
        $is_active = $category['id'] == $active_id;
        $is_branch_active_val = is_branch_active($category, $active_id);
        $padding_left = $level * 20;
        
        echo '<div class="category-item" style="padding-left: ' . $padding_left . 'px;">';
        echo '<a href="products.php?category=' . $category['id'] . '" class="category-link ' . ($is_active ? 'active' : '') . '">';
        if ($has_children) {
            echo '<i class="fas fa-folder' . ($is_branch_active_val ? '-open' : '') . '"></i> ';
        } else {
            echo '<i class="fas fa-tag"></i> ';
        }
        echo htmlspecialchars($category['name']);
        echo '</a>';
        echo '</div>';
        
        if ($has_children && $is_branch_active_val) {
            render_category_list($category['children'], $active_id, $level + 1);
        }
    }
}

// --- END: HELPER FUNCTIONS ---

// Modal Popup for session messages
if (isset($_SESSION['message'])): ?>
    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <h5 class="mb-3"><?= htmlspecialchars($_SESSION['message']); ?></h5>
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
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
<?php endif;

// Get filters from URL
$search = isset($_GET['search']) && is_string($_GET['search']) ? $_GET['search'] : '';
$sort_by = $_GET['sort_by'] ?? 'recently_added';
$selected_category_id = isset($_GET['category']) && !empty($_GET['category']) ? (int)$_GET['category'] : null;

// Price slider logic
$max_price_limit = 500000;
$min_price_default = 0;
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (int)$_GET['min_price'] : $min_price_default;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (int)$_GET['max_price'] : $max_price_limit;
if ($min_price > $max_price) {
    list($min_price, $max_price) = [$max_price, $min_price];
}

// Filter params
$filter_in_stock = isset($_GET['in_stock']);
$filter_product_type = $_GET['product_type'] ?? 'all';

// Build the query
$params = [];
$sql = "SELECT p.*, c.name as category_name FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_deleted = 0";

$sql .= " AND p.status != 'inactive'";

// Availability filter
if ($filter_in_stock) {
    $sql .= " AND p.status = 'active'";
}

// Product type filter based on top-level categories
if ($filter_product_type !== 'all') {
    // Find the category ID that matches the product type
    $stmt_type = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) LIKE ? AND parent_id IS NULL");
    $stmt_type->execute(['%' . $filter_product_type . '%']);
    $type_category = $stmt_type->fetch(PDO::FETCH_ASSOC);
    
    if ($type_category) {
        // Get all child categories recursively
        $stmt_cat_tree = $pdo->prepare("
            WITH RECURSIVE CategoryCTE AS (
                SELECT id FROM categories WHERE id = ?
                UNION ALL
                SELECT c.id FROM categories c JOIN CategoryCTE ccte ON c.parent_id = ccte.id
            )
            SELECT id FROM CategoryCTE
        ");
        $stmt_cat_tree->execute([$type_category['id']]);
        $type_category_ids = $stmt_cat_tree->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($type_category_ids)) {
            $placeholders_type = implode(',', array_fill(0, count($type_category_ids), '?'));
            $sql .= " AND p.category_id IN ($placeholders_type)";
            $params = array_merge($params, $type_category_ids);
        }
    }
}

// Search filter
if (!empty($search)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = '%' . $search . '%';
}

// Category filter logic
if ($selected_category_id) {
    $stmt_cat = $pdo->prepare("
        WITH RECURSIVE CategoryCTE AS (
            SELECT id FROM categories WHERE id = ?
            UNION ALL
            SELECT c.id FROM categories c JOIN CategoryCTE ccte ON c.parent_id = ccte.id
        )
        SELECT id FROM CategoryCTE
    ");
    $stmt_cat->execute([$selected_category_id]);
    $category_ids = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($category_ids)) {
        $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
        $sql .= " AND p.category_id IN ($placeholders)";
        $params = array_merge($params, $category_ids);
    }
}

// Price range filter
if ($min_price !== $min_price_default || $max_price !== $max_price_limit) {
    $sql .= " AND p.price BETWEEN ? AND ?";
    $params[] = $min_price;
    $params[] = $max_price;
}

// Sorting logic
switch ($sort_by) {
    case 'popularity': $sql .= " ORDER BY p.views DESC"; break;
    case 'price_asc': $sql .= " ORDER BY p.price ASC"; break;
    case 'price_desc': $sql .= " ORDER BY p.price DESC"; break;
    case 'name_asc': $sql .= " ORDER BY p.name ASC"; break;
    case 'name_desc': $sql .= " ORDER BY p.name DESC"; break;
    case 'recently_added': default: $sql .= " ORDER BY p.id DESC"; break;
}

// Execute the final query
$stmt_products = $pdo->prepare($sql);
$stmt_products->execute($params);
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Fetch the category tree structure
$category_tree = fetch_category_tree($pdo);

// Get top-level categories for product type filter
$top_level_categories = [];
foreach ($category_tree as $cat) {
    if ($cat['parent_id'] === null) {
        $top_level_categories[] = $cat;
    }
}

// Fetch wishlist for the current user
$wishlist_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt_wish = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt_wish->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt_wish->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch wishlist for the current user
$wishlist_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt_wish = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt_wish->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt_wish->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch reviews and ratings for all products
$product_reviews = [];
$stmt_reviews = $pdo->query("
    SELECT product_id, 
           AVG(rating) as avg_rating, 
           COUNT(*) as review_count 
    FROM reviews 
    GROUP BY product_id
");
while ($row = $stmt_reviews->fetch(PDO::FETCH_ASSOC)) {
    $product_reviews[$row['product_id']] = [
        'avg_rating' => round($row['avg_rating'], 1),
        'review_count' => $row['review_count']
    ];
}

// Get selected category name for breadcrumb and page title
$selected_category_name = 'All Products';
$page_icon = 'fa-shopping-bag';

if ($filter_product_type !== 'all') {
    // Set title based on product type
    foreach ($top_level_categories as $cat) {
        if (stripos($cat['name'], $filter_product_type) !== false) {
            $selected_category_name = $cat['name'];
            // Set icon based on category name
            if (stripos($cat['name'], 'equipment') !== false || stripos($cat['name'], 'gear') !== false) {
                $page_icon = 'fa-dumbbell';
            } elseif (stripos($cat['name'], 'supplement') !== false) {
                $page_icon = 'fa-capsules';
            }
            break;
        }
    }
} elseif ($selected_category_id) {
    $stmt_cat_name = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt_cat_name->execute([$selected_category_id]);
    $cat_result = $stmt_cat_name->fetch(PDO::FETCH_ASSOC);
    if ($cat_result) {
        $selected_category_name = $cat_result['name'];
        // Determine icon based on category name
        if (stripos($cat_result['name'], 'equipment') !== false || stripos($cat_result['name'], 'gear') !== false
         || stripos($cat_result['name'], 'Home') !== false || stripos($cat_result['name'], 'Machines') !== false
         || stripos($cat_result['name'], 'Stepper') !== false || stripos($cat_result['name'], 'Stationary') !== false
         || stripos($cat_result['name'], 'Treadmills') !== false || stripos($cat_result['name'], 'Elliptical') !== false
         || stripos($cat_result['name'], 'Accessories') !== false || stripos($cat_result['name'], 'Flooring') !== false
         || stripos($cat_result['name'], 'Gym') !== false || stripos($cat_result['name'], 'Racks') !== false
         || stripos($cat_result['name'], 'Bars') !== false || stripos($cat_result['name'], 'Benches') !== false
         || stripos($cat_result['name'], 'Dumbbells') !== false || stripos($cat_result['name'], 'Kettlebells') !== false
         || stripos($cat_result['name'], 'Balls') !== false || stripos($cat_result['name'], 'Sand') !== false
         || stripos($cat_result['name'], 'Weight') !== false || stripos($cat_result['name'], 'Weighted') !== false
         || stripos($cat_result['name'], 'Weightlifting') !== false) {
            $page_icon = 'fa-dumbbell';
        } elseif (stripos($cat_result['name'], 'supplement') !== false || stripos($cat_result['name'], 'Acids') !== false
        || stripos($cat_result['name'], 'Creatine') !== false || stripos($cat_result['name'], 'Energy') !== false
        || stripos($cat_result['name'], 'Fat') !== false || stripos($cat_result['name'], 'Hydration') !== false
        || stripos($cat_result['name'], 'Post') !== false || stripos($cat_result['name'], 'Pre') !== false
        || stripos($cat_result['name'], 'Protein') !== false || stripos($cat_result['name'], 'Vitamins') !== false
        || stripos($cat_result['name'], 'Minerals') !== false || stripos($cat_result['name'], 'Home') !== false) {
            $page_icon = 'fa-capsules';
        }
    }
}
?>

<style>
    /* Page Header */
    .product-detail-container {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
        padding: 30px 0;
        margin-bottom: 30px;
        border-bottom: 2px solid #333;
    }

    .page-header-content {
        /* Wrapper for breadcrumbs and title/search */
    }

    /* NEW: Wrapper for Title and Search */
    .page-header-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap; /* Allow wrapping on small screens */
    }

    /* NEW: Header Search Form */
    .header-search-form {
        flex-grow: 1;
        max-width: 450px; /* Limit max width */
        min-width: 250px; /* Ensure it's usable on wrap */
    }

    .header-search-form .input-group {
        width: 100%;
        border-radius: 50px; /* Fully rounded */
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden; /* Keep rounded corners */
        transition: all 0.3s ease;
    }
    
    .header-search-form .input-group:focus-within {
        background: rgba(255, 255, 255, 0.2);
        border-color: #ff5c1c;
        box-shadow: 0 0 10px rgba(255, 92, 28, 0.3);
    }

    .header-search-form .form-control {
        background: transparent;
        border: none;
        color: white;
        padding: 10px 20px;
        font-size: 0.95rem;
        border-radius: 50px 0 0 50px;
    }

    .header-search-form .form-control:focus {
        box-shadow: none;
        background: transparent;
    }
    
    .header-search-form .form-control::placeholder {
        color: #aaa;
    }

    .header-search-form .btn-search {
        background: #ff5c1c;
        color: white;
        border: none;
        padding: 0 25px;
        font-size: 1.1rem;
        border-radius: 0 50px 50px 0;
        transition: background 0.3s ease;
    }

    .header-search-form .btn-search:hover {
        background: #e64a0a;
    }
    
    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        margin-bottom: 15px;
    }
    
    .breadcrumb-nav a {
        color: #ccc;
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .breadcrumb-nav a:hover {
        color: #ff5c1c;
    }
    
    .breadcrumb-nav span {
        color: #666;
    }
    
    .breadcrumb-nav span:last-child {
        color: #fff;
        font-weight: 600;
    }
    
    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-shrink: 0; /* Prevent title from shrinking */
    }
    
    .page-title i {
        color: #ff5c1c;
    }

    /* Filter Sidebar */
    .filter-sidebar {
        position: fixed;
        left: -350px;
        top: 0;
        width: 350px;
        height: 100vh;
        background: linear-gradient(180deg, #1a1a1a 0%, #2a2a2a 100%);
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
        transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1050;
        overflow-y: auto;
        padding: 80px 20px 20px 20px;
    }
    
    .filter-sidebar.active {
        left: 0;
    }
    
    .filter-toggle-btn {
        position: fixed;
        top: 100px;
        left: 0px;
        z-index: 1051;
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ff5c1c;
        border: none;
        border-radius: 0 8px 8px 0;
        padding: 15px 18px;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
        transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1),  width 0.5s cubic-bezier(0.4, 0, 0.2, 1), background 0.3s ease, padding 0.3s ease;
        width: 130px;
    }
    
    .filter-toggle-btn:hover {
        background: #e64a0a;
        padding-right: 18px;
    }
    
    .filter-toggle-btn.active {
        width: 55px;
        left: 350px;
    }

    .filter-toggle-btn i {
        transition: transform 0.4s ease;  
    }

    .filter-toggle-btn span {
        white-space: nowrap;
        transition: opacity 0.3s ease, transform 0.5s ease;
    }

    .filter-toggle-btn.active span {
        opacity: 0;
        transform: translateX(-20px);
    }
    
    .filter-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1049;
        display: none;
    }
    
    .filter-overlay.active {
        display: block;
    }

    /* Filter Sections */
    .filter-section {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .filter-section-title {
        color: #ff5c1c;
        font-weight: 700;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-section-title i {
        font-size: 1.1rem;
    }

    /* Product Type Buttons */
    .product-type-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .btn-product-type {
        width: 100%;
        padding: 15px;
        border: 2px solid #444;
        background: transparent;
        color: #ccc;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }
    
    .btn-product-type:hover {
        border-color: #ff5c1c;
        color: #ff5c1c;
        background: rgba(255, 92, 28, 0.1);
    }
    
    .btn-product-type.active {
        border-color: #ff5c1c;
        background: #ff5c1c;
        color: white;
    }
    
    .btn-product-type i {
        font-size: 1.5rem;
    }
    
    .btn-product-type span {
        font-size: 1rem;
    }

    /* Category List in Filter */
    .category-item {
        margin-bottom: 8px;
    }
    
    .category-link {
        display: block;
        padding: 10px 15px;
        color: #ccc;
        text-decoration: none;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    .category-link:hover {
        background: rgba(255, 92, 28, 0.1);
        color: #ff5c1c;
        padding-left: 20px;
    }
    
    .category-link.active {
        background: #ff5c1c;
        color: white;
        font-weight: 600;
    }
    
    .category-link i {
        margin-right: 8px;
        width: 16px;
    }

    /* Form Controls */
    .filter-sidebar .form-control,
    .filter-sidebar .form-select {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        border-radius: 8px;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }
    
    .filter-sidebar .form-control:focus,
    .filter-sidebar .form-select:focus {
        background: rgba(0, 0, 0, 0.4);
        border-color: #ff5c1c;
        color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(255, 92, 28, 0.25);
    }
    
    .filter-sidebar .form-control::placeholder {
        color: #888;
    }

    /* Price Slider */
    .price-slider-group {
        position: relative;
        padding: 40px 0 20px 0;
        height: 80px;
    }
    
    .price-range-display {
        display: flex;
        justify-content: space-between;
        font-weight: 600;
        margin-bottom: 15px;
        font-size: 0.95rem;
        color: #fff;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
    }
    
    .slider-track {
        position: absolute;
        width: 100%;
        height: 6px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .price-slider-group input[type="range"] {
        position: absolute;
        width: 100%;
        -webkit-appearance: none;
        appearance: none;
        background: transparent;
        pointer-events: none;
        margin: 0;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .price-slider-group input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        background: #ff5c1c;
        cursor: grab;
        border-radius: 50%;
        pointer-events: all;
        box-shadow: 0 2px 8px rgba(255, 92, 28, 0.5);
        border: 3px solid white;
    }
    
    .price-slider-group input[type="range"]::-moz-range-thumb {
        width: 20px;
        height: 20px;
        background: #ff5c1c;
        cursor: grab;
        border-radius: 50%;
        pointer-events: all;
        box-shadow: 0 2px 8px rgba(255, 92, 28, 0.5);
        border: 3px solid white;
    }

    /* Checkbox */
    .filter-sidebar .form-check-input {
        width: 20px;
        height: 20px;
        background-color: rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.3);
        cursor: pointer;
    }
    
    .filter-sidebar .form-check-input:checked {
        background-color: #ff5c1c;
        border-color: #ff5c1c;
    }
    
    .filter-sidebar .form-check-label {
        color: #fff;
        font-weight: 500;
        margin-left: 8px;
    }

    /* Reset Button */
    .btn-reset-filters {
        background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
        border: none;
        color: white;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        width: 100%;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    
    .btn-reset-filters:hover {
        background: linear-gradient(135deg, #545b62 0%, #3d4247 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        color: white;
    }

    /* Product Cards - Modern Design */
    .product-card {
        background: linear-gradient(145deg, #2a2a2a 0%, #1f1f1f 100%);
        border: 1px solid #333;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(255, 92, 28, 0.2);
        border-color: #ff5c1c;
    }
    
    .stock-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        z-index: 10;
        letter-spacing: 0.5px;
    }
    
    .stock-badge.in-stock {
        background: #10b981;
        color: white;
    }
    
    .stock-badge.out-of-stock {
        background: #ef4444;
        color: white;
    }
    
    .product-card .image-container {
        position: relative;
        width: 100%;
        padding-top: 100%;
        overflow: hidden;
        background: #1a1a1a;
        transition: all 0.3s ease;
    }
    
    .product-card.out-of-stock .image-container {
        background: #0a0a0a;
    }
    
    .product-card.out-of-stock .image-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 1;
    }
    
    .product-card .image-wrapper {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .product-card .image-wrapper a {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .product-card .product-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: transform 0.3s ease, filter 0.3s ease;
    }
    
    .product-card.out-of-stock .product-image {
        filter: brightness(0.4) grayscale(0.3);
    }
    
    .product-card:hover .product-image {
        transform: scale(1.05);
    }
    
    .product-card.out-of-stock:hover .product-image {
        transform: scale(1.02);
    }
    
    .product-card .card-body {
        padding: 20px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    
    .product-company {
        font-size: 0.75rem;
        color: #ff5c1c;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 8px;
    }
    
    .product-card .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        margin-bottom: 8px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* ADD THIS NEW CSS */
.product-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 0.85rem;
}

.product-rating .stars {
    color: #ffa500;
    display: flex;
    gap: 2px;
}

.product-rating .stars.no-rating {
    color: #444; /* Darker gray for empty stars */
}

.product-rating .rating-text {
    color: #999;
}

.product-rating .rating-text.no-reviews {
    color: #666;
    font-style: italic;
}
/* END NEW CSS */
    
    .product-description {
        font-size: 0.85rem;
        color: #999;
        margin-bottom: 12px;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-card .card-text {
        font-size: 1.25rem;
        font-weight: 700;
        color: #4ade80;
        margin-bottom: auto;
    }
    
    .product-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .btn-cart,
    .btn-wishlist-outline,
    .btn-wishlist-filled {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .btn-cart {
        background: #ff5c1c;
        color: white;
    }
    
    .btn-cart:hover {
        background: #e64a0a;
        transform: scale(1.1);
    }
    
    .btn-wishlist-outline {
        background: transparent;
        color: #fbbf24;
        border: 2px solid #fbbf24;
    }
    
    .btn-wishlist-outline:hover {
        background: #fbbf24;
        color: #1a1a1a;
        transform: scale(1.1);
    }
    
    .btn-wishlist-filled {
        background: #fbbf24;
        color: #1a1a1a;
    }
    
    .btn-wishlist-filled:hover {
        background: #f59e0b;
        transform: scale(1.1);
    }

    /* This ONLY disables the cart button when out of stock */
    .product-card.out-of-stock .btn-cart {
        pointer-events: none;
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Wishlist buttons remain active even if out of stock */
    .product-card.out-of-stock .btn-wishlist-outline,
    .product-card.out-of-stock .btn-wishlist-filled {
        pointer-events: auto;
        opacity: 1;
        cursor: pointer;
    }
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .page-header-wrapper { /* UPDATED */
            flex-direction: column;
            align-items: flex-start;
        }
        .header-search-form { /* UPDATED */
            width: 100%;
            max-width: 100%;
        }

        .filter-sidebar {
            width: 300px;
            left: -300px;
        }
        
        .filter-toggle-btn.active {
            left: 300px;
        }
        
        .page-title {
            font-size: 1.8rem;
        }
    }
    
    /* Keep sidebar open on page load if it was open */
    .filter-sidebar.keep-open {
        left: 0;
    }
    
    .filter-toggle-btn.keep-open {
        left: 350px;
    }
    
    @media (max-width: 768px) {
        .filter-toggle-btn.keep-open {
            left: 300px;
        }
    }

    /* Flying Cart Animation */
.flying-image {
    position: fixed;
    z-index: 9999;
    width: 80px;
    height: 80px;
    border-radius: 12px;
    object-fit: cover;
    pointer-events: none;
    box-shadow: 0 10px 40px rgba(255, 92, 28, 0.6);
    border: 3px solid #ff5c1c;
}

@keyframes flyToCart {
    0% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
    50% {
        transform: scale(0.8) rotate(10deg);
        opacity: 0.8;
    }
    100% {
        transform: scale(0.2) rotate(20deg);
        opacity: 0;
    }
}

@keyframes cartPulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.3);
    }
}

.cart-pulse {
    animation: cartPulse 0.5s ease;
}

.success-toast {
    position: fixed;
    top: 100px;
    right: 20px;
    background: linear-gradient(135deg, #00ff88, #00cc6e);
    color: #000;
    padding: 15px 25px;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 10px 30px rgba(0, 255, 136, 0.4);
    z-index: 10000;
    animation: slideIn 0.5s ease, slideOut 0.5s ease 2.5s;
    display: flex;
    align-items: center;
    gap: 10px;
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}
</style>

<button class="filter-toggle-btn <?php echo isset($_COOKIE['filter_open']) && $_COOKIE['filter_open'] === 'true' ? 'keep-open' : ''; ?>" id="filterToggleBtn" onclick="toggleFilters()">
    <i class="fas fa-filter"></i>
    <span>Filters</span>
</button>

<div class="filter-overlay <?php echo isset($_COOKIE['filter_open']) && $_COOKIE['filter_open'] === 'true' ? 'active' : ''; ?>" id="filterOverlay" onclick="toggleFilters()"></div>

<div class="filter-sidebar <?php echo isset($_COOKIE['filter_open']) && $_COOKIE['filter_open'] === 'true' ? 'keep-open' : ''; ?>" id="filterSidebar">
    <form method="GET" action="products.php" id="filtersForm">
        <input type="hidden" name="category" value="<?= htmlspecialchars($selected_category_id ?? '') ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"> <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-boxes"></i>
                Product Type
            </div>
            <div class="product-type-buttons">
                <a href="?product_type=all&search=<?= urlencode($search) ?>&sort_by=<?= $sort_by ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?><?= $filter_in_stock ? '&in_stock=1' : '' ?>" 
                   class="btn-product-type <?= $filter_product_type === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-th"></i>
                    <span>All Products</span>
                </a>
                <?php foreach ($top_level_categories as $top_cat): 
                    $cat_name_lower = strtolower($top_cat['name']);
                    $icon = 'fa-box';
                    if (stripos($top_cat['name'], 'equipment') !== false || stripos($top_cat['name'], 'gear') !== false) {
                        $icon = 'fa-dumbbell';
                    } elseif (stripos($top_cat['name'], 'supplement') !== false) {
                        $icon = 'fa-capsules';
                    }
                    $is_active = $filter_product_type !== 'all' && stripos($top_cat['name'], $filter_product_type) !== false;
                ?>
                <a href="?product_type=<?= urlencode($cat_name_lower) ?>&search=<?= urlencode($search) ?>&sort_by=<?= $sort_by ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?><?= $filter_in_stock ? '&in_stock=1' : '' ?>" 
                   class="btn-product-type <?= $is_active ? 'active' : '' ?>">
                    <i class="fas <?= $icon ?>"></i>
                    <span><?= htmlspecialchars($top_cat['name']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-tag"></i>
                Price Range
            </div>
            <div class="price-slider-group">
                <div class="price-range-display">
                    <span id="minPriceDisplay">₱<?= number_format($min_price, 0) ?></span>
                    <span id="maxPriceDisplay">₱<?= number_format($max_price, 0) ?></span>
                </div>
                
                <div class="slider-track"></div>
                
                <input type="range" min="<?= $min_price_default ?>" max="<?= $max_price_limit ?>" step="100"
                       value="<?= $min_price ?>" id="minPriceSlider"
                       onmouseup="submitPriceFilter()" ontouchend="submitPriceFilter()">
                
                <input type="range" min="<?= $min_price_default ?>" max="<?= $max_price_limit ?>" step="100"
                       value="<?= $max_price ?>" id="maxPriceSlider"
                       onmouseup="submitPriceFilter()" ontouchend="submitPriceFilter()">

                <input type="hidden" name="min_price" id="minPriceHidden" value="<?= $min_price ?>">
                <input type="hidden" name="max_price" id="maxPriceHidden" value="<?= $max_price ?>">
                <input type="hidden" name="product_type" value="<?= htmlspecialchars($filter_product_type) ?>">
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-list"></i>
                Categories
            </div>
            <div class="category-item">
                <a href="products.php" class="category-link <?= !$selected_category_id ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i> All Categories
                </a>
            </div>
            <?php render_category_list($category_tree, $selected_category_id); ?>
        </div>

        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-sort"></i>
                Sort By
            </div>
            <select class="form-select" name="sort_by" onchange="this.form.submit()">
                <option value="recently_added" <?= $sort_by == 'recently_added' || $sort_by == '' ? 'selected' : '' ?>>Recently Added</option>
                <option value="popularity" <?= $sort_by == 'popularity' ? 'selected' : '' ?>>Most Popular</option>
                <option value="price_asc" <?= $sort_by == 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort_by == 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="name_asc" <?= $sort_by == 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                <option value="name_desc" <?= $sort_by == 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
            </select>
        </div>

        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-check-circle"></i>
                Availability
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="in_stock" id="inStockCheck"
                       value="1" <?= $filter_in_stock ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label" for="inStockCheck">
                    Only Show In Stock
                </label>
            </div>
        </div>
        
        <div class="filter-section">
            <a href="products.php" class="btn btn-reset-filters">
                <i class="fas fa-redo"></i> Reset All Filters
            </a>
        </div>
    </form>
</div>

<div class="product-detail-container" style="padding-top: 80px;">
    <div class="container" style="padding-left: 50px;">
        <div class="page-header-content">
            <div class="breadcrumb-nav">
                <a href="index.php">Home</a>
                <span>/</span>
                <a href="products.php">Products</a>
                <?php if ($selected_category_id): ?>
                    <span>/</span>
                    <span><?= htmlspecialchars($selected_category_name) ?></span>
                <?php endif; ?>
            </div>
            
            <div class="page-header-wrapper">
                <h1 class="page-title">
                    <i class="fas <?= $page_icon ?>"></i>
                    <?= htmlspecialchars($selected_category_name) ?>
                </h1>

                <form method="GET" action="products.php" class="header-search-form" style="padding-right: 20px;">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($selected_category_id ?? '') ?>">
                    <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                    <input type="hidden" name="min_price" value="<?= htmlspecialchars($min_price) ?>">
                    <input type="hidden" name="max_price" value="<?= htmlspecialchars($max_price) ?>">
                    <input type="hidden" name="product_type" value="<?= htmlspecialchars($filter_product_type) ?>">
                    <?php if ($filter_in_stock): ?>
                        <input type="hidden" name="in_stock" value="1">
                    <?php endif; ?>
                    
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search <?= $selected_category_id ? htmlspecialchars($selected_category_name) : 'all products' ?>..."
                               value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-search" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open" style="font-size: 4rem; color: #666; margin-bottom: 20px;"></i>
                    <p class="text-muted fs-5">No products found matching your criteria.</p>
                    <a href="products.php" class="btn btn-reset-filters" style="width: auto; padding: 12px 30px;">
                        Clear Filters
                    </a>
                </div>
                
            <?php else: ?>
                <div class="row">
                    <?php foreach ($products as $row):
                        $in_wishlist = in_array($row['id'], $wishlist_items);
                        $is_out_of_stock = $row['status'] === 'out of stock';
                        $company = $row['company'] ?? $row['brand'] ?? $row['category_name'] ?? 'GymBRO';
                        
                          // Get product reviews
                        $has_reviews = isset($product_reviews[$row['id']]);
                        $avg_rating = $has_reviews ? $product_reviews[$row['id']]['avg_rating'] : 0;
                        $review_count = $has_reviews ? $product_reviews[$row['id']]['review_count'] : 0;

                        // Get product description teaser
                        $description_teaser = '';
                        if (!empty($row['details'])) {
                            $description_teaser = strlen($row['details']) > 80 
                                ? '...' . substr($row['details'], 0, 80) . '...' 
                                : '...' . $row['details'];
                        }
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card product-card <?= $is_out_of_stock ? 'out-of-stock' : '' ?>"
                                 onclick="window.location.href='product.php?id=<?= $row['id'] ?>'">
                                
                                <span class="stock-badge <?= $is_out_of_stock ? 'out-of-stock' : 'in-stock' ?>">
                                    <?= $is_out_of_stock ? 'Out of Stock' : 'In Stock' ?>
                                </span>
                                
                                <div class="image-container">
                                    <div class="image-wrapper">
                                        <a href="product.php?id=<?= $row['id'] ?>">
                                            <img src="uploads/images/<?= htmlspecialchars($row['image']) ?>"
                                                 alt="<?= htmlspecialchars($row['name']) ?>"
                                                 class="product-image">
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="product-company"><?= htmlspecialchars($company) ?></div>
                                    <h5 class="card-title"><?= htmlspecialchars($row['name']) ?></h5>
                                    
 <!-- Always show rating section -->
    <div class="product-rating">
        <div class="stars <?= !$has_reviews ? 'no-rating' : '' ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?= $has_reviews && $i <= round($avg_rating) ? '★' : '☆' ?>
            <?php endfor; ?>
        </div>
        <span class="rating-text <?= !$has_reviews ? 'no-reviews' : '' ?>">
            <?php if ($has_reviews): ?>
                <?= $avg_rating ?> (<?= $review_count ?>)
            <?php else: ?>
                No reviews yet
            <?php endif; ?>
        </span>
    </div>
        
                                    <?php if ($description_teaser): ?>
                                        <p class="product-description"><?= htmlspecialchars($description_teaser) ?></p>
                                    <?php endif; ?>
                                    
                                    <p class="card-text">₱<?= number_format($row['price'], 2) ?></p>
                                    
                                    <div class="product-actions" onclick="event.stopPropagation()">
                                      <form method="POST" action="add_to_cart.php" style="margin: 0;" onsubmit="return handleAddToCartFromCard(event, this)">
                                            <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="ajax" value="1">
                                            <button type="submit" class="btn-cart" title="Add to Cart">
                                                <i class="fas fa-shopping-cart"></i>
                                            </button>
                                        </form>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <?php if ($in_wishlist): ?>
                                                <form method="POST" action="remove_from_wishlist.php" style="margin: 0;">
                                                    <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="btn-wishlist-filled" title="Remove from Wishlist">
                                                        <i class="fas fa-heart"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="add_to_wishlist.php" style="margin: 0;">
                                                    <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                                    <button type="submit" class="btn-wishlist-outline" title="Add to Wishlist">
                                                        <i class="far fa-heart"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Filter toggle functionality
    function toggleFilters() {
        const sidebar = document.getElementById('filterSidebar');
        const overlay = document.getElementById('filterOverlay');
        const toggleBtn = document.getElementById('filterToggleBtn');
        
        const isOpen = sidebar.classList.contains('active') || sidebar.classList.contains('keep-open');
        
        if (isOpen) {
            sidebar.classList.remove('active', 'keep-open');
            overlay.classList.remove('active');
            toggleBtn.classList.remove('active', 'keep-open');
            document.cookie = "filter_open=false; path=/; max-age=86400";
        } else {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            toggleBtn.classList.add('active');
            document.cookie = "filter_open=true; path=/; max-age=86400";
        }
    }
    
    // Initialize filter state on page load
    document.addEventListener("DOMContentLoaded", function () {
        const sidebar = document.getElementById('filterSidebar');
        const overlay = document.getElementById('filterOverlay');
        const toggleBtn = document.getElementById('filterToggleBtn');
        
        if (sidebar.classList.contains('keep-open')) {
            sidebar.classList.remove('keep-open');
            sidebar.classList.add('active');
            overlay.classList.add('active');
            toggleBtn.classList.remove('keep-open');
            toggleBtn.classList.add('active');
        }
    });

    // Price slider functionality
    document.addEventListener("DOMContentLoaded", function () {
        const minSlider = document.getElementById('minPriceSlider');
        const maxSlider = document.getElementById('maxPriceSlider');
        const minDisplay = document.getElementById('minPriceDisplay');
        const maxDisplay = document.getElementById('maxPriceDisplay');
        const minHidden = document.getElementById('minPriceHidden');
        const maxHidden = document.getElementById('maxPriceHidden');
        const form = document.getElementById('filtersForm');

        if (!minSlider) return; // Guard if sliders don't exist

        const formatPrice = (price) => `₱${Number(price).toLocaleString('en-US', { minimumFractionDigits: 0 })}`;

        function updateSliderValues() {
            let minVal = parseInt(minSlider.value);
            let maxVal = parseInt(maxSlider.value);

            if (minVal > maxVal) {
                [minVal, maxVal] = [maxVal, minVal];
                minSlider.value = minVal;
                maxSlider.value = maxVal;
            }
            
            minDisplay.textContent = formatPrice(minVal);
            maxDisplay.textContent = formatPrice(maxVal);
            minHidden.value = minVal;
            maxHidden.value = maxVal;
        }

        updateSliderValues();
        minSlider.addEventListener('input', updateSliderValues);
        maxSlider.addEventListener('input', updateSliderValues);

        window.submitPriceFilter = function() {
            setTimeout(() => {
                updateSliderValues();
                form.submit();
            }, 50);
        };
    });

    // Close filters when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('filterSidebar');
        const toggleBtn = document.getElementById('filterToggleBtn');
        
        if ((sidebar.classList.contains('active') || sidebar.classList.contains('keep-open')) && 
            !sidebar.contains(event.target) && 
            !toggleBtn.contains(event.target)) {
            toggleFilters();
        }
    });

    
</script> 
<script>
function handleAddToCartFromCard(event, form) {
    event.preventDefault();
    
    const formData = new FormData(form);
    const productImage = form.closest('.product-card').querySelector('.product-image');
    const cartIcon = document.querySelector('.fa-shopping-cart').closest('.icon-wrapper');
    
    if (!productImage || !cartIcon) {
        form.submit();
        return false;
    }
    
    const flyingImg = document.createElement('img');
    flyingImg.src = productImage.src;
    flyingImg.className = 'flying-image';
    
    const imgRect = productImage.getBoundingClientRect();
    const cartRect = cartIcon.getBoundingClientRect();
    
    flyingImg.style.left = imgRect.left + 'px';
    flyingImg.style.top = imgRect.top + 'px';
    
    document.body.appendChild(flyingImg);
    
    setTimeout(() => {
        flyingImg.style.transition = 'all 0.8s cubic-bezier(0.5, 0, 0.5, 1)';
        flyingImg.style.left = cartRect.left + (cartRect.width / 2) - 40 + 'px';
        flyingImg.style.top = cartRect.top + (cartRect.height / 2) - 40 + 'px';
        flyingImg.style.animation = 'flyToCart 0.8s ease-in';
    }, 10);
    
    setTimeout(() => {
        flyingImg.remove();
        cartIcon.classList.add('cart-pulse');
        setTimeout(() => cartIcon.classList.remove('cart-pulse'), 500);
    }, 800);
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessToast(data.message || 'Product added to cart!');
            if (data.cart_count) {
                updateCartCount(data.cart_count);
            }
        } else {
            alert(data.message || 'Failed to add product to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        form.submit();
    });
    
    return false;
}

function showSuccessToast(message) {
    const toast = document.createElement('div');
    toast.className = 'success-toast';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function updateCartCount(count) {
    const badge = document.querySelector('.fa-shopping-cart').parentElement.querySelector('.icon-badge');
    if (badge) {
        badge.textContent = count;
    } else if (count > 0) {
        const iconWrapper = document.querySelector('.fa-shopping-cart').parentElement;
        const newBadge = document.createElement('span');
        newBadge.className = 'icon-badge';
        newBadge.textContent = count;
        iconWrapper.appendChild(newBadge);
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>