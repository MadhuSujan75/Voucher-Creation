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

        .breadcrumb {
            margin-bottom: 16px;
        }

        .breadcrumb a {
            color: var(--eventbrite-orange);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .event-header {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 48px;
            margin-bottom: 48px;
        }

        .event-info {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .event-category {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .event-title {
            font-size: 36px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .event-description {
            font-size: 16px;
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .event-details {
            display: grid;
            gap: 16px;
        }

        .event-detail {
            display: flex;
            align-items: center;
            padding: 16px;
            background: var(--gray-50);
            border-radius: 12px;
        }

        .event-detail-icon {
            width: 48px;
            height: 48px;
            background: var(--eventbrite-orange);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 20px;
            color: var(--white);
        }

        .event-detail-content h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .event-detail-content p {
            font-size: 14px;
            color: var(--gray-600);
        }

        .booking-section {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .price-display {
            text-align: center;
            margin-bottom: 32px;
        }

        .price {
            font-size: 48px;
            font-weight: 700;
            color: var(--eventbrite-orange);
            margin-bottom: 8px;
        }

        .price-label {
            font-size: 16px;
            color: var(--gray-600);
        }

        .booking-form {
            display: grid;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--eventbrite-orange);
            box-shadow: 0 0 0 3px rgba(255, 128, 0, 0.1);
        }

        .voucher-section {
            border: 2px solid var(--gray-200);
            border-radius: 16px;
            padding: 24px;
            background: var(--gray-50);
        }

        .voucher-section h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 16px;
        }

        .voucher-input-group {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .voucher-input-group input {
            flex: 1;
        }

        .voucher-btn {
            padding: 12px 20px;
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .voucher-btn:hover {
            background: var(--eventbrite-orange-dark);
        }

        .voucher-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
        }

        .available-vouchers {
            margin-top: 20px;
            border-radius: 16px;
            background: var(--white);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .vouchers-header {
            padding: 24px 32px;
            background: linear-gradient(135deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
            color: var(--white);
            font-weight: 700;
            font-size: 18px;
            text-align: center;
            cursor: pointer;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .vouchers-header:hover {
            background: linear-gradient(135deg, var(--eventbrite-orange-dark), var(--eventbrite-orange));
        }

        .toggle-arrow {
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .vouchers-content {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 8px 0;
        }

        .voucher-item {
            padding: 28px 32px;
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--white);
            position: relative;
            overflow: hidden;
            margin: 8px 16px;
            border-radius: 12px;
        }

        .voucher-item:hover {
            background: var(--gray-50);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .voucher-item:last-child {
            border-bottom: none;
        }

        .voucher-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--eventbrite-orange);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .voucher-item:hover::before {
            transform: scaleY(1);
        }

        .voucher-badge {
            display: inline-block;
            background: var(--eventbrite-orange);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .voucher-info {
            margin-bottom: 20px;
        }

        .voucher-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 12px;
            font-size: 18px;
        }

        .voucher-code {
            background: var(--gray-50);
            padding: 16px 20px;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-800);
            border: 3px dashed var(--eventbrite-orange);
            text-align: center;
            margin-bottom: 16px;
        }

        .voucher-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--eventbrite-orange);
            font-weight: 600;
            padding: 12px 16px;
            background: var(--gray-50);
            border-radius: 8px;
        }

        .apply-text {
            font-size: 16px;
        }

        .apply-icon {
            font-size: 20px;
            transition: transform 0.2s ease;
        }

        .voucher-item:hover .apply-icon {
            transform: translateX(4px);
        }

        .price-breakdown {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .price-row.total {
            border-top: 1px solid var(--gray-300);
            padding-top: 8px;
            font-weight: 600;
            font-size: 18px;
        }

        .discount {
            color: var(--success-green);
        }

        .book-btn {
            width: 100%;
            padding: 16px 24px;
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .book-btn:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        .book-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .event-header {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .event-title {
                font-size: 28px;
            }

            .price {
                font-size: 36px;
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
                                    <span>🎟️ Available Discount Codes</span>
                                    <span class="toggle-arrow">▼</span>
                                </div>
                                <div class="vouchers-content" id="vouchers-content">
                                    <?php foreach ($applicable_vouchers as $voucher): ?>
                                        <div class="voucher-item" onclick="selectVoucher('<?= htmlspecialchars($voucher['code']) ?>', <?= $voucher['id'] ?>)">
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
                                            <div class="voucher-action">
                                                <span class="apply-text">Click to Apply</span>
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
                            <span>Discount:</span>
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
            
            document.getElementById('price-breakdown').style.display = 'block';
        }

        function selectVoucher(code, voucherId) {
            console.log('selectVoucher called with code:', code, 'voucherId:', voucherId);
            document.getElementById('voucher-code').value = code;
            applyVoucher(code, voucherId);
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
                alert('Please enter a voucher code');
                return;
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
            
            // Show discount row
            document.getElementById('discount-row').style.display = 'flex';
            document.getElementById('price-breakdown').style.display = 'block';
            
            // Show success message
            showAlert('Voucher applied successfully!', 'success');
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            
            const form = document.getElementById('booking-form');
            form.insertBefore(alertDiv, form.firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
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
