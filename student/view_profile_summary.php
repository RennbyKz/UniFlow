<?php
// student/view_profile_summary.php
ob_start();
// Include SweetAlert2 CDN here for better UX
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once '../core/functions.php'; 
// Student Role Check
init_system('student_profile_summary', 'student'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];
$profile = [];
$skills = [];
$experiences = [];
$projects = [];
$certificates = [];

try{
    // 1. Profile Core Data
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    $student_id = $profile['student_id'] ?? 0;
    if (!$profile) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: ../logout.php");
        ob_end_flush();
        exit();
    }
    
    // 2. Skills, Experiences, Projects (Dynamic Data)
    $stmt = $pdo->prepare("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $skills = $stmt->fetchAll();
    
    // FIX: Using 'exp_id' as confirmed by user
    $stmt = $pdo->prepare("SELECT exp_id, title, company, start_date, end_date FROM student_experiences WHERE student_id = ? ORDER BY start_date DESC");
    $stmt->execute([$student_id]);
    $experiences = $stmt->fetchAll();
    
    // Using 'project_id'
    $stmt = $pdo->prepare("SELECT project_id, title, project_url, project_image_path, technologies, description FROM student_projects WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $projects = $stmt->fetchAll();

    // 3. Certificates Data
    $stmt = $pdo->prepare("SELECT cert_id, cert_name, issuer, issue_date, cert_path FROM student_certificates WHERE student_id = ? ORDER BY issue_date DESC");
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    set_session_message("An error occurred: " . $e->getMessage(), 'error');
}

// ------------------------------------------
// 3. ACTION HANDLING (Certificate Add/Edit/Delete)
// ------------------------------------------
$upload_dir = '../uploads/certificates/';
$base_upload_path = 'uploads/certificates/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize_input($_POST['action']);
    $redirect = 'view_profile_summary.php';
    
    // Inputs for Add/Update
    $cert_id = sanitize_input($_POST['cert_id_edit'] ?? null); 
    $cert_name = sanitize_input($_POST['cert_name'] ?? ''); 
    $issuer = sanitize_input($_POST['issuer'] ?? '');
    $issue_date = sanitize_input($_POST['issue_date'] ?? '');
    
    $cert_path = null;
    $upload_success = true;
    
    // Existing Path
    if ($cert_id) {
        $stmt = $pdo->prepare("SELECT cert_path FROM student_certificates WHERE cert_id = ?");
        $stmt->execute([$cert_id]);
        $cert_path = $stmt->fetchColumn(); 
    }

    // A. ADD/UPDATE CERTIFICATE LOGIC
    if ($action === 'add_cert' || $action === 'update_cert') {
        
        // 1. Validation
        if (empty($cert_name) || empty($issuer) || empty($issue_date)) {
            $errors[] = $lang['error_fill_all'] ?? 'Please fill in all required fields (Name, Issuer, Date).';
        }

        // 2. File Upload Logic (New File Received)
        if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cert_file'];
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
            $max_size = 5 * 1024 * 1024; // 5 MB
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_ext) || $file['size'] > $max_size) {
                $errors[] = $lang['error_invalid_cert_file'] ?? 'Invalid file type or size. Only PDF/Images (Max 5MB) are allowed.';
                $upload_success = false;
            } else {
                $new_file_name = 'cert_' . $student_id . '_' . uniqid() . '.' . $ext;
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
                    // Old File Deletion (if updating an old file)
                    if ($cert_path && $action === 'update_cert' && strpos($cert_path, $base_upload_path) === 0 && file_exists('../' . $cert_path)) {
                        unlink('../' . $cert_path); 
                    }
                    $cert_path = $base_upload_path . $new_file_name;
                } else { $errors[] = $lang['error_file_upload_failed'] ?? 'File upload failed.'; $upload_success = false; }
            }
        } 
        // Check file presence for ADD action
        if ($action === 'add_cert' && empty($cert_path) && empty($errors)) {
            $errors[] = 'Certificate file is required for new entries.';
            $upload_success = false;
        }

        // 3. Database Action
        if (empty($errors) && $upload_success) {
            try {
                if ($action === 'add_cert') {
                    $stmt = $pdo->prepare("INSERT INTO student_certificates (student_id, cert_name, issuer, issue_date, cert_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$student_id, $cert_name, $issuer, $issue_date, $cert_path]);
                    $message_key = 'Certificate added successfully.';
                } elseif ($action === 'update_cert') {
                    $stmt = $pdo->prepare("UPDATE student_certificates SET cert_name=?, issuer=?, issue_date=?, cert_path=? WHERE cert_id=? AND student_id=?");
                    $stmt->execute([$cert_name, $issuer, $issue_date, $cert_path, $cert_id, $student_id]);
                    $message_key = 'Certificate updated successfully.';
                }
                set_session_message($message_key, 'success');
            } catch (PDOException $e) {
                $errors[] = ($lang['error_general_try_again'] ?? "An error occurred.") . " (DB Update Error)";
            }
        } 
    } 
    
    // B. DELETE CERTIFICATE LOGIC
    elseif ($action === 'delete_cert' && isset($_POST['cert_id'])) {
        $cert_id = sanitize_input($_POST['cert_id']);
        try {
            // Select file path for deletion
            $stmt = $pdo->prepare("SELECT cert_path FROM student_certificates WHERE cert_id = ? AND student_id = ?");
            $stmt->execute([$cert_id, $student_id]);
            $path_to_delete = $stmt->fetchColumn();

            // Perform DB deletion
            $stmt = $pdo->prepare("DELETE FROM student_certificates WHERE cert_id = ? AND student_id = ?");
            $stmt->execute([$cert_id, $student_id]);
            
            // Delete file from filesystem
            if ($path_to_delete && strpos($path_to_delete, $base_upload_path) === 0 && file_exists('../' . $path_to_delete)) { 
                unlink('../' . $path_to_delete); 
            }

            set_session_message('Certificate deleted successfully.', 'success');
        } catch (PDOException $e) { $errors[] = ($lang['error_general_try_again'] ?? "An error occurred.") . " (Deletion Error)"; }
    }
    
    // Error Handling & Final Redirect
    if (!empty($errors)) { foreach ($errors as $err) { set_session_message($err, 'error'); } }
    header("Location: " . $redirect);
    ob_end_flush();
    exit();
}

