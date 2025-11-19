<?php
/**
 * Clock In/Out Module with Face Recognition
 * Uses vladmandic/face-api for facial recognition
 */

// Get current user info
$user_id = $_SESSION['user_id'];
$employee_id = $_SESSION['employee_id'] ?? null;

$errors = [];
$success_message = '';

// Get employee info
if ($employee_id) {
    $emp_result = executeQuery(
        "SELECT e.*, c.name as company_name, ou.unit_name, p.position_title
         FROM employees e
         LEFT JOIN companies c ON e.company_id = c.id
         LEFT JOIN organizational_units ou ON e.organizational_unit_id = ou.id
         LEFT JOIN positions p ON e.position_id = p.id
         WHERE e.id = ? AND e.is_deleted = 0",
        "i",
        [$employee_id]
    );
    $employee = $emp_result->fetch_assoc();
} else {
    $errors[] = "No employee record found for your account.";
}

// Check if employee has enrolled face data
$has_face_data = false;
if ($employee_id) {
    $face_result = executeQuery(
        "SELECT id FROM face_data WHERE employee_id = ? AND is_active = 1 AND is_deleted = 0",
        "i",
        [$employee_id]
    );
    $has_face_data = $face_result->num_rows > 0;
}

// Get today's attendance record
$today = date('Y-m-d');
$attendance = null;
if ($employee_id) {
    $att_result = executeQuery(
        "SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ? AND is_deleted = 0",
        "is",
        [$employee_id, $today]
    );
    if ($att_result->num_rows > 0) {
        $attendance = $att_result->fetch_assoc();
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Clock In/Out</h1>
    <div class="page-actions">
        <a href="index.php?page=attendance/history" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-clock-history"></i> Attendance History
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

<?php if ($success_message): ?>
    <div class="alert alert-success"><?= escapeHtml($success_message) ?></div>
<?php endif; ?>

<?php if ($employee): ?>
    <div class="row mb-4">
        <div class="col-md-8">
            <!-- Employee Info Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Employee Information</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?= escapeHtml($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                            <p><strong>Employee #:</strong> <?= escapeHtml($employee['employee_number']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Position:</strong> <?= escapeHtml($employee['position_title']) ?></p>
                            <p><strong>Department:</strong> <?= escapeHtml($employee['unit_name']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!$has_face_data): ?>
                <!-- No Face Data Warning -->
                <div class="alert alert-warning">
                    <h5><i class="bi bi-exclamation-triangle"></i> Face Recognition Not Set Up</h5>
                    <p>You haven't enrolled your face data yet. Please enroll to use face recognition for attendance.</p>
                    <a href="index.php?page=attendance/enroll" class="btn btn-warning">
                        <i class="bi bi-person-bounding-box"></i> Enroll Face Now
                    </a>
                </div>
            <?php endif; ?>

            <!-- Clock In/Out Interface -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Today's Attendance - <?= date('F d, Y') ?></h5>

                    <!-- Attendance Status -->
                    <div class="row text-center mb-4">
                        <div class="col-6 col-md-3">
                            <div class="attendance-status <?= $attendance && $attendance['time_in'] ? 'completed' : '' ?>">
                                <i class="bi bi-box-arrow-in-right"></i>
                                <div class="status-label">Time In</div>
                                <div class="status-time"><?= $attendance && $attendance['time_in'] ? date('h:i A', strtotime($attendance['time_in'])) : '--:--' ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="attendance-status <?= $attendance && $attendance['lunch_out'] ? 'completed' : '' ?>">
                                <i class="bi bi-cup-straw"></i>
                                <div class="status-label">Lunch Out</div>
                                <div class="status-time"><?= $attendance && $attendance['lunch_out'] ? date('h:i A', strtotime($attendance['lunch_out'])) : '--:--' ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="attendance-status <?= $attendance && $attendance['lunch_in'] ? 'completed' : '' ?>">
                                <i class="bi bi-cup-hot"></i>
                                <div class="status-label">Lunch In</div>
                                <div class="status-time"><?= $attendance && $attendance['lunch_in'] ? date('h:i A', strtotime($attendance['lunch_in'])) : '--:--' ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="attendance-status <?= $attendance && $attendance['time_out'] ? 'completed' : '' ?>">
                                <i class="bi bi-box-arrow-right"></i>
                                <div class="status-label">Time Out</div>
                                <div class="status-time"><?= $attendance && $attendance['time_out'] ? date('h:i A', strtotime($attendance['time_out'])) : '--:--' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Time -->
                    <div class="text-center mb-4">
                        <h2 id="current-time" class="display-4"></h2>
                        <p class="text-muted" id="current-date"></p>
                    </div>

                    <!-- Face Recognition Interface -->
                    <div id="face-recognition-interface">
                        <div class="text-center mb-3">
                            <video id="video" width="640" height="480" autoplay muted style="max-width: 100%; border: 2px solid #ddd; border-radius: 8px;"></video>
                            <canvas id="overlay" style="position: absolute; display: none;"></canvas>
                        </div>

                        <div id="face-status" class="alert alert-info text-center">
                            <i class="bi bi-camera-video"></i> Initializing camera...
                        </div>

                        <!-- Clock Action Buttons -->
                        <div class="d-grid gap-2">
                            <?php if (!$attendance || !$attendance['time_in']): ?>
                                <button type="button" id="btn-time-in" class="btn btn-success btn-lg" disabled>
                                    <i class="bi bi-box-arrow-in-right"></i> Clock In
                                </button>
                            <?php elseif (!$attendance['lunch_out']): ?>
                                <button type="button" id="btn-lunch-out" class="btn btn-warning btn-lg" disabled>
                                    <i class="bi bi-cup-straw"></i> Lunch Out
                                </button>
                            <?php elseif (!$attendance['lunch_in']): ?>
                                <button type="button" id="btn-lunch-in" class="btn btn-info btn-lg" disabled>
                                    <i class="bi bi-cup-hot"></i> Lunch In
                                </button>
                            <?php elseif (!$attendance['time_out']): ?>
                                <button type="button" id="btn-time-out" class="btn btn-danger btn-lg" disabled>
                                    <i class="bi bi-box-arrow-right"></i> Clock Out
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success text-center">
                                    <i class="bi bi-check-circle"></i> You have completed your attendance for today!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Help Card -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle"></i> How to Use</h6>
                    <ol class="small">
                        <li>Allow camera access when prompted</li>
                        <li>Position your face in the camera view</li>
                        <li>Wait for face detection (green box)</li>
                        <li>Click the appropriate clock button</li>
                        <li>Wait for face verification</li>
                    </ol>
                </div>
            </div>

            <!-- Verification Status -->
            <?php if ($attendance): ?>
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-shield-check"></i> Verification Status</h6>
                        <ul class="list-unstyled small">
                            <?php if ($attendance['time_in']): ?>
                                <li>
                                    <i class="bi bi-<?= $attendance['time_in_face_verified'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                    Time In: <?= $attendance['time_in_face_verified'] ? 'Verified' : 'Not Verified' ?>
                                </li>
                            <?php endif; ?>
                            <?php if ($attendance['lunch_out']): ?>
                                <li>
                                    <i class="bi bi-<?= $attendance['lunch_out_face_verified'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                    Lunch Out: <?= $attendance['lunch_out_face_verified'] ? 'Verified' : 'Not Verified' ?>
                                </li>
                            <?php endif; ?>
                            <?php if ($attendance['lunch_in']): ?>
                                <li>
                                    <i class="bi bi-<?= $attendance['lunch_in_face_verified'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                    Lunch In: <?= $attendance['lunch_in_face_verified'] ? 'Verified' : 'Not Verified' ?>
                                </li>
                            <?php endif; ?>
                            <?php if ($attendance['time_out']): ?>
                                <li>
                                    <i class="bi bi-<?= $attendance['time_out_face_verified'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                    Time Out: <?= $attendance['time_out_face_verified'] ? 'Verified' : 'Not Verified' ?>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
.attendance-status {
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 10px;
}

.attendance-status.completed {
    border-color: #28a745;
    background-color: #d4edda;
}

.attendance-status i {
    font-size: 2rem;
    color: #6c757d;
}

.attendance-status.completed i {
    color: #28a745;
}

.status-label {
    font-size: 0.9rem;
    font-weight: 600;
    margin-top: 5px;
}

.status-time {
    font-size: 1.1rem;
    font-weight: bold;
    color: #333;
}

#video {
    transform: scaleX(-1);
}
</style>

<!-- Face API Library -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

<script>
let video, faceDetected = false;
let labeledDescriptors = null;
let currentDescriptor = null;

// Update clock
function updateClock() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    document.getElementById('current-time').textContent = timeStr;
    document.getElementById('current-date').textContent = dateStr;
}

setInterval(updateClock, 1000);
updateClock();

// Initialize Face API
async function initFaceAPI() {
    try {
        updateStatus('Loading face recognition models...', 'info');

        // Load models from CDN
        await faceapi.nets.tinyFaceDetector.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model');
        await faceapi.nets.faceLandmark68Net.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model');
        await faceapi.nets.faceRecognitionNet.loadFromUri('https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model');

        updateStatus('Models loaded. Starting camera...', 'info');
        await startVideo();

    } catch (error) {
        console.error('Error initializing Face API:', error);
        updateStatus('Error loading face recognition: ' + error.message, 'danger');
    }
}

// Start video stream
async function startVideo() {
    try {
        video = document.getElementById('video');
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480 }
        });
        video.srcObject = stream;

        video.addEventListener('play', () => {
            detectFace();
            loadEnrolledFace();
        });

    } catch (error) {
        console.error('Error accessing camera:', error);
        updateStatus('Camera access denied. Please allow camera access to use this feature.', 'danger');
    }
}

