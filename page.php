<?php 
include 'config/db.php'; 
include 'includes/header.php'; 

// Get the page slug from the URL, e.g., page.php?slug=about-us
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    // No slug provided, redirect to homepage
    header("Location: index.php");
    exit;
}

// Fetch the page content from the database
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    // Page not found in database
    // You could redirect to a 404 page, but for simplicity, we'll show a message
    $page_title = "Page Not Found";
    $page_content = "<p>The page you are looking for could not be found.</p>";
} else {
    $page_title = htmlspecialchars($page['title']);
    $page_content = $page['content']; // This comes from TinyMCE, so it's already HTML
}

?>

<div class="container" style="padding: 120px 20px 40px 20px; min-height: 70vh;">
    <div class="row">
        <div class="col-12">
            
            <h1 class="mb-4"><?= $page_title ?></h1>
            
            <div class="page-content">
                <?= $page_content ?>
            </div>

        </div>
    </div>
</div>


<?php include 'includes/footer.php'; ?>