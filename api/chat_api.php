<?php
// api/chat_api.php
ob_start();
// core/functions.php ကို ခေါ်ယူပါ
require_once '../core/functions.php'; 

// ဤ API သည် user role မခွဲခြားဘဲ၊ logged in ဖြစ်ရုံဖြင့် ဝင်ရောက်နိုင်ရမည်
init_system('chat_api'); 

global $pdo, $lang;

header('Content-Type: application/json');
$user_id = $_SESSION['user_id'] ?? 0;
$response = ['status' => 'error', 'message' => $lang['error_general_try_again'] ?? 'An unknown error occurred.'];


if (!$user_id) {
    // User ID မရှိပါက (Login မလုပ်ရသေးပါက)
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in.']);
    ob_end_flush();
    exit();
}

// 1. DELETE Method Override ကို စစ်ဆေးခြင်း
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_REQUEST['_method']) && strtoupper($_REQUEST['_method']) === 'DELETE') {
    $method = 'DELETE';
}

// 2. application_id ကို တစ်ကြိမ်သာ တန်ဖိုးယူခြင်း
$application_id = isset($_REQUEST['app_id']) ? (int)sanitize_input($_REQUEST['app_id']) : 0;


// 3. Application ID မရှိမဖြစ်လိုအပ်ကြောင်း စစ်ဆေးခြင်း
if (!$application_id) {
    // GET/POST (Send) အတွက် application_id မရှိရင် 400 ပြန်ပေး
    // DELETE အတွက် message_id သာ လိုတာကြောင့် ဒီ check ကို ကျော်ဖို့လို
    if ($method !== 'DELETE') { 
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Application ID is missing.']);
        ob_end_flush();
        exit();
    }
}


// ------------------------------------------
// 1. Authorization Check (ဤ Application ID သည် လက်ရှိ user နှင့် သက်ဆိုင်ကြောင်း စစ်ဆေးခြင်း)
//    DELETE request သည် application_id မပါဘဲ message_id ကိုသာသုံးပြီး delete_message() ထဲမှာ စစ်တာဖြစ်လို့
//    application_id ရှိမှသာ ဒီ Authorization check ကို လုပ်ဆောင်ပါမယ်။
// ------------------------------------------

if ($application_id) {
    try {
        // ဤလျှောက်လွှာသည် လက်ရှိ Company သို့မဟုတ် Student မှ ဖန်တီးထားခြင်း ဟုတ်၊ မဟုတ် စစ်ဆေးသည်
        $stmt = $pdo->prepare("
            SELECT 
                a.application_id 
            FROM applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN jobs j ON a.job_id = j.job_id
            JOIN companies c ON j.company_id = c.company_id
            WHERE a.application_id = ? AND (s.user_id = ? OR c.user_id = ?)
        ");
        $stmt->execute([$application_id, $user_id, $user_id]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied to this chat room.']);
            ob_end_flush();
            exit();
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error during authorization check.']);
        ob_end_flush();
        exit();
    }
}


// ------------------------------------------
// 2. ACTION HANDLING (GET for Fetch, POST for Send, DELETE for Delete)
// ------------------------------------------

if ($method === 'POST') {
    // ========== SEND MESSAGE (POST) ==========
    $content = isset($_POST['content']) ? sanitize_input($_POST['content']) : '';

    // POST/GET က application_id မရှိရင် အပေါ်မှာ 400 ပြန်ပြီးသားဖြစ်လို့ ဒီနေရာမှာ $application_id ရှိနေမယ်

    if (empty($content)) {
        http_response_code(400);
        $response['message'] = 'Message content cannot be empty.';
    } elseif (send_message($application_id, $user_id, $content)) {
        // စာပို့ပြီးပါက၊ မဖတ်ရသေးသော အခြေအနေမှန်သမျှကို ဖတ်ပြီးကြောင်း အမှတ်အသားပြုပါ
        mark_messages_as_read($application_id, $user_id); 
        $response = ['status' => 'success', 'message' => 'Message sent successfully.'];
    } else {
        $response['message'] = 'Failed to send message.';
    }

} elseif ($method === 'DELETE') {
    // ========== DELETE MESSAGE (POST with _method=DELETE) ==========
    
    // message_id ကို POST data မှ ရယူပါ
    $message_id = isset($_REQUEST['message_id']) ? (int)sanitize_input($_REQUEST['message_id']) : 0;
    error_log("Attempting DELETE: Message ID=" . $message_id . ", Session User ID=" . $user_id);

    if (!$message_id) {
        http_response_code(400);
        $response['message'] = 'Message ID is missing for deletion.';
    } elseif (delete_message($message_id, $user_id)) {
        // delete_message function သည် authorization ကိုပါ စစ်ဆေးရမည် (ဤ user ၏ စာဟုတ်၊ မဟုတ်)
        $response = ['status' => 'success', 'message' => 'Message deleted successfully.'];
    } else {
        // delete_message function သည် authorization မအောင်မြင်ပါက false ပြန်ပေးနိုင်သည်
        http_response_code(403);
        $response['message'] = 'Failed to delete message or you do not have permission.';
    }

} elseif ($method === 'GET') {
    // ========== FETCH MESSAGES (GET) ==========
    
    // 1. စာတိုများ ရယူပါ
    $messages = get_messages($application_id); 

    // 2. စာတိုများ ရယူပြီးနောက် လက်ရှိ user အတွက် မဖတ်ရသေးသော စာများကို ဖတ်ပြီးကြောင်း မှတ်သားပါ
    mark_messages_as_read($application_id, $user_id);
    
    $response = [
        'status' => 'success',
        'messages' => $messages,
        'user_id' => $user_id // Frontend တွင် စာပို့သူကို စစ်ဆေးရန်အတွက်
    ];
} else {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
}

// ------------------------------------------
// 3. FINAL OUTPUT
// ------------------------------------------
echo json_encode($response);
ob_end_flush();
?>