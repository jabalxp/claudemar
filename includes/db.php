<?php
$host = 'localhost';
$db = 'senai_gestao';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';
$port = 3308; // Default MySQL port

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
     die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

if (!function_exists('xe')) {
     function xe($v)
     {
          return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
     }
}
?>
