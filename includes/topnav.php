<?php
$current_user = getCurrentUser();
$username = $current_user['username'] ?? 'User';
?>
<nav class="navbar navbar-expand-lg navbar-white bg-white border-bottom sticky-top p-0">
    <div class="container-fluid p-0">
        <button class="btn btn-link d-lg-none me-3 p-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
            <i class="bi bi-list fs-3 text-dark"></i>
        </button>

        <form class="d-none d-md-flex ms-3 my-2" role="search" action="index.php" method="GET">
            <input type="hidden" name="page" value="search">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control border-start-0 bg-light" type="search" placeholder="Search..." aria-label="Search" name="q">
            </div>
        </form>

        <div class="navbar-nav ms-auto align-items-center p-2">
            <div class="nav-item dropdown me-3">
                <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if (isset($notification_count) && $notification_count > 0): ?>
                    <span class="position-absolute top-20 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $notification_count ?>
                        <span class="visually-hidden">unread messages</span>
                    </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 300px;">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="text-center py-3 text-muted small">No new notifications</li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center small text-primary" href="?page=notifications">View All</a></li>
                </ul>
            </div>

            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <?= strtoupper(substr($username, 0, 2)) ?>
                    </div>
                    <span class="d-none d-sm-block"><?= htmlspecialchars($username) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><h6 class="dropdown-header"><?= htmlspecialchars($current_user['role_name'] ?? 'Role') ?></h6></li>
                    <li><a class="dropdown-item" href="?page=profile"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="?page=settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/modules/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
