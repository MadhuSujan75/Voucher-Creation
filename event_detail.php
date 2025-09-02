<?php
require 'db.php';

$event_id = $_GET['id'] ?? null;

if (!$event_id || !is_numeric($event_id)) {
    header("Location: events.php?error=invalid_event");
    exit();
}

// Fetch event details
$stmt = $pdo->prepare("
    SELECT e.*, c.name as category_name
    FROM events e
    JOIN categories c ON e.category_id = c.category_id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: events.php?error=event_not_found");
    exit();
}

// Fetch applicable vouchers for this event (one code per voucher)
$vouchers_query = "
    SELECT DISTINCT v.*, vc.code, vc.state, vc.remaining_balance
    FROM vouchers v
    JOIN voucher_codes vc ON v.id = vc.voucher_id
    JOIN voucher_applicability va ON v.id = va.voucher_id
    WHERE v.status = 'ACTIVE'
    AND v.start_at <= NOW()
    AND v.end_at >= NOW()
    AND vc.state = 'AVAILABLE'
    AND (
        va.scope = 'ALL' OR
        (va.scope = 'CATEGORY' AND va.category_id = ?) OR
        (va.scope = 'EVENT' AND va.event_id = ?)
    )
    AND vc.id = (
        SELECT MIN(vc2.id) 
        FROM voucher_codes vc2 
        WHERE vc2.voucher_id = v.id 
        AND vc2.state = 'AVAILABLE'
    )
    ORDER BY v.discount_type, v.percent_off DESC, v.amount_off DESC
";

