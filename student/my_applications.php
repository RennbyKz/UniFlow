<?php
// student/my_applications.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('my_applications', 'student'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$applications_list = [];

try {
    // Student ID ကို session မှ ရယူ
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_id = $stmt->fetchColumn();

    if (!$student_id) { 
        set_session_message('profile_data_not_found', 'error');
        header("Location: dashboard.php"); ob_end_flush(); exit(); 
    }
    
    // 💡 SQL Query: Applications, Jobs, and Company Names များကို တစ်ပြိုင်နက်တည်း ယူခြင်း
    $stmt = $pdo->prepare("
        SELECT 
            a.status, 
            a.applied_at,
            j.title AS job_title, 
            j.type AS job_type,
            c.name AS company_name,
            c.logo_path
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN companies c ON j.company_id = c.company_id
        WHERE a.student_id = ?
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([$student_id]);
    $applications_list = $stmt->fetchAll();

} catch (PDOException $e) {
    set_session_message($lang['error_general_try_again'] . " (DB Error)", 'error');
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg">
                
                <h1 class="text-3xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-main); border-color: var(--color-border);">
                    <?php echo $lang['my_applications'] ?? 'Application Tracking Center'; ?>
                </h1>
                
                <?php display_session_messages($lang); ?>

                <?php if (empty($applications_list)): ?>
                    <div class="p-6 text-center" style="color: var(--color-text-card); opacity: 0.8;">
                        <i class="fa fa-info-circle text-4xl mb-4" style="color: var(--color-accent);"></i>
                        <p class="text-xl"><?php echo $lang['no_applications'] ?? 'You have not submitted any applications yet.'; ?></p>
                        <a href="view_jobs.php" class="mt-4 inline-block font-medium" style="color: var(--color-primary);">
                            <?php echo $lang['browse_jobs'] ?? 'Browse Available Jobs'; ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($applications_list as $app): ?>
                        
                        <?php 
                            // Status visualization
                            $status_color = match($app['status']) {
                                'Selected' => '#10b981', // Green
                                'Shortlisted' => '#2563eb', // Blue
                                'Rejected' => '#ef4444', // Red
                                default => '#f59e0b', // Pending (Amber)
                            };
                            $status_label = htmlspecialchars($app['status']);
                        ?>
                        
                        <div class="card p-5 shadow-md border-l-4" style="border-color: <?php echo $status_color; ?>; background-color: var(--color-bg-card);">
                            
                            <div class="flex justify-between items-center mb-3">
                                
                                <div class="flex items-center gap-3">
                                    <?php if ($app['logo_path']): ?>
                                        <img src="../<?php echo htmlspecialchars($app['logo_path']); ?>" alt="Logo" class="w-10 h-10 object-contain rounded-full border">
                                    <?php endif; ?>
                                    
                                    <div>
                                        <h3 class="text-xl font-bold" style="color: var(--color-text-card);"><?php echo htmlspecialchars($app['job_title']); ?></h3>
                                        <p class="text-sm opacity-80" style="color: var(--color-text-card);"><?php echo htmlspecialchars($app['company_name']); ?></p>
                                    </div>
                                </div>
                                
                                <span class="px-3 py-1 rounded-full text-xs font-semibold text-white" 
                                      style="background-color: <?php echo $status_color; ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </div>
                            
                            <div class="text-sm border-t pt-3 mt-3" style="border-color: var(--color-border); color: var(--color-text-card); opacity: 0.8;">
                                <p><strong><?php echo $lang['label_job_type'] ?? 'Type'; ?>:</strong> <?php echo htmlspecialchars($app['job_type']); ?></p>
                                <p><strong><?php echo $lang['label_applied_on'] ?? 'Applied On'; ?>:</strong> <?php echo date('M d, Y', strtotime($app['applied_at'])); ?></p>
                            </div>

                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
                    <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); height: 100%; position: sticky; top: 60px;">
               
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-text-card);">Profile Status</h2>          
            <nav class="mt-8 space-y-3 border-t pt-4" style="border-color: var(--color-border);">
                <?php
                // 💡 Quick Links for navigation
                echo sidebar_link('profile.php', 'fa-user-circle', 'student_profile', 'profile.php');
                echo sidebar_link('my_applications.php', 'fa-file-alt', 'my_applications', 'my_applications.php');
                echo sidebar_link('view_jobs.php', 'fa-briefcase', 'browse_jobs', 'view_jobs.php');
                echo sidebar_link('cv_upload.php', 'fa-upload', 'upload_resume', 'cv_upload.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>                     
        </main>
        
        </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>