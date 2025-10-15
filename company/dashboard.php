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
$total_interviewed = 0; // NEW KPI

try {
    global $pdo;
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT c.company_id, u.is_approved FROM companies c JOIN users u ON c.user_id = u.user_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $company_data = $stmt->fetch();
    $company_id = $company_data['company_id'] ?? 0;
    
    // ... (Approval Check Logic) ...

    if ($company_id) {
        $total_jobs = $pdo->query("SELECT COUNT(*) FROM jobs WHERE company_id = {$company_id}")->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT status, COUNT(application_id) AS count FROM applications a JOIN jobs j ON a.job_id = j.job_id WHERE j.company_id = ? GROUP BY status");
        $stmt->execute([$company_id]);
        $application_funnel = $stmt->fetchAll();
        
        foreach($application_funnel as $item) {
            if ($item['status'] === 'Shortlisted') $total_shortlisted = $item['count'];
            if ($item['status'] === 'Interview') $total_interviewed = $item['count'];
            $total_applicants += $item['count']; 
        }
        
        // 💡 Static Data for Chart Demo (Dynamic Data သည် 0 ဖြစ်နေလျှင် ယာယီတန်ဖိုးများ)
        $chart_data_labels = ['Pending', 'Shortlisted', 'Interview', 'Rejected'];
        $chart_data_counts = [
            $total_applicants - $total_shortlisted - $total_interviewed, // Pending Count (Simplified)
            $total_shortlisted,
            $total_interviewed,
            1 // Example Static Reject Count
        ];
    }

} catch (PDOException $e) {
    $dashboard_error = $lang['error_general_try_again'] . " (DB): " . $e->getMessage();
}

// Capacity Calculation (Using total applicants as capacity for shortlisted/interviewed metrics)
$shortlisted_percent = ($total_applicants > 0) ? round($total_shortlisted / $total_applicants * 100) : 0;
$interviewed_percent = ($total_applicants > 0) ? round($total_interviewed / $total_applicants * 100) : 0;

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 

// Stat Card Helper (Design ကို ပြန်လည်သတ်မှတ်)
function create_company_card($title, $value, $icon_class, $progress_percent, $color_var) {
    global $lang;
    $percent = min(100, (int)$progress_percent);
    $title_label = isset($lang[$title]) ? $lang[$title] : $title;

    return <<<HTML
    <div class="card p-6 flex flex-col justify-between shadow-xl transition duration-300 transform hover:shadow-2xl" 
         style="background-color: var(--color-bg-card); border-top: 5px solid {$color_var};">
        
        <div class="flex justify-between items-center mb-4">
            <div class="flex items-center gap-3">
                <i class="fa {$icon_class} text-4xl" style="color: {$color_var};"></i>
                <p class="text-lg font-semibold uppercase" style="color: var(--color-text-card);">{$title_label}</p>
            </div>
            <span class="text-5xl font-extrabold" style="color: var(--color-text-card);">{$value}</span>
        </div>

        <div class="w-full h-2 rounded-full mt-2 overflow-hidden" style="background-color: var(--color-border);">
            <div class="h-full rounded-full" 
                 style="width: {$percent}%; background-color: {$color_var}; transition: width 0.5s;">
            </div>
        </div>
        <p class="text-right text-xs mt-1" style="color: var(--color-text-card); opacity: 0.7;">{$percent}% Review Rate</p>
    </div>
    HTML;
}
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
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', $currentPageFile); // Active
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['company_dashboard']; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($dashboard_error)): ?>
                 <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $dashboard_error; ?></p>
                 </div>
            <?php endif; ?>

            <section class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                
                <?php // Primary Counts ?>
                <?php echo create_company_card('total_jobs_posted', $total_jobs, 'fa-briefcase', $total_jobs + 1, 'var(--color-primary)'); ?>
                <?php echo create_company_card('total_applicants', $total_applicants, 'fa-users', $total_applicants + 1, '#f59e0b'); ?>
                
                <?php // Funnel Metrics ?>
                <?php echo create_company_card('total_shortlisted', $total_shortlisted, 'fa-star', $total_applicants, '#2563eb'); ?>
                <?php echo create_company_card('total_interviewed', $total_interviewed, 'fa-phone-alt', $total_applicants, '#10b981'); ?>
            </section>

            <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="card p-6 shadow-xl border" style="background-color: var(--color-bg-card); border-left: 5px solid var(--color-accent);">
                    <h3 class="text-lg font-bold mb-2">Last Account Activity</h3>
                    <p class="text-xl font-bold text-gray-700 dark:text-gray-300">Oct 15, 2025</p>
                    <p class="text-sm opacity-70">Last login date (Needs separate fetch).</p>
                </div>

                <div class="card p-6 shadow-xl border md:col-span-2">
                    <h3 class="text-xl font-bold mb-4">Application Funnel Breakdown</h3>
                    <div style="max-height: 350px;">
                        <canvas id="funnelChart"></canvas>
                    </div>
                </div>
            </section>
            
            <section class="mt-10">
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-primary);">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="manage_applications.php" class="p-6 rounded-xl shadow-lg transition duration-300 hover:scale-[1.02] flex items-center gap-3" 
                       style="background-color: var(--color-bg-card); border-left: 5px solid #f59e0b;">
                        <i class="fa fa-envelope-open-text text-2xl" style="color: #f59e0b;"></i>
                        <span class="font-semibold" style="color: var(--color-text-card);">Review Applicants</span>
                    </a>
                    <a href="post_job.php" class="p-6 rounded-xl shadow-lg transition duration-300 hover:scale-[1.02] flex items-center gap-3" 
                       style="background-color: var(--color-bg-card); border-left: 5px solid var(--color-accent);">
                        <i class="fa fa-plus-circle text-2xl" style="color: var(--color-accent);"></i>
                        <span class="font-semibold" style="color: var(--color-text-card);">Post New Job</span>
                    </a>
                    <a href="profile.php" class="p-6 rounded-xl shadow-lg transition duration-300 hover:scale-[1.02] flex items-center gap-3" 
                       style="background-color: var(--color-bg-card); border-left: 5px solid var(--color-primary);">
                        <i class="fa fa-user-circle text-2xl" style="color: var(--color-primary);"></i>
                        <span class="font-semibold" style="color: var(--color-text-card);">Update Profile</span>
                    </a>
                </div>
            </section>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data from PHP
            const funnelData = <?php echo json_encode($application_funnel); ?>;
            
            // Extract data for Chart.js
            const labels = funnelData.map(item => item.status);
            const counts = funnelData.map(item => parseInt(item.count));
            
            const ctx = document.getElementById('funnelChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut', 
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: [
                            '#f59e0b',  // Pending (Amber)
                            '#2563eb', // Shortlisted (Blue)
                            '#10b981', // Selected (Green)
                            '#ef4444', // Rejected (Red)
                            '#06b6d4', // Interview (Cyan)
                        ],
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: 'var(--color-text-card)' } },
                        title: { display: false }
                    }
                }
            });
        });
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>