// Load enrolled face data for current employee
async function loadEnrolledFace() {
    try {
        const response = await fetch('modules/attendance/get_face_data.php');
        const data = await response.json();

        if (data.success && data.descriptor) {
            // Convert descriptor array to Float32Array
            const descriptor = new Float32Array(data.descriptor);
            labeledDescriptors = [new faceapi.LabeledFaceDescriptors('<?= $employee['employee_number'] ?>', [descriptor])];
            console.log('Enrolled face data loaded successfully');
        } else {
            console.log('No enrolled face data found');
        }
    } catch (error) {
        console.error('Error loading enrolled face:', error);
    }
}

// Detect face continuously
async function detectFace() {
    const displaySize = { width: video.width, height: video.height };

    setInterval(async () => {
        if (!video.paused) {
            const detections = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (detections) {
                faceDetected = true;
                currentDescriptor = detections.descriptor;
                updateStatus('Face detected! You may proceed.', 'success');
                enableClockButtons();
            } else {
                faceDetected = false;
                currentDescriptor = null;
                updateStatus('No face detected. Please position your face in the camera.', 'warning');
                disableClockButtons();
            }
        }
    }, 1000);
}

function updateStatus(message, type) {
    const statusEl = document.getElementById('face-status');
    statusEl.className = `alert alert-${type} text-center`;
    statusEl.innerHTML = `<i class="bi bi-${getIcon(type)}"></i> ${message}`;
}

