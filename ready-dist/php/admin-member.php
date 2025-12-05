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

$action = $data["action"] ?? null;

if (!$action) {
    echo json_encode([
        "success" => false,
        "message" => "Missing action."
    ]);
    exit;
}

try {
    switch ($action) {

        // ----------------------------------------------------
        // FETCH: return all members + wallet + membership info
        // ----------------------------------------------------
        case "fetch": {

            $sql = "
                SELECT 
                    m.member_id,
                    m.wallet_id,
                    m.first_name,
                    m.last_name,
                    m.email,
                    m.phone_number,
                    m.date_of_birth,
                    tw.time_credits,
                    ms.status,
                    ms.expiry_date
                FROM members_table m
                LEFT JOIN time_wallet tw 
                    ON m.wallet_id = tw.wallet_id
                LEFT JOIN memberships_table ms 
                    ON m.member_id = ms.member_id
                ORDER BY m.member_id ASC
            ";

            $stmt = $dbConnect->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "members" => $rows
            ]);
            break;
        }

        // ----------------------------------------------------
        // ADD: create member + wallet + membership
        // ----------------------------------------------------
        case "add": {

            $memberId   = $data["member_id"]      ?? null;
            $walletId   = $data["wallet_id"]      ?? null;
            $firstName  = $data["first_name"]     ?? null;
            $lastName   = $data["last_name"]      ?? null;
            $email      = $data["email"]          ?? null;
            $phone      = $data["phone_number"]   ?? null;
            $dob        = $data["date_of_birth"]  ?? null; // YYYY-MM-DD
            $timeCreds  = $data["time_credits"]   ?? 0;    // numeric (your choice: seconds / hours)
            $expiryDate = $data["expiry_date"]    ?? null; // optional, YYYY-MM-DD

            if (
                !$memberId || !trim($memberId) ||
                !$walletId || !trim($walletId) ||
                !$firstName || !trim($firstName) ||
                !$lastName || !trim($lastName)
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => "Missing required fields for add (member_id, wallet_id, first_name, last_name)."
                ]);
                exit;
            }

            $memberId  = trim($memberId);
            $walletId  = trim($walletId);
            $firstName = trim($firstName);
            $lastName  = trim($lastName);
            $email     = $email !== null ? trim($email) : null;
            $phone     = $phone !== null ? trim($phone) : null;
            $dob       = $dob ?: null;
            $timeCreds = (float)$timeCreds;

            $dbConnect->beginTransaction();

            // Ensure wallet_id not already taken
            $checkWallet = $dbConnect->prepare("
                SELECT wallet_id FROM time_wallet WHERE wallet_id = :wallet_id LIMIT 1
            ");
            $checkWallet->execute([":wallet_id" => $walletId]);
            if ($checkWallet->fetch()) {
                throw new Exception("Wallet ID already exists.");
            }

            // Ensure member_id not already taken
            $checkMember = $dbConnect->prepare("
                SELECT member_id FROM members_table WHERE member_id = :member_id LIMIT 1
            ");
            $checkMember->execute([":member_id" => $memberId]);
            if ($checkMember->fetch()) {
                throw new Exception("Member ID already exists.");
            }

            // 1) Create wallet
            $insertWallet = $dbConnect->prepare("
                INSERT INTO time_wallet (wallet_id, time_credits)
                VALUES (:wallet_id, :time_credits)
            ");
            $insertWallet->execute([
                ":wallet_id"    => $walletId,
                ":time_credits" => $timeCreds,
            ]);

            // 2) Create member
            $insertMember = $dbConnect->prepare("
                INSERT INTO members_table 
                    (member_id, wallet_id, phone_number, email, first_name, last_name, date_of_birth)
                VALUES
                    (:member_id, :wallet_id, :phone_number, :email, :first_name, :last_name, :date_of_birth)
            ");
            $insertMember->execute([
                ":member_id"     => $memberId,
                ":wallet_id"     => $walletId,
                ":phone_number"  => $phone,
                ":email"         => $email,
                ":first_name"    => $firstName,
                ":last_name"     => $lastName,
                ":date_of_birth" => $dob,
            ]);

            // 3) Create membership row (optional expiry), default Active
            $dateJoined = date("Y-m-d");

            $insertMembership = $dbConnect->prepare("
                INSERT INTO memberships_table (member_id, date_joined, expiry_date, status)
                VALUES (:member_id, :date_joined, :expiry_date, 'Active')
            ");
            $insertMembership->execute([
                ":member_id"   => $memberId,
                ":date_joined" => $dateJoined,
                ":expiry_date" => $expiryDate ?: null,
            ]);

            $dbConnect->commit();

            echo json_encode([
                "success" => true,
                "message" => "Member created successfully."
            ]);
            break;
        }

        // ----------------------------------------------------
        // UPDATE: member info, wallet credits, membership expiry & status
        // ----------------------------------------------------
        case "update": {

            $memberId   = $data["member_id"]      ?? null;
            $walletId   = $data["wallet_id"]      ?? null;
            $firstName  = $data["first_name"]     ?? null;
            $lastName   = $data["last_name"]      ?? null;
            $email      = $data["email"]          ?? null;
            $phone      = $data["phone_number"]   ?? null;
            $dob        = $data["date_of_birth"]  ?? null;
            $timeCreds  = $data["time_credits"]   ?? null;
            $expiryDate = $data["expiry_date"]    ?? null;
            $status     = $data["status"]         ?? null;   // 'Active' | 'Expired' | null

            if (!$memberId) {
                echo json_encode([
                    "success" => false,
                    "message" => "member_id is required for update."
                ]);
                exit;
            }

            $dbConnect->beginTransaction();

            // Update members_table
            $updateMember = $dbConnect->prepare("
                UPDATE members_table
                SET
                    wallet_id     = COALESCE(:wallet_id, wallet_id),
                    first_name    = COALESCE(:first_name, first_name),
                    last_name     = COALESCE(:last_name, last_name),
                    email         = COALESCE(:email, email),
                    phone_number  = COALESCE(:phone_number, phone_number),
                    date_of_birth = COALESCE(:date_of_birth, date_of_birth)
                WHERE member_id = :member_id
            ");
            $updateMember->execute([
                ":wallet_id"     => $walletId ?: null,
                ":first_name"    => $firstName ?: null,
                ":last_name"     => $lastName ?: null,
                ":email"         => $email ?: null,
                ":phone_number"  => $phone ?: null,
                ":date_of_birth" => $dob ?: null,
                ":member_id"     => $memberId,
            ]);

            // Update wallet time credits if provided
            if ($walletId && $timeCreds !== null) {
                $timeCreds = (float)$timeCreds;
                $updateWallet = $dbConnect->prepare("
                    UPDATE time_wallet
                    SET time_credits = :time_credits
                    WHERE wallet_id = :wallet_id
                ");
                $updateWallet->execute([
                    ":time_credits" => $timeCreds,
                    ":wallet_id"    => $walletId,
                ]);
            }

            // Update membership (expiry_date and/or status) if provided
            if ($expiryDate !== null || $status !== null) {
                $updateMembership = $dbConnect->prepare("
                    UPDATE memberships_table
                    SET
                        expiry_date = COALESCE(:expiry_date, expiry_date),
                        status      = COALESCE(:status, status)
                    WHERE member_id = :member_id
                ");

                $updateMembership->execute([
                    ":expiry_date" => $expiryDate ?: null,
                    ":status"      => $status ?: null,
                    ":member_id"   => $memberId,
                ]);
            }

            $dbConnect->commit();

            echo json_encode([
                "success" => true,
                "message" => "Member updated successfully."
            ]);
            break;
        }

        default:
            echo json_encode([
                "success" => false,
                "message" => "Unknown action: $action"
            ]);
    }

} catch (Exception $e) {
    if ($dbConnect->inTransaction()) {
        $dbConnect->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error in admin-member.",
        //"error" => $e->getMessage()
    ]);
    exit;
}
