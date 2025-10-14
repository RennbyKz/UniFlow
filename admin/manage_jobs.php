<?php
// admin/manage_jobs.php
ob_start(); 
require_once '../core/functions.php'; 
// Admin Role Check
init_system('manage_jobs', 'admin'); 

// ------------------------------------------
// 1. ACTION HANDLING (Delete Job)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['job_id'])) {
    $action = sanitize_input($_POST['action']);
    $job_id = sanitize_input($_POST['job_id']);
    $redirect = 'manage_jobs.php';

    if ($action === 'delete') {
        try {
            // PERMANENT DELETE: jobs table ကနေ ဖျက်ပါ (CASCADE ကြောင့် applications များပါ ဖျက်မည်)
            global $pdo;
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ?");
            $stmt->execute([$job_id]);
            set_session_message('job_delete_success', 'success');
        } catch (PDOException $e) {
            set_session_message('error_general_try_again', 'error');
        }
    }
    
    header("Location: " . $redirect);
    ob_end_flush();
    exit();
}

// ------------------------------------------
// 2. DATA FETCHING (All Jobs List - with Filter)
// ------------------------------------------

global $pdo;
$jobs_list = [];
$errors = [];

$sql = "
    SELECT 
        j.job_id, j.title, j.type, j.deadline, j.posted_date,
        c.name AS company_name, c.is_approved AS company_status
    FROM jobs j
    JOIN companies c ON j.company_id = c.company_id
    WHERE 1=1
";
$params = [];

// 💡 FIX: Company ID Filter Logic
if (!empty($filter_company_id)) {
    $sql .= " AND c.company_id = :company_id";
    $params[':company_id'] = $filter_company_id;
}

$sql .= " ORDER BY j.posted_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // Parameters တွေကို Execute လုပ်ပါ
    $jobs_list = $stmt->fetchAll();
} catch (PDOException $e) {
    // ... (Error Handling) ...
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__); // manage_jobs.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', 'dashboard.php');
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', 'manage_companies.php');
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', 'manage_students.php');
                echo sidebar_link('manage_jobs.php', 'fa-briefcase', 'manage_jobs', $currentPageFile); // Active
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', 'reports.php');
                echo sidebar_link('settings.php', 'fa-cog', 'settings', 'settings.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['manage_jobs'] ?? 'System Job Listings'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">
                Total Jobs: <?php echo count($jobs_list); ?>
            </h2>
            
            <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead style="background-color: var(--color-bg-main);">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Job Title / Company</th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Type / Deadline</th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider">Company Status</th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                        <?php if (empty($jobs_list)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center">No jobs found in the system.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($jobs_list as $job): 
                            $is_active_company = ($job['company_status'] === 'Yes');
                            $status_color = $is_active_company ? 'bg-green-500' : 'bg-red-500';
                            $deadline_passed = strtotime($job['deadline']) < time() ? 'text-red-500' : 'text-green-500';
                        ?>
                            <tr class="hover:bg-opacity-5 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap font-medium">
                                    <p><?php echo htmlspecialchars($job['title']); ?></p>
                                    <p class="text-xs opacity-70"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <p><?php echo htmlspecialchars($job['type']); ?></p>
                                    <p class="text-xs <?php echo $deadline_passed; ?>">Deadline: <?php echo htmlspecialchars($job['deadline']); ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold text-white <?php echo $status_color; ?>">
                                        <?php echo $is_active_company ? 'Approved' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <div class="flex justify-center space-x-2">
                                        <a href="view_job_details.php?job_id=<?php echo $job['job_id']; ?>" 
                                           class="text-blue-500 hover:text-blue-700" title="View Details" target="_blank">
                                            <i class="fa fa-eye"></i>
                                        </a>

                                        <button type="button" 
                                                onclick="confirmAction(<?php echo $job['job_id']; ?>, 'delete', 'WARNING: Permanently delete job listing?')">
                                            <i class="fa fa-trash text-gray-500 hover:text-red-700"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php foreach ($jobs_list as $job): ?>
                <form method="POST" action="manage_jobs.php" id="action-form-<?php echo $job['job_id']; ?>" style="display: none;">
                    <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                    <input type="hidden" name="action" id="action-<?php echo $job['job_id']; ?>">
                </form>
            <?php endforeach; ?>

        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmAction(jobId, action, confirmText) {
            let iconType = (action === 'delete') ? 'error' : 'warning';
            let confirmButtonColor = (action === 'delete') ? '#dc3545' : '#ffc107';
            
            Swal.fire({
                title: 'Confirm Deletion',
                text: confirmText,
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete Permanently'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formId = `action-form-${jobId}`;
                    const form = document.getElementById(formId);

                    if (form) {
                        form.elements['action'].value = action;
                        form.submit();
                    }
                }
            });
        }
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>