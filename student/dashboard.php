<?php
// student/dashboard.php
ob_start(); 
require_once '../core/functions.php'; 
// စနစ်ကို စတင်ပါ (Student Role လိုအပ်)
init_system('student_dashboard', 'student'); 

// ------------------------------------------
// 1. DATA FETCHING FOR DASHBOARD KPIS
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$student_data = [];
$total_applied = 0; $shortlisted_count = 0; $unread_notifications = 0;
$profile_complete_percent = 0; $dashboard_error = '';
$application_funnel = []; // For Chart Data

try {
    // 1. Student Data (Profile Health, Name, Major, Resume Path)
    $stmt = $pdo->prepare("
        SELECT student_id, full_name, gpa, major, resume_path, photo_path, contact_phone, address, linkedin_url
        FROM students 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student_data = $stmt->fetch();
    
    if ($student_data) {
        $student_id = $student_data['student_id'];
        
        // Applications Counts & Funnel Data
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $total_applied = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'Shortlisted'");
        $stmt->execute([$student_id]);
        $shortlisted_count = $stmt->fetchColumn();

        // Unread Notifications Count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND is_read = FALSE");
        $stmt->execute([$student_id]);
        $unread_notifications = $stmt->fetchColumn();
        
        // Application Funnel Data for Chart
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM applications WHERE student_id = ? GROUP BY status");
        $stmt->execute([$student_id]);
        $application_funnel = $stmt->fetchAll();


        // 3. Profile Completion Percentage (10 items simplified check)
        $total_items = 10; // Example count of essential fields
        $filled_count = 0;
        // Simple checks for profile completeness
        if (!empty($student_data['full_name'])) $filled_count++;
        if (!empty($student_data['major'])) $filled_count++;
        if (!empty($student_data['gpa'])) $filled_count++;
        if (!empty($student_data['contact_phone'])) $filled_count++; 
        if (!empty($student_data['linkedin_url'])) $filled_count++; 
        if (!empty($student_data['resume_path'])) $filled_count++; 
        if (!empty($student_data['profile_picture_path'])) $filled_count++;
        $filled_count += 3; // Placeholder for fixed fields like User ID, Email, etc.
        
        $profile_complete_percent = round(($filled_count / $total_items) * 100);
    }

} catch (PDOException $e) {
    error_log("Student Dashboard DB Error: " . $e->getMessage());
    $dashboard_error = $lang['error_general_try_again'] . " (DB): " . $e->getMessage();
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 

// 💡 New Helper: Ring Chart SVG (Dashboard မှ ကူးယူခြင်း)
function get_ring_chart_svg($percent, $color_hex) {
    $circumference = 2 * M_PI * 45; // Radius 45 for stroke-width 10
    $offset = $circumference - ($percent / 100 * $circumference);
    
    return <<<HTML
    <svg width="100" height="100" viewBox="0 0 100 100" class="transform -rotate-90">
        <circle cx="50" cy="50" r="45" fill="transparent" stroke="var(--color-border)" stroke-width="10"></circle>
        <circle cx="50" cy="50" r="45" fill="transparent" stroke="{$color_hex}" stroke-width="10"
                stroke-dasharray="{$circumference} {$circumference}" 
                stroke-dashoffset="{$offset}" style="transition: stroke-dashoffset 0.5s;">
        </circle>
    </svg>
    HTML;
}
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['student_dashboard'] ?? 'Student Dashboard'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($dashboard_error)): ?>
                 <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $dashboard_error; ?></p>
                 </div>
            <?php endif; ?>

            <section class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <div class="card p-6 flex flex-col items-center justify-center shadow-lg" style="border-left: 5px solid #2563eb;">
                    <i class="fa fa-paper-plane text-3xl mb-3" style="color: #2563eb;"></i>
                    <p class="text-xl font-bold" style="color: var(--color-text-card);"><?php echo $total_applied; ?></p>
                    <p class="text-sm uppercase opacity-70" style="color: var(--color-text-card);"><?php echo $lang['total_applied'] ?? 'Total Applied'; ?></p>
                </div>

                <div class="card p-6 flex flex-col items-center justify-center shadow-lg" style="border-left: 5px solid #f97316;">
                    <i class="fa fa-star text-3xl mb-3" style="color: #f97316;"></i>
                    <p class="text-xl font-bold" style="color: var(--color-text-card);"><?php echo $shortlisted_count; ?></p>
                    <p class="text-sm uppercase opacity-70" style="color: var(--color-text-card);"><?php echo $lang['shortlisted_count'] ?? 'Shortlisted Count'; ?></p>
                </div>
                
                <a href="inbox.php" class="card p-6 flex flex-col items-center justify-center shadow-lg hover:bg-opacity-90 transition" style="border-left: 5px solid #9333ea;">
                    <i class="fa fa-bell text-3xl mb-3" style="color: #9333ea;"></i>
                    <p class="text-xl font-bold" style="color: var(--color-text-card);"><?php echo $unread_notifications; ?></p>
                    <p class="text-sm uppercase opacity-70" style="color: var(--color-text-card);">Unread Messages</p>
                </a>

                <div class="card p-6 flex flex-col items-center justify-center shadow-lg" style="border-left: 5px solid var(--color-primary);">
                    <i class="fa fa-briefcase text-3xl mb-3" style="color: var(--color-primary);"></i>
                    <p class="text-sm uppercase opacity-70 mb-2" style="color: var(--color-text-card);">Check New Matches</p>
                    <a href="view_jobs.php" class="px-4 py-1 rounded-lg text-white font-medium" style="background-color: var(--color-primary);">
                        Browse Jobs
                    </a>
                </div>
            </section>
            
            <section class="mt-10 mb-8 p-6 rounded-xl  border" style=" border-color: var(--color-border);">
                 <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Application Status Breakdown</h2>
                 <div style="max-height: 350px;">
                    <canvas id="funnelChart"></canvas> 
                 </div>
            </section>
            
            <section>
                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Recommended Internships</h2>
                <p class="card p-4" style="color: var(--color-text-card); opacity: 0.8;">
                     <?php echo $lang['lable_jobfind'] ?? 'Your personalized job recommendations will appear here based on your profile and skills.'; ?>
                    <a href="view_jobs.php" style="color: var(--color-primary);" class="font-medium ml-2"><?php echo $lang['browse_jobs']; ?></a>
                </p>
            </section>

        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">
               
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Profile Status</h2>
            
            <div class="flex flex-col items-center">
                <div class="relative mb-4">
                    <?php echo get_ring_chart_svg($profile_complete_percent, '#10b981'); ?>
                    <div class="absolute inset-0 flex items-center justify-center text-xl font-bold" style="color: var(--color-text-card);">
                        <?php echo $profile_complete_percent; ?>%
                    </div>
                </div>
                
                <p class="text-center text-lg font-semibold" style="color: var(--color-text-card);">
                    <?php echo $student_data['full_name'] ?? 'Student User'; ?>
                </p>
                <p class="text-sm opacity-80" style="color: var(--color-text-card);">
                    <?php echo $student_data['major'] ?? 'N/A'; ?> (GPA: <?php echo $student_data['gpa'] ?? 'N/A'; ?>)
                </p>
                
                <a href="profile.php" class="mt-4 px-4 py-2 rounded-lg text-white font-medium button-primary" style="background-color: var(--color-primary);">
                    Update Profile
                </a>
            </div>
            
            <nav class="mt-8 space-y-3 border-t pt-4" style="border-color: var(--color-border);">
                <?php
                // 💡 Quick Links for navigation
                echo sidebar_link('profile.php', 'fa-user-edit', 'student_profile', 'profile.php');
                echo sidebar_link('my_applications.php', 'fa-file-alt', 'my_applications', 'my_applications.php');
                echo sidebar_link('view_jobs.php', 'fa-briefcase', 'view_jobs', 'view_jobs.php');
                echo sidebar_link('cv_upload.php', 'fa-upload', 'upload_resume', 'cv_upload.php');
                echo sidebar_link('inbox.php', 'fa-bell', 'notification_inbox', 'inbox.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Data from PHP
            const funnelData = <?php echo json_encode($application_funnel); ?>;
            
            // Prepare data for Chart.js
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
                            '#f59e0b',  // Pending
                            '#2563eb', // Shortlisted
                            '#10b981', // Selected
                            '#ef4444', // Rejected
                            '#06b6d4', // Interview
                        ],
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: 'var(--color-text-card)' } },
                        title: { display: false }
                    },
                    cutout: '60%' // Adjust inner size
                }
            });
        });
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>