<?php
// admin/settings.php
ob_start(); 
require_once '../core/functions.php'; 
// Admin Role Check
init_system('settings', 'admin'); 

// ------------------------------------------
// 1. Initialization & Data Fetch
// ------------------------------------------

global $pdo;
$settings = [];
$errors = [];
$redirect = 'settings.php';

try {
    // Current Settings အားလုံးကို Fetch လုပ်ခြင်း
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings_raw = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT user_id, username, email, is_approved FROM users WHERE role = 'admin'");
    $admin_list = $stmt->fetchAll();

    foreach ($settings_raw as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (PDOException $e) {
    // Log the error instead of exposing detailed exception message in production
    error_log("Database Error in settings.php: " . $e->getMessage());
    set_session_message($lang['error_general_try_again'] . " (Config Error)", 'error');
    set_session_message($lang['error_general_try_again'] . " (Admin List Fetch Error)", 'error');
}

// ------------------------------------------
// 2. ACTION HANDLING (General, Password, New Admin)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize_input($_POST['action']);

    try {
        $pdo->beginTransaction();

        if ($action === 'update_general') {
            // General Settings Logic
            $site_name = sanitize_input($_POST['site_name'] ?? '');
            $max_size = sanitize_input($_POST['max_resume_size'] ?? '');

            if (empty($site_name) || !is_numeric($max_size)) { throw new Exception($lang['error_fill_all'] ?? 'Please fill all fields correctly.'); }

            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'site_name'");
            $stmt->execute([$site_name]);
            
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'max_resume_size'");
            $stmt->execute([$max_size]);
            
            set_session_message('settings_update_success', 'success');

        } elseif ($action === 'update_password') {
            // Admin Password Change Logic
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';
            $user_id = $_SESSION['user_id']; 

            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                throw new Exception($lang['error_fill_all'] ?? "All password fields are required.");
            }
            if ($new_pass !== $confirm_pass || strlen($new_pass) < 6) {
                throw new Exception($lang['error_password_match'] ?? "New passwords do not match or are too short (min 6 characters).");
            }
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stored_hash = $stmt->fetchColumn();

            if (!password_verify($current_pass, $stored_hash)) {
                throw new Exception($lang['invalid_credentials'] ?? "Current password is incorrect.");
            }

            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$new_hash, $user_id]);
            set_session_message('password_change_success', 'success');
        
        } elseif ($action === 'add_new_admin') {
            // Add New Admin Logic (Includes Email/Username Check)
            $email = sanitize_input($_POST['admin_email'] ?? '');
            $username = sanitize_input($_POST['admin_username'] ?? '');
            $password = $_POST['admin_password'] ?? '';

            if (empty($email) || empty($username) || strlen($password) < 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception($lang['error_fill_all'] ?? "All fields are required and valid.");
            }
            
            // Duplication Check (Email OR Username)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception($lang['error_user_exists'] ?? "Email or Username already exists.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (email, username, password, role, is_approved) 
                VALUES (?, ?, ?, 'admin', 'Yes')
            ");
            $stmt->execute([$email, $username, $hashed_password]);
            set_session_message('admin_added_success', 'success');
        }
        
        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
    
    // Error Handling & Redirect
    if (!empty($errors)) {
        foreach ($errors as $err) { set_session_message($err, 'error'); } 
    }
    header("Location: " . $redirect . (isset($action) && $action === 'add_new_admin' ? '#admin_management' : '')); // Redirect to appropriate tab
    ob_end_flush();
    exit();
}

