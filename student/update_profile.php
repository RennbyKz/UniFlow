<?php
// student/update_profile.php
ob_start(); 
require_once '../core/functions.php'; 
// စနစ်ကို စတင်ပါ (Student Role လိုအပ်)
init_system('student_update_profile', 'student'); 

// ------------------------------------------
// 1. Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$user_id = $_SESSION['user_id'];
$student_data = [];
$errors = [];

try {
    // 1. Student ရဲ့လက်ရှိ Data ကို ရယူခြင်း (Form မှာ ပြသရန်)
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student_data = $stmt->fetch();
    
    if (!$student_data) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: ../logout.php");
        ob_end_flush();
        exit();
    }
} catch (PDOException $e) {
    set_session_message('error_general_try_again', 'error');
    $errors[] = $lang['error_general_try_again'] . " (Data Fetch Error)";
    $errors[] = "Database Error: " . $e->getMessage();
}

// ------------------------------------------
// 2. FORM SUBMISSION (Data Update & File Handling)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input Data များ
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $major = sanitize_input($_POST['major'] ?? '');
    $gpa = sanitize_input($_POST['gpa'] ?? '');
    $contact_phone = sanitize_input($_POST['contact_phone'] ?? '');
    $dob = sanitize_input($_POST['dob'] ?? '');
    if ($dob === '') {
    $dob = null;
    }
    $gender = sanitize_input($_POST['gender'] ?? ''); 
    $address = sanitize_input($_POST['address'] ?? '');
    $linkedin_url = sanitize_input($_POST['linkedin'] ?? '');
    $github_url = sanitize_input($_POST['github'] ?? '');
    $career_objective = sanitize_input($_POST['career_objective'] ?? '');
    $university_name = sanitize_input($_POST['university_name'] ?? '');
    $year_of_study = sanitize_input($_POST['year_of_study'] ?? '');
    $education = sanitize_input($_POST['education'] ?? '');



    // Profile Photo Upload Logic
    $photo_path = $student_data['photo_path'] ?? '';
    $upload_success = true;  
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $photo_file = $_FILES['profile_photo'];
        $allowed_photo_ext = ['jpg', 'jpeg', 'png', 'gif'];
        $photo_ext = strtolower(pathinfo($photo_file['name'], PATHINFO_EXTENSION));
        $max_photo_size = 2 * 1024 * 1024; // 2 MB

        if (!in_array($photo_ext, $allowed_photo_ext)) {
            $errors[] = $lang['error_invalid_file_type'] ?? 'Invalid file type for profile photo. Only JPG/JPEG/PNG/GIF are allowed.';
            $upload_success = false;
        } elseif ($photo_file['size'] > $max_photo_size) {
            $errors[] = $lang['error_file_too_large'] ?? 'Profile photo size must not exceed 2MB.';
            $upload_success = false;
        } else {
            $new_photo_name = 'profile_' . $student_data['student_id'] . '_' . uniqid() . '.' . $photo_ext;
            $photo_upload_dir = '../uploads/profile_photos/';

            if (!is_dir($photo_upload_dir)) { mkdir($photo_upload_dir, 0777, true); }

            if (move_uploaded_file($photo_file['tmp_name'], $photo_upload_dir . $new_photo_name)) {
                $photo_path = 'uploads/profile_photos/' . $new_photo_name;
            } else {
                $errors[] = $lang['error_file_upload_failed'] ?? 'Profile photo upload failed.';
                $upload_success = false;
            }
        }
    }

    // 3. Database Update (Profile Data)
    if (empty($errors) && $upload_success) {
        try {
            $stmt = $pdo->prepare("
                UPDATE students SET 
                full_name = :name,
                major = :major,
                gpa = :gpa,
                contact_phone = :phone,
                dob = :dob,
                gender = :gender,
                address = :address,
                linkedin_url = :linkedin,
                github_url = :github,
                career_objective = :career,
                university_name = :university,
                year_of_study = :year_study,
                photo_path = :photo_path,
                education = :education
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':name' => $full_name,
                ':major' => $major,
                ':gpa' => $gpa,
                ':phone' => $contact_phone,
                ':gender' => $gender,
                ':dob' => $dob,
                ':address' => $address,
                ':linkedin' => $linkedin_url,
                ':github' => $github_url,
                ':career' => $career_objective,
                ':university' => $university_name,
                ':year_study' => $year_of_study,
                ':photo_path' => $photo_path,
                ':education' => $education,
                ':user_id' => $user_id
            ]);

            set_session_message('profile_update_success', 'success');
            // Redirect to refresh data and clear POST
            header("Location: profile.php");
            ob_end_flush();
            exit();

        } catch (PDOException $e) {
            $errors[] = $lang['error_general_try_again'] . " (DB Update Error)";
             $errors[] = "Database Error: " . $e->getMessage();
        }
    }
    
    // Error Toast ကို ထုတ်လွှတ်ရန်
    if (!empty($errors)) {
        foreach ($errors as $err) { set_session_message($err, 'error'); } 
        header("Location: update_profile.php");
        ob_end_flush();
        exit();
    }
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<!-- ✅ Toast container -->
<div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css"> 

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1 pt-16">
        
        <main class="flex-grow p-8" style="background-color: var(--color-bg-main);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-main); border-color: var(--color-border);">
                <?php echo $lang['student_profile']; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="w-full max-w-6xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">

                <!-- Profile Photo Display -->
                <div class="flex justify-center mb-8">
                    <?php if (!empty($student_data['photo_path']) && file_exists('../' . $student_data['photo_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($student_data['photo_path']); ?>" 
                             alt="Profile Photo" 
                             class="w-32 h-32 rounded-full object-cover border-4 border-primary">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-gray-300 flex items-center justify-center border-4 border-primary text-gray-600 text-6xl">
                            <i class="fa fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <h2 class="text-2xl font-bold mb-4" style="color: var(--color-text-card);">Personal & Academic Details</h2>

                <form method="POST" action="update_profile.php" enctype="multipart/form-data" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-b pb-4">
                        <div>
                            <label for="username" class="block font-medium mb-1 opacity-70">Username (Login ID):</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($student_data['username'] ?? ''); ?>"
                                   readonly disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100 dark:bg-gray-700 opacity-80">
                        </div>
                        <div>
                            <label for="email" class="block font-medium mb-1 opacity-70">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student_data['email'] ?? ''); ?>"
                                   readonly disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100 dark:bg-gray-700 opacity-80">
                        </div>
                    </div>

                    <!-- Profile Photo Upload -->
                    <div>
                        <label for="profile_photo" class="block font-medium mb-1">Profile Photo:</label>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="block w-full text-sm text-gray-500
                               file:mr-4 file:py-2 file:px-4
                               file:rounded-full file:border-0
                               file:text-sm file:font-semibold
                               file:bg-primary file:text-white
                               hover:file:bg-primary-dark
                               "/>
                        <small class="text-gray-500">Allowed file types: JPG, JPEG, PNG, GIF. Max size: 2MB.</small>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-xl font-semibold mb-3">Profile Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="full_name" class="block font-medium mb-1">Full Name:</label>
                                <input type="text" id="full_name" name="full_name" autocomplete="name"
                                       value="<?php echo htmlspecialchars($student_data['full_name'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label for="contact_phone" class="block font-medium mb-1">Phone Number:</label>
                                <input type="tel" id="contact_phone" name="contact_phone" autocomplete="tel"
                                       value="<?php echo htmlspecialchars($student_data['contact_phone'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="major" class="block font-medium mb-1">Major:</label>
                                <input type="text" id="major" name="major" autocomplete="organization-title"
                                       value="<?php echo htmlspecialchars($student_data['major'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="gpa" class="block font-medium mb-1">GPA:</label>
                                <input type="number" id="gpa" name="gpa" step="0.01" min="0" max="4.0" autocomplete="off"
                                       value="<?php echo htmlspecialchars($student_data['gpa'] ?? ''); ?>" required>
                            </div>
                            <div>
                                <label for="year_of_study" class="block font-medium mb-1">Year of Study:</label>
                                <input type="text" id="year_of_study" name="year_of_study" autocomplete="off"
                                       value="<?php echo htmlspecialchars($student_data['year_of_study'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="dob" class="block font-medium mb-1">Date of Birth:</label>
                            <input type="date" id="dob" name="dob" autocomplete="bday"
                                   value="<?php echo htmlspecialchars($student_data['dob'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="address" class="block font-medium mb-1">Address:</label>
                            <input type="text" id="address" name="address" autocomplete="street-address"
                                   value="<?php echo htmlspecialchars($student_data['address'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="linkedin" class="block font-medium mb-1">LinkedIn URL:</label>
                            <input type="url" id="linkedin" name="linkedin" autocomplete="url"
                                   value="<?php echo htmlspecialchars($student_data['linkedin_url'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="github" class="block font-medium mb-1">GitHub URL:</label>
                            <input type="url" id="github" name="github" autocomplete="url"
                                   value="<?php echo htmlspecialchars($student_data['github_url'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="university_name" class="block font-medium mb-1">University Name:</label>
                            <input type="text" id="university_name" name="university_name" autocomplete="organization"
                                   value="<?php echo htmlspecialchars($student_data['university_name'] ?? ''); ?>" required>
                        </div>
                        <div>
                        <label for="gender" class="block font-medium mb-1" style="color: var(--color-text-card);"><?php echo $lang['label_gender'] ?? 'Gender'; ?>:</label>
                        <select id="gender" name="gender" class="w-full px-4 py-2 border rounded-lg" required>
                            <option value="">-- Select --</option>
                            <option value="Male" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($student_data['gender']) && $student_data['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                        <div>
                            <label for="education" class="block font-medium mb-1">Education:</label>
                            <input type="text" id="education" name="education" autocomplete="off"
                                   value="<?php echo htmlspecialchars($student_data['education'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-xl font-semibold border-b pb-2">Career Goals / Objective</h3>
                        <label for="career_objective" class="sr-only">Career Objective</label>
                        <textarea id="career_objective" name="career_objective" rows="4"
                                  class="w-full p-3 border rounded-lg"><?php echo htmlspecialchars($student_data['career_objective'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="update_profile"
                            class="button-primary px-6 py-2 rounded-lg font-semibold transition duration-300 w-full"
                            style="background-color: var(--color-primary); color: var(--color-bg-card);">
                        <i class="fa fa-save mr-2"></i> <?php echo $lang['button_save_profile']; ?>
                    </button>
                </form>

            </div>
        </main>
        
        <aside class="w-full md:w-80 p-6 border-l" 
               style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">
               
            <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Quick Actions</h2>
            
            <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
                <a href="dashboard.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-tachometer-alt w-5"></i><span><?php echo $lang['student_dashboard']; ?></span>
                </a>
                <a href="view_jobs.php" class="flex items-center gap-3 hover:text-gray-600" style="color: var(--color-text-card);">
                    <i class="fa fa-briefcase w-5"></i><span><?php echo $lang['view_jobs']; ?></span>
                </a>
                <a href="../logout.php" class="flex items-center gap-3 hover:text-red-500" style="color: var(--color-error);">
                    <i class="fa fa-sign-out-alt w-5"></i><span><?php echo $lang['logout']; ?></span>
                </a>
            </nav>
        </aside>
    </div>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?> 
