<?php
// core/check_username.php
// 💡 NOTE: ဤ File ကို AJAX မှ ခေါ်ယူရန် ရည်ရွယ်သည်။

header('Content-Type: application/json');

// Session/init ကို ခေါ်စရာ မလို၊ DB Connection ကိုသာ တိုက်ရိုက် ခေါ်ပါမည်။
require_once 'config.php';
require_once 'db.php'; 
require_once 'functions.php'; // sanitize_input() function အတွက်

$email = sanitize_input($_GET['email'] ?? '');
$response = ['available' => false];

if (!empty($email)) {
    try {
        global $pdo;
        // users table တွင် ထို username ရှိမရှိ စစ်ဆေးခြင်း
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $count = $stmt->fetchColumn();
        
        if ($count === 0) {
            $response['available'] = true; // email ကို အသုံးပြုနိုင်သည်
        }
    } catch (PDOException $e) {
        // Database Error ရှိလျှင် လုံခြုံရေးအရ available = false ပေးပါ
        $response['error'] = 'Database error occurred.';
    }
}

echo json_encode($response);
?>