<?php

$pageTitle = 'Login - Laptro';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url-helper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? url('admin/index.php') : url('dashboard.php'));
}

initSession();
$conn = getDBConnection();
user_security_ensure_columns();
$redirect = $_GET['redirect'] ?? 'dashboard';
$errors = [];

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    verify_csrf_or_abort();
    $email = clean($_POST['email']);
    $password = $_POST['password'];

    $loginErrors = [];
    v_required($email, 'Email', $loginErrors);
    if (empty($loginErrors)) {
        v_email($email, 'email address', $loginErrors);
    }

    $userRules = constraints('user');
    $pwdRule = $userRules['password'] ?? ['min'=>1,'max'=>128];
    v_string_length($password, 'Password', 1, $pwdRule['max'], $loginErrors);

    // Rate limit (5 per minute)
    if (!rate_limit_allow('login_attempt', 5, 60)) {
        $loginErrors[] = 'Too many login attempts. Please wait a minute and try again.';
    }

    if (empty($loginErrors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    if (empty($loginErrors) && $user && password_verify($password, $user['password'])) {
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $reh = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $reh->execute([$newHash, $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        setFlash('success', 'Welcome back, ' . $user['name'] . '!');
        if (!empty($user['must_change_password'])) {
            redirect(url('dashboard.php#security'));
        } elseif ($user['is_admin'] == 1) {
            redirect(url('admin/index.php'));
        } else {
            redirect(url('dashboard.php'));
        }
    } else {
        $loginError = $loginErrors[0] ?? 'Invalid email or password';
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    verify_csrf_or_abort();
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $accountType = clean($_POST['account_type'] ?? 'customer');
    $adminSignupCode = trim((string)($_POST['admin_signup_code'] ?? ''));
    
    $errors = [];
    v_required($name, 'Full name', $errors);
    $userRules = constraints('user');
    $nameRule = $userRules['name'] ?? ['min'=>2,'max'=>100];
    v_string_length($name, 'Full name', $nameRule['min'], $nameRule['max'], $errors);
    v_required($email, 'Email', $errors);
    if (!empty($email)) v_email($email, 'email address', $errors);
    v_required($password, 'Password', $errors);
    $pwdRule = $userRules['password'] ?? ['min'=>8,'max'=>128];
    v_password_strict($password, $errors, 'Password', $pwdRule['min'], $pwdRule['max']);
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
    if (!in_array($accountType, ['customer', 'admin'], true)) $errors[] = 'Select a valid account type';
    if ($accountType === 'admin') {
        if (ADMIN_SIGNUP_CODE === '') {
            $errors[] = 'Admin registration is disabled';
        } elseif (!hash_equals(ADMIN_SIGNUP_CODE, $adminSignupCode)) {
            $errors[] = 'Invalid admin sign-up code';
        }
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered';
        } else {
            // Create user
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $isAdminAccount = $accountType === 'admin' ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, is_admin, must_change_password) VALUES (?, ?, ?, ?, 1)");
            
            if ($stmt->execute([$name, $email, $hashedPassword, $isAdminAccount])) {
                $userId = $conn->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                $_SESSION['is_admin'] = $isAdminAccount;
                
                setFlash('success', 'Account created successfully! Welcome to Laptro.');
                redirect(url('dashboard.php#security'));
            } else {
                $errors[] = 'Error creating account. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section-padding">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="row g-0 shadow-sm" style="border-radius: 8px; overflow: hidden;">
                <div class="col-lg-6 p-5" style="background-color: white;">
                    <h2 class="mb-4">Login</h2>
                    
                    <?php if (isset($loginError)): ?>
                        <div class="alert alert-danger"><?php echo $loginError; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" aria-labelledby="login-heading">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label" for="login_email">Email address</label>
                            <input type="email" id="login_email" name="email" class="form-control" required autocomplete="email">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="login_password">Password</label>
                            <div class="position-relative">
                                <input type="password" id="login_password" name="password" class="form-control pe-5" required autocomplete="current-password">
                                <button type="button" data-password-toggle data-target="login_password" class="btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y px-3" aria-label="Show password" aria-pressed="false">
                                    <i class="far fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary w-100">
                            Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">Demo Admin: admin@laptro.com / password</p>
                    </div>
                </div>
                
                <div class="col-lg-6 p-5" style="background-color: var(--off-white);">
                    <h2 class="mb-4" id="register-heading">Create Account</h2>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" aria-labelledby="register-heading">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label" for="account_type">Account type</label>
                            <select id="account_type" name="account_type" class="form-control" required>
                                <option value="customer" <?php echo ($_POST['account_type'] ?? 'customer') === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                <option value="admin" <?php echo ($_POST['account_type'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="reg_name">Full name</label>
                            <input type="text" id="reg_name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="reg_email">Email address</label>
                            <input type="email" id="reg_email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="reg_password">Password</label>
                            <div class="position-relative">
                                <input type="password" id="reg_password" name="password" class="form-control pe-5" required aria-describedby="pwd_help">
                                <button type="button" data-password-toggle data-target="reg_password" class="btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y px-3" aria-label="Show password" aria-pressed="false">
                                    <i class="far fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="pwd_help" class="form-text small">
                                <span class="text-muted me-3" data-pwd-rule="len"><i class="far fa-circle me-1"></i>6+ chars</span>
                                <span class="text-muted me-3" data-pwd-rule="upper"><i class="far fa-circle me-1"></i>Uppercase</span>
                                <span class="text-muted me-3" data-pwd-rule="lower"><i class="far fa-circle me-1"></i>Lowercase</span>
                                <span class="text-muted" data-pwd-rule="digit"><i class="far fa-circle me-1"></i>Number</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="reg_confirm">Confirm password</label>
                            <div class="position-relative">
                                <input type="password" id="reg_confirm" name="confirm_password" class="form-control pe-5" required>
                                <button type="button" data-password-toggle data-target="reg_confirm" class="btn btn-sm btn-link text-muted position-absolute top-50 end-0 translate-middle-y px-3" aria-label="Show password" aria-pressed="false">
                                    <i class="far fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3" id="admin-signup-code-wrap" <?php echo ($_POST['account_type'] ?? 'customer') === 'admin' ? '' : 'hidden'; ?>>
                            <label class="form-label" for="admin_signup_code">Admin sign-up code</label>
                            <input type="password" id="admin_signup_code" name="admin_signup_code" class="form-control" value="<?php echo htmlspecialchars($_POST['admin_signup_code'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the Terms & Conditions
                            </label>
                        </div>
                        
                        <button type="submit" name="register" class="btn btn-primary w-100">
                            Create Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
