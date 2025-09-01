<?php
require 'db.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's orders
$stmt = $pdo->prepare("
    SELECT o.*, e.title as event_title, e.event_date, e.start_time, e.venue
    FROM orders o
    JOIN events e ON o.event_id = e.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's vouchers
$stmt = $pdo->prepare("
    SELECT vc.*, v.title as voucher_title, v.discount_type, v.percent_off, v.amount_off, v.currency
    FROM voucher_codes vc
    JOIN vouchers v ON vc.voucher_id = v.id
    WHERE vc.assigned_user_id = ?
    ORDER BY vc.created_at DESC
");
$stmt->execute([$user_id]);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch voucher redemption history
$stmt = $pdo->prepare("
    SELECT vr.*, v.title as voucher_title, vc.code as voucher_code, e.title as event_title
    FROM voucher_redemptions vr
    JOIN vouchers v ON vr.voucher_id = v.id
    JOIN voucher_codes vc ON vr.voucher_code_id = vc.id
    JOIN events e ON vr.event_id = e.id
    WHERE vr.user_id = ?
    ORDER BY vr.redeemed_at DESC
");
$stmt->execute([$user_id]);
$redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: var(--eventbrite-orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            font-size: 18px;
        }

        .user-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .user-details p {
            font-size: 14px;
            color: var(--gray-600);
        }

        .logout-btn {
            padding: 8px 16px;
            background: var(--gray-200);
            color: var(--gray-700);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: var(--gray-300);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            background: var(--eventbrite-orange);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 24px;
            color: var(--white);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 16px;
            color: var(--gray-600);
        }

        .section {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .view-all-btn {
            padding: 8px 16px;
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .view-all-btn:hover {
            background: var(--eventbrite-orange-dark);
        }

        .ticket-card, .voucher-card {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }

        .ticket-card:hover, .voucher-card:hover {
            border-color: var(--eventbrite-orange);
            box-shadow: 0 4px 12px rgba(255, 128, 0, 0.1);
        }

        .ticket-header, .voucher-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .ticket-title, .voucher-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .ticket-date, .voucher-code {
            font-size: 14px;
            color: var(--gray-600);
        }

        .ticket-details, .voucher-details {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .detail-label {
            color: var(--gray-600);
        }

        .detail-value {
            color: var(--gray-900);
            font-weight: 500;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed {
            background: #f0fdf4;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-available {
            background: #f0fdf4;
            color: #166534;
        }

        .status-redeemed {
            background: #f3f4f6;
            color: #374151;
        }

        .discount-amount {
            color: var(--success-green);
            font-weight: 600;
        }

        .no-data {
            text-align: center;
            padding: 48px 24px;
            color: var(--gray-500);
        }

        .no-data h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .no-data p {
            font-size: 16px;
            margin-bottom: 24px;
        }

        .browse-events-btn {
            display: inline-block;
            padding: 12px 24px;
            background: var(--eventbrite-orange);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .browse-events-btn:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Dashboard</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($user['username']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-number"><?= count($orders) ?></div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎟️</div>
                <div class="stat-number"><?= count($vouchers) ?></div>
                <div class="stat-label">Available Vouchers</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number">$<?= number_format(array_sum(array_column($vouchers, 'remaining_balance')), 2) ?></div>
                <div class="stat-label">Voucher Balance</div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Recent Tickets</h2>
                <a href="events.php" class="view-all-btn">Browse Events</a>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="no-data">
                    <h3>No tickets yet</h3>
                    <p>Start exploring amazing events and book your first ticket!</p>
                    <a href="events.php" class="browse-events-btn">Browse Events</a>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($orders, 0, 3) as $order): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <div>
                                <div class="ticket-title"><?= htmlspecialchars($order['event_title']) ?></div>
                                <div class="ticket-date"><?= date('M j, Y', strtotime($order['event_date'])) ?></div>
                            </div>
                            <div class="status-badge status-<?= strtolower($order['status']) ?>">
                                <?= ucfirst($order['status']) ?>
                            </div>
                        </div>
                        <div class="ticket-details">
                            <div class="detail-row">
                                <span class="detail-label">Date & Time:</span>
                                <span class="detail-value"><?= date('l, F j, Y', strtotime($order['event_date'])) ?> at <?= date('g:i A', strtotime($order['start_time'])) ?></span>
                            </div>
                            <?php if ($order['venue']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Venue:</span>
                                    <span class="detail-value"><?= htmlspecialchars($order['venue']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Tickets:</span>
                                <span class="detail-value"><?= $order['tickets'] ?> <?= $order['tickets'] == 1 ? 'ticket' : 'tickets' ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Total Paid:</span>
                                <span class="detail-value">$<?= number_format($order['total'], 2) ?></span>
                            </div>
                            <?php if ($order['discount'] > 0): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Discount Applied:</span>
                                    <span class="detail-value discount-amount">-$<?= number_format($order['discount'], 2) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <div class="section-header">
                <h2 class="section-title">My Vouchers</h2>
            </div>
            
            <?php if (empty($vouchers)): ?>
                <div class="no-data">
                    <h3>No vouchers yet</h3>
                    <p>Vouchers will appear here when you receive them from events or promotions.</p>
                </div>
            <?php else: ?>
                <?php foreach ($vouchers as $voucher): ?>
                    <div class="voucher-card">
                        <div class="voucher-header">
                            <div>
                                <div class="voucher-title"><?= htmlspecialchars($voucher['voucher_title']) ?></div>
                                <div class="voucher-code">Code: <?= htmlspecialchars($voucher['code']) ?></div>
                            </div>
                            <div class="status-badge status-<?= strtolower($voucher['state']) ?>">
                                <?= ucfirst($voucher['state']) ?>
                            </div>
                        </div>
                        <div class="voucher-details">
                            <div class="detail-row">
                                <span class="detail-label">Discount Type:</span>
                                <span class="detail-value">
                                    <?php if ($voucher['discount_type'] === 'PERCENT'): ?>
                                        <?= $voucher['percent_off'] ?>% off
                                    <?php else: ?>
                                        $<?= number_format($voucher['amount_off'], 2) ?> off
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Remaining Balance:</span>
                                <span class="detail-value">$<?= number_format($voucher['remaining_balance'], 2) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Expires:</span>
                                <span class="detail-value"><?= date('M j, Y', strtotime($voucher['created_at'] . ' +1 year')) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($redemptions)): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Voucher Usage History</h2>
                </div>
                
                <?php foreach (array_slice($redemptions, 0, 5) as $redemption): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <div>
                                <div class="ticket-title"><?= htmlspecialchars($redemption['event_title']) ?></div>
                                <div class="ticket-date"><?= date('M j, Y', strtotime($redemption['redeemed_at'])) ?></div>
                            </div>
                            <div class="status-badge status-redeemed">Redeemed</div>
                        </div>
                        <div class="ticket-details">
                            <div class="detail-row">
                                <span class="detail-label">Voucher:</span>
                                <span class="detail-value"><?= htmlspecialchars($redemption['voucher_code']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Discount Applied:</span>
                                <span class="detail-value discount-amount">-$<?= number_format($redemption['applied_discount'], 2) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Remaining Balance:</span>
                                <span class="detail-value">$<?= number_format($redemption['remaining_balance_after'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
