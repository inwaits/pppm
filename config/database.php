<?php
// Database configuration (MySQL)
$host = 'localhost';        // Your MySQL server host
$dbname = 'pphotelc_appex'; // Your database name
$username = 'pphotelc_appex';  // Your MySQL username
$password = 'ciq3j4SGuwnJ';  // Your MySQL password
$port = 3306;                // MySQL port (default: 3306)
$charset = 'utf8';          // Character set

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Set SQL mode for better MySQL compatibility
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>