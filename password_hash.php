<?php
// Initialize variables to avoid errors on first load
$originalPassword = '';
$hashedPassword = '';

// Check if the form has been submitted using the POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if the 'password' input field is not empty
    if (!empty($_POST['password'])) {
        // Get the password from the form
        $originalPassword = $_POST['password'];

        // Hash the password using PHP's recommended default algorithm
        $hashedPassword = password_hash($originalPassword, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hashing Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-shield-lock-fill"></i>
                            </div>
                            <div>
                                <h1 class="h4 mb-1">Password Hashing Tool</h1>
                                <p class="text-muted mb-0">Enter a password to generate a secure hash.</p>
                            </div>
                        </div>

                        <hr class="my-4">

                        <form action="" method="post" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Enter Password</label>
                                <input
                                    type="text"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    required
                                >
                                <div class="invalid-feedback">Please provide a password to hash.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Hash Password</button>
                        </form>

                        <?php if ($hashedPassword): ?>
                            <div class="alert alert-secondary mt-4 mb-0" role="alert">
                                <div class="mb-2">
                                    <span class="text-muted small text-uppercase">Original Password</span>
                                    <p class="mb-0 fw-semibold font-monospace"><?= htmlspecialchars($originalPassword) ?></p>
                                </div>
                                <div>
                                    <span class="text-muted small text-uppercase">Hashed Password</span>
                                    <p class="mb-0 fw-semibold font-monospace text-break"><?= htmlspecialchars($hashedPassword) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap validation styling
        (() => {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
