<?php
// student/company_profile.php
ob_start(); 
require_once '../core/functions.php'; 
// Student Role Check
init_system('view_company_profile', 'student'); 

// ------------------------------------------
// Initialization & Data Fetch (Based on company_id)
// ------------------------------------------

global $pdo;
$company_id_to_view = sanitize_input($_GET['company_id'] ?? 0);
$company_data = [];
$jobs_list = [];
$errors = [];

if (!$company_id_to_view) {
    set_session_message('error_general_try_again', 'error');
    header("Location: view_jobs.php"); // Job List သို့ ပြန်ပို့
    ob_end_flush();
    exit();
}

try {
    // 1. Company Data ကို ရယူခြင်း (Logo, Description, Contact Info)
    $stmt = $pdo->prepare("
        SELECT c.*, u.email 
        FROM companies c 
        JOIN users u ON c.user_id = u.user_id
        WHERE c.company_id = ? AND c.is_approved = 'Yes' -- Approved company ကိုသာ ပြသမည်
    ");
    $stmt->execute([$company_id_to_view]);
    $company_data = $stmt->fetch();

    if (!$company_data) {
        set_session_message('error_company_not_found', 'error');
        header("Location: view_jobs.php"); 
        ob_end_flush(); exit();
    }
    
    // 2. Company က တင်ထားသော Active Jobs များကို ရယူခြင်း
    $stmt = $pdo->prepare("
        SELECT job_id, title, type, location, posted_date
        FROM jobs
        WHERE company_id = ? AND deadline >= CURDATE()
        ORDER BY posted_date DESC LIMIT 5
    ");
    $stmt->execute([$company_id_to_view]);
    $jobs_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = $lang['error_general_try_again'] . " (DB Error)";
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-5xl p-8 rounded-xl shadow-2xl border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="pb-6 mb-6 border-b" style="border-color: var(--color-primary);">
                    <div class="flex items-center gap-5">
                        <?php if (!empty($company_data['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($company_data['logo_path']); ?>" alt="Company Logo" class="w-20 h-20 object-contain rounded-md border shadow-sm">
                        <?php else: ?>
                            <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center text-gray-500">
                                <i class="fa fa-building text-3xl"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h1 class="text-4xl font-extrabold" style="color: var(--color-primary);">
                                <?php echo htmlspecialchars($company_data['name'] ?? 'N/A'); ?>
                            </h1>
                            <p class="text-lg font-medium" style="color: var(--color-text-card); opacity: 0.8;">
                                Industry: <?php echo htmlspecialchars($company_data['industry'] ?? 'N/A'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <section class="mb-8">
                    <h2 class="text-2xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        Company Overview
                    </h2>
                    <p class="text-base leading-relaxed whitespace-pre-wrap" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($company_data['description'] ?? 'No official company description provided.')); ?>
                    </p>
                </section>
                
                <section class="mb-8 border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-2xl font-bold mb-3" style="color: var(--color-text-card);">Contact & Links</h2>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong><i class="fa fa-map-marker-alt mr-2"></i>Location:</strong> <?php echo htmlspecialchars($company_data['address'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-link mr-2"></i>Website:</strong> <a href="<?php echo htmlspecialchars($company_data['website_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);"><?php echo htmlspecialchars($company_data['website_url'] ?? 'N/A'); ?></a></p>
                        <p class="col-span-2 text-red-500 font-medium"><i class="fa fa-envelope mr-2"></i>HR Email address is kept private for security purposes.</p>
                    </div>
                </section>

                <section class="border-t pt-4" style="border-color: var(--color-border);">
                    <h2 class="text-2xl font-bold mb-3" style="color: var(--color-text-card);">
                        Active Job Openings (<?php echo count($jobs_list); ?>)
                    </h2>
                    
                    <?php if (empty($jobs_list)): ?>
                        <p class="text-lg opacity-80">This company has no active job listings at the moment.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($jobs_list as $job): ?>
                                <div class="p-4 rounded-lg border flex justify-between items-center" style="background-color: var(--color-bg-main); border-left: 5px solid var(--color-accent);">
                                    <div>
                                        <a href="job_details.php?job_id=<?php echo $job['job_id']; ?>" class="font-semibold text-lg hover:underline" style="color: var(--color-primary);"><?php echo htmlspecialchars($job['title']); ?></a>
                                        <p class="text-xs opacity-80"><?php echo htmlspecialchars($job['location']); ?> | Type: <?php echo htmlspecialchars($job['type']); ?></p>
                                    </div>
                                    <a href="apply_job.php?job_id=<?php echo $job['job_id']; ?>" class="button-primary px-3 py-1 rounded-lg text-white text-sm" style="background-color: var(--color-accent);">Apply</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">
               
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Quick Navigation</h2>
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
                <a href="view_jobs.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-primary);">
                    <i class="fa fa-arrow-left w-5"></i><span>Back to Job Listings</span>
                </a>
            </nav>
        </aside>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>