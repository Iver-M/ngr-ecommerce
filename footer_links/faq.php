<?php 
include __DIR__ . '/../includes/header.php'; 
?>

<style>
    .faq-header {
        background-color: #343a40;
        color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
    }
    .accordion-button:not(.collapsed) {
        background-color: #fd7e14; /* Your theme's orange */
        color: white;
    }
    .accordion-button:focus {
        box-shadow: 0 0 0 0.25rem rgba(253, 126, 20, 0.25);
    }
</style>

<div class="container my-5" style="padding-top: 80px;">
    <div class="faq-header text-center">
        <h1>Frequently Asked Questions</h1>
        <p class="lead">Find answers to common questions about our products, orders, and shipping.</p>
    </div>

    <div class="accordion" id="faqAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    What are your shipping options?
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    We offer Standard and Express shipping. Shipping costs and estimated delivery times are calculated at checkout based on your location. You can view these details before placing your order.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                    How can I track my order?
                </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    Once your order has been shipped, you can view its status and tracking information in your profile under the "My Orders" section. You will also receive an email with the tracking details.
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    What is your return policy?
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    You can request a return for any item within 7 days of delivery, provided it is in its original condition. To start a return, please go to the "My Orders" page in your profile and click the "Return Item" button next to the product you wish to return.
                </div>
            </div>
        </div>

        </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>