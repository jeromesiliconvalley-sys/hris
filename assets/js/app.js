// Sidebar Toggle for Desktop
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('expanded');
    
    // Close all submenus when collapsing
    if (sidebar.classList.contains('collapsed')) {
        document.querySelectorAll('.submenu').forEach(submenu => {
            submenu.classList.remove('show');
        });
        document.querySelectorAll('.nav-link.has-submenu').forEach(link => {
            link.setAttribute('aria-expanded', 'false');
        });
    }
}

// Multi-level Menu Toggle
function toggleSubmenu(event, element) {
    event.preventDefault();

    const sidebar = document.getElementById('sidebar');

    // Don't toggle submenu if sidebar is collapsed on desktop
    if (sidebar.classList.contains('collapsed') && window.innerWidth > 768) {
        return;
    }

    const submenu = element.nextElementSibling;
    const isOpen = submenu.classList.contains('show');

    // Close all other submenus
    document.querySelectorAll('.submenu').forEach(sub => {
        if (sub !== submenu) {
            sub.classList.remove('show');
        }
    });

    document.querySelectorAll('.nav-link.has-submenu').forEach(link => {
        if (link !== element) {
            link.setAttribute('aria-expanded', 'false');
        }
    });

    // Toggle current submenu
    if (isOpen) {
        submenu.classList.remove('show');
        element.setAttribute('aria-expanded', 'false');
    } else {
        submenu.classList.add('show');
        element.setAttribute('aria-expanded', 'true');
    }
}

// Mobile Sidebar Functions
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Active Navigation Link
document.addEventListener('DOMContentLoaded', function() {
    // Handle main nav links
    document.querySelectorAll('.nav-link:not(.has-submenu)').forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active from all nav links and submenu links
            document.querySelectorAll('.nav-link').forEach(l => {
                if (!l.classList.contains('has-submenu')) {
                    l.classList.remove('active');
                }
            });
            document.querySelectorAll('.submenu-link').forEach(l => l.classList.remove('active'));
            
            // Add active to clicked link
            this.classList.add('active');
            
            // Close sidebar on mobile after clicking
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // Submenu Link Click Handler
    document.querySelectorAll('.submenu-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Remove active from all submenu links and main nav links
            document.querySelectorAll('.submenu-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.nav-link:not(.has-submenu)').forEach(l => l.classList.remove('active'));
            
            // Add active to clicked submenu link
            this.classList.add('active');
            
            // Close sidebar on mobile after clicking
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // Search Functionality
    const searchInput = document.querySelector('.search-bar input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            console.log('Searching for:', e.target.value);
            // Implement search logic here
        });
    }

    // Quick Action Buttons
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const label = this.querySelector('.quick-action-label');
            if (label) {
                console.log('Quick action clicked:', label.textContent);
                // Implement action logic here
            }
        });
    });

    // Notification Badge Click
    document.querySelectorAll('.icon-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('Notification clicked');
            // Implement notification logic here
        });
    });

    // Employee Item Click
    document.querySelectorAll('.employee-item').forEach(item => {
        item.addEventListener('click', function() {
            const name = this.querySelector('.employee-name');
            if (name) {
                console.log('Employee clicked:', name.textContent);
                // Navigate to employee details
            }
        });
    });

    // Card Action Buttons
    document.querySelectorAll('.card-action').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Card action clicked');
            // Implement navigation or action
        });
    });

    // User Profile Dropdown (basic toggle)
    const userProfile = document.querySelector('.user-profile');
    if (userProfile) {
        userProfile.addEventListener('click', function() {
            console.log('User profile clicked');
            // Implement dropdown menu
        });
    }

    // Table Row Click
    document.querySelectorAll('.custom-table tbody tr').forEach(row => {
        row.addEventListener('click', function() {
            console.log('Table row clicked');
            // Navigate to details page
        });
    });

    // Smooth Scroll Animation
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.stat-card, .card').forEach(el => {
        observer.observe(el);
    });

    // ===========================================
    // UPPERCASE INPUT CONVERSION
    // ===========================================
    // Get all text inputs and textareas
    const inputs = document.querySelectorAll('input[type="text"], input[type="search"], textarea');
    
    inputs.forEach(input => {
        // Skip if input has explicit class to prevent uppercase
        if (input.classList.contains('no-uppercase')) {
            return;
        }
        
        // Skip email inputs
        if (input.type === 'email' || input.name === 'email' || input.id === 'email') {
            return;
        }
        
        // Add input event listener for real-time uppercase conversion
        input.addEventListener('input', function(e) {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(start, end);
        });
    });

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });
});

// Responsive Grid Adjustments
function adjustGridLayout() {
    const cards = document.querySelectorAll('.content-grid > .card');
    const width = window.innerWidth;
    
    cards.forEach(card => {
        if (width <= 768) {
            card.style.gridColumn = 'span 12';
        } else if (width <= 1024) {
            const currentSpan = card.style.gridColumn;
            if (currentSpan.includes('8')) {
                card.style.gridColumn = 'span 12';
            } else if (currentSpan.includes('6')) {
                card.style.gridColumn = 'span 12';
            }
        }
    });
}

window.addEventListener('resize', adjustGridLayout);
window.addEventListener('load', adjustGridLayout);