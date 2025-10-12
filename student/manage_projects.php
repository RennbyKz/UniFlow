<?php
// student/manage_projects.php
ob_start(); 
require_once '../core/functions.php'; 
// Student Role Check
init_system('manage_projects', 'student'); 

// ------------------------------------------
// Initialization & Student Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];

// Student ID ကို session မှ ရယူ
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_id = $stmt->fetchColumn();

// Account မတွေ့ရင် ထွက်
if (!$student_id) { 
    set_session_message('profile_data_not_found', 'error');
    header("Location: ../logout.php"); 
    ob_end_flush(); 
    exit(); 
}

// ------------------------------------------
// 1. ACTION HANDLING (Add / Delete / Update Project)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');
    $redirect = 'manage_projects.php';

    if ($action === 'add_project' || $action === 'update_project') {
        $title = sanitize_input($_POST['title'] ?? '');
        $url = sanitize_input($_POST['project_url'] ?? '');
        $tech = sanitize_input($_POST['technologies'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $project_id = sanitize_input($_POST['project_id_edit'] ?? null); // For update only
        
        $image_path = null; 
        $upload_success = true;
        
        // 1A. လက်ရှိ Path ကို Update Mode အတွက် ပြန်ယူခြင်း (File အသစ်မရွေးရင်)
        if ($action === 'update_project') {
             $current_path_stmt = $pdo->prepare("SELECT project_image_path FROM student_projects WHERE project_id = ?");
             $current_path_stmt->execute([$project_id]);
             $image_path = $current_path_stmt->fetchColumn(); // Existing path
        }
        
        // 1B. Image Upload Logic (New File Received)
        if (isset($_FILES['project_logo']) && $_FILES['project_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['project_logo'];
            $allowed_ext = ['jpg', 'jpeg', 'png'];
            $max_size = 2 * 1024 * 1024; // 2 MB
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_ext) || $file['size'] > $max_size) {
                $errors[] = $lang['error_invalid_image'] ?? 'Invalid file type or size. Max 2MB, JPG/PNG.';
                $upload_success = false;
            } else {
                // File Rename and Save (Security)
                $new_file_name = 'project_' . $student_id . '_' . uniqid() . '.' . $ext;
                $upload_dir = '../uploads/projects/';
                
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
                    // Upload အဟောင်းကို File System ကနေ ဖျက်ပါ (Optional)
                    if ($image_path && file_exists('../' . $image_path)) {
                        unlink('../' . $image_path); 
                    }
                    $image_path = 'uploads/projects/' . $new_file_name;
                } else {
                    $errors[] = $lang['error_image_upload_failed'] ?? 'Image upload failed.';
                    $upload_success = false;
                }
            }
        } 
        
        // 1C. Final Insertion/Update
        if ($upload_success && empty($errors) && !empty($title) && !empty($tech)) {
            if ($action === 'add_project') {
                $stmt = $pdo->prepare("
                    INSERT INTO student_projects (student_id, title, project_url, project_image_path, technologies, description) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$student_id, $title, $url, $image_path, $tech, $description]);
                set_session_message('project_add_success', 'success');
            } elseif ($action === 'update_project') {
                $stmt = $pdo->prepare("
                    UPDATE student_projects 
                    SET title = ?, project_url = ?, project_image_path = ?, technologies = ?, description = ? 
                    WHERE project_id = ? AND student_id = ?
                ");
                $stmt->execute([$title, $url, $image_path, $tech, $description, $project_id, $student_id]);
                set_session_message('project_update_success', 'success');
            }
        } else {
            if (empty($errors)) $errors[] = $lang['error_fill_all'];
        }
    } 
    
    elseif ($action === 'delete_project' && isset($_POST['project_entry_id'])) {
        $entry_id = sanitize_input($_POST['project_entry_id']);
        try {
            // Delete Logic: File System ကနေ Image ကိုပါ ဖျက်ရန်
            $stmt = $pdo->prepare("SELECT project_image_path FROM student_projects WHERE project_id = ? AND student_id = ?");
            $stmt->execute([$entry_id, $student_id]);
            $path_to_delete = $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM student_projects WHERE project_id = ? AND student_id = ?");
            $stmt->execute([$entry_id, $student_id]);
            
            // File System ကနေ Image ကိုပါ ဖျက်ပါ (Optional)
            if ($path_to_delete && file_exists('../' . $path_to_delete)) {
                unlink('../' . $path_to_delete); 
            }

            set_session_message('project_delete_success', 'success');
        } catch (PDOException $e) {
            $errors[] = $lang['error_general_try_again'] . " (Deletion Error)";
        }
    }
    
    // Error Handling & Redirect
    if (!empty($errors)) {
        foreach ($errors as $err) { set_session_message($err, 'error'); } 
    }
    
    header("Location: " . $redirect);
    ob_end_flush();
    exit();
}

