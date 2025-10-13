<?php
// student/view_profile_summary.php
ob_start();
require_once '../core/functions.php'; 
init_system('student_profile', 'student'); 

global $pdo;
$user_id = $_SESSION['user_id'];
$student_id = 0;
$profile = [];
$skills = [];
$experiences = [];
$projects = [];

try {
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    $student_id = $profile['student_id'] ?? 0;

    $stmt = $pdo->prepare("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $skills = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT title, company, start_date, end_date FROM student_experiences WHERE student_id = ? ORDER BY start_date DESC");
    $stmt->execute([$student_id]);
    $experiences = $stmt->fetchAll();

    $projects = $stmt->fetchAll();

} catch (PDOException $e) {
    set_session_message($lang['error_general_try_again'] . " (DB Error)", 'error');
}
// 4. Projects (student_projects table မှ) - Example
    $stmt = $pdo->prepare("SELECT title, project_url, project_image_path, technologies, description FROM student_projects WHERE student_id = ? ORDER BY project_id DESC");
    $stmt->execute([$student_id]);
    $projects = $stmt->fetchAll();

include '../templates/header.php'; 
?>

<body class="min-h-screen flex flex-col font-sans bg-gray-50">

    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <main class="flex-grow p-8 bg-white rounded-xl shadow-lg border border-gray-200">

            <?php display_session_messages($lang); ?>

            <div class="flex items-center gap-6 mb-8 border-b border-gray-300 pb-4">
                <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-indigo-600 shadow-md">
                    <?php if (!empty($profile['photo_path']) && file_exists('../' . $profile['photo_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Profile Picture" class="object-cover w-full h-full">
                    <?php else: ?>
                        <img src="../assets/images/default_profile.png" alt="Default Profile Picture" class="object-cover w-full h-full">
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-indigo-700"><?php echo htmlspecialchars($profile['full_name'] ?? 'N/A'); ?></h1>
                    <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($profile['username'] ?? ''); ?></p>
                    <p class="mt-2 text-gray-700 max-w-xl"><?php echo nl2br(htmlspecialchars($profile['bio'] ?? '')); ?></p>
                    <a href="update_profile.php" class="inline-block mt-4 px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <i class="fa fa-user-edit mr-2"></i> <?php echo $lang['button_edit_profile'] ?? 'Edit Profile'; ?>
                    </a>
                </div>
            </div>

            <section class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6 text-gray-700">
                <div class="space-y-3 bg-indigo-50 p-5 rounded-lg shadow-inner">
                    <h2 class="text-2xl font-semibold mb-3 text-indigo-700"><?php echo $lang['personal_info'] ?? 'Personal Information'; ?></h2>
                    <p><strong><?php echo $lang['label_gender'] ?? 'Gender'; ?>:</strong> <?php echo !empty($profile['gender']) ? htmlspecialchars(ucfirst($profile['gender'])) : 'N/A'; ?></p>
                    <p><strong><?php echo $lang['label_dob'] ?? 'Date of Birth'; ?>:</strong> 
                        <?php 
                        if (!empty($profile['dob']) && $profile['dob'] !== '0000-00-00') {
                            echo htmlspecialchars(date('F j, Y', strtotime($profile['dob'])));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </p>
                    <p><strong><?php echo $lang['label_phone'] ?? 'Phone'; ?>:</strong> <?php echo htmlspecialchars($profile['contact_phone'] ?? 'N/A'); ?></p>
                    <p><strong><?php echo $lang['label_student_email'] ?? 'Email'; ?>:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></p>
                    <p><strong><?php echo $lang['label_address'] ?? 'Address'; ?>:</strong> <?php echo htmlspecialchars($profile['address'] ?? 'N/A'); ?></p>
                    <p><strong><?php echo $lang['label_education'] ?? 'Education'; ?>:</strong> <?php echo htmlspecialchars($profile['education'] ?? 'N/A'); ?></p>
                </div>

                <div class="space-y-3 bg-indigo-50 p-5 rounded-lg shadow-inner">
                    <h2 class="text-2xl font-semibold mb-3 text-indigo-700"><?php echo $lang['technical_skills'] ?? 'Technical Skills'; ?></h2>
                    <?php if (!empty($skills)): ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($skills as $skill): ?>
                                <span class="bg-indigo-600 text-white px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo htmlspecialchars($skill['skill_name']); ?> (<?php echo htmlspecialchars($skill['proficiency_level']); ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php echo $lang['no_skills_added'] ?? 'No skills added yet.'; ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!empty($experiences)): ?>
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 text-indigo-700"><?php echo $lang['experience'] ?? 'Work Experience'; ?></h2>
                <ul class="space-y-4">
                    <?php foreach ($experiences as $exp): ?>
                    <li class="border border-gray-200 rounded-lg p-4 bg-white shadow-sm">
                        <h3 class="text-xl font-semibold text-indigo-600"><?php echo htmlspecialchars($exp['title']); ?></h3>
                        <p class="text-gray-700"><?php echo htmlspecialchars($exp['company']); ?></p>
                        <p class="text-sm text-gray-500">
                            <?php 
                            echo (!empty($exp['start_date']) && $exp['start_date'] !== '0000-00-00') ? date('M Y', strtotime($exp['start_date'])) : 'N/A'; 
                            ?> - 
                            <?php 
                            echo (!empty($exp['end_date']) && $exp['end_date'] !== '0000-00-00') ? date('M Y', strtotime($exp['end_date'])) : 'Present'; 
                            ?>
                        </p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if (!empty($projects)): ?>
            <section class="mb-6">
    <h2 class="text-xl font-bold mb-3 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">
        <i class="fa fa-code-branch mr-2" style="color: var(--color-accent);"></i>
        <?php echo $lang['manage_projects'] ?? 'Projects & Portfolio'; ?>
    </h2>

    <?php if (empty($projects)): ?>
        <p class="text-sm opacity-70" style="color: var(--color-text-card);">
            No projects added yet. Please update your profile to include your portfolio.
        </p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($projects as $project): ?>
            <div class="p-3 rounded-lg border flex items-start gap-4" 
                 style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                
                <div class="flex-shrink-0">
                    <?php if (!empty($project['project_image_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($project['project_image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($project['title']); ?> Logo" 
                             class="w-16 h-16 object-cover rounded-md border" style="border-color: var(--color-border);">
                    <?php else: ?>
                         <div class="w-16 h-16 flex items-center justify-center rounded-md border" style="border-color: var(--color-border); background-color: var(--color-bg-dashboard);">
                             <i class="fa fa-code text-2xl" style="color: var(--color-text-card); opacity: 0.5;"></i>
                         </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex-grow">
                    <div class="flex justify-between items-start">
                        <span class="font-semibold text-lg" style="color: var(--color-primary);"><?php echo htmlspecialchars($project['title']); ?></span>
                        
                        <?php if (!empty($project['project_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" 
                               class="text-sm text-green-600 hover:text-green-800 transition whitespace-nowrap">
                                <i class="fa fa-external-link-alt"></i> View Link
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-xs mt-1" style="color: var(--color-text-card); opacity: 0.8;">
                        **Tech:** <?php echo htmlspecialchars($project['technologies']); ?>
                    </p>
                    <p class="text-sm mt-2" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
            <?php endif; ?>
        </main>

        <aside class="w-full md:w-80 p-6 border-l bg-indigo-50 rounded-lg ml-8 sticky top-16 h-fit shadow-md">
            <h2 class="text-xl font-bold mb-4 text-indigo-700">Quick Actions</h2>
            <nav class="space-y-3">
                <a href="dashboard.php" class="flex items-center gap-3 text-indigo-700 hover:text-indigo-900 transition">
                    <i class="fa fa-tachometer-alt w-5"></i><span><?php echo $lang['student_dashboard']; ?></span>
                </a>
                <a href="manage_skills.php" class="flex items-center gap-3 text-indigo-700 hover:text-indigo-900 transition">
                    <i class="fa fa-cogs w-5"></i><span><?php echo $lang['manage_skills']; ?></span>
                </a>
                <a href="manage_projects.php" class="flex items-center gap-3 text-indigo-700 hover:text-indigo-900 transition">
                    <i class="fa fa-folder-open w-5"></i><span><?php echo $lang['manage_projects'] ?? 'Manage/Add Projects'; ?></span>
                </a>
                <a href="view_profile_summary.php" class="flex items-center gap-3 text-indigo-700 hover:text-indigo-900 transition">
                    <i class="fa fa-user w-5"></i><span><?php echo $lang['view_profile'] ?? 'View Profile Summary'; ?></span>
                </a>
            </nav>
        </aside>
    </div>

<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>
