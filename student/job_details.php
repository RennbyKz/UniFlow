<?php
// student/job_details.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('view_job_details', 'student'); 

// ------------------------------------------
// Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? sanitize_input($_GET['job_id']) : 0;
$job = [];
$applied_status = false;

if (!$job_id) {
    set_session_message('error_job_not_found', 'error');
    header("Location: view_jobs.php"); ob_end_flush(); exit();
}

try {
    // 1. Job, Company Details ကို ရယူခြင်း
    $stmt = $pdo->prepare("
        SELECT j.*, c.name AS company_name, c.logo_path, c.description AS company_description, c.website_url
        FROM jobs j
        JOIN companies c ON j.company_id = c.company_id
        WHERE j.job_id = ? AND c.is_approved = 'Yes'
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        set_session_message('error_job_not_found', 'error');
        header("Location: view_jobs.php"); ob_end_flush(); exit();
    }

    // 2. Student Data ကို ရယူခြင်း (Apply Button ကို ထိန်းချုပ်ရန်)
    $stmt = $pdo->prepare("SELECT student_id, resume_path FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_data = $stmt->fetch();
    $student_id = $student_data['student_id'] ?? null;
    $resume_path = $student_data['resume_path'] ?? null;

    // 3. လျှောက်ထားပြီးသား ဖြစ်/မဖြစ် စစ်ဆေးခြင်း
    $stmt = $pdo->prepare("SELECT application_id, status FROM applications WHERE student_id = ? AND job_id = ?");
    $stmt->execute([$student_id, $job_id]);
    $application_info = $stmt->fetch(); // Fetch the entire row

    $applied_status = $application_info ? true : false;
    $application_id_if_applied = $application_info['application_id'] ?? 0; // 💡 application_id ကို သိမ်းထားပါ

    } catch (PDOException $e) {
        set_session_message($lang['error_general_try_again'] . " (DB Error)", 'error');
    }

include '../templates/header.php'; 
?>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 
   <!-- ✅ Toast container -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8 flex justify-center" style="background-color: var(--color-bg-main);">
            
            <div class="w-full max-w-5xl p-8 rounded-xl shadow-2xl border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                
                <?php display_session_messages($lang); ?>

                <div class="pb-6 mb-6 border-b" style="border-color: var(--color-primary);">
                    <div class="flex items-center justify-between">
                        <h1 class="text-4xl font-extrabold" style="color: var(--color-primary);">
                            <?php echo htmlspecialchars($job['title']); ?>
                        </h1>
                        <span class="px-3 py-1 rounded-full font-semibold text-white" 
                              style="background-color: var(--color-accent);">
                            <?php echo htmlspecialchars($job['type']); ?>
                        </span>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <?php if ($job['logo_path']): ?>
                            <img src="../<?php echo htmlspecialchars($job['logo_path']); ?>" alt="Logo" class="w-10 h-10 object-contain rounded-full border">
                        <?php endif; ?>
                        <h2 class="text-xl font-semibold" style="color: var(--color-text-card);"><?php echo htmlspecialchars($job['company_name']); ?></h2>
                    </div>
                </div>

                <div class="mb-8 p-4 rounded-lg flex justify-between items-center" style="background-color: var(--color-bg-main); border: 1px solid var(--color-border);">
                    
                    <div>
                        <p class="font-medium text-lg mb-1" style="color: var(--color-text-card);">
                            <?php echo $lang['deadline'] ?? 'Deadline'; ?>: 
                            <span class="font-bold text-red-500"><?php echo htmlspecialchars($job['deadline']); ?></span>
                        </p>
                        <p class="text-sm opacity-80" style="color: var(--color-text-card);">
                            <?php echo $lang['posted_on'] ?? 'Posted On'; ?>: <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                        </p>
                    </div>

                    <?php if ($applied_status): ?>
                        <span class="px-4 py-2 rounded-lg font-semibold text-white" style="background-color: #f59e0b;">
                            <i class="fa fa-check-double mr-2"></i> <?php echo $lang['status_applied'] ?? 'Already Applied'; ?>
                        </span>
                    <?php elseif (empty($resume_path)): ?>
                         <a href="cv_upload.php" class="px-4 py-2 rounded-lg font-semibold text-white" style="background-color: #ef4444;">
                            <i class="fa fa-upload mr-2"></i> <?php echo $lang['error_resume_missing'] ?? 'Upload CV to Apply'; ?>
                        </a>
               <?php else: ?>
                        <button id="applyButton" data-job-id="<?php echo $job_id; ?>" 
                                class="button-primary px-6 py-3 rounded-lg font-semibold cursor-pointer" 
                                style="background-color: var(--color-primary); color: var(--color-bg-card);">
                                <i class="fa fa-paper-plane mr-2"></i> <?php echo $lang['button_apply'] ?? 'Apply Now'; ?>
                        </button>
                <?php endif; ?>
                </div>

                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        Job Description
                    </h2>
                    <p class="text-base leading-relaxed whitespace-pre-wrap" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </p>
                </section>
                
                <section class="mb-6">
                    <h2 class="text-xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        Required Skills
                    </h2>
                    <div class="flex flex-wrap gap-2 text-sm" style="color: var(--color-text-card);">
                        <?php 
                            $skills = explode(',', $job['required_skills']);
                            foreach ($skills as $skill):
                        ?>
                            <span class="px-3 py-1 rounded-full" style="background-color: var(--color-bg-dashboard); border: 1px solid var(--color-border);">
                                <?php echo trim(htmlspecialchars($skill)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-bold mb-3 border-b pb-1" style="color: var(--color-text-card); border-color: var(--color-border);">
                        About <?php echo htmlspecialchars($job['company_name']); ?>
                    </h2>
                    <p class="text-sm leading-relaxed" style="color: var(--color-text-card); opacity: 0.9;">
                        <?php echo nl2br(htmlspecialchars($job['company_description'] ?? 'No official company description provided.')); ?>
                    </p>
                </section>

            </div>
        </main>
        
            <aside class="w-full md:w-80 p-6 border-l" 
            style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">

            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Quick Actions</h2>
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">

                            <?php 
                                // လျှောက်လွှာတင်ပြီးမှသာ Chat ခလုတ်ပေါ်လာသင့်သည်
                                if ($applied_status): 
                            ?>
            <a href="chat.php?app_id=<?php echo $application_id_if_applied; ?>" 
            class="block px-4 py-3 rounded-lg font-semibold text-white text-center"
            style="background-color: var(--color-accent); cursor: pointer;">
            <i class="fa fa-comment mr-2"></i> Chat with Recruiter
            </a>
                            <?php else: ?>
                            <span class="block px-4 py-3 rounded-lg text-center opacity-70" style="color: var(--color-text-card);">
                                Chat available after application.
                            </span>
                            <?php endif; ?>

                            <a href="<?php echo htmlspecialchars($job['website_url'] ?? '#'); ?>" target="_blank" 
            class="block px-4 py-2 rounded-lg hover:underline" 
            style="background-color: var(--color-bg-main); border: 1px solid var(--color-border); color: var(--color-text-card);">
            <i class="fa fa-globe mr-2"></i> Visit Company Website
            </a>
                            
                            <a href="company_profile.php?company_id=<?php echo $job['company_id']; ?>" 
                            class="block px-4 py-2 rounded-lg hover:underline"
                            style="background-color: var(--color-bg-main); border: 1px solid var(--color-border); color: var(--color-text-card);">
                            <i class="fa fa-building mr-2"></i> View Company Profile
                            </a>
                            
            </nav>
            </aside>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
    <script>
        $(document).ready(function() {
            $('#applyButton').on('click', function() {
                const jobId = $(this).data('job-id');

                Swal.fire({
                    title: 'Confirm Application?',
                    text: "You are about to submit your application for this job.",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--color-primary)', // Use your primary color
                    cancelButtonColor: '#ef4444',
                    confirmButtonText: 'Yes, Apply!',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return $.ajax({
                            url: 'apply_job.php',
                            type: 'POST',
                            dataType: 'json',
                            data: { job_id: jobId }
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                             // Handle PHP/server errors (e.g., if apply_job.php throws 500)
                             return Swal.showValidationMessage(`Request failed: ${textStatus} ${errorThrown}`);
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        const response = result.value;
                        
                        if (response.status === 'success') {
                            Swal.fire({
                                title: 'Success!',
                                text: response.message,
                                icon: 'success'
                            }).then(() => {
                                // Reload page to update 'Already Applied' status
                                window.location.reload(); 
                            });
                        } else if (response.status === 'resume_error') {
                            Swal.fire({
                                title: 'CV Required',
                                text: response.message,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Go to CV Upload'
                            }).then((r) => {
                                if (r.isConfirmed) {
                                    window.location.href = 'cv_upload.php';
                                }
                            });
                        } else {
                            // General or Already Applied error
                            Swal.fire('Failed', response.message, 'error');
                        }
                    }
                });
            });
        });
    </script>
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>