$stmt = $pdo->prepare($vouchers_query);
$stmt->execute([$event['category_id'], $event_id]);
$applicable_vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Default event price (you can add this to events table later)
$event_price = 50.00; // Default price for demo
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']) ?> - Event Details</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-orange: #ff6b35;
            --primary-orange-light: #ff8a5c;
            --primary-orange-dark: #e55a2b;
            --secondary-orange: #ffa726;
            --accent-green: #4caf50;
            --accent-red: #f44336;
            --accent-blue: #2196f3;
            
            --gray-900: #1a1a1a;
            --gray-800: #2d2d2d;
            --gray-700: #404040;
            --gray-600: #666666;
            --gray-500: #999999;
            --gray-400: #cccccc;
            --gray-300: #e0e0e0;
            --gray-200: #f0f0f0;
            --gray-100: #f8f8f8;
            --white: #ffffff;
            
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.2);
            --shadow-xl: 0 16px 48px rgba(0, 0, 0, 0.25);
            
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px;
        }

        .breadcrumb {
            margin-bottom: 12px;
            padding: 4px 0;
            color: var(--gray-600);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .event-header {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 24px;
            margin-bottom: 16px;
        }

        .event-info {
            background: var(--white);
            border-radius: var(--border-radius-md);
            padding: 16px;
            border: 1px solid var(--gray-200);
        }

        .event-category {
            display: inline-block;
            background: var(--primary-orange);
            color: var(--white);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .event-description {
            font-size: 14px;
            color: var(--gray-600);
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .event-details {
            display: grid;
            gap: 8px;
        }

        .event-detail {
            display: flex;
            align-items: center;
            padding: 12px;
            background: var(--white);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-200);
        }

        .event-detail-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-orange);
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 16px;
            color: var(--white);
        }

        .event-detail-content h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 2px;
        }

        .event-detail-content p {
            font-size: 13px;
            color: var(--gray-600);
        }

        .booking-section {
            background: var(--white);
            border-radius: var(--border-radius-md);
            padding: 16px;
            border: 1px solid var(--gray-200);
            height: fit-content;
            position: sticky;
            top: 12px;
        }

        .price-display {
            text-align: center;
            margin-bottom: 16px;
            padding: 12px;
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-200);
        }

        .price {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-orange);
            margin-bottom: 2px;
            line-height: 1;
        }

        .price-label {
            font-size: 12px;
            color: var(--gray-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .booking-form {
            display: grid;
            gap: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 6px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            font-family: inherit;
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-orange);
        }

        .form-group select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .voucher-section {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            padding: 12px;
            background: var(--white);
        }

        .voucher-section h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .voucher-section h4::before {
            content: '🎟️';
            font-size: 14px;
        }

        .voucher-input-group {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
        }

        .voucher-input-group input {
            flex: 1;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .voucher-btn {
            padding: 10px 12px;
            background: var(--primary-orange);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .voucher-btn:hover {
            background: var(--primary-orange-dark);
        }

        .voucher-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }

        #voucher-code:disabled {
            background: var(--gray-100);
            color: var(--gray-500);
            cursor: not-allowed;
            border-color: var(--gray-300);
        }

        .available-vouchers {
            margin-top: 12px;
            border-radius: var(--border-radius-sm);
            background: var(--white);
            border: 1px solid var(--gray-300);
            overflow: hidden;
        }

        .vouchers-header {
            padding: 10px 12px;
            background: var(--gray-100);
            color: var(--gray-800);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
            border-bottom: 1px solid var(--gray-200);
        }

        .vouchers-header:hover {
            background: var(--gray-200);
        }

        .vouchers-header::before {
            content: '🎟️';
            margin-right: 8px;
            font-size: 16px;
        }

        .toggle-arrow {
            font-size: 12px;
            transition: transform 0.2s ease;
            color: var(--gray-600);
        }

        .vouchers-content {
            max-height: 300px;
            overflow-y: auto;
            padding: 0;
            background: var(--white);
        }

        .vouchers-content::-webkit-scrollbar {
            width: 6px;
        }

        .vouchers-content::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 3px;
        }

        .vouchers-content::-webkit-scrollbar-thumb {
            background: var(--primary-orange);
            border-radius: 3px;
        }

        .voucher-item {
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .voucher-item:hover {
            background: var(--gray-50);
        }

        .voucher-item:last-child {
            border-bottom: none;
        }

        .voucher-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .voucher-badge {
            background: var(--primary-orange);
            color: var(--white);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .voucher-info {
            flex: 1;
        }

        .voucher-title {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 14px;
            margin-bottom: 2px;
        }

        .voucher-code {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--gray-600);
            font-weight: 500;
        }

        .voucher-action {
            color: var(--primary-orange);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .apply-icon {
            font-size: 16px;
            transition: transform 0.2s ease;
        }

        .voucher-item:hover .apply-icon {
            transform: translateX(2px);
        }

        .price-breakdown {
            background: var(--gray-50);
            border-radius: var(--border-radius-sm);
            padding: 12px;
            margin: 12px 0;
            border: 1px solid var(--gray-200);
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            padding: 2px 0;
            font-size: 13px;
        }

        .price-row.total {
            border-top: 1px solid var(--gray-300);
            padding-top: 8px;
            margin-top: 8px;
            font-weight: 700;
            font-size: 15px;
            color: var(--gray-900);
        }

        .discount {
            color: var(--accent-green);
            font-weight: 600;
        }

        .book-btn {
            width: 100%;
            padding: 12px 20px;
            background: var(--primary-orange);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .book-btn:hover {
            background: var(--primary-orange-dark);
        }

        .book-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }

        /* Swiggy-style Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }

        .toast {
            background: var(--white);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary-orange);
        }

        .toast-success::before {
            background: #10b981;
        }

        .toast-error::before {
            background: #ef4444;
        }

        .toast-info::before {
            background: #3b82f6;
        }

        .toast-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: var(--white);
            flex-shrink: 0;
        }

        .toast-success .toast-icon {
            background: #10b981;
        }

        .toast-error .toast-icon {
            background: #ef4444;
        }

        .toast-info .toast-icon {
            background: #3b82f6;
        }

        .toast-message {
            flex: 1;
            color: var(--gray-800);
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .toast-close:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .toast {
                padding: 14px 16px;
                font-size: 13px;
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .event-header {
                grid-template-columns: 1fr 400px;
                gap: 20px;
            }
        }

        @media (max-width: 1024px) {
            .event-header {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .booking-section {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 8px;
            }

            .event-info {
                padding: 12px;
            }

            .event-title {
                font-size: 24px;
            }

            .price {
                font-size: 28px;
            }

            .voucher-item {
                padding: 8px 10px;
            }

            .book-btn {
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .event-title {
                font-size: 20px;
            }

            .price {
                font-size: 24px;
            }

            .voucher-input-group {
                flex-direction: column;
            }

            .voucher-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="categories.php">Categories</a> → 
            <a href="events.php?category=<?= $event['category_id'] ?>"><?= htmlspecialchars($event['category_name']) ?></a> → 
            <?= htmlspecialchars($event['title']) ?>
        </div>

        <div class="event-header">
            <div class="event-info">
                <div class="event-category"><?= htmlspecialchars($event['category_name']) ?></div>
                <h1 class="event-title"><?= htmlspecialchars($event['title']) ?></h1>
                <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>

                <div class="event-details">
                    <div class="event-detail">
                        <div class="event-detail-icon">📅</div>
                        <div class="event-detail-content">
                            <h4>Date & Time</h4>
                            <p><?= date('l, F j, Y', strtotime($event['event_date'])) ?></p>
                            <p><?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?></p>
                        </div>
                    </div>

                    <?php if ($event['venue']): ?>
                        <div class="event-detail">
                            <div class="event-detail-icon">📍</div>
                            <div class="event-detail-content">
                                <h4>Venue</h4>
                                <p><?= htmlspecialchars($event['venue']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="event-detail">
                        <div class="event-detail-icon">🎫</div>
                        <div class="event-detail-content">
                            <h4>Event Type</h4>
                            <p><?= htmlspecialchars($event['category_name']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="booking-section">
                <div class="price-display">
                    <div class="price" id="final-price">$<?= number_format($event_price, 2) ?></div>
                    <div class="price-label">per ticket</div>
                </div>

                <form class="booking-form" id="booking-form">
                    <div class="form-group">
                        <label for="tickets">Number of Tickets</label>
                        <select name="tickets" id="tickets" onchange="updatePrice()">
                            <option value="1">1 ticket</option>
                            <option value="2">2 tickets</option>
                            <option value="3">3 tickets</option>
                            <option value="4">4 tickets</option>
                            <option value="5">5 tickets</option>
                        </select>
                    </div>

                    <div class="voucher-section">
                        <h4>Apply Voucher Code</h4>
                        <div class="voucher-input-group">
                            <input type="text" id="voucher-code" placeholder="Enter voucher code" maxlength="20">
                            <button type="button" class="voucher-btn" onclick="applyVoucher()">Apply</button>
                        </div>
                        
                        <?php if (!empty($applicable_vouchers)): ?>
                            <div class="available-vouchers">
                                <div class="vouchers-header" onclick="toggleVouchers()">
                                    <span>Available Discount Codes</span>
                                    <span class="toggle-arrow">▼</span>
                                </div>
                                <div class="vouchers-content" id="vouchers-content">
                                    <?php foreach ($applicable_vouchers as $voucher): ?>
                                        <div class="voucher-item" onclick="selectVoucher('<?= htmlspecialchars($voucher['code']) ?>', <?= $voucher['id'] ?>)">
                                            <div class="voucher-left">
                                                <div class="voucher-badge">
                                                    <?php if ($voucher['discount_type'] === 'PERCENT'): ?>
                                                        <?= $voucher['percent_off'] ?>% OFF
                                                    <?php else: ?>
                                                        $<?= number_format($voucher['amount_off'], 2) ?> OFF
                                                    <?php endif; ?>
                                                </div>
                                                <div class="voucher-info">
                                                    <div class="voucher-title"><?= htmlspecialchars($voucher['title']) ?></div>
                                                    <div class="voucher-code"><?= htmlspecialchars($voucher['code']) ?></div>
                                                </div>
                                            </div>
                                            <div class="voucher-action">
                                                <span>Apply</span>
                                                <span class="apply-icon">→</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="price-breakdown" id="price-breakdown" style="display: none;">
                        <div class="price-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">$<?= number_format($event_price, 2) ?></span>
                        </div>
                        <div class="price-row discount" id="discount-row" style="display: none;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span id="discount-label">Discount:</span>
                                <button type="button" onclick="clearVoucher(event); return false;" style="background: none; border: none; color: var(--accent-red); font-size: 12px; cursor: pointer; text-decoration: underline;">Remove</button>
                            </div>
                            <span id="discount-amount">-$0.00</span>
                        </div>
                        <div class="price-row total">
                            <span>Total:</span>
                            <span id="total-amount">$<?= number_format($event_price, 2) ?></span>
                        </div>
                    </div>

                    <button type="button" class="book-btn" onclick="proceedToPayment()">
                        Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
        const eventPrice = <?= $event_price ?>;
        let appliedVoucher = null;
        let discountAmount = 0;

        function updatePrice() {
            const tickets = parseInt(document.getElementById('tickets').value);
            const subtotal = eventPrice * tickets;
            const finalPrice = subtotal - discountAmount;
            
            document.getElementById('final-price').textContent = '$' + finalPrice.toFixed(2);
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('total-amount').textContent = '$' + finalPrice.toFixed(2);
            
            // Update discount amount if voucher is applied
            if (appliedVoucher && discountAmount > 0) {
                // Recalculate discount based on new subtotal
                if (appliedVoucher.discount_type === 'PERCENT') {
                    discountAmount = subtotal * (appliedVoucher.percent_off / 100);
                } else {
                    discountAmount = Math.min(appliedVoucher.amount_off, subtotal);
                }
                
                const newFinalPrice = subtotal - discountAmount;
                document.getElementById('final-price').textContent = '$' + newFinalPrice.toFixed(2);
                document.getElementById('discount-amount').textContent = '-$' + discountAmount.toFixed(2);
                document.getElementById('total-amount').textContent = '$' + newFinalPrice.toFixed(2);
            }
            
            document.getElementById('price-breakdown').style.display = 'block';
        }

        function selectVoucher(code, voucherId) {
            console.log('selectVoucher called with code:', code, 'voucherId:', voucherId);
            document.getElementById('voucher-code').value = code;
            applyVoucher(code, voucherId);
        }

        function clearVoucher(event) {
            // Prevent form submission and page navigation
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            appliedVoucher = null;
            discountAmount = 0;
            
            // Reset discount display
            document.getElementById('discount-label').textContent = 'Discount:';
            document.getElementById('discount-row').style.display = 'none';
            
            // Clear voucher input
            document.getElementById('voucher-code').value = '';
            
            // Re-enable the apply button and input field
            const applyButton = document.querySelector('.voucher-btn');
            const voucherInput = document.getElementById('voucher-code');
            
            if (applyButton) {
                applyButton.disabled = false;
                applyButton.textContent = 'Apply';
                applyButton.style.background = 'var(--primary-orange)';
                console.log('Button state changed to Apply');
            } else {
                console.log('Apply button not found during clear');
            }
            
            if (voucherInput) {
                voucherInput.disabled = false;
                console.log('Voucher input re-enabled');
            } else {
                console.log('Voucher input not found during clear');
            }
            
            // Manually update prices to ensure they're correct
            const tickets = parseInt(document.getElementById('tickets').value);
            const subtotal = eventPrice * tickets;
            const finalPrice = subtotal; // No discount
            
            document.getElementById('final-price').textContent = '$' + finalPrice.toFixed(2);
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('total-amount').textContent = '$' + finalPrice.toFixed(2);
            
            // Show message
            showAlert('Voucher removed', 'success');
        }

        function toggleVouchers() {
            const content = document.getElementById('vouchers-content');
            const arrow = document.querySelector('.toggle-arrow');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                arrow.textContent = '▼';
                arrow.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'none';
                arrow.textContent = '▶';
                arrow.style.transform = 'rotate(-90deg)';
            }
        }

        function applyVoucher(specificCode = null, specificVoucherId = null) {
            const voucherCode = specificCode || document.getElementById('voucher-code').value.trim();
            
            if (!voucherCode) {
                showAlert('Please enter a voucher code', 'error');
                return;
            }

            // Check if the same voucher is already applied
            if (appliedVoucher && appliedVoucher.code === voucherCode) {
                showAlert('This voucher is already applied', 'error');
                return;
            }

            // Check if any voucher is already applied
            if (appliedVoucher && appliedVoucher.code !== voucherCode) {
                // Clear the current voucher first, then continue with new voucher
                appliedVoucher = null;
                discountAmount = 0;
                document.getElementById('discount-label').textContent = 'Discount:';
                document.getElementById('discount-row').style.display = 'none';
                showAlert('Previous voucher removed, applying new voucher...', 'info');
            }

            // Simulate voucher validation (in real implementation, this would be an AJAX call)
            const availableVouchers = <?= json_encode($applicable_vouchers) ?>;
            console.log('Looking for voucher code:', voucherCode);
            console.log('Looking for voucher ID:', specificVoucherId);
            console.log('Available vouchers:', availableVouchers);
            
            // First try to find by exact code and ID match if provided
            let voucher = null;
            if (specificVoucherId) {
                voucher = availableVouchers.find(v => v.code === voucherCode && v.id == specificVoucherId);
                console.log('Found voucher by code and ID:', voucher);
            }
            
            // If not found, fallback to code only
            if (!voucher) {
                voucher = availableVouchers.find(v => v.code === voucherCode);
                console.log('Found voucher by code only:', voucher);
            }
            
            if (!voucher) {
                alert('Invalid voucher code');
                return;
            }

            // Debug: Log which voucher is being applied
            console.log('Applying voucher:', voucher.title, 'Code:', voucher.code, 'ID:', voucher.id);
            console.log('Available vouchers:', availableVouchers);
            console.log('Found voucher:', voucher);
            
            appliedVoucher = voucher;
            const tickets = parseInt(document.getElementById('tickets').value);
            const subtotal = eventPrice * tickets;
            
            // Calculate discount
            if (voucher.discount_type === 'PERCENT') {
                discountAmount = subtotal * (voucher.percent_off / 100);
            } else {
                discountAmount = Math.min(voucher.amount_off, subtotal);
            }
            
            const finalPrice = subtotal - discountAmount;
            
            // Update display
            document.getElementById('final-price').textContent = '$' + finalPrice.toFixed(2);
            document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('discount-amount').textContent = '-$' + discountAmount.toFixed(2);
            document.getElementById('total-amount').textContent = '$' + finalPrice.toFixed(2);
            
            // Update discount label to show applied code
            document.getElementById('discount-label').textContent = `Discount (${voucher.code}):`;
            
            // Show discount row
            document.getElementById('discount-row').style.display = 'flex';
            document.getElementById('price-breakdown').style.display = 'block';
            
            // Show success message without scrolling
            showAlert(`Voucher "${voucher.code}" applied successfully!`, 'success');
            
            // Disable the apply button and input field to prevent multiple applications
            const applyButton = document.querySelector('.voucher-btn');
            const voucherInput = document.getElementById('voucher-code');
            
            if (applyButton) {
                applyButton.disabled = true;
                applyButton.textContent = 'Applied';
                applyButton.style.background = 'var(--gray-400)';
                console.log('Button state changed to Applied');
            } else {
                console.log('Apply button not found');
            }
            
            if (voucherInput) {
                voucherInput.disabled = true;
                console.log('Voucher input disabled');
            } else {
                console.log('Voucher input not found');
            }
        }

        function showAlert(message, type) {
            // Remove any existing toasts first
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Get appropriate icon for each type
            let icon = '✓';
            if (type === 'error') icon = '✕';
            else if (type === 'info') icon = 'ℹ';
            
            toast.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-message">${message}</div>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, 4000);
        }

        function proceedToPayment() {
            const tickets = parseInt(document.getElementById('tickets').value);
            const subtotal = eventPrice * tickets;
            const finalPrice = subtotal - discountAmount;
            
            // Store booking data in sessionStorage for payment page
            const bookingData = {
                eventId: <?= $event_id ?>,
                eventTitle: '<?= addslashes($event['title']) ?>',
                tickets: tickets,
                subtotal: subtotal,
                discount: discountAmount,
                total: finalPrice,
                voucherCode: appliedVoucher ? appliedVoucher.code : null,
                voucherId: appliedVoucher ? appliedVoucher.id : null
            };
            
            console.log('Storing booking data:', bookingData);
            console.log('Applied voucher:', appliedVoucher);
            
            sessionStorage.setItem('bookingData', JSON.stringify(bookingData));
            
            // Redirect to payment page
            window.location.href = 'payment.php';
        }
    </script>
</body>
</html>
