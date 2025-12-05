<?php
$db_server = "localhost";
$db_user = "root";
$db_password = "";
$db_name = "pc_cafe_database";


try {
    $dbConnect = new PDO("mysql:host=$db_server;dbname=$db_name", $db_user, $db_password); #PDO - PHP Data Objects
    $dbConnect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $dbConnect->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "error" => $e->getMessage()
    ]);
    exit;
}

?>