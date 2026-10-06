<?php
session_start();
include __DIR__ . '/../../config/db.php';
include __DIR__ . '/../../includes/header.php';

// Validate product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p>Invalid product ID.</p>";
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$product_id = (int)$_GET['id'];

// Update views
$stmt_update_views = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$stmt_update_views->execute([$product_id]);

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product || $product['status'] === 'inactive') {
    echo "<p>Product not found.</p>";
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch reviews
$stmt_reviews = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC");
$stmt_reviews->execute([$product_id]);
$reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$stmt_avg = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ?");
$stmt_avg->execute([$product_id]);
$review_stats = $stmt_avg->fetch(PDO::FETCH_ASSOC);
$avg_rating = $review_stats['avg_rating'] ? round($review_stats['avg_rating'], 1) : 0;
$review_count = $review_stats['review_count'];

// Check wishlist status
$is_in_wishlist = false;
if (isset($_SESSION['user_id'])) {
    $stmt_wishlist = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt_wishlist->execute([$_SESSION['user_id'], $product_id]);
    if ($stmt_wishlist->fetchColumn() > 0) {
        $is_in_wishlist = true;
    }
}
?>

<style>
/* ===== PRODUCT PAGE STYLES ===== */
.product-detail-container {
    background: #000;
    color: #fff;
    padding: 100px 0 40px;
}

.product-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Breadcrumb */
.breadcrumb-nav {
    margin-bottom: 30px;
    font-size: 0.9rem;
}

.breadcrumb-nav a {
    color: #ff5c1c;
    text-decoration: none;
    transition: color 0.2s;
}

.breadcrumb-nav a:hover {
    color: #ff8c42;
}

.breadcrumb-nav span {
    color: #666;
    margin: 0 10px;
}

/* Main Product Section */
.product-main-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    margin-bottom: 60px;
}

@media (max-width: 992px) {
    .product-main-section {
        grid-template-columns: 1fr;
        gap: 40px;
    }
}

/* Image Gallery */
.product-gallery {
    position: sticky;
    top: 120px;
    height: fit-content;
}

.main-product-image {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    background: linear-gradient(145deg, #1a1a1a, #252525);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
}

.main-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.main-product-image:hover img {
    transform: scale(1.05);
}

.thumbnail-gallery {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding: 10px 0;
}

.thumbnail-gallery::-webkit-scrollbar {
    height: 6px;
}

.thumbnail-gallery::-webkit-scrollbar-track {
    background: #1a1a1a;
    border-radius: 10px;
}

.thumbnail-gallery::-webkit-scrollbar-thumb {
    background: #ff5c1c;
    border-radius: 10px;
}

.thumbnail-item {
    flex-shrink: 0;
    width: 100px;
    height: 100px;
    background: #1a1a1a;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.3s ease;
}

.thumbnail-item:hover,
.thumbnail-item.active {
    border-color: #ff5c1c;
    transform: translateY(-5px);
}

.thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Product Info */
.product-info-section {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.product-title-row {
    padding-bottom: 20px;
    border-bottom: 1px solid #333;
}

.product-name {
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 15px;
    line-height: 1.2;
}

.product-rating-bar {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 10px;
}

.rating-stars {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 1.2rem;
    color: #ffa500;
}

.rating-number {
    font-weight: 600;
    color: #fff;
}

.review-link {
    color: #ff5c1c;
    text-decoration: none;
    font-size: 0.95rem;
    transition: color 0.2s;
}

.review-link:hover {
    color: #ff8c42;
    text-decoration: underline;
}

.product-price-section {
    padding: 25px 0;
    border-bottom: 1px solid #333;
}

.current-price {
    font-size: 3rem;
    font-weight: 700;
    color: #00ff88;
    text-shadow: 0 0 20px rgba(0, 255, 136, 0.3);
}

.stock-status {
    margin-top: 15px;
    padding: 12px 20px;
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.1), rgba(0, 255, 136, 0.05));
    border-left: 4px solid #00ff88;
    border-radius: 8px;
    font-size: 0.95rem;
}

.stock-status.out-of-stock {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(220, 38, 38, 0.05));
    border-left-color: #dc2626;
    color: #ff6b6b;
}

/* Add to Cart Section */
.add-to-cart-section {
    padding: 25px 0;
    border-bottom: 1px solid #333;
}

