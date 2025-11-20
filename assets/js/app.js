// assets/js/app.js

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Bootstrap Tooltips & Popovers globally
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

    // 2. Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // 3. Force Uppercase on Text Inputs (except emails/passwords)
    const inputs = document.querySelectorAll('input[type="text"], input[type="search"], textarea');
    inputs.forEach(input => {
        if (!input.classList.contains('no-uppercase') && input.id !== 'email' && input.type !== 'email') {
            input.addEventListener('input', function() {
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(start, end);
            });
        }
    });

    // 4. Handle Active State for Sidebar Links (Bootstrap Collapse)
    // Ensures the parent dropdown stays open if a child is active
    const activeLinks = document.querySelectorAll('.nav-link.active');
    activeLinks.forEach(link => {
        const parentCollapse = link.closest('.collapse');
        if (parentCollapse) {
            const collapseInstance = new bootstrap.Collapse(parentCollapse, { toggle: false });
            collapseInstance.show();

            // Highlight parent toggle
            const toggler = document.querySelector(`[data-bs-target="#${parentCollapse.id}"]`);
            if (toggler) {
                toggler.classList.remove('collapsed');
                toggler.setAttribute('aria-expanded', 'true');
                toggler.classList.add('text-primary');
            }
        }
    });
});
