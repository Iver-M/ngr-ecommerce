<?php
require_once dirname(__DIR__, 2) . '/core/paths.php';
include 'config/db.php';
include 'includes/header.php';
?>

<style>
    /* CSS for dark background */
    body {
        background: #000;
        color: #fff;
    }

    /* Custom Orange Button */
    .btn-orange-main {
        background: #ff5c1c;
        border: none;
        color: white;
        font-weight: 600;
        transition: background 0.3s ease;
    }
    .btn-orange-main:hover {
        background: #e64a0a;
        color: white;
    }
    
    /* --- NEW BUTTON STYLE --- */
    .btn-clear-wishlist {
        background: transparent;
        border: 1px solid #666;
        color: #999;
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
        height: 50px; /* Match btn-lg height */
        line-height: 1.5;
        border-radius: 0.3rem;
    }
    .btn-clear-wishlist:hover {
        background: #ef4444;
        color: white;
        border-color: #ef4444;
    }
    /* --- END NEW STYLE --- */

    /* Page Header */
    .product-detail-container {
        background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
        padding: 30px 0;
        margin-bottom: 30px;
        border-bottom: 2px solid #333;
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
    }
    
    .page-title i {
        color: #ff5c1c;
    }

    /* Product Cards - Modern Design (from products.php) */
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
        -webkit-line-clamp: 2; /* Limit to 2 lines */
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .product-card .card-title a {
        text-decoration: none;
        color: inherit;
    }
    
    .product-description {
        font-size: 0.85rem;
        color: #999;
        margin-bottom: 12px;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* Limit to 2 lines */
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-card .card-text {
        font-size: 1.25rem;
        font-weight: 700;
        color: #4ade80; /* Green price */
        margin-bottom: auto; /* Pushes actions to bottom */
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

</style>

<?php if (isset($_SESSION['message'])): ?>
    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4" style="background-color: #1e1e1e; border: 1px solid #333;">
                <div class="modal-body">
                    <h5 class="mb-3"><?= htmlspecialchars($_SESSION['message']); ?></h5>
                    <button type="button" class="btn btn-orange-main" data-bs-dismiss="modal">OK</button>
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
<?php
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- *** FIXED SQL QUERY *** ---
// Changed specific columns like p.company, p.brand to p.*
// This safely fetches all available columns from the products table.
$stmt = $pdo->prepare("
    SELECT 
        w.wishlist_id, 
        p.*,  -- This is the change
        p.id AS product_id, -- Keep this alias for the card logic
        c.name as category_name
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ?
");
$stmt->execute([$user_id]);
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
// --- *** END OF FIX *** ---

// Count the items for the new counter
$item_count = count($wishlist_items); 
?>

<div class="product-detail-container" style="padding-top: 80px;">
    <div class="container" style="padding-left: 50px;">
        <nav class="breadcrumb-nav">
            <a href="index.php">Home</a>
            <span>/</span>
            <a href="profile.php">Profile</a>
            <span>/</span>
            <span>Wishlist</span>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="page-title mb-1">
                    <i class="fas fa-heart"></i>
                    Wishlist
                </h1>
                <p class="mb-0 text-white-50">
                    <span class="fw-bold me-1"><?= $item_count ?></span> item(s) in your list
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center" style="padding-right: 20px;">
                <?php if (!empty($wishlist_items)): ?>
                    <!-- <form method="POST" action="add_all_to_cart.php" class="m-0">
                        <button type="submit" class="btn btn-md btn-orange-main">
                            <i class="fas fa-cart-plus"></i> Add All to Cart
                        </button>
                    </form> -->
                    <button type="button" class="btn btn-md btn-orange-main" data-bs-toggle="modal" data-bs-target="#clearWishlistModal" title="Clear Wishlist">
                        <i class="fas fa-trash-alt"></i> Clear All
                    </button>
                <?php endif; ?>
            </div>
            </div>
        </div>
</div>
<div class="container my-5">
    
    <?php if (empty($wishlist_items)): ?>
        <div class="text-center p-5 rounded" style="background-color: #1e1e1e;">
            <i class="fas fa-heart-broken" style="font-size: 4rem; color: #444;"></i>
            <h4 class="mt-3">Your wishlist is empty.</h4>
            <p class="text-white-50">Start adding some items you love!</p>
            <a href="products.php" class="btn btn-orange-main">
                <i class="fas fa-shopping-bag"></i> Start Shopping
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            
            <?php foreach ($wishlist_items as $item): 
                
                // Replicating logic from products.php
                $is_out_of_stock = $item['status'] === 'out of stock' || $item['stock'] <= 0;
                // This logic is now safe because $item['company'] or $item['brand'] will just be null if they don't exist
                $company = $item['company'] ?? $item['brand'] ?? $item['category_name'] ?? 'GymBRO';
                
                $description_teaser = '';
                if (!empty($item['details'])) {
                    $description_teaser = strlen($item['details']) > 80 
                        ? '...' . substr(strip_tags($item['details']), 0, 80) . '...' 
                        : '...' . strip_tags($item['details']);
                }
            ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card product-card <?= $is_out_of_stock ? 'out-of-stock' : '' ?>"
                         onclick="window.location.href='product.php?id=<?= $item['product_id'] ?>'">
                        
                        <span class="stock-badge <?= $is_out_of_stock ? 'out-of-stock' : 'in-stock' ?>">
                            <?= $is_out_of_stock ? 'Out of Stock' : 'In Stock' ?>
                        </span>
                        
                        <div class="image-container">
                            <div class="image-wrapper">
                                <a href="product.php?id=<?= $item['product_id'] ?>">
                                    <img src="uploads/images/<?= htmlspecialchars($item['image']) ?>"
                                         alt="<?= htmlspecialchars($item['name']) ?>"
                                         class="product-image">
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="product-company"><?= htmlspecialchars($company) ?></div>
                            
                            <h5 class="card-title"><?= htmlspecialchars($item['name']) ?></h5>
                            
                            <?php if ($description_teaser): ?>
                                <p class="product-description"><?= htmlspecialchars($description_teaser) ?></p>
                            <?php endif; ?>
                            
                            <p class="card-text">₱<?= number_format($item['price'], 2) ?></p>
                            
                            <div class="product-actions" onclick="event.stopPropagation()">
                                
                                <form method="POST" action="add_to_cart.php" style="margin: 0;">
                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn-cart" title="Add to Cart" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                                        <i class="fas fa-shopping-cart"></i>
                                    </button>
                                </form>
                                
                                <form method="POST" action="remove_from_wishlist.php" style="margin: 0;">
                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                    <button type="submit" class="btn-wishlist-filled" title="Remove from Wishlist">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="clearWishlistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4" style="background-color: #1e1e1e; border: 1px solid #333;">
            <div class="modal-body">
                <h5 class="mb-3">Are you sure?</h5>
                <p class="text-white-50 mb-4">This will remove all <?= $item_count ?> item(s) from your wishlist. This action cannot be undone.</p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="clear_wishlist.php" style="margin: 0;">
                        <button type="submit" class="btn btn-danger">Clear Wishlist</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