.quantity-selector {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.quantity-selector label {
    font-weight: 600;
    font-size: 1.1rem;
}

.quantity-input {
    width: 100px;
    padding: 12px;
    background: #1a1a1a;
    border: 2px solid #333;
    border-radius: 10px;
    color: #fff;
    font-size: 1.1rem;
    text-align: center;
    transition: border-color 0.3s;
}

.quantity-input:focus {
    outline: none;
    border-color: #ff5c1c;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn-add-cart {
    flex: 1;
    min-width: 200px;
    padding: 18px 30px;
    background: linear-gradient(135deg, #ff5c1c, #ff8c42);
    border: none;
    border-radius: 12px;
    color: #000;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-add-cart:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(255, 92, 28, 0.4);
}

.btn-add-cart:disabled {
    background: #333;
    color: #666;
    cursor: not-allowed;
}

.btn-wishlist {
    padding: 18px 25px;
    background: #1a1a1a;
    border: 2px solid #333;
    border-radius: 12px;
    color: #ff5c1c;
    font-size: 1.3rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-wishlist:hover {
    background: #ff5c1c;
    color: #000;
    border-color: #ff5c1c;
    transform: translateY(-2px);
}

.btn-wishlist.in-wishlist {
    background: #ff5c1c;
    color: #000;
    border-color: #ff5c1c;
}

.btn-wishlist.in-wishlist:hover {
    background: #e65118;
    border-color: #e65118;
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

/* Cart Icon Pulse Animation */
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

/* Success Toast */
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

/* Product Description */
.product-description {
    padding: 25px 0;
}

.description-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 15px;
}

.description-text {
    color: #ccc;
    line-height: 1.8;
    font-size: 1.05rem;
}

/* Tabbed Information Section */
.product-tabs-section {
    margin: 60px 0;
}

.tab-navigation {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #333;
    margin-bottom: 30px;
    overflow-x: auto;
}

.tab-btn {
    padding: 15px 30px;
    background: transparent;
    border: none;
    color: #999;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    position: relative;
    transition: color 0.3s;
    white-space: nowrap;
}

.tab-btn:hover {
    color: #fff;
}

.tab-btn.active {
    color: #ff5c1c;
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: #ff5c1c;
}

.tab-content {
    display: none;
    animation: fadeIn 0.4s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-card {
    background: linear-gradient(145deg, #1a1a1a, #252525);
    padding: 30px;
    border-radius: 16px;
    border: 1px solid #333;
}

.info-card h3 {
    color: #ff5c1c;
    font-size: 1.3rem;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #333;
}

.info-card p,
.info-card ul {
    color: #ccc;
    line-height: 1.8;
    font-size: 1.05rem;
}

.info-card ul {
    list-style: none;
    padding: 0;
}

.info-card ul li {
    padding: 8px 0;
    padding-left: 25px;
    position: relative;
}

.info-card ul li::before {
    content: '▸';
    position: absolute;
    left: 0;
    color: #ff5c1c;
}

/* Reviews Section */
.reviews-section {
    margin: 60px 0;
    padding: 40px;
    background: linear-gradient(145deg, #1a1a1a, #252525);
    border-radius: 20px;
    border: 1px solid #333;
}

.reviews-header {
    text-align: center;
    margin-bottom: 40px;
}

.reviews-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 20px;
}

.rating-summary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 20px;
}

.rating-summary .rating-stars {
    font-size: 1.8rem;
}

.rating-summary .rating-number {
    font-size: 1.5rem;
}

.write-review-btn {
    padding: 15px 40px;
    background: linear-gradient(135deg, #ff5c1c, #ff8c42);
    border: none;
    border-radius: 10px;
    color: #000;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.write-review-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 92, 28, 0.4);
}

/* Review Form */
.review-form-container {
    background: #1a1a1a;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 40px;
    border: 1px solid #333;
}

.review-form-container h4 {
    margin-bottom: 25px;
    font-size: 1.5rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 12px;
    background: #252525;
    border: 1px solid #333;
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #ff5c1c;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

/* Star Rating Input */
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 10px;
    font-size: 2.5rem;
    margin: 15px 0;
}

.rating-input input {
    display: none;
}

.rating-input label {
    color: #333;
    cursor: pointer;
    transition: color 0.2s;
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffa500;
}

/* Review List */
.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.review-item {
    background: #1a1a1a;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #333;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.reviewer-name {
    font-weight: 700;
    font-size: 1.1rem;
}

.review-date {
    color: #666;
    font-size: 0.9rem;
}

.review-rating {
    color: #ffa500;
    margin-bottom: 10px;
}

.review-title {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.review-text {
    color: #ccc;
    line-height: 1.6;
}

.no-reviews {
    text-align: center;
    color: #666;
    font-size: 1.1rem;
    padding: 40px 0;
}
</style>

<div class="product-detail-container">
    <div class="product-wrapper">
        <!-- Breadcrumb -->
        <div class="breadcrumb-nav">
            <a href="index.php">Home</a>
            <span>/</span>
            <a href="products.php">Products</a>
            <span>/</span>
            <span><?= htmlspecialchars($product['name']) ?></span>
        </div>

        <!-- Main Product Section -->
        <div class="product-main-section">
            <!-- Image Gallery -->
            <div class="product-gallery">
                <div class="main-product-image">
                    <img id="mainProductImg" 
                         src="uploads/images/<?= htmlspecialchars($product['image']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
                <div class="thumbnail-gallery">
                    <?php 
                    $images = [$product['image'], $product['image1'], $product['image2'], $product['image3'], $product['image4']];
                    foreach ($images as $index => $img) {
                        if (!empty($img)) {
                            $activeClass = $index === 0 ? 'active' : '';
                            echo '<div class="thumbnail-item ' . $activeClass . '" onclick="changeMainImage(\'uploads/images/' . htmlspecialchars($img) . '\', this)">';
                            echo '<img src="uploads/images/' . htmlspecialchars($img) . '" alt="Thumbnail">';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Product Info -->
            <div class="product-info-section">
                <div class="product-title-row">
                    <h1 class="product-name"><?= htmlspecialchars($product['name']) ?></h1>
                    <?php if ($review_count > 0): ?>
                    <div class="product-rating-bar">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?= $i <= round($avg_rating) ? '★' : '☆' ?>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-number"><?= $avg_rating ?></span>
                        <a href="#reviews-section" class="review-link"><?= $review_count ?> reviews</a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="product-price-section">
                    <div class="current-price">₱<?= number_format($product['price'], 2) ?></div>
                    <div class="stock-status <?= $product['stock'] > 0 && $product['status'] !== 'out of stock' ? '' : 'out-of-stock' ?>">
                        <?php if ($product['stock'] > 0 && $product['status'] !== 'out of stock'): ?>
                            ✓ In Stock - Only <?= $product['stock'] ?> left
                        <?php else: ?>
                            ✗ Out of Stock
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($product['stock'] > 0 && $product['status'] !== 'out of stock'): ?>
                <div class="add-to-cart-section">
                    <div class="quantity-selector">
                        <label for="qty">Quantity:</label>
                        <input type="number" 
                               name="quantity" 
                               id="qty" 
                               class="quantity-input"
                               value="1" 
                               min="1" 
                               max="<?= $product['stock'] ?>" 
                               form="cartForm"
                               required>
                    </div>
                    <div class="action-buttons">
                        <form method="POST" action="add_to_cart.php" id="cartForm" style="flex: 1; display: contents;" onsubmit="return handleAddToCart(event);">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="ajax" value="1">
                            <button type="submit" class="btn-add-cart">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </form>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php
                            if ($is_in_wishlist) {
                                $wishlist_action = 'remove_from_wishlist.php';
                                $wishlist_title = 'Remove from Wishlist';
                                $wishlist_icon_class = 'fas fa-heart';
                                $wishlist_btn_class = 'in-wishlist';
                            } else {
                                $wishlist_action = 'add_to_wishlist.php';
                                $wishlist_title = 'Add to Wishlist';
                                $wishlist_icon_class = 'far fa-heart';
                                $wishlist_btn_class = '';
                            }
                            ?>
                            <form method="POST" action="<?= $wishlist_action ?>" style="display: contents;">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                <button type="submit" 
                                        class="btn-wishlist <?= $wishlist_btn_class ?>" 
                                        title="<?= $wishlist_title ?>">
                                    <i class="<?= $wishlist_icon_class ?>"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="add-to-cart-section">
                    <button class="btn-add-cart" disabled>Out of Stock</button>
                </div>
                <?php endif; ?>

                <div class="product-description">
                    <h3 class="description-title">Product Description</h3>
                    <div class="description-text">
                        <?= nl2br(htmlspecialchars($product['details'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabbed Information Section -->
        <div class="product-tabs-section">
            <div class="tab-navigation">
                <button class="tab-btn active" onclick="switchTab(event, 'specifications')">Specifications</button>
                <button class="tab-btn" onclick="switchTab(event, 'warranty')">Warranty</button>
                <button class="tab-btn" onclick="switchTab(event, 'company')">Company Info</button>
            </div>

            <div id="specifications" class="tab-content active">
                <div class="info-card">
                    <h3>Technical Specifications</h3>
                    <?php if (!empty($product['specifications'])): ?>
                        <div><?= nl2br(htmlspecialchars($product['specifications'])) ?></div>
                    <?php else: ?>
                        <p>No specifications available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="warranty" class="tab-content">
                <div class="info-card">
                    <h3>Warranty Information</h3>
                    <?php if (!empty($product['warranty'])): ?>
                        <p><?= htmlspecialchars($product['warranty']) ?></p>
                    <?php else: ?>
                        <p>No warranty information available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="company" class="tab-content">
                <div class="info-card">
                    <h3>Company Information</h3>
                    <?php if (!empty($product['company'])): ?>
                        <p><?= htmlspecialchars($product['company']) ?></p>
                    <?php else: ?>
                        <p>No company information available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div id="reviews-section" class="reviews-section">
            <div class="reviews-header">
                <h2 class="reviews-title">Customer Reviews</h2>
                <?php if ($review_count > 0): ?>
                <div class="rating-summary">
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?= $i <= round($avg_rating) ? '★' : '☆' ?>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-number"><?= $avg_rating ?> out of 5</span>
                </div>
                <p>Based on <?= $review_count ?> review<?= $review_count !== 1 ? 's' : '' ?></p>
                <?php else: ?>
                <p>Be the first to review this product</p>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <button class="write-review-btn" onclick="toggleReviewForm()">Write a Review</button>
                <?php else: ?>
                <button class="write-review-btn" onclick="alert('Please log in to write a review'); window.location.href='login.php'">Write a Review</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
            <div id="reviewFormContainer" class="review-form-container" style="display: none;">
                <h4>Write Your Review</h4>
                <form action="submit_review.php" method="POST">
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">

                    <div class="form-group">
                        <label>Rating *</label>
                        <div class="rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                <label for="star<?= $i ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reviewTitle">Review Title</label>
                        <input type="text" id="reviewTitle" name="title" class="form-control" maxlength="100" placeholder="Summary of your experience">
                    </div>

                    <div class="form-group">
                        <label for="reviewComment">Your Review *</label>
                        <textarea id="reviewComment" name="comment" class="form-control" required placeholder="Share your thoughts about this product..."></textarea>
                    </div>

                    <div class="action-buttons">
                        <button type="button" onclick="toggleReviewForm()" class="btn-add-cart" style="background: #333">Cancel</button>
                        <button type="submit" class="btn-add-cart">Submit Review</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="reviews-list">
                <?php if ($reviews): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="reviewer-name"><?= htmlspecialchars($review['name']) ?></span>
                                <span class="review-date"><?= date("F j, Y", strtotime($review['created_at'])) ?></span>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= (int)$review['rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </div>
                            <?php if (!empty($review['title'])): ?>
                                <div class="review-title"><?= ($review['title']) ?></div>
                            <?php endif; ?>
                            <div class="review-text"><?= nl2br(($review['comment'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-reviews">No reviews yet. Be the first to review this product!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Image gallery functionality
function changeMainImage(src, element) {
    document.getElementById('mainProductImg').src = src;
    document.querySelectorAll('.thumbnail-item').forEach(item => item.classList.remove('active'));
    element.classList.add('active');
}

// Tab switching
function switchTab(event, tabName) {
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

// Toggle review form
function toggleReviewForm() {
    const form = document.getElementById('reviewFormContainer');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Add to Cart with Animation
function handleAddToCart(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const productImage = document.getElementById('mainProductImg');
    const cartIcon = document.querySelector('.fa-shopping-cart').closest('.icon-wrapper');
    
    // Create flying image
    const flyingImg = document.createElement('img');
    flyingImg.src = productImage.src;
    flyingImg.className = 'flying-image';
    
    // Get positions
    const imgRect = productImage.getBoundingClientRect();
    const cartRect = cartIcon.getBoundingClientRect();
    
    // Set initial position
    flyingImg.style.left = imgRect.left + 'px';
    flyingImg.style.top = imgRect.top + 'px';
    
    document.body.appendChild(flyingImg);
    
    // Animate to cart
    setTimeout(() => {
        flyingImg.style.transition = 'all 0.8s cubic-bezier(0.5, 0, 0.5, 1)';
        flyingImg.style.left = cartRect.left + (cartRect.width / 2) - 40 + 'px';
        flyingImg.style.top = cartRect.top + (cartRect.height / 2) - 40 + 'px';
        flyingImg.style.animation = 'flyToCart 0.8s ease-in';
    }, 10);
    
    // Remove flying image and trigger cart pulse
    setTimeout(() => {
        flyingImg.remove();
        cartIcon.classList.add('cart-pulse');
        setTimeout(() => cartIcon.classList.remove('cart-pulse'), 500);
    }, 800);
    
    // Submit form via AJAX
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success toast
            showSuccessToast(data.message || 'Product added to cart!');
            
            // Update cart count
            if (data.cart_count) {
                updateCartCount(data.cart_count);
            }
        } else {
            alert(data.message || 'Failed to add product to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to normal form submission
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