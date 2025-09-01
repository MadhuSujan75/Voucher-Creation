<?php
require 'db.php';
$errors = [];
$success = '';

// Fetch categories for checkbox list
$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Check for success after redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = "Voucher created successfully!";
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
    $created_by = 1; // example admin/manager id

    // Voucher applicability
    $applicability = $_POST['applicability'] ?? [];

    // Voucher code options
    $code_quantity = (int)($_POST['code_quantity'] ?: 0);
    $code_prefix = $_POST['code_prefix'] ?: '';
    $code_length = (int)($_POST['code_length'] ?: 10);

    // Validation
    if (!$title) $errors[] = "Title is required.";
    if (!in_array($discount_type, ['PERCENT', 'FIXED'])) $errors[] = "Invalid discount type.";
    if ($discount_type === 'PERCENT' && !$percent_off) $errors[] = "Percent Off required.";
    if ($discount_type === 'FIXED' && !$amount_off) $errors[] = "Amount Off required.";
    if ($code_quantity < 1) $errors[] = "Code quantity must be at least 1.";
    if ($code_length < 4) $errors[] = "Code length must be at least 4.";

    // Optional: check if voucher with same title exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vouchers WHERE title = ?");
    $stmt->execute([$title]);
    if ($stmt->fetchColumn() > 0) $errors[] = "A voucher with this title already exists.";

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Insert voucher
            $stmt = $pdo->prepare("INSERT INTO vouchers
                (title, discount_type, percent_off, amount_off, currency, min_purchase_amount,
                 start_at, end_at, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $title,
                $discount_type,
                $percent_off,
                $amount_off,
                $currency,
                $min_purchase,
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
            header("Location: vouchers_list.php?success=1");
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
    <title>Create Voucher</title>
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
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-content {
            text-align: left;
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

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .header p {
            color: var(--gray-600);
            font-size: 18px;
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
        }

        .submit-btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            box-shadow: 0 2px 4px rgba(255, 128, 0, 0.2);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, var(--eventbrite-orange-light), var(--eventbrite-orange));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 128, 0, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
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

        .highlight-input {
            position: relative;
        }

        .highlight-input::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--eventbrite-orange), transparent);
            border-radius: 8px 8px 0 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .highlight-input input:focus + .highlight-input::after {
            opacity: 1;
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

            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .header-content {
                text-align: center;
            }

            .header h1 {
                font-size: 28px;
            }

            .form-column {
                padding: 24px;
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
            <div class="header-content">
                <h1>Create Voucher</h1>
                <p>Set up promotional vouchers with custom discount codes</p>
            </div>
            <a href="vouchers_list.php" class="back-btn">← Back to List</a>
        </div>

        <?php if ($errors): ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" class="form-container">
            <div class="form-grid">
                <!-- Left Column -->
                <div class="form-column">
                    <h2 class="section-title">Voucher Details</h2>

                    <div class="form-group">
                        <label for="title">Voucher Title</label>
                        <input type="text" name="title" id="title" required placeholder="Enter a descriptive title">
                    </div>

                    <div class="form-group">
                        <label for="discount_type">Discount Type</label>
                        <select name="discount_type" id="discount_type" required>
                            <option value="PERCENT">Percentage Discount</option>
                            <option value="FIXED">Fixed Amount Off</option>
                        </select>
                    </div>

                    <div class="form-group" id="percent_group">
                        <label for="percent_off">Percentage Off (%)</label>
                        <input type="number" step="0.01" name="percent_off" id="percent_off" placeholder="e.g., 25">
                    </div>

                    <div class="form-group" id="amount_group" style="display:none;">
                        <label for="amount_off">Amount Off</label>
                        <input type="number" step="0.01" name="amount_off" id="amount_off" placeholder="e.g., 500">
                    </div>

                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select name="currency" id="currency" required>
                            <option value="USD" selected>USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="INR">INR - Indian Rupee</option>
                            <option value="JPY">JPY - Japanese Yen</option>
                            <option value="CAD">CAD - Canadian Dollar</option>
                            <option value="AUD">AUD - Australian Dollar</option>
                            <option value="CHF">CHF - Swiss Franc</option>
                            <option value="CNY">CNY - Chinese Yuan</option>
                            <option value="SGD">SGD - Singapore Dollar</option>
                            <option value="HKD">HKD - Hong Kong Dollar</option>
                            <option value="NZD">NZD - New Zealand Dollar</option>
                            <option value="SEK">SEK - Swedish Krona</option>
                            <option value="NOK">NOK - Norwegian Krone</option>
                            <option value="DKK">DKK - Danish Krone</option>
                            <option value="PLN">PLN - Polish Zloty</option>
                            <option value="CZK">CZK - Czech Koruna</option>
                            <option value="HUF">HUF - Hungarian Forint</option>
                            <option value="BRL">BRL - Brazilian Real</option>
                            <option value="MXN">MXN - Mexican Peso</option>
                            <option value="ZAR">ZAR - South African Rand</option>
                            <option value="KRW">KRW - South Korean Won</option>
                            <option value="THB">THB - Thai Baht</option>
                            <option value="MYR">MYR - Malaysian Ringgit</option>
                            <option value="PHP">PHP - Philippine Peso</option>
                            <option value="IDR">IDR - Indonesian Rupiah</option>
                            <option value="VND">VND - Vietnamese Dong</option>
                            <option value="TRY">TRY - Turkish Lira</option>
                            <option value="RUB">RUB - Russian Ruble</option>
                            <option value="AED">AED - UAE Dirham</option>
                            <option value="SAR">SAR - Saudi Riyal</option>
                            <option value="QAR">QAR - Qatari Riyal</option>
                            <option value="KWD">KWD - Kuwaiti Dinar</option>
                            <option value="BHD">BHD - Bahraini Dinar</option>
                            <option value="OMR">OMR - Omani Rial</option>
                            <option value="JOD">JOD - Jordanian Dinar</option>
                            <option value="LBP">LBP - Lebanese Pound</option>
                            <option value="EGP">EGP - Egyptian Pound</option>
                            <option value="ILS">ILS - Israeli Shekel</option>
                            <option value="PKR">PKR - Pakistani Rupee</option>
                            <option value="BDT">BDT - Bangladeshi Taka</option>
                            <option value="LKR">LKR - Sri Lankan Rupee</option>
                            <option value="NPR">NPR - Nepalese Rupee</option>
                            <option value="AFN">AFN - Afghan Afghani</option>
                            <option value="IRR">IRR - Iranian Rial</option>
                            <option value="IQD">IQD - Iraqi Dinar</option>
                            <option value="SYP">SYP - Syrian Pound</option>
                            <option value="YER">YER - Yemeni Rial</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="min_purchase">Minimum Purchase Amount</label>
                        <input type="number" step="0.01" name="min_purchase" id="min_purchase" placeholder="Optional minimum">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_at">Start Date & Time</label>
                            <input type="datetime-local" name="start_at" id="start_at" required>
                        </div>
                        <div class="form-group">
                            <label for="end_at">End Date & Time</label>
                            <input type="datetime-local" name="end_at" id="end_at" required>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="form-column">
                    <h2 class="section-title">Code Generation</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="code_quantity">Number of Codes</label>
                            <input type="number" name="code_quantity" id="code_quantity" required min="1" placeholder="e.g., 100">
                        </div>
                        <div class="form-group">
                            <label for="code_length">Code Length</label>
                            <input type="number" name="code_length" id="code_length" value="10" min="4" max="20">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="code_prefix">Code Prefix (Optional)</label>
                        <input type="text" name="code_prefix" id="code_prefix" placeholder="e.g., SAVE2024">
                    </div>

                    <h2 class="section-title">Voucher Applicability</h2>

                    <div class="form-group">
                        <label>Choose where this voucher can be used:</label>
                        <div class="checkbox-container">
                            <div class="checkbox-item special-checkbox">
                                <label>
                                    <input type="checkbox" name="applicability[]" value="ALL" id="all_checkbox">
                                    Apply to All Categories & Events
                                </label>
                            </div>

                            <?php foreach ($categories as $c): ?>
                                <div class="category-group">
                                    <div class="checkbox-item">
                                        <label>
                                            <input type="checkbox" class="category_checkbox" value="category_<?= $c['category_id'] ?>" name="applicability[]" id="cat_<?= $c['category_id'] ?>">
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
                    <button type="submit" class="submit-btn">Create Voucher & Generate Codes</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Discount toggle
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
                        const eventDiv = document.createElement('div');
                        eventDiv.className = 'checkbox-item';
                        eventDiv.innerHTML = `
                            <label>
                                <input type="checkbox" class="event_checkbox" name="applicability[]" value="event_${catId}_${ev.id}">
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
        });
    </script>
</body>

</html>