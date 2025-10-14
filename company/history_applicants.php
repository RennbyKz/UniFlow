<?php
// company/history_applicants.php
ob_start();
require_once '../core/functions.php';
init_system('history_applicants', 'company');

global $pdo;
$user_id = $_SESSION['user_id'];
$company_id = 0;
$history_list = [];

try {
    // Get company_id for the logged-in user
    $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $company_id = $stmt->fetchColumn();

    if (!$company_id) {
        set_session_message('error_general_try_again', 'error');
        header("Location: dashboard.php");
        ob_end_flush();
        exit();
    }

    // Fetch selected and rejectect from application_history table
    $stmt = $pdo->prepare("
        SELECT 
        a.application_id, a.status, a.applied_at, a.interview_date, a.reason,
        j.title AS job_title, 
        s.full_name AS student_name,
        s.student_id
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN students s ON a.student_id = s.student_id
    WHERE j.company_id = :company_id
    AND a.status IN ('Selected', 'Rejected','Interview')
        
    ");
    $stmt->execute([$company_id]);
    $history_list = $stmt->fetchAll();

} catch (PDOException $e) {
    set_session_message('Database Error: ' . $e->getMessage(), 'error');
}

include '../templates/header.php';
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">

<?php include_utility_bar(); ?>
<!-- ✅ Toast container -->
<div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
<div class="flex flex-1" style="margin-top: 60px;">

    <!-- Sidebar -->
    <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
           style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
        <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
        <nav class="space-y-2">
            <?php
            $currentPageFile = basename(__FILE__); // history_applicants.php
            echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
            echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
            echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
            echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
            echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
            ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
        <div class="flex justify-between items-center mb-4">
        <h1 class="text-3xl font-extrabold mb-6 pb-2 border-b" style="color: var(--color-main); border-color: var(--color-border);">
            <?php echo $lang['application_history'] ?? 'Application History'; ?>
        </h1>
        <a href="manage_applications.php" 
                class="text-sm px-4 py-2 bg-gray-800 text-white rounded-md hover:bg-gray-700 flex items-center">
                    <i class="fa fa-history mr-2"></i> <?php echo $lang['button_manage_history'] ?? 'Back To Applicants List'; ?>
        </a>
        </div>
        <?php display_session_messages($lang); ?>

        <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
            <table class="min-w-full divide-y divide-gray-200">
                <thead style="background-color: var(--color-bg-main);">
                    <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_applicant'] ?? 'Applicant'; ?></th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_job_title'] ?? 'Job Title'; ?></th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_applied_on'] ?? 'Applied On'; ?></th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_interview_date'] ?? 'Interview Date'; ?></th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_status'] ?? 'Status'; ?></th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_action'] ?? 'Review'; ?></th>
                            <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_reason'] ?? 'Reason'; ?></th>

                    </tr>
                </thead>
                <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                    <?php if (empty($history_list)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center">No applications found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history_list as $item): ?>
                            <tr>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($item['student_name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($item['job_title']); ?></td>
                                <td class="px-6 py-4"><?php echo date('Y-m-d H:i', strtotime($item['applied_at'])); ?></td>
                                <td class="px-6 py-4"><?php 
                                    if ($item['interview_date']) {
                                        echo date('Y-m-d H:i', strtotime($item['interview_date'])); 
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php
                                    $status_color = 'gray';
                                    if ($item['status'] === 'Selected') {
                                        $status_color = 'green';
                                    } elseif ($item['status'] === 'Rejected') {
                                        $status_color = 'red';
                                    } elseif ($item['status'] === 'Under Review') {
                                        $status_color = 'yellow';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800">
                                        <?php echo htmlspecialchars($item['status']); ?>
                                    </span>
                                    <td class="px-6 py-4 text-center text-sm font-medium">
                                        <a href="review_applicant.php?application_id=<?php echo $item['application_id']; ?>" 
                                        class="text-sm px-3 py-1 rounded-md text-white font-semibold" style="background-color: var(--color-primary);">
                                            <?php echo $lang['button_review'] ?? 'Review'; ?>
                                        </a>
                                    </td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($item['reason']); ?></td>
                                <td class="px-6 py-4 text-center">
                                <button class="delete-btn text-red-600 hover:text-red-800" 
                                        data-id="<?php echo $item['application_id']; ?>" 
                                        title="Delete Application">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteButtons = document.querySelectorAll('.delete-btn');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const appId = this.getAttribute('data-id');

            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it (POST method)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete_application.php';

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'application_id';
                    input.value = appId;

                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
});
</script>
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>