// ------------------------------------------
// 4. ACTION HANDLING (Add / Delete / Update Experience)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exp_action'])) {
    $exp_action = sanitize_input($_POST['exp_action']);
    $redirect = 'view_profile_summary.php';
    $exp_errors = [];

    // Common fields
    // FIX: Using 'exp_id' for consistency with DB column
    $exp_id = sanitize_input($_POST['exp_id'] ?? null);
    $title = sanitize_input($_POST['title'] ?? '');
    $company = sanitize_input($_POST['company'] ?? '');
    $start_date = sanitize_input($_POST['start_date'] ?? '');
    $end_date = sanitize_input($_POST['end_date'] ?? null);

    try {
        // Validation check for Add/Update
        if (in_array($exp_action, ['add_experience', 'update_experience']) && (empty($title) || empty($company) || empty($start_date))) {
            $exp_errors[] = 'Job Title, Company, and Start Date are required fields.';
        }
        
        // A. Add Experience
        if ($exp_action === 'add_experience' && empty($exp_errors)) {
            $stmt = $pdo->prepare("INSERT INTO student_experiences (student_id, title, company, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $title, $company, $start_date, $end_date]);
            set_session_message('Experience added successfully.', 'success');
        }

        // B. Update Experience
        // FIX: Using 'exp_id' in the WHERE clause
        elseif ($exp_action === 'update_experience' && $exp_id && empty($exp_errors)) {
            $stmt = $pdo->prepare("UPDATE student_experiences SET title = ?, company = ?, start_date = ?, end_date = ? WHERE exp_id = ? AND student_id = ?");
            $stmt->execute([$title, $company, $start_date, $end_date, $exp_id, $student_id]);
            set_session_message('Experience updated successfully.', 'success');
        }

        // C. Delete Experience
        elseif ($exp_action === 'delete_experience') {
            if ($exp_id) {
                // FIX: Using 'exp_id' in the WHERE clause
                $stmt = $pdo->prepare("DELETE FROM student_experiences WHERE exp_id = ? AND student_id = ?");
                $stmt->execute([$exp_id, $student_id]);
                set_session_message('Experience deleted successfully.', 'success');
            } else {
                $exp_errors[] = 'Invalid experience ID for deletion.';
            }
        }

    } catch (PDOException $e) {
        $exp_errors[] = "Database error: " . $e->getMessage();
    }

    // Handle errors
    if (!empty($exp_errors)) {
        foreach ($exp_errors as $err) {
            set_session_message($err, 'error');
        }
    }

    header("Location: $redirect");
    ob_end_flush();
    exit();
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<style>
    /* Custom styles for better UI */
 .certificate-download-icon {
    /* icon ကို အခြား icon များနှင့် မျဉ်းတတန်းတည်းဖြစ်စေရန် အနည်းငယ် ချိန်ညှိခြင်း */
    padding-top: 16px; /* ဤတန်ဖိုးကို လိုအပ်သလို ပြောင်းလဲနိုင်သည် */
    display: inline-block;
}
.flex.space-x-2 {
    align-items: center; /* parent div တွင် ဤအချက်ပါမပါ စစ်ဆေးပါ */
}
</style>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-4xl p-8 rounded-xl shadow-2xl border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>
                        <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-indigo-600 shadow-md">
                            <?php if (!empty($profile['photo_path']) && file_exists('../' . $profile['photo_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Profile Picture" class="object-cover w-full h-full">
                            <?php else: ?>
                                <img src="../assets/images/default_profile.png" alt="Default Profile Picture" class="object-cover w-full h-full">
                            <?php endif; ?>
                        </div>
                <div class="flex justify-between items-center pb-4 mb-6 border-b" style="border-color: var(--color-primary);">
                    <h1 class="text-4xl font-extrabold" style="color: var(--color-primary);">
                        <?php echo htmlspecialchars($profile['full_name'] ?? 'N/A'); ?>
                    </h1>
                    <a href="profile.php" class="px-4 py-2 rounded-lg text-white font-medium" style="background-color: var(--color-accent);">
                        <i class="fa fa-user-edit mr-2"></i> <?php echo $lang['button_edit_profile'] ?? 'Edit Profile'; ?>
                    </a>
                </div>

                <section class="mb-6">
                    
                    <h2 class="text-xl font-bold mb-2 border-b-2" style="color: var(--color-text-card); border-color: var(--color-accent);">
                        <i class="fa fa-user mr-2" style="color: var(--color-accent);"></i>
                        <?php echo $lang['personal_info'] ?? 'Personal Information'; ?>
                    </h2>
                    <div class="grid grid-cols-2 gap-4 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong><i class="fa fa-phone mr-2"></i><?php echo $lang['label_contact_phone'] ?? 'Phone'; ?>:</strong> <?php echo htmlspecialchars($profile['contact_phone'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-envelope mr-2"></i><?php echo $lang['email_label'] ?? 'Email'; ?>:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-birthday-cake mr-2"></i><?php echo $lang['label_dob'] ?? 'D.O.B'; ?>:</strong> <?php echo htmlspecialchars($profile['dob'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-venus-mars mr-2"></i><?php echo $lang['label_gender'] ?? 'Gender'; ?>:</strong> <?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></p>
                        <p class="col-span-2"><strong><i class="fa fa-map-marker-alt mr-2"></i><?php echo $lang['label_address'] ?? 'Address'; ?>:</strong> <?php echo htmlspecialchars($profile['address'] ?? 'N/A'); ?></p>
                        <p class="col-span-2">
                            <strong><i class="fa fa-linkedin mr-2"></i>LinkedIn:</strong> 
                            <a href="<?php echo htmlspecialchars($profile['linkedin_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);">
                                <?php echo htmlspecialchars($profile['linkedin_url'] ?? 'N/A'); ?>
                            </a>
                        </p>
                    </div>
                </section>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b-2" style="color: var(--color-text-card); border-color: var(--color-accent);">
                        <i class="fa fa-graduation-cap mr-2" style="color: var(--color-accent);"></i>
                        <?php echo $lang['academic_info'] ?? 'Academic Information'; ?>
                    </h2>
                    <div class="space-y-1 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong><?php echo $lang['label_uni_name'] ?? 'University'; ?>:</strong> <?php echo htmlspecialchars($profile['university_name'] ?? 'N/A'); ?></p>
                        <p><strong><?php echo $lang['label_major'] ?? 'Major'; ?>:</strong> <?php echo htmlspecialchars($profile['major'] ?? 'N/A'); ?></p>
                        <p><strong><?php echo $lang['label_year_study'] ?? 'Year of Study'; ?>:</strong> <?php echo htmlspecialchars($profile['year_of_study'] ?? 'N/A'); ?></p>
                        <p><strong><?php echo $lang['label_gpa'] ?? 'GPA'; ?>:</strong> <span class="font-bold" style="color: var(--color-primary);"><?php echo htmlspecialchars($profile['gpa'] ?? 'N/A'); ?></span></p>
                    </div>
                </section>

                <?php if (!empty($skills)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b-2" style="color: var(--color-text-card); border-color: var(--color-accent);">
                        <i class="fa fa-cogs mr-2" style="color: var(--color-accent);"></i>
                        <?php echo $lang['manage_skills'] ?? 'Technical Skills'; ?>
                    </h2>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <?php foreach ($skills as $skill): ?>
                            <span class="px-3 py-1 rounded-full text-white font-medium" style="background-color: var(--color-accent);">
                                <?php echo htmlspecialchars($skill['skill_name']); ?> (<?php echo htmlspecialchars($skill['proficiency_level']); ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <a href="manage_skills.php" class="text-sm mt-2 block" style="color: var(--color-primary);">Manage/Add Skills</a>
                </section>
                <?php endif; ?>

            <section class="mb-6 pt-4 border-t" style="border-color: var(--color-border);">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-xl font-bold border-b-2" style="color: var(--color-text-card); border-color: var(--color-accent);">
                            <i class="fa fa-briefcase mr-2" style="color: var(--color-accent);"></i>
                            <?php echo $lang['work_experiences'] ?? 'Work Experiences'; ?>
                        </h2>
                        <button type="button" onclick="openExpModal('add')" class="px-3 py-1 rounded-md text-white text-sm" style="background-color: var(--color-primary);">
                             <i class="fa fa-plus"></i> Add New
                        </button>
                    </div>
                <?php if (empty($experiences)): ?>
                    <p class="text-sm opacity-70" style="color: var(--color-text-card);">No work experience added yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($experiences as $exp): ?>
                                <div class="p-3 rounded-lg border" style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-grow">
                                            <span class="font-semibold text-base" style="color: var(--color-text-card);"><?php echo htmlspecialchars($exp['title']); ?></span>
                                            <p class="text-sm opacity-90" style="color: var(--color-text-card);"><?php echo htmlspecialchars($exp['company']); ?></p>
                                            <p class="text-xs opacity-70 mt-1">
                                                <?php echo htmlspecialchars($exp['start_date']); ?> - <?php echo htmlspecialchars($exp['end_date'] ?: 'Present'); ?>
                                            </p>
                                        </div>
                                        <div class="flex space-x-2 ml-4">
                                            <button type="button"
                                                onclick='openExpModal(
                                                    "edit",
                                                    <?php echo json_encode($exp["exp_id"]); ?>,
                                                    <?php echo json_encode($exp["title"]); ?>,
                                                    <?php echo json_encode($exp["company"]); ?>,
                                                    <?php echo json_encode($exp["start_date"]); ?>,
                                                    <?php echo json_encode($exp["end_date"]); ?>
                                                )'
                                                class="text-blue-500 hover:text-blue-700">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <form method="POST" action="view_profile_summary.php" onsubmit="return confirmExpDeletion(event, '<?php echo $exp['exp_id']; ?>');" id="deleteExpForm_<?php echo $exp['exp_id']; ?>">
                                                <input type="hidden" name="exp_action" value="delete_experience">
                                                <input type="hidden" name="exp_id" value="<?php echo $exp['exp_id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 certificate-download-icon"><i class="fa fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                <?php endif; ?>
            </section>

                <?php if (!empty($projects)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b-2" style="color: var(--color-text-card); border-color: var(--color-accent);">
                        <i class="fa fa-project-diagram mr-2" style="color: var(--color-accent);"></i>
                        <?php echo $lang['manage_projects'] ?? 'Projects & Portfolio'; ?>
                    </h2>
                    <div class="space-y-4">
                    <?php foreach ($projects as $project): ?>
            <div class="p-3 rounded-lg border flex items-start gap-4" 
                 style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                
                <div class="flex-shrink-0">
                    <?php if (!empty($project['project_image_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($project['project_image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($project['title']); ?> Logo" 
                             class="w-16 h-16 object-cover rounded-md border" style="border-color: var(--color-border);">
                    <?php else: ?>
                         <div class="w-16 h-16 flex items-center justify-center rounded-md border" style="border-color: var(--color-border); background-color: var(--color-bg-dashboard);">
                             <i class="fa fa-code text-2xl" style="color: var(--color-text-card); opacity: 0.5;"></i>
                         </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex-grow">
                    <div class="flex justify-between items-start">
                        <span class="font-semibold text-lg" style="color: var(--color-primary);"><?php echo htmlspecialchars($project['title']); ?></span>
                        
                        <?php if (!empty($project['project_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" 
                               class="text-sm text-green-600 hover:text-green-800 transition whitespace-nowrap">
                                <i class="fa fa-external-link-alt"></i> View Link
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-xs mt-1" style="color: var(--color-text-card); opacity: 0.8;">
                        **Tech:** <?php echo htmlspecialchars($project['technologies']); ?>
                    </p>
                    <p class="text-sm mt-2" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
                    </div>
                    <a href="manage_projects.php" class="text-sm mt-2 block" style="color: var(--color-primary);">Manage Projects</a>
                </section>
                <?php endif; ?>

                <section class="mb-6 pt-4 border-t" style="border-color: var(--color-border);">
                    <div class="flex justify-between items-center mb-3">
                        <h2 class="text-xl font-bold border-b-2" style="color: var(--color-text-card); border-color: var(--color-accent);">
                             <i class="fa fa-award mr-2" style="color: var(--color-accent);"></i>
                            <?php echo $lang['certificates'] ?? 'Certificates & Awards'; ?>
                        </h2>
                        <button type="button" onclick="openCertModal('add')" class="px-3 py-1 rounded-md text-white text-sm" style="background-color: var(--color-primary);">
                             <i class="fa fa-plus"></i> Add New
                        </button>
                    </div>
                    
                    <?php if (empty($certificates)): ?>
                        <p class="text-sm opacity-70" style="color: var(--color-text-card);">No certificates added yet.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($certificates as $cert): ?>
                            <div class="flex justify-between items-center p-3 rounded-lg border" style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                                <div class="flex-grow">
                                    <span class="font-semibold" style="color: var(--color-text-card);"><?php echo htmlspecialchars($cert['cert_name']); ?></span>
                                    <p class="text-xs opacity-70">
                                        <?php echo htmlspecialchars($cert['issuer']); ?> (<?php echo htmlspecialchars($cert['issue_date']); ?>)
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <?php if (!empty($cert['cert_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($cert['cert_path']); ?>" target="_blank" class="text-sm text-green-500 hover:text-green-700">
                                            <i class="fa fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                        onclick="openCertModal('edit', <?php echo $cert['cert_id']; ?>, '<?php echo addslashes($cert['cert_name']); ?>', '<?php echo addslashes($cert['issuer']); ?>', '<?php echo $cert['issue_date']; ?>', '<?php echo $cert['cert_path']; ?>')" 
                                            class="text-blue-500 hover:text-blue-700"><i class="fa fa-edit"></i></button>
                                    
                                            <form method="POST" action="view_profile_summary.php" onsubmit="return confirmCertDeletion(event, '<?php echo $cert['cert_id']; ?>');" id="deleteCertForm_<?php echo $cert['cert_id']; ?>">
                                            <input type="hidden" name="action" value="delete_cert">
                                            <input type="hidden" name="cert_id" value="<?php echo $cert['cert_id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 certificate-download-icon"><i class="fa fa-trash"></i></button>
                                        </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

               <section class="mb-6 text-center pt-4 border-t" style="border-color: var(--color-border);">
                <a href="generate_pdf.php" target="_blank" class="px-4 py-2 rounded-lg text-white font-medium" style="background-color: var(--color-primary); display: inline-block;">
                    <i class="fa fa-file-pdf mr-2"></i> <?php echo $lang['download_profile_pdf'] ?? 'Download Full Profile as PDF'; ?>
                </a>
            </section>

            </div>
        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
            style="background-color: var(--color-bg-card); border-color: var(--color-border); height: max-content; position: sticky; top: 60px;">
                
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Profile Actions</h2>
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
                <a href="dashboard.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-tachometer-alt w-5"></i><span><?php echo $lang['dashboard'] ?? 'Dashboard'; ?></span>
                </a>
                <a href="profile.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-primary);">
                    <i class="fa fa-user-edit w-5"></i><span><?php echo $lang['button_edit_profile'] ?? 'Edit Profile'; ?> (Edit Form)</span>
                </a>
                <a href="manage_skills.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-cogs w-5"></i><span><?php echo $lang['manage_skills'] ?? 'Manage Skills'; ?></span>
                </a>
                <a href="manage_projects.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-project-diagram w-5"></i><span><?php echo $lang['manage_projects'] ?? 'Manage Projects'; ?></span>
                </a>
                <a href="../logout.php" class="flex items-center gap-3 hover:text-red-500" style="color: var(--color-error);">
                    <i class="fa fa-sign-out-alt w-5"></i><span><?php echo $lang['logout'] ?? 'Logout'; ?></span>
                </a>
            </nav>
        </aside>
    </div>

    <div id="expModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="p-6 rounded-lg shadow-2xl w-full max-w-md" style="background-color: var(--color-bg-card);">
            <h3 class="text-2xl font-bold mb-4" id="expModalTitle" style="color: var(--color-primary);">Add Work Experience</h3>
            
            <form method="POST" action="view_profile_summary.php" class="space-y-4">
                <input type="hidden" name="exp_action" id="expModalAction" value="add_experience">
                <input type="hidden" name="exp_id" id="experience_id_edit"> <div>
                    <label for="exp_title" class="block font-medium mb-1">Job Title:</label>
                    <input type="text" name="title" id="exp_title" required>
                </div>

                <div>
                    <label for="exp_company" class="block font-medium mb-1">Company:</label>
                    <input type="text" name="company" id="exp_company" required>
                    
                </div>

                <div>
                    <label for="exp_start_date" class="block font-medium mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="exp_start_date" required>
                </div>

                <div>
                    <label for="exp_end_date" class="block font-medium mb-1">End Date (or leave empty for Present):</label>
                    <input type="date" name="end_date" id="exp_end_date">
                </div>
                
                <div class="flex justify-end space-x-3 mt-5">
                    <button type="button" onclick="document.getElementById('expModal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" class="button-primary px-4 py-2 rounded-lg" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-save mr-1"></i> Save Experience
                    </button>
                </div>
            </form>
        </div>
    </div>
 
    <div id="certModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="p-6 rounded-lg shadow-2xl w-full max-w-md" style="background-color: var(--color-bg-card);">
            <h3 class="text-2xl font-bold mb-4" id="modalTitle" style="color: var(--color-primary);">Add Certificate</h3>
            
            <form method="POST" action="view_profile_summary.php" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" id="modalAction" value="add_cert">
                <input type="hidden" name="cert_id_edit" id="cert_id_edit">

                <div><label for="cert_name" class="block font-medium mb-1">Certificate Name:</label><input type="text" name="cert_name" id="cert_name" required></div>
                <div><label for="issuer" class="block font-medium mb-1">Issuing Organization:</label><input type="text" name="issuer" id="issuer" required></div>
                <div><label for="issue_date" class="block font-medium mb-1">Issue Date:</label><input type="date" name="issue_date" id="issue_date" required></div>
                
                <div>
                    <label for="cert_file" class="block font-medium mb-1">Certificate File (PDF, JPG, PNG, Max 5MB):</label>
                    <input type="file" name="cert_file" id="cert_file" accept=".pdf, image/*" class="w-full text-sm">
                    <p class="text-xs mt-1 opacity-70" id="currentFilePath"></p>
                </div>

                <div class="flex justify-end space-x-3 mt-5">
                    <button type="button" onclick="document.getElementById('certModal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="button-primary px-4 py-2 rounded-lg" style="background-color: var(--color-primary); color: var(--color-bg-card);"><i class="fa fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        // Get the site's base URL once in PHP for use in JS
        const PHP_BASE_URL = '<?php echo get_base_url(); ?>'; 
        
        // Modal Logic for Certificates
        function openCertModal(mode, id = null, name = '', issuer = '', date = '', path = '') {
            const modal = document.getElementById('certModal');
            document.getElementById('modalTitle').textContent = mode === 'add' ? 'Add New Certificate' : 'Edit Certificate';
            document.getElementById('modalAction').value = mode === 'add' ? 'add_cert' : 'update_cert';
            document.getElementById('cert_id_edit').value = id;
            
            const certFile = document.getElementById('cert_file');
            
            // If editing, pre-fill text inputs
            if (mode === 'edit') {
                document.getElementById('cert_name').value = name;
                document.getElementById('issuer').value = issuer;
                document.getElementById('issue_date').value = date;
                certFile.removeAttribute('required'); // File not required on edit if old file exists
            } else {
                document.getElementById('cert_name').value = '';
                document.getElementById('issuer').value = '';
                document.getElementById('issue_date').value = '';
                certFile.setAttribute('required', 'required'); // File required on add
            }

            // Clear file input value to prevent accidental resubmission of the old file
            certFile.value = '';
            
            const pathDisplay = document.getElementById('currentFilePath');
            pathDisplay.innerHTML = ''; 

            if (path) {
                pathDisplay.innerHTML = `Current File: <a href="${PHP_BASE_URL}/${path}" target="_blank" class="text-blue-500">View File</a> (Upload a new file to replace)`;
            } else {
                pathDisplay.textContent = mode === 'edit' ? 'No file currently uploaded.' : '';
            }
            
            modal.classList.remove('hidden');
        }

        document.getElementById('certModal').addEventListener('click', function(e) {
            if (e.target.id === 'certModal') { e.target.classList.add('hidden'); }
        });

    // Experience Modal Logic
    function openExpModal(mode, id = null, title = '', company = '', start_date = '', end_date = '') {
        const modal = document.getElementById('expModal');
        
        // Set modal title
        document.getElementById('expModalTitle').textContent =
            mode === 'add' ? 'Add New Experience' : 'Edit Experience';

        // Set form action value
        document.getElementById('expModalAction').value =
            mode === 'add' ? 'add_experience' : 'update_experience';

        // Populate form fields
        document.getElementById('experience_id_edit').value = id || '';
        document.getElementById('exp_title').value = title || '';
        document.getElementById('exp_company').value = company || '';
        document.getElementById('exp_start_date').value = start_date || '';
        document.getElementById('exp_end_date').value = end_date || '';

        // Show the modal
        modal.classList.remove('hidden');
    }

    // Hide modal when clicking on backdrop
    document.getElementById('expModal').addEventListener('click', function(e) {
        if (e.target.id === 'expModal') {
            e.target.classList.add('hidden');
        }
    });

    // SweetAlert2 Confirmation for Certificates
    function confirmCertDeletion(event, certId) {
        event.preventDefault(); // Stop the form from submitting immediately
        
        Swal.fire({
            title: 'Are you sure?',
            text: "This certificate file and record will be permanently deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--color-primary)', // Use theme colors
            cancelButtonColor: 'var(--color-error)',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // If confirmed, manually submit the form
                document.getElementById('deleteCertForm_' + certId).submit();
            }
        });
        return false; // Prevent default form submission
    }

    // SweetAlert2 Confirmation for Experiences
    function confirmExpDeletion(event, expId) {
        event.preventDefault(); // Stop the form from submitting immediately

        Swal.fire({
            title: 'Are you sure?',
            text: "This work experience will be permanently removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--color-primary)',
            cancelButtonColor: 'var(--color-error)',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // If confirmed, manually submit the form
                document.getElementById('deleteExpForm_' + expId).submit();
            }
        });
        return false; // Prevent default form submission
    }


    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>