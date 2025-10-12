<?php
// logout.php
session_start();

// Session များဖျက်
session_unset();
session_destroy();

// ✅ Logout Success Message Session
session_start(); // destroy လုပ်ပြီးပြန်စဖို့လိုတယ်
$_SESSION['logout_success'] = true;

// ✅ Redirect to login page (e.g. index.php)
header("Location: index.php");
exit();
