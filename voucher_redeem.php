<?php
require 'db.php';
$voucher_code = $_POST['voucher_code'];
$user_id = $_POST['user_id'];
$order_id = $_POST['order_id'];

try {
    $pdo->beginTransaction();

    // Lock the code
    $stmt = $pdo->prepare("SELECT * FROM voucher_codes WHERE code=? AND state='AVAILABLE' FOR UPDATE");
    $stmt->execute([$voucher_code]);
    $code = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$code) throw new Exception("Voucher not available");

    // Validate policy, scope, min purchase, expiry here...

    // Assign and redeem
    $stmt = $pdo->prepare("UPDATE voucher_codes SET state='REDEEMED', assigned_user_id=?, reserved_by_user_id=NULL WHERE id=?");
    $stmt->execute([$user_id, $code['id']]);

    // Record redemption
    $stmt = $pdo->prepare("INSERT INTO voucher_redemptions (voucher_code_id, voucher_id, user_id, order_id, redeemed_at) VALUES (?,?,?,?,NOW())");
    $stmt->execute([$code['id'], $code['voucher_id'], $user_id, $order_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'discount_applied' => $code['amount_off'] ?? 0]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
