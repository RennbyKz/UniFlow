<?php
// company/manage_applications.php
ob_start(); 
require_once '../core/functions.php'; 
// Company Role Check
init_system('manage_applications', 'company'); 

// ------------------------------------------
// 1. Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];
$company_id = 0;
$applications_list = [];
$jobs_posted = []; // Dropdown filter အတွက်

// Search and Filter parameters
$filter_job_id = sanitize_input($_GET['job_id'] ?? '');
$filter_status = sanitize_input($_GET['status'] ?? '');

try {
    // 1. Company ID ကို ရယူခြင်း
    $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $company_id = $stmt->fetchColumn();

    if (!$company_id) {
        // Approval check ပြီးသားဖြစ်သော်လည်း DB Error ရှိပါက
        set_session_message('error_general_try_again', 'error');
        header("Location: dashboard.php"); ob_end_flush(); exit();
    }
    
    // 2. Company ၏ တင်ထားသော Job များကို ရယူခြင်း (Filter Dropdown အတွက်)
    $stmt = $pdo->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY posted_date DESC");
    $stmt->execute([$company_id]);
    $jobs_posted = $stmt->fetchAll();

    // 3. SQL Query Construction (Applications List)
    $sql = "
        SELECT 
            a.application_id, a.status, a.applied_at,
            j.title AS job_title, 
            s.full_name AS student_name,
            s.student_id
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN students s ON a.student_id = s.student_id
        WHERE j.company_id = :company_id
    ";
    $params = [':company_id' => $company_id];
    $where_clauses = [];

    // Job ID Filter
    if (!empty($filter_job_id)) {
        $where_clauses[] = "j.job_id = :job_id";
        $params[':job_id'] = $filter_job_id;
    }

    // Status Filter
    if (!empty($filter_status)) {
        $where_clauses[] = "a.status = :status";
        $params[':status'] = $filter_status;
    }

    if (!empty($where_clauses)) {
        $sql .= " AND " . implode(' AND ', $where_clauses);
    }
    
    $sql .= " ORDER BY a.applied_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = $lang['error_general_try_again'] . " (DB Error): " . $e->getMessage();
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
                $currentPageFile = basename(__FILE__); // manage_applications.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', $currentPageFile); // Active
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-main); border-color: var(--color-border);">
                <?php echo $lang['manage_applications'] ?? 'Manage Applications'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="p-6 rounded-xl shadow-lg border mb-8" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <form method="GET" action="manage_applications.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div class="md:col-span-2">
                        <label for="job_id" class="block font-medium mb-1"><?php echo $lang['label_filter_job'] ?? 'Filter by Job Title:'; ?></label>
                        <select id="job_id" name="job_id" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- All Jobs --</option>
                            <?php foreach ($jobs_posted as $job): ?>
                                <option value="<?php echo $job['job_id']; ?>" <?php echo ($filter_job_id == $job['job_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block font-medium mb-1"><?php echo $lang['label_filter_status'] ?? 'Status:'; ?></label>
                        <select id="status" name="status" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- All Statuses --</option>
                            <option value="Pending" <?php echo ($filter_status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Shortlisted" <?php echo ($filter_status == 'Shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                            <option value="Interview" <?php echo ($filter_status == 'Interview') ? 'selected' : ''; ?>>Interview</option>
                            <option value="Selected" <?php echo ($filter_status == 'Selected') ? 'selected' : ''; ?>>Selected</option>
                            <option value="Rejected" <?php echo ($filter_status == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button-primary px-6 py-2 rounded-lg font-semibold" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-filter mr-2"></i> <?php echo $lang['button_filter'] ?? 'Filter'; ?>
                    </button>
                </form>
            </div>
            
            <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">
                Total Applications (<?php echo count($applications_list); ?>)
            </h2>
            
            <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead style="background-color: var(--color-bg-main);">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_applicant'] ?? 'Applicant'; ?></th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_job_title'] ?? 'Job Title'; ?></th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_applied_on'] ?? 'Applied On'; ?></th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_status'] ?? 'Status'; ?></th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_action'] ?? 'Review'; ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                        <?php if (empty($applications_list)): ?>
                            <tr><td colspan="5" class="px-6 py-4 text-center"><?php echo $lang['no_applications_found'] ?? 'No applications found based on filters.'; ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($applications_list as $app): ?>
                                <tr class="hover:bg-opacity-5 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium"><?php echo htmlspecialchars($app['student_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($app['job_title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo date('Y-m-d', strtotime($app['applied_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold text-white" 
                                              style="background-color: <?php echo match($app['status']) {
                                                  'Selected' => '#10b981', 'Shortlisted' => '#2563eb', 'Rejected' => '#ef4444', default => '#f59e0b',
                                              }; ?>;">
                                            <?php echo htmlspecialchars($app['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <a href="review_applicant.php?application_id=<?php echo $app['application_id']; ?>" 
                                           class="text-sm px-3 py-1 rounded-md text-white font-semibold" style="background-color: var(--color-primary);">
                                            <?php echo $lang['button_review'] ?? 'Review'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>