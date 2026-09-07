<?php 
include 'config/db.php'; 
include 'includes/header.php'; 

// Fetch all active FAQs from the new 'faqs' table
$stmt = $pdo->query("SELECT * FROM faqs WHERE is_active = 1 ORDER BY display_order ASC");
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .faq-header {
        background-color: #343a40;
        color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
        margin-top: 100px; /* Added margin for header */
    }
    .accordion-button:not(.collapsed) {
        background-color: #fd7e14; /* Your theme's orange */
        color: white;
    }
    .accordion-button:focus {
        box-shadow: 0 0 0 0.25rem rgba(253, 126, 20, 0.25);
    }
    
    /* Styles for content from the rich text editor */
    .faq-answer p {
        margin-bottom: 0;
    }
    .faq-answer a {
        color: #fd7e14;
        text-decoration: none;
    }
     .faq-answer a:hover {
        text-decoration: underline;
    }
</style>

<div class="container my-5">
    <div class="faq-header text-center">
        <h1>Frequently Asked Questions</h1>
        <p class="lead">Find answers to common questions about our products, orders, and shipping.</p>
    </div>

    <div class="accordion" id="faqAccordion">

        <?php if (empty($faqs)): ?>
            <div class="alert alert-info">No frequently asked questions have been added yet.</div>
        <?php else: ?>
            <?php foreach ($faqs as $index => $faq): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?= $faq['id'] ?>">
                        <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $faq['id'] ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= $faq['id'] ?>">
                            <?= htmlspecialchars($faq['question']) ?>
                        </button>
                    </h2>
                    <div id="collapse-<?= $faq['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= $faq['id'] ?>" data-bs-parent="#faqAccordion">
                        <div class="accordion-body faq-answer">
                            <?= $faq['answer'] // Render the HTML answer from the database ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

