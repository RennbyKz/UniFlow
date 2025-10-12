<?php
// admin/dashboard.php
// 💡 Core Logic များကို စတင်ခြင်း
require_once '../core/functions.php'; 
init_system('admin_dashboard', 'admin'); 

// ✅ Login successful popup ပြရန်
if (isset($_SESSION['login_success'])) {
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Login Successful!',
            text: 'Welcome back, admin 👋',
            showConfirmButton: false,
            timer: 1800,
            background: '#1e293b',
            color: '#fff',
            timerProgressBar: true,
        });
    </script>
    ";
    unset($_SESSION['login_success']);
}

// ------------------------------------------
// Data Fetching for Dashboard Summary
// ------------------------------------------

$total_students = 0;
$approved_companies = 0;
$pending_companies = 0;
$total_jobs = 0;
$dashboard_error = '';

try {
    // $pdo variable ကို core/db.php မှ ရရှိပြီးသား ဖြစ်သည်
    $total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $approved_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_approved = 'Yes'")->fetchColumn();
    $pending_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE is_approved = 'No'")->fetchColumn();
    $total_jobs = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
} catch (PDOException $e) {
    $dashboard_error = $lang['error_general_try_again'] . " (DB): " . $e->getMessage();
}

// ------------------------------------------
// Helper Functions (Dashboard အတွက်သာ)
// ------------------------------------------

// 💡 Stat Card Helper (Displaying Data)
function create_stat_card($title, $value, $color_hex, $icon_class) {
    global $lang;
    return <<<HTML
    <div class="p-6 rounded-xl shadow-lg transition duration-300 transform hover:scale-[1.02] min-w-full" 
         style="background-color: var(--color-bg-card); border-left: 5px solid {$color_hex};">
        <div class="flex justify-between items-center">
            <p class="text-sm font-medium uppercase" style="color: {$color_hex};">{$title}</p>
            <i class="fa {$icon_class} text-3xl" style="color: {$color_hex};"></i>
        </div>
        <div class="mt-1 text-4xl font-extrabold" style="color: var(--color-text-card);">{$value}</div>
    </div>
    HTML;
}

// ------------------------------------------
// HTML Output
// ------------------------------------------
?>

<?php include '../templates/header.php'; ?> 
   <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
       style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
        
        <h3 class="text-lg font-bold mb-6 pt-4" style="color: var(--color-primary);">Admin Panel</h3>
        
        <nav class="space-y-2">
            <?php
            // 💡 Sidebar Link Call (functions.php မှ sidebar_link ကို သုံး)
            $currentPageFile = basename(__FILE__); // 'dashboard.php'
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

    <main class="flex-1 p-8 ml-64 min-height: var(--content-min-height);">
        <h1 class="text-4xl font-bold mb-6" style="color: var(--color-text-card);">
            <?php echo $lang['admin_dashboard']; ?>
        </h1>
        
        <?php display_session_messages($lang); ?>
        <?php if (!empty($dashboard_error)): ?>
             <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <p><?php echo $dashboard_error; ?></p>
             </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <?php
            // 💡 Card Data (Color Coding: Indigo, Green, Amber, Cyan)
            echo create_stat_card($lang['total_students'], $total_students, '#4f46e5', 'fa-graduation-cap'); 
            echo create_stat_card($lang['approved_companies'], $approved_companies, '#10b981', 'fa-building'); 
            echo create_stat_card($lang['pending_approval'], $pending_companies, '#f59e0b', 'fa-hourglass-half'); 
            echo create_stat_card($lang['total_jobs'], $total_jobs, '#06b6d4', 'fa-briefcase'); 
            ?>
        </div>

        <?php if ($pending_companies > 0): ?>
            <div class="p-5 rounded-xl shadow-lg border border-amber-500 mb-6" style="background-color: var(--color-bg-card);">
                <h3 class="text-xl font-semibold mb-3" style="color: var(--color-text-card);">
                    <i class="fa fa-exclamation-triangle mr-2 text-yellow-500"></i> <?php echo $lang['action_required']; ?>
                </h3>
                <p style="color: var(--color-text-card);">
                    <?php echo $pending_companies; ?> <?php echo $lang['pending_companies_count_message']; ?>
                    <a href="manage_companies.php" style="color: var(--color-primary);" class="hover:underline ml-2 font-medium">
                        <?php echo $lang['review_now']; ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>

        </main>
    </div>
    
<?php include '../templates/footer.php'; ?>