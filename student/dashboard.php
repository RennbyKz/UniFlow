<?php
// student/dashboard.php
ob_start(); 
require_once '../core/functions.php'; 
// စနစ်ကို စတင်ပါ (Student Role လိုအပ်)
init_system('student_dashboard', 'student'); 

// ------------------------------------------
// Data Fetching for Student Summary
// ------------------------------------------

$student_id = 0; $total_applied = 0; $profile_complete_percent = 0; $shortlisted_count = 0; $dashboard_error = '';

try {
    global $pdo;
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_data = $stmt->fetch();
    
    if ($student_data) {
        $student_id = $student_data['student_id'];
        
        // 1. စုစုပေါင်း လျှောက်ထားမှု အရေအတွက်
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $total_applied = $stmt->fetchColumn();

        // 2. Shortlisted အရေအတွက်
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE student_id = ? AND status = 'Shortlisted'");
        $stmt->execute([$student_id]);
        $shortlisted_count = $stmt->fetchColumn();

        // 3. Profile ဖြည့်သွင်းမှု ရာခိုင်နှုန်း (5 items: Name, Major, GPA, Email, Resume)
        $total_items = 16;
        $filled_count = 0;
        if (!empty($student_data['full_name'])) $filled_count++;
        if (!empty($student_data['major'])) $filled_count++;
        if (!empty($student_data['gpa'])) $filled_count++;
        if (!empty($_SESSION['user_name'])) $filled_count++; // Username/Email from session
        if (!empty($student_data['contact_phone'])) $filled_count++; 
        if (!empty($student_data['photo_path'])) $filled_count++; 
        if (!empty($student_data['dob'])) $filled_count++; 
        if (!empty($student_data['gender'])) $filled_count++; 
        if (!empty($student_data['address'])) $filled_count++; 
        if (!empty($student_data['linkedin_url'])) $filled_count++; 
        if (!empty($student_data['github_url'])) $filled_count++; 
        if (!empty($student_data['career_objective'])) $filled_count++; 
        if (!empty($student_data['university_name'])) $filled_count++; 
        if (!empty($student_data['year_of_study'])) $filled_count++; 
        if (!empty($student_data['education'])) $filled_count++; 
        if (!empty($student_data['cv_path'])) $filled_count++; // Assume cv_path is set upon upload
        
        $profile_complete_percent = round(($filled_count / $total_items) * 100);
    }

} catch (PDOException $e) {
    $dashboard_error = $lang['error_general_try_again'] . " (DB): " . $e->getMessage();
    echo $dashboard_error;
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 

// 💡 New Helper: Ring Chart SVG (Pure CSS/SVG)
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
            
            <h1 class="text-4xl font-extrabold mb-8" style="color: var(--color-main);">
                <?php echo $lang['student_dashboard']; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($dashboard_error)): ?>
                 <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $dashboard_error; ?></p>
                 </div>
            <?php endif; ?>

            <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                
                <div class="card p-6 flex flex-col items-center justify-center shadow-lg" style="border-left: 5px solid #2563eb;">
                    <i class="fa fa-paper-plane text-3xl mb-3" style="color: #2563eb;"></i>
                    <p class="text-xl font-bold" style="color: var(--color-text-card);"><?php echo $total_applied; ?></p>
                    <p class="text-sm uppercase opacity-70" style="color: var(--color-text-card);"><?php echo $lang['total_applied']; ?></p>
                </div>

                <div class="card p-6 flex flex-col items-center justify-center shadow-lg" style="border-left: 5px solid #f97316;">
                    <i class="fa fa-star text-3xl mb-3" style="color: #f97316;"></i>
                    <p class="text-xl font-bold" style="color: var(--color-text-card);"><?php echo $shortlisted_count; ?></p>
                    <p class="text-sm uppercase opacity-70" style="color: var(--color-text-card);"><?php echo $lang['shortlisted_count']; ?></p>
                </div>
                
                <div class="card p-6 flex flex-col items-center justify-center shadow-lg" style="border-left: 5px solid #ef4444;">
                    <i class="fa fa-times-circle text-3xl mb-3" style="color: #ef4444;"></i>
                    <p class="text-xl font-bold" style="color: var(--color-text-card);">N/A</p>
                    <p class="text-sm uppercase opacity-70" style="color: var(--color-text-card);">Pending Decisions</p>
                </div>
            </section>
            
            <section>
                 <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Recommended Internships</h2>
                 <p class="card p-4" style="color: var(--color-text-card); opacity: 0.8;">
                     Your personalized job recommendations will appear here based on your profile and skills.
                     <a href="view_jobs.php" style="color: var(--color-primary);" class="font-medium ml-2">Browse All Jobs</a>
                 </p>
            </section>

        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); height: 100%; position: sticky; top: 60px;">
               
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-text-card);">Profile Status</h2>
            
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
                
                <a href="update_profile.php" class="mt-4 px-4 py-2 rounded-lg text-white font-medium button-primary" style="background-color: var(--color-primary);">
                    Update Profile
                </a>
            </div>
            
            <nav class="mt-8 space-y-3 border-t pt-4" style="border-color: var(--color-border);">
                <?php
                // 💡 Quick Links for navigation
                echo sidebar_link('profile.php', 'fa-user-circle', 'student_profile', 'profile.php');
                echo sidebar_link('my_applications.php', 'fa-file-alt', 'my_applications', 'my_applications.php');
                echo sidebar_link('view_jobs.php', 'fa-briefcase', 'browse_jobs', 'view_jobs.php');
                echo sidebar_link('cv_upload.php', 'fa-upload', 'upload_resume', 'cv_upload.php');
                echo sidebar_link('inbox.php', 'fa-bell', 'notification_inbox', 'inbox.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>