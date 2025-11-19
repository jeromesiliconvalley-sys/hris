<?php
/**
 * Attendance History
 * View personal attendance records
 */

$user_id = $_SESSION['user_id'];
$employee_id = $_SESSION['employee_id'] ?? null;

$errors = [];

// Get employee info
if ($employee_id) {
    $emp_result = executeQuery(
        "SELECT e.* FROM employees e WHERE e.id = ? AND e.is_deleted = 0",
        "i",
        [$employee_id]
    );
    $employee = $emp_result->fetch_assoc();
} else {
    $errors[] = "No employee record found for your account.";
}

// Pagination
$page_num = isset($_GET['pg']) ? (int)$_GET['pg'] : 1;
$records_per_page = 20;
$offset = ($page_num - 1) * $records_per_page;

// Filter by month
$filter_month = $_GET['month'] ?? date('Y-m');

// Get attendance records
$attendance_records = [];
$total_records = 0;

if ($employee_id) {
    // Count total records
    $count_result = executeQuery(
        "SELECT COUNT(*) as total FROM attendance
         WHERE employee_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? AND is_deleted = 0",
        "is",
        [$employee_id, $filter_month]
    );
    $total_records = $count_result->fetch_assoc()['total'];

    // Get records
    $result = executeQuery(
        "SELECT * FROM attendance
         WHERE employee_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ? AND is_deleted = 0
         ORDER BY attendance_date DESC
         LIMIT ? OFFSET ?",
        "isii",
        [$employee_id, $filter_month, $records_per_page, $offset]
    );

    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

$total_pages = ceil($total_records / $records_per_page);
?>

<div class="page-header">
    <h1 class="page-title">Attendance History</h1>
    <div class="page-actions">
        <a href="index.php?page=attendance/clock" class="btn btn-sm btn-primary">
            <i class="bi bi-clock"></i> Clock In/Out
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escapeHtml($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-3">
            <input type="hidden" name="page" value="attendance/history">
            <div class="col-md-4">
                <label for="month" class="form-label">Month</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= escapeHtml($filter_month) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Records -->
<div class="card">
    <div class="card-body">
        <h5 class="card-title">Attendance Records - <?= date('F Y', strtotime($filter_month . '-01')) ?></h5>

        <?php if (empty($attendance_records)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No attendance records found for the selected month.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Lunch Out</th>
                            <th>Lunch In</th>
                            <th>Time Out</th>
                            <th>Hours</th>
                            <th>Verified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?= date('M d, Y (D)', strtotime($record['attendance_date'])) ?></td>
                                <td>
                                    <?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '<span class="text-muted">--</span>' ?>
                                    <?php if ($record['time_in_face_verified']): ?>
                                        <i class="bi bi-shield-check text-success" title="Face Verified"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= $record['lunch_out'] ? date('h:i A', strtotime($record['lunch_out'])) : '<span class="text-muted">--</span>' ?></td>
                                <td><?= $record['lunch_in'] ? date('h:i A', strtotime($record['lunch_in'])) : '<span class="text-muted">--</span>' ?></td>
                                <td>
                                    <?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '<span class="text-muted">--</span>' ?>
                                    <?php if ($record['time_out_face_verified']): ?>
                                        <i class="bi bi-shield-check text-success" title="Face Verified"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($record['time_in'] && $record['time_out']) {
                                        $time_in = strtotime($record['time_in']);
                                        $time_out = strtotime($record['time_out']);
                                        $lunch_duration = 0;

                                        if ($record['lunch_out'] && $record['lunch_in']) {
                                            $lunch_out = strtotime($record['lunch_out']);
                                            $lunch_in = strtotime($record['lunch_in']);
                                            $lunch_duration = $lunch_in - $lunch_out;
                                        }

                                        $work_seconds = ($time_out - $time_in) - $lunch_duration;
                                        $work_hours = floor($work_seconds / 3600);
                                        $work_minutes = floor(($work_seconds % 3600) / 60);

                                        echo sprintf('%d:%02d hrs', $work_hours, $work_minutes);
                                    } else {
                                        echo '<span class="text-muted">--</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $verified_count = 0;
                                    $total_actions = 0;

                                    if ($record['time_in']) {
                                        $total_actions++;
                                        if ($record['time_in_face_verified']) $verified_count++;
                                    }
                                    if ($record['time_out']) {
                                        $total_actions++;
                                        if ($record['time_out_face_verified']) $verified_count++;
                                    }

                                    if ($total_actions > 0) {
                                        $percentage = ($verified_count / $total_actions) * 100;
                                        $badge_class = $percentage == 100 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                        echo '<span class="badge bg-' . $badge_class . '">' . $verified_count . '/' . $total_actions . '</span>';
                                    } else {
                                        echo '<span class="text-muted">--</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
                                <a class="page-link" href="?page=attendance/history&month=<?= urlencode($filter_month) ?>&pg=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
