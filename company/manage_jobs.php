<?php
// company/manage_jobs.php
ob_start(); 
require_once '../core/functions.php'; 
// Company Role Check
init_system('manage_jobs', 'company'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];

// Company ID ကို session မှ ရယူ
$stmt = $pdo->prepare("SELECT company_id FROM companies WHERE user_id = ?");
$stmt->execute([$user_id]);
$company_id = $stmt->fetchColumn();

if (!$company_id) { 
    set_session_message('profile_data_not_found', 'error');
    header("Location: dashboard.php"); 
    ob_end_flush(); 
    exit(); 
}

// ------------------------------------------
// 1. ACTION HANDLING (Edit / Delete Job)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');
    $job_id = sanitize_input($_POST['job_id'] ?? $_POST['job_id_edit'] ?? null);
    $redirect = 'manage_jobs.php';

    if ($action === 'delete') {
        try {
            // DELETE Logic: Company ID ကို စစ်ပြီးမှသာ ဖျက်ရမည် (Security)
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ? AND company_id = ?");
            $stmt->execute([$job_id, $company_id]);
            set_session_message('job_delete_success', 'success');
        } catch (PDOException $e) {
            $errors[] = $lang['error_general_try_again'] . " (Deletion Error)";
        }
    } elseif ($action === 'update') {
        // UPDATE Logic
        $title = sanitize_input($_POST['title_edit'] ?? '');
        $description = sanitize_input($_POST['description_edit'] ?? '');
        $required_skills = sanitize_input($_POST['required_skills_edit'] ?? '');
        $type = sanitize_input($_POST['type_edit'] ?? '');
        $location = sanitize_input($_POST['location_edit'] ?? '');
        $deadline = sanitize_input($_POST['deadline_edit'] ?? '');

        if (!empty($title) && !empty($description) && !empty($deadline)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE jobs SET 
                    title = ?, description = ?, required_skills = ?, type = ?, location = ?, deadline = ?
                    WHERE job_id = ? AND company_id = ?
                ");
                $stmt->execute([$title, $description, $required_skills, $type, $location, $deadline, $job_id, $company_id]);
                set_session_message('job_update_success', 'success');
            } catch (PDOException $e) {
                 $errors[] = $lang['error_general_try_again'] . " (Update Error)";
            }
        } else {
             $errors[] = $lang['error_fill_all'];
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
// 2. DATA FETCHING (Current Job List)
// ------------------------------------------

$jobs_list = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE company_id = ? ORDER BY posted_date DESC");
    $stmt->execute([$company_id]);
    $jobs_list = $stmt->fetchAll();
} catch (PDOException $e) {
    set_session_message('error_general_try_again', 'error');
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    <!-- ✅ Toast container -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__); // manage_jobs.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
        <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b flex items-center" 
            style="color: var(--color-main); border-color: var(--color-border);">

            <!-- Title -->
            <span class="flex-grow"><?php echo $lang['manage_jobs'] ?? 'Manage Job Postings'; ?></span>

            <!-- Save Changes Button (Link to post_jobs.php) -->
            <a href="post_job.php" class="button-primary px-3 py-1.5 text-sm rounded-lg"
                style="background-color: Green; color: var(--color-bg-card);">
                <i class="fa fa-save mr-1"></i><?php echo $lang['post_new_job']; ?>
            </a>
        </h1>

            
            
            <?php display_session_messages($lang); ?>

            <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead style="background-color: var(--color-bg-main);">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);">Title / Location</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);">Deadline</th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                        <?php if (empty($jobs_list)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center"><?php echo $lang['no_jobs_found'] ?? 'You have not posted any jobs yet.'; ?></td></tr>
                        <?php endif; ?>

                        <?php foreach ($jobs_list as $job): ?>
                            <tr class="hover:bg-opacity-5 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap font-medium">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                    <p class="text-xs opacity-70 mt-1"><?php echo htmlspecialchars($job['location']); ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: var(--color-accent); color: white;">
                                        <?php echo htmlspecialchars($job['type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($job['deadline']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center space-x-2">
                                        <button type="button" 
                                                onclick="openEditModal(<?php echo $job['job_id']; ?>, 
                                                         '<?php echo addslashes(htmlspecialchars($job['title'])); ?>', 
                                                         '<?php echo addslashes(htmlspecialchars($job['description'])); ?>',
                                                         '<?php echo addslashes(htmlspecialchars($job['required_skills'])); ?>',
                                                         '<?php echo htmlspecialchars($job['type']); ?>',
                                                         '<?php echo htmlspecialchars($job['location']); ?>',
                                                         '<?php echo htmlspecialchars($job['deadline']); ?>'
                                                        )"
                                                class="text-blue-500 hover:text-blue-700 transition"><i class="fa fa-edit"></i></button>
                                        
                                        <form method="POST" action="manage_jobs.php" onsubmit="return confirm('<?php echo $lang['confirm_delete_job'] ?? 'Are you sure you want to delete this job?'; ?>');" class="inline-block">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 transition">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
    
    <div id="editJobModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="p-6 rounded-lg shadow-2xl w-full max-w-xl" style="background-color: var(--color-bg-card);">
            <h3 class="text-2xl font-bold mb-4" style="color: var(--color-primary);">Edit Job Listing</h3>
            
            <form method="POST" action="manage_jobs.php" class="space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="job_id_edit" id="modal_job_id">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="modal_title" class="block font-medium mb-1">Title:</label>
                        <input type="text" name="title_edit" id="modal_title" required>
                    </div>
                    <div>
                        <label for="modal_type" class="block font-medium mb-1">Type:</label>
                        <select id="modal_type" name="type_edit" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="Internship">Internship</option>
                            <option value="Full-Time">Full-Time</option>
                            <option value="Part-Time">Part-Time</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="modal_location" class="block font-medium mb-1">Location:</label>
                        <input type="text" id="modal_location" name="location_edit" required>
                    </div>
                    <div>
                        <label for="modal_deadline" class="block font-medium mb-1">Deadline:</label>
                        <input type="date" id="modal_deadline" name="deadline_edit" required>
                    </div>
                </div>

                <div>
                    <label for="modal_description" class="block font-medium mb-1">Description:</label>
                    <textarea id="modal_description" name="description_edit" rows="5" required class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                
                <div>
                    <label for="modal_skills" class="block font-medium mb-1">Required Skills (Comma Separated):</label>
                    <input type="text" id="modal_skills" name="required_skills_edit" required>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="document.getElementById('editJobModal').classList.add('hidden')" 
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
        // Modal Handling Logic
        function openEditModal(id, title, description, skills, type, location, deadline) {
            // 1. Data ဖြည့်သွင်းခြင်း
            document.getElementById('modal_job_id').value = id;
            document.getElementById('modal_title').value = title;
            document.getElementById('modal_description').value = description;
            document.getElementById('modal_skills').value = skills;
            document.getElementById('modal_type').value = type;
            document.getElementById('modal_location').value = location;
            document.getElementById('modal_deadline').value = deadline;
            
            // 2. Modal ကို ပြသခြင်း
            document.getElementById('editJobModal').classList.remove('hidden');
        }

        // Modal ကို ပိတ်သည့်အခါ Shadow ပေါ်တွင် နှိပ်လျှင် ပိတ်ရန်
        document.getElementById('editJobModal').addEventListener('click', function(e) {
            if (e.target.id === 'editJobModal') {
                e.target.classList.add('hidden');
            }
        });
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>