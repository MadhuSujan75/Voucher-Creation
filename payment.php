<?php
require 'db.php';

// Get booking data from sessionStorage (passed from event detail page)
$booking_data = null;

// Check if booking data is passed via POST (from form submission)
if (isset($_POST['booking_data'])) {
    $booking_data = json_decode($_POST['booking_data'], true);
} else {
    // For demo purposes, create sample booking data
    $booking_data = [
        'eventId' => 1,
        'eventTitle' => 'Sample Event',
        'tickets' => 1,
        'subtotal' => 50.00,
        'discount' => 0,
        'total' => 50.00,
        'voucherCode' => null,
        'voucherId' => null
    ];
}

// For demo purposes, let's create a simple user session
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Demo user ID
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Create demo user if not exists
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute(['demo_user', 'demo@example.com', password_hash('demo123', PASSWORD_DEFAULT)]);
    $user_id = $pdo->lastInsertId();
    $_SESSION['user_id'] = $user_id;

    $user = [
        'id' => $user_id,
        'username' => 'demo_user',
        'email' => 'demo@example.com'
    ];
}

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$booking_data['eventId']]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Payment - <?= htmlspecialchars($booking_data['eventTitle']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        /* Cache busting - v2.0 */
        :root {
            --primary-orange: #fc8019;
            --primary-orange-dark: #e6730d;
            --gray-900: #1a1a1a;
            --gray-800: #2a2a2a;
            --gray-700: #3a3a3a;
            --gray-600: #6b7280;
            --gray-500: #9ca3af;
            --gray-400: #d1d5db;
            --gray-300: #e5e7eb;
            --gray-200: #f3f4f6;
            --gray-100: #f9fafb;
            --white: #ffffff;
            --success-green: #10b981;
            --error-red: #ef4444;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 42px;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 12px;
            background: linear-gradient(135deg, var(--primary-orange), #ff6b35);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header p {
            color: var(--gray-600);
            font-size: 16px;
            font-weight: 500;
        }

        .payment-container {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 40px;
            align-items: start;
        }

        .payment-form {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
        }

        .form-section {
            margin-bottom: 40px;
        }

        .form-section h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--primary-orange);
            position: relative;
        }

        .form-section h3::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-orange), #ff6b35);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius-md);
            font-size: 16px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(252, 128, 25, 0.1);
            transform: translateY(-1px);
        }

        .form-group input::placeholder {
            color: var(--gray-400);
        }

        .order-summary {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            position: sticky;
            top: 20px;
        }

        .event-info {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--gray-100);
        }

        .event-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary-orange), #ff6b35);
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--white);
            margin-right: 20px;
            box-shadow: var(--shadow-md);
        }

        .event-details h4 {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 6px;
        }

        .event-details p {
            font-size: 14px;
            color: var(--gray-600);
            font-weight: 500;
        }

        .price-breakdown {
            margin-bottom: 32px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
            font-size: 15px;
        }

        .price-row.total {
            border-top: 2px solid var(--gray-200);
            padding-top: 16px;
            margin-top: 16px;
            font-weight: 700;
            font-size: 20px;
            color: var(--gray-900);
            background: linear-gradient(135deg, var(--gray-50), var(--white));
            padding: 16px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--gray-200);
        }

        .discount {
            color: var(--success-green);
            font-weight: 600;
        }

        .voucher-info {
            background: linear-gradient(135deg, var(--gray-50), var(--white));
            border-radius: var(--border-radius-md);
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
        }

        .voucher-info .label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .voucher-info .code {
            font-family: 'Courier New', monospace;
            color: var(--primary-orange);
            font-weight: 600;
            font-size: 14px;
            margin-top: 4px;
        }

        .payment-btn {
            width: 100%;
            padding: 24px 32px;
            background: linear-gradient(135deg, var(--primary-orange), #ff6b35);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius-md);
            font-size: 20px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 32px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 8px 25px rgba(252, 128, 25, 0.3);
            position: relative;
            overflow: hidden;
        }

        .payment-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .payment-btn:hover::before {
            left: 100%;
        }

        .payment-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(252, 128, 25, 0.4);
        }

        .payment-btn:active {
            transform: translateY(0);
        }

        .payment-btn:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 24px;
        }

        .spinner {
            border: 4px solid var(--gray-200);
            border-top: 4px solid var(--primary-orange);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading p {
            color: var(--gray-600);
            font-weight: 500;
        }

        @media (max-width: 1024px) {
            .payment-container {
                grid-template-columns: 1fr;
                gap: 32px;
            }

            .order-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .payment-form {
                padding: 24px;
            }

            .order-summary {
                padding: 24px;
            }

            .header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Complete Your Purchase</h1>
            <p>Secure payment powered by Stripe</p>
        </div>

        <div class="payment-container">
            <div class="payment-form">
                <form id="payment-form">
                    <div class="form-section">
                        <h3>Contact Information</h3>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                    </div>

                    <div class="form-section" id="payment-section">
                        <h3>Payment Information</h3>
                        <div class="form-group" id="card-group">
                            <label for="card-element">Card Details</label>
                            <div id="card-element" style="padding: 12px; border: 2px solid var(--gray-300); border-radius: 8px;">
                                <!-- Stripe Elements will create form elements here -->
                            </div>
                            <div id="card-errors" role="alert"></div>
                        </div>
                        <div class="form-group" id="free-order-notice" style="display: none;">
                            <div class="alert alert-success">
                                <strong>🎉 Free Order!</strong> Your voucher covers the full amount. No payment required.
                            </div>
                        </div>
                    </div>

                    <div class="loading" id="loading">
                        <div class="spinner"></div>
                        <p>Processing payment...</p>
                    </div>

                    <button type="submit" class="payment-btn" id="submit-button">
                        Pay $<?= number_format($booking_data['total'], 2) ?>
                    </button>
                </form>
            </div>

            <div class="order-summary">
                <div class="event-info">
                    <div class="event-icon">🎫</div>
                    <div class="event-details">
                        <h4><?= htmlspecialchars($booking_data['eventTitle']) ?></h4>
                        <p><?= $booking_data['tickets'] ?> <?= $booking_data['tickets'] == 1 ? 'ticket' : 'tickets' ?></p>
                    </div>
                </div>

                <?php if ($booking_data['voucherCode']): ?>
                    <div class="voucher-info">
                        <div class="label">🎟️ Voucher Applied:</div>
                        <div class="code"><?= htmlspecialchars($booking_data['voucherCode']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="price-breakdown">
                    <div class="price-row">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($booking_data['subtotal'], 2) ?></span>
                    </div>
                    <?php if ($booking_data['discount'] > 0): ?>
                        <div class="price-row discount">
                            <span>Discount (<?= htmlspecialchars($booking_data['voucherCode']) ?>):</span>
                            <span>-$<?= number_format($booking_data['discount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="price-row total">
                        <span>Total:</span>
                        <span>$<?= number_format($booking_data['total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Stripe - using test mode
        const stripe = Stripe('pk_test_51RzuPbB4RsBfqXlvibWioyIea8FWg4csZrJvazJsW8f7USf1TQrc3jUYUD5DWiFb7ul9pOZnEEUMNCUqJQSLBsIJ00LWWDwh54');

        const elements = stripe.elements();
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
            },
        });

        cardElement.mount('#card-element');

        // Update order summary with data from sessionStorage
        updateOrderSummary();

        // Check if this is a zero-amount order and update UI accordingly
        checkAndUpdateUIForZeroAmount();

        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const loading = document.getElementById('loading');

        // Handle real-time validation errors from the card Element
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        // Handle form submission
        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            // Get booking data to check if amount is zero
            let bookingData = null;
            const sessionData = sessionStorage.getItem('bookingData');
            if (sessionData) {
                bookingData = JSON.parse(sessionData);
            } else {
                bookingData = <?= json_encode($booking_data) ?>;
            }

            // Show loading state
            submitButton.disabled = true;
            loading.style.display = 'block';

            // Update loading text based on order type
            const loadingText = loading.querySelector('p');
            if (bookingData && bookingData.total <= 0) {
                loadingText.textContent = 'Completing your free order...';
            } else {
                loadingText.textContent = 'Processing payment...';
            }

            // Check if total is zero (free order)
            console.log('Checking if order is free. Total:', bookingData?.total);
            if (bookingData && bookingData.total <= 0) {
                console.log('Processing free order, skipping Stripe');
                // For zero-amount orders, skip Stripe and process directly
                await processPayment(null);
            } else {
                // For non-zero amounts, create Stripe token
                const {
                    token,
                    error
                } = await stripe.createToken(cardElement);

                if (error) {
                    // Show error to customer
                    const errorElement = document.getElementById('card-errors');
                    errorElement.textContent = error.message;
                    submitButton.disabled = false;
                    loading.style.display = 'none';
                } else {
                    // Process payment
                    await processPayment(token);
                }
            }
        });

        function updateOrderSummary() {
            const sessionData = sessionStorage.getItem('bookingData');
            if (sessionData) {
                const bookingData = JSON.parse(sessionData);

                // Update event title
                const eventTitle = document.querySelector('.event-details h4');
                if (eventTitle) {
                    eventTitle.textContent = bookingData.eventTitle;
                }

                // Update ticket count
                const ticketCount = document.querySelector('.event-details p');
                if (ticketCount) {
                    ticketCount.textContent = bookingData.tickets + ' ticket' + (bookingData.tickets > 1 ? 's' : '');
                }

                // Update price breakdown
                const priceRows = document.querySelectorAll('.price-row');
                if (priceRows.length >= 2) {
                    // Update subtotal
                    const subtotalElement = priceRows[0].querySelector('span:last-child');
                    if (subtotalElement) {
                        subtotalElement.textContent = '$' + bookingData.subtotal.toFixed(2);
                    }

                    // Update total (last row)
                    const totalElement = priceRows[priceRows.length - 1].querySelector('span:last-child');
                    if (totalElement) {
                        totalElement.textContent = '$' + bookingData.total.toFixed(2);
                    }
                }

                // Update payment button
                const paymentBtn = document.querySelector('.payment-btn');
                if (paymentBtn) {
                    if (bookingData.total <= 0) {
                        paymentBtn.textContent = 'Complete Free Order';
                    } else {
                        paymentBtn.textContent = 'Pay $' + bookingData.total.toFixed(2);
                    }
                }

                // Check and update UI for zero amount
                checkAndUpdateUIForZeroAmount();

                // Show discount if applicable
                if (bookingData.discount > 0) {
                    const discountRow = document.querySelector('.price-row.discount');
                    if (discountRow) {
                        discountRow.style.display = 'flex';
                        const discountLabel = discountRow.querySelector('span:first-child');
                        const discountAmount = discountRow.querySelector('span:last-child');
                        if (discountLabel) {
                            discountLabel.textContent = 'Discount (' + bookingData.voucherCode + '):';
                        }
                        if (discountAmount) {
                            discountAmount.textContent = '-$' + bookingData.discount.toFixed(2);
                        }
                    }

                    // Show voucher info if available
                    if (bookingData.voucherCode) {
                        const voucherInfo = document.querySelector('.voucher-info');
                        if (voucherInfo) {
                            voucherInfo.style.display = 'block';
                            const voucherCode = voucherInfo.querySelector('.code');
                            if (voucherCode) {
                                voucherCode.textContent = bookingData.voucherCode;
                            }
                        }
                    }
                } else {
                    // Hide discount row if no discount
                    const discountRow = document.querySelector('.price-row.discount');
                    if (discountRow) {
                        discountRow.style.display = 'none';
                    }

                    // Hide voucher info if no voucher
                    const voucherInfo = document.querySelector('.voucher-info');
                    if (voucherInfo) {
                        voucherInfo.style.display = 'none';
                    }
                }
            }
        }

        async function processPayment(token) {
            try {
                // Get booking data from sessionStorage if available
                let bookingData = null;
                const sessionData = sessionStorage.getItem('bookingData');
                if (sessionData) {
                    bookingData = JSON.parse(sessionData);
                } else {
                    // Fallback to PHP data if sessionStorage is not available
                    bookingData = <?= json_encode($booking_data) ?>;
                }

                console.log('Processing payment with data:', {
                    token: token ? token.id : null,
                    bookingData: bookingData,
                    userId: <?= $user_id ?>
                });

                const response = await fetch('process_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token ? token.id : null,
                        booking_data: bookingData,
                        user_id: <?= $user_id ?>
                    })
                });

                console.log('Response status:', response.status);
                const result = await response.json();
                console.log('Payment result:', result);

                if (result.success) {
                    // Payment successful
                    console.log('Payment successful, redirecting to order:', result.order_id);
                    window.location.href = 'payment_success.php?order_id=' + result.order_id;
                } else {
                    // Payment failed
                    console.error('Payment failed:', result.message);
                    showError(result.message || 'Payment failed. Please try again.');
                }
            } catch (error) {
                console.error('Payment error:', error);
                showError('An error occurred. Please try again.');
            }

            submitButton.disabled = false;
            loading.style.display = 'none';
        }

        function checkAndUpdateUIForZeroAmount() {
            // Get booking data to check if amount is zero
            let bookingData = null;
            const sessionData = sessionStorage.getItem('bookingData');
            if (sessionData) {
                bookingData = JSON.parse(sessionData);
            } else {
                bookingData = <?= json_encode($booking_data) ?>;
            }

            console.log('checkAndUpdateUIForZeroAmount called with bookingData:', bookingData);
            console.log('Total amount:', bookingData?.total);

            if (bookingData && bookingData.total <= 0) {
                console.log('Updating UI for zero amount order');
                // Hide card input and show free order notice
                document.getElementById('card-group').style.display = 'none';
                document.getElementById('free-order-notice').style.display = 'block';

                // Update payment button text
                const paymentBtn = document.querySelector('.payment-btn');
                if (paymentBtn) {
                    paymentBtn.textContent = 'Complete Free Order';
                }
            } else {
                console.log('Order has non-zero amount, keeping normal UI');
            }
        }

        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.textContent = message;

            form.insertBefore(errorDiv, form.firstChild);

            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
    </script>
</body>

</html>