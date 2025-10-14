<?php
// admin/view_company_profile.php
ob_start();
require_once '../core/functions.php'; 
// Admin နဲ့ Company နှစ်ခုလုံးကြည့်နိုင်အောင် Role Check ကို Logic တွင် ထားရှိသည်
init_system('view_company_profile', null); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$company_id_to_view = sanitize_input($_GET['company_id'] ?? 0);
$company_data = [];

// 1. Access Control Check (Must be Admin OR Logged-in Company)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'company')) {
    set_session_message('invalid_role', 'error');
    header("Location: ../index.php"); ob_end_flush(); exit();
}

if (!$company_id_to_view) {
    set_session_message('error_general_try_again', 'error');
    header("Location: manage_companies.php"); 
    ob_end_flush();
    exit();
}

try {
    // 2. Company Data ကို ရယူခြင်း
    $stmt = $pdo->prepare("
        SELECT c.*, u.email, u.is_approved
        FROM companies c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.company_id = ?
    ");
    $stmt->execute([$company_id_to_view]);
    $company_data = $stmt->fetch();
    
    if (!$company_data) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: manage_companies.php"); ob_end_flush(); exit();
    }

} catch (PDOException $e) {
    set_session_message($lang['error_general_try_again'] . " (DB Error)", 'error');
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
                $currentPageFile = basename(__FILE__);
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', 'dashboard.php');
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', 'manage_companies.php');
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', $currentPageFile); // Active
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', 'reports.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main); padding-left: 350px;">
            
            <div class="w-full max-w-4xl p-8 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="pb-4 mb-6 border-b flex justify-between items-center" style="border-color: var(--color-primary);">
                    <div>
                        <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                            <?php echo htmlspecialchars($company_data['name'] ?? 'N/A'); ?> Profile
                        </h1>
                        <p class="text-lg font-medium" style="color: var(--color-text-card); opacity: 0.8;">
                            Industry: <?php echo htmlspecialchars($company_data['industry'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full font-semibold text-white text-sm" 
                          style="background-color: <?php echo ($company_data['is_approved'] === 'Yes') ? '#10b981' : '#ef4444'; ?>;">
                        <?php echo ($company_data['is_approved'] === 'Yes') ? 'Active' : 'Pending/Disabled'; ?>
                    </span>
                </div>
                
                <section class="mb-6 border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-xl font-bold mb-2" style="color: var(--color-text-card);">Company Overview</h2>
                    <p class="text-sm leading-relaxed" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($company_data['description'] ?? 'No company description provided yet.')); ?>
                    </p>
                </section>

                <section class="mb-6 border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-xl font-bold mb-3" style="color: var(--color-text-card);">Contact & Branding</h2>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <p class="col-span-2"><strong><i class="fa fa-envelope mr-2"></i>HR Email:</strong> <?php echo htmlspecialchars($company_data['hr_contact_email'] ?? 'N/A'); ?></p>
                        <p class="col-span-2"><strong><i class="fa fa-map-marker-alt mr-2"></i>Address:</strong> <?php echo htmlspecialchars($company_data['address'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-link mr-2"></i>Website:</strong> <a href="<?php echo htmlspecialchars($company_data['website_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);"><?php echo htmlspecialchars($company_data['website_url'] ?? 'N/A'); ?></a></p>
                    </div>
                </section>

                <section class="border-t pt-4" style="border-color: var(--color-border);">
                    <a href="../company/manage_jobs.php" class="font-medium" style="color: var(--color-primary);">View All Jobs Posted by this Company</a>
                </section>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>