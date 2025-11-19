<?php
/**
 * Face Enrollment Page
 * Allows employees to register their face for attendance recognition
 */

// Get current user info
$user_id = $_SESSION['user_id'];
$employee_id = $_SESSION['employee_id'] ?? null;

$errors = [];
$success_message = '';

// Get employee info
if ($employee_id) {
    $emp_result = executeQuery(
        "SELECT e.*, c.name as company_name
         FROM employees e
         LEFT JOIN companies c ON e.company_id = c.id
         WHERE e.id = ? AND e.is_deleted = 0",
        "i",
        [$employee_id]
    );
    $employee = $emp_result->fetch_assoc();
} else {
    $errors[] = "No employee record found for your account.";
}

// Check existing face data
$existing_face_data = [];
if ($employee_id) {
    $face_result = executeQuery(
        "SELECT * FROM face_data WHERE employee_id = ? AND is_deleted = 0 ORDER BY created_at DESC",
        "i",
        [$employee_id]
    );
    while ($row = $face_result->fetch_assoc()) {
        $existing_face_data[] = $row;
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Face Enrollment</h1>
    <div class="page-actions">
        <a href="index.php?page=attendance/clock" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Clock
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

<div class="row">
    <div class="col-md-8">
        <!-- Enrollment Interface -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Enroll Your Face</h5>
                <p class="text-muted">Follow the instructions below to register your face for attendance verification.</p>

                <!-- Video Feed -->
                <div class="text-center mb-3">
                    <video id="video" width="640" height="480" autoplay muted style="max-width: 100%; border: 2px solid #ddd; border-radius: 8px;"></video>
                    <canvas id="canvas" style="display: none;"></canvas>
                </div>

                <!-- Status Messages -->
                <div id="enrollment-status" class="alert alert-info text-center">
                    <i class="bi bi-camera-video"></i> Initializing camera...
                </div>

                <!-- Captured Images Preview -->
                <div id="captured-previews" class="row mb-3" style="display: none;">
                    <div class="col-12">
                        <h6>Captured Images (<span id="capture-count">0</span>/3)</h6>
                    </div>
                    <div class="col-4" id="preview-1"></div>
                    <div class="col-4" id="preview-2"></div>
                    <div class="col-4" id="preview-3"></div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    <button type="button" id="btn-capture" class="btn btn-primary btn-lg" disabled>
                        <i class="bi bi-camera"></i> Capture Face (0/3)
                    </button>
                    <button type="button" id="btn-enroll" class="btn btn-success btn-lg" style="display: none;">
                        <i class="bi bi-check-circle"></i> Complete Enrollment
                    </button>
                    <button type="button" id="btn-reset" class="btn btn-secondary" style="display: none;">
                        <i class="bi bi-arrow-clockwise"></i> Start Over
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Instructions -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-info-circle"></i> Instructions</h6>
                <ol class="small">
                    <li>Position your face in the camera view</li>
                    <li>Ensure good lighting on your face</li>
                    <li>Remove glasses, masks, or hats if possible</li>
                    <li>Look directly at the camera</li>
                    <li>Click "Capture Face" 3 times from different angles</li>
                    <li>Click "Complete Enrollment" to save</li>
                </ol>
            </div>
        </div>

        <!-- Tips -->
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-lightbulb"></i> Tips for Best Results</h6>
                <ul class="small">
                    <li>Use natural or bright lighting</li>
                    <li>Capture from slightly different angles</li>
                    <li>Keep a neutral expression</li>
                    <li>Avoid shadows on your face</li>
                </ul>
            </div>
        </div>

        <!-- Existing Enrollments -->
        <?php if (!empty($existing_face_data)): ?>
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-person-check"></i> Existing Enrollments</h6>
                    <ul class="list-unstyled small">
                        <?php foreach ($existing_face_data as $face): ?>
                            <li class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-<?= $face['is_active'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                        <?= date('M d, Y', strtotime($face['enrollment_date'])) ?>
                                    </div>
                                    <span class="badge bg-<?= $face['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $face['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
#video {
    transform: scaleX(-1);
}

.captured-preview {
    width: 100%;
    border: 2px solid #28a745;
    border-radius: 8px;
    margin-bottom: 10px;
}
</style>

<!-- Face API Library -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

<script>
let video;
let capturedDescriptors = [];
let capturedImages = [];
let captureCount = 0;
const REQUIRED_CAPTURES = 3;

// Initialize Face API
async function initFaceAPI() {
    try {
        updateStatus('Loading face recognition models...', 'info');

        // Load models
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
            video: { width: 640, height: 480, facingMode: 'user' }
        });
        video.srcObject = stream;

        video.addEventListener('play', () => {
            monitorFaceDetection();
        });

    } catch (error) {
        console.error('Error accessing camera:', error);
        updateStatus('Camera access denied. Please allow camera access.', 'danger');
    }
}

