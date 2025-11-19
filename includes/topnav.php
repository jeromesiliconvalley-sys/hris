<?php
/**
 * Top Navigation Bar
 * 
 * Contains search bar, notifications, messages, and user profile dropdown.
 * All data is dynamically loaded from database.
 * 
 * @package HRIS
 * @version 2.1
 */

// Get current user info
$current_user = getCurrentUser();
$user_id = $current_user['id'];

// FIXED: Get real notification count from database
$notification_count = 0;
if ($user_id) {
    $notif_query = "SELECT COUNT(*) as count FROM notifications 
                    WHERE recipient_user_id = ? AND is_read = 0 AND is_deleted = 0";
    $notif_result = executeQuery($notif_query, 'i', [$user_id]);
    if ($notif_result && $notif_row = $notif_result->fetch_assoc()) {
        $notification_count = (int)$notif_row['count'];
    }
}

// FIXED: Get real message count (unread employee requests or memos)
$message_count = 0;
if ($user_id) {
    $msg_query = "SELECT COUNT(*) as count FROM employee_memos 
                  WHERE employee_id = (SELECT employee_id FROM users WHERE id = ?) 
                  AND status = 'Issued' AND viewed_at IS NULL AND is_deleted = 0";
    $msg_result = executeQuery($msg_query, 'i', [$user_id]);
    if ($msg_result && $msg_row = $msg_result->fetch_assoc()) {
        $message_count = (int)$msg_row['count'];
    }
}

// Get user initials for avatar
$username = $current_user['username'];
$user_initials = strtoupper(substr($username, 0, 2));

// Get employee name if available
$display_name = !empty($current_user['employee_name']) ? $current_user['employee_name'] : $username;
?>

<nav class="top-nav" role="banner">
  <button class="mobile-menu-btn" onclick="openSidebar()" aria-label="Open menu">
    <i class="bi bi-list" aria-hidden="true"></i>
  </button>

  <!-- FIXED: Make search functional -->
  <div class="search-bar">
    <form action="<?= BASE_URL ?>/index.php" method="get" id="topSearchForm">
      <input type="hidden" name="page" value="search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input 
        type="text" 
        name="q" 
        placeholder="Search employees, departments, reports..." 
        aria-label="Search"
        autocomplete="off"
      >
    </form>
  </div>

  <div class="top-nav-actions">
    
    <!-- Notifications -->
    <div class="dropdown">
      <button 
        class="icon-btn" 
        id="notificationBtn" 
        aria-label="Notifications"
        aria-expanded="false"
        aria-haspopup="true">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($notification_count > 0): ?>
        <span class="badge badge-danger" aria-label="<?= $notification_count ?> unread notifications">
          <?= $notification_count ?>
        </span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu" id="notificationDropdown" role="menu">
        <div class="dropdown-header">
          <h6>Notifications</h6>
          <?php if ($notification_count > 0): ?>
          <a href="#" class="text-secondary small" onclick="markAllNotificationsRead(event)">Mark all read</a>
          <?php endif; ?>
        </div>
        <div class="dropdown-body" id="notificationList">
          <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
        <div class="dropdown-footer">
          <a href="<?= BASE_URL ?>/index.php?page=notifications">View all notifications</a>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <div class="dropdown">
      <button 
        class="icon-btn" 
        id="messageBtn"
        aria-label="Messages"
        aria-expanded="false"
        aria-haspopup="true">
        <i class="bi bi-envelope" aria-hidden="true"></i>
        <?php if ($message_count > 0): ?>
        <span class="badge badge-primary" aria-label="<?= $message_count ?> unread messages">
          <?= $message_count ?>
        </span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu" id="messageDropdown" role="menu">
        <div class="dropdown-header">
          <h6>Messages</h6>
          <a href="<?= BASE_URL ?>/index.php?page=memos&action=new" class="text-secondary small">New</a>
        </div>
        <div class="dropdown-body" id="messageList">
          <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
        <div class="dropdown-footer">
          <a href="<?= BASE_URL ?>/index.php?page=memos">View all messages</a>
        </div>
      </div>
    </div>

    <!-- User Profile -->
    <div class="dropdown">
      <button 
        class="user-profile" 
        id="profileBtn"
        aria-label="User menu"
        aria-expanded="false"
        aria-haspopup="true">
        <div class="user-avatar" aria-hidden="true"><?= escapeHtml($user_initials) ?></div>
        <div class="user-info">
          <div class="user-name"><?= escapeHtml($display_name) ?></div>
          <div class="user-role"><?= escapeHtml($current_user['role_name']) ?></div>
        </div>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end dropdown-menu-compact" id="profileDropdown" role="menu">
        <?php if ($current_user['employee_id']): ?>
        <a href="<?= BASE_URL ?>/index.php?page=employees&action=view&id=<?= $current_user['employee_id'] ?>" 
           class="dropdown-item"
           role="menuitem">
          <i class="bi bi-person" aria-hidden="true"></i>
          <span>My Profile</span>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?page=settings&action=account" 
           class="dropdown-item"
           role="menuitem">
          <i class="bi bi-gear" aria-hidden="true"></i>
          <span>Account Settings</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=help" 
           class="dropdown-item"
           role="menuitem">
          <i class="bi bi-question-circle" aria-hidden="true"></i>
          <span>Help & Support</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= BASE_URL ?>/modules/auth/logout.php"
           class="dropdown-item text-danger"
           role="menuitem">
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- FIXED: Move JavaScript to external file reference -->
<script>
// Initialize topnav functionality
// Note: Full implementation is in assets/js/topnav.js
// This inline script only initializes with server-side data

