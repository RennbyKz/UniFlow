<?php
// student/chat.php
ob_start(); 
require_once '../core/functions.php'; 
// Role ကို စစ်ဆေးရန်အတွက် user_id ကိုသာ စစ်ဆေးသည်
init_system('chat_window'); 
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ------------------------------------------
// 1. Initialization & Data Fetch
// ------------------------------------------

global $pdo, $lang;
$user_id = $_SESSION['user_id'];
$application_id = isset($_GET['app_id']) ? (int)sanitize_input($_GET['app_id']) : 0;
$chat_error = '';
$current_role = $_SESSION['role'] ?? 'guest';
$chat_partner_name = ($current_role === 'student') ? 'Recruiter' : 'Applicant'; 
$job_title = 'Chat Room';

if (!$application_id) {
    set_session_message('error_invalid_chat_room', 'error');
    header("Location: dashboard.php"); ob_end_flush(); exit();
}

try {
    // Job, Company, Student Data ကို ရယူပြီး Chat Partner Name ကို သတ်မှတ်ခြင်း
    $stmt = $pdo->prepare("
        SELECT 
            j.title AS job_title,
            c.name AS company_name,
            s.full_name AS student_name,
            j.job_id AS job_id 
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN companies c ON j.company_id = c.company_id
        JOIN students s ON a.student_id = s.student_id
        WHERE a.application_id = ?
    ");
    $stmt->execute([$application_id]);
    $chat_info = $stmt->fetch();

    if (!$chat_info) {
        set_session_message('error_chat_room_not_found', 'error');
        header("Location: dashboard.php"); ob_end_flush(); exit();
    }

    $job_title = $chat_info['job_title'];
    $job_id = $chat_info['job_id'];
    
    // Chat Partner ၏ နာမည်ကို လက်ရှိ role ပေါ်မူတည်၍ သတ်မှတ်သည်
    if ($current_role === 'student') {
        $chat_partner_name = $chat_info['company_name'] . ' Recruiter';
    } elseif ($current_role === 'company') {
        $chat_partner_name = $chat_info['student_name'] . ' (Applicant)'; 
    }

} catch (PDOException $e) {
    $chat_error = 'Error loading chat details.';
}

// Determine the layout classes based on role
$is_student_layout = ($current_role === 'student');

// 💡 Student (Dashboard-like) Layout: justify-start (for left alignment), no ml-64 offset.
$main_content_class = $is_student_layout ? 'flex-grow flex justify-center p-8' : 'flex-grow flex justify-center ml-64 p-8';

// 💡 Chat Window Size & Placement: max-w-3xl (smaller than max-w-4xl) to allow extra space on the left/right.
// Added 'ml-auto mr-auto' to the main chat window container for centering within its space.
$chat_window_class = $is_student_layout ? 'w-full max-w-3xl ml-1 mr-8' : 'w-full max-w-3xl';
// Note: mr-8 added to separate chat window from sticky sidebar

// ------------------------------------------
// 2. HTML Output
// ------------------------------------------

include '../templates/header.php'; 
?>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>

    <div class="flex flex-1 pt-16"> 
        
        <?php if (!$is_student_layout): ?>
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                // Company Links (UNCHANGED)
                echo sidebar_link('../company/dashboard.php', 'fa-tachometer-alt', 'company_dashboard', 'dashboard.php');
                echo sidebar_link('../company/manage_applications.php', 'fa-envelope-open-text', 'manage_applications', 'manage_applications.php');
                echo sidebar_link('../company/post_job.php', 'fa-plus-circle', 'post_new_job', 'post_job.php');
                echo sidebar_link('../company/profile.php', 'fa-user-circle', 'company_profile', 'profile.php');
                // Back to Review Page (Specific to this chat)
                echo sidebar_link('../company/review_applicant.php?application_id=' . $application_id, 'fa-arrow-left', 'back_to_review', 'review_applicant.php');
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>
        <?php endif; ?>

        <main class="<?php echo $main_content_class; ?>" style="background-color: var(--color-bg-main);">
            
            <div class="<?php echo $chat_window_class; ?> h-[85vh] flex flex-col rounded-xl shadow-2xl border" 
                 style="background-color: var(--color-bg-card); border-color: var(--color-border); margin-top: 20px;">
                
                <div class="p-4 border-b flex justify-between items-center" style="border-color: var(--color-primary);">
                    <h2 class="text-xl font-bold" style="color: var(--color-primary);">
                        Chat for: <?php echo htmlspecialchars($job_title); ?>
                    </h2>
                    <div class="flex items-center space-x-3">
                        <p class="text-sm opacity-80" style="color: var(--color-text-card);">
                            Talking with: <?php echo htmlspecialchars($chat_partner_name); ?>
                        </p>
                    </div>
                </div>
                
                <?php display_session_messages($lang); ?>

                <div id="messagesContainer" class="flex-1 p-4 overflow-y-auto space-y-3" style="background-color: var(--color-bg-main);">
                    <?php if ($chat_error): ?>
                        <div class="text-red-500 text-center"><?php echo $chat_error; ?></div>
                    <?php else: ?>
                        <div class="text-center text-gray-500">Loading messages...</div>
                    <?php endif; ?>
                </div>

                <div class="p-4 border-t" style="border-color: var(--color-border);">
                    <div id="chatFormContainer">
                        <input type="hidden" id="appId" value="<?php echo $application_id; ?>">
                        <textarea id="messageInput" rows="2" 
                                  class="w-full p-3 border rounded-lg resize-none mb-2" 
                                  placeholder="Type your message here..."
                                  style="border-color: var(--color-border); background-color: var(--color-bg-main); color: var(--color-text-card);"></textarea>
                        <button id="sendMessageBtn" 
                                class="button-primary w-full py-2 rounded-lg text-white font-semibold" 
                                style="background-color: var(--color-primary);">
                            Send Message <i class="fa fa-paper-plane ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php if ($is_student_layout): ?>
            <aside class="w-full md:w-80 p-6 border-l flex-shrink-0" 
                   style="background-color: var(--color-bg-card); border-color: var(--color-border); height: 100%; position: sticky; top: 60px;">
                   
                <h2 class="text-xl font-bold mb-4" style="color: var(--color-text-card);">Navigation</h2>
                
                <div class="mb-6 pb-4 border-b" style="border-color: var(--color-border);">
                    <p class="text-sm opacity-80" style="color: var(--color-text-card);">
                        Chatting about: **<?php echo htmlspecialchars($job_title); ?>**
                    </p>
                </div>
                
                <nav class="space-y-3">
                    <?php
                    // Student Links (Matching Dashboard's right sidebar)
                    echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'student_dashboard', 'dashboard.php');
                    echo sidebar_link('job_details.php?job_id=' . $job_id, 'fa-briefcase', 'back_view_job_details', 'job_details.php');
                    echo sidebar_link('my_applications.php', 'fa-file-alt', 'my_applications', 'my_applications.php');
                    echo sidebar_link('view_jobs.php', 'fa-briefcase', 'browse_jobs', 'view_jobs.php');                
                    echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                    ?>
                </nav>
            </aside>
            <?php endif; ?>

        </main>
    </div>
    
   <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> 
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        const messagesContainer = $('#messagesContainer');
        const appId = $('#appId').val();
        const currentUserId = <?php echo $user_id; ?>;
        let lastMessageCount = 0;

        // Simple HTML Sanitizer for user generated content (Basic)
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return str;
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }

        // Function to handle message deletion via AJAX
        function handleDeleteMessage(messageId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this message!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                // ✅ 1. URL PATH ကို root-relative path ဖြင့် ပြင်ဆင်ခြင်း
                url: '/uniflow/api/chat_api.php', 
                type: 'POST', 
                dataType: 'json',
                data: { 
                    message_id: messageId,
                    app_id: appId, // app_id ကို ပို့ထားတာ မှန်ကန်ပါတယ်
                    _method: 'DELETE'
                }
            }).done(function(response) {
                if (response.status === 'success') {
                    Swal.fire('Deleted!', 'Your message has been deleted.', 'success');
                    fetchMessages(); 
                } else {
                    // 403 Error message ကို API က JSON အနေနဲ့ ပြန်ပို့ရင် ဒီမှာ ဖမ်းမိမယ်
                    Swal.fire('Error', response.message, 'error');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) { // ⬅️ jqXHR ကို ဖမ်းယူခြင်း
                let errorMessage = 'Server connection failed.';
                if (jqXHR.status === 403) {
                    // 403 Forbidden ဆိုရင် API က message ပြန်မပို့ရင်တောင် ဒီ Error ကို ပြနိုင်တယ်
                    errorMessage = 'Access denied: You do not have permission to delete this message.';
                } else if (jqXHR.status > 0) {
                    errorMessage = `API Error (Status: ${jqXHR.status}). Check server logs.`;
                }
                
                // ✅ 2. Error Message ကို Status Code နဲ့အတူ ပိုတိကျစွာ ပြသခြင်း
                Swal.fire('Error', errorMessage, 'error');
            });
        }
    });
}


        // Function to render messages
        function renderMessages(messages) {
            if (messages.length === lastMessageCount) {
                return; // No new messages to render
            }
            lastMessageCount = messages.length;
            
            const isScrolledToBottom = messagesContainer[0].scrollHeight - messagesContainer[0].clientHeight <= messagesContainer[0].scrollTop + 1;

            messagesContainer.empty(); 
            
            if (messages.length === 0) {
                messagesContainer.append('<div class="text-center text-gray-500">Start the conversation!</div>');
                return;
            }

            messages.forEach(msg => {
                const isSender = msg.sender_id == currentUserId;
                const alignment = isSender ? 'justify-end' : 'justify-start';
                const bgColor = isSender ? 'var(--color-primary)' : 'var(--color-bg-dashboard)';
                const textColor = isSender ? 'white' : 'var(--color-text-card)';
                
                const time = new Date(msg.sent_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                const deleteButton = isSender 
                    ? `<button class="delete-message-btn text-xs ml-2 opacity-70 hover:opacity-100" 
                               data-msg-id="${msg.message_id}" 
                               style="color: ${isSender ? 'white' : 'red'};">
                               <i class="fa fa-trash-alt"></i>
                           </button>` 
                    : '';
                
                const messageHtml = `
                    <div class="flex ${alignment}">
                        <div class="max-w-xs md:max-w-md px-4 py-2 rounded-xl shadow-md flex items-center" 
                             style="background-color: ${bgColor}; color: ${textColor};">
                            <p class="text-sm leading-snug">${htmlspecialchars(msg.message_content)}</p>
                            <span class="text-xs opacity-70 block ml-3">${time}</span>
                            ${deleteButton} 
                        </div>
                    </div>
                `;
                messagesContainer.append(messageHtml);
            });

            // Scroll to the bottom only if the user was near the bottom before the refresh
            if (isScrolledToBottom) {
                 messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
            }
        }

        // Function to fetch messages (Polling)
        function fetchMessages() {
            $.ajax({
                url: '../api/chat_api.php',
                type: 'GET',
                dataType: 'json',
                data: { app_id: appId }
            }).done(function(response) {
                if (response.status === 'success') {
                    renderMessages(response.messages);
                } else {
                    console.error("Failed to fetch messages:", response.message);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                if (jqXHR.status === 403) {
                     clearInterval(pollingInterval);
                     messagesContainer.html('<div class="text-red-500 text-center">Chat access denied.</div>');
                } else {
                     console.error("API error during fetch:", textStatus, errorThrown);
                }
            });
        }
        
        // Function to send message
        $('#sendMessageBtn').on('click', function() {
            const input = $('#messageInput');
            const content = input.val().trim();
            if (content === '') return;

            input.prop('disabled', true);
            
            $.ajax({
                url: '../api/chat_api.php',
                type: 'POST',
                dataType: 'json',
                data: { app_id: appId, content: content }
            }).done(function(response) {
                if (response.status === 'success') {
                    input.val(''); // Clear input
                    fetchMessages(); // Refresh message list
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }).fail(function() {
                Swal.fire('Server Error', 'Could not connect to the chat service.', 'error');
            }).always(function() {
                input.prop('disabled', false);
                input.focus();
            });
        });
        
        // Message Delete Event Listener
        messagesContainer.on('click', '.delete-message-btn', function(e) {
            e.preventDefault();
            const messageId = $(this).data('msg-id');
            handleDeleteMessage(messageId);
        });

        // Polling setup (Fetch every 3 seconds)
        fetchMessages(); // Initial fetch
        const pollingInterval = setInterval(fetchMessages, 3000); // Poll every 3 seconds
        
    });
    
    </script>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>