<?php
// admin/dashboard.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('admin_dashboard', 'admin'); 

// ------------------------------------------
// 1. DATA AGGREGATION FOR REPORTS & KPIS
// ------------------------------------------

global $pdo;
$reports = [];
$total_students = 0;
$approved_companies = 0;
$pending_companies = 0;
$total_jobs = 0;
$recent_logins = 0;
$missing_resumes = 0;
$overall_avg_gpa = 0;
$dashboard_error = '';

try {
    // Basic Counts
    $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $approved_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_approved = 'Yes'")->fetchColumn();
    $pending_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_approved = 'No'")->fetchColumn();
    $total_jobs = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
    
    // KPI 1: Users Online (Last 24 Hrs)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $recent_logins = $stmt->fetchColumn();

    // KPI 2: Missing Resume Count (Data Quality)
    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE resume_path IS NULL OR resume_path = ''");
    $missing_resumes = $stmt->fetchColumn();

    // KPI 3: Overall Average GPA 
    $stmt = $pdo->query("SELECT AVG(gpa) FROM students WHERE gpa IS NOT NULL AND gpa > 0");
    $overall_avg_gpa = $stmt->fetchColumn();

    // WIDGET 1: Placement Status Breakdown (For Doughnut Chart)
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS count
        FROM applications
        GROUP BY status
    ");
    $reports['placement_status'] = $stmt->fetchAll();
    
    // WIDGET 2: Latest 5 Applications
    $stmt = $pdo->query("
        SELECT 
            a.status, a.applied_at,
            j.title AS job_title, 
            s.full_name AS student_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN students s ON a.student_id = s.student_id
        ORDER BY a.applied_at DESC
        LIMIT 5
    ");
    $reports['latest_applications'] = $stmt->fetchAll();

    // WIDGET 3: Application Trend (Last 3 Months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(applied_at, '%Y-%m') AS month_year, 
            COUNT(*) AS total_applications
        FROM applications
        WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY month_year
        ORDER BY month_year ASC
    ");
    $reports['monthly_trend'] = $stmt->fetchAll();

} catch (PDOException $e) {
    // 💡 ERROR LOGGING
    error_log("Admin Dashboard Data Fetch Failed: " . $e->getMessage());
    $dashboard_error = $lang['error_general_try_again'] . " (Database connection/query failure)";
}

// ------------------------------------------
// 2. HELPER FUNCTIONS (Stat Card - Should be defined in functions.php)
// ------------------------------------------

function create_stat_card($title, $value, $color_hex, $icon_class) {
    global $lang;
    $title_label = isset($lang[$title]) ? $lang[$title] : $title;

    return <<<HTML
    <div class="p-6 rounded-xl shadow-lg transition duration-300 transform hover:scale-[1.02] min-w-full" 
         style="background-color: var(--color-bg-card); border-left: 5px solid {$color_hex};">
        <div class="flex justify-between items-center">
            <p class="text-sm font-medium uppercase" style="color: {$color_hex};">{$title_label}</p>
            <i class="fa {$icon_class} text-3xl" style="color: {$color_hex};"></i>
        </div>
        <div class="mt-1 text-4xl font-extrabold" style="color: var(--color-text-card);">{$value}</div>
    </div>
    HTML;
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
                $currentPageFile = basename(__FILE__); // dashboard.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', $currentPageFile); // Active
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', 'manage_companies.php');
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', 'manage_students.php');
                echo sidebar_link('manage_jobs.php', 'fa-briefcase', 'manage_jobs', 'manage_jobs.php');
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', 'reports.php');
                echo sidebar_link('settings.php', 'fa-cog', 'settings', 'settings.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['admin_dashboard']; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($dashboard_error)): ?>
                 <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $dashboard_error; ?></p>
                 </div>
            <?php endif; ?>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-6 mb-10">
                
                <?php // Row 1: Core Counts ?>
                <?php echo create_stat_card('total_students', $total_students, '#4f46e5', 'fa-graduation-cap'); ?>
                <?php echo create_stat_card('approved_companies', $approved_companies, '#10b981', 'fa-building'); ?>
                <?php echo create_stat_card('pending_approval', $pending_companies, '#f59e0b', 'fa-hourglass-half'); ?>
                <?php echo create_stat_card('total_jobs', $total_jobs, '#06b6d4', 'fa-briefcase'); ?>

                <?php // Row 2: Analytics & Quality Checks ?>
                <?php echo create_stat_card('missing_resumes', $missing_resumes, '#ef4444', 'fa-exclamation-triangle'); ?>
                <?php echo create_stat_card('overall_avg_gpa', number_format($overall_avg_gpa ?? 0, 2), '#9333ea', 'fa-star'); ?>
                <?php echo create_stat_card('recent_logins', $recent_logins, '#ff007f', 'fa-globe'); ?>
                
            </section>
            
            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="card p-6 shadow-xl border">
                    <h3 class="text-xl font-bold mb-4">Application Status Breakdown</h3>
                    <div style="max-height: 350px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <div class="card p-6 shadow-xl border">
                    <h3 class="text-xl font-bold mb-4">Application Trend (Last 3 Months)</h3>
                    <div style="max-height: 350px;">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
                
                <div class="card p-6 shadow-xl border md:col-span-2">
                    <h3 class="text-xl font-bold mb-4">Recent Application Activity</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead style="background-color: var(--color-bg-sidebar);">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider">Applicant</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider">Job Title</th>
                                    <th class="px-3 py-2 text-center text-xs font-bold uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                                <?php if (empty($reports['latest_applications'])): ?>
                                    <tr><td colspan="3" class="px-3 py-4 text-center">No recent applications found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($reports['latest_applications'] as $app): 
                                        $status_color = match($app['status']) {
                                            'Pending' => '#f59e0b', 'Shortlisted' => '#2563eb', 'Selected' => '#10b981', 'Rejected' => '#ef4444', default => '#f59e0b',
                                        };
                                    ?>
                                    <tr class="hover:bg-opacity-5 transition duration-150">
                                        <td class="px-3 py-2 text-sm font-medium"><?php echo htmlspecialchars($app['student_name']); ?></td>
                                        <td class="px-3 py-2 text-sm opacity-80"><?php echo htmlspecialchars($app['job_title']); ?></td>
                                        <td class="px-3 py-2 text-center text-xs">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold text-white" 
                                                  style="background-color: <?php echo $status_color; ?>;">
                                                <?php echo htmlspecialchars($app['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data from PHP for Doughnut Chart
            const statusData = <?php echo json_encode($reports['placement_status']); ?>;
            const labels = statusData.map(item => item.status);
            const counts = statusData.map(item => item.count);
            
            const ctx = document.getElementById('statusChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut', 
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: [
                            '#10b981', // Selected (Green)
                            '#ef4444', // Rejected (Red)
                            '#2563eb', // Interview/Shortlisted (Blue)
                            '#f59e0b'  // Pending (Amber)
                        ],
                        hoverOffset: 4
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

            // ------------------------------------------
            // Monthly Trend Line Chart
            // ------------------------------------------
            const monthlyTrendData = <?php echo json_encode($reports['monthly_trend']); ?>;
            const monthlyLabels = monthlyTrendData.map(item => {
                const date = new Date(item.month_year + '-01');
                return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
            });
            const monthlyCounts = monthlyTrendData.map(item => parseInt(item.total_applications));
            
            const trendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Total Applications',
                        data: monthlyCounts,
                        backgroundColor: 'rgba(79, 70, 229, 0.4)', // Primary color with opacity
                        borderColor: 'var(--color-primary)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Applications Count',
                                color: 'var(--color-text-card)'
                            },
                            ticks: {
                                color: 'var(--color-text-card)'
                            }
                        },
                        x: {
                            ticks: {
                                color: 'var(--color-text-card)'
                            }
                        }
                    }
                }
            });
        });
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>