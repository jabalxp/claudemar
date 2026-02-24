<?php
$host = 'localhost';
$db   = 'senai_gestao';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // If the database doesn't exist yet, we might need to handle that or let the user create it with the SQL file provided
     // For now, let's keep it simple
     die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>
