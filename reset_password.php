<?php
// reset_password.php
ob_start();
require_once 'core/functions.php';
init_system('reset_password_title');

$errors = [];
$email = sanitize_input($_GET['email'] ?? $_POST['email'] ?? '');
$token = sanitize_input($_GET['token'] ?? $_POST['token'] ?? ''); // OTP
$user_id_to_reset = null;

// ------------------------------------------
// 1. Token Validation (Initial Check)
// ------------------------------------------
if (!empty($email) && !empty($token)) {
    try {
        global $pdo;
        // Check for Token, Email, and Expiry Date (must be later than NOW())
        $stmt = $pdo->prepare("
            SELECT pr.user_id 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.user_id
            WHERE pr.token = ? AND u.email = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token, $email]);
        $reset_user = $stmt->fetch();

        if ($reset_user) {
            $user_id_to_reset = $reset_user['user_id']; // Valid token
        } else {
            // FIX: Invalid/Expired Token Error ကို Session ထဲထည့်ပြီး Current Page မှာပဲ ပြန်ပြပါ
            set_session_message($lang['error_invalid_or_expired_token'] ?? 'Invalid or expired reset code.', 'error');
            // $user_id_to_reset သည် NULL အတိုင်း ဆက်ရှိနေမည်။
        }
    } catch (PDOException $e) {
        // 💡 ERROR LOGGING: Database Query Failure
        error_log("Password Reset Validation DB Error: " . $e->getMessage()); 
        error_log("Token: " . $token . " Email: " . $email . " Expires At: " . $expires_at);
        $errors[] = $e->getMessage();   
        set_session_message($lang['error_general_try_again'], 'error');
    }
}

// ------------------------------------------
// 2. PASSWORD RESET HANDLING (POST)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_submit']) && isset($_POST['user_id_reset'])) {
    
    $user_id_to_reset = sanitize_input($_POST['user_id_reset']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Check 1: Client-Side Validation Re-check
    if ($new_password !== $confirm_password || strlen($new_password) < 6) {
        $errors[] = $lang['error_password_match'] ?? 'Passwords do not match or are too short.';
    } else {
        try {
            global $pdo;
            $pdo->beginTransaction(); // 💡 Transaction စတင်ခြင်း (Code ထဲမှာ မပါသေးရင် ထည့်ပါ)
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // 1. Password ကို Update လုပ်ခြင်း
            $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt_update->execute([$hashed_password, $user_id_to_reset]);
            
            // 2. Token ကို Database မှ ဖျက်ပစ်ခြင်း (Security)
            $pdo->exec("DELETE FROM password_resets WHERE user_id = {$user_id_to_reset}");
            
            // 💡 FIX: Database Transaction ကို အတည်ပြုခြင်း (Commit)
            $pdo->commit(); 

            
            // -----------------------------------------------------
            // 🔴 ဤနေရာတွင် User Role Fetch & Redirect Logic ထည့်သွင်းရပါမည်
            // -----------------------------------------------------
            
            // 💡 FIX 1: User ID မှ Role ကို ပြန်လည် ဆွဲယူခြင်း
            $stmt_role = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
            $stmt_role->execute([$user_id_to_reset]);
            $user_role = $stmt_role->fetchColumn();


            set_session_message($lang['password_reset_success'] ?? 'Password reset successful. Please log in.', 'success');

            // 💡 FIX 2: Role ပေါ်မူတည်၍ သက်ဆိုင်ရာ Login Page သို့ Redirect လုပ်ခြင်း
            $redirect_page = 'index.php'; // Default Fallback

            if ($user_role === 'student') {
                $redirect_page = 'student_login.php';
            } elseif ($user_role === 'company') {
                $redirect_page = 'company_login.php';
            } elseif ($user_role === 'admin') {
                $redirect_page = 'admin_login.php';
            }
            
            header("Location: " . $redirect_page);
            ob_end_flush(); 
            exit();

        } catch (PDOException $e) { 
            // ... (Error Handling) ...
            $pdo->rollBack();
        }
    }
}

// Error Handling (Display logic only)
if (!empty($errors)) {
    foreach ($errors as $err) { set_session_message($err, 'error'); }
}

include 'templates/header.php';
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    <?php include_utility_bar(); ?> 
       <!-- ✅ Toast container -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <div class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)]">
        <div class="w-full max-w-md rounded-2xl shadow-2xl p-8 login-card">
            <img src="assets/images/UNIFLOW.png" alt="UNIFLOW Logo" style="height: 180px; width: auto; margin: 0 auto;">
            <h1 class="text-3xl font-extrabold mb-4" style="color: var(--color-primary);">
                <?php echo $lang['reset_password_title'] ?? 'Reset Password'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <?php if ($user_id_to_reset): ?>
                <p class="text-lg mb-4 text-green-600">Code Verified! Set your new password.</p>
                <form method="POST" action="reset_password.php" class="space-y-4">
                    <input type="hidden" name="user_id_reset" value="<?php echo $user_id_to_reset; ?>">
                    <input type="hidden" name="reset_submit" value="1">
                    
                    <div>
                        <label for="new_password" class="block font-medium mb-1"><?php echo $lang['label_newpass6'] ?? 'New Password (Min 6 chars):'; ?></label>
                        <input type="password" id="new_password" name="new_password" required minlength="6" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="confirm_password" class="block font-medium mb-1"><?php echo $lang['label_newpass'] ?? 'New Confirm Password:'; ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-2 border rounded-lg">
                    </div>

                    <button type="submit" class="w-full button-primary py-2 rounded-lg font-semibold" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                       <?php echo $lang['reset_password_title'] ?? 'Reset Password'; ?>
                    </button>
                </form>

            <?php else: ?>
                <p class="text-sm opacity-80 mb-4">Please enter the 6-digit code sent to <?php echo htmlspecialchars($email); ?>.</p>
                <form method="GET" action="reset_password.php" class="space-y-4">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>"> 
                    
                    <div>
                        <label for="token_input" class="block font-medium mb-1">6-Digit Code (OTP):</label>
                        <input type="text" id="token_input" name="token" required maxlength="6" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <button type="submit" class="w-full button-primary py-2 rounded-lg font-semibold" style="background-color: var(--color-accent); color: var(--color-bg-card);">
                      <?php echo $lang['verify_code'] ?? 'Verify Code'; ?>
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>
    
<?php include 'templates/footer.php'; ?>
<?php ob_end_flush(); ?>