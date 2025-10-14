<?php
// forgot_password.php
ob_start();
require_once 'core/functions.php';
init_system('forgot_password_title');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $lang['error_invalid_email'] ?? 'Please enter a valid email address.';
    } else {
        try {
            global $pdo;
            $pdo->beginTransaction();
            
            // 1. User Existence Check
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Security Measure: Always display success message even if email is not found
                // to prevent enumerating valid emails.
                set_session_message($lang['password_reset_sent'] ?? 'If an account exists, a reset link has been sent.', 'success');
                $pdo->rollBack();
                header("Location: reset_password.php?email=" . urlencode($email)); 
                ob_end_flush(); exit();
            }

            $user_id = $user['user_id'];
            $username = $user['username'];
            
            // 2. Token (6-digit OTP) and Expiry Date (15 minutes) Generate
            $otp_code = strval(random_int(100000, 999999)); 
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // 3. Database: Delete old tokens and insert new OTP
            $pdo->exec("DELETE FROM password_resets WHERE user_id = {$user_id}");
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $otp_code, $expires_at]);
            
            // 4. Email Sending Logic (Requires PHPMailer setup)
            $reset_link = get_base_url() . "/reset_password.php?token={$otp_code}&email=" . urlencode($email);
            $email_subject = APP_NAME . " Password Reset Code";
            $email_body = "Dear {$username},<br><br>
                Your password reset code is: <strong>{$otp_code}</strong>. 
                This code will expire in 15 minutes.
                <br>Please use the following link to reset your password: <a href='{$reset_link}'>Reset Password</a>";
            
            // 💡 Assuming send_external_email is working and includes PHPMailer classes
            send_external_email($email, $email_subject, $email_body); 
            
            $pdo->commit();
            
            // Redirect to the Reset Page (prompting for OTP)
            set_session_message($lang['password_reset_sent'] ?? 'Reset code sent to your email.', 'success');
            header("Location: reset_password.php?email=" . urlencode($email));
            ob_end_flush();
            exit();

        } catch (\Exception $e) {
            $pdo->rollBack();
            $errors[] = $lang['error_general_try_again'] . ": " . $e->getMessage();
        }
    }
    
    // Error Handling
    if (!empty($errors)) {
        foreach ($errors as $err) { set_session_message($err, 'error'); }
    }
    header("Location: forgot_password.php"); ob_end_flush(); exit();
}

include 'templates/header.php';
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    <?php include 'templates/header.php'; // Includes Utility Bar Logic ?>
       <!-- ✅ Toast container -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <div class="flex flex-col items-center justify-center min-h-[calc(100vh-70px)]">
        <div class="w-full max-w-md rounded-2xl shadow-2xl p-8 login-card">
             <img src="assets/images/UNIFLOW.png" alt="UNIFLOW Logo" style="height: 250px; width: auto; margin: 0 auto;">
            <h1 class="text-4xl font-extrabold mb-5" style="color: var(--color-primary);">
                <?php echo $lang['forgot_password_title'] ?? 'Forgot Password?'; ?>
            </h1>
            <p class="text-sm opacity-80 mb-6">
               <?php echo $lang['noti_restpass'] ?? 'Enter your email to receive a password reset code.'; ?>
            </p>
            
            <?php display_session_messages($lang); ?>

            <form method="POST" action="forgot_password.php" class="space-y-5">
                <div>
                    <label for="email" class="block font-medium mb-1"><?php echo $lang['email_label']; ?></label>
                    <input type="email" id="email" name="email" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <button type="submit" class="w-full button-primary py-2 rounded-lg font-semibold" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                    <?php echo $lang['send_code'] ?? 'Send Reset Code'; ?>
                </button>
            </form>
            
            <p class="mt-4 text-center text-sm">
                <a href="index.php" style="color: var(--color-accent);"><?php echo $lang['back_login'] ?? 'Back to Login'; ?></a>
            </p>
        </div>
    </div>
    
<?php include 'templates/footer.php'; ?>
<?php ob_end_flush(); ?>