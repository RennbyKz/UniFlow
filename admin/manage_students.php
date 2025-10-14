<?php
// admin/manage_students.php
ob_start(); 
require_once '../core/functions.php'; 
// Admin Role Check
init_system('manage_students', 'admin'); 

// ------------------------------------------
// 1. ACTION HANDLING (Delete / Disable/Enable Account)
// ------------------------------------------

global $pdo;
$errors = [];
$student_id = 0; // Initialize outside the if block

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['student_id'])) {
    $action = sanitize_input($_POST['action']);
    $student_id = sanitize_input($_POST['student_id']);
    
    try {
        $pdo->beginTransaction();

        // Fetch user_id for security and cascade
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $user_id_to_act = $stmt->fetchColumn();

        if (!$user_id_to_act) { throw new Exception("Student not found."); }

        if ($action === 'delete') {
            // PERMANENT DELETE: users table ကနေ ဖျက်ပါ (CASCADE ကြောင့် students ပါ ဖျက်မည်)
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id_to_act]);
            $message_key = 'student_deleted';
            $toast_type = 'success';
            
        } elseif ($action === 'toggle_status') {
            // DISABLE/ENABLE: is_approved ကို ပြောင်းပြန်လှန်ပါ
            $current_approved = $pdo->query("SELECT is_approved FROM users WHERE user_id = {$user_id_to_act}")->fetchColumn();
            $new_status = ($current_approved === 'Yes') ? 'No' : 'Yes';
            
            $stmt = $pdo->prepare("UPDATE users SET is_approved = ? WHERE user_id = ?");
            $stmt->execute([$new_status, $user_id_to_act]);
            
            $message_key = ($new_status === 'Yes') ? 'student_enabled' : 'student_disabled';
            $toast_type = ($new_status === 'Yes') ? 'success' : 'warning';
        }
        
        $pdo->commit();
        set_session_message($message_key, $toast_type);
        header("Location: manage_students.php"); 
        ob_end_flush();
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        set_session_message('error_general_try_again', 'error');
        header("Location: manage_students.php");
        ob_end_flush();
        exit();
    }
}

// ------------------------------------------
// 2. DATA FETCHING (All Students List)
// ------------------------------------------

$students_list = [];
$search_keyword = sanitize_input($_GET['keyword'] ?? '');
$sql = "
    SELECT 
        s.student_id, s.full_name, s.major, s.gpa, s.year_of_study, 
        u.email, u.is_approved, u.user_id
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    WHERE 1=1
";
$params = [];
$where_clauses = [];

if (!empty($search_keyword)) {
    $where_clauses[] = "(s.full_name LIKE :keyword OR u.email LIKE :keyword OR s.major LIKE :keyword)";
    $params[':keyword'] = '%' . $search_keyword . '%';
}

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY s.full_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students_list = $stmt->fetchAll();
} catch (PDOException $e) {
    set_session_message($lang['error_general_try_again'] . " (List Error)", 'error');
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
                $currentPageFile = basename(__FILE__);
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', 'dashboard.php');
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', 'manage_companies.php');
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', $currentPageFile); // Active
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', 'reports.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-dashboard);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['manage_students'] ?? 'Manage Students'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="p-6 rounded-xl shadow-lg border mb-8" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <form method="GET" action="manage_students.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-3">
                        <label for="keyword" class="block font-medium mb-1">Search Name, Email, or Major:</label>
                        <input type="text" id="keyword" name="keyword" placeholder="Enter keywords..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>
                    
                    <button type="submit" class="button-primary px-6 py-2 rounded-lg font-semibold" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-search mr-2"></i> <?php echo $lang['button_search'] ?? 'Search'; ?>
                    </button>
                </form>
            </div>
            
            <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">
                Total Students: <?php echo count($students_list); ?>
            </h2>
            
            <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead style="background-color: var(--color-bg-main);">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Name / Major</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Contact / GPA</th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                        <?php if (empty($students_list)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center">No students found.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($students_list as $student): ?>
                            <?php 
                            $status = $student['is_approved'];
                            $is_active = ($status === 'Yes');
                            ?>
                            <tr class="hover:bg-opacity-5 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap font-medium">
                                    <p><?php echo htmlspecialchars($student['full_name']); ?></p>
                                    <p class="text-xs opacity-70"><?php echo htmlspecialchars($student['major']); ?> (<?php echo htmlspecialchars($student['year_of_study']); ?> Year)</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <p><?php echo htmlspecialchars($student['email']); ?></p>
                                    <p class="text-xs font-semibold" style="color: var(--color-accent);">GPA: <?php echo htmlspecialchars($student['gpa']); ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold text-white <?php echo $is_active ? 'bg-green-500' : 'bg-red-500'; ?>">
                                        <?php echo $is_active ? 'Active' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center space-x-2">
                                        
                                        <a href="view_profile.php?student_id=<?php echo $student['student_id']; ?>" 
                                           class="text-blue-500 hover:text-blue-700" title="View Profile">
                                            <i class="fa fa-eye"></i>
                                        </a>

                                        <button type="button" 
                                                onclick="confirmAction(<?php echo $student['student_id']; ?>, 'toggle_status', '<?php echo $is_active ? 'Are you sure you want to disable this account?' : 'Are you sure you want to ACTIVATE this account?'; ?>')"
                                                class="<?php echo $is_active ? 'text-red-500 hover:text-red-700' : 'text-green-500 hover:text-green-700'; ?>"
                                                title="<?php echo $is_active ? 'Disable Account' : 'Activate Account'; ?>">
                                            <i class="fa fa-toggle-on"></i>
                                        </button>

                                        <button type="button" 
                                                onclick="confirmAction(<?php echo $student['student_id']; ?>, 'delete', 'WARNING: This action will permanently delete the student and all their data. Are you sure?')"
                                                class="text-gray-500 hover:text-red-700" title="Delete Account">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php foreach ($students_list as $student): ?>
                <form method="POST" action="manage_students.php" id="action-form-<?php echo $student['student_id']; ?>" style="display: none;">
                    <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                    <input type="hidden" name="action" id="action-<?php echo $student['student_id']; ?>">
                </form>
            <?php endforeach; ?>

        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        /**
         * Universal Confirmation Alert for Toggle/Delete actions
         * @param {number} studentId - ID of the student to act on
         * @param {string} action - 'toggle_status' or 'delete'
         * @param {string} confirmText - Localized confirmation question
         */
        function confirmAction(studentId, action, confirmText) {
            let iconType = (action === 'delete') ? 'error' : 'warning';
            let confirmButtonColor = (action === 'delete') ? '#dc3545' : '#ffc107';
            let confirmButtonText = (action === 'delete') ? 'Yes, Delete Permanently' : 'Yes, Change Status';

            Swal.fire({
                title: 'Confirm Action',
                text: confirmText,
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: confirmButtonText
            }).then((result) => {
                if (result.isConfirmed) {
                    // Find the hidden form and submit it
                    const formId = `action-form-${studentId}`;
                    const form = document.getElementById(formId);

                    if (form) {
                        // Set the action field value and submit
                        form.elements['action'].value = action;
                        form.submit();
                    } else {
                        Swal.fire('Error', 'Form setup error. Please contact support.', 'error');
                    }
                }
            });
        }
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>