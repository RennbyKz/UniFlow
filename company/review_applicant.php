<?php
// company/review_applicant.php
ob_start();
require_once '../core/functions.php'; 
// Company Role Check
init_system('review_applicant', 'company'); 

// ------------------------------------------
// Initialization & Data Fetch (Based on Application ID)
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$application_id = sanitize_input($_GET['application_id'] ?? 0);
$profile = [];
$application = [];
$skills = [];
$projects = [];
$certificates = [];

// 1. Validate Application ID
if (!$application_id) {
    set_session_message('error_app_not_found', 'error');
    header("Location: manage_applications.php"); ob_end_flush(); exit();
}

try {
    // 1. Fetch Application, Job, Company & Student Data in one query
    $stmt = $pdo->prepare("
        SELECT 
            a.*, j.title AS job_title, j.company_id,
            s.*, u.email, u.username
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN companies c ON j.company_id = c.company_id
        JOIN students s ON a.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        WHERE a.application_id = :app_id AND c.user_id = :user_id
    ");
    $stmt->execute([':app_id' => $application_id, ':user_id' => $user_id]);
    $review_data = $stmt->fetch();

    if (!$review_data) {
        set_session_message('error_app_not_found', 'error');
        header("Location: manage_applications.php"); ob_end_flush(); exit();
    }
    
    // Assign fetched data
    $profile = $review_data;
    $application = [
        'id' => $review_data['application_id'],
        'status' => $review_data['status'],
        'applied_at' => $review_data['applied_at'],
        'job_title' => $review_data['job_title']
    ];
    $student_id = $review_data['student_id'];

    // 2. Fetch Dynamic Data (Skills, Projects, Certificates)
    $stmt = $pdo->prepare("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $skills = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT title, project_url, project_image_path, technologies, description FROM student_projects WHERE student_id = ? ORDER BY project_id DESC");
    $stmt->execute([$student_id]);
    $projects = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT cert_name, issuer, issue_date, cert_path FROM student_certificates WHERE student_id = ? ORDER BY issue_date DESC");
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll();


} catch (PDOException $e) {
    set_session_message($lang['error_general_try_again'] . " (DB Error)", 'error');
}

// ------------------------------------------
// 3. ACTION HANDLING (Change Application Status)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $new_status = sanitize_input($_POST['new_status']);
    $interview_date_str = sanitize_input($_POST['interview_date'] ?? null); 
    
    try {
        $pdo->beginTransaction();
        $params = [$new_status, $application['id']];
        $update_query = "UPDATE applications SET status = ?, interview_date = NULL "; // Default date clear

        // Conditional Action: Interview Schedule
        if ($new_status === 'Interview') {
            if (empty($interview_date_str)) {
                // If JS failed to provide date, throw error
                throw new Exception($lang['error_interview_date_missing'] ?? 'Interview date is required.');
            }
            $update_query .= ", interview_date = ?";
            $params = [$new_status, $interview_date_str, $application['id']];
        }

        // Database Update
        $stmt = $pdo->prepare($update_query . " WHERE application_id = ?");
        $stmt->execute($params);

        $pdo->commit();
        
        // 💡 Notification Logic (Placeholder for Email/Internal Inbox)
        // Note: Implement send_internal_notification() and send_external_email() in core/functions.php
        // Example: send_internal_notification($student_id, "Status Update: {$new_status}", $content, 'info');

        set_session_message('status_update_success', 'success');
        header("Location: review_applicant.php?application_id={$application['id']}");
        ob_end_flush(); exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        set_session_message($e->getMessage(), 'error'); 
        header("Location: review_applicant.php?application_id={$application['id']}");
        ob_end_flush(); exit();
    }
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
                echo sidebar_link('manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
                echo sidebar_link('post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-grow p-8 ml-64 flex" style="background-color: var(--color-bg-dashboard);">
            
            <div class="w-full max-w-4xl p-8 rounded-xl shadow-2xl border flex-grow" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="pb-4 mb-6 border-b flex justify-between items-center" style="border-color: var(--color-primary);">
                    <div>
                        <h1 class="text-3xl font-extrabold" style="color: var(--color-primary);">
                            <?php echo htmlspecialchars($profile['full_name']); ?> Profile
                        </h1>
                        <p class="text-lg font-medium" style="color: var(--color-text-card); opacity: 0.8;">
                            Applying for: <?php echo htmlspecialchars($application['job_title']); ?>
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full font-semibold text-white text-sm" style="background-color: <?php echo match($application['status']) { 'Selected' => '#10b981', 'Shortlisted' => '#2563eb', 'Rejected' => '#ef4444', default => '#f59e0b', }; ?>;">
                        <?php echo htmlspecialchars($application['status']); ?>
                    </span>
                </div>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Personal Information</h2>
                    <div class="grid grid-cols-2 gap-4 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong><i class="fa fa-phone mr-2"></i>Phone:</strong> <?php echo htmlspecialchars($profile['contact_phone'] ?? 'N/A'); ?></p>
                        <p><strong><i class="fa fa-envelope mr-2"></i>Email:</strong> <?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></p>
                        <p class="col-span-2"><strong><i class="fa fa-map-marker-alt mr-2"></i>Address:</strong> <?php echo htmlspecialchars($profile['address'] ?? 'N/A'); ?></p>
                    </div>
                </section>
                
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Academic Information</h2>
                    <div class="space-y-1 text-sm" style="color: var(--color-text-card); opacity: 0.9;">
                        <p><strong>Major:</strong> <?php echo htmlspecialchars($profile['major'] ?? 'N/A'); ?></p>
                        <p><strong>GPA:</strong> <span class="font-bold" style="color: var(--color-primary);"><?php echo htmlspecialchars($profile['gpa'] ?? 'N/A'); ?></span></p>
                    </div>
                </section>
                
                <?php if (!empty($projects)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Projects</h2>
                    <div class="space-y-4">
                        <?php if (empty($projects)): ?>
             <p class="text-sm opacity-70">No projects listed by the applicant.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($projects as $project): ?>
                            <div class="p-4 rounded-lg border flex items-start gap-4" style="border-color: var(--color-border); background-color: var(--color-bg-main);">
                                
                                <div class="flex-shrink-0">
                                    <?php if (!empty($project['project_image_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($project['project_image_path']); ?>" alt="Project Logo" class="w-16 h-16 object-cover rounded-md border">
                                    <?php else: ?>
                                        <div class="w-16 h-16 flex items-center justify-center rounded-md border" style="border-color: var(--color-border); background-color: #f3f4f6;">
                                            <i class="fa fa-code text-2xl" style="color: var(--color-primary); opacity: 0.7;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-grow">
                                    <h3 class="font-semibold text-lg" style="color: var(--color-primary);"><?php echo htmlspecialchars($project['title']); ?></h3>
                                    
                                    <p class="text-xs mt-1 opacity-80" style="color: var(--color-text-card);">
                                        **Tech Stack:** <?php echo htmlspecialchars($project['technologies']); ?>
                                    </p>
                                    
                                    <p class="text-sm mt-2 leading-snug" style="color: var(--color-text-card); opacity: 0.9;">
                                        <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                                    </p>
                                    
                                    <?php if (!empty($project['project_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" class="mt-2 inline-block text-sm font-medium" style="color: var(--color-accent);">
                                            <i class="fa fa-external-link-alt mr-1"></i> View Live Project / GitHub
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </section>
                <?php endif; ?>
                
                <?php if (!empty($skills)): ?>
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-2 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">Technical Skills</h2>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <?php foreach ($skills as $skill): ?>
                            <span class="px-3 py-1 rounded-full text-white font-medium" style="background-color: var(--color-accent);"><?php echo htmlspecialchars($skill['skill_name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </section>
                
                <?php endif; ?>

                <?php if (!empty($profile['resume_path'])): ?>
                <section class="mb-6 text-center pt-4 border-t" style="border-color: var(--color-border);">
                    <a href="../<?php echo htmlspecialchars($profile['resume_path']); ?>" target="_blank" class="px-6 py-3 rounded-lg text-white font-semibold text-lg" style="background-color: #f59e0b;">
                        <i class="fa fa-file-pdf mr-2"></i> Download Original Resume
                    </a>
                </section>
                <?php endif; ?>
                
            </div>
            
            <aside class="w-full md:w-80 p-6 border-l flex-shrink-0" 
                   style="background-color: var(--color-bg-card); border-color: var(--color-border); height: calc(100vh - 60px); position: sticky; top: 60px;">
                
                <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Application Status</h2>
                
                <p class="text-lg font-semibold mb-6">
                    Current: 
                    <span class="px-3 py-1 rounded-full text-white" 
                          style="background-color: <?php echo match($application['status']) {
                                'Selected' => '#10b981', 'Shortlisted' => '#2563eb', 'Rejected' => '#ef4444', default => '#f59e0b',
                          }; ?>;">
                        <?php echo htmlspecialchars($application['status']); ?>
                    </span>
                </p>

                <form method="POST" id="statusUpdateForm" action="review_applicant.php?application_id=<?php echo $application['id']; ?>" class="space-y-4">
                    <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                    
                    <h3 class="font-bold mb-2">Update Status:</h3>
                    
                    <select name="new_status" id="new_status_select" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="">-- Select New Status --</option>
                        <option value="Pending" <?php echo ($application['status'] === 'Pending') ? 'selected' : ''; ?>>Pending (Default)</option>
                        <option value="Shortlisted" <?php echo ($application['status'] === 'Shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="Interview" <?php echo ($application['status'] === 'Interview') ? 'selected' : ''; ?>>Schedule Interview</option>
                        <option value="Selected" <?php echo ($application['status'] === 'Selected') ? 'selected' : ''; ?>>Offer Selected</option>
                        <option value="Rejected" <?php echo ($application['status'] === 'Rejected') ? 'selected' : ''; ?>>Reject</option>
                    </select>

                    <button type="submit" class="w-full button-primary py-2 rounded-lg text-white font-semibold" style="background-color: var(--color-primary);">
                        Update Applicant
                    </button>
                </form>

            </aside>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('new_status_select');
            const updateForm = document.getElementById('statusUpdateForm');

            statusSelect.addEventListener('change', function() {
                const newStatus = this.value;
                
                if (newStatus === 'Interview') {
                    Swal.fire({
                        title: 'Schedule Interview Date',
                        html: `<p class="text-sm mb-3">Select the date and time for the interview:</p><input id="interviewDateInput" type="datetime-local" class="swal2-input" required>`,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Confirm Schedule',
                        preConfirm: () => {
                            const date = document.getElementById('interviewDateInput').value;
                            if (!date) {
                                Swal.showValidationMessage('Interview date is required.');
                                return false;
                            }
                            return date;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // 1. Date Input ကို Form ထဲသို့ Hidden Field အနေဖြင့် ထည့်သွင်း
                            const hiddenDate = document.createElement('input');
                            hiddenDate.type = 'hidden';
                            hiddenDate.name = 'interview_date';
                            hiddenDate.value = result.value;
                            updateForm.appendChild(hiddenDate);
                            
                            // 2. Form Submit လုပ်ခြင်း
                            updateForm.submit();
                        } else {
                            // Cancel လုပ်ရင် Status ကို ပုံမှန်အတိုင်း ပြန်ထား
                            statusSelect.value = '<?php echo $application['status']; ?>'; 
                        }
                    });

                } else if (newStatus === 'Selected' || newStatus === 'Rejected') {
                    // Offer Selected/Reject အတွက် Confirmation
                    let title = newStatus === 'Selected' ? 'Final Offer Confirmation' : 'Reject Applicant?';
                    let icon = newStatus === 'Selected' ? 'success' : 'error';
                    
                    Swal.fire({
                        title: title,
                        text: `Are you sure you want to set the status to ${newStatus}? A notification will be sent.`,
                        icon: icon,
                        showCancelButton: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            updateForm.submit(); // Submit the form with the selected status
                        } else {
                            statusSelect.value = '<?php echo $application['status']; ?>'; 
                        }
                    });
                } else {
                    // Pending/Shortlisted အတွက် ချက်ချင်း Submit လုပ်ပါ
                    updateForm.submit();
                }
            });
        });
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>