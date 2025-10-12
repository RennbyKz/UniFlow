<?php
// company/post_job.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('post_new_job', 'company'); 

// ------------------------------------------
// Initialization & Access Control
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];

// Company ID ကို session မှ ရယူပြီး Admin Approval စစ်ဆေးခြင်း
$stmt = $pdo->prepare("
    SELECT c.company_id, u.is_approved 
    FROM companies c 
    JOIN users u ON c.user_id = u.user_id 
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$company_data = $stmt->fetch();

if (!$company_data || $company_data['is_approved'] !== 'Yes') {
    set_session_message('account_pending', 'warning');
    header("Location: dashboard.php"); 
    ob_end_flush();
    exit();
}

$company_id = $company_data['company_id'];
$form_data = $_POST; // Validation မအောင်မြင်ပါက Form Data ကို ပြန်ဖြည့်ရန်

// ------------------------------------------
// 1. ACTION HANDLING (Job Insertion)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input Data များကို ရယူပြီး Sanitize လုပ်ခြင်း
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $required_skills = sanitize_input($_POST['required_skills'] ?? '');
    $type = sanitize_input($_POST['type'] ?? '');
    $location = sanitize_input($_POST['location'] ?? '');
    $deadline = sanitize_input($_POST['deadline'] ?? '');
    
    // 1A. Validation Logic
    if (empty($title) || empty($description) || empty($required_skills) || empty($type) || empty($location) || empty($deadline)) {
        $errors[] = $lang['error_fill_all'] ?? 'Please fill all required fields.';
    }
    
    // 1B. Final Insertion
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO jobs (company_id, title, description, required_skills, type, location, deadline) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $company_id, 
                $title, 
                $description, 
                $required_skills, 
                $type, 
                $location, 
                $deadline
            ]);
            
            set_session_message('job_post_success', 'success');
            header("Location: dashboard.php"); // Dashboard သို့ ပြန်ပို့
            ob_end_flush();
            exit();

        } catch (PDOException $e) {
            $errors[] = $lang['error_general_try_again'] . " (DB Error)";
            echo $e->getMessage();
        }
    }
    
    // Error Handling & Redirect
    if (!empty($errors)) {
        foreach ($errors as $err) { set_session_message($err, 'error'); } 
    }
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-admin);">
   <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__); // post_job.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', $currentPageFile);
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', $currentPageFile); // Active
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', $currentPageFile);
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', $currentPageFile);
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-main); border-color: var(--color-border);">
                <?php echo $lang['post_new_job'] ?? 'Post a New Job/Internship'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <form method="POST" action="post_job.php" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block font-medium mb-1"><?php echo $lang['label_job_title'] ?? 'Job Title'; ?> <span class="text-red-500">*</span>:</label>
                            <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($form_data['title'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="type" class="block font-medium mb-1"><?php echo $lang['label_job_type'] ?? 'Job Type'; ?> <span class="text-red-500">*</span>:</label>
                            <select id="type" name="type" required class="w-full px-4 py-2 border rounded-lg">
                                <option value="">-- Select Type --</option>
                                <option value="Internship" <?php echo (isset($form_data['type']) && $form_data['type'] == 'Internship') ? 'selected' : ''; ?>>Internship</option>
                                <option value="Full-Time" <?php echo (isset($form_data['type']) && $form_data['type'] == 'Full-Time') ? 'selected' : ''; ?>>Full-Time</option>
                                <option value="Part-Time" <?php echo (isset($form_data['type']) && $form_data['type'] == 'Part-Time') ? 'selected' : ''; ?>>Part-Time</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="location" class="block font-medium mb-1"><?php echo $lang['label_location'] ?? 'Location'; ?> <span class="text-red-500">*</span>:</label>
                            <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($form_data['location'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="deadline" class="block font-medium mb-1"><?php echo $lang['label_deadline'] ?? 'Application Deadline'; ?> <span class="text-red-500">*</span>:</label>
                            <input type="date" id="deadline" name="deadline" required value="<?php echo htmlspecialchars($form_data['deadline'] ?? date('Y-m-d')); ?>">
                        </div>
                    </div>
                    
                    <div>
                        <label for="description" class="block font-medium mb-1"><?php echo $lang['label_job_description'] ?? 'Job Description'; ?> <span class="text-red-500">*</span>:</label>
                        <textarea id="description" name="description" rows="6" required class="w-full px-4 py-2 border rounded-lg"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label for="required_skills" class="block font-medium mb-1"><?php echo $lang['label_required_skills'] ?? 'Required Skills (Comma Separated)'; ?> <span class="text-red-500">*</span>:</label>
                        <input type="text" id="required_skills" name="required_skills" required placeholder="e.g., PHP, SQL, Laravel, Teamwork" value="<?php echo htmlspecialchars($form_data['required_skills'] ?? ''); ?>">
                    </div>

                    <button type="submit" name="post_job" 
                            class="button-primary px-6 py-3 rounded-lg font-semibold w-full"
                            style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-paper-plane mr-2"></i> <?php echo $lang['button_post_job'] ?? 'Post Job'; ?>
                    </button>
                </form>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>