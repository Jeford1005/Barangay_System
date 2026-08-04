<?php
require_once __DIR__ . '/config.php';
redirect_if_authenticated();
$csrf_token = generate_csrf_token();

// Handle AJAX login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    $response = ['status' => 'error', 'message' => 'Invalid request.'];
    
    try {
        $inputRole = $_POST['role'] ?? 'admin';
        $inputUsername = trim($_POST['username'] ?? '');
        $inputPassword = $_POST['password'] ?? '';
        $postCsrf = $_POST['csrf_token'] ?? '';
        
        if (!verify_csrf_token($postCsrf)) {
            $response['message'] = 'Invalid security token. Please refresh and try again.';
            echo json_encode($response);
            exit;
        }
        
        if (empty($inputUsername) || empty($inputPassword)) {
            $response['message'] = 'Please enter both username and password.';
            echo json_encode($response);
            exit;
        }
        
        // Build query: match username OR email
        $stmt = $pdo->prepare("
            SELECT id, username, email, password, full_name, role, status
            FROM users
            WHERE (username = :username OR email = :email)
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':username' => $inputUsername, ':email' => $inputUsername]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($inputPassword, $user['password'])) {
            $response['message'] = 'Invalid username or password. Please try again.';
            echo json_encode($response);
            exit;
        }
        
        // Role check: if role is set, verify match
        if (!empty($inputRole) && $user['role'] !== $inputRole && $inputRole !== 'resident') {
            // Allow resident login if user has resident_id, otherwise block
            if ($inputRole === 'resident' && empty($user['resident_id'])) {
                $response['message'] = 'This account is not linked to a resident profile.';
                echo json_encode($response);
                exit;
            }
        }
        
        // Successful login - set session
        secure_session_regenerate();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);
        
        // Audit log
        log_audit('login', 'user', $user['id']);
        
        $redirectUrl = ($user['role'] === 'admin' || $user['role'] === 'staff') 
            ? '/BARANGAY_MANAGEMENT/dashboard.php' 
            : '/BARANGAY_MANAGEMENT/resident-dashboard.php';
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful! Redirecting...',
            'redirect' => $redirectUrl
        ]);
        exit;
    } catch (Exception $e) {
        error_log('Login Error: ' . $e->getMessage());
        $response['message'] = 'An error occurred. Please try again later.';
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Barangay Bidduang</title>
    <link rel="shortcut icon" type="image/png" href="assets/img/Brgy_Bidduang.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/login-fix.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
</head>
<body>
    <div class="auth-container" id="authContainer">
        <!-- Left Panel: Branding -->
        <div class="auth-left">
            <div class="halftone-bg">
                <div class="stripe-overlay"></div>
            </div>
            <div class="brand-content">
                <div class="seal-circle">
                    <img src="assets/img/Brgy_Bidduang.png" alt="Barangay Bidduang Seal" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
                <h1>Barangay Bidduang</h1>
                <p>Secure access to barangay services and records.</p>
                <div class="brand-icons">
                    <i class="fas fa-shield-alt"></i>
                    <i class="fas fa-lock"></i>
                    <i class="fas fa-user-shield"></i>
                </div>
            </div>
        </div>

        <!-- Right Panel: Forms -->
        <div class="auth-right">
            <!-- Login Form -->
            <div class="form-panel login-panel" id="loginPanel">
                <div class="form-header">
                    <p>Welcome back! Please enter your credentials.</p>
                </div>

                <!-- Role Switcher -->
                <div class="role-switcher">
                    <button type="button" class="role-btn active" data-role="admin" id="roleAdmin">
                        <i class="fas fa-user-shield"></i> Admin / Official
                    </button>
                    <button type="button" class="role-btn" data-role="resident" id="roleResident">
                        <i class="fas fa-user"></i> Resident
                    </button>
                </div>

                <form id="loginForm" novalidate>
                    <input type="hidden" name="role" value="admin" id="loginRoleInput">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="form-group">
                        <label for="loginUsername">
                            <i class="fas fa-user"></i> Username/Email
                        </label>
                        <input type="text" id="loginUsername" name="username" required
                               placeholder="Enter your username or email" autocomplete="username">
                        <span class="error-msg" id="loginUsernameError"></span>
                    </div>

                    <div class="form-group">
                        <label for="loginPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="loginPassword" name="password" required
                                   placeholder="Enter your password" autocomplete="current-password">
                            <button type="button" class="toggle-password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-msg" id="loginPasswordError"></span>
                    </div>

                    <button type="submit" class="btn-submit" id="loginSubmitBtn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>

                    <div class="form-footer">
                        <p>Don't have an account? 
                            <button type="button" class="link-btn" id="showRegisterBtn">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </p>
                    </div>
                </form>

                <div class="alert" id="loginAlert" style="display:none;"></div>
            </div>
        </div>

        <!-- Register Overlay (slides in from right) -->
        <div class="register-overlay" id="registerOverlay">
            <div class="register-panel">
                <div class="register-header">
                    <h2><i class="fas fa-user-plus"></i> Resident Registration</h2>
                    <p>Create your resident account to access services.</p>
                    <button type="button" class="close-register" id="closeRegisterBtn" aria-label="Close registration">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="registerForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="form-group">
                        <label for="regFullName">
                            <i class="fas fa-id-card"></i> Full Name
                        </label>
                        <input type="text" id="regFullName" name="full_name" required
                               placeholder="Enter your full name" autocomplete="name">
                        <span class="error-msg" id="regFullNameError"></span>
                    </div>

                    <div class="form-group">
                        <label for="regEmail">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="regEmail" name="email" required
                               placeholder="Example@Gmail.Com" autocomplete="email">
                        <span class="error-msg" id="regEmailError"></span>
                    </div>

                    <div class="form-group">
                        <label for="regPhone">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" id="regPhone" name="phone" required
                               placeholder="09XXXXXXXXX" autocomplete="tel">
                        <span class="error-msg" id="regPhoneError"></span>
                    </div>

                    <div class="form-group">
                        <label for="regAddress">
                            <i class="fas fa-map-marker-alt"></i> Address
                        </label>
                        <input type="text" id="regAddress" name="address" required
                               placeholder="Your residential address">
                        <span class="error-msg" id="regAddressError"></span>
                    </div>

                    <div class="form-group">
                        <label for="regUsername">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <input type="text" id="regUsername" name="username" required
                               placeholder="Choose a username" autocomplete="username">
                        <span class="error-msg" id="regUsernameError"></span>
                    </div>

                    <div class="form-group">
                        <label for="regPassword">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="regPassword" name="password" required
                                   placeholder="Create a password" autocomplete="new-password">
                            <button type="button" class="toggle-password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-msg" id="regPasswordError"></span>
                    </div>

                    <div class="form-group">
                        <label for="regConfirmPassword">
                            <i class="fas fa-check-circle"></i> Confirm Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" id="regConfirmPassword" name="confirm_password" required
                                   placeholder="Repeat your password" autocomplete="new-password">
                            <button type="button" class="toggle-password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <span class="error-msg" id="regConfirmPasswordError"></span>
                    </div>

                    <button type="submit" class="btn-submit" id="registerSubmitBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>

                    <div class="form-footer">
                        <p>Already have an account? 
                            <button type="button" class="link-btn" id="showLoginBtn">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </p>
                    </div>
                </form>

                <div class="alert" id="registerAlert" style="display:none;"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>