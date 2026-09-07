const title = document.getElementById("scroll-title");
    const button = document.getElementById("scroll-button");
    const navbar = document.getElementById("navbar");
    let lastScroll = 0;

    window.addEventListener("scroll", () => {
      let scrollY = window.scrollY;

      // Title & button move up and shrink
      let scale = Math.max(1 - scrollY / 800, 0.5); 
      let translateY = scrollY * 0.4; 
      title.style.transform = `translateY(${translateY}px) scale(${scale})`;
      button.style.transform = `translateY(${translateY}px) scale(${scale})`;

      // Navbar hide/show
      if (scrollY > lastScroll) {
        navbar.classList.add("hidden"); // scrolling down → hide
      } else {
        navbar.classList.remove("hidden"); // scrolling up → show
      }
      lastScroll = scrollY;
    });

    // Carousel controls
    const carousel = document.getElementById("carousel");
    const pages = document.querySelectorAll(".carousel-page");
    const prevBtn = document.getElementById("prev");
    const nextBtn = document.getElementById("next");

    let currentIndex = 0;
    const totalPages = pages.length;

    // Clone first and last slide for seamless looping
    const firstClone = pages[0].cloneNode(true);
    const lastClone = pages[totalPages - 1].cloneNode(true);

    carousel.appendChild(firstClone);
    carousel.insertBefore(lastClone, pages[0]);

    let slideWidth = 100; // percent
    let offset = -100; // start from original first slide after lastClone
    carousel.style.transform = `translateX(${offset}%)`;

    // Smooth slide function
    function slideTo(index) {
      offset = -(index + 1) * slideWidth;
      carousel.style.transition = 'transform 0.6s ease';
      carousel.style.transform = `translateX(${offset}%)`;
    }

    // Handle transition end for seamless loop
    carousel.addEventListener('transitionend', () => {
      if (currentIndex < 0) {
        currentIndex = totalPages - 1;
        carousel.style.transition = 'none';
        offset = -(currentIndex + 1) * slideWidth;
        carousel.style.transform = `translateX(${offset}%)`;
      }
      if (currentIndex >= totalPages) {
        currentIndex = 0;
        carousel.style.transition = 'none';
        offset = -(currentIndex + 1) * slideWidth;
        carousel.style.transform = `translateX(${offset}%)`;
      }
    });

    // Next button
    nextBtn.addEventListener('click', () => {
      currentIndex++;
      slideTo(currentIndex);
    });

    // Prev button
    prevBtn.addEventListener('click', () => {
      currentIndex--;
      slideTo(currentIndex);
    });

    document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".wishlist-btn").forEach(button => {
        button.addEventListener("click", function() {
            let icon = this.querySelector("i");
            // Toggle between hollow (far) and solid (fas)
            icon.classList.toggle("far");
            icon.classList.toggle("fas");
        });
    });
});

function handleCategoryChange(select) {
    const form = document.getElementById("filtersForm");
    const sortSelect = form.querySelector("select[name='sort_by']");

    if (select.value === "") {
        // Reset sorting to default
        sortSelect.value = "";
    }

    form.submit();
}