// ------------------------------------------
// 2. DATA FETCHING (Current Projects List)
// ------------------------------------------

$projects_list = [];
try {
    $stmt = $pdo->prepare("SELECT project_id, title, project_url, project_image_path, technologies, description FROM student_projects WHERE student_id = ? ORDER BY project_id DESC");
    $stmt->execute([$student_id]);
    $projects_list = $stmt->fetchAll();
} catch (PDOException $e) {
    set_session_message('error_general_try_again', 'error');
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8" style="background-color: var(--color-bg-dashboard);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['manage_projects'] ?? 'Manage Projects & Assignments'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border mb-8" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Add New Project</h2>

                <form method="POST" action="manage_projects.php" enctype="multipart/form-data" class="space-y-4 items-end">
                    <input type="hidden" name="action" value="add_project">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="title" class="block font-medium mb-1"><?php echo $lang['label_project_title'] ?? 'Project Title'; ?>:</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div>
                            <label for="project_url" class="block font-medium mb-1"><?php echo $lang['label_website_link'] ?? 'Website / GitHub Link'; ?>:</label>
                            <input type="url" id="project_url" name="project_url">
                        </div>
                    </div>

                    <div>
                        <label for="project_logo" class="block font-medium mb-1"><?php echo $lang['label_project_logo'] ?? 'Project Logo / Screenshot (Max 2MB)'; ?>:</label>
                        <input type="file" name="project_logo" id="project_logo" accept="image/png, image/jpeg, image/jpg" class="w-full text-sm">
                    </div>

                    <div>
                        <label for="technologies" class="block font-medium mb-1"><?php echo $lang['label_technologies'] ?? 'Technologies Used (e.g., PHP, MySQL, Tailwind)'; ?>:</label>
                        <input type="text" id="technologies" name="technologies" required>
                    </div>

                    <div>
                        <label for="description" class="block font-medium mb-1"><?php echo $lang['label_description'] ?? 'Description / Your Role'; ?>:</label>
                        <textarea id="description" name="description" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea>
                    </div>

                    <button type="submit" class="button-primary px-6 py-2 rounded-lg font-semibold whitespace-nowrap"
                            style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-plus-circle mr-2"></i> <?php echo $lang['button_add_project'] ?? 'Add Project'; ?>
                    </button>
                </form>
            </div>

            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Current Projects (<?php echo count($projects_list); ?>)</h2>

                <?php if (empty($projects_list)): ?>
                    <p class="text-sm opacity-70">You have not added any projects yet. Add your academic or personal projects.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($projects_list as $project): ?>
                        <div class="p-4 rounded-lg border flex items-start gap-4" style="border-color: var(--color-border);">
                            
                            <div class="mr-4 flex-shrink-0">
                                <?php if (!empty($project['project_image_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($project['project_image_path']); ?>" alt="Project Logo" class="w-16 h-16 object-cover rounded-md border" style="border-color: var(--color-border);">
                                <?php else: ?>
                                     <div class="w-16 h-16 flex items-center justify-center rounded-md border" style="border-color: var(--color-border); background-color: var(--color-bg-dashboard);">
                                         <i class="fa fa-code text-2xl" style="color: var(--color-text-card); opacity: 0.5;"></i>
                                     </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-grow">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="font-semibold text-lg" style="color: var(--color-primary);"><?php echo htmlspecialchars($project['title']); ?></span>
                                        <p class="text-xs mt-1" style="color: var(--color-text-card); opacity: 0.8;">
                                            Tech: <span class="font-medium"><?php echo htmlspecialchars($project['technologies']); ?></span>
                                        </p>
                                    </div>
                                    
                                    <div class="flex space-x-2 items-center">
                                        
                                        <button type="button" 
                                                onclick="openEditModal(
                                                            <?php echo $project['project_id']; ?>, 
                                                            '<?php echo htmlspecialchars(addslashes($project['title'])); ?>', 
                                                            '<?php echo htmlspecialchars(addslashes($project['project_url'])); ?>',
                                                            '<?php echo htmlspecialchars(addslashes($project['technologies'])); ?>',
                                                            '<?php echo htmlspecialchars(addslashes($project['description'])); ?>',
                                                            '<?php echo htmlspecialchars(addslashes($project['project_image_path'] ?? '')); ?>'
                                                        )"
                                                class="text-blue-500 hover:text-blue-700 transition text-sm">
                                            <i class="fa fa-edit"></i>
                                        </button>

                                        <?php if (!empty($project['project_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" class="text-sm text-green-500 hover:text-green-700 transition">
                                                <i class="fa fa-external-link-alt"></i> Link
                                            </a>
                                        <?php endif; ?>
                                        
                                        <form method="POST" action="manage_projects.php" onsubmit="return confirm('<?php echo $lang['confirm_delete_project'] ?? 'Are you sure you want to delete this project?'; ?>');">
                                            <input type="hidden" name="action" value="delete_project">
                                            <input type="hidden" name="project_entry_id" value="<?php echo $project['project_id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 transition text-sm">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <p class="text-sm mt-2" style="color: var(--color-text-card); opacity: 0.9;"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">
            
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Profile Management</h2>
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
                <?php 
                echo sidebar_link('profile.php', 'fa-user-circle', 'student_profile', 'profile.php');
                echo sidebar_link('manage_skills.php', 'fa-cogs', 'manage_skills', 'manage_skills.php'); 
                echo sidebar_link('manage_projects.php', 'fa-project-diagram', 'manage_projects', 'manage_projects.php'); // Active
                echo sidebar_link('view_profile_summary.php', 'fa-id-card', 'view_summary', 'view_profile_summary.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>
    </div>
    
    <div id="editProjectModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-lg" style="background-color: var(--color-bg-card);">
            <h3 class="text-2xl font-bold mb-4" style="color: var(--color-primary);">Edit Project Details</h3>
            
            <form method="POST" action="manage_projects.php" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="update_project">
                <input type="hidden" name="project_id_edit" id="modal_project_id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="modal_title" class="block font-medium mb-1"><?php echo $lang['label_project_title'] ?? 'Project Title'; ?>:</label>
                        <input type="text" name="title" id="modal_title" required>
                    </div>
                    <div>
                        <label for="modal_project_url" class="block font-medium mb-1"><?php echo $lang['label_website_link'] ?? 'Website / GitHub Link'; ?>:</label>
                        <input type="url" name="project_url" id="modal_project_url">
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="modal_project_logo" class="block font-medium mb-1"><?php echo $lang['label_project_logo'] ?? 'Update Logo/Screenshot (Max 2MB)'; ?>:</label>
                    
                    <div id="current_image_preview" class="mb-2 flex items-center gap-3">
                        </div>
                    
                    <input type="file" name="project_logo" id="modal_project_logo" accept="image/png, image/jpeg, image/jpg" class="w-full text-sm">
                </div>

                <div>
                    <label for="modal_technologies" class="block font-medium mb-1"><?php echo $lang['label_technologies'] ?? 'Technologies Used'; ?>:</label>
                    <input type="text" name="technologies" id="modal_technologies" required>
                </div>

                <div>
                    <label for="modal_description" class="block font-medium mb-1"><?php echo $lang['label_description'] ?? 'Description / Your Role'; ?>:</label>
                    <textarea name="description" id="modal_description" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-5">
                    <button type="button" onclick="document.getElementById('editProjectModal').classList.add('hidden')" 
                            class="px-4 py-2 rounded-lg text-gray-700 hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" class="button-primary px-4 py-2 rounded-lg"
                            style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>


    <script>
        /**
         * Project Edit Modal ကို ဖွင့်ပြီး Data များ ဖြည့်သွင်းခြင်း
         */
        function openEditModal(id, title, url, technologies, description, imagePath) {
            // Base URL ကို PHP ကနေ ရယူရန်
            const baseUrl = '<?php echo get_base_url(); ?>'; 

            // 1. Data ဖြည့်သွင်းခြင်း
            document.getElementById('modal_project_id').value = id;
            document.getElementById('modal_title').value = title;
            document.getElementById('modal_project_url').value = url;
            document.getElementById('modal_technologies').value = technologies;
            document.getElementById('modal_description').value = description;
            
            // 2. Image Preview ပြသခြင်း
            const previewDiv = document.getElementById('current_image_preview');
            previewDiv.innerHTML = ''; // Clear previous content

            if (imagePath) {
                previewDiv.innerHTML = `
                    <span class="text-sm font-medium mr-2">Current Image:</span>
                    <img src="${baseUrl + '/' + imagePath}" alt="Current Logo" class="w-12 h-12 object-cover rounded-md">
                `;
            } else {
                previewDiv.innerHTML = `<span class="text-sm opacity-70">No current image.</span>`;
            }
            
            // 3. Modal ကို ပြသခြင်း
            document.getElementById('editProjectModal').classList.remove('hidden');
        }

        // Modal ကို ပိတ်သည့်အခါ Shadow ပေါ်တွင် နှိပ်လျှင် ပိတ်ရန်
        document.getElementById('editProjectModal').addEventListener('click', function(e) {
            if (e.target.id === 'editProjectModal') {
                e.target.classList.add('hidden');
            }
        });
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>