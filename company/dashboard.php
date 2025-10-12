<?php
// company/dashboard.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('company_dashboard', 'company'); 

// ... (Access Control Logic - Approval Check) ...

// ------------------------------------------
// Data Fetching for Dashboard Summary
// ------------------------------------------
$company_id = 0; $total_jobs = 0; $total_applicants = 0; $total_shortlisted = 0; $dashboard_error = '';

try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company_id = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $total_jobs = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(application_id) FROM applications a
        JOIN jobs j ON a.job_id = j.job_id WHERE j.company_id = ?
    ");
    $stmt->execute([$company_id]);
    $total_applicants = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(application_id) FROM applications a
        JOIN jobs j ON a.job_id = j.job_id WHERE j.company_id = ? AND a.status = 'Shortlisted'
    ");
    $stmt->execute([$company_id]);
    $total_shortlisted = $stmt->fetchColumn();

} catch (PDOException $e) {
    $dashboard_error = $lang['error_general_try_again'] . " (DB): " . $e->getMessage();
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 

// 💡 Stat Card Helper (Design အသစ်)
function create_company_card($title, $value, $icon_class, $progress_percent, $color_var) {
    global $lang;
    $percent = min(100, (int)$progress_percent);
    
    return <<<HTML
    <div class="card p-6 flex flex-col justify-between shadow-xl transition duration-300 transform hover:shadow-2xl" 
         style="background-color: var(--color-bg-card); border-top: 5px solid {$color_var};">
        
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-3">
                <i class="fa {$icon_class} text-4xl" style="color: {$color_var};"></i>
                <p class="text-lg font-semibold uppercase" style="color: var(--color-text-card);">{$title}</p>
            </div>
            <span class="text-5xl font-extrabold" style="color: var(--color-text-card);">{$value}</span>
        </div>

        <div class="w-full h-2 rounded-full mt-2 overflow-hidden" style="background-color: var(--color-border);">
            <div class="h-full rounded-full" 
                 style="width: {$percent}%; background-color: {$color_var}; transition: width 0.5s;">
            </div>
        </div>
        <p class="text-right text-xs mt-1" style="color: var(--color-text-card); opacity: 0.7;">{$percent}% Capacity</p>
    </div>
    HTML;
}

// Data တွက်ချက်မှု
$applicant_percent = ($total_jobs > 0) ? round(($total_applicants / $total_jobs) * 100) : 0;
$shortlisted_percent = ($total_applicants > 0) ? round(($total_shortlisted / $total_applicants) * 100) : 0;
?>

    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-company-main);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__);
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', $currentPageFile);
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">
                <?php echo $lang['company_dashboard']; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($dashboard_error)): ?>
                 <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $dashboard_error; ?></p>
                 </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
                <?php
                echo create_company_card($lang['total_jobs_posted'], $total_jobs, 'fa-briefcase', $applicant_percent, 'var(--color-company-main)');
                echo create_company_card($lang['total_applicants'], $total_applicants, 'fa-users', $shortlisted_percent, '#f59e0b'); // Amber
                echo create_company_card($lang['total_shortlisted'], $total_shortlisted, 'fa-heart', 50, '#ef4444'); // Red
                ?>
            </div>

           
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>