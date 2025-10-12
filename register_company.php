<?php
// register_company.php
ob_start(); 
require_once 'core/functions.php'; 
init_system('register_company_title'); 

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
    $company_name = sanitize_input($_POST['company_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $industry = sanitize_input($_POST['industry'] ?? '');
    $hr_contact_email = sanitize_input($_POST['hr_contact_email'] ?? '');

    // Form data များကို ပြန်ဖြည့်ရန် သိမ်းဆည်း
    $form_data = compact('company_name', 'email', 'username', 'industry', 'hr_contact_email');

    // 2. Validation Logic
    if (empty($company_name) || empty($email) || empty($username) || empty($password) || empty($industry) || empty($hr_contact_email)) {
        $errors[] = $lang['error_fill_all'] ?? 'Please fill in all required fields.';
    }
    if ($password !== $confirm_password) {
        $errors[] = $lang['error_password_match'] ?? 'Passwords do not match.';
    }
    // (အခြား validation များ ထပ်ထည့်နိုင်သည်)
    
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

            // A. users Table ထဲသို့ ထည့်သွင်းခြင်း (role='company')
            $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role) VALUES (?, ?, ?, 'company')");
            $stmt->execute([$email, $username, $hashed_password]);
            
            $user_id = $pdo->lastInsertId();

            // B. companies Table ထဲသို့ ထည့်သွင်းခြင်း (is_approved='No' ကို Default ထားပါ)
            $stmt = $pdo->prepare("INSERT INTO companies (user_id, name, industry, hr_contact_email, is_approved) VALUES (?, ?, ?, ?, 'No')");
            $stmt->execute([$user_id, $company_name, $industry, $hr_contact_email]);

            $pdo->commit();

            // စာရင်းသွင်းပြီးပါက Login Page သို့ Toast Message ဖြင့် ပို့ဆောင်ခြင်း
            set_session_message('company_register_pending', 'warning');
            header("Location: company_login.php");
            ob_end_flush();
            exit();

        } catch (\Exception $e) {
            $pdo->rollBack();
            set_session_message('error_general_try_again', 'error'); 
            header("Location: register_company.php");
            ob_end_flush();
            exit();
        }
    } else {
        foreach ($errors as $error) {
            set_session_message($error, 'error');
        }
        header("Location: register_company.php");
        ob_end_flush();
        exit();
    }
}
// ------------------------------------------
// HTML Output
// ------------------------------------------
?>

<?php include 'templates/header.php'; ?> 
    
    <div class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)]">
        
        <div class="w-full max-w-lg rounded-2xl shadow-2xl p-8 login-card">
            
            <div class="text-center mb-6">
                <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                    <?php echo $lang['register_company_title'] ?? 'Company Registration'; ?>
                </h1><br/>
                <p class="mt-1 text-sm" style="color: var(--color-text-card); opacity: 0.7;">
                    <?php echo $lang['label_fill_company_details'] ?? 'Please fill in your company details for approval.'; ?>
                </p>
            </div>

            <form method="POST" action="register_company.php" class="space-y-4">
                
                <div>
                    <label for="company_name" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_company_name'] ?? 'Company Name'; ?>:</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($form_data['company_name'] ?? ''); ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>

                <div>
                    <label for="industry" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_industry'] ?? 'Industry Type'; ?>:</label>
                    <input type="text" id="industry" name="industry" value="<?php echo htmlspecialchars($form_data['industry'] ?? ''); ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div>
                    <label for="hr_contact_email" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_hr_email'] ?? 'HR Contact Email'; ?>:</label>
                    <input type="email" id="hr_contact_email" name="hr_contact_email" value="<?php echo htmlspecialchars($form_data['hr_contact_email'] ?? ''); ?>" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_username'] ?? 'Username'; ?>:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required
                                class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label for="email" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['email_label'] ?? 'Login Email'; ?>:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required
                                class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div>
                    <label for="password" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['password_label'] ?? 'Password'; ?> (Min 6 chars):</label>
                    <input type="password" id="password" name="password" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <div>
                    <label for="confirm_password" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_confirm_password'] ?? 'Confirm Password'; ?>:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                            class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <button type="submit"
                        class="w-full font-semibold py-2 rounded-lg transition duration-300 hover:brightness-110 shadow-lg mt-6 button-primary"
                        style="background-color: var(--color-primary); color: var(--color-bg-card);">
                    <?php echo $lang['register_button'] ?? 'Register Company'; ?>
                </button>
            </form>
            
            <p class="mt-6 text-center text-sm" style="color: var(--color-text-card);">
                <?php echo $lang['already_registered'] ?? 'Already registered?'; ?> 
                <a href="company_login.php" class="hover:underline" style="color: var(--color-primary);">
                    <?php echo $lang['company_login'] ?? 'Company Login'; ?>
                </a>
            </p>
        </div>

    </div>

<?php include 'templates/footer.php'; ?>
<?php ob_end_flush(); ?>