// Monitor face detection
async function monitorFaceDetection() {
    setInterval(async () => {
        if (!video.paused && captureCount < REQUIRED_CAPTURES) {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (detection) {
                updateStatus('Face detected! Click "Capture Face" to capture.', 'success');
                document.getElementById('btn-capture').disabled = false;
            } else {
                updateStatus('No face detected. Please position your face in the camera.', 'warning');
                document.getElementById('btn-capture').disabled = true;
            }
        }
    }, 500);
}

// Capture face
async function captureFace() {
    try {
        updateStatus('Capturing face...', 'info');

        const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            updateStatus('No face detected. Please try again.', 'danger');
            return;
        }

        // Store descriptor
        capturedDescriptors.push(Array.from(detection.descriptor));

        // Capture image
        const canvas = document.getElementById('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const imageData = canvas.toDataURL('image/jpeg');
        capturedImages.push(imageData);

        captureCount++;

        // Update UI
        updateCaptureUI();

        if (captureCount >= REQUIRED_CAPTURES) {
            updateStatus('All captures complete! Click "Complete Enrollment" to save.', 'success');
            document.getElementById('btn-capture').style.display = 'none';
            document.getElementById('btn-enroll').style.display = 'block';
            document.getElementById('btn-reset').style.display = 'block';
        } else {
            updateStatus(`Face captured (${captureCount}/${REQUIRED_CAPTURES})! Capture from a different angle.`, 'success');
        }

    } catch (error) {
        console.error('Error capturing face:', error);
        updateStatus('Error capturing face: ' + error.message, 'danger');
    }
}

// Update capture UI
function updateCaptureUI() {
    document.getElementById('btn-capture').innerHTML = `<i class="bi bi-camera"></i> Capture Face (${captureCount}/${REQUIRED_CAPTURES})`;
    document.getElementById('capture-count').textContent = captureCount;
    document.getElementById('captured-previews').style.display = 'block';

    // Show preview
    const previewDiv = document.getElementById(`preview-${captureCount}`);
    const img = document.createElement('img');
    img.src = capturedImages[captureCount - 1];
    img.className = 'captured-preview';
    previewDiv.innerHTML = '';
    previewDiv.appendChild(img);
}

// Reset enrollment
function resetEnrollment() {
    capturedDescriptors = [];
    capturedImages = [];
    captureCount = 0;

    document.getElementById('btn-capture').innerHTML = '<i class="bi bi-camera"></i> Capture Face (0/3)';
    document.getElementById('btn-capture').style.display = 'block';
    document.getElementById('btn-enroll').style.display = 'none';
    document.getElementById('btn-reset').style.display = 'none';
    document.getElementById('captured-previews').style.display = 'none';
    document.getElementById('capture-count').textContent = '0';

    ['preview-1', 'preview-2', 'preview-3'].forEach(id => {
        document.getElementById(id).innerHTML = '';
    });

    updateStatus('Ready to start enrollment', 'info');
}

// Complete enrollment
async function completeEnrollment() {
    if (capturedDescriptors.length < REQUIRED_CAPTURES) {
        alert('Please capture at least ' + REQUIRED_CAPTURES + ' images');
        return;
    }

    updateStatus('Processing enrollment...', 'info');
    document.getElementById('btn-enroll').disabled = true;

    try {
        // Average the descriptors for better accuracy
        const avgDescriptor = averageDescriptors(capturedDescriptors);

        // Send to server
        const formData = new FormData();
        formData.append('face_descriptor', JSON.stringify(avgDescriptor));
        formData.append('face_images', JSON.stringify(capturedImages));

        const response = await fetch('modules/attendance/save_enrollment.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            updateStatus('Enrollment completed successfully!', 'success');
            setTimeout(() => {
                window.location.href = 'index.php?page=attendance/clock';
            }, 2000);
        } else {
            updateStatus(result.message || 'Enrollment failed', 'danger');
            document.getElementById('btn-enroll').disabled = false;
        }

    } catch (error) {
        console.error('Error:', error);
        updateStatus('An error occurred: ' + error.message, 'danger');
        document.getElementById('btn-enroll').disabled = false;
    }
}

// Average multiple descriptors
function averageDescriptors(descriptors) {
    const descriptorLength = descriptors[0].length;
    const avgDescriptor = new Array(descriptorLength).fill(0);

    for (let i = 0; i < descriptorLength; i++) {
        let sum = 0;
        for (const descriptor of descriptors) {
            sum += descriptor[i];
        }
        avgDescriptor[i] = sum / descriptors.length;
    }

    return avgDescriptor;
}

function updateStatus(message, type) {
    const statusEl = document.getElementById('enrollment-status');
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

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btn-capture').addEventListener('click', captureFace);
    document.getElementById('btn-enroll').addEventListener('click', completeEnrollment);
    document.getElementById('btn-reset').addEventListener('click', resetEnrollment);

    initFaceAPI();
});
</script>