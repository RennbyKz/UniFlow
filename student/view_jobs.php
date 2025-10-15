<?php
// student/view_jobs.php
ob_start(); 
require_once '../core/functions.php'; 
// Student Role Check
init_system('view_jobs', 'student'); 

// ------------------------------------------
// 1. Data Fetching & Search/Filter Logic
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$search_keyword = sanitize_input($_GET['keyword'] ?? '');
$filter_type = sanitize_input($_GET['type'] ?? '');
$jobs_list = [];
$errors = [];

// 💡 Student Profile Data Fetching (Aside Panel အတွက်)
$student_data = [];
$profile_complete_percent = 0;
$student_id = 0;
try {
    $stmt = $pdo->prepare("SELECT student_id, full_name, gpa, major, resume_path FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_data = $stmt->fetch();
    if ($student_data) {
        $student_id = $student_data['student_id'];
        // Profile completion calculation (Simplified)
        $total_items = 5; $filled_count = 0;
        if (!empty($student_data['full_name'])) $filled_count++;
        if (!empty($student_data['major'])) $filled_count++;
        if (!empty($student_data['gpa'])) $filled_count++;
        if (!empty($_SESSION['user_name'])) $filled_count++; 
        if (!empty($student_data['resume_path'])) $filled_count++; 
        $profile_complete_percent = round(($filled_count / $total_items) * 100);
    }
} catch (PDOException $e) { /* Error handling */ }


// SQL Query Construction (Job List)
$sql = "
    SELECT j.*, c.name AS company_name, c.industry 
    FROM jobs j
    JOIN companies c ON j.company_id = c.company_id
    WHERE c.is_approved = 'Yes' AND j.deadline >= CURDATE()
";
$params = [];
$where_clauses = [];

// 1. Keyword Search
if (!empty($search_keyword)) {
    $where_clauses[] = "(j.title LIKE :keyword OR j.description LIKE :keyword OR c.name LIKE :keyword)";
    $params[':keyword'] = '%' . $search_keyword . '%';
}

// 2. Job Type Filter
if (!empty($filter_type)) {
    $where_clauses[] = "j.type = :type";
    $params[':type'] = $filter_type;
}

// 3. Deadline Check
$where_clauses[] = "j.deadline >= CURDATE()";

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY j.posted_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = $lang['error_general_try_again'] . " (Search Error)";
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 

// 💡 Ring Chart Helper (Dashboard မှ ကူးယူခြင်း)
function get_ring_chart_svg($percent, $color_hex) {
    $circumference = 2 * M_PI * 45; 
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
       <!-- ✅ Toast container -->
        <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">

        <main class="flex-grow p-8" style="background-color: var(--color-bg-main);">

            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-main); border-color: var(--color-border);">
                <?php echo $lang['view_jobs'] ?? 'Browse Job Listings'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="p-6 rounded-xl shadow-lg border mb-8" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                <form method="GET" action="view_jobs.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    
                    <div class="md:col-span-2">
                        <label for="keyword" class="block font-medium mb-1">Keywords / Company:</label>
                        <input type="text" id="keyword" name="keyword" placeholder="e.g., PHP, Data Analyst, Internship" value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>
                    
                    <div>
                        <label for="type" class="block font-medium mb-1"><?php echo $lang[ 'label_jobtype'] ?? 'Job Type' ?></label>
                        <select id="type" name="type" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">-- <?php echo $lang[ 'label_all'] ?? 'ALL' ?>--</option>
                            <option value="Internship" <?php echo ($filter_type == 'Internship') ? 'selected' : ''; ?>><?php echo $lang[ 'label_all_intern'] ?? 'Internship' ?></option>
                            <option value="Full-Time" <?php echo ($filter_type == 'Full-Time') ? 'selected' : ''; ?>><?php echo $lang[ 'label_fulltime'] ?? 'Full-Time' ?></option>
                            <option value="Part-Time" <?php echo ($filter_type == 'Part-Time') ? 'selected' : ''; ?>><?php echo $lang[ 'label_parttiime'] ?? 'Part-Time' ?></option>
                        </select>
                    </div>
                    
                    <button type="submit" class="button-primary px-6 py-2 rounded-lg font-semibold" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-search mr-2"></i> <?php echo $lang['button_search'] ?? 'Search'; ?>
                    </button>
                </form>
            </div>
            
            <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Showing <?php echo count($jobs_list); ?> Available Jobs</h2>
            
            <div class="space-y-6">
                <?php if (empty($jobs_list)): ?>
                    <div class="p-6 rounded-lg border" style="background-color: var(--color-bg-card); color: var(--color-text-card);">
                        <p><?php echo $lang['no_jobs_found'] ?? 'No job listings found matching your criteria.'; ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($jobs_list as $job): ?>
                    <div class="card p-5 shadow-lg border" style="background-color: var(--color-bg-card); border-left: 5px solid var(--color-primary);">
                        <a href="job_details.php?job_id=<?php echo $job['job_id']; ?>" style="color: var(--color-primary);">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="text-xl font-bold" style="color: var(--color-primary);"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <p class="text-sm opacity-80" style="color: var(--color-text-card);">
                                    <?php echo htmlspecialchars($job['company_name']); ?> | <?php echo htmlspecialchars($job['location']); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold text-white" 
                                  style="background-color: var(--color-accent);">
                                <?php echo htmlspecialchars($job['type']); ?>
                            </span>
                        </div>
                        
                        <p class="text-sm mb-3" style="color: var(--color-text-card); opacity: 0.9;">
                            <?php echo substr(htmlspecialchars($job['description']), 0, 150) . '...'; ?>
                        </p>
                        
                        <div class="flex justify-between items-center text-xs">
                            <div>
                                <span class="text-sm font-semibold mr-3" style="color: var(--color-text-card);"><?php echo $lang['label_skill'] ?? 'Skill'; ?></span>
                                <span class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded-md text-xs" style="color: var(--color-text-card);">
                                    <?php echo htmlspecialchars(substr($job['required_skills'], 0, 30)); ?>...
                                </span>
                            </div>
                            
                            <button type="button" 
                                class="button-primary px-4 py-2 rounded-lg text-white text-sm" 
                                style="background-color: var(--color-primary);">
                                <i class="fa fa-paper-plane mr-1"></i> <?php echo $lang['button_detail'] ?? 'Details'; ?>
                            </button>
                        </div>
                    </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">
               
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);"><?php echo $lang['lable_profile_status'] ?? 'Profile Status' ?></h2>
            
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
                   <?php echo $lang[ 'button_edit_profile']?>
                </a>
            </div>
            
            <nav class="mt-8 space-y-3 border-t pt-4" style="border-color: var(--color-border);">
                <a href="dashboard.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-tachometer-alt w-5"></i><span><?php echo $lang['student_dashboard']; ?></span>
                </a>
                <a href="profile.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-user-edit w-5"></i><span><?php echo $lang['student_profile']; ?></span>
                </a>
                <a href="my_applications.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-file-alt w-5"></i><span><?php echo $lang['my_applications']; ?></span>
                </a>
                <a href="cv_upload.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-upload w-5"></i><span><?php echo $lang['upload_resume']; ?></span>
                </a>
                <a href="../logout.php" class="flex items-center gap-3 hover:text-red-500" style="color: var(--color-error);">
                    <i class="fa fa-sign-out-alt w-5"></i><span><?php echo $lang['logout']; ?></span>
                </a>
            </nav>
        </aside>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>


/**
 * 2. AJAX Submission and Result Handling
 */
function handleApply(jobId) {
    Swal.fire({
        title: 'Submitting...',
        text: 'Please wait while we process your application.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('apply_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `job_id=${jobId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Success!', data.message, 'success');
            // Page ကို Refresh လုပ်ပြီး Status ကို ပြရန်
            setTimeout(() => { window.location.reload(); }, 1500); 

        } else if (data.status === 'warning') {
            // Already Applied
            Swal.fire('Already Applied', data.message, 'warning');
        
        } else if (data.status === 'resume_error') {
            // Resume Missing Error (Redirect to CV Upload)
            Swal.fire({
                title: 'CV Required',
                text: data.message,
                icon: 'error',
                showConfirmButton: true,
                confirmButtonText: 'Go to CV Upload'
            }).then(() => {
                 window.location.href = 'cv_upload.php';
            });
        
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Network Error', 'Could not connect to the server.', 'error');
    });
}
</script>
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>