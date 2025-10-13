<?php
// student/cv_upload.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('upload_resume', 'student'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];

// Student ID, Resume Path နှင့် CV Path နှစ်ခုလုံးကို ရယူခြင်း
$stmt = $pdo->prepare("SELECT student_id, resume_path, cv_path FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_data = $stmt->fetch();

if (!$student_data) { 
    set_session_message('profile_data_not_found', 'error');
    header("Location: ../logout.php"); 
    ob_end_flush(); 
    exit(); 
}

$student_id = $student_data['student_id'];
$current_resume_path = $student_data['resume_path'];
$current_cv_path = $student_data['cv_path']; // NEW: CV Path

// ------------------------------------------
// 1. ACTION HANDLING (File Upload Logic)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_action'])) {
    
    $upload_action = sanitize_input($_POST['upload_action']);
    
    // 💡 Target Column Name ကို သတ်မှတ်ခြင်း
    $target_column = ($upload_action === 'upload_resume') ? 'resume_path' : 'cv_path';
    $file_input_name = 'official_file'; // HTML Form မှာ သတ်မှတ်ထားသော name

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$file_input_name];
        
        // Security: Whitelist ဖြင့် စစ်ဆေးခြင်း
        $allowed_ext = ['pdf', 'doc', 'docx'];
        $max_size = 5 * 1024 * 1024; // 5 MB
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext)) {
            $errors[] = $lang['error_invalid_file_type'] ?? 'Invalid file type. Only PDF, DOCX are allowed.';
        } elseif ($file['size'] > $max_size) {
            $errors[] = $lang['error_file_too_large'] ?? 'File size must not exceed 5MB.';
        } else {
            // File Rename and Save
            $new_file_name = $target_column . '_' . $student_id . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/official_docs/'; // Path အသစ်
            
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
                $new_file_path = 'uploads/official_docs/' . $new_file_name;
                
                // 💡 Database Update (Target Column ကို Variable ဖြင့် သတ်မှတ်)
                $stmt = $pdo->prepare("UPDATE students SET {$target_column} = ? WHERE student_id = ?");
                $stmt->execute([$new_file_path, $student_id]);
                
                // Optional: File System ကနေ Resume အဟောင်းကို ဖျက်ပါ
                $old_path = ($target_column === 'resume_path') ? $current_resume_path : $current_cv_path;
                if ($old_path && file_exists('../' . $old_path)) {
                    unlink('../' . $old_path); 
                }

                set_session_message($target_column === 'resume_path' ? 'resume_upload_success' : 'cv_upload_success', 'success');
                header("Location: cv_upload.php"); 
                ob_end_flush();
                exit();
                
            } else {
                $errors[] = $lang['error_file_upload_failed'] ?? 'File upload failed due to server error.';
            }
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors[] = $lang['error_no_file_selected'] ?? 'No file selected or invalid request.';
    }
    
    // Error Handling & Redirect
    if (!empty($errors)) {
        foreach ($errors as $err) { set_session_message($err, 'error'); } 
        header("Location: cv_upload.php");
        ob_end_flush();
        exit();
    }
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-4xl p-8 rounded-xl shadow-2xl border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <h1 class="text-3xl font-extrabold mb-6 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                    <?php echo $lang['upload_resume'] ?? 'CV Management Center'; ?>
                </h1>
                
                <?php display_session_messages($lang); ?>

                <div class="mb-8 p-4 rounded-lg border border-dashed" style="border-color: var(--color-accent);">
                    <p class="font-medium text-lg mb-2">Current Status:</p>
                    <p class="text-sm">
                        **Resume:** <?php if ($current_resume_path): ?>
                            <span class="text-green-600 dark:text-green-400">Uploaded.</span> 
                            <a href="../<?php echo htmlspecialchars($current_resume_path); ?>" target="_blank" class="font-semibold" style="color: var(--color-primary);">View File</a>
                        <?php else: ?>
                            <span class="text-red-600 dark:text-red-400">No Resume Uploaded.</span>
                        <?php endif; ?>
                    </p>
                     <p class="text-sm mt-1">
                        **CV:** <?php if ($current_cv_path): ?>
                            <span class="text-green-600 dark:text-green-400">Uploaded.</span> 
                            <a href="../<?php echo htmlspecialchars($current_cv_path); ?>" target="_blank" class="font-semibold" style="color: var(--color-primary);">View File</a>
                        <?php else: ?>
                            <span class="text-red-600 dark:text-red-400">No CV Uploaded.</span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div class="p-6 rounded-xl border shadow-md" style="border-color: var(--color-border);">
                        <h2 class="text-xl font-bold mb-4" style="color: var(--color-text-card);">1. Upload Existing File</h2>
                        <p class="text-sm opacity-80 mb-4">Select and upload your finished PDF/DOCX file to replace the existing documents.</p>

                        <form method="POST" action="cv_upload.php" enctype="multipart/form-data" class="space-y-4">
                            
                            <label for="official_file" class="block font-medium mb-2 text-md">Select Document (PDF/DOCX):</label>
                            <input type="file" name="official_file" id="official_file" required
                                   accept=".pdf,.doc,.docx" 
                                   class="w-full px-4 py-3 border rounded-lg bg-gray-50 dark:bg-gray-700 text-sm">
                            
                            <p class="text-xs mt-1 opacity-70">
                                Allowed: PDF, DOC, DOCX. Max: 5MB.
                            </p>

                            <button type="submit" name="upload_action" value="upload_resume"
                                    class="button-primary px-6 py-3 rounded-lg font-semibold w-full"
                                    style="background-color: var(--color-primary); color: var(--color-bg-card);">
                                <i class="fa fa-file-alt mr-2"></i> Upload as RESUME (Brief)
                            </button>
                            <button type="submit" name="upload_action" value="upload_cv"
                                    class="button-primary px-6 py-3 rounded-lg font-semibold w-full mt-2"
                                    style="background-color: var(--color-accent); color: var(--color-bg-card);">
                                <i class="fa fa-file-pdf mr-2"></i> Upload as CV (Detailed)
                            </button>
                        </form>
                    </div>

                    <div class="p-6 rounded-xl border shadow-md flex flex-col justify-between" style="border-color: var(--color-border);">            
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
                <a href="dashboard.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-tachometer-alt w-5"></i><span><?php echo $lang['student_dashboard']; ?></span>
                </a>
                <a href="view_jobs.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-briefcase w-5"></i><span><?php echo $lang['view_jobs']; ?></span>
                </a>
                <a href="../logout.php" class="flex items-center gap-3 hover:text-red-500" style="color: var(--color-error);">
                    <i class="fa fa-sign-out-alt w-5"></i><span><?php echo $lang['logout']; ?></span>
                </a>
            </nav>
                    </div>
                </div>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>