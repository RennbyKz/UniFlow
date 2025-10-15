<?php
// core/config.php
// Database & App Configurations

// 💡 Database Settings
define('DB_USER', 'root');
define('DB_HOST', 'localhost');
define('DB_DATABASE', 'sipms_db'); 
define('DB_PASSWORD', 'root');          
define('DB_PORT', '3307');           

// 💡 App Settings
define('APP_NAME', 'UniFlow'); // Logo Name
define('ROOT_DIR', dirname(__DIR__));

// 💡 BASE URL SETTING
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/uniflow');



// 💡 SMTP Configuration 
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); 
define('SMTP_USERNAME', 'noreplyuniflow@gmail.com');
define('SMTP_PASSWORD', 'sjxc uhkf bvlb rtpn');

?>