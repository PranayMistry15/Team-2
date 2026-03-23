<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url-helper.php';
require_once __DIR__ . '/../includes/support_chat.php';

$pageTitle = 'Contact Us - Laptro';
$conn = getDBConnection();

$formData = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'order_id' => '',
    'message' => '',
];
$errors = [];

if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([getUserId()]);
    $user = $stmt->fetch();
    if ($user) {
        $formData['name'] = (string)$user['name'];
        $formData['email'] = (string)$user['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();

    $formData['name'] = clean($_POST['name'] ?? '');
    $formData['email'] = clean($_POST['email'] ?? '');
    $formData['subject'] = clean($_POST['subject'] ?? '');
    $formData['order_id'] = clean($_POST['order_id'] ?? '');
    $formData['message'] = trim((string)($_POST['message'] ?? ''));

    v_required($formData['name'], 'Full name', $errors);
    v_string_length($formData['name'], 'Full name', 2, 100, $errors);

    v_required($formData['email'], 'Email', $errors);
    if ($formData['email'] !== '') {
        v_email($formData['email'], 'email address', $errors);
    }

    v_required($formData['subject'], 'Subject', $errors);
    v_string_length($formData['subject'], 'Subject', 4, 120, $errors);

    v_required($formData['message'], 'Message', $errors);
    v_string_length($formData['message'], 'Message', 10, 1200, $errors);

    if ($formData['order_id'] !== '') {
        v_matches($formData['order_id'], 'Order reference', '/^[A-Za-z0-9\\-#]{1,30}$/', $errors, 'Order reference should use only letters, numbers, #, or -');
    }

    if (!rate_limit_allow('contact_form_submit', 5, 300)) {
        $errors[] = 'Too many contact requests. Please wait a few minutes and try again.';
    }

    if (empty($errors)) {
        try {
            $conversationId = support_chat_create_contact_request($conn, [
                'name' => $formData['name'],
                'email' => $formData['email'],
                'subject' => $formData['subject'],
                'order_id' => $formData['order_id'],
                'message' => $formData['message'],
                'user_id' => isLoggedIn() ? getUserId() : null,
            ]);

            if (isLoggedIn()) {
                notify_user_by_id(getUserId(), 'Contact request submitted', 'Your support request has been sent to the admin team.');
            }

            setFlash('success', 'Your message has been sent to the admin team. Reference #' . $conversationId . '.');
            redirect(url('contact.php'));
        } catch (Throwable $e) {
            app_log_error('Contact form submission failed: ' . $e->getMessage());
            $errors[] = 'We could not send your message right now. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-hero contact-page-hero">
    <div class="container">
        <div class="page-hero-copy">
            <span class="eyebrow">Contact Us</span>
            <h1>Contact us</h1>
            <p class="lead mb-0">Send us a message if you need help with a product or an order.</p>
        </div>
    </div>
</section>

<section class="section-padding contact-page-section">
    <div class="container">
        <div class="contact-layout">
            <div class="dashboard-card contact-form-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <span class="eyebrow">Message Form</span>
                        <h2 class="mb-1">Send a message</h2>
                        <p class="text-muted mb-0">We will receive it in the admin panel.</p>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo url('contact.php'); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="contact_name">Full Name</label>
                            <input id="contact_name" type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="contact_email">Email Address</label>
                            <input id="contact_email" type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label" for="contact_subject">Subject</label>
                            <input id="contact_subject" type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($formData['subject']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="contact_order_id">Order Reference</label>
                            <input id="contact_order_id" type="text" name="order_id" class="form-control" value="<?php echo htmlspecialchars($formData['order_id']); ?>" placeholder="#1042">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_message">Message</label>
                        <textarea id="contact_message" name="message" class="form-control" rows="4" required><?php echo htmlspecialchars($formData['message']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>

            <div class="d-flex flex-column gap-4">
                <div class="brand-panel">
                    <span class="eyebrow">Details</span>
                    <h3>Contact details</h3>
                    <ul class="contact-details-list">
                        <li><i class="fas fa-envelope"></i><span>support@laptro.com</span></li>
                        <li><i class="fas fa-phone"></i><span>+44 123 456 7890</span></li>
                        <li><i class="fas fa-map-marker-alt"></i><span>Birmingham, United Kingdom</span></li>
                    </ul>
                </div>

                <div class="info-card">
                    <span class="eyebrow">Quick Note</span>
                    <h4 class="mb-2">Before you send</h4>
                    <p class="mb-2">If your message is about an order, add the order reference.</p>
                    <p class="mb-0">If it is about a product, just write the laptop name.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
