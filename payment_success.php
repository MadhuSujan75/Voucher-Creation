<?php
require 'db.php';

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header("Location: events.php");
    exit();
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.*, e.title as event_title, e.event_date, e.start_time, e.venue, u.email, u.username
    FROM orders o
    JOIN events e ON o.event_id = e.id
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: events.php");
    exit();
}

// Fetch voucher redemption details if applicable
$voucher_redemption = null;
if ($order['discount'] > 0) {
    $stmt = $pdo->prepare("
        SELECT vr.*, vc.code as voucher_code, v.title as voucher_title
        FROM voucher_redemptions vr
        JOIN voucher_codes vc ON vr.voucher_code_id = vc.id
        JOIN vouchers v ON vr.voucher_id = v.id
        WHERE vr.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $voucher_redemption = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Order #<?= $order_id ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Benton+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --eventbrite-orange: #ff8000;
            --eventbrite-orange-light: #ff9524;
            --eventbrite-orange-dark: #e6730d;
            --gray-900: #1e293b;
            --gray-800: #334155;
            --gray-700: #475569;
            --gray-600: #64748b;
            --gray-500: #94a3b8;
            --gray-400: #cbd5e1;
            --gray-300: #e2e8f0;
            --gray-200: #f1f5f9;
            --gray-100: #f8fafc;
            --white: #ffffff;
            --success-green: #10b981;
            --error-red: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Benton Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
        }

        .success-header {
            text-align: center;
            margin-bottom: 48px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
            color: var(--white);
        }

        .success-header h1 {
            font-size: 36px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .success-header p {
            font-size: 18px;
            color: var(--gray-600);
        }

        .order-details {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .order-number {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .order-status {
            background: var(--success-green);
            color: var(--white);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .event-info {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding: 20px;
            background: var(--gray-50);
            border-radius: 12px;
        }

        .event-icon {
            width: 64px;
            height: 64px;
            background: var(--eventbrite-orange);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--white);
            margin-right: 20px;
        }

        .event-details h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .event-details p {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 2px;
        }

        .order-summary {
            display: grid;
            gap: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
        }

        .summary-row.total {
            border-top: 2px solid var(--gray-300);
            font-weight: 600;
            font-size: 18px;
            color: var(--gray-900);
        }

        .discount {
            color: var(--success-green);
        }

        .voucher-info {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .voucher-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .voucher-details {
            display: grid;
            gap: 8px;
        }

        .voucher-detail {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .voucher-code {
            font-family: monospace;
            color: var(--eventbrite-orange);
            font-weight: 600;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn {
            padding: 16px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--eventbrite-orange);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-secondary:hover {
            border-color: var(--gray-400);
        }

        .email-confirmation {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            text-align: center;
        }

        .email-confirmation p {
            font-size: 14px;
            color: var(--gray-600);
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-header">
            <div class="success-icon">✓</div>
            <h1>Payment Successful!</h1>
            <p>Your event tickets have been confirmed</p>
        </div>

        <div class="order-details">
            <div class="order-header">
                <div class="order-number">Order #<?= $order_id ?></div>
                <div class="order-status">Confirmed</div>
            </div>

            <div class="event-info">
                <div class="event-icon">🎫</div>
                <div class="event-details">
                    <h3><?= htmlspecialchars($order['event_title']) ?></h3>
                    <p><?= date('l, F j, Y', strtotime($order['event_date'])) ?></p>
                    <p><?= date('g:i A', strtotime($order['start_time'])) ?></p>
                    <?php if ($order['venue']): ?>
                        <p>📍 <?= htmlspecialchars($order['venue']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-summary">
                <div class="summary-row">
                    <span>Tickets (<?= $order['tickets'] ?>):</span>
                    <span>$<?= number_format($order['subtotal'], 2) ?></span>
                </div>
                
                <?php if ($order['discount'] > 0): ?>
                    <div class="summary-row discount">
                        <span>Discount:</span>
                        <span>-$<?= number_format($order['discount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="summary-row total">
                    <span>Total Paid:</span>
                    <span>$<?= number_format($order['total'], 2) ?></span>
                </div>
            </div>

            <?php if ($voucher_redemption): ?>
                <div class="voucher-info">
                    <h4>Voucher Applied</h4>
                    <div class="voucher-details">
                        <div class="voucher-detail">
                            <span>Voucher:</span>
                            <span class="voucher-code"><?= htmlspecialchars($voucher_redemption['voucher_code']) ?></span>
                        </div>
                        <div class="voucher-detail">
                            <span>Discount Applied:</span>
                            <span>$<?= number_format($voucher_redemption['applied_discount'], 2) ?></span>
                        </div>
                        <div class="voucher-detail">
                            <span>Remaining Balance:</span>
                            <span>$<?= number_format($voucher_redemption['remaining_balance_after'], 2) ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="email-confirmation">
                <p>📧 A confirmation email has been sent to <strong><?= htmlspecialchars($order['email']) ?></strong></p>
            </div>
        </div>

        <div class="actions">
            <a href="events.php" class="btn btn-secondary">Browse More Events</a>
            <a href="user_dashboard.php" class="btn btn-primary">View My Tickets</a>
        </div>
    </div>
</body>
</html>
