<?php
// admin/view_job_details.php
ob_start(); 
require_once '../core/functions.php'; 
// Admin Role Check
init_system('view_job_details', 'admin'); 

// ------------------------------------------
// Initialization & Data Fetch (Based on Job ID)
// ------------------------------------------

global $pdo;
$job_id = sanitize_input($_GET['job_id'] ?? 0);
$job = [];
$errors = [];

if (!$job_id) {
    set_session_message('error_job_not_found', 'error');
    header("Location: manage_jobs.php"); ob_end_flush(); exit();
}

try {
    // 1. Job, Company Details ကို JOIN တွဲပြီး ရယူခြင်း
    $stmt = $pdo->prepare("
        SELECT j.*, c.name AS company_name, c.logo_path, c.description AS company_description, c.website_url
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.job_id = ? 
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        set_session_message('error_job_not_found', 'error');
        header("Location: manage_jobs.php"); ob_end_flush(); exit();
    }
    
    // 2. Application Count (Admin အတွက်သာ)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?");
    $stmt->execute([$job_id]);
    $total_applicants = $stmt->fetchColumn();

} catch (PDOException $e) {
    $errors[] = $lang['error_general_try_again'] . " (DB Error)";
    set_session_message($errors[0], 'error');
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
        
        <main class="flex-grow p-8 ml-64 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-5xl p-8 rounded-xl shadow-2xl border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="pb-6 mb-6 border-b" style="border-color: var(--color-primary);">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-4xl font-extrabold" style="color: var(--color-primary);">
                                <?php echo htmlspecialchars($job['title']); ?>
                            </h1>
                            <p class="text-lg font-medium" style="color: var(--color-text-card); opacity: 0.8;">
                                Posted by: <?php echo htmlspecialchars($job['company_name']); ?>
                            </p>
                        </div>
                        <span class="px-3 py-1 rounded-full font-semibold text-white text-sm" 
                              style="background-color: var(--color-accent);">
                            <?php echo htmlspecialchars($job['type']); ?>
                        </span>
                    </div>

                    <div class="mt-3 flex justify-between items-center text-sm" style="color: var(--color-text-card); opacity: 0.8;">
                        <p>Applicants: <span class="font-bold text-lg" style="color: #4f46e5;"><?php echo $total_applicants ?? 0; ?></span></p>
                        <p>Deadline: <span class="font-bold text-red-500"><?php echo htmlspecialchars($job['deadline']); ?></span></p>
                    </div>
                </div>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        Job Description
                    </h2>
                    <p class="text-base leading-relaxed whitespace-pre-wrap" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </p>
                </section>
                
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        Required Skills
                    </h2>
                    <div class="flex flex-wrap gap-2 text-sm" style="color: var(--color-text-card);">
                        <?php 
                            $skills = explode(',', $job['required_skills']);
                            foreach ($skills as $skill):
                        ?>
                            <span class="px-3 py-1 rounded-full" style="background-color: var(--color-bg-main); border: 1px solid var(--color-border);">
                                <?php echo trim(htmlspecialchars($skill)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        Posted Company
                    </h2>
                    <p class="text-base leading-relaxed" style="color: var(--color-text-card); opacity: 0.9;">
                        <a href="view_company_profile.php?company_id=<?php echo $job['company_id']; ?>" style="color: var(--color-accent);" class="font-medium hover:underline">
                            <?php echo htmlspecialchars($job['company_name']); ?> Profile Link <i class="fa fa-external-link-alt ml-1"></i>
                        </a>
                    </p>
                </section>

                <div class="mt-6 text-center">
                    <a href="manage_jobs.php" class="px-6 py-3 rounded-lg text-white font-semibold" style="background-color: #6c757d;">
                        <i class="fa fa-arrow-left mr-2"></i> Back to Job List
                    </a>
                </div>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>