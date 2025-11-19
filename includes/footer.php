<!-- Footer -->
<footer class="app-footer bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-8">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400 m-0">
                    &copy; <?= date('Y') ?> <?= escapeHtml(COMPANY_NAME) ?>. All rights reserved.
                </p>
            </div>
            <div class="text-left md:text-right">
                <p class="text-sm text-gray-600 dark:text-gray-400 m-0">
                    <?= escapeHtml(SYSTEM_NAME) ?> v2.1 |
                    <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Privacy Policy</a> |
                    <a href="#" class="text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</footer>

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
        toastContainer.className = 'fixed top-4 right-4 z-[9999] space-y-2';
        document.body.appendChild(toastContainer);
    }

    // Determine toast color
    const type = '<?= $flash['type'] ?>';
    let bgColor = 'bg-primary-600';
    if (type === 'error' || type === 'danger') bgColor = 'bg-red-600';
    else if (type === 'success') bgColor = 'bg-green-600';
    else if (type === 'warning') bgColor = 'bg-yellow-600';
    else if (type === 'info') bgColor = 'bg-blue-600';

    // Create toast
    const toastHTML = `
        <div class="flex items-center gap-3 ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg max-w-md" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="flex-1">
                <?= escapeHtml($flash['text']) ?>
            </div>
            <button type="button" class="text-white hover:text-gray-200 text-xl" onclick="this.parentElement.remove()" aria-label="Close">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);

    // Auto-hide after 5 seconds
    const toastElement = toastContainer.lastElementChild;
    setTimeout(function() {
        toastElement.style.opacity = '0';
        toastElement.style.transition = 'opacity 0.3s';
        setTimeout(function() {
            toastElement.remove();
        }, 300);
    }, 5000);
});
</script>
<?php endif; ?>

<!-- Page-specific scripts (can be added by modules) -->
<?php if (isset($page_scripts) && is_array($page_scripts)): ?>
    <?php foreach ($page_scripts as $script): ?>
        <script src="<?= BASE_URL ?>/<?= escapeHtml($script) ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>