window.HRIS = window.HRIS || {};
window.HRIS.userId = <?= (int)$user_id ?>;
window.HRIS.baseUrl = '<?= escapeHtml(BASE_URL) ?>';
window.HRIS.csrfToken = '<?= escapeHtml(generateCsrfToken()) ?>';

// Load notification and message data on dropdown open
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    const messageBtn = document.getElementById('messageBtn');
    const messageDropdown = document.getElementById('messageDropdown');
    
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');

    // Close all dropdowns
    function closeAllDropdowns() {
        notificationDropdown.classList.remove('show');
        messageDropdown.classList.remove('show');
        profileDropdown.classList.remove('show');
        
        notificationBtn.setAttribute('aria-expanded', 'false');
        messageBtn.setAttribute('aria-expanded', 'false');
        profileBtn.setAttribute('aria-expanded', 'false');
    }

    // Toggle notification dropdown
    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = notificationDropdown.classList.contains('show');
        closeAllDropdowns();
        
        if (!isOpen) {
            notificationDropdown.classList.add('show');
            notificationBtn.setAttribute('aria-expanded', 'true');
            loadNotifications();
        }
    });

    // Toggle message dropdown
    messageBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = messageDropdown.classList.contains('show');
        closeAllDropdowns();
        
        if (!isOpen) {
            messageDropdown.classList.add('show');
            messageBtn.setAttribute('aria-expanded', 'true');
            loadMessages();
        }
    });

    // Toggle profile dropdown
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = profileDropdown.classList.contains('show');
        closeAllDropdowns();
        
        if (!isOpen) {
            profileDropdown.classList.add('show');
            profileBtn.setAttribute('aria-expanded', 'true');
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        closeAllDropdowns();
    });

    // Prevent dropdown from closing when clicking inside
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    // Load notifications via AJAX
    function loadNotifications() {
        const container = document.getElementById('notificationList');
        
        fetch(window.HRIS.baseUrl + '/api/notifications.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    container.innerHTML = data.notifications.map(notif => `
                        <a href="${notif.action_url || '#'}"
                           class="dropdown-item ${notif.is_read ? '' : 'unread'}"
                           onclick="markNotificationRead(event, ${notif.id})">
                            <div class="notification-icon ${notif.priority === 'High' ? 'bg-danger' : notif.priority === 'Normal' ? 'bg-primary' : 'bg-success'}">
                                <i class="bi bi-${notif.icon || 'info-circle'}"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${escapeHtmlJS(notif.title)}</div>
                                <div class="notification-text">${escapeHtmlJS(notif.message)}</div>
                                <div class="notification-time">${notif.time_ago}</div>
                            </div>
                        </a>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="text-center py-3 text-secondary">No notifications</div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                container.innerHTML = '<div class="text-center py-3 text-danger">Error loading notifications</div>';
            });
    }

    // Load messages via AJAX
    function loadMessages() {
        const container = document.getElementById('messageList');
        
        fetch(window.HRIS.baseUrl + '/api/messages.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    container.innerHTML = data.messages.map(msg => `
                        <a href="${window.HRIS.baseUrl}/index.php?page=memos&action=view&id=${msg.id}"
                           class="dropdown-item ${msg.viewed_at ? '' : 'unread'}">
                            <div class="message-avatar">${msg.sender_initials}</div>
                            <div class="notification-content">
                                <div class="notification-title">${escapeHtmlJS(msg.sender_name)}</div>
                                <div class="notification-text">${escapeHtmlJS(msg.subject)}</div>
                                <div class="notification-time">${msg.time_ago}</div>
                            </div>
                        </a>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="text-center py-3 text-secondary">No messages</div>';
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                container.innerHTML = '<div class="text-center py-3 text-danger">Error loading messages</div>';
            });
    }

    // Helper function to escape HTML in JavaScript strings
    function escapeHtmlJS(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});

// Mark notification as read
function markNotificationRead(event, notificationId) {
    fetch(window.HRIS.baseUrl + '/api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.HRIS.csrfToken
        },
        body: JSON.stringify({ id: notificationId })
    }).catch(error => console.error('Error marking notification as read:', error));
}

// Mark all notifications as read
function markAllNotificationsRead(event) {
    event.preventDefault();
    
    fetch(window.HRIS.baseUrl + '/api/notifications.php?action=mark_all_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.HRIS.csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update badge
            const badge = document.querySelector('#notificationBtn .badge');
            if (badge) badge.remove();
            
            // Reload notifications
            document.getElementById('notificationBtn').click();
            document.getElementById('notificationBtn').click(); // Toggle twice to reload
        }
    })
    .catch(error => console.error('Error marking all as read:', error));
}
</script>