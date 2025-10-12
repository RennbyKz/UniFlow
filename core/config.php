<?php
// core/config.php
// Database & App Configurations

// 💡 Database Settings
define('DB_USER', 'root');
define('DB_HOST', 'localhost');
define('DB_DATABASE', 'sipms_db'); // Project Name အသစ်ဖြင့် သတ်မှတ်ပါ
define('DB_PASSWORD', 'root');           // XAMPP Default
define('DB_PORT', '3307');           // Standard Default Port

// 💡 App Settings
define('APP_NAME', 'UniFlow'); // Logo Name
define('ROOT_DIR', dirname(__DIR__)); // C:\xampp\htdocs\uniflow

// 💡 BASE URL SETTING
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/uniflow');

?>