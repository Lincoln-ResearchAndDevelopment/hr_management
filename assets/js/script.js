// ========================================
// COURSE SLIDER FUNCTIONALITY
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Course Slider Variables
    const slider = document.getElementById('courseSlider');
    const sliderBtnLeft = document.getElementById('sliderBtnLeft');
    const sliderBtnRight = document.getElementById('sliderBtnRight');
    
    // Slider Configuration
    const scrollAmount = 300; // pixels to scroll per click
    
    // Left Arrow Click Handler
    if (sliderBtnLeft) {
        sliderBtnLeft.addEventListener('click', function() {
            slider.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        });
    }
    
    // Right Arrow Click Handler
    if (sliderBtnRight) {
        sliderBtnRight.addEventListener('click', function() {
            slider.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        });
    }
    
    // ========================================
    // SORT DROPDOWN FUNCTIONALITY
    // ========================================
    
    const sortBtn = document.getElementById('sortBtn');
    const sortMenu = document.getElementById('sortMenu');
    
    if (sortBtn && sortMenu) {
        // Toggle dropdown on button click
        sortBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = sortMenu.style.display === 'block';
            sortMenu.style.display = isVisible ? 'none' : 'block';
        });
        
        // Handle dropdown item clicks
        const dropdownItems = sortMenu.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const selectedText = this.textContent;
                sortBtn.innerHTML = 'Sort by <i class="fas fa-chevron-down ms-2"></i>';
                sortMenu.style.display = 'none';
                
                // You can add sorting logic here
                console.log('Sorted by: ' + selectedText);
            });
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (sortMenu && !sortBtn.contains(e.target) && !sortMenu.contains(e.target)) {
            sortMenu.style.display = 'none';
        }
    });
    
    // ========================================
    // JOB CARDS HOVER SHADOW
    // ========================================
    
    const jobCards = document.querySelectorAll('.job-card');
    jobCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('shadow-hover');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('shadow-hover');
        });
    });
    
    // ========================================
    // SMOOTH SCROLL FOR NAVIGATION LINKS
    // ========================================
    
    const navLinks = document.querySelectorAll('a[href*="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Only smooth scroll if it's a section link (starts with #)
            if (href.startsWith('#') && href !== '#' && href !== '#home' && href !== '#login') {
                e.preventDefault();
                const target = document.querySelector(href);
                
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // ========================================
    // SCROLL TO TOP FUNCTIONALITY
    // ========================================
    
    window.addEventListener('scroll', function() {
        // Add any scroll-based animations here
        updateActiveNavLink();
    });
    
    // ========================================
    // UPDATE ACTIVE NAVIGATION LINK
    // ========================================
    
    function updateActiveNavLink() {
        const sections = ['home', 'courses', 'jobs'];
        const navLinks = document.querySelectorAll('.nav-link');
        
        let currentSection = 'home';
        
        sections.forEach(section => {
            const element = document.getElementById(section);
            if (element) {
                const rect = element.getBoundingClientRect();
                if (rect.top <= 150) {
                    currentSection = section;
                }
            }
        });
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + currentSection) {
                link.classList.add('active');
            }
        });
    }
    
    // ========================================
    // TOUCH/SWIPE SUPPORT FOR MOBILE SLIDER
    // ========================================
    
    let touchStartX = 0;
    let touchEndX = 0;
    
    if (slider) {
        slider.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, false);
        
        slider.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, false);
    }
    
    function handleSwipe() {
        if (slider) {
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    // Swiped left - scroll right
                    slider.scrollBy({
                        left: scrollAmount,
                        behavior: 'smooth'
                    });
                } else {
                    // Swiped right - scroll left
                    slider.scrollBy({
                        left: -scrollAmount,
                        behavior: 'smooth'
                    });
                }
            }
        }
    }
    
    // ========================================
    // FADE-IN ANIMATION FOR ELEMENTS
    // ========================================
    
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // Observe feature boxes, course cards, and job cards
    document.querySelectorAll('.feature-box, .course-card, .job-card').forEach(element => {
        observer.observe(element);
    });
    
    // ========================================
    // KEYBOARD ACCESSIBILITY
    // ========================================
    
    // Allow keyboard navigation for slider
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft' && slider) {
            slider.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        } else if (e.key === 'ArrowRight' && slider) {
            slider.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        }
    });
    
    // ========================================
    // TESTIMONIAL CAROUSEL FUNCTIONALITY
    // ========================================
    
    const testimonialCarousel = document.querySelector('.testimonials-carousel');
    const testimonialPrev = document.querySelector('.testimonial-prev');
    const testimonialNext = document.querySelector('.testimonial-next');
    const testimonialItems = document.querySelectorAll('.testimonial-item');
    
    let currentTestimonialIndex = 0;
    
    function showTestimonial(index) {
        // Reset current display
        testimonialItems.forEach(item => {
            item.style.display = 'none';
            item.style.opacity = '0';
            item.style.transform = 'translateX(20px)';
        });
        
        // Show current testimonial with animation
        if (testimonialItems[index]) {
            testimonialItems[index].style.display = 'block';
            setTimeout(() => {
                testimonialItems[index].style.opacity = '1';
                testimonialItems[index].style.transform = 'translateX(0)';
                testimonialItems[index].style.transition = 'all 0.3s ease';
            }, 10);
        }
    }
    
    // Previous testimonial
    if (testimonialPrev) {
        testimonialPrev.addEventListener('click', function() {
            currentTestimonialIndex = (currentTestimonialIndex - 1 + testimonialItems.length) % testimonialItems.length;
            showTestimonial(currentTestimonialIndex);
        });
    }
    
    // Next testimonial
    if (testimonialNext) {
        testimonialNext.addEventListener('click', function() {
            currentTestimonialIndex = (currentTestimonialIndex + 1) % testimonialItems.length;
            showTestimonial(currentTestimonialIndex);
        });
    }
    
    // Initialize first testimonial
    if (testimonialItems.length > 0) {
        showTestimonial(0);
    }
    
});

