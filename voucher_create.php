<?php
require 'db.php';
$errors = [];
$success = '';

// Fetch categories for checkbox list
$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $discount_type = $_POST['discount_type'];
    $percent_off = $_POST['percent_off'] ?: NULL;
    $amount_off = $_POST['amount_off'] ?: NULL;
    $max_discount_amount = $_POST['max_discount_amount'] ?: NULL;
    $currency = $_POST['currency'] ?: 'INR';
    $min_purchase = $_POST['min_purchase'] ?: NULL;
    $usage_limit_total = $_POST['usage_limit_total'] ?: NULL;
    $usage_limit_per_user = $_POST['usage_limit_per_user'] ?: 1;
    $start_at = $_POST['start_at'];
    $end_at = $_POST['end_at'];
    $created_by = 1; // example admin/manager id

    // Voucher applicability
    $applicability = $_POST['applicability'] ?? []; // array of 'ALL', 'category_X', 'event_X_Y'

    // Voucher code options
    $code_quantity = (int)($_POST['code_quantity'] ?: 0);
    $code_prefix = $_POST['code_prefix'] ?: '';
    $code_length = (int)($_POST['code_length'] ?: 10);

    // Validation
    if (!$title) $errors[] = "Title is required.";
    if (!in_array($discount_type, ['PERCENT', 'FIXED', 'STORED_VALUE'])) $errors[] = "Invalid discount type.";
    if ($discount_type === 'PERCENT' && !$percent_off) $errors[] = "Percent Off required.";
    if ($discount_type === 'FIXED' && !$amount_off) $errors[] = "Amount Off required.";
    if ($code_quantity < 1) $errors[] = "Code quantity must be at least 1.";
    if ($code_length < 4) $errors[] = "Code length must be at least 4.";

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Insert voucher
            $stmt = $pdo->prepare("INSERT INTO vouchers
                (title, discount_type, percent_off, amount_off, max_discount_amount, currency, min_purchase_amount,
                 usage_limit_total, usage_limit_per_user, start_at, end_at, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $title,
                $discount_type,
                $percent_off,
                $amount_off,
                $max_discount_amount,
                $currency,
                $min_purchase,
                $usage_limit_total,
                $usage_limit_per_user,
                $start_at,
                $end_at,
                $created_by
            ]);
            $voucher_id = $pdo->lastInsertId();

            // Insert voucher applicability
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

            // Insert voucher batch
            $stmt = $pdo->prepare("INSERT INTO voucher_batches
                (voucher_id, requested_quantity, generated_quantity, code_prefix, code_length, created_by, created_at)
                VALUES (?, ?, 0, ?, ?, ?, NOW())");
            $stmt->execute([$voucher_id, $code_quantity, $code_prefix, $code_length, $created_by]);
            $batch_id = $pdo->lastInsertId();

            // Generate codes
            for ($i = 0; $i < $code_quantity; $i++) {
                $code = $code_prefix . strtoupper(bin2hex(random_bytes($code_length / 2)));
                $stmt = $pdo->prepare("INSERT INTO voucher_codes (voucher_id, batch_id, code, state, created_at) VALUES (?, ?, ?, 'AVAILABLE', NOW())");
                $stmt->execute([$voucher_id, $batch_id, $code]);
            }

            // Update batch generated quantity
            $stmt = $pdo->prepare("UPDATE voucher_batches SET generated_quantity=? WHERE id=?");
            $stmt->execute([$code_quantity, $batch_id]);

            $pdo->commit();
            $success = "Voucher created successfully!";
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
            max-width: 700px;
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
            transition: .3s;
        }

        button:hover {
            background: #0056b3;
        }

        .error {
            color: #d93025;
            margin-bottom: 15px;
        }

        .success {
            color: green;
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

        .events_container {
            margin-left: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Create Voucher + Codes</h1>
        <?php if ($errors): foreach ($errors as $e) echo "<div class='error'>$e</div>";
        endif; ?>
        <?php if ($success) echo "<div class='success'>$success</div>"; ?>

        <form method="post">
            <!-- Basic Info -->
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>Discount Type</label>
                <select name="discount_type" id="discount_type" required>
                    <option value="PERCENT">Percentage</option>
                    <option value="FIXED">Fixed Amount</option>
                    <option value="STORED_VALUE">Stored Value</option>
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
                <label>Max Discount Amount</label>
                <input type="number" step="0.01" name="max_discount_amount">
            </div>

            <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" value="INR" maxlength="3">
            </div>

            <div class="form-group">
                <label>Minimum Purchase Amount</label>
                <input type="number" step="0.01" name="min_purchase">
            </div>

            <div class="form-group">
                <label>Usage Limit (Total)</label>
                <input type="number" name="usage_limit_total">
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
            <h3>Voucher Applicability</h3>
            <div class="form-group">
                <label><input type="checkbox" name="applicability[]" value="ALL"> All</label>
            </div>

            <?php foreach ($categories as $c): ?>
                <div class="form-group">
                    <label><input type="checkbox" class="category_checkbox" value="category_<?= $c['category_id'] ?>" name="applicability[]"> <?= htmlspecialchars($c['name']) ?></label>
                    <div class="events_container"></div>
                </div>
            <?php endforeach; ?>

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
        // Discount toggle (existing)
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

        // Global "All" checkbox
        const allCheckbox = document.querySelector('input[name="applicability[]"][value="ALL"]');
        allCheckbox.addEventListener('change', function() {
            const checked = this.checked;
            document.querySelectorAll('.category_checkbox').forEach(catCb => {
                catCb.checked = checked;
                // trigger event checkbox population
                catCb.dispatchEvent(new Event('change'));
            });
            // after population, check all events if "All" is checked
            if (checked) {
                document.querySelectorAll('.events_container input[type="checkbox"]').forEach(evCb => evCb.checked = true);
            }
        });

        // Fetch events for category checkboxes
        document.querySelectorAll('.category_checkbox').forEach(catCb => {
            catCb.addEventListener('change', function() {
                const container = this.closest('.form-group').querySelector('.events_container');
                container.innerHTML = '';
                if (!this.checked) return;
                const catId = this.value.split('_')[1];
                fetch('get_events.php?category_id=' + catId)
                    .then(res => res.json())
                    .then(data => {
                        // Add "All Events" option
                        const allEventsCb = document.createElement('label');
                        allEventsCb.innerHTML = `<input type="checkbox" class="all_events_cb" data-cat="${catId}" value="event_${catId}_0" name="applicability[]"> All Events`;
                        container.appendChild(allEventsCb);
                        container.appendChild(document.createElement('br'));

                        data.forEach(ev => {
                            const cb = document.createElement('label');
                            cb.innerHTML = `<input type="checkbox" name="applicability[]" value="event_${catId}_${ev.id}"> ${ev.name}`;
                            container.appendChild(cb);
                            container.appendChild(document.createElement('br'));
                        });

                        // Add listener for "All Events" in this category
                        container.querySelector('.all_events_cb').addEventListener('change', function() {
                            const checked = this.checked;
                            container.querySelectorAll('input[type="checkbox"]:not(.all_events_cb)').forEach(evCb => {
                                evCb.checked = checked;
                            });
                        });
                    });
            });
        });
    </script>
</body>

</html>