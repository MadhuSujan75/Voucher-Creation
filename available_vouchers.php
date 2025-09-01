<?php
require 'db.php';

// Fetch all available voucher codes (one per voucher)
$vouchers_query = "
    SELECT DISTINCT v.*, vc.code, vc.state, vc.remaining_balance
    FROM vouchers v
    JOIN voucher_codes vc ON v.id = vc.voucher_id
    WHERE v.status = 'ACTIVE'
    AND v.start_at <= NOW()
    AND v.end_at >= NOW()
    AND vc.state = 'AVAILABLE'
    AND vc.id = (
        SELECT MIN(vc2.id) 
        FROM voucher_codes vc2 
        WHERE vc2.voucher_id = v.id 
        AND vc2.state = 'AVAILABLE'
    )
    ORDER BY v.discount_type, v.percent_off DESC, v.amount_off DESC
";

$stmt = $pdo->prepare($vouchers_query);
$stmt->execute();
$available_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Voucher Codes</title>
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
            text-align: center;
            margin-bottom: 48px;
        }

        .header h1 {
            font-size: 48px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 16px;
        }

        .header p {
            font-size: 20px;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        .vouchers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }

        .voucher-card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .voucher-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--eventbrite-orange);
        }

        .voucher-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
        }

        .voucher-discount {
            font-size: 32px;
            font-weight: 700;
            color: var(--eventbrite-orange);
            margin-bottom: 12px;
            text-align: center;
        }

        .voucher-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 16px;
            text-align: center;
        }

        .voucher-code-section {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            text-align: center;
        }

        .voucher-code-label {
            font-size: 12px;
            color: var(--gray-600);
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .voucher-code {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            font-family: monospace;
            background: var(--white);
            padding: 12px 16px;
            border-radius: 8px;
            border: 2px dashed var(--eventbrite-orange);
            margin-bottom: 12px;
        }

        .copy-btn {
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .copy-btn:hover {
            background: var(--eventbrite-orange-dark);
        }

        .voucher-details {
            display: grid;
            gap: 8px;
            margin-bottom: 20px;
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

        .use-voucher-btn {
            width: 100%;
            padding: 12px 24px;
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            display: block;
        }

        .use-voucher-btn:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        .back-link {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--gray-800);
            color: var(--white);
            padding: 16px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
        }

        .back-link:hover {
            background: var(--gray-900);
            transform: translateY(-2px);
        }

        .no-vouchers {
            text-align: center;
            padding: 64px 24px;
            color: var(--gray-500);
        }

        .no-vouchers h3 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .no-vouchers p {
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

            .header h1 {
                font-size: 36px;
            }

            .header p {
                font-size: 18px;
            }

            .vouchers-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Available Voucher Codes</h1>
            <p>Use these discount codes to save money on your event tickets. Copy the code and apply it during checkout!</p>
        </div>

        <?php if (empty($available_vouchers)): ?>
            <div class="no-vouchers">
                <h3>No vouchers available</h3>
                <p>Check back later for new discount codes and special offers.</p>
                <a href="events.php" class="browse-events-btn">Browse Events</a>
            </div>
        <?php else: ?>
            <div class="vouchers-grid">
                <?php foreach ($available_vouchers as $voucher): ?>
                    <div class="voucher-card">
                        <div class="voucher-discount">
                            <?php if ($voucher['discount_type'] === 'PERCENT'): ?>
                                <?= $voucher['percent_off'] ?>% OFF
                            <?php else: ?>
                                $<?= number_format($voucher['amount_off'], 2) ?> OFF
                            <?php endif; ?>
                        </div>
                        
                        <div class="voucher-title"><?= htmlspecialchars($voucher['title']) ?></div>
                        
                        <div class="voucher-code-section">
                            <div class="voucher-code-label">Discount Code</div>
                            <div class="voucher-code" id="code-<?= $voucher['id'] ?>"><?= htmlspecialchars($voucher['code']) ?></div>
                            <button class="copy-btn" onclick="copyCode('<?= $voucher['id'] ?>')">Copy Code</button>
                        </div>
                        
                        <div class="voucher-details">
                            <div class="detail-row">
                                <span class="detail-label">Valid Until:</span>
                                <span class="detail-value"><?= date('M j, Y', strtotime($voucher['end_at'])) ?></span>
                            </div>
                            <?php if ($voucher['min_purchase_amount'] > 0): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Minimum Purchase:</span>
                                    <span class="detail-value">$<?= number_format($voucher['min_purchase_amount'], 2) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Remaining Balance:</span>
                                <span class="detail-value">$<?= number_format($voucher['remaining_balance'], 2) ?></span>
                            </div>
                        </div>
                        
                        <a href="events.php" class="use-voucher-btn">Use This Code</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <a href="events.php" class="back-link">← Back to Events</a>

    <script>
        function copyCode(voucherId) {
            const codeElement = document.getElementById('code-' + voucherId);
            const code = codeElement.textContent;
            
            navigator.clipboard.writeText(code).then(function() {
                // Change button text temporarily
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.style.background = 'var(--success-green)';
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.style.background = 'var(--eventbrite-orange)';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Code: ' + code);
            });
        }
    </script>
</body>
</html>
