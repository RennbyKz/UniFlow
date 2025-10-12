<?php
// company/view_profile.php
ob_start();
require_once '../core/functions.php'; 
// Company Role Check
init_system('view_company_profile', 'company'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$company_data = [];

try {
    // 1. Company Data ကို ရယူခြင်း
    $stmt = $pdo->prepare("
        SELECT c.*, u.email, u.username
        FROM companies c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $company_data = $stmt->fetch();
    
    if (!$company_data) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: ../logout.php"); ob_end_flush(); exit();
    }

} catch (PDOException $e) {
    set_session_message($lang['error_general_try_again'] . " (Data Fetch Error)", 'error');
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
      <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>  
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__); // profile.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', $currentPageFile); // Active
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="flex justify-between items-center pb-4 mb-6 border-b" style="border-color: var(--color-main);">
                    <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                        <?php echo htmlspecialchars($company_data['name'] ?? 'N/A'); ?> Profile
                    </h1>
                    <a href="profile.php" class="px-4 py-2 rounded-lg text-white font-medium" style="background-color: var(--color-accent);">
                        <i class="fa fa-user-edit mr-2"></i> <?php echo $lang['button_edit_profile'] ?? 'Edit Profile'; ?>
                    </a>
                </div>
                
                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <div class="md:col-span-1">
                        <?php if (!empty($company_data['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($company_data['logo_path']); ?>" alt="Company Logo" class="w-32 h-32 object-contain rounded-md border">
                        <?php else: ?>
                            <div class="w-32 h-32 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center text-gray-500">
                                <i class="fa fa-building text-4xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="md:col-span-2 space-y-2 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong><i class="fa fa-envelope mr-2"></i>HR Email:</strong> <?php echo htmlspecialchars($company_data['hr_contact_email'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-link mr-2"></i>Website:</strong> <a href="<?php echo htmlspecialchars($company_data['website_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);"><?php echo htmlspecialchars($company_data['website_url'] ?? 'N/A'); ?></a></p>
                        <p><strong><i class="fa fa-industry mr-2"></i>Industry:</strong> <?php echo htmlspecialchars($company_data['industry'] ?? 'N/A'); ?></p>
                    </div>
                </div>

                <section class="mb-6 border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-xl font-bold mb-2" style="color: var(--color-text-card);">Company Overview</h2>
                    <p class="text-sm leading-relaxed" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($company_data['description'] ?? 'No company description provided yet.')); ?>
                    </p>
                </section>
                
                <section class="mb-6 border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-xl font-bold mb-2" style="color: var(--color-text-card);">Head Office Location</h2>
                    <p class="text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <i class="fa fa-map-marker-alt mr-2"></i> <?php echo htmlspecialchars($company_data['address'] ?? 'N/A'); ?>
                    </p>
                </section>

                <section class="border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-xl font-bold mb-2" style="color: var(--color-text-card);">Current Job Status</h2>
                    <p class="text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        This section can list the company's currently active jobs and application counts.
                    </p>
                </section>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>