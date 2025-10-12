<?php
// switch_lang.php (Root Folder တွင် ထားရှိရန်)
session_start();
$new_lang = $_GET['lang'] ?? 'en'; // GET request ကနေ 'lang' value ကို ယူမယ်
$redirect_to = $_GET['redirect'] ?? 'index.php'; // ပြန်သွားရမယ့် လိပ်စာ (URL) ကို ယူမယ်
if ($new_lang === 'en' || $new_lang === 'my') {
    $_SESSION['lang'] = $new_lang; // Session ထဲမှာ Language ကို သတ်မှတ်လိုက်ပြီ
}
header("Location: " . urldecode($redirect_to));
exit();
?>