<?php
$pageTitle = 'Customer Form - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

$conn = getDBConnection();
$customer = null;
$isEdit = false;
$formErrors = [];

if (isset($_GET['id'])) {
    $isEdit = true;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
    $stmt->execute([(int)$_GET['id']]);
    $customer = $stmt->fetch();
    if (!$customer) {
        setFlash('error', 'Customer not found');
        redirect(url('admin/customers.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();

    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $address = clean($_POST['address'] ?? '');
    $city = clean($_POST['city'] ?? '');
    $postalCode = clean($_POST['postal_code'] ?? '');
    $password = $_POST['password'] ?? '';

    $userRules = constraints('user');
    $nameRule = $userRules['name'] ?? ['min' => 2, 'max' => 100];
    $pwdRule = $userRules['password'] ?? ['min' => 6, 'max' => 128];

    v_required($name, 'Full name', $formErrors);
    v_string_length($name, 'Full name', $nameRule['min'], $nameRule['max'], $formErrors);
    v_required($email, 'Email', $formErrors);
    if ($email !== '') {
        v_email($email, 'email address', $formErrors);
    }
    if ($phone !== '') {
        v_matches($phone, 'Phone', '/^[0-9\+\-\s\(\)]{7,20}$/', $formErrors, 'Phone number format is invalid');
    }
    if ($address !== '') {
        v_string_length($address, 'Address', 5, 200, $formErrors);
    }
    if ($city !== '') {
        v_string_length($city, 'City', 2, 100, $formErrors);
    }
    if ($postalCode !== '') {
        v_matches($postalCode, 'Postal code', '/^[A-Za-z0-9 \-]{3,12}$/', $formErrors, 'Postal code should be 3-12 letters/digits');
    }
    if (!$isEdit || $password !== '') {
        v_password_strict($password, $formErrors, 'Password', $pwdRule['min'], $pwdRule['max']);
    }

    if (empty($formErrors)) {
        $sql = "SELECT id FROM users WHERE email = ? AND is_admin = 0";
        $params = [$email];
        if ($isEdit) {
            $sql .= " AND id <> ?";
            $params[] = (int)$customer['id'];
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $formErrors[] = 'Email already registered';
        }
    }

    if (empty($formErrors)) {
        if ($isEdit) {
            if ($password !== '') {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ?, password = ?, must_change_password = 1 WHERE id = ? AND is_admin = 0");
                $stmt->execute([$name, $email, $phone ?: null, $address ?: null, $city ?: null, $postalCode ?: null, password_hash($password, PASSWORD_BCRYPT), (int)$customer['id']]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ? WHERE id = ? AND is_admin = 0");
                $stmt->execute([$name, $email, $phone ?: null, $address ?: null, $city ?: null, $postalCode ?: null, (int)$customer['id']]);
            }
            setFlash('success', 'Customer updated successfully.');
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, city, postal_code, is_admin, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $phone ?: null, $address ?: null, $city ?: null, $postalCode ?: null]);
            setFlash('success', 'Customer created successfully.');
        }
        redirect(url('admin/customers.php'));
    }

    $customer = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'city' => $city,
        'postal_code' => $postalCode,
    ];
}

require_once '../includes/header.php';
?>

<aside class="admin-sidebar">
    <div class="text-center ">
        <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link" href="<?php echo url('admin/stock-receipts.php'); ?>"><i class="fas fa-boxes-stacked me-2"></i>Stock In</a>
        <a class="nav-link" href="<?php echo url('admin/inventory-reports.php'); ?>"><i class="fas fa-chart-column me-2"></i>Reports</a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link active" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
        <a class="nav-link" href="<?php echo url('admin/assistance.php'); ?>"><i class="fas fa-headset me-2"></i>Assistance</a>
        <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
</aside>

<main class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $isEdit ? 'Edit Customer' : 'Add Customer'; ?></h1>
        <a href="<?php echo url('admin/customers.php'); ?>" class="btn btn-outline">Back to Customers</a>
    </div>

    <div class="dashboard-card">
        <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($formErrors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="cust_name">Full Name</label>
                    <input id="cust_name" type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="cust_email">Email Address</label>
                    <input id="cust_email" type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="cust_phone">Phone</label>
                    <input id="cust_phone" type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="cust_postcode">Postal Code</label>
                    <input id="cust_postcode" type="text" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($customer['postal_code'] ?? ''); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="cust_address">Address</label>
                <input id="cust_address" type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="cust_city">City</label>
                <input id="cust_city" type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label" for="cust_password"><?php echo $isEdit ? 'New Password' : 'Password'; ?></label>
                <input id="cust_password" type="password" name="password" class="form-control" <?php echo $isEdit ? '' : 'required'; ?>>
                <?php if ($isEdit): ?>
                    <div class="form-text">Leave blank to keep the current password.</div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save Changes' : 'Create Customer'; ?></button>
        </form>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>
