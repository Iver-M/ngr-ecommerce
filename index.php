<?php 
include 'config/db.php'; 
include 'includes/header.php'; 

// --- NEW CODE: Fetch Active Promotions ---
try {
    $stmt_promos = $pdo->prepare("
        SELECT * FROM promotions 
        WHERE is_active = 1 
        ORDER BY display_order ASC, created_at DESC
    ");
    $stmt_promos->execute();
    $promotions = $stmt_promos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If table doesn't exist or error, promotions will be an empty array
    $promotions = [];
    error_log("Error fetching promotions: " . $e->getMessage());
}
// --- END NEW CODE ---

?>
<div class="video-container">
    <video id="comp-lqjgz0j9_video" class="K8MSra" crossorigin="anonymous" playsinline="" preload="auto" muted="" loop="" autoplay="" 
    src="https://video.wixstatic.com/video/84770f_303792991d87468bbce18d3d78beb7f5/1080p/mp4/file.mp4" 
    style="height: 100%; width: 100%; object-fit: cover; object-position: center center; opacity: 1;">
    </video>
</div>

<div class="hero" id="home">
  <h1 id="scroll-title">NEWGROUNDS</h1>
  <a href="products.php">
      <button id="scroll-button">Browse the Selection</button>
  </a>
</div>

<div class="section-divider">
  <span>FOR YOUR FITNESS NEEDS</span>
</div>

<section id="about">
    <div class="about-carousel">
    <div class="carousel-controls">
      <button id="prev">&#9664;</button>
      <button id="next">&#9654;</button>
    </div>
    <div class="carousel" id="carousel">
      <div class="carousel-page">
        <div class="carousel-left">
          <div>
            <h2>THE TOOLS TO BREAK YOU DOWN, AND BUILD YOU BACK UP</h2>
            <p>Push your limits with high-quality, durable gym equipment designed for beginners and professionals alike.</p>
          </div>
          <div>
            <button onclick="location.href='products.php?product_type=equipment'">Browse Equipment</button>
          </div>
        </div>
        <div class="carousel-right">
          <img src="uploads/images/media/abt_equip.jpg" alt="Gym Equipment">
        </div>
      </div>

      <div class="carousel-page">
        <div class="carousel-left">
          <div>
            <h2>GIVE YOUR BODY THE BUILDING BLOCKS IT NEEDS TO BE STRONGER</h2>
            <p>Boost your performance and recovery with our premium supplements crafted for serious athletes and fitness lovers.</p>
          </div>
          <button onclick="location.href='products.php?product_type=supplements'">Browse Supplements</button>
        </div>
        <div class="carousel-right">
          <img src="uploads/images/media/abt_protien.jpg" alt="Gym Supplements">
        </div>
      </div>
    </div>
</section>


<!-- =========== NEW PROMOTIONS CAROUSEL =========== -->
<?php if (!empty($promotions)): ?>
  <div class="section-divider">
  <span>October Promos</span>
</div>
<div class="container-fluid promo-carousel-container p-0">
    <div id="promotionCarousel" class="carousel slide promo-carousel" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($promotions as $index => $promo): ?>
                <button type-="button" data-bs-target="#promotionCarousel" data-bs-slide-to="<?= $index ?>" class="<?= $index === 0 ? 'active' : '' ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($promotions as $index => $promo): ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <img src="uploads/promotions/<?= htmlspecialchars($promo['image_path']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($promo['title']) ?>">
                    <div class="carousel-caption">
                        <h2 class="mb-3"><?= htmlspecialchars($promo['title']) ?></h2>
                        <p class="lead mb-4"><?= htmlspecialchars($promo['description']) ?></p>
                        <a href="<?= htmlspecialchars($promo['link_url']) ?>" class="btn-promo">Shop Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#promotionCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#promotionCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<?php endif; ?>
<!-- =========== END OF PROMOTIONS CAROUSEL =========== -->

<div class="section-divider">
  <span>New Arrivals</span>
</div>

<h3></h3>
<div class="row">
  <?php
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC 
        LIMIT 4
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
        $company = $row['company'] ?? $row['brand'] ?? $row['category_name'] ?? 'GymBRO';
    ?>
      <div class="col-md-3 mb-4">
          <div class="index-card" onclick="window.location.href='product.php?id=<?= $row['id'] ?>'">
              <div class="image-wrapper">
                <img src="uploads/images/<?= htmlspecialchars($row['image']) ?>" class="index-card-img-top" alt="<?= htmlspecialchars($row['name']) ?>">
              </div>
              <div class="index-card-body">
                  <div class="product-company"><?= htmlspecialchars($company) ?></div>
                  <h5 class="index-card-title"><?= htmlspecialchars($row['name']) ?></h5>
                  <p class="index-card-text">₱<?= number_format($row['price'], 2) ?></p>
              </div>
          </div>
      </div>
  <?php endwhile; ?>
</div>


<style>
?>

<style>
/* Styling for the new promotions carousel */
.promo-carousel-container {
    width: 100%;
    margin-top: -80px; /* Pulls it up to overlap the video slightly */
    position: relative;
    z-index: 10;
}

.promo-carousel .carousel-item {
    height: 50vh; /* Adjust height as needed */
    min-height: 400px;
}

.promo-carousel .carousel-item img {
    object-fit: cover;
    height: 100%;
    width: 100%;
    filter: brightness(0.6);
}

.promo-carousel .carousel-caption {
    bottom: 0;
    left: 0;
    right: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 2rem;
}

.promo-carousel .carousel-caption h2 {
    font-size: 3rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 2px 2px 8px rgba(0,0,0,0.7);
}

.promo-carousel .carousel-caption p {
    font-size: 1.25rem;
    color: #fff;
    text-shadow: 1px 1px 4px rgba(0,0,0,0.7);
}

.promo-carousel .btn-promo {
    background-color: #fd7e14;
    border-color: #fd7e14;
    color: #fff;
    padding: 0.75rem 1.5rem;
    font-weight: bold;
    text-transform: uppercase;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.promo-carousel .btn-promo:hover {
    background-color: #e06c0c;
    border-color: #d3650b;
    transform: translateY(-2px);
}
</style>

<?php include 'includes/footer.php'; ?>
