<?php
// admin/reports.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('reports', 'admin'); 

// ------------------------------------------
// Data Aggregation for Reports
// ------------------------------------------

global $pdo;
$reports = [];
$report_error = '';

try {
    // 1. Total Placements by Status (Pie Chart Data)
    $stmt = $pdo->query("
        SELECT status, COUNT(*) AS count
        FROM applications
        GROUP BY status
    ");
    $reports['placement_status'] = $stmt->fetchAll();

    // 2. Top 5 Hiring Companies (Bar Chart Data)
    $stmt = $pdo->query("
        SELECT c.name AS company_name, COUNT(a.application_id) AS hired_count
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN companies c ON j.company_id = c.company_id
        WHERE a.status = 'Selected'
        GROUP BY c.name
        ORDER BY hired_count DESC
        LIMIT 5
    ");
    $reports['top_companies'] = $stmt->fetchAll();

    // 3. Application Trend (Total Applied Jobs Over Time - By Month/Year)
    // Simplified: Total applications by type
    $stmt = $pdo->query("
        SELECT 
            j.type, COUNT(a.application_id) as total
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        GROUP BY j.type
    ");
    $reports['app_by_type'] = $stmt->fetchAll();
    
    // 4. GPA Distribution of Placed Students (Average GPA)
    $stmt = $pdo->query("
        SELECT AVG(s.gpa) AS avg_gpa, COUNT(a.application_id) AS selected_count
        FROM applications a
        JOIN students s ON a.student_id = s.student_id
        WHERE a.status = 'Selected'
    ");
    $reports['avg_gpa_placed'] = $stmt->fetch();


} catch (PDOException $e) {
    $report_error = $lang['error_general_try_again'] . " (Report DB Error): " . $e->getMessage();
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
                $currentPageFile = basename(__FILE__); // reports.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', 'dashboard.php');
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', 'manage_companies.php');
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', 'manage_students.php');
                echo sidebar_link('manage_jobs.php', 'fa-briefcase', 'manage_jobs', 'manage_jobs.php');
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', $currentPageFile); // Active
                echo sidebar_link('settings.php', 'fa-cog', 'settings', 'settings.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['reports'] ?? 'Placement Reports & Analytics'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($report_error)): ?>
                 <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $report_error; ?></p>
                 </div>
            <?php endif; ?>

            <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="card p-6 shadow-lg border" style="border-left: 5px solid #2563eb;">
                    <h3 class="text-lg font-semibold mb-2">Avg. GPA of Placed Students</h3>
                    <p class="text-4xl font-extrabold" style="color: #2563eb;">
                        <?php echo number_format($reports['avg_gpa_placed']['avg_gpa'] ?? 0, 2); ?>
                    </p>
                    <p class="text-sm opacity-70">Based on <?php echo $reports['avg_gpa_placed']['selected_count'] ?? 0; ?> placed students.</p>
                </div>
                
                <div class="card p-6 shadow-lg border" style="border-left: 5px solid #0d9488;">
                    <h3 class="text-lg font-semibold mb-2">Applications by Job Type</h3>
                    <?php foreach($reports['app_by_type'] as $item): ?>
                        <p class="text-sm">
                            <span class="font-medium"><?php echo htmlspecialchars($item['type']); ?>:</span> 
                            <?php echo $item['total']; ?>
                        </p>
                    <?php endforeach; ?>
                </div>

                <div class="card p-6 shadow-lg border" style="border-left: 5px solid #f97316;">
                    <h3 class="text-lg font-semibold mb-2">Total Placements Status</h3>
                    <ul>
                        <?php 
                        foreach($reports['placement_status'] as $status_item):
                            $color = match($status_item['status']) {
                                'Selected' => 'text-green-600',
                                'Rejected' => 'text-red-600',
                                default => 'text-yellow-600',
                            };
                        ?>
                            <li class="flex justify-between text-sm">
                                <span class="<?php echo $color; ?>"><?php echo htmlspecialchars($status_item['status']); ?>:</span>
                                <span class="font-bold"><?php echo $status_item['count']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            
            <section class="mb-10">
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Top Hiring Companies (Selected Students)</h2>
                
                <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead style="background-color: var(--color-bg-main);">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Company Name</th>
                                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Total Hired</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                            <?php foreach ($reports['top_companies'] as $company): ?>
                                <tr class="hover:bg-opacity-5 transition duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap font-medium"><?php echo htmlspecialchars($company['company_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold"><?php echo $company['hired_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>