<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
require __DIR__ . '/dbConnect.php';

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$walletID = $data["cardID"] ?? null;

if (!$walletID || !trim($walletID)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing Card ID."
    ]);
    exit;
}

$today = date("Y-m-d");

try {

    $sql =
    "SELECT
        m.member_id,
        m.wallet_id,
        m.email,
        m.phone_number,
        m.first_name,
        m.last_name,
        m.date_of_birth,
        m.role,

        tw.time_credits,

        ms.membership_id,
        ms.date_joined,
        ms.expiry_date,
        ms.status

    FROM time_wallet tw
    JOIN members_table m
        ON m.wallet_id = tw.wallet_id
    LEFT JOIN memberships_table ms
        ON ms.member_id = m.member_id

    WHERE tw.wallet_id = :walletId
    ORDER BY ms.expiry_date DESC
    LIMIT 1"
    ;

    $stmt = $dbConnect->prepare($sql);
    $stmt->execute([':walletId' => $walletID]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode([
            "success" => false,
            "message" => "Card not found."
        ]);
        exit;
    }

    $membershipStatus = $row['status'];
    $expiryDate = $row['expiry_date'];
    $isMembershipActive = false;


    if ($membershipStatus === 'Active' && $expiryDate != null) {
        
        if ($expiryDate >= $today) {
            $isMembershipActive = true;
        }
        else {
        $membershipStatus = "Expired";
        //Update status in database to Expired
        $updateSql = "UPDATE memberships_table SET status = 'Expired' WHERE membership_id = :membershipId";
        $updateStmt = $dbConnect->prepare($updateSql);
        $updateStmt->execute([':membershipId' => $row['membership_id']]);
        } 
    }


    $timeCreditsRaw = $row["time_credits"];
    $timeCreditsHours = $timeCreditsRaw !== null ? (float)$timeCreditsRaw : 0.0;
    $timeCreditsSeconds = (int) round($timeCreditsHours * 3600);


    echo json_encode([
        "success" => true,
        "member" => [
            "memberID" => $row['member_id'],
            "walletID" => $row['wallet_id'],
            "role" => $row['role'],
            "email" => $row['email'],
            "phoneNumber" => $row['phone_number'],
            "firstName" => $row['first_name'],
            "lastName" => $row['last_name'],
            "dateOfBirth" => $row['date_of_birth'],
        ],
        "timeWallet" => [
            "walletID"            => $row["wallet_id"],
            "time_credits_raw"     => $timeCreditsRaw,
            "time_credits_hours"   => $timeCreditsHours,
            "time_credits_seconds" => $timeCreditsSeconds
        ],
        "membership" => [
            "membershipID" => $row['membership_id'],
            "status" => $membershipStatus,
            "active" => $isMembershipActive,
            "dateJoined" => $row['date_joined'],
            "expiryDate" => $expiryDate,

        ]
    ]);



} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
    exit;
}
?>