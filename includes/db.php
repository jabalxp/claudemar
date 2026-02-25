<?php
$host = 'localhost';
$db = 'senai_gestao';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$charset = 'utf8mb4';
$port = 3308; // Default MySQL port

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
     $mysqli = new mysqli($host, $user, $pass, $db, $port);
     $mysqli->set_charset($charset);
}
catch (mysqli_sql_exception $e) {
     die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

if (!function_exists('xe')) {
     function xe($v)
     {
          return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8');
     }
}
?>
