<!-- Footer -->
<footer class="app-footer mt-4">
    <div class="container-fluid px-4 py-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <p class="text-secondary small m-0">
                    &copy; <?= date('Y') ?> <?= escapeHtml(COMPANY_NAME) ?>. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="text-secondary small m-0">
                    <?= escapeHtml(SYSTEM_NAME) ?> v3.0 |
                    <a href="#" class="text-secondary">Privacy Policy</a> |
                    <a href="#" class="text-secondary">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
></script>

<!-- Custom JS with defer for better performance -->
<script src="<?= BASE_URL ?>/assets/js/app.js" defer></script>

<!-- Display flash messages if present -->
<?php
$flash = getFlashMessage();
if ($flash):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1090';
        document.body.appendChild(toastContainer);
    }

    // Determine toast color based on type
    const type = '<?= $flash['type'] ?>';
    let bgClass = 'bg-primary';
    let textClass = 'text-white';
    if (type === 'error' || type === 'danger') bgClass = 'bg-danger';
    else if (type === 'success') bgClass = 'bg-success';
    else if (type === 'warning') { bgClass = 'bg-warning'; textClass = 'text-dark'; }
    else if (type === 'info') bgClass = 'bg-info';

    // Create Bootstrap toast
    const toastHTML = `
        <div class="toast align-items-center ${bgClass} ${textClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <?= escapeHtml($flash['text']) ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);

    // Initialize and show the toast
    const toastElement = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
});
</script>
<?php endif; ?>

<!-- Page-specific scripts (can be added by modules) -->
<?php if (isset($page_scripts) && is_array($page_scripts)): ?>
    <?php foreach ($page_scripts as $script): ?>
        <script src="<?= BASE_URL ?>/<?= escapeHtml($script) ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>