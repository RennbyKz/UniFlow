<?php
// admin/view_profile.php
ob_start();
require_once '../core/functions.php'; 
// Admin và Company Role Check
// Role is set to null, and check is done explicitly below, allowing both Admin/Company access
init_system('view_profile_title', null); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$errors = [];
$profile = [];
$skills = [];
$projects = [];
$experiences = [];
$certificates = [];

// 1. Get Student ID from URL
$student_id_to_view = isset($_GET['student_id']) ? sanitize_input($_GET['student_id']) : 0;

// 2. Access Control Check (Must be Admin or Company)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'company')) {
    set_session_message('invalid_role', 'error');
    header("Location: ../index.php");
    ob_end_flush();
    exit();
}

if (!$student_id_to_view) {
    set_session_message('error_general_try_again', 'error');
    header("Location: manage_students.php"); 
    ob_end_flush();
    exit();
}

try {
    // 3. Profile Core Data
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username, u.is_approved
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id_to_view]);
    $profile = $stmt->fetch();

    if (!$profile) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: manage_students.php");
        ob_end_flush();
        exit();
    }
    
    // 4. Fetch Dynamic Data (Skills, Projects, Experiences, Certificates)
    $stmt = $pdo->prepare("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = ? ORDER BY FIELD(proficiency_level, 'Expert', 'Advanced', 'Intermediate', 'Beginner')");
    $stmt->execute([$profile['student_id']]);
    $skills = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT title, company, start_date, end_date, description FROM student_experiences WHERE student_id = ? ORDER BY start_date DESC");
    $stmt->execute([$profile['student_id']]);
    $experiences = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT title, project_url, project_image_path, technologies, description FROM student_projects WHERE student_id = ? ORDER BY project_id DESC");
    $stmt->execute([$profile['student_id']]);
    $projects = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT cert_name, issuer, issue_date, cert_path FROM student_certificates WHERE student_id = ? ORDER BY issue_date DESC");
    $stmt->execute([$profile['student_id']]);
    $certificates = $stmt->fetchAll();

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

        <main class="flex-grow p-8 ml-64 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-4xl p-8 rounded-xl shadow-2xl border flex-grow" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="flex justify-between items-center pb-4 mb-6 border-b" style="border-color: var(--color-primary);">
                    <div>
                        <h1 class="text-4xl font-extrabold" style="color: var(--color-primary);">
                            <?php echo htmlspecialchars($profile['full_name'] ?? 'N/A'); ?>
                        </h1>
                        <p class="text-lg font-medium" style="color: var(--color-text-card); opacity: 0.8;">
                            <?php echo htmlspecialchars($profile['major'] ?? 'N/A'); ?> (GPA: <?php echo htmlspecialchars($profile['gpa'] ?? 'N/A'); ?>)
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full font-semibold text-white text-sm" 
                          style="background-color: <?php echo ($profile['is_approved'] === 'Yes') ? '#10b981' : '#ef4444'; ?>;">
                        <?php echo ($profile['is_approved'] === 'Yes') ? 'Account Active' : 'Account Disabled'; ?>
                    </span>
                </div>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">
                        <?php echo $lang['personal_info'] ?? 'Personal Information'; ?>
                    </h2>
                    <div class="grid grid-cols-2 gap-4 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong><i class="fa fa-phone mr-2"></i>Phone:</strong> <?php echo htmlspecialchars($profile['contact_phone'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-envelope mr-2"></i>Email:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-birthday-cake mr-2"></i>D.O.B:</strong> <?php echo htmlspecialchars($profile['dob'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-venus-mars mr-2"></i>Gender:</strong> <?php echo htmlspecialchars($profile['gender'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-university mr-2"></i>Major:</strong> <?php echo htmlspecialchars($profile['major'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-graduation-cap mr-2"></i>Academic Information:</strong> <?php echo htmlspecialchars($profile['year_of_study'] ?? 'N/A'); ?>
                        <p><strong><?php echo $lang['label_uni_name'] ?? 'University'; ?>:</strong> <?php echo htmlspecialchars($profile['university_name'] ?? 'N/A'); ?></p>
                      
                        <p class="col-span-2"><strong><i class="fa fa-map-marker-alt mr-2"></i>Address:</strong> <?php echo htmlspecialchars($profile['address'] ?? 'N/A'); ?></p>
                        <p class="col-span-2">
                            <strong><i class="fab fa-linkedin mr-2"></i>LinkedIn:</strong> 
                            <a href="<?php echo htmlspecialchars($profile['linkedin_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);">
                                <?php echo htmlspecialchars($profile['linkedin_url'] ?? 'N/A'); ?>
                            </a>
                        </p>
                        <p class="col-span-2">
                            <strong><i class="fab fa-github mr-2"></i>GitHub:</strong> 
                            <a href="<?php echo htmlspecialchars($profile['github_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);">
                                <?php echo htmlspecialchars($profile['github_url'] ?? 'N/A'); ?>
                            </a>
                        </p>
                    </div>
                </section>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Academic Information</h2>
                    <div class="space-y-1 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong>University:</strong> <?php echo htmlspecialchars($profile['university_name'] ?? 'N/A'); ?></p>
                        <p><strong>Degree:</strong> <?php echo htmlspecialchars($profile['degree'] ?? 'N/A'); ?></p>
                        <p><strong>Year:</strong> <?php echo htmlspecialchars($profile['year_of_study'] ?? 'N/A'); ?></p>
                        <p><strong>GPA:</strong> <span class="font-bold" style="color: var(--color-primary);"><?php echo htmlspecialchars($profile['gpa'] ?? 'N/A'); ?></span></p>
                    </div>
                </section>
                
                <?php if (!empty($skills)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Technical Skills</h2>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <?php foreach ($skills as $skill): ?>
                            <span class="px-3 py-1 rounded-full text-white font-medium" style="background-color: var(--color-accent);">
                                <?php echo htmlspecialchars($skill['skill_name']); ?> (<?php echo htmlspecialchars($skill['proficiency_level']); ?>)
                            </span>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($projects)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Projects & Portfolio</h2>
                    <div class="space-y-3">
                        <?php foreach ($projects as $project): ?>
                            <div class="p-3 rounded-lg border" style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                                <p class="font-semibold" style="color: var(--color-primary);"><?php echo htmlspecialchars($project['title']); ?></p>
                                <p class="text-xs mt-1 opacity-80">Tech: <?php echo htmlspecialchars($project['technologies']); ?></p>
                                <a href="<?php echo htmlspecialchars($project['project_url'] ?? '#'); ?>" target="_blank" style="color: var(--color-accent);">View Link</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($experiences)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Work Experience</h2>
                    <?php foreach ($experiences as $exp): ?>
                        <div class="p-3 rounded-lg border mb-2" style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                            <p class="font-semibold" style="color: var(--color-primary);"><?php echo htmlspecialchars($exp['title']); ?> @ <?php echo htmlspecialchars($exp['company']); ?></p>
                            <p class="text-xs opacity-80">
                                <?php echo date('M Y', strtotime($exp['start_date'])); ?> - 
                                <?php echo $exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : 'Current'; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>
                
                <?php if (!empty($profile['resume_path'])): ?>
                <section class="mb-6 text-center pt-4 border-t" style="border-color: var(--color-border);">
                    <a href="../<?php echo htmlspecialchars($profile['resume_path']); ?>" target="_blank" class="px-6 py-3 rounded-lg text-white font-semibold text-lg" style="background-color: var(--color-accent);">
                        <i class="fa fa-file-pdf mr-2"></i> View Report
                    </a>
                </section>
                <?php endif; ?>

            </div>
        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>