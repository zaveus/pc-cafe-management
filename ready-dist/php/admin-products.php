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


$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$action = $data["action"] ?? null;


//Cehck Admin action fetch/update/add
if(!$action) {

    echo json_encode([
        "success" => false,
        "message" => "Missing Action."
    ]);

    exit;
}

try{
    // Fetches items from database
    switch ($action) {

        case "fetch": {

            $sql = "
                SELECT 
                    item_id,
                    item_name,
                    category,
                    price,
                    is_active
                FROM product_table
                ORDER BY item_name ASC
            ";
            $stmt = $dbConnect->query($sql);
            $rows = $stmt->fetchAll();

            echo json_encode([
                "success"  => true,
                "products" => $rows
            ]);
            break;
        }

        // Adds items in database
        case "add": {

            $itemId    = $data["item_id"]    ?? null;
            $itemName  = $data["item_name"]  ?? null;
            $category  = $data["category"]   ?? null;
            $price     = $data["price"]      ?? null;
            $isActive  = $data["is_active"]  ?? true;


            if (
                    !$itemId || !trim($itemId) ||
                    !$itemName || !trim($itemName) ||
                    $price === null || $price === "" || !is_numeric($price)
                ) {
                    echo json_encode([
                        "success" => false,
                        "message" => "Invalid payload for add. Need item_id, item_name, and numeric price."
                    ]);
                    exit;
                }


            $itemId    = trim($itemId);
            $itemName  = trim($itemName);
            $category  = $category !== null ? trim($category) : null;
            $price     = (float)$price;


            //Check if ID exists
            $check = $dbConnect->prepare("
                SELECT item_id 
                FROM product_table
                WHERE item_id = :item_id
                LIMIT 1
            ");
            $check->execute([":item_id" => $itemId]);

            if ($check->fetch()) {
                echo json_encode([
                    "success" => false,
                    "message" => "Item ID already exists."
                ]);
                exit;
            }

            $insert =  $dbConnect->prepare("
                INSERT INTO product_table (item_id, item_name, category, price, is_active)
                VALUES (:item_id, :item_name, :category, :price, :is_active)
            ");
            $insert->execute([
                ":item_id"   => $itemId,
                ":item_name" => $itemName,
                ":category"  => $category,
                ":price"     => $price,
                ":is_active" => $isActive ? 1 : 0,
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Product added."
            ]);
            break;
        }

        case "updateStatus": {
            $itemId   = $data["item_id"]   ?? null;
            $isActive = $data["is_active"] ?? null;

            if (!$itemId || !is_bool($isActive)) {
                echo json_encode([
                    "success" => false,
                    "message" => "Invalid payload for updateStatus."
                ]);
                exit;
            }

            $update = $dbConnect->prepare("
                UPDATE product_table
                SET is_active = :is_active
                WHERE item_id = :item_id
                LIMIT 1
            ");
            $update->execute([
                ":is_active" => $isActive ? 1 : 0,
                ":item_id"   => $itemId,
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Product status updated."
            ]);
            break;
        }

        default:
            echo json_encode([
                "success" => false,
                "message" => "Unknown action: $action"
            ]);
            break;
    } // end switch



} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error in admin-products: " . $e->getMessage()
    ]);
    exit;
}



?>