// Handle Delete Admin (GET request as per existing logic, protected by admin role check above)
if (isset($_GET['action']) && $_GET['action'] === 'delete_admin' && isset($_GET['admin_id'])) {
    $admin_id = (int)$_GET['admin_id'];
    
    // Prevent admin from deleting themselves
    if ($admin_id === (int)($_SESSION['user_id'] ?? 0)) {
         set_session_message('Error: You cannot delete your own admin account!', 'error');
         header("Location: settings.php#admin_list");
         exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'admin'");
        $stmt->execute([$admin_id]);
        set_session_message('Admin deleted successfully.', 'success');
    } catch (PDOException $e) {
        // Log the detailed error
        error_log("Admin delete error: " . $e->getMessage());
        set_session_message('Error deleting admin. Please check server logs.', 'error');
    }
    header("Location: settings.php#admin_list"); // Redirect back to admin list tab
    exit();
}

// Header ကို ခေါ်ယူရန်
include '../templates/header.php'; 
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main);">
    
    <?php include_utility_bar(); ?> 
    <div id="toast-container" class="fixed top-4 right-4 space-y-3 z-50"></div> 
    <div class="flex flex-1" style="margin-top: 60px;">
        
        <aside class="w-64 p-4 border-r fixed h-full overflow-y-auto left-0" 
                style="background-color: var(--color-bg-sidebar); border-color: var(--color-border); top: 60px;">
            <h3 class="text-xl font-bold mb-8 pt-4" style="color: var(--color-primary);"><?php echo APP_NAME; ?></h3>
            <nav class="space-y-2">
                <?php
                $currentPageFile = basename(__FILE__);
                echo sidebar_link('dashboard.php', 'fa-tachometer-alt', 'admin_dashboard', 'dashboard.php');
                echo sidebar_link('manage_companies.php', 'fa-building', 'manage_companies', 'manage_companies.php');
                echo sidebar_link('manage_students.php', 'fa-users', 'manage_students', 'manage_students.php');
                echo sidebar_link('manage_jobs.php', 'fa-briefcase', 'manage_jobs', 'manage_jobs.php');
                echo sidebar_link('reports.php', 'fa-chart-line', 'reports', 'reports.php');
                echo sidebar_link('settings.php', 'fa-cog', 'settings', $currentPageFile); // Active
                echo sidebar_link('../logout.php', 'fa-sign-out-alt', 'logout', 'logout');
                ?>
            </nav>
        </aside>

        <main class="flex-1 p-8 ml-64" style="background-color: var(--color-bg-main;">
            
            <h1 class="text-4xl font-extrabold mb-8 pb-3 border-b" style="color: var(--color-primary); border-color: var(--color-border);">
                <?php echo $lang['settings'] ?? 'System Settings'; ?>
            </h1>
            
            <?php display_session_messages($lang); ?>

            <div class="flex gap-6">
                
                <nav class="w-56 flex-shrink-0 space-y-2">
                    <a href="#general" onclick="showTab('general')" class="tab-link active-tab p-3 rounded-lg font-medium block" data-tab="general">
                        <i class="fa fa-cog mr-2"></i> General Settings
                    </a>
                    <a href="#security" onclick="showTab('security')" class="tab-link p-3 rounded-lg font-medium block" data-tab="security">
                        <i class="fa fa-lock mr-2"></i> Security
                    </a>
                    <a href="#admin_management" onclick="showTab('admin_management')" class="tab-link p-3 rounded-lg font-medium block" data-tab="admin_management">
                        <i class="fa fa-user-plus mr-2"></i> Add Admin
                    </a>
                   <a href="#admin_list" onclick="showTab('admin_list')" class="tab-link p-3 rounded-lg font-medium block" data-tab="admin_list">
                         <i class="fa fa-list-alt mr-2"></i> Admin List
                    </a>
                </nav>

                <div class="flex-grow">
                    
                    <div id="general" class="setting-tab p-6 rounded-xl shadow-lg border" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                        <h2 class="text-2xl font-bold mb-4">General Website Settings</h2>
                        
                        <form method="POST" action="settings.php" class="space-y-4">
                            <input type="hidden" name="action" value="update_general">
                            
                            <div><label for="site_name" class="block font-medium mb-1">Site Name (Header/Footer):</label><input type="text" id="site_name" name="site_name" required value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>"></div>
                            
                            <div><label for="max_resume_size" class="block font-medium mb-1">Max Resume Upload Size (MB):</label><input type="number" id="max_resume_size" name="max_resume_size" required value="<?php echo htmlspecialchars($settings['max_resume_size'] ?? 5); ?>"></div>
                            
                            <button type="submit" class="button-primary px-4 py-2 rounded-lg font-semibold" style="background-color: var(--color-primary); color: var(--color-bg-card);">
                                Save General Settings
                            </button>
                        </form>
                    </div>

                    <div id="security" class="setting-tab p-6 rounded-xl shadow-lg border hidden" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                        <h2 class="text-2xl font-bold mb-4">Security & Admin Password</h2>
                        <form method="POST" action="settings.php" class="space-y-4">
                            <input type="hidden" name="action" value="update_password">
                            
                            <p class="text-sm opacity-80 mb-4">You must enter your current password to change it.</p>

                            <div><label for="current_password" class="block font-medium mb-1">Current Password:</label><input type="password" id="current_password" name="current_password" required></div>
                            
                            <div>
                                <label for="new_password" class="block font-medium mb-1">New Password:</label>
                                <input type="password" id="new_password" name="new_password" oninput="checkPasswordLength(this.value, 'security')" required minlength="6">
                            </div>
                            
                           <div>
                                <label for="confirm_password" class="block font-medium mb-1">Confirm New Password:</label>
                                <input type="password" id="confirm_password" name="confirm_password" oninput="checkPasswordMatch(this.value)" required>
                                <p id="confirm_password_status" class="text-xs mt-1 font-semibold"></p>
                            </div>

                            
                            <button type="submit" class="button-primary px-4 py-2 rounded-lg font-semibold" style="background-color: var(--color-error); color: var(--color-bg-card);">
                                Change Password
                            </button>
                        </form>
                    </div>
                    
                    <div id="admin_management" class="setting-tab p-6 rounded-xl shadow-lg border hidden" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                        <h2 class="text-2xl font-bold mb-4">Add New Administrator</h2>
                        
                        <form method="POST" action="settings.php" class="space-y-4">
                            <input type="hidden" name="action" value="add_new_admin">
                            
                            <p class="text-sm opacity-80 mb-4">New admin will be automatically active upon creation.</p>
                            
                          <div>
                                <label for="admin_email" class="block font-medium mb-1">Email (Login ID):</label>
                                <input type="email" id="admin_email" name="admin_email" required oninput="checkEmailAvailability(this.value)">
                                <p id="email_status" class="text-xs mt-1 font-semibold"></p>
                            </div>


                            <div>
                                <label for="admin_username" class="block font-medium mb-1">Username:</label>
                                <input type="text" id="admin_username" name="admin_username" required oninput="checkUsernameAvailability(this.value)">
                                <p id="username_status" class="text-xs mt-1 font-semibold"></p>
                            </div>
                            
                            <div>
                                <label for="admin_password" class="block font-medium mb-1">Initial Password (Min 6 chars):</label>
                                <input type="password" id="admin_password" name="admin_password" required oninput="checkPasswordLength(this.value, 'new_admin')">
                                <p id="password_status" class="text-xs mt-1 font-semibold"></p>
                            </div>

                            
                            <button type="submit" id="admin_submit_btn" disabled class="button-primary px-4 py-2 rounded-lg font-semibold" style="background-color: #10b981; color: var(--color-bg-card);">
                                Create New Admin
                            </button>
                        </form>
                    </div>
                    <div id="admin_list" class="setting-tab p-6 rounded-xl shadow-lg border hidden" style="background-color: var(--color-bg-card); border-color: var(--color-border);">
                            <h2 class="text-2xl font-bold mb-4">Admin List</h2>

                            <table class="min-w-full bg-white shadow-lg rounded-lg overflow-hidden text-gray-800">
                                <thead style="background-color: var(--color-bg-sidebar);">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Username</th>
                                        <th class="py-3 px-4 text-left">Email</th>
                                        <th class="py-3 px-4 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admin_list as $admin): ?>
                                        <tr class="border-b" style="border-color: var(--color-border);">
                                            <td class="py-2 px-4"><?php echo htmlspecialchars($admin['username']); ?></td>
                                            <td class="py-2 px-4"><?php echo htmlspecialchars($admin['email']); ?></td>
                                            <td class="py-2 px-4">
                                                <?php if ((int)($admin['user_id']) !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                                    <button class="delete-admin-btn text-red-600 hover:text-red-800 font-medium" data-id="<?php echo $admin['user_id']; ?>" data-username="<?php echo htmlspecialchars($admin['username']); ?>">
                                                        Delete
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-gray-500 text-sm">Cannot Delete Self</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
             </div>
            </div>

        </main>
    </div>
    
    <script>
        // Tab switching logic
        function showTab(tabId) {
            document.querySelectorAll('.setting-tab').forEach(tab => {
                tab.classList.add('hidden');
            });
            document.getElementById(tabId).classList.remove('hidden');
            
            document.querySelectorAll('.tab-link').forEach(link => {
                link.classList.remove('active-tab');
            });
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active-tab');
        }

        // Initialization
        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            if(hash && document.getElementById(hash)) {
                showTab(hash);
            } else {
                showTab('general');
            }

            // 💡 SweetAlert2 Delete Confirmation Logic for Admin List
            document.querySelectorAll('.delete-admin-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const adminId = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        html: `You are about to delete the admin **${username}** (ID: ${adminId}). This action cannot be undone.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, delete it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // If confirmed, redirect to the PHP script's delete URL
                            window.location.href = `settings.php?action=delete_admin&admin_id=${adminId}`;
                        }
                    });
                });
            });
        });
        
        // 💡 AJAX Availability Check Logic (for Add Admin form)
        let usernameAvailable = false;
        let emailAvailable = false;
        let debounceTimer;
        const baseUrl = '<?php echo get_base_url(); ?>';

        function checkUsernameAvailability(username) {
            const statusElement = document.getElementById('username_status');
            const submitBtn = document.getElementById('admin_submit_btn');
            
            if (!submitBtn || !statusElement) return;

            clearTimeout(debounceTimer);
            
            if (username.length < 3) {
                statusElement.textContent = "Username must be at least 3 characters.";
                statusElement.style.color = 'var(--color-error)';
                usernameAvailable = false;
                submitBtn.disabled = true;
                return;
            }

            // Debounce: 300ms ခြားမှသာ AJAX ခေါ်ရန်
            debounceTimer = setTimeout(() => {
                statusElement.textContent = "Checking availability...";
                statusElement.style.color = 'var(--color-primary)';
                submitBtn.disabled = true;

                fetch(`${baseUrl}/core/check_username.php?username=${username}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available === true) {
                            statusElement.textContent = "Username is available!";
                            statusElement.style.color = 'green';
                            usernameAvailable = true;
                            // Final Check: All checks must pass to enable button
                            const passwordInput = document.getElementById('admin_password');
                            if (emailAvailable && passwordInput && passwordInput.value.length >= 6) {
                                submitBtn.disabled = false;
                            }
                        } else {
                            statusElement.textContent = "Username is already taken.";
                            statusElement.style.color = 'var(--color-error)';
                            usernameAvailable = false;
                            submitBtn.disabled = true;
                        }
                    })
                    .catch(error => {
                        statusElement.textContent = "Network error during check.";
                        statusElement.style.color = 'var(--color-error)';
                        submitBtn.disabled = true;
                    });
            }, 300);
        }
        
        function checkEmailAvailability(email) {
            const statusElement = document.getElementById('email_status');
            const submitBtn = document.getElementById('admin_submit_btn');
            
            if (!submitBtn || !statusElement) return;

            clearTimeout(debounceTimer);
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.length < 5 || !emailRegex.test(email)) {
                statusElement.textContent = "Enter a valid email address.";
                statusElement.style.color = 'var(--color-error)';
                emailAvailable = false;
                submitBtn.disabled = true;
                return;
            }

            // Debounce: 300ms ခြားမှသာ AJAX ခေါ်ရန်
            debounceTimer = setTimeout(() => {
                statusElement.textContent = "Checking availability...";
                statusElement.style.color = 'var(--color-primary)';
                submitBtn.disabled = true;

                fetch(`${baseUrl}/core/check_email.php?email=${email}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available === true) {
                            statusElement.textContent = "Email is available!";
                            statusElement.style.color = 'green';
                            emailAvailable = true;
                            // Final Check: All checks must pass to enable button
                            const passwordInput = document.getElementById('admin_password');
                            if (usernameAvailable && passwordInput && passwordInput.value.length >= 6) {
                                submitBtn.disabled = false;
                            }
                        } else {
                            statusElement.textContent = "Email is already registered.";
                            statusElement.style.color = 'var(--color-error)';
                            emailAvailable = false;
                            submitBtn.disabled = true;
                        }
                    })
                    .catch(error => {
                        statusElement.textContent = "Network error during check.";
                        statusElement.style.color = 'var(--color-error)';
                        submitBtn.disabled = true;
                    });
            }, 300);
        }
        
        // Use a mode parameter to check length for either 'security' (change password) or 'new_admin'
        function checkPasswordLength(password, mode) {
            const isNewAdmin = mode === 'new_admin';
            const statusElement = isNewAdmin ? document.getElementById('password_status') : null;
            const submitBtn = isNewAdmin ? document.getElementById('admin_submit_btn') : document.querySelector('#security button[type="submit"]');
            
            if (!submitBtn) return;

            // Check if password length is at least 6 characters
            if (password.length < 6) {
                if (statusElement) {
                    statusElement.textContent = "Password must be at least 6 characters.";
                    statusElement.style.color = 'var(--color-error)';
                }
                submitBtn.disabled = true; // Disable the submit button
            } else {
                 if (statusElement) {
                    statusElement.textContent = "Password length is valid.";
                    statusElement.style.color = 'green';
                }
                
                // For New Admin, enable only if all three fields are valid
                if (isNewAdmin) {
                    if (usernameAvailable && emailAvailable) {
                        submitBtn.disabled = false;
                    }
                } else {
                     // For Security, check if confirmation and current password fields are filled correctly
                    const confirmPass = document.getElementById('confirm_password').value;
                    const currentPass = document.getElementById('current_password').value;
                    if (password === confirmPass && currentPass.length > 0) {
                        submitBtn.disabled = false;
                    } else {
                        submitBtn.disabled = true;
                    }
                }
            }
        }
        
        // This function is for the Security tab (Change Password) only
        function checkPasswordMatch(confirmPassword) {
            const newPassword = document.getElementById('new_password').value;
            const statusElement = document.getElementById('confirm_password_status');
            const submitBtn = document.querySelector('#security button[type="submit"]');

            if (confirmPassword !== newPassword) {
                statusElement.textContent = "Passwords do not match.";
                statusElement.style.color = 'var(--color-error)';
                submitBtn.disabled = true;
            } else {
                statusElement.textContent = "Passwords match.";
                statusElement.style.color = 'green';
                // Enable the submit button if all conditions are met
                const currentPass = document.getElementById('current_password').value;
                if (newPassword.length >= 6 && currentPass.length > 0) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
        }
    </script>
    <style>
        .active-tab {
            background-color: var(--color-primary) !important;
            color: white !important;
        }
    </style>
    
<?php include '../templates/footer.php'; ?>
<?php ob_end_flush(); ?>