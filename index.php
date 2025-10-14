<?php
// index.php
require_once 'core/functions.php'; 
init_system('welcome_title'); 
if (isset($_SESSION['user_id'])) {
    header("Location: redirect.php");
    exit();
}
?>

<?php include 'templates/header.php'; ?> 
    
    <style>
        /* 💡 Dark Mode support အတွက် CSS variables သာ အသုံးပြုပါမည် */
        body {
            /* Light Mode အတွက် Default Gradient ကို style.css မှာ သတ်မှတ်ထားသင့်ပါသည်။ */
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        .login-card {
            background-color: var(--color-bg-card);
            color: var(--color-text);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: transform 0.2s ease-in-out;
        }
        .login-card:hover {
            transform: translateY(-5px);
        }
    </style>

    <div class="flex flex-col items-center justify-center min-h-[calc(100vh-100px)]">
        
            <div class="text-center mb-10 mt-6">
                <h1 class="text-4xl font-bold mb-2 text-white drop-shadow-lg">
                    <?php echo $lang['welcome_title']; ?><br><br>
                    <?php echo $lang['Empowering Students,Companies for Smarter Placements']; ?>
                </h1><br/>
                <p class="text-lg text-gray-200"><?php echo $lang['login_options']; ?></p>
            </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 max-w-5xl w-full">
            <a href="student_login.php" class="login-card p-6 flex flex-col items-center text-center hover:bg-opacity-90">
                <i class="fas fa-user-graduate text-blue-600 text-4xl mb-3"></i>
                <h3 class="font-semibold text-lg"><?php echo $lang['student_login']; ?></h3>
                <p class="text-sm mt-2" style="color: var(--color-text); opacity: 0.7;">Access your internship dashboard</p>
            </a>

            <a href="company_login.php" class="login-card p-6 flex flex-col items-center text-center hover:bg-opacity-90">
                <i class="fas fa-building text-purple-600 text-4xl mb-3"></i>
                <h3 class="font-semibold text-lg"><?php echo $lang['company_login']; ?></h3>
                <p class="text-sm mt-2" style="color: var(--color-text); opacity: 0.7;">Manage job and internship listings</p>
            </a>

            <a href="admin_login.php" class="login-card p-6 flex flex-col items-center text-center hover:bg-opacity-90">
                <i class="fas fa-user-shield text-green-600 text-4xl mb-3"></i>
                <h3 class="font-semibold text-lg"><?php echo $lang['admin_login']; ?></h3>
                <p class="text-sm mt-2" style="color: var(--color-text); opacity: 0.7;">Manage all placements and reports</p>
            </a>
        </div>

        <div class="mt-12 p-6 rounded-xl backdrop-blur-sm text-center w-full max-w-3xl"
             style="background-color: rgba(255, 255, 255, 0.1);">
            <h2 class="text-2xl font-semibold mb-4 text-white"><?php echo $lang['registration']; ?></h2>
            <div class="flex flex-col sm:flex-row justify-center gap-6">
                <a href="register_student.php" class="bg-white text-blue-700 px-5 py-2 rounded-lg font-semibold hover:bg-blue-100 transition">
                    <i class="fas fa-user-plus mr-2"></i> <?php echo $lang['register_student']; ?>
                </a>
                <a href="register_company.php" class="bg-white text-purple-700 px-5 py-2 rounded-lg font-semibold hover:bg-purple-100 transition">
                    <i class="fas fa-briefcase mr-2"></i> <?php echo $lang['register_company']; ?>
                </a>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="assets/css/sweetalert2.min.css">
<?php
// ✅ Logout successful message
if (isset($_SESSION['logout_success'])) {
    echo "
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Logged Out',
            text: 'You have successfully logged out.',
            showConfirmButton: false,
            timer: 2000,
            background: '#1e293b',
            color: '#fff',
            timerProgressBar: true,
        });
    </script>
    ";
    unset($_SESSION['logout_success']); // ထပ်ပြမလာအောင်ဖျက်
}
?>


<?php include 'templates/footer.php'; ?>