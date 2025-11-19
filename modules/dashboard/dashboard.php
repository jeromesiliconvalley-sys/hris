<?php
require_once __DIR__ . '/../../config/db_connect.php';

// ----------------------------------
// 1️⃣ TOTAL EMPLOYEES
// ----------------------------------
$total_employees = 0;
$query = "SELECT COUNT(*) AS total FROM employees WHERE is_deleted = 0";
if ($result = $conn->query($query)) {
    $row = $result->fetch_assoc();
    $total_employees = $row['total'];
}

// ----------------------------------
// 2️⃣ PRESENT TODAY
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
// 3️⃣ ON LEAVE
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
// 4️⃣ NEW HIRES (Month-to-Date)
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
// 5️⃣ ATTENDANCE PERCENTAGE
// ----------------------------------
$attendance_rate = $total_employees > 0 ? round(($present_today / $total_employees) * 100, 1) : 0;

// ----------------------------------
// 6️⃣ RECENT EMPLOYEES (Last 5)
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
// 7️⃣ RECENT ACTIVITY (Last 5)
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

<div class="page-header">
  <h1 class="page-title">Dashboard Overview</h1>
  <p class="page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
</div>

<!-- STATS GRID -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon primary"><i class="bi bi-people-fill"></i></div></div>
    <div class="stat-value"><?= number_format($total_employees) ?></div>
    <div class="stat-label">Total Employees</div>
  </div>

  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon success"><i class="bi bi-person-check"></i></div></div>
    <div class="stat-value"><?= number_format($present_today) ?></div>
    <div class="stat-label">Present Today</div>
    <span class="stat-change positive"><?= $attendance_rate ?>% Attendance</span>
  </div>

  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon warning"><i class="bi bi-calendar-x"></i></div></div>
    <div class="stat-value"><?= number_format($on_leave) ?></div>
    <div class="stat-label">On Leave</div>
  </div>

  <div class="stat-card">
    <div class="stat-header"><div class="stat-icon danger"><i class="bi bi-person-plus"></i></div></div>
    <div class="stat-value"><?= number_format($new_hires) ?></div>
    <div class="stat-label">New Hires (MTD)</div>
  </div>
</div>

<!-- DASHBOARD CONTENT GRID -->
<div class="content-grid">
  <!-- CHARTS -->
  <div class="card" style="grid-column: span 12; grid-column: span 6;">
    <div class="card-header">
      <h2 class="card-title">Employee Overview</h2>
    </div>
    <canvas id="employeeChart" height="150"></canvas>
  </div>

  <div class="card" style="grid-column: span 12; grid-column: span 6;">
    <div class="card-header">
      <h2 class="card-title">Attendance Summary</h2>
    </div>
    <canvas id="attendanceChart" height="150"></canvas>
  </div>

  <!-- RECENT EMPLOYEES -->
  <div class="card" style="grid-column: span 12; grid-column: span 6;">
    <div class="card-header">
      <h2 class="card-title">Recent Employees</h2>
    </div>
    <div class="employee-list">
      <?php foreach ($recent_employees as $emp): ?>
        <div class="employee-item">
          <div class="employee-avatar"><?= strtoupper(substr($emp['first_name'],0,1)) . strtoupper(substr($emp['last_name'],0,1)) ?></div>
          <div class="employee-info">
            <div class="employee-name"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
            <div class="employee-position"><?= htmlspecialchars($emp['position'] ?? 'N/A') ?></div>
          </div>
          <span class="employee-status <?= strtolower($emp['status'] ?? 'active') ?>"><?= htmlspecialchars($emp['status'] ?? 'Active') ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (empty($recent_employees)): ?>
        <p class="text-secondary">No recent employees found.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- RECENT ACTIVITY -->
  <div class="card" style="grid-column: span 12; grid-column: span 6;">
    <div class="card-header">
      <h2 class="card-title">Recent Activity</h2>
    </div>
    <div class="activity-timeline">
      <?php foreach ($recent_activities as $act): ?>
        <div class="activity-item">
          <div class="activity-icon primary"><i class="bi bi-clock-history"></i></div>
          <div class="activity-content">
            <div class="activity-title"><?= htmlspecialchars($act['action']) ?></div>
            <div class="activity-description"><?= htmlspecialchars($act['module']) ?></div>
            <div class="activity-time"><?= date('M d, Y h:i A', strtotime($act['created_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($recent_activities)): ?>
        <p class="text-secondary">No recent activities logged.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
  // Employee Overview Chart - Bootstrap Default Colors
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
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  // Attendance Summary Chart - Bootstrap Default Colors
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
      plugins: { legend: { position: "bottom" } },
      cutout: "70%"
    }
  });
});
</script>