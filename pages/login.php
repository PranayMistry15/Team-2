<?php

$pageTitle = 'Login - Laptro';
require_once __DIR__ . '/../includes/header.php';

// Login Check
if (isLoggedIn()) {
    redirect(url('dashboard.php'));
}

$conn = getDBConnection();
$redirect = $_GET['redirect'] ?? 'dashboard';

// Handler
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

    // Rate limit per 5 mins
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
        // Redirect admin to panel and users to dashboard
        if ($user['is_admin'] == 1) {
            redirect(url('admin/index.php'));
        } else {
            redirect(url('dashboard.php'));
        }
    } else {
        $loginError = $loginErrors[0] ?? 'Invalid email or password';
    }
}

// Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    verify_csrf_or_abort();
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
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
    
    if (empty($errors)) {
        // Queries the db
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered';
        } else {
            // Creates User
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $hashedPassword])) {
                $userId = $conn->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                $_SESSION['is_admin'] = 0;
                
                setFlash('success', 'Account created successfully! Welcome to Laptro.');
                redirect(url('dashboard.php'));
            } else {
                $errors[] = 'Error creating account. Please try again.';
            }
        }
    }
}
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
                            <input type="password" id="login_password" name="password" class="form-control" required autocomplete="current-password">
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
                            <input type="password" id="reg_password" name="password" class="form-control" required aria-describedby="pwd_help">
                            <small id="pwd_help" class="text-muted">Minimum 6 characters; include upper, lower and a digit.</small>   
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="reg_confirm">Confirm password</label>
                            <input type="password" id="reg_confirm" name="confirm_password" class="form-control" required>
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
                    
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            Your information is secure
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
