<?php

$pageTitle = 'Assistance - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';
require_once '../includes/support_chat.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

$conn = getDBConnection();
support_chat_ensure_tables($conn);

$supportConfig = [
    'apiListUrl' => url('admin/assistance-api.php?action=list'),
    'apiMessagesUrl' => url('admin/assistance-api.php?action=messages'),
    'apiSendUrl' => url('admin/assistance-api.php?action=send'),
    'apiCloseUrl' => url('admin/assistance-api.php?action=close'),
    'csrfToken' => csrf_token(),
];

require_once '../includes/header.php';
?>

<aside class="admin-sidebar">
    <div class="text-center">
        <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link" href="<?php echo url('admin/stock-receipts.php'); ?>"><i class="fas fa-boxes-stacked me-2"></i>Stock In</a>
        <a class="nav-link" href="<?php echo url('admin/inventory-reports.php'); ?>"><i class="fas fa-chart-column me-2"></i>Reports</a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
        <a class="nav-link active" href="<?php echo url('admin/assistance.php'); ?>"><i class="fas fa-headset me-2"></i>Assistance</a>
        <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
</aside>

<main class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Assistance</h1>
        <p class="text-muted mb-0">Live customer support queue</p>
    </div>

    <section class="assistance-layout">
        <aside class="assistance-conversations">
            <div class="assistance-heading">Conversations</div>
            <div id="assistance-conversation-list" class="assistance-list"></div>
        </aside>

        <section class="assistance-chat">
            <div class="assistance-chat-header">
                <span id="assistance-chat-header">Select a conversation</span>
                <button type="button" id="assistance-chat-close-conversation" class="assistance-chat-close-conversation">Close conversation</button>
            </div>
            <div id="assistance-chat-messages" class="assistance-chat-messages"></div>
            <div class="assistance-chat-input-wrap">
                <input type="text" id="assistance-chat-input" class="assistance-chat-input" maxlength="1000" placeholder="Reply to customer...">
                <button type="button" id="assistance-chat-send" class="assistance-chat-send">Send</button>
            </div>
        </section>
    </section>
</main>

<script type="application/json" id="laptro-assistance-config"><?php echo json_encode($supportConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<?php $assistanceVersion = file_exists(__DIR__ . '/../js/admin-assistance.js') ? filemtime(__DIR__ . '/../js/admin-assistance.js') : time(); ?>
<script src="<?php echo url('js/admin-assistance.js?v=' . $assistanceVersion); ?>"></script>

<?php require_once '../includes/footer.php'; ?>
