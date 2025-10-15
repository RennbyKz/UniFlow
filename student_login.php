<?php
// student_login.php
ob_start();
require_once 'core/functions.php';
init_system('student_login_title');

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
    // 💡 NOTE: ?? Operator များကို Ternary ဖြင့် ပြင်ထားခြင်း ရှိ/မရှိ စစ်ဆေးပါ။
    // ဤကုဒ်သည် PHP 7.0+ (??) နှင့် 5.6+ (isset()) နှစ်မျိုးလုံးတွင် အလုပ်လုပ်စေရန် ရိုးရှင်းစွာရေးထားသည်။
    $login_input = sanitize_input($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    global $pdo;
    
    // 1. Data Fetch: User ID, Hashed Password, Role, and Approval Status ကို ယူခြင်း
    $stmt = $pdo->prepare("
        SELECT user_id, username, email, password, role, is_approved 
        FROM users 
        WHERE (email = ? OR username = ?) AND role = 'student'
    ");
    $stmt->execute([$login_input, $login_input]);
    $user = $stmt->fetch();
    
    // Default error type
    $toast_type = 'error';

    // 2. Authentication Check & Status Filtering
    if (!$user) {
        // Case 1: User/Password combination not found
        $error_message = $lang['invalid_credentials'] ?? 'Invalid username or password.';

    } elseif (!password_verify($password, $user['password'])) {
        // Case 2: Password Wrong
        $error_message = $lang['invalid_credentials'] ?? 'Invalid username or password.';

    } elseif ($user['is_approved'] === 'No') {
        // 💡 Case 3: Account Disabled by Admin
        $error_message = $lang['account_disabled'] ?? 'Your account has been disabled by the administration.';
        $toast_type = 'error'; // Disabled account သည် Error အဖြစ် ပြသရန်

    } else {
        // ✅ Case 4: SUCCESS - Login ဝင်ခွင့်ပြုပါ
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $login_input; // Header မှာ ပြသရန်
        
        // Student Profile Picture ကို Fetch ပြီး Session တွင် ထည့်သွင်းရန် လိုအပ်ပါက:
        // $stmt_pic = $pdo->prepare("SELECT profile_picture_path FROM students WHERE user_id = ?");
        // $stmt_pic->execute([$user['user_id']]);
        // $_SESSION['photo_path'] = $stmt_pic->fetchColumn(); 

        $login_success = true;
        $success_message = $lang['login_success'] ?? 'Login successful! Redirecting...';
        $pdo->exec("UPDATE users SET last_login = NOW() WHERE user_id = {$user['user_id']}"); 
    }
    
    // 3. Error Toast Handling (Login Failure)
    if (isset($error_message)) {
        // Error ရှိမှသာ Toast ကို ထုတ်လွှတ်ပြီး Page ကို ဆက်သွားစေပါမည်
        set_session_message($error_message, $toast_type); 
    }

    if ($login_success) {
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo $current_lang; ?>">
        <head>
            <title><?php echo page_title('student_login_title', $lang); ?></title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    title: '<?php echo addslashes($lang['welcome_student'] ?? "Welcome!"); ?>',
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
                <?php echo $lang['student_login_title'] ?? 'Student Login'; ?>
            </h1><br>
            <p class="mt-1 text-sm" style="color: var(--color-text-card); opacity: 0.7;">
                <?php echo $lang['enter_student_credentials'] ?? 'Access your internship portal'; ?>
            </p>
        </div>

        <form method="POST" action="student_login.php" class="space-y-5">
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
            <a href="register_student.php" class="hover:underline" style="color: var(--color-primary);">
                <?php echo $lang['register_student'] ?? 'Register Now'; ?>
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
