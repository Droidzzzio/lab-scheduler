<?php /* ======================== /app/db.php =========================== */ ?>
<?php
class DB {
public static PDO $pdo;
public static function init(){
$host = env('DB_HOST','localhost');
$port = env('DB_PORT','3306');
$db = env('DB_NAME','lab_scheduler');
$user = env('DB_USER','root');
$pass = env('DB_PASS','');
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
$opt = [ PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC ];
self::$pdo = new PDO($dsn, $user, $pass, $opt);
self::$pdo->exec("SET time_zone = '+00:00'"); // store UTC
}
}
?>
