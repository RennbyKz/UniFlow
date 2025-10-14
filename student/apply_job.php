<?php
// student/apply_job.php (Final Logic - JSON Output)
ob_start(); 
require_once '../core/functions.php'; 
init_system('apply_job', 'student'); 

global $pdo;
$user_id = $_SESSION['user_id'];
// Job ID ကို POST ကနေ ယူပါ
$job_id = isset($_POST['job_id']) ? (int)sanitize_input($_POST['job_id']) : 0;

// Default error message 
$response = ['status' => 'error', 'message' => (isset($lang['error_general_try_again']) ? $lang['error_general_try_again'] : 'An unknown error occurred.')];

// ------------------------------------------
// 1. INPUT VALIDATION & ACTION HANDLING (POST ONLY)
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $job_id) {
    try {
        // 1. Student ID, Resume Path ကို ရယူခြင်း
        $stmt = $pdo->prepare("SELECT student_id, resume_path FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student_data = $stmt->fetch();
        $student_id = $student_data['student_id'] ?? null;
        $resume_path = $student_data['resume_path'] ?? null;

        if (!$student_id) {
            $response['message'] = 'User data incomplete. Please relog in.';
        } elseif (empty($resume_path)) {
            // Check 1: Resume Missing
            $response['status'] = 'resume_error';
            $response['message'] = (isset($lang['error_resume_missing']) ? $lang['error_resume_missing'] : 'Please upload your Resume/CV before applying.');
        } else {
            // Check 2: Already Applied
            $stmt = $pdo->prepare("SELECT status FROM applications WHERE student_id = ? AND job_id = ?");
            $stmt->execute([$student_id, $job_id]);
            $current_status = $stmt->fetchColumn();

            if ($current_status) {
                $response['status'] = 'warning';
                $response['message'] = (isset($lang['error_already_applied']) ? $lang['error_already_applied'] : 'You have already applied for this job.');
            } else {
                // Check 3: Final Insertion
                $stmt = $pdo->prepare("INSERT INTO applications (student_id, job_id, status) VALUES (?, ?, 'Pending')");
                $stmt->execute([$student_id, $job_id]);
                
                $response['status'] = 'success';
                $response['message'] = (isset($lang['job_apply_success']) ? $lang['job_apply_success'] : 'Application submitted successfully!');
            }
        }

    } catch (PDOException $e) {
        $response['message'] = 'Database Error: ' . $e->getMessage();
    }
}

// ------------------------------------------
// 2. JSON Response ဖြင့် ပြန်ပို့ခြင်း (AJAX Call အတွက်)
// ------------------------------------------
header('Content-Type: application/json');
echo json_encode($response);
ob_end_flush();
exit();
// This JSON output is the only thing the AJAX call should receive.
?>