<?php
// redirect.php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Login ဝင်ထားခြင်း မရှိရင် Home/Login page ကို ပြန်ပို့ရန်
    header("Location: index.php"); 
    exit();
}

$role = $_SESSION['role'];

// Role အလိုက် သက်ဆိုင်ရာ Dashboard ကို ပို့ဆောင်ရန်
switch ($role) {
    case 'admin':
        header("Location: admin/dashboard.php");
        break;
    case 'student':
        header("Location: student/dashboard.php");
        break;
    case 'company':
        header("Location: company/dashboard.php");
        break;
    default:
        // Role မသိပါက Session ဖျက်ပြီး Home/Login page ကို ပြန်ပို့ရန်
        session_unset();
        session_destroy();
        header("Location: index.php?error=invalid_role");
        break;
}
exit();