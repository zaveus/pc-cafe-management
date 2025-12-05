<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require "dbConnect.php";


if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$memberId = $data["member_id"] ?? null;
$items    = $data["items"]      ?? null;


if (!$memberId || !is_array($items) || count($items) === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid order payload. Need member_id and at least one item."
    ]);
    exit;
}

try {
    // validate member exists
    $checkMember = $dbConnect->prepare("
        SELECT member_id
        FROM members_table
        WHERE member_id = :member_id
        LIMIT 1
    ");
    $checkMember->execute([":member_id" => $memberId]);
    $memberRow = $checkMember->fetch();

    if (!$memberRow) {
        echo json_encode([
            "success" => false,
            "message" => "Member not found for this order."
        ]);
        exit;
    }

    // Use a transaction so it's all-or-nothing
    $dbConnect->beginTransaction();

    // 1) Insert into order_table (initially with total_amount = 0)
    $insertOrder = $dbConnect->prepare("
        INSERT INTO order_table (member_id, order_date, total_amount)
        VALUES (:member_id, NOW(), 0)
    ");
    $insertOrder->execute([
        ":member_id" => $memberId,
    ]);
    
    $orderId = (int)$dbConnect->lastInsertId();

    // Prepare reusable statements
    $getProduct = $dbConnect->prepare("
        SELECT price
        FROM product_table
        WHERE item_id = :item_id
        LIMIT 1
    ");

    $insertOrderItem = $dbConnect->prepare("
        INSERT INTO order_items (order_id, item_id, quantity, subtotal)
        VALUES (:order_id, :item_id, :quantity, :subtotal)
    ");

    $totalAmount = 0.0;

    foreach ($items as $item) {
        $itemId   = $item["item_id"]  ?? null;
        $quantity = $item["quantity"] ?? 0;

        // Skip invalid rows
        if (!$itemId || $quantity <= 0) {
            continue;
        }

        // Get product price from DB
        $getProduct->execute([":item_id" => $itemId]);
        $product = $getProduct->fetch();

        if (!$product) {
            // If a product is missing, fail the whole order
            throw new Exception("Invalid product ID: " . $itemId);
        }

        $price    = (float)$product["price"];
        $subtotal = $price * (int)$quantity;

        $totalAmount += $subtotal;

        // Insert order_items row
        $insertOrderItem->execute([
            ":order_id" => $orderId,
            ":item_id"  => $itemId,
            ":quantity" => (int)$quantity,
            ":subtotal" => $subtotal,
        ]);
    }

    if ($totalAmount <= 0) {
        // No valid items; rollback and return error
        $dbConnect->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "No valid items in order."
        ]);
        exit;
    }

    // 3) Update total_amount in order_table
    $updateOrder = $dbConnect->prepare("
        UPDATE order_table
        SET total_amount = :total
        WHERE order_id = :order_id
    ");
    $updateOrder->execute([
        ":total"    => $totalAmount,
        ":order_id" => $orderId,
    ]);

    $dbConnect->commit();

    echo json_encode([
        "success"      => true,
        "order_id"     => $orderId,
        "total_amount" => $totalAmount
    ]);

} catch (Exception $e) {
    if ($dbConnect->inTransaction()) {
        $dbConnect->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error creating order.",
        "error"   => $e->getMessage() // uncomment while debugging
    ]);
    exit;
}
