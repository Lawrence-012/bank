<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank System | Home</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    
    <!-- Google Font: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=2.3" rel="stylesheet">
</head>
<body class="landing-body overflow-hidden-lg">

    <div class="d-flex flex-column justify-content-between min-vh-100 container-xl px-4 py-2">
        <!-- Header / Navigation Bar -->
        <nav class="navbar navbar-expand-lg py-2">
            <div class="container-fluid px-0">
                <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                    <img src="assets/img/logo.png" alt="Bank Logo" class="brand-logo" onerror="this.src='https://via.placeholder.com/40'">
                    <div class="d-flex flex-column lh-1">
                        <span class="fw-bold text-navy fs-5">Bank System</span>
                        <small class="text-muted fst-italic" style="font-size: 0.65rem;">*logo to kunyare lang*</small>
                    </div>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <button class="btn btn-outline-transparent px-4 rounded-pill py-1" data-bs-toggle="modal" data-bs-target="#loginModal">Log In</button>
                    <button class="btn btn-primary-custom px-4 rounded-pill py-1" data-bs-toggle="modal" data-bs-target="#registerModal">Get Started</button>
                </div>
            </div>
        </nav>

        <!-- Main Hero Section -->
        <main class="my-auto py-2">
            <div class="row align-items-center">
                <div class="col-lg-6 pe-lg-4">
                    <h1 class="hero-title fw-bolder text-navy display-4 mb-2">
                        Banking That Works <br><span class="text-primary-custom">For You</span>
                    </h1>
                    <p class="lead my-3 fs-6" style="max-width: 420px;">
                        Secure, simple, and smart banking designed to help you manage your money with confidence.
                    </p>
                    <div class="d-flex gap-3 pt-2">
                        <button class="btn btn-primary-custom rounded-pill px-4 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#registerModal">Open Account</button>
                    </div>
                </div>
                <div class="col-lg-6 text-center text-lg-end d-none d-lg-block pe-lg-2">
                    <img src="assets/img/card.png" alt="Credit Card" class="img-fluid hero-card-img" style="max-height: 250px;" onerror="this.src='https://via.placeholder.com/400x250?text=Credit+Card'">
                </div>
            </div>
        </main>

        <!-- Footer / Services Bar -->
        <footer class="pt-3 pb-3 border-top" id="services">
            <div class="row text-center align-items-center">
                <div class="col-md-4 feature-box mb-2 mb-md-0 border-end-md">
                    <i class="bi bi-shield-check text-primary-custom fs-3 mb-1"></i>
                    <h6 class="fw-bold text-navy mb-1">Bank Securely</h6>
                    <p class="text-muted small mb-0 fs-7">Advanced security for your peace of mind.</p>
                </div>
                <div class="col-md-4 feature-box mb-2 mb-md-0 border-end-md">
                    <i class="bi bi-lightning text-primary-custom fs-3 mb-1"></i>
                    <h6 class="fw-bold text-navy mb-1">Transact Instantly</h6>
                    <p class="text-muted small mb-0 fs-7">Fast transfer and real-time payments.</p>
                </div>
                <div class="col-md-4 feature-box">
                    <i class="bi bi-pie-chart text-primary-custom fs-3 mb-1"></i>
                    <h6 class="fw-bold text-navy mb-1">Track Effortlessly</h6>
                    <p class="text-muted small mb-0 fs-7">Smart insights for better financial decisions.</p>
                </div>
            </div>
        </footer>
    </div>

    <!-- MODALS -->

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg style-landbank-modal overflow-hidden">
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Column: Big Logo, Name, and Tagline -->
                        <div class="col-md-5 d-none d-md-flex flex-column justify-content-center align-items-center p-4 text-center bg-light border-end">
                            <img src="assets/img/logo.png" alt="Bank Logo" class="img-fluid mb-3" style="max-height: 110px;" onerror="this.src='https://via.placeholder.com/100'">
                            <h4 class="fw-bold text-navy mb-1">Bank System</h4>
                            <p class="text-muted small fst-italic mb-0">*logo to kunyare lang*</p>
                        </div>

                        <!-- Right Column: Login Form -->
                        <div class="col-md-7 p-4 p-lg-5 position-relative">
                            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                            
                            <div class="mb-4">
                                <div class="d-md-none text-center mb-3">
                                    <img src="assets/img/logo.png" alt="Bank Logo" class="img-fluid mb-2" style="max-height: 60px;" onerror="this.src='https://via.placeholder.com/60'">
                                </div>
                                <h3 class="fw-bold text-navy mb-1">Welcome to Bank System Online!</h3>
                            </div>

                            <form id="loginForm">
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-navy small mb-1">Username</label>
                                    <input type="text" name="username" class="form-control custom-input" required autocomplete="username">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold text-navy small mb-1">Password</label>
                                    <input type="password" name="password" class="form-control custom-input" required autocomplete="current-password">
                                </div>
                                <div class="mb-4">
                                    <button type="submit" class="btn btn-navy-landbank rounded-3 px-4 py-2 fw-semibold">Login</button>
                                </div>
                            </form>

                            <div class="pt-3 border-top">
                                <span class="text-muted small">Don't have Online Banking yet? </span>
                                <a href="#" class="fw-bold text-primary-custom text-decoration-none small" data-bs-toggle="modal" data-bs-target="#registerModal">Open an Account</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg style-landbank-modal">
                <div class="modal-header border-0 pb-0 pt-3 px-4">
                    <div>
                        <span class="text-uppercase fw-semibold text-muted small tracking-wide" style="font-size: 0.725rem;">Online Account Opening</span>
                        <h4 class="fw-bold text-navy mb-0">CREATE AN ACCOUNT</h4>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="registerForm">
                    <div class="modal-body p-4 py-3">
                        <div class="card card-landbank border-0 p-3 mb-2">
                            
                            <!-- Personal Info -->
                            <div class="mb-2">
                                <label class="form-label fw-bold text-navy small mb-1">Full Name</label>
                                <input type="text" name="full_name" class="form-control custom-input" placeholder="e.g. Juan De La Cruz" required>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-navy small mb-1">Email Address</label>
                                    <input type="email" name="email" class="form-control custom-input" placeholder="name@example.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-navy small mb-1">Mobile Number</label>
                                    <input type="text" name="phone" class="form-control custom-input" placeholder="09123456789" required>
                                </div>
                            </div>

                            <hr class="my-2 text-muted opacity-25">

                            <!-- Login Security Credentials -->
                            <div class="mb-2">
                                <label class="form-label fw-bold text-navy small mb-1">Username</label>
                                <input type="text" name="username" class="form-control custom-input" placeholder="Choose a username" required autocomplete="username">
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-navy small mb-1">Password</label>
                                    <input type="password" name="password" id="reg_password" class="form-control custom-input" placeholder="••••••••" required autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-navy small mb-1">Confirm Password</label>
                                    <input type="password" id="reg_confirm_password" class="form-control custom-input" placeholder="••••••••" required autocomplete="new-password">
                                </div>
                            </div>

                            <!-- Terms Checkbox -->
                            <div class="terms-box p-2 border rounded bg-white mt-2">
                                <div class="form-check d-flex align-items-start gap-2">
                                    <input class="form-check-input mt-1" type="checkbox" id="termsCheck" required>
                                    <label class="form-check-label text-muted small" for="termsCheck" style="font-size: 0.75rem;">
                                        I agree to the Terms and Conditions and confirm that all details provided are accurate.
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="modal-footer border-0 px-4 pb-3 pt-0 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light border rounded-3 px-3 py-1 text-uppercase fw-semibold small" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-navy-landbank rounded-3 px-4 py-1 text-uppercase fw-semibold small">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>