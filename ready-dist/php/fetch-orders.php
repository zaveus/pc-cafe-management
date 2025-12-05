<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

require __DIR__ . '/dbConnect.php';

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}   


try{

    $sql = "
    
    SELECT 
    p.item_id,
    p.item_name,
    p.category,
    p.price,

    o.order_id,
    o.member_id,
    o.order_date,
    o.total_amount,

    oi.order_item_id,
    oi.quantity,
    oi.subtotal
    FROM order_table AS o
    JOIN order_items AS oi
        ON o.order_id = oi.order_id
    JOIN product_table AS p
        ON oi.item_id = p.item_id
    ORDER BY o.order_date DESC, o.order_id DESC;
    ";


    $stmt = $dbConnect->query($sql);
    $rows = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "orders" => $rows
    ]);


} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch orders.",
        "error" => $e->getMessage()
    ]);
    exit;

}   






?>