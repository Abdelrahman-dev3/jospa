<!-- Lightning Progress Bar -->
<div id="progress-bar" class="progress-bar-container" style="display: none;">
    <div class="progress-bar-fill"></div>
</div>

<style>
.progress-bar-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    z-index: 9999;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: var(--primary-color);
    width: 0%;
    transition: width 0.3s ease;
    position: relative;
    animation: lightning 0.8s ease-in-out;
}

.progress-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
    animation: shimmer 0.6s ease-in-out;
}

@keyframes lightning {
    0% { width: 0%; }
    20% { width: 30%; }
    40% { width: 60%; }
    60% { width: 85%; }
    80% { width: 95%; }
    100% { width: 100%; }
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.progress-bar-container.show {
    display: block;
}

.progress-bar-container.hide {
    animation: fadeOut 0.3s ease forwards;
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateY(-100%);
    }
}
</style>

<script>
// Global function to show progress (accessible from anywhere)
window.showPageProgress = function() {
    const progressBar = document.getElementById('progress-bar');
    const progressFill = progressBar.querySelector('.progress-bar-fill');

    // Show progress bar
    progressBar.style.display = 'block';
    progressBar.classList.add('show');
    progressFill.style.width = '0%';

    // Lightning fast progress
    setTimeout(() => progressFill.style.width = '30%', 50);
    setTimeout(() => progressFill.style.width = '60%', 150);
    setTimeout(() => progressFill.style.width = '85%', 250);
    setTimeout(() => progressFill.style.width = '95%', 350);
    setTimeout(() => progressFill.style.width = '100%', 450);

    // Hide after completion
    setTimeout(() => {
        progressBar.classList.add('hide');
        setTimeout(() => {
            progressBar.style.display = 'none';
            progressBar.classList.remove('show', 'hide');
        }, 300);
    }, 600);
};

document.addEventListener('DOMContentLoaded', function() {
    const progressBar = document.getElementById('progress-bar');
    const progressFill = progressBar.querySelector('.progress-bar-fill');

    // Show progress bar on page load
    function showProgress() {
        progressBar.style.display = 'block';
        progressBar.classList.add('show');
        progressFill.style.width = '0%';

        // Lightning fast progress
        setTimeout(() => progressFill.style.width = '30%', 50);
        setTimeout(() => progressFill.style.width = '60%', 150);
        setTimeout(() => progressFill.style.width = '85%', 250);
        setTimeout(() => progressFill.style.width = '95%', 350);
        setTimeout(() => progressFill.style.width = '100%', 450);

        // Hide after completion
        setTimeout(() => {
            progressBar.classList.add('hide');
            setTimeout(() => {
                progressBar.style.display = 'none';
                progressBar.classList.remove('show', 'hide');
            }, 300);
        }, 600);
    }

    // Show progress on page load
    showProgress();

    // Show progress only on actual navigation links
    document.addEventListener('click', function(e) {
        // Only proceed if the clicked element is an <a> tag or is inside an <a> tag
        const link = e.target.closest('a');

        // If no link found or the clicked element is a button, input, or other interactive element, ignore
        if (!link ||
            e.target.tagName === 'BUTTON' ||
            e.target.tagName === 'INPUT' ||
            e.target.tagName === 'SELECT' ||
            e.target.tagName === 'TEXTAREA' ||
            e.target.closest('button') ||
            e.target.closest('input') ||
            e.target.closest('select') ||
            e.target.closest('textarea')) {
            return;
        }

        if (link.href) {
            const href = link.href;
            const currentUrl = window.location.href.split('?')[0]; // Remove query parameters
            const targetUrl = href.split('?')[0]; // Remove query parameters

            // Only show progress if it's a different page (not same page, not hash, not javascript)
            if (href.startsWith(window.location.origin) &&
                !href.includes('#') &&
                !href.includes('javascript:') &&
                link.target !== '_blank' &&
                targetUrl !== currentUrl) {

                e.preventDefault();
                showProgress();

                // Navigate after a short delay
                setTimeout(() => {
                    window.location.href = href;
                }, 100);
            }
        }
    });

    // Show progress on form submissions that actually submit
    document.addEventListener('submit', function(e) {
        const form = e.target;
        // Only show progress if form has action or will actually submit
        if (form.tagName === 'FORM' && (form.action || form.method === 'post')) {
            showProgress();
        }
    });

    // Show progress on browser back/forward
    window.addEventListener('popstate', function() {
        showProgress();
    });
});
</script>
