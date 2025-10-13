<?php
require_once '../vendor/autoload.php'; // Dompdf Autoloader (Make sure you ran 'composer require dompdf/dompdf')
require_once '../core/functions.php'; 
ob_start();

// User & Role Check (Same as view_profile_summary.php)
init_system('student_pdf_generation', 'student'); 

use Dompdf\Dompdf;
use Dompdf\Options;

// ------------------------------------------
// 1. Data Fetching (Copied from view_profile_summary.php)
// ------------------------------------------
global $pdo;
$user_id = $_SESSION['user_id'];
$profile = [];
$skills = [];
$experiences = [];
$projects = [];
$certificates = [];

try{
    // 1. Profile Core Data
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username
        FROM students s 
        JOIN users u ON s.user_id = u.user_id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    $student_id = $profile['student_id'] ?? 0;
    if (!$profile) {
        // Handle case where profile is not found (although init_system should prevent this)
        exit("Profile data not found.");
    }
    
    // 2. Skills, Experiences, Projects (Dynamic Data)
    $stmt = $pdo->prepare("SELECT skill_name, proficiency_level FROM student_skills WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $skills = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT exp_id, title, company, start_date, end_date FROM student_experiences WHERE student_id = ? ORDER BY start_date DESC");
    $stmt->execute([$student_id]);
    $experiences = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT project_id, title, project_url, project_image_path, technologies, description FROM student_projects WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $projects = $stmt->fetchAll();

    // 3. Certificates Data
    $stmt = $pdo->prepare("SELECT cert_id, cert_name, issuer, issue_date, cert_path FROM student_certificates WHERE student_id = ? ORDER BY issue_date DESC");
    $stmt->execute([$student_id]);
    $certificates = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    exit("An error occurred while fetching data.");
}

// ------------------------------------------
// 2. Prepare Filename
// ------------------------------------------
$student_name_raw = $profile['full_name'] ?? 'student';
$filename_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', $student_name_raw)));
$filename = $filename_name . '_cvform.pdf';

// ------------------------------------------
// 3. Capture the Clean HTML Content
// ------------------------------------------

// Capture the output of the clean PDF template
include 'view_profile_summary_pdf.php'; // <<-- This file now exists!

$html = ob_get_clean();
// ------------------------------------------

// 4. Instantiate Dompdf and Render
$options = new Options();
// Enable to load external files like images (if you need the profile photo/project images)
$options->set('isRemoteEnabled', true); 
// Set default font for Unicode support (Myanmar font might need separate loading, but DejaVu Sans helps with general Unicode)
$options->set('defaultFont', 'DejaVu Sans');      
$dompdf = new Dompdf($options);

// Load the HTML into Dompdf
$dompdf->loadHtml($html);

// Setup the paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF (forces download with the desired filename)
// 'D' means Download: send the file to the browser and force a download
$dompdf->stream($filename, ["Attachment" => true]);

exit();