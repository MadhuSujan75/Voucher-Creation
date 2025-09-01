<?php
require 'db.php';

// Get voucher ID from URL
$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id || !is_numeric($voucher_id)) {
    header("Location: vouchers_list.php?error=invalid_voucher");
    exit();
}

// Check for success message
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Voucher updated successfully!';
}

try {
    // Fetch voucher details
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
    $stmt->execute([$voucher_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        header("Location: vouchers_list.php?error=voucher_not_found");
        exit();
    }

    // Fetch voucher codes
    $stmt = $pdo->prepare("SELECT * FROM voucher_codes WHERE voucher_id = ? ORDER BY created_at DESC");
    $stmt->execute([$voucher_id]);
    $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch voucher batches
    $stmt = $pdo->prepare("SELECT * FROM voucher_batches WHERE voucher_id = ? ORDER BY created_at DESC");
    $stmt->execute([$voucher_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count codes by state
    $stmt = $pdo->prepare("SELECT state, COUNT(*) as count FROM voucher_codes WHERE voucher_id = ? GROUP BY state");
    $stmt->execute([$voucher_id]);
    $code_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = ['AVAILABLE' => 0, 'REDEEMED' => 0, 'EXPIRED' => 0];
    foreach ($code_stats as $stat) {
        $stats[$stat['state']] = $stat['count'];
    }

} catch (Exception $e) {
    header("Location: vouchers_list.php?error=database_error");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Details - <?= htmlspecialchars($voucher['title']) ?></title>
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

        .back-btn {
            padding: 12px 24px;
            background: var(--white);
            color: var(--gray-700);
            text-decoration: none;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .card-subtitle {
            font-size: 14px;
            color: var(--gray-600);
        }

        .card-body {
            padding: 24px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray-700);
        }

        .info-value {
            color: var(--gray-800);
            text-align: right;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--gray-200);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-available .stat-number {
            color: var(--success-green);
        }

        .stat-redeemed .stat-number {
            color: var(--eventbrite-orange);
        }

        .stat-expired .stat-number {
            color: var(--gray-500);
        }

        .codes-section {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .codes-header {
            padding: 20px 24px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .codes-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .export-btn {
            padding: 8px 16px;
            background: var(--eventbrite-orange);
            color: var(--white);
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .export-btn:hover {
            background: var(--eventbrite-orange-dark);
        }

        .codes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .codes-table th {
            background: var(--gray-50);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .codes-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .codes-table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .code-text {
            font-family: monospace;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .code-state {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .code-state.AVAILABLE {
            background: #dcfce7;
            color: #166534;
        }

        .code-state.REDEEMED {
            background: #fef3c7;
            color: #92400e;
        }

        .code-state.EXPIRED {
            background: #f3f4f6;
            color: #6b7280;
        }

        .copy-btn {
            padding: 4px 8px;
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .copy-btn:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .copy-btn.copied {
            background: var(--success-green);
            color: var(--white);
            border-color: var(--success-green);
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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .header h1 {
                font-size: 28px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .codes-section {
                overflow-x: auto;
            }

            .codes-table {
                min-width: 500px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($voucher['title']) ?></h1>
            <a href="vouchers_list.php" class="back-btn">← Back to List</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card stat-available">
                <div class="stat-number"><?= $stats['AVAILABLE'] ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card stat-redeemed">
                <div class="stat-number"><?= $stats['REDEEMED'] ?></div>
                <div class="stat-label">Redeemed</div>
            </div>
            <div class="stat-card stat-expired">
                <div class="stat-number"><?= $stats['EXPIRED'] ?></div>
                <div class="stat-label">Expired</div>
            </div>
        </div>

        <!-- Voucher Details -->
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Voucher Information</div>
                    <div class="card-subtitle">Basic details and settings</div>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Title</span>
                        <span class="info-value"><?= htmlspecialchars($voucher['title']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type</span>
                        <span class="info-value">
                            <span class="discount-type <?= $voucher['discount_type'] ?>">
                                <?= $voucher['discount_type'] ?>
                            </span>
                        </span>
                    </div>
                    <?php if ($voucher['discount_type'] === 'PERCENT' && $voucher['percent_off']): ?>
                        <div class="info-row">
                            <span class="info-label">Discount</span>
                            <span class="info-value"><?= $voucher['percent_off'] ?>%</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($voucher['discount_type'] === 'FIXED' && $voucher['amount_off']): ?>
                        <div class="info-row">
                            <span class="info-label">Amount Off</span>
                            <span class="info-value"><?= $voucher['currency'] ?> <?= number_format($voucher['amount_off'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($voucher['max_discount_amount']): ?>
                        <div class="info-row">
                            <span class="info-label">Max Discount</span>
                            <span class="info-value"><?= $voucher['currency'] ?> <?= number_format($voucher['max_discount_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($voucher['min_purchase_amount']): ?>
                        <div class="info-row">
                            <span class="info-label">Min Purchase</span>
                            <span class="info-value"><?= $voucher['currency'] ?> <?= number_format($voucher['min_purchase_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value">
                            <span class="status <?= strtoupper($voucher['status'] ?? 'ACTIVE') ?>">
                                <?= strtoupper($voucher['status'] ?? 'ACTIVE') ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Validity & Usage</div>
                    <div class="card-subtitle">Time limits and restrictions</div>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Start Date</span>
                        <span class="info-value"><?= date('M j, Y g:i A', strtotime($voucher['start_at'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">End Date</span>
                        <span class="info-value"><?= date('M j, Y g:i A', strtotime($voucher['end_at'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Usage Limit</span>
                        <span class="info-value"><?= $voucher['usage_limit_total'] ?: 'Unlimited' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Per User Limit</span>
                        <span class="info-value"><?= $voucher['usage_limit_per_user'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created</span>
                        <span class="info-value"><?= date('M j, Y g:i A', strtotime($voucher['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generated Codes -->
        <div class="codes-section">
            <div class="codes-header">
                <div class="codes-title">Generated Codes (<?= count($codes) ?> total)</div>
                <a href="#" class="export-btn" onclick="exportCodes()">Export CSV</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="codes-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>State</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($codes as $code): ?>
                            <tr>
                                <td>
                                    <span class="code-text"><?= htmlspecialchars($code['code']) ?></span>
                                </td>
                                <td>
                                    <span class="code-state <?= $code['state'] ?>">
                                        <?= $code['state'] ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($code['created_at'])) ?></td>
                                <td>
                                    <button class="copy-btn" onclick="copyCode('<?= htmlspecialchars($code['code']) ?>', this)">
                                        Copy
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function copyCode(code, button) {
            navigator.clipboard.writeText(code).then(function() {
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('copied');
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy code');
            });
        }

        function exportCodes() {
            const codes = <?= json_encode(array_column($codes, 'code')) ?>;
            const csvContent = "data:text/csv;charset=utf-8," + codes.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "voucher_codes_<?= $voucher['id'] ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>