function getIcon(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'x-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function enableClockButtons() {
    const buttons = ['btn-time-in', 'btn-lunch-out', 'btn-lunch-in', 'btn-time-out'];
    buttons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.disabled = false;
    });
}

function disableClockButtons() {
    const buttons = ['btn-time-in', 'btn-lunch-out', 'btn-lunch-in', 'btn-time-out'];
    buttons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.disabled = true;
    });
}

// Clock action handler
async function performClockAction(action) {
    if (!faceDetected || !currentDescriptor) {
        alert('Please wait for face detection before clocking.');
        return;
    }

    updateStatus('Verifying face and processing...', 'info');
    disableClockButtons();

    try {
        // Capture current frame as image
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const imageData = canvas.toDataURL('image/jpeg');

        // Get geolocation
        let latitude = null, longitude = null;
        if (navigator.geolocation) {
            const position = await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(resolve, reject);
            });
            latitude = position.coords.latitude;
            longitude = position.coords.longitude;
        }

        // Send to server
        const formData = new FormData();
        formData.append('action', action);
        formData.append('face_descriptor', JSON.stringify(Array.from(currentDescriptor)));
        formData.append('selfie_image', imageData);
        formData.append('latitude', latitude);
        formData.append('longitude', longitude);

        const response = await fetch('modules/attendance/process_clock.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            updateStatus(result.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            updateStatus(result.message || 'Clock action failed', 'danger');
            enableClockButtons();
        }

    } catch (error) {
        console.error('Error:', error);
        updateStatus('An error occurred: ' + error.message, 'danger');
        enableClockButtons();
    }
}

// Attach event listeners
document.addEventListener('DOMContentLoaded', () => {
    const btnTimeIn = document.getElementById('btn-time-in');
    const btnLunchOut = document.getElementById('btn-lunch-out');
    const btnLunchIn = document.getElementById('btn-lunch-in');
    const btnTimeOut = document.getElementById('btn-time-out');

    if (btnTimeIn) btnTimeIn.addEventListener('click', () => performClockAction('time_in'));
    if (btnLunchOut) btnLunchOut.addEventListener('click', () => performClockAction('lunch_out'));
    if (btnLunchIn) btnLunchIn.addEventListener('click', () => performClockAction('lunch_in'));
    if (btnTimeOut) btnTimeOut.addEventListener('click', () => performClockAction('time_out'));

    // Initialize face recognition
    initFaceAPI();
});
</script>
