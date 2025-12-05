<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

require __DIR__ . '/dbConnect.php';


if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {
    $sql = "
    SELECT 
        item_id,
        item_name,
        category,
        price
    FROM product_table
    WHERE is_active = TRUE
    ORDER BY item_name ASC
    ";

    $stmt = $dbConnect->query($sql);
    $rows = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "products" => $rows
    ]);


} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch products.",
        "error" => $e->getMessage() //remove in production
    ]);
    exit;

}


?>