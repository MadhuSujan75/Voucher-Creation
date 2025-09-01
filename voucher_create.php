<?php
require 'db.php';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $discount_type = $_POST['discount_type'];
    $percent_off = $_POST['percent_off'] ?: NULL;
    $amount_off = $_POST['amount_off'] ?: NULL;
    $min_purchase = $_POST['min_purchase'] ?: NULL;
    $usage_limit_total = $_POST['usage_limit_total'] ?: NULL;
    $usage_limit_per_user = $_POST['usage_limit_per_user'] ?: 1;
    $start_at = $_POST['start_at'];
    $end_at = $_POST['end_at'];
    $created_by = 1; // example admin/manager id

    // Voucher code options
    $code_quantity = (int)($_POST['code_quantity'] ?: 0);
    $code_prefix = $_POST['code_prefix'] ?: '';
    $code_length = (int)($_POST['code_length'] ?: 10);

    // Validation
    if (!$title) $errors[] = "Title is required.";
    if (!in_array($discount_type, ['PERCENT', 'FIXED'])) $errors[] = "Invalid discount type.";
    if ($discount_type === 'PERCENT' && !$percent_off) $errors[] = "Percent Off is required.";
    if ($discount_type === 'FIXED' && !$amount_off) $errors[] = "Amount Off is required.";
    if ($code_quantity < 1) $errors[] = "Code quantity must be at least 1.";
    if ($code_length < 4) $errors[] = "Code length must be at least 4.";

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Insert voucher
            $stmt = $pdo->prepare("INSERT INTO vouchers 
                (title, discount_type, percent_off, amount_off, min_purchase_amount, usage_limit_total, usage_limit_per_user, start_at, end_at, created_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'DRAFT')");
            $stmt->execute([$title, $discount_type, $percent_off, $amount_off, $min_purchase, $usage_limit_total, $usage_limit_per_user, $start_at, $end_at, $created_by]);
            $voucher_id = $pdo->lastInsertId();

            // Generate codes
            for ($i = 0; $i < $code_quantity; $i++) {
                $code = $code_prefix . strtoupper(bin2hex(random_bytes($code_length / 2)));
                $stmt = $pdo->prepare("INSERT INTO voucher_codes (voucher_id, batch_id, code) VALUES (?, NULL, ?)");
                $stmt->execute([$voucher_id, $code]);
            }

            $pdo->commit();
            header("Location: voucher_view.php?id=$voucher_id");
            exit;
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
    <title>Create Voucher + Codes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #0056b3;
        }

        .error {
            color: #d93025;
            margin-bottom: 15px;
        }

        hr {
            margin: 25px 0;
            border: none;
            border-top: 1px solid #ccc;
        }

        h3 {
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Create Voucher + Codes</h1>
        <?php if ($errors): ?>
            <?php foreach ($errors as $e) echo "<div class='error'>$e</div>"; ?>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>Discount Type</label>
                <select name="discount_type" id="discount_type" required>
                    <option value="PERCENT">Percentage</option>
                    <option value="FIXED">Fixed Amount</option>
                </select>
            </div>

            <div class="form-group" id="percent_group">
                <label>Percent Off (%)</label>
                <input type="number" step="0.01" name="percent_off" placeholder="e.g., 15">
            </div>

            <div class="form-group" id="amount_group" style="display:none;">
                <label>Amount Off</label>
                <input type="number" step="0.01" name="amount_off" placeholder="e.g., 100">
            </div>

            <div class="form-group">
                <label>Minimum Purchase Amount</label>
                <input type="number" step="0.01" name="min_purchase" placeholder="Optional">
            </div>

            <div class="form-group">
                <label>Usage Limit (Total)</label>
                <input type="number" name="usage_limit_total" placeholder="e.g., 1000">
            </div>

            <div class="form-group">
                <label>Usage Limit Per User</label>
                <input type="number" name="usage_limit_per_user" value="1">
            </div>

            <div class="form-group">
                <label>Start Date & Time</label>
                <input type="datetime-local" name="start_at" required>
            </div>

            <div class="form-group">
                <label>End Date & Time</label>
                <input type="datetime-local" name="end_at" required>
            </div>

            <hr>
            <h3>Generate Voucher Codes</h3>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="code_quantity" required>
            </div>

            <div class="form-group">
                <label>Prefix</label>
                <input type="text" name="code_prefix">
            </div>

            <div class="form-group">
                <label>Code Length</label>
                <input type="number" name="code_length" value="10">
            </div>

            <button type="submit">Create Voucher + Codes</button>
        </form>
    </div>

    <script>
        const discountSelect = document.getElementById('discount_type');
        const percentGroup = document.getElementById('percent_group');
        const amountGroup = document.getElementById('amount_group');

        discountSelect.addEventListener('change', function() {
            if (this.value === 'PERCENT') {
                percentGroup.style.display = 'block';
                amountGroup.style.display = 'none';
            } else {
                percentGroup.style.display = 'none';
                amountGroup.style.display = 'block';
            }
        });
    </script>

</body>

</html>