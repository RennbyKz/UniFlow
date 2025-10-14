<?php
require_once '../core/functions.php';
init_system('delete_application', 'company');

global $pdo;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['application_id'])) {
    $application_id = sanitize_input($_POST['application_id']);

    try {
        $stmt = $pdo->prepare("
            DELETE a FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN companies c ON j.company_id = c.company_id
            WHERE a.application_id = :application_id AND c.user_id = :user_id
        ");
        $stmt->execute([
            ':application_id' => $application_id,
            ':user_id' => $user_id
        ]);

        set_session_message('Application deleted successfully.', 'success');
    } catch (PDOException $e) {
        set_session_message('Error deleting application: ' . $e->getMessage(), 'error');
    }
}

header("Location: history_applicants.php");
exit;
?>
