<?php
// student/manage_skills.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('manage_skills', 'student'); 

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
// 1. ACTION HANDLING (Add / Delete / Update Skill)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action'] ?? '');
    $redirect = 'manage_skills.php';

    if ($action === 'add_skill') {
        $skill_name = sanitize_input($_POST['skill_name'] ?? '');
        $level = sanitize_input($_POST['level'] ?? '');
        
        if (!empty($skill_name) && !empty($level)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO student_skills (student_id, skill_name, proficiency_level) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$student_id, $skill_name, $level]);
                set_session_message('skill_add_success', 'success');
            } catch (PDOException $e) {
                set_session_message('skill_exists_error', 'error'); // Duplicated Entry Error (Unique Key Error)
            }
        } else {
            set_session_message('error_fill_all', 'error');
        }
    } 
    
    elseif ($action === 'delete_skill' && isset($_POST['skill_entry_id'])) {
        $skill_entry_id = sanitize_input($_POST['skill_entry_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM student_skills WHERE skill_id = ? AND student_id = ?");
            $stmt->execute([$skill_entry_id, $student_id]);
            set_session_message('skill_delete_success', 'success');
        } catch (PDOException $e) {
            set_session_message('error_general_try_again', 'error');
        }
    }
    
    elseif ($action === 'update_skill' && isset($_POST['skill_id_edit'], $_POST['skill_name_edit'], $_POST['level_edit'])) {
        $skill_id = sanitize_input($_POST['skill_id_edit']);
        $new_name = sanitize_input($_POST['skill_name_edit']);
        $new_level = sanitize_input($_POST['level_edit']);
        
        if (!empty($new_name) && !empty($new_level)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE student_skills 
                    SET skill_name = ?, proficiency_level = ? 
                    WHERE skill_id = ? AND student_id = ?
                ");
                $stmt->execute([$new_name, $new_level, $skill_id, $student_id]);
                set_session_message('skill_update_success', 'success');
            } catch (PDOException $e) {
                set_session_message('skill_exists_error', 'error'); // Duplicated Entry
            }
        } else {
            set_session_message('error_fill_all', 'error');
        }
    }
    
    // Redirect to clear POST data and show Toast
    header("Location: " . $redirect);
    ob_end_flush();
    exit();
}

// ------------------------------------------
// 2. DATA FETCHING (Current Skills List)
// ------------------------------------------

