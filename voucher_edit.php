<?php
require 'db.php';
$errors = [];
$success = '';

// Get voucher ID from URL
$voucher_id = $_GET['id'] ?? null;

if (!$voucher_id || !is_numeric($voucher_id)) {
    header("Location: vouchers_list.php?error=invalid_voucher");
    exit();
}

// Fetch categories for checkbox list
$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing voucher data
try {
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
    $stmt->execute([$voucher_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        header("Location: vouchers_list.php?error=voucher_not_found");
        exit();
    }

    // Fetch existing voucher applicability
    $stmt = $pdo->prepare("SELECT * FROM voucher_applicability WHERE voucher_id = ?");
    $stmt->execute([$voucher_id]);
    $existing_applicability = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    header("Location: vouchers_list.php?error=database_error");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $discount_type = $_POST['discount_type'];
    $percent_off = $_POST['percent_off'] ?: NULL;
    $amount_off = $_POST['amount_off'] ?: NULL;

    $currency = $_POST['currency'] ?: 'USD';
    $min_purchase = $_POST['min_purchase'] ?: NULL;

    $start_at = $_POST['start_at'];
    $end_at = $_POST['end_at'];
    $status = $_POST['status'] ?? 'ACTIVE';

    // Voucher applicability
    $applicability = $_POST['applicability'] ?? []; // array of 'ALL', 'category_X', 'event_X_Y'

    // Validation
    if (!$title) $errors[] = "Title is required.";
    if (!in_array($discount_type, ['PERCENT', 'FIXED'])) $errors[] = "Invalid discount type.";
    if ($discount_type === 'PERCENT' && !$percent_off) $errors[] = "Percent Off required.";
    if ($discount_type === 'FIXED' && !$amount_off) $errors[] = "Amount Off required.";
    if (!in_array($status, ['ACTIVE', 'INACTIVE', 'EXPIRED', 'ARCHIVED'])) $errors[] = "Invalid status.";

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Update voucher
            $stmt = $pdo->prepare("UPDATE vouchers SET
                title = ?, discount_type = ?, percent_off = ?, amount_off = ?, 
                currency = ?, min_purchase_amount = ?,
                start_at = ?, end_at = ?, status = ?, updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $title,
                $discount_type,
                $percent_off,
                $amount_off,
                $currency,
                $min_purchase,
                $start_at,
                $end_at,
                $status,
                $voucher_id
            ]);

            // Delete existing applicability
            $stmt = $pdo->prepare("DELETE FROM voucher_applicability WHERE voucher_id = ?");
            $stmt->execute([$voucher_id]);

            // Insert new voucher applicability
            foreach ($applicability as $item) {
                if ($item === 'ALL') {
                    $stmt = $pdo->prepare("INSERT INTO voucher_applicability (voucher_id, scope) VALUES (?, 'ALL')");
                    $stmt->execute([$voucher_id]);
                } elseif (strpos($item, 'category_') === 0) {
                    $cat_id = (int)str_replace('category_', '', $item);
                    $stmt = $pdo->prepare("INSERT INTO voucher_applicability (voucher_id, scope, category_id) VALUES (?, 'CATEGORY', ?)");
                    $stmt->execute([$voucher_id, $cat_id]);
                } elseif (strpos($item, 'event_') === 0) {
                    list(, $cat_id, $event_id) = explode('_', $item);
                    $stmt = $pdo->prepare("INSERT INTO voucher_applicability (voucher_id, scope, category_id, event_id) VALUES (?, 'EVENT', ?, ?)");
                    $stmt->execute([$voucher_id, (int)$cat_id, (int)$event_id]);
                }
            }

            $pdo->commit();
            header("Location: voucher_view.php?id=" . $voucher_id . "&success=1");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Voucher - <?= htmlspecialchars($voucher['title']) ?></title>
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

        .form-container {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .form-column {
            padding: 32px;
        }

        .form-column:first-child {
            border-right: 1px solid var(--gray-200);
            background: var(--white);
        }

        .form-column:last-child {
            background: var(--gray-50);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 24px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--eventbrite-orange);
            display: inline-block;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.2s ease;
            background: var(--white);
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--eventbrite-orange);
            box-shadow: 0 0 0 3px rgba(255, 128, 0, 0.1);
        }

        input:hover, select:hover {
            border-color: var(--gray-400);
        }

        .checkbox-container {
            max-height: 350px;
            overflow-y: auto;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            padding: 16px;
            background: var(--white);
        }

        .checkbox-container::-webkit-scrollbar {
            width: 8px;
        }

        .checkbox-container::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 4px;
        }

        .checkbox-container::-webkit-scrollbar-thumb {
            background: var(--eventbrite-orange);
            border-radius: 4px;
        }

        .checkbox-item {
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background-color 0.15s ease;
        }

        .checkbox-item:hover {
            background-color: var(--gray-100);
        }

        .checkbox-item label {
            display: flex;
            align-items: center;
            font-weight: 500;
            cursor: pointer;
            margin: 0;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 12px;
            accent-color: var(--eventbrite-orange);
            cursor: pointer;
        }

        .special-checkbox {
            background: linear-gradient(135deg, #fff5e6, #ffe4cc);
            border: 1px solid var(--eventbrite-orange);
            font-weight: 600;
        }

        .events-container {
            margin-left: 30px;
            margin-top: 8px;
            padding-left: 16px;
            border-left: 3px solid var(--gray-300);
        }

        .events-container .checkbox-item {
            margin-bottom: 8px;
            background: var(--gray-100);
        }

        .events-container .checkbox-item label {
            font-weight: 400;
            color: var(--gray-600);
            font-size: 14px;
        }

        .load-more-container {
            text-align: center;
            margin-top: 12px;
            padding: 8px 0;
        }

        .load-more-btn {
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .load-more-btn:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        .submit-section {
            grid-column: 1 / -1;
            padding: 24px 32px;
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 16px;
            justify-content: flex-end;
        }

        .btn {
            padding: 16px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
            color: var(--white);
            box-shadow: 0 2px 4px rgba(255, 128, 0, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--eventbrite-orange-light), var(--eventbrite-orange));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 128, 0, 0.3);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 8px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left-color: var(--error-red);
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left-color: var(--success-green);
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box h4 {
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .info-box p {
            color: #1e40af;
            font-size: 14px;
            margin: 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-column:first-child {
                border-right: none;
                border-bottom: 1px solid var(--gray-200);
                background: var(--white);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 28px;
            }

            .form-column {
                padding: 24px;
            }

            .submit-section {
                flex-direction: column;
            }
        }

        /* Custom select styling */
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Input placeholders */
        input::placeholder {
            color: var(--gray-500);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Edit Voucher</h1>
            <a href="voucher_view.php?id=<?= $voucher_id ?>" class="back-btn">← Back to View</a>
        </div>

        <?php if ($errors): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="info-box">
            <h4>⚠️ Important Note</h4>
            <p>Editing this voucher will not affect already generated codes. Only the voucher settings and applicability will be updated.</p>
        </div>

        <form method="post" class="form-container">
            <div class="form-grid">
                <!-- Left Column -->
                <div class="form-column">
                    <h2 class="section-title">Voucher Details</h2>

                    <div class="form-group">
                        <label for="title">Voucher Title</label>
                        <input type="text" name="title" id="title" required placeholder="Enter a descriptive title" value="<?= htmlspecialchars($voucher['title']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="discount_type">Discount Type</label>
                        <select name="discount_type" id="discount_type" required>
                            <option value="PERCENT" <?= $voucher['discount_type'] === 'PERCENT' ? 'selected' : '' ?>>Percentage Discount</option>
                            <option value="FIXED" <?= $voucher['discount_type'] === 'FIXED' ? 'selected' : '' ?>>Fixed Amount Off</option>
                        </select>
                    </div>

                    <div class="form-group" id="percent_group" style="<?= $voucher['discount_type'] === 'PERCENT' ? '' : 'display:none;' ?>">
                        <label for="percent_off">Percentage Off (%)</label>
                        <input type="number" step="0.01" name="percent_off" id="percent_off" placeholder="e.g., 25" value="<?= $voucher['percent_off'] ?>">
                    </div>

                    <div class="form-group" id="amount_group" style="<?= $voucher['discount_type'] === 'FIXED' ? '' : 'display:none;' ?>">
                        <label for="amount_off">Amount Off</label>
                        <input type="number" step="0.01" name="amount_off" id="amount_off" placeholder="e.g., 500" value="<?= $voucher['amount_off'] ?>">
                    </div>

                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select name="currency" id="currency" required>
                            <option value="USD" <?= ($voucher['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                            <option value="EUR" <?= ($voucher['currency'] ?? 'USD') === 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                            <option value="GBP" <?= ($voucher['currency'] ?? 'USD') === 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                            <option value="INR" <?= ($voucher['currency'] ?? 'USD') === 'INR' ? 'selected' : '' ?>>INR - Indian Rupee</option>
                            <option value="JPY" <?= ($voucher['currency'] ?? 'USD') === 'JPY' ? 'selected' : '' ?>>JPY - Japanese Yen</option>
                            <option value="CAD" <?= ($voucher['currency'] ?? 'USD') === 'CAD' ? 'selected' : '' ?>>CAD - Canadian Dollar</option>
                            <option value="AUD" <?= ($voucher['currency'] ?? 'USD') === 'AUD' ? 'selected' : '' ?>>AUD - Australian Dollar</option>
                            <option value="CHF" <?= ($voucher['currency'] ?? 'USD') === 'CHF' ? 'selected' : '' ?>>CHF - Swiss Franc</option>
                            <option value="CNY" <?= ($voucher['currency'] ?? 'USD') === 'CNY' ? 'selected' : '' ?>>CNY - Chinese Yuan</option>
                            <option value="SGD" <?= ($voucher['currency'] ?? 'USD') === 'SGD' ? 'selected' : '' ?>>SGD - Singapore Dollar</option>
                            <option value="HKD" <?= ($voucher['currency'] ?? 'USD') === 'HKD' ? 'selected' : '' ?>>HKD - Hong Kong Dollar</option>
                            <option value="NZD" <?= ($voucher['currency'] ?? 'USD') === 'NZD' ? 'selected' : '' ?>>NZD - New Zealand Dollar</option>
                            <option value="SEK" <?= ($voucher['currency'] ?? 'USD') === 'SEK' ? 'selected' : '' ?>>SEK - Swedish Krona</option>
                            <option value="NOK" <?= ($voucher['currency'] ?? 'USD') === 'NOK' ? 'selected' : '' ?>>NOK - Norwegian Krone</option>
                            <option value="DKK" <?= ($voucher['currency'] ?? 'USD') === 'DKK' ? 'selected' : '' ?>>DKK - Danish Krone</option>
                            <option value="PLN" <?= ($voucher['currency'] ?? 'USD') === 'PLN' ? 'selected' : '' ?>>PLN - Polish Zloty</option>
                            <option value="CZK" <?= ($voucher['currency'] ?? 'USD') === 'CZK' ? 'selected' : '' ?>>CZK - Czech Koruna</option>
                            <option value="HUF" <?= ($voucher['currency'] ?? 'USD') === 'HUF' ? 'selected' : '' ?>>HUF - Hungarian Forint</option>
                            <option value="BRL" <?= ($voucher['currency'] ?? 'USD') === 'BRL' ? 'selected' : '' ?>>BRL - Brazilian Real</option>
                            <option value="MXN" <?= ($voucher['currency'] ?? 'USD') === 'MXN' ? 'selected' : '' ?>>MXN - Mexican Peso</option>
                            <option value="ZAR" <?= ($voucher['currency'] ?? 'USD') === 'ZAR' ? 'selected' : '' ?>>ZAR - South African Rand</option>
                            <option value="KRW" <?= ($voucher['currency'] ?? 'USD') === 'KRW' ? 'selected' : '' ?>>KRW - South Korean Won</option>
                            <option value="THB" <?= ($voucher['currency'] ?? 'USD') === 'THB' ? 'selected' : '' ?>>THB - Thai Baht</option>
                            <option value="MYR" <?= ($voucher['currency'] ?? 'USD') === 'MYR' ? 'selected' : '' ?>>MYR - Malaysian Ringgit</option>
                            <option value="PHP" <?= ($voucher['currency'] ?? 'USD') === 'PHP' ? 'selected' : '' ?>>PHP - Philippine Peso</option>
                            <option value="IDR" <?= ($voucher['currency'] ?? 'USD') === 'IDR' ? 'selected' : '' ?>>IDR - Indonesian Rupiah</option>
                            <option value="VND" <?= ($voucher['currency'] ?? 'USD') === 'VND' ? 'selected' : '' ?>>VND - Vietnamese Dong</option>
                            <option value="TRY" <?= ($voucher['currency'] ?? 'USD') === 'TRY' ? 'selected' : '' ?>>TRY - Turkish Lira</option>
                            <option value="RUB" <?= ($voucher['currency'] ?? 'USD') === 'RUB' ? 'selected' : '' ?>>RUB - Russian Ruble</option>
                            <option value="AED" <?= ($voucher['currency'] ?? 'USD') === 'AED' ? 'selected' : '' ?>>AED - UAE Dirham</option>
                            <option value="SAR" <?= ($voucher['currency'] ?? 'USD') === 'SAR' ? 'selected' : '' ?>>SAR - Saudi Riyal</option>
                            <option value="QAR" <?= ($voucher['currency'] ?? 'USD') === 'QAR' ? 'selected' : '' ?>>QAR - Qatari Riyal</option>
                            <option value="KWD" <?= ($voucher['currency'] ?? 'USD') === 'KWD' ? 'selected' : '' ?>>KWD - Kuwaiti Dinar</option>
                            <option value="BHD" <?= ($voucher['currency'] ?? 'USD') === 'BHD' ? 'selected' : '' ?>>BHD - Bahraini Dinar</option>
                            <option value="OMR" <?= ($voucher['currency'] ?? 'USD') === 'OMR' ? 'selected' : '' ?>>OMR - Omani Rial</option>
                            <option value="JOD" <?= ($voucher['currency'] ?? 'USD') === 'JOD' ? 'selected' : '' ?>>JOD - Jordanian Dinar</option>
                            <option value="LBP" <?= ($voucher['currency'] ?? 'USD') === 'LBP' ? 'selected' : '' ?>>LBP - Lebanese Pound</option>
                            <option value="EGP" <?= ($voucher['currency'] ?? 'USD') === 'EGP' ? 'selected' : '' ?>>EGP - Egyptian Pound</option>
                            <option value="ILS" <?= ($voucher['currency'] ?? 'USD') === 'ILS' ? 'selected' : '' ?>>ILS - Israeli Shekel</option>
                            <option value="PKR" <?= ($voucher['currency'] ?? 'USD') === 'PKR' ? 'selected' : '' ?>>PKR - Pakistani Rupee</option>
                            <option value="BDT" <?= ($voucher['currency'] ?? 'USD') === 'BDT' ? 'selected' : '' ?>>BDT - Bangladeshi Taka</option>
                            <option value="LKR" <?= ($voucher['currency'] ?? 'USD') === 'LKR' ? 'selected' : '' ?>>LKR - Sri Lankan Rupee</option>
                            <option value="NPR" <?= ($voucher['currency'] ?? 'USD') === 'NPR' ? 'selected' : '' ?>>NPR - Nepalese Rupee</option>
                            <option value="AFN" <?= ($voucher['currency'] ?? 'USD') === 'AFN' ? 'selected' : '' ?>>AFN - Afghan Afghani</option>
                            <option value="IRR" <?= ($voucher['currency'] ?? 'USD') === 'IRR' ? 'selected' : '' ?>>IRR - Iranian Rial</option>
                            <option value="IQD" <?= ($voucher['currency'] ?? 'USD') === 'IQD' ? 'selected' : '' ?>>IQD - Iraqi Dinar</option>
                            <option value="SYP" <?= ($voucher['currency'] ?? 'USD') === 'SYP' ? 'selected' : '' ?>>SYP - Syrian Pound</option>
                            <option value="YER" <?= ($voucher['currency'] ?? 'USD') === 'YER' ? 'selected' : '' ?>>YER - Yemeni Rial</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="min_purchase">Minimum Purchase Amount</label>
                        <input type="number" step="0.01" name="min_purchase" id="min_purchase" placeholder="Optional minimum" value="<?= $voucher['min_purchase_amount'] ?>">
                    </div>



                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_at">Start Date & Time</label>
                            <input type="datetime-local" name="start_at" id="start_at" required value="<?= date('Y-m-d\TH:i', strtotime($voucher['start_at'])) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_at">End Date & Time</label>
                            <input type="datetime-local" name="end_at" id="end_at" required value="<?= date('Y-m-d\TH:i', strtotime($voucher['end_at'])) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" required>
                            <option value="ACTIVE" <?= ($voucher['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                            <option value="INACTIVE" <?= ($voucher['status'] ?? 'ACTIVE') === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                            <option value="EXPIRED" <?= ($voucher['status'] ?? 'ACTIVE') === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
                            <option value="ARCHIVED" <?= ($voucher['status'] ?? 'ACTIVE') === 'ARCHIVED' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="form-column">
                    <h2 class="section-title">Voucher Applicability</h2>

                    <div class="form-group">
                        <label>Choose where this voucher can be used:</label>
                        <div class="checkbox-container">
                            <div class="checkbox-item special-checkbox">
                                <label>
                                    <input type="checkbox" name="applicability[]" value="ALL" id="all_checkbox" <?= in_array('ALL', array_column($existing_applicability, 'scope')) ? 'checked' : '' ?>>
                                    Apply to All Categories & Events
                                </label>
                            </div>

                            <?php foreach ($categories as $c): ?>
                                <div class="category-group">
                                    <div class="checkbox-item">
                                        <label>
                                            <input type="checkbox" class="category_checkbox" value="category_<?= $c['category_id'] ?>" name="applicability[]" id="cat_<?= $c['category_id'] ?>" <?= in_array($c['category_id'], array_column(array_filter($existing_applicability, function($a) { return $a['scope'] === 'CATEGORY'; }), 'category_id')) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </label>
                                    </div>
                                    <div class="events-container"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="submit-section">
                    <a href="voucher_view.php?id=<?= $voucher_id ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Voucher</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Pass existing applicability data to JavaScript
        const existingApplicability = <?= json_encode($existing_applicability) ?>;
        
        // Discount toggle
        const discountSelect = document.getElementById('discount_type');
        const percentGroup = document.getElementById('percent_group');
        const amountGroup = document.getElementById('amount_group');
        
        discountSelect.addEventListener('change', function() {
            if (this.value === 'PERCENT') {
                percentGroup.style.display = 'block';
                amountGroup.style.display = 'none';
            } else if (this.value === 'FIXED') {
                percentGroup.style.display = 'none';
                amountGroup.style.display = 'block';
            } else {
                percentGroup.style.display = 'none';
                amountGroup.style.display = 'none';
            }
        });

        // Function to load events for a category with pagination
        function loadEventsForCategory(catId, container, page = 1) {
            fetch(`get_events.php?category_id=${catId}&page=${page}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    // Add "All Events" option (only on first page)
                    if (page === 1) {
                        const allEventsDiv = document.createElement('div');
                        allEventsDiv.className = 'checkbox-item special-checkbox';
                        allEventsDiv.innerHTML = `
                            <label>
                                <input type="checkbox" class="all_events_cb" data-cat="${catId}" value="event_${catId}_0" name="applicability[]">
                                All Events in Category (${data.pagination.total_events} total)
                            </label>
                        `;
                        container.appendChild(allEventsDiv);
                        
                        // Add listener for "All Events" in this category
                        const allEventsCb = container.querySelector('.all_events_cb');
                        allEventsCb.addEventListener('change', function() {
                            const checked = this.checked;
                            container.querySelectorAll('.event_checkbox').forEach(evCb => {
                                evCb.checked = checked;
                            });
                            
                            // Update global "All" checkbox state
                            updateGlobalAllCheckbox();
                        });
                    }
                    
                    // Add individual events
                    data.events.forEach(ev => {
                        // Only check if this specific event is explicitly selected in the database
                        const isEventSelected = existingApplicability.some(app => 
                            app.scope === 'EVENT' && app.category_id == catId && app.event_id == ev.id
                        );
                        
                        const eventDiv = document.createElement('div');
                        eventDiv.className = 'checkbox-item';
                        eventDiv.innerHTML = `
                            <label>
                                <input type="checkbox" class="event_checkbox" name="applicability[]" value="event_${catId}_${ev.id}" ${isEventSelected ? 'checked' : ''}>
                                ${ev.name}
                            </label>
                        `;
                        container.appendChild(eventDiv);
                    });
                    
                    // Add "Load More" button if there are more pages
                    if (data.pagination.has_more) {
                        const loadMoreDiv = document.createElement('div');
                        loadMoreDiv.className = 'checkbox-item load-more-container';
                        loadMoreDiv.innerHTML = `
                            <button type="button" class="load-more-btn" data-cat="${catId}" data-page="${page + 1}">
                                Load More Events (${data.pagination.total_events - (page * data.pagination.per_page)} remaining)
                            </button>
                        `;
                        container.appendChild(loadMoreDiv);
                        
                        // Add listener for load more button
                        const loadMoreBtn = loadMoreDiv.querySelector('.load-more-btn');
                        loadMoreBtn.addEventListener('click', function() {
                            const nextPage = parseInt(this.dataset.page);
                            this.remove(); // Remove the button
                            loadEventsForCategory(catId, container, nextPage);
                        });
                    }
                    
                    // Add listeners for individual event checkboxes
                    container.querySelectorAll('.event_checkbox').forEach(evCb => {
                        evCb.addEventListener('change', function() {
                            const eventCheckboxes = container.querySelectorAll('.event_checkbox');
                            const checkedEvents = container.querySelectorAll('.event_checkbox:checked');
                            
                            // Update "All Events" checkbox based on individual selections
                            const allEventsCb = container.querySelector('.all_events_cb');
                            if (allEventsCb) {
                                if (checkedEvents.length === 0) {
                                    allEventsCb.checked = false;
                                    allEventsCb.indeterminate = false;
                                } else if (checkedEvents.length === eventCheckboxes.length) {
                                    allEventsCb.checked = true;
                                    allEventsCb.indeterminate = false;
                                } else {
                                    allEventsCb.checked = false;
                                    allEventsCb.indeterminate = true;
                                }
                            }
                            
                            // Update global "All" checkbox state
                            updateGlobalAllCheckbox();
                        });
                    });
                    
                    // Update "All Events" checkbox state after loading
                    const eventCheckboxes = container.querySelectorAll('.event_checkbox');
                    const checkedEvents = container.querySelectorAll('.event_checkbox:checked');
                    const allEventsCb = container.querySelector('.all_events_cb');
                    
                    if (allEventsCb && eventCheckboxes.length > 0) {
                        if (checkedEvents.length === 0) {
                            allEventsCb.checked = false;
                            allEventsCb.indeterminate = false;
                        } else if (checkedEvents.length === eventCheckboxes.length) {
                            allEventsCb.checked = true;
                            allEventsCb.indeterminate = false;
                        } else {
                            allEventsCb.checked = false;
                            allEventsCb.indeterminate = true;
                        }
                    }
                })
                .catch(err => {
                    console.error('Error fetching events:', err);
                });
        }

        // Function to update global "All" checkbox state
        function updateGlobalAllCheckbox() {
            const allCheckbox = document.querySelector('input[name="applicability[]"][value="ALL"]');
            const categoryCheckboxes = document.querySelectorAll('.category_checkbox');
            const checkedCategories = document.querySelectorAll('.category_checkbox:checked');
            const eventCheckboxes = document.querySelectorAll('.event_checkbox');
            const checkedEvents = document.querySelectorAll('.event_checkbox:checked');
            
            const totalCheckboxes = categoryCheckboxes.length + eventCheckboxes.length;
            const totalChecked = checkedCategories.length + checkedEvents.length;
            
            if (totalChecked === 0) {
                allCheckbox.checked = false;
                allCheckbox.indeterminate = false;
            } else if (totalChecked === totalCheckboxes) {
                allCheckbox.checked = true;
                allCheckbox.indeterminate = false;
            } else {
                allCheckbox.checked = false;
                allCheckbox.indeterminate = true;
            }
        }

        // Global "All" checkbox
        const allCheckbox = document.querySelector('input[name="applicability[]"][value="ALL"]');
        allCheckbox.addEventListener('change', function() {
            const checked = this.checked;
            
            // Select/deselect all category checkboxes
            document.querySelectorAll('.category_checkbox').forEach(catCb => {
                catCb.checked = checked;
                // Trigger change event to load events if category is checked
                if (checked) {
                    catCb.dispatchEvent(new Event('change'));
                    // Wait a bit for events to load, then select them
                    setTimeout(() => {
                        const container = catCb.closest('.category-group').querySelector('.events-container');
                        container.querySelectorAll('input[type="checkbox"]').forEach(evCb => {
                            evCb.checked = checked;
                        });
                    }, 100);
                } else {
                    // Clear events container if unchecked
                    const container = catCb.closest('.category-group').querySelector('.events-container');
                    container.innerHTML = '';
                }
            });
            
            // Select/deselect all existing event checkboxes
            document.querySelectorAll('.events-container input[type="checkbox"]').forEach(evCb => {
                evCb.checked = checked;
            });
        });

        // Add event listeners to category checkboxes
        document.querySelectorAll('.category_checkbox').forEach(catCb => {
            catCb.addEventListener('change', function() {
                // Update global "All" checkbox state
                updateGlobalAllCheckbox();
                
                const container = this.closest('.category-group').querySelector('.events-container');
                container.innerHTML = '';
                if (!this.checked) return;
                
                const catId = this.value.split('_')[1];
                loadEventsForCategory(catId, container, 1);


            });

            // Trigger change event if checkbox is already checked
            if (catCb.checked) {
                catCb.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>
