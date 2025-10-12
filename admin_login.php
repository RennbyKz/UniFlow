<?php
// admin_login.php
ob_start();
require_once 'core/functions.php';
init_system('admin_login_title');

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    header("Location: redirect.php");
    ob_end_flush();
    exit();
}

$error_message = '';
$toast_type = 'error'; // For toast.js (error, success, warning, info)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = sanitize_input($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT user_id, password, role 
        FROM users 
        WHERE (email = ? OR username = ?) AND role = 'admin'
    ");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // ✅ Success
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $login_input;
        $_SESSION['login_success'] = true;

        header("Location: redirect.php");
        ob_end_flush();
        exit();
    } else {
        // ❌ Fail
        $error_message = $lang['invalid_credentials'] ?? 'Invalid username or password.';
        $toast_type = 'error';
    }
}
?>

<?php include 'templates/header.php'; ?>

<!-- ✅ Toast container -->
<div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>

<!-- ✅ Login Form -->
<div class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)]">
    <div class="w-full max-w-md rounded-2xl shadow-2xl p-8 login-card">
        <div class="text-center mb-6">
            <img src="assets/images/UNIFLOW.png" alt="UNIFLOW Logo" style="height: 180px; width: auto; margin: 0 auto;">
            <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                <?php echo $lang['admin_login_title'] ?? 'Admin Login'; ?>
            </h1><br>
            <p class="mt-1 text-sm" style="color: var(--color-text-card); opacity: 0.7;">
                <?php echo $lang['enter_admin_credentials'] ?? 'Enter your credentials to access the admin panel'; ?>
            </p>
        </div>

        <form method="POST" action="admin_login.php" class="space-y-5">
            <div>
                <label for="login_input" class="block font-medium mb-1" style="color: var(--color-text-card);">
                    <?php echo $lang['label_user_login'] ?? 'Username or Email'; ?>
                </label>
                <input type="text" id="login_input" name="login_input" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                       style="border-color: var(--color-border); background-color: var(--color-bg-main); color: var(--color-text-card);">
            </div>

            <div>
                <label for="password" class="block font-medium mb-1" style="color: var(--color-text-card);">
                    <?php echo $lang['password_label'] ?? 'Password'; ?>
                </label>
                <input type="password" id="password" name="password" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                       style="border-color: var(--color-border); background-color: var(--color-bg-main); color: var(--color-text-card);">
            </div>

            <button type="submit"
                    class="w-full font-semibold py-2 rounded-lg transition duration-300 hover:brightness-110 shadow-lg button-primary"
                    style="background-color: var(--color-primary); color: var(--color-bg-card);">
                <?php echo $lang['login_button'] ?? 'Login'; ?>
            </button>
        </form>

        <p class="mt-6 text-center text-sm" style="color: var(--color-text-card);">
            <a href="index.php" class="hover:underline" style="color: var(--color-primary);">
                <?php echo $lang['go_to_home'] ?? 'Go to Home'; ?>
            </a>
        </p>
    </div>
</div>

<!-- ✅ Toast JS (external or inline) -->
<script src="assets/js/toast.js"></script> <!-- ✅ If using external -->
<!-- If not using external, you can paste showToast() function inline here -->

<?php if (!empty($error_message)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        showToast('<?php echo addslashes($error_message); ?>', '<?php echo $toast_type; ?>');
    });
</script>
<?php endif; ?>

<!-- ✅ SweetAlert2 (success popup after redirect) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if (isset($_SESSION['login_success'])): ?>
    Swal.fire({
        icon: 'success',
        title: '<?php echo addslashes($lang['login_success_message'] ?? "Login Successful!"); ?>',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
    <?php unset($_SESSION['login_success']); ?>
<?php endif; ?>
</script>

<?php include 'templates/footer.php'; ?>
<?php ob_end_flush(); ?>