$skills_list = [];
try {
    $stmt = $pdo->prepare("SELECT skill_id, skill_name, proficiency_level FROM student_skills WHERE student_id = ? ORDER BY FIELD(proficiency_level, 'Expert', 'Advanced', 'Intermediate', 'Beginner'), skill_name ASC");
    $stmt->execute([$student_id]);
    $skills_list = $stmt->fetchAll();
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

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8" style="background-color: var(--color-bg-main); padding-left: 400px;">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['manage_skills'] ?? 'Manage Skills'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border mb-8" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Add New Skill</h2>

                <form method="POST" action="manage_skills.php" class="space-y-4 md:flex md:space-x-4 md:space-y-0 items-end">
                    <input type="hidden" name="action" value="add_skill">
                    
                    <div class="flex-1">
                        <label for="skill_name" class="block font-medium mb-1"><?php echo $lang['label_skill_name'] ?? 'Skill Name'; ?>:</label>
                        <input type="text" id="skill_name" name="skill_name" required class="w-full px-4 py-2 border rounded-lg">
                    </div>

                    <div class="flex-1">
                        <label for="level" class="block font-medium mb-1"><?php echo $lang['label_proficiency'] ?? 'Proficiency'; ?>:</label>
                        <select name="level" id="level" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- <?php echo $lang['select_level'] ?? 'Select Level'; ?> --</option>
                            <option value="Expert"><?php echo $lang['level_expert'] ?? 'Expert'; ?></option>
                            <option value="Advanced"><?php echo $lang['level_advanced'] ?? 'Advanced'; ?></option>
                            <option value="Intermediate"><?php echo $lang['level_intermediate'] ?? 'Intermediate'; ?></option>
                            <option value="Beginner"><?php echo $lang['level_beginner'] ?? 'Beginner'; ?></option>
                        </select>
                    </div>

                    <button type="submit" class="button-primary px-6 py-2 rounded-lg font-semibold whitespace-nowrap"
                            style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-plus-circle mr-2"></i> <?php echo $lang['button_add_skill'] ?? 'Add Skill'; ?>
                    </button>
                </form>
            </div>

            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Current Skills (<?php echo count($skills_list); ?>)</h2>

                <?php if (empty($skills_list)): ?>
                    <p class="text-sm opacity-70">You have not added any skills yet. Add your technical and soft skills.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($skills_list as $skill): ?>
                        <div class="flex justify-between items-center p-3 rounded-lg border" style="border-color: var(--color-border);">
                            <div class="flex flex-col">
                                <span class="font-semibold" style="color: var(--color-text-card);"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                                <span class="text-xs" style="color: var(--color-primary);"><?php echo htmlspecialchars($skill['proficiency_level']); ?></span>
                            </div>
                            
                            <div class="flex space-x-2 items-center">
                                <button type="button" 
                                        onclick="openEditModal(
                                                    <?php echo $skill['skill_id']; ?>, 
                                                    '<?php echo htmlspecialchars(addslashes($skill['skill_name'])); ?>', 
                                                    '<?php echo htmlspecialchars($skill['proficiency_level']); ?>'
                                                )"
                                        class="text-blue-500 hover:text-blue-700 transition">
                                    <i class="fa fa-edit"></i>
                                </button>
                                
                                <form method="POST" action="manage_skills.php" onsubmit="return confirm('<?php echo $lang['confirm_delete_skill'] ?? 'Are you sure you want to delete this skill?'; ?>');">
                                    <input type="hidden" name="action" value="delete_skill">
                                    <input type="hidden" name="skill_entry_id" value="<?php echo $skill['skill_id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 transition">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(50vh - 60px); position: sticky; top: 60px;">
            
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Profile Management</h2>
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
                <?php 
                echo sidebar_link('profile.php', 'fa-user-circle', 'student_profile', 'profile.php');
                echo sidebar_link('manage_skills.php', 'fa-cogs', 'manage_skills', 'manage_skills.php'); // Active
                echo sidebar_link('manage_projects.php', 'fa-project-diagram', 'manage_projects', 'manage_projects.php'); 
                echo sidebar_link('view_profile_summary.php', 'fa-id-card', 'view_summary', 'view_profile_summary.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>
    </div>
    
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md" style="background-color: var(--color-bg-card);">
            <h3 class="text-2xl font-bold mb-4" style="color: var(--color-primary);">Edit Skill</h3>
            
            <form method="POST" action="manage_skills.php" class="space-y-4">
                <input type="hidden" name="action" value="update_skill">
                <input type="hidden" name="skill_id_edit" id="modal_skill_id">

                <div>
                    <label for="modal_skill_name" class="block font-medium mb-1"><?php echo $lang['label_skill_name'] ?? 'Skill Name'; ?>:</label>
                    <input type="text" name="skill_name_edit" id="modal_skill_name" required>
                </div>

                <div>
                    <label for="modal_level" class="block font-medium mb-1"><?php echo $lang['label_proficiency'] ?? 'Proficiency'; ?>:</label>
                    <select name="level_edit" id="modal_level" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="Expert"><?php echo $lang['level_expert'] ?? 'Expert'; ?></option>
                        <option value="Advanced"><?php echo $lang['level_advanced'] ?? 'Advanced'; ?></option>
                        <option value="Intermediate"><?php echo $lang['level_intermediate'] ?? 'Intermediate'; ?></option>
                        <option value="Beginner"><?php echo $lang['level_beginner'] ?? 'Beginner'; ?></option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 mt-5">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" 
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
         * Skill Edit Modal ကို ဖွင့်ပြီး Data များ ဖြည့်သွင်းခြင်း
         */
        function openEditModal(id, name, level) {
            // 1. Data ဖြည့်သွင်းခြင်း
            document.getElementById('modal_skill_id').value = id;
            document.getElementById('modal_skill_name').value = name;
            // Select Element ကို တန်ဖိုးနှင့် ကိုက်ညီအောင် ပြောင်းလဲပေးခြင်း
            document.getElementById('modal_level').value = level; 
            
            // 2. Modal ကို ပြသခြင်း
            document.getElementById('editModal').classList.remove('hidden');
        }

        // Modal ကို ပိတ်သည့်အခါ Shadow ပေါ်တွင် နှိပ်လျှင် ပိတ်ရန်
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target.id === 'editModal') {
                e.target.classList.add('hidden');
            }
        });
    </script>
    
<?php include '../templates/footer.php'; ?>