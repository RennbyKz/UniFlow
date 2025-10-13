<?php
// student/inbox.php
ob_start(); 
require_once '../core/functions.php'; 
init_system('my_inbox', 'student'); 

global $pdo;
$user_id = $_SESSION['user_id'];
$notifications = [];
$errors = [];

// ---------------------
// Handle Delete All
// ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM student_notifications WHERE student_id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        set_session_message('all_deleted_success', 'success');
    } catch (PDOException $e) {
        set_session_message('delete_error', 'error');
    }
    header("Location: inbox.php");
    exit;
}

// ---------------------
// Initialization & Data
// ---------------------
try {
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_id = $stmt->fetchColumn();
    $_SESSION['student_id'] = $student_id;

    if (!$student_id) {
        set_session_message('profile_data_not_found', 'error');
        header("Location: dashboard.php"); ob_end_flush(); exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM student_notifications WHERE student_id = ? ORDER BY created_at DESC");
    $stmt->execute([$student_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt_read = $pdo->prepare("UPDATE student_notifications SET is_read = TRUE WHERE student_id = ? AND is_read = FALSE");
    $stmt_read->execute([$student_id]);

} catch (PDOException $e) {
    $errors[] = $lang['error_general_try_again'] . " (DB Error)";
}

include '../templates/header.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">

<?php include_utility_bar(); ?>

<div class="flex flex-1 pt-16">
    <main class="flex-grow p-8 flex justify-center" style="background-color: var(--color-bg-main);">
        <div class="w-full max-w-4xl p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">

            <h1 class="text-3xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['my_inbox'] ?? 'Notification Inbox'; ?> (<?php echo count($notifications); ?>)
            </h1>

            <?php display_session_messages($lang); ?>
            <?php if (!empty($errors)): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
                    <?php echo implode('<br>', $errors); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($notifications)): ?>
                <div class="p-6 text-center" style="color: var(--color-text-card); opacity: 0.8;">
                    <i class="fa fa-bell-slash text-4xl mb-4" style="color: var(--color-accent);"></i>
                    <p class="text-xl"><?php echo $lang['no_notifications'] ?? 'Your inbox is empty.'; ?></p>
                </div>
            <?php else: ?>
                <form id="inboxForm" method="POST">
                    <div class="flex justify-end mb-4">
                        <button type="button" onclick="confirmDeleteAll()" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded transition">
                            <i class="fa fa-trash-alt"></i> Delete All
                        </button>
                    </div>

                    <input type="hidden" name="delete_all" value="1" id="deleteAllInput" disabled>

                    <div class="space-y-4">
                        <?php foreach ($notifications as $noti): 
                            switch (htmlspecialchars($noti['type'])) {
                                case 'success':
                                    $icon_data = ['icon' => 'fa-check-circle', 'bg_color' => 'bg-green-100', 'text_color' => 'text-green-700'];
                                    break;
                                case 'warning':
                                    $icon_data = ['icon' => 'fa-exclamation-triangle', 'bg_color' => 'bg-yellow-100', 'text_color' => 'text-yellow-700'];
                                    break;
                                default:
                                    $icon_data = ['icon' => 'fa-info-circle', 'bg_color' => 'bg-blue-100', 'text_color' => 'text-blue-700'];
                                    break;
                            }

                            $unread_style = $noti['is_read'] ? 'opacity-80' : 'font-semibold shadow-md border-2 border-dashed border-blue-300';
                        ?>
                        <div class="p-4 rounded-lg flex items-start gap-4 transition duration-200 <?php echo $unread_style; ?> <?php echo $icon_data['bg_color']; ?>" 
                             style="color: var(--color-text-card);">

                            <i class="fa <?php echo $icon_data['icon']; ?> text-2xl flex-shrink-0 mt-1 <?php echo $icon_data['text_color']; ?>"></i>
                            
                            <div class="flex-grow">
                                <p class="font-bold text-base" style="color: inherit;">
                                    <?php echo htmlspecialchars($noti['message_title']); ?>
                                </p>
                                <p class="text-sm mt-1" style="color: inherit; opacity: 0.9;">
                                    <?php echo nl2br(htmlspecialchars($noti['message_content'])); ?>
                                </p>
                                <p class="text-xs mt-2 <?php echo $icon_data['text_color']; ?>">
                                    Received on: <?php echo date('M d, Y H:i', strtotime($noti['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <aside class="w-full md:w-80 p-6 border-l hidden md:block" 
           style="background-color: var(--color-bg-card); border-color: var(--color-border); min-height: calc(100vh - 60px); position: sticky; top: 60px;">
        <h2 class="text-xl font-bold mb-4" style="color: var(--color-primary);">Quick Navigation</h2>
        <nav class="mt-8 space-y-3 pt-4 border-t" style="border-color: var(--color-border);">
            <?php
            echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'student_dashboard', 'dashboard.php');
            echo sidebar_link('my_applications.php', 'fa-file-alt', 'my_applications', 'my_applications.php');
            echo sidebar_link('view_jobs.php', 'fa-briefcase', 'view_jobs', 'view_jobs.php');
            echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
            ?>
        </nav>
    </aside>
</div>

<script>
    function confirmDeleteAll() {
        Swal.fire({
            title: 'Delete ALL Notifications?',
            text: "This action cannot be undone!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#b91c1c',
            confirmButtonText: 'Yes, delete all',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteAllInput').disabled = false;
                document.getElementById('inboxForm').submit();
            }
        });
    }
</script>

<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>
