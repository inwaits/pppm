<?php
// MySQL Database Configuration
$host = 'localhost';        // Your MySQL server host
$dbname = 'u441652487_onitapp'; // Your database name
$username = 'u441652487_onitapp';  // Your MySQL username
$password = 's[dCccG2';  // Your MySQL password
$port = 3306;                // MySQL port (default: 3306)

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+00:00'");
} catch(PDOException $e) {
    die("MySQL Connection failed: " . $e->getMessage());
}
?>