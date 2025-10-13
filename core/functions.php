<?php
// core/functions.php

// Global Variables
global $pdo, $lang, $current_lang;

/**
 * 💡 System Initialization Function (Pages တိုင်းမှာ ခေါ်ရန်)
 * @param string $title_key Localization Key for the page title.
 * @param string $required_role Check user access (optional).
 */
function init_system($title_key, $required_role = null) {
    global $pdo, $lang, $current_lang, $page_title_key;
    
    // 1. Configuration & DB Connection Load
    require_once 'config.php';
    require_once 'db.php'; // $pdo ကို ဒီကနေ ရရှိမည်

    $page_title_key = $title_key;

    // 2. Localization Logic
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $current_lang = $_SESSION['lang'] ?? 'en';
    $lang = include ROOT_DIR . "/lang/{$current_lang}.php";
    
    // 3. Access Control Check
    if ($required_role && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $required_role)) {
        set_session_message('invalid_role', 'error');
        header("Location: " . get_base_url() . "/index.php"); 
        exit();
    }
}

// ------------------------------------------
// Helper Functions
// ------------------------------------------

function page_title($title_key, $lang) {
    return ($lang[$title_key] ?? $title_key) . " | " . APP_NAME;
}

function set_session_message($message_key, $type = 'success') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['messages'])) {
        $_SESSION['messages'] = [];
    }

    // 💡 Message array ထဲသို့ ထည့်သွင်း
    $_SESSION['messages'][$message_key] = [
        'type' => $type
    ];
}


function display_session_messages($lang) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['messages'])) return;

    echo '<script>
        function showToast(message, type = "success") {
            const container = document.getElementById("toast-container");
            if (!container) return;
            
            const toast = document.createElement("div");
            toast.className = `px-4 py-3 rounded-lg shadow-lg text-white text-sm flex items-center gap-2 animate-fade-in
                ${type === "success" ? "bg-green-600" : (type === "error" ? "bg-red-600" : "bg-yellow-500")}`;
            toast.innerHTML = `<i class="fa ${type === "success" ? "fa-check-circle" : "fa-exclamation-triangle"}"></i> ${message}`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add("animate-fade-out");
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }

        const style = document.createElement("style");
        style.textContent = `
        @keyframes fadeIn { from {opacity: 0; transform: translateY(-10px);} to {opacity: 1; transform: translateY(0);} }
        @keyframes fadeOut { from {opacity: 1;} to {opacity: 0; transform: translateY(-10px);} }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .animate-fade-out { animation: fadeOut 0.4s ease-in forwards; }
        `;
        document.head.appendChild(style);
    </script>';

    // 💡 Toast ပြရန် Message Loop
    foreach ($_SESSION['messages'] as $key => $msg) {
        $type = $msg['type'] ?? 'success';
        $text = $lang[$key] ?? $key;
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast(" . json_encode($text) . ", " . json_encode($type) . "));</script>";
    }

    unset($_SESSION['messages']);
}

function get_base_url() {
    // 💡 Root Directory ကို URL ပုံစံဖြင့် ရယူရန် (localhost/uniflow)
    return "http://localhost/uniflow"; 
}

function sanitize_input($data) {
    $data = trim($data);              // အပတ်လည် space များကို ဖယ်ရှား
    $data = stripslashes($data);      // \ ကို ဖယ်ရှား
    $data = htmlspecialchars($data);  // HTML special characters ကို encode
    return $data;
}

function sidebar_link($href, $icon, $label_key, $current_page_uri = '') {
    global $lang;
    
    // 💡 Active စစ်ဆေးရန်အတွက် Current Request URI ထဲမှာ Link name ပါမပါ စစ်ဆေးသည်
    $uri = $_SERVER['REQUEST_URI'];
    // $href သည် 'dashboard.php' ဖြစ်လျှင် $uri တွင် 'dashboard.php' ပါမပါ စစ်မည်
    $isActive = (strpos($uri, $href) !== false) ? 'bg-opacity-10 font-semibold' : 'hover:bg-opacity-5';
    $color = (strpos($uri, $href) !== false) ? 'var(--color-primary)' : 'var(--color-text)';
    
    // Logout link ကို ခွဲခြားရန်
    if ($label_key === 'logout') {
        $color = 'var(--color-error)';
    }

    return <<<HTML
    <a href="{$href}" class="flex items-center p-3 rounded-lg {$isActive} transition duration-150" 
       style="color: {$color}; background-color: var(--color-bg-card);">
        <i class="fa {$icon} w-5"></i>
        <span class="ml-3">{$lang[$label_key]}</span>
    </a>
    HTML;
}

if (!function_exists('include_utility_bar')) {
    function include_utility_bar() {
        include_once ROOT_DIR . '/templates/header.php'; 
    }
}

function horizontal_link($href, $icon, $label_key, $color_var) {
    global $lang;
    $uri = $_SERVER['REQUEST_URI'];
    // 💡 Active စစ်ဆေးမှု (Current page match)
    $isActive = (strpos($uri, $href) !== false);
    
    $active_classes = $isActive 
        ? 'border-b-2 font-bold' 
        : 'hover:opacity-80';
    
    // Fallback label
    $label = isset($lang[$label_key]) ? $lang[$label_key] : $label_key;
    
    return <<<HTML
    <a href="{$href}" class="flex items-center p-2 rounded-t-md transition duration-150 {$active_classes}" 
       style="color: {$color_var}; border-color: {$color_var};">
        <i class="fa {$icon} w-5 mr-1"></i>
        <span>{$label}</span>
    </a>
    HTML;
}

function send_external_email($recipient_email, $subject, $body_html) {
    require_once ROOT_DIR . '/vendor/autoload.php'; 

    $mail = new PHPMailer(true);
    try {
        // 💡 1. SMTP ကို ဖွင့်ပြီး Host ကို သတ်မှတ်
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;     // e.g., 'smtp.gmail.com'
        $mail->SMTPAuth   = true;
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; 
        // 💡 2. Authentication Credentials
        $mail->Username   = SMTP_USERNAME; // e.g., 'noreplyuniflow@gmail.com'
        $mail->Password   = SMTP_PASSWORD; // 💡 APP PASSWORD (Not Gmail Password)
        
        // 💡 3. Encryption Type and Port
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // Use ENCRYPTION_SMTPS or ENCRYPTION_STARTTLS
        $mail->Port       = SMTP_PORT;     // 465 (for SMTPS) or 587 (for TLS)

        $mail->setFrom(SMTP_USERNAME, APP_NAME . ' Admin'); // 💡 Config မှ Username ကို သုံးသင့်သည်
        // ... (AddAddress and Body) ...
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // ... (Error Logging) ...
error_log("MAIL ERROR: " . $mail->ErrorInfo . " | Recipient: " . $recipient_email);
        return false;
    }
}

function send_internal_notification($student_id, $title, $content, $type = 'info') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO student_notifications (student_id, message_title, message_content, type) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $title, $content, $type]);
    } catch (PDOException $e) {
        error_log("Failed to send internal notification: " . $e->getMessage());
    }
}
?>