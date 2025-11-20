<?php
require_once __DIR__ . '/../../config/db_connect.php';

// ----------------------------------
// 1. TOTAL EMPLOYEES
// ----------------------------------
$total_employees = 0;
$query = "SELECT COUNT(*) AS total FROM employees WHERE is_deleted = 0";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $total_employees = $row['total'];
}

// ----------------------------------
// 2. PRESENT TODAY
// ----------------------------------
$present_today = 0;
$query = "
    SELECT COUNT(DISTINCT employee_id) AS present
    FROM attendance
    WHERE DATE(attendance_date) = CURDATE()
    AND time_in IS NOT NULL
    AND is_deleted = 0
";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $present_today = $row['present'];
}

// ----------------------------------
// 3. ON LEAVE
// ----------------------------------
$on_leave = 0;
$query = "
    SELECT COUNT(DISTINCT employee_id) AS on_leave
    FROM employee_leaves
    WHERE leave_status = 'Approved'
    AND CURDATE() BETWEEN date_start AND date_end
    AND is_deleted = 0
";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $on_leave = $row['on_leave'];
}

// ----------------------------------
// 4. NEW HIRES (Month-to-Date)
// ----------------------------------
$new_hires = 0;
$query = "
    SELECT COUNT(*) AS new_hires
    FROM employees
    WHERE is_deleted = 0
    AND MONTH(date_hired) = MONTH(CURDATE())
    AND YEAR(date_hired) = YEAR(CURDATE())
";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $new_hires = $row['new_hires'];
}

// ----------------------------------
// 5. ATTENDANCE PERCENTAGE
// ----------------------------------
$attendance_rate = $total_employees > 0 ? round(($present_today / $total_employees) * 100, 1) : 0;

// ----------------------------------
// 6. RECENT EMPLOYEES (Last 5)
// ----------------------------------
$recent_employees = [];
$query = "
    SELECT e.first_name, e.last_name, p.position_title AS position, es.status_name AS status
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN employment_statuses es ON e.employment_status_id = es.id
    WHERE e.is_deleted = 0
    ORDER BY e.date_hired DESC
    LIMIT 5
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_employees[] = $row;
}

// ----------------------------------
// 7. RECENT ACTIVITY (Last 5)
// ----------------------------------
$recent_activities = [];
$query = "
    SELECT action, table_name AS module, created_at
    FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 5
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-uppercase fw-bold text-muted mb-1">Total Employees</div>
                        <div class="h3 mb-0 fw-bold"><?= number_format($total_employees) ?></div>
                    </div>
                    <div class="text-primary opacity-50"><i class="bi bi-people-fill fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-uppercase fw-bold text-muted mb-1">Present Today</div>
                        <div class="h3 mb-0 fw-bold"><?= number_format($present_today) ?></div>
                        <div class="small text-success"><i class="bi bi-graph-up"></i> <?= $attendance_rate ?>% rate</div>
                    </div>
                    <div class="text-success opacity-50"><i class="bi bi-person-check fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-uppercase fw-bold text-muted mb-1">On Leave</div>
                        <div class="h3 mb-0 fw-bold"><?= number_format($on_leave) ?></div>
                    </div>
                    <div class="text-warning opacity-50"><i class="bi bi-calendar-x fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-uppercase fw-bold text-muted mb-1">New Hires (Mo)</div>
                        <div class="h3 mb-0 fw-bold"><?= number_format($new_hires) ?></div>
                    </div>
                    <div class="text-info opacity-50"><i class="bi bi-person-plus fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0">Employee Analytics</h5>
            </div>
            <div class="card-body">
                <canvas id="employeeChart" height="300"></canvas>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Employees</h5>
                <a href="?page=employees" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_employees as $emp): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:0.8rem;">
                                        <?= strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1)) ?>
                                    </div>
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </div>
                            </td>
                            <td class="text-muted small"><?= htmlspecialchars($emp['position']) ?></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success rounded-pill"><?= htmlspecialchars($emp['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0">Attendance Today</h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="250"></canvas>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($recent_activities as $act): ?>
                <div class="list-group-item border-0 px-3 py-3">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <h6 class="mb-1 small fw-bold text-primary"><?= htmlspecialchars($act['action']) ?></h6>
                        <small class="text-muted" style="font-size:0.75rem;"><?= date('M d, H:i', strtotime($act['created_at'])) ?></small>
                    </div>
                    <p class="mb-1 small text-secondary"><?= htmlspecialchars($act['module']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Employee Overview Chart
    new Chart(document.getElementById("employeeChart"), {
        type: "bar",
        data: {
            labels: ["Total", "New Hires"],
            datasets: [{
                label: "Employees",
                data: [<?= $total_employees ?>, <?= $new_hires ?>],
                backgroundColor: ["#0d6efd", "#6c757d"]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Attendance Summary Chart
    new Chart(document.getElementById("attendanceChart"), {
        type: "doughnut",
        data: {
            labels: ["Present", "On Leave", "Absent"],
            datasets: [{
                data: [<?= $present_today ?>, <?= $on_leave ?>, <?= max(0, $total_employees - $present_today - $on_leave) ?>],
                backgroundColor: ["#198754", "#ffc107", "#dc3545"]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: "bottom" } },
            cutout: "70%"
        }
    });
});
</script>
