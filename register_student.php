<?php
// register_student.php
ob_start(); 
require_once 'core/functions.php'; 
init_system('register_student_title'); 

// Login ဝင်ထားပြီးသားဆိုရင် Redirect လုပ်ပါ
if (isset($_SESSION['user_id'])) {
    header("Location: redirect.php");
    ob_end_flush();
    exit();
}

$errors = [];
$form_data = []; // Form Value များကို ပြန်ဖြည့်ရန်

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Input Data များကို ရယူပြီး Sanitize လုပ်ခြင်း
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $major = sanitize_input($_POST['major'] ?? '');
    $gpa = sanitize_input($_POST['gpa'] ?? '');

    // Form data များကို ပြန်ဖြည့်ရန် သိမ်းဆည်း
    $form_data = compact('full_name', 'email', 'username', 'major', 'gpa');

    // 2. Validation Logic
    if (empty($full_name) || empty($email) || empty($username) || empty($password) || empty($major) || empty($gpa)) {
        $errors[] = $lang['error_fill_all'] ?? 'Please fill in all required fields.';
    }
    if ($password !== $confirm_password) {
        $errors[] = $lang['error_password_match'] ?? 'Passwords do not match.';
    }
    if (strlen($password) < 6) {
        $errors[] = $lang['error_password_length'] ?? 'Password must be at least 6 characters long.';
    }
    
    // 3. Email / Username Duplication Check
    if (empty($errors)) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = $lang['error_user_exists'] ?? 'Email or Username is already registered.';
        }
    }

    // 4. Data Insertion (PDO Transaction)
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $pdo->beginTransaction();

            // A. users Table ထဲသို့ ထည့်သွင်းခြင်း (role='student')
            $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role) VALUES (?, ?, ?, 'student')");
            $stmt->execute([$email, $username, $hashed_password]);
            
            $user_id = $pdo->lastInsertId();

            // B. students Table ထဲသို့ ထည့်သွင်းခြင်း
            $stmt = $pdo->prepare("INSERT INTO students (user_id, full_name, major, gpa) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $full_name, $major, $gpa]);

            $pdo->commit();

            // စာရင်းသွင်းပြီးပါက Login Page သို့ Toast Message ဖြင့် ပို့ဆောင်ခြင်း
            set_session_message('register_success', 'success');
            header("Location: student_login.php");
            ob_end_flush();
            exit();

        } catch (\Exception $e) {
            $pdo->rollBack();
            // Database Error ကို Toast ဖြင့် ပြသရန်
            set_session_message('error_general_try_again', 'error'); 
            header("Location: register_student.php");
            ob_end_flush();
            exit();
        }
    } else {
        // Validation Error များအားလုံးကို Toast ဖြင့် ပြသရန် Session ထဲ ထည့်သွင်း
        foreach ($errors as $error) {
            set_session_message($error, 'error');
        }
        header("Location: register_student.php"); // Page ကို Refresh လုပ်ပါ
        ob_end_flush();
        exit();
    }
}
?>

<?php include 'templates/header.php'; ?> 
<!-- ✅ Toast container -->
<div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>  
<div class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)]">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl p-8 login-card">
        <div class="text-center mb-6">
            <img src="assets/images/UNIFLOW.png" alt="UNIFLOW Logo" style="height: 180px; width: auto; margin: 0 auto;">
            <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                <?php echo $lang['register_student_title'] ?? 'Student Registration'; ?>
            </h1><br/>
            <p class="mt-1 text-sm" style="color: var(--color-text-card); opacity: 0.7;">
                <?php echo $lang['label_fill_details'] ?? 'Please fill in your details to create an account.'; ?>
            </p>
        </div>

        <form id="registerForm" method="POST" action="register_student.php" class="space-y-4">
            
            <div>
                <label for="full_name" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_full_name'] ?? 'Full Name'; ?>:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" required
                        class="w-full px-4 py-2 border rounded-lg">
            </div>

            <div>
                <label for="username" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_username'] ?? 'Username'; ?>:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required
                        class="w-full px-4 py-2 border rounded-lg" oninput="checkUsername()">
                <small id="usernameError" style="color: red; display: none;">Username is already taken.</small>
            </div>
            
            <div>
                <label for="email" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['email_label'] ?? 'Email Address'; ?>:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required
                        class="w-full px-4 py-2 border rounded-lg" oninput="checkEmail()">
                <small id="emailError" style="color: red; display: none;">Email is already registered.</small>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="major" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_major'] ?? 'Major'; ?>:</label>
                    <input type="text" id="major" name="major" value="<?php echo htmlspecialchars($form_data['major'] ?? ''); ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label for="gpa" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_gpa'] ?? 'GPA'; ?> (0.00-4.00):</label>
                    <input type="number" step="0.01" min="0" max="4.0" id="gpa" name="gpa" value="<?php echo htmlspecialchars($form_data['gpa'] ?? ''); ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            
            <div>
                <label for="password" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['password_label'] ?? 'Password'; ?> (Min 6 chars):</label>
                <input type="password" id="password" name="password" required
                        class="w-full px-4 py-2 border rounded-lg" oninput="validatePasswords()">
            </div>
            
            <div>
                <label for="confirm_password" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_confirm_password'] ?? 'Confirm Password'; ?>:</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                        class="w-full px-4 py-2 border rounded-lg" oninput="validatePasswords()">
                <small id="passwordError" style="color: red; display: none;">Passwords do not match.</small>
            </div>
            
            <button type="submit"
                    class="w-full font-semibold py-2 rounded-lg transition duration-300 hover:brightness-110 shadow-lg mt-6 button-primary"
                    style="background-color: var(--color-primary); color: var(--color-bg-card);">
                <?php echo $lang['register_button'] ?? 'Register'; ?>
            </button>
        </form>
        
        <p class="mt-6 text-center text-sm" style="color: var(--color-text-card);">
            <?php echo $lang['already_registered'] ?? 'Already registered?'; ?> 
            <a href="student_login.php" class="hover:underline" style="color: var(--color-primary);">
                <?php echo $lang['student_login'] ?? 'Student Login'; ?>
            </a>
        </p>
    </div>
</div>

<?php include 'templates/footer.php'; ?>

<script>
// Check Username Availability
function checkUsername() {
    const username = document.getElementById('username').value;
    if (username.length > 2) {
        fetch(`core/check_username.php?username=${username}`)
            .then(response => response.json())
            .then(data => {
                if (!data.available) {
                    document.getElementById('usernameError').style.display = 'inline';
                } else {
                    document.getElementById('usernameError').style.display = 'none';
                }
            });
    }
}

// Check Email Availability
function checkEmail() {
    const email = document.getElementById('email').value;
    fetch(`core/check_email.php?email=${email}`)
        .then(response => response.json())
        .then(data => {
            if (!data.available) {
                document.getElementById('emailError').style.display = 'inline';
            } else {
                document.getElementById('emailError').style.display = 'none';
            }
        });
}

// Password Validation
function validatePasswords() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    if (password !== confirmPassword) {
        document.getElementById('passwordError').style.display = 'inline';
    } else {
        document.getElementById('passwordError').style.display = 'none';
    }
}
</script>

<?php ob_end_flush(); ?>
