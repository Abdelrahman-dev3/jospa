<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('pages-css/footer.css') }}">

<footer class="footer-section">
    <div class="footer-background-layer"></div>

    <div class="footer-curve-top">
        <svg class="top-curve-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none">
            <path d="M0,100 C250,10 750,10 1000,100 L1000,100 L0,100 Z" fill="#ffffff" />
        </svg>
    </div>
    
    <div class="footer-content">
        
        <div class="footer-logo-container">
            <h1 class="footer-logo">JO | SPA</h1>
            <p class="footer-logo-exp">EXPERIENCE RIYADH</p>
        </div>

        <p class="footer-description">
             {{ __('messagess.footer_description') }}
        </p>

        <div class="contact-buttons">
            <a href="https://wa.me/966504470706" target="_blank" class="contact-btn">
                <i class="bi bi-whatsapp"></i> 966504470706
            </a>
            <a href="https://wa.me/966920012924" target="_blank" class="contact-btn">
                <i class="bi bi-whatsapp"></i> 920012924
            </a>
            <a href="mailto:contact@jospa.com.sa" class="contact-btn">
                <i class="bi bi-envelope-fill"></i> info@jospa-sa.com
            </a>
        </div>

        <div class="social-icons-main">
            <a href="https://www.instagram.com/jospa_sa/#" target="_blank"><i class="bi bi-facebook"></i></a>
            <a href="https://x.com/Jospa_sa" target="_blank"><i class="bi bi-twitter"></i></a>
            <a href="https://www.instagram.com/jospa_sa/#" target="_blank"><i class="bi bi-instagram"></i></a>
        </div>

        <div class="footer-links">
            <a href="{{ route('frontend.privacy.policy') }}" class="footer-link-item">
                <i class="bi bi-shield-lock"></i> {{ __('messagess.privacy_footer_link') }}
            </a>
        </div>
        
    </div>
</footer>

<script>
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
</script>
