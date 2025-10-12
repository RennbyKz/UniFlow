<?php
// core/db.php
// PDO Connection Logic (config.php မှ Constants များကို သုံးသည်)
// NOTE: ဤ file သည် functions.php မှ ခေါ်ရမည်။

$host = DB_HOST;
$db   = DB_DATABASE;
$user = DB_USER;
$pass = DB_PASSWORD;
$port = DB_PORT; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
} catch (\PDOException $e) {
     // Production မှာ ဒီလို Error အပြည့်အစုံ မပြသင့်ပါ
     die("Database Connection Error: " . $e->getMessage());
}
?>