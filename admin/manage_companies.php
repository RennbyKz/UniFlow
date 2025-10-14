<?php
// admin/manage_companies.php
ob_start(); 
require_once '../core/functions.php'; 
// စနစ်ကို စတင်ပါ (Admin Role လိုအပ်)
init_system('manage_companies', 'admin'); 

// ------------------------------------------
// 1. ACTION HANDLING (Approve / Reject Logic)
// admin/manage_companies.php (Action Handling Section)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['company_id'])) {
    $action = sanitize_input($_POST['action']);
    $company_id = sanitize_input($_POST['company_id']);
    
    try {
        global $pdo;
        $pdo->beginTransaction();

        if ($action === 'delete' || $action === 'reject') {
            // 💡 DELETE ACTION: Reject (Pending List) သို့မဟုတ် Delete (Approved List) အတွက်
            $stmt = $pdo->prepare("DELETE FROM companies WHERE company_id = :id");
            $stmt->bindParam(':id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            $message_key = 'company_deleted';
            set_session_message($message_key, 'success'); 
            
        } elseif ($action === 'approve' || $action === 'disable') {
            // 💡 UPDATE ACTION: Approve (Yes) သို့မဟုတ် Disable (No) အတွက်
            $status = ($action === 'approve') ? 'Yes' : 'No';
            $message_key = ($action === 'approve') ? 'company_approved' : 'company_disabled';
            set_session_message($message_key, 'success'); 
            $stmt = $pdo->prepare("UPDATE companies SET is_approved = :status WHERE company_id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $company_id, PDO::PARAM_INT);
            $stmt->execute();

            // users table ကိုပါ update လုပ်ခြင်း
            $pdo->exec("UPDATE users SET is_approved = '{$status}' WHERE user_id = (SELECT user_id FROM companies WHERE company_id = {$company_id})");
        }
        
        $pdo->commit();

        set_session_message($message_key, 'success');
        header("Location: manage_companies.php");
        ob_end_flush();
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        set_session_message('error_general_try_again', 'error');
        header("Location: manage_companies.php");
        ob_end_flush();
        exit();
    }
}

// ------------------------------------------
// 2. DATA FETCHING (Pending and Approved Lists)
// ------------------------------------------

$pending_list = [];
$approved_list = [];
$list_error = '';

try {
    global $pdo;
    // Pending List ကို ယူခြင်း
    $stmt = $pdo->query("
        SELECT c.company_id, c.name, c.industry, u.email 
        FROM companies c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.is_approved = 'No'
        ORDER BY c.company_id DESC
    ");
    $pending_list = $stmt->fetchAll();

    // Approved List ကို ယူခြင်း
    $stmt = $pdo->query("
        SELECT c.company_id, c.name, c.industry, u.email 
        FROM companies c 
        JOIN users u ON c.user_id = u.user_id 
        WHERE c.is_approved = 'Yes'
        ORDER BY c.company_id DESC
    ");
    $approved_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $list_error = $lang['error_general_try_again'] . " (Data): " . $e->getMessage();
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
       <!-- ✅ Toast container -->
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <?php include_utility_bar(); ?> 

    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
               style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            
            <h3 class="text-lg font-bold mb-6 pt-4" style="color: var(--color-primary);"><?php echo $lang['admin_panel']; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__); // manage_companies.php
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', $currentPageFile);
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', $currentPageFile);
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', $currentPageFile);
                echo sidebar_link('manage_jobs.php', 'fa-briefcase', 'manage_jobs', $currentPageFile);
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', $currentPageFile);
                echo sidebar_link('settings.php', 'fa-cog', 'settings', $currentPageFile);
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main); min-height: var(--content-min-height);">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-text-card); border-color: var(--color-border);">
                <?php echo $lang['manage_companies']; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>
            <?php if (!empty($list_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <p><?php echo $list_error; ?></p>
                </div>
            <?php endif; ?>

            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-3" style="color: var(--color-text-card);">
                    <i class="fa fa-hourglass-half text-amber-500"></i>
                    <?php echo $lang['pending_approval']; ?> 
                    <span class="bg-amber-500 text-white font-semibold text-sm px-3 py-1 rounded-full">
                        <?php echo count($pending_list); ?>
                    </span>
                </h2>
                
                <?php if (empty($pending_list)): ?>
                    <div class="p-6 rounded-lg shadow-md border" style="background-color: var(--color-bg-card); color: var(--color-text-card); border-color: var(--color-border);">
                        <p class="text-lg"><?php echo $lang['no_pending_companies']; ?></p>
                    </div>
                <?php else: ?>
                    <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead style="background-color: var(--color-bg-main);">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_company_name']; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_email']; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_industry']; ?></th>
                                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_action']; ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                                <?php foreach ($pending_list as $company): ?>
                                    <tr class="hover:bg-opacity-5 transition duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap font-medium"><?php echo htmlspecialchars($company['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm opacity-80"><?php echo htmlspecialchars($company['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm opacity-80"><?php echo htmlspecialchars($company['industry']); ?></td>
                                      <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            
                                            <form method="POST" action="manage_companies.php" 
                                                  class="flex justify-center space-x-2" 
                                                  id="form-<?php echo $company['company_id']; ?>"> <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                                
                                                <button type="button" 
                                                        onclick="confirmAction(<?php echo $company['company_id']; ?>, 'approve', '<?php echo addslashes($lang['confirm_approve']); ?>', 'form-<?php echo $company['company_id']; ?>')"
                                                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded-md text-xs shadow-md transition duration-300">
                                                    <i class="fa fa-check mr-1"></i> <?php echo $lang['button_approve']; ?>
                                                </button>
                                                
                                                <button type="button" 
                                                        onclick="confirmAction(<?php echo $company['company_id']; ?>, 'reject', '<?php echo addslashes($lang['confirm_reject']); ?>', 'form-<?php echo $company['company_id']; ?>')"
                                                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-md text-xs shadow-md transition duration-300">
                                                    <i class="fa fa-times mr-1"></i> <?php echo $lang['button_reject']; ?>
                                                </button>
                                                
                                                <input type="hidden" name="action" id="action-<?php echo $company['company_id']; ?>">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

           <section>
                <h2 class="text-2xl font-bold mb-4 flex items-center gap-3" style="color: var(--color-text-card);">
                    <i class="fa fa-check-circle text-green-600"></i>
                    <?php echo $lang['approved_companies']; ?> 
                    <span class="bg-green-600 text-white font-semibold text-sm px-3 py-1 rounded-full">
                        <?php echo count($approved_list); ?>
                    </span>
                </h2>
                
                <?php if (empty($approved_list)): ?>
                    <div class="p-6 rounded-lg shadow-md border" style="background-color: var(--color-bg-card); color: var(--color-text-card); border-color: var(--color-border);">
                        <p class="text-lg"><?php echo $lang['no_approved_companies']; ?></p>
                    </div>
                <?php else: ?>
                    <div class="shadow-xl rounded-xl overflow-x-auto border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead style="background-color: var(--color-bg-main);">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_company_name']; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_email']; ?></th>
                                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_industry']; ?></th>
                                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider" style="color: var(--color-text-card);"><?php echo $lang['label_action']; ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y" style="color: var(--color-text-card); border-color: var(--color-border);">
                                <?php foreach ($approved_list as $company): ?>
                                    <tr class="hover:bg-opacity-5 transition duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap font-medium"><?php echo htmlspecialchars($company['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm opacity-80"><?php echo htmlspecialchars($company['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm opacity-80"><?php echo htmlspecialchars($company['industry']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <form method="POST" action="manage_companies.php" 
                                            class="flex justify-center space-x-2" 
                                            id="form-<?php echo $company['company_id']; ?>">
                                            
                                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">

                                             <a href="view_company_profile.php?company_id=<?php echo $company['company_id']; ?>" 
                                                class="text-blue-500 hover:text-blue-700" title="View Profile">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button type="button" 
                                                    onclick="confirmAction(<?php echo $company['company_id']; ?>, 'disable', '<?php echo $lang['confirm_disable']; ?>')"
                                                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded-md text-xs shadow-md transition duration-300">
                                                <i class="fa fa-ban mr-1"></i> <?php echo $lang['button_disable']; ?>
                                            </button>

                                            <button type="button" 
                                                    onclick="confirmAction(<?php echo $company['company_id']; ?>, 'delete', '<?php echo $lang['confirm_delete']; ?>')"
                                                    class="bg-red-800 hover:bg-red-900 text-white font-bold py-1 px-3 rounded-md text-xs shadow-md transition duration-300">
                                                <i class="fa fa-trash mr-1"></i> <?php echo $lang['button_delete']; ?>
                                            </button>


                                            
                                            <input type="hidden" name="action" id="action-<?php echo $company['company_id']; ?>">
                                        </form>
                                         </td>
                                         </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>
 <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

    function confirmAction(companyId, action, confirmText) {
        let iconType = (action === 'delete' || action === 'reject') ? 'warning' : 'info';
        let confirmButtonColor = (action === 'delete') ? '#d33' : '#3085d6';
        let confirmButtonText = (action === 'approve') ? 'Yes, Approve!' : (action === 'delete' ? 'Yes, Delete!' : 'Yes, Proceed!');

        Swal.fire({
            title: 'Are you sure?',
            text: confirmText,
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: confirmButtonColor,
            cancelButtonColor: '#aaa',
            confirmButtonText: confirmButtonText
        }).then((result) => {
            if (result.isConfirmed) {
                // 💡 JavaScript ကနေ Hidden Input ထဲကို Action Value ထည့်သွင်း
                const formId = `form-${companyId}`;
                const form = document.getElementById(formId);
                
                // Approve/Reject ခလုတ်ကို နှိပ်တဲ့အခါ Hidden Field ရဲ့ ID ကို ပုံသေမပေးနိုင်လို့
                // form ကို တိုက်ရိုက် Submits လုပ်ရပါမယ်။
                
                // Hidden input ထဲကို action value ထည့်
                let actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = action;
                form.appendChild(actionInput);

                // Form Submit
                form.submit();
            }
        });
    }
    
    // NOTE: Pending List မှာရှိတဲ့ Approve/Reject Button တွေကိုလည်း 
    // onclick="confirmAction(<?php echo $company['company_id']; ?>, 'approve', '<?php echo $lang['confirm_approve']; ?>')" ဖြင့် ပြောင်းလဲရပါမယ်။
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;

    // Toast style
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-md text-white text-sm animate-fade-in 
                       ${type === 'success' ? 'bg-green-600' : 
                        type === 'error' ? 'bg-red-600' : 'bg-yellow-500'}`;

    toast.textContent = message;
    toastContainer.appendChild(toast);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('animate-fade-out');
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// CSS animation for fade in/out
const style = document.createElement('style');
style.textContent = `
@keyframes fadeIn { from {opacity: 0; transform: translateY(-10px);} to {opacity: 1; transform: translateY(0);} }
@keyframes fadeOut { from {opacity: 1;} to {opacity: 0; transform: translateY(-10px);} }
.animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
.animate-fade-out { animation: fadeOut 0.5s ease-in forwards; }
`;
document.head.appendChild(style);
</script>   
<?php include '../templates/footer.php'; ?>
<?php
ob_end_flush();
?>