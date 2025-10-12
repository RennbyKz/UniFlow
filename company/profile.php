<?php
// company/profile.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('company_profile', 'company'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$company_data = [];
$errors = [];

try {
    // Company Data ကို ရယူခြင်း (Form မှာ ပြသရန်)
    $stmt = $pdo->prepare("
        SELECT c.*, u.email, u.username
        FROM companies c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $company_data = $stmt->fetch();
    
    if (!$company_data) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: ../logout.php"); ob_end_flush(); exit();
    }
    // Website URL ကို Check လုပ်ပြီး empty ဖြစ်နေရင်လည်း POST မှာ Error မပြအောင် Default ထား
    $company_data['website_url'] = $company_data['website_url'] ?? ''; 

} catch (PDOException $e) {
    $errors[] = $lang['error_general_try_again'] . " (Data Fetch Error)";
}

// ------------------------------------------
// B. FORM SUBMISSION (Data Update & Logo Upload)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input Data များ
    $company_name = sanitize_input($_POST['name'] ?? '');
    $industry = sanitize_input($_POST['industry'] ?? '');
    $website = sanitize_input($_POST['website_url'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $hr_contact_email = sanitize_input($_POST['hr_contact_email'] ?? '');

    $logo_path = $company_data['logo_path']; // လက်ရှိ Path ကို Default ယူ
    $upload_success = true;

    // 1. Logo Upload Logic (File Upload ကို ကိုင်တွယ်)
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        $max_size = 1 * 1024 * 1024; // 1 MB

        if (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowed_ext) || $file['size'] > $max_size) {
            $errors[] = $lang['error_invalid_image'] ?? 'Invalid logo type or size. Max 1MB, JPG/PNG.';
            $upload_success = false;
        } else {
            // File Rename and Save
            $new_file_name = 'logo_' . $company_data['company_id'] . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $upload_dir = '../uploads/logos/'; 
            
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
                $logo_path = 'uploads/logos/' . $new_file_name; 
            } else { $errors[] = $lang['error_file_upload_failed']; }
        }
    }

    // 2. Database Update
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE companies SET 
                name = :name, industry = :industry, website_url = :website, 
                address = :address, description = :description, hr_contact_email = :hr_email, 
                logo_path = :logo_path 
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':name' => $company_name, ':industry' => $industry, ':website' => $website, 
                ':address' => $address, ':description' => $description, ':hr_email' => $hr_contact_email,
                ':logo_path' => $logo_path,
                ':user_id' => $user_id
            ]);

            set_session_message('profile_update_success', 'success');
            header("Location: profile.php");
            ob_end_flush();
            exit();

        } catch (PDOException $e) { $errors[] = $lang['error_general_try_again'] . " (DB Update Error)"; echo $e->getMessage(); }

    }
    
    // Error Handling & Redirect
    if (!empty($errors)) { foreach ($errors as $err) { set_session_message($err, 'error'); } 
        header("Location: profile.php"); ob_end_flush(); exit();
    }
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
        <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__); // profile.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', $currentPageFile); // Active
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-main); border-color: var(--color-border);">
                <?php echo $lang['company_profile'] ?? 'Company Profile Management'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <a href="view_profile.php" class="px-4 py-2 rounded-lg text-white font-medium button-primary" style="background-color: var(--color-accent);">
                                <i class="fa fa-eye mr-2"></i> <?php echo $lang['button_view_profile'] ?? 'View Company Profile'; ?>
                            </a>
                <form method="POST" action="profile.php" enctype="multipart/form-data" class="space-y-6">
                    
                    <div class="border p-4 rounded-lg flex items-center justify-between" style="border-color: var(--color-border);">
                        <div>
                            <h3 class="text-xl font-semibold mb-2"><?php echo $lang['label_company_logo'] ?? 'Update Company Logo (JPG/PNG)'; ?>:</h3>
                            <input type="file" name="logo" id="logo" accept="image/jpeg, image/png" class="w-full text-sm">
                            
                            <p class="text-xs opacity-70 mt-1">Max 1MB. For best results, use a square logo.</p>
                        </div>
                        
                        <?php if (!empty($company_data['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($company_data['logo_path']); ?>" alt="Company Logo" class="w-20 h-20 object-contain rounded-md border">
                        <?php else: ?>
                            <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-md flex items-center justify-center text-gray-500">
                                <i class="fa fa-building text-3xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block font-medium mb-1"><?php echo $lang['label_company_name'] ?? 'Company Name'; ?>:</label>
                            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($company_data['name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="industry" class="block font-medium mb-1"><?php echo $lang['label_industry'] ?? 'Industry Type'; ?>:</label>
                            <input type="text" id="industry" name="industry" required value="<?php echo htmlspecialchars($company_data['industry'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="website_url" class="block font-medium mb-1"><?php echo $lang['label_website_url'] ?? 'Website URL'; ?>:</label>
                            <input type="url" id="website_url" name="website_url" value="<?php echo htmlspecialchars($company_data['website_url'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="address" class="block font-medium mb-1"><?php echo $lang['label_address'] ?? 'Head Office Address'; ?>:</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($company_data['address'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="hr_contact_email" class="block font-medium mb-1"><?php echo $lang['label_hr_email'] ?? 'Primary HR Contact Email'; ?>:</label>
                            <input type="email" id="hr_contact_email" name="hr_contact_email" required value="<?php echo htmlspecialchars($company_data['hr_contact_email'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="description" class="block font-medium mb-1"><?php echo $lang['label_description'] ?? 'Company Description/About Us (Max 500 chars)'; ?>:</label>
                            <textarea id="description" name="description" rows="3" maxlength="500" required class="w-full px-4 py-2 border rounded-lg"><?php echo htmlspecialchars($company_data['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" 
                            class="button-primary px-6 py-3 rounded-lg font-semibold w-full"
                            style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-save mr-2"></i> <?php echo $lang['button_save_profile']; ?>
                    </button>
                </form>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>