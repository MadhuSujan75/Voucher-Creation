<?php
require 'db.php';

// Check for success message
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Voucher created successfully!';
}

// Fetch vouchers
$stmt = $pdo->query("SELECT * FROM vouchers ORDER BY created_at DESC");
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher List</title>
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
            --warning-yellow: #f59e0b;
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
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .create-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(255, 128, 0, 0.2);
        }

        .create-btn:hover {
            background: linear-gradient(135deg, var(--eventbrite-orange-light), var(--eventbrite-orange));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 128, 0, 0.3);
        }

        .table-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--gray-50);
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .voucher-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .voucher-id {
            font-size: 12px;
            color: var(--gray-500);
            font-family: monospace;
        }

        .discount-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .discount-type.PERCENT {
            background: #dbeafe;
            color: #1e40af;
        }

        .discount-type.FIXED {
            background: #dcfce7;
            color: #166534;
        }

        .discount-type.STORED_VALUE {
            background: #fef3c7;
            color: #92400e;
        }

        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.ACTIVE {
            background: #dcfce7;
            color: #166534;
        }

        .status.INACTIVE {
            background: #fee2e2;
            color: #991b1b;
        }

        .status.EXPIRED {
            background: #f3f4f6;
            color: #6b7280;
        }

        .status.ARCHIVED {
            background: #fef3c7;
            color: #92400e;
        }

        .date-range {
            font-size: 14px;
            color: var(--gray-600);
        }

        .date-start {
            font-weight: 500;
            color: var(--gray-800);
        }

        .date-end {
            color: var(--gray-500);
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s ease;
            border: 1px solid;
        }

        .action-btn.view {
            background: var(--white);
            color: var(--gray-700);
            border-color: var(--gray-300);
        }

        .action-btn.view:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .action-btn.edit {
            background: var(--white);
            color: var(--eventbrite-orange);
            border-color: var(--eventbrite-orange);
        }

        .action-btn.edit:hover {
            background: var(--eventbrite-orange);
            color: var(--white);
        }

        .action-btn.batch {
            background: var(--white);
            color: var(--success-green);
            border-color: var(--success-green);
        }

        .action-btn.batch:hover {
            background: var(--success-green);
            color: var(--white);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: var(--gray-600);
        }

        .empty-state p {
            margin-bottom: 24px;
        }

        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left-color: var(--success-green);
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .header h1 {
                font-size: 28px;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }

            .table th,
            .table td {
                padding: 12px 16px;
            }

            .actions {
                flex-direction: column;
            }

            .action-btn {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .table th,
            .table td {
                padding: 8px 12px;
                font-size: 14px;
            }

            .voucher-title {
                font-size: 14px;
            }

            .date-range {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Voucher List</h1>
            <a href="voucher_create.php" class="create-btn">+ Create New Voucher</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="table-container">
            <?php if (empty($vouchers)): ?>
                <div class="empty-state">
                    <h3>No vouchers found</h3>
                    <p>Get started by creating your first voucher</p>
                    <a href="voucher_create.php" class="create-btn">Create Voucher</a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Voucher</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Validity Period</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $v): ?>
                            <tr>
                                <td>
                                    <div class="voucher-title"><?= htmlspecialchars($v['title']) ?></div>
                                    <div class="voucher-id">ID: <?= $v['id'] ?></div>
                                </td>
                                <td>
                                    <span class="discount-type <?= $v['discount_type'] ?>">
                                        <?= $v['discount_type'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status <?= strtoupper($v['status'] ?? 'ACTIVE') ?>">
                                        <?= strtoupper($v['status'] ?? 'ACTIVE') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-range">
                                        <div class="date-start"><?= date('M j, Y', strtotime($v['start_at'])) ?></div>
                                        <div class="date-end">to <?= date('M j, Y', strtotime($v['end_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="voucher_view.php?id=<?= $v['id'] ?>" class="action-btn view">View</a>
                                        <a href="voucher_edit.php?id=<?= $v['id'] ?>" class="action-btn edit">Edit</a>
                                        <a href="voucher_batch_create.php?voucher_id=<?= $v['id'] ?>" class="action-btn batch">Analytics</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>