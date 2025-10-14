<?php
// company_login.php
ob_start();
require_once 'core/functions.php';
init_system('company_login_title');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: redirect.php");
    ob_end_flush();
    exit();
}

$error_message = '';
$toast_type = 'error';
$login_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = sanitize_input($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT user_id, username, email, password, role, is_approved 
        FROM users 
        WHERE (email = ? OR username = ?)
    ");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch();

    if ($user && $user['role'] !== 'company') {
        $error_message = $lang['not_company_account'] ?? 'Only company accounts can login here.';
        $toast_type = 'error';
    } elseif ($user && password_verify($password, $user['password'])) {
        if ($user['is_approved'] === 'No') {
            $error_message = $lang['account_pending'] ?? 'Your account is pending admin approval.';
            $toast_type = 'warning';
        } else {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $login_input;

            $login_success = true;
            $success_message = $lang['login_success'] ?? 'Login successful! Redirecting...';
        }
    } else {
        $error_message = $lang['invalid_credentials'] ?? 'Invalid username or password.';
        $toast_type = 'error';
    }

    if ($login_success) {
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo $current_lang; ?>">
        <head>
            <title><?php echo page_title('company_login_title', $lang); ?></title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    title: '<?php echo addslashes($lang['welcome_company'] ?? "Welcome!"); ?>',
                    text: '<?php echo addslashes($success_message); ?>',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    didClose: () => {
                        window.location.href = 'redirect.php';
                    }
                });
            });
        </script>
        </body>
        </html>
        <?php
        ob_end_flush();
        exit();
    }
}
?>

<?php include 'templates/header.php'; ?>

<!-- ✅ Toast container -->
<div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>

<!-- ✅ Login Form UI -->
<div class="flex flex-col items-center justify-center min-h-[calc(100vh-150px)]">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl p-8 login-card">
        <div class="text-center mb-6">
            <img src="assets/images/UNIFLOW.png" alt="UNIFLOW Logo" style="height: 180px; width: auto; margin: 0 auto;">
            <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                <?php echo $lang['company_login_title'] ?? 'Company Login'; ?>
            </h1><br>
            <p class="mt-1 text-sm" style="color: var(--color-text-card); opacity: 0.7;">
                <?php echo $lang['enter_company_credentials'] ?? 'Enter your credentials to manage listings'; ?>
            </p>
        </div>

        <form method="POST" action="company_login.php" class="space-y-5">
            <div>
                <label for="login_input" class="block font-medium mb-1" style="color: var(--color-text-card);">
                    <?php echo $lang['label_user_login'] ?? 'Username or Email'; ?>
                </label>
                <input type="text" id="login_input" name="login_input" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label for="password" class="block font-medium mb-1" style="color: var(--color-text-card);">
                    <?php echo $lang['password_label'] ?? 'Password'; ?>
                </label>
                <input type="password" id="password" name="password" required
                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <button type="submit"
                    class="w-full font-semibold py-2 rounded-lg transition duration-300 hover:brightness-110 shadow-lg button-primary"
                    style="background-color: var(--color-primary); color: var(--color-bg-card);">
                <?php echo $lang['login_button'] ?? 'Login'; ?>
            </button>
        </form>

        <p class="mt-6 text-center text-sm" style="color: var(--color-text-card);">
            <?php echo $lang['not_registered'] ?? 'Not registered?'; ?>
            <a href="register_company.php" class="hover:underline" style="color: var(--color-primary);">
                <?php echo $lang['register_company'] ?? 'Register Company'; ?>
            </a>
        </p>
        <p class="mt-6 text-center text-sm" style="color: var(--color-text-card);">
            <?php echo $lang['forgot_password_title'] ?? 'Forgot Password?'; ?>
            <a href="forgot_password.php" class="hover:underline" style="color: var(--color-primary);">
                <?php echo $lang['reset_password_title'] ?? 'Reset Password'; ?>
            </a>
        </p>
    </div>
</div>

<!-- ✅ Toast Script -->
<script src="assets/js/toast.js"></script>

<?php if (!empty($error_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showToast('<?php echo addslashes($error_message); ?>', '<?php echo $toast_type ?? 'error'; ?>');
        });
    </script>
<?php endif; ?>

<?php include 'templates/footer.php'; ?>
<?php ob_end_flush(); ?>
