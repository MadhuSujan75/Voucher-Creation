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
    <title>Payment - <?= htmlspecialchars($booking_data['eventTitle']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Benton+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .header p {
            color: var(--gray-600);
            font-size: 16px;
        }

        .payment-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 32px;
        }

        .payment-form {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .form-section {
            margin-bottom: 32px;
        }

        .form-section h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--eventbrite-orange);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--eventbrite-orange);
            box-shadow: 0 0 0 3px rgba(255, 128, 0, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .order-summary {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .event-info {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .event-icon {
            width: 64px;
            height: 64px;
            background: var(--eventbrite-orange);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--white);
            margin-right: 16px;
        }

        .event-details h4 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .event-details p {
            font-size: 14px;
            color: var(--gray-600);
        }

        .price-breakdown {
            margin-bottom: 24px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .price-row.total {
            border-top: 1px solid var(--gray-300);
            padding-top: 12px;
            font-weight: 600;
            font-size: 18px;
            color: var(--gray-900);
        }

        .discount {
            color: var(--success-green);
        }

        .voucher-info {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .voucher-info .label {
            font-weight: 600;
            color: var(--gray-700);
        }

        .voucher-info .code {
            font-family: monospace;
            color: var(--eventbrite-orange);
        }

        .payment-btn {
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
            margin-top: 24px;
        }

        .payment-btn:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        .payment-btn:disabled {
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

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 3px solid var(--gray-300);
            border-top: 3px solid var(--eventbrite-orange);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .payment-container {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .form-row {
                grid-template-columns: 1fr;
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

                    <div class="form-section">
                        <h3>Payment Information</h3>
                        <div class="form-group">
                            <label for="card-element">Card Details</label>
                            <div id="card-element" style="padding: 12px; border: 2px solid var(--gray-300); border-radius: 8px;">
                                <!-- Stripe Elements will create form elements here -->
                            </div>
                            <div id="card-errors" role="alert"></div>
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
                        <div class="label">Voucher Applied:</div>
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
                            <span>Discount:</span>
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
        // Initialize Stripe
        const stripe = Stripe('pk_test_51RzuPbB4RsBfqXlvibWioyIea8FWg4csZrJvazJsW8f7USf1TQrc3jUYUD5DWiFb7ul9pOZnEEUMNCUqJQSLBsIJ00LWWDwh54'); // Replace with your actual publishable key
        
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
            
            // Show loading state
            submitButton.disabled = true;
            loading.style.display = 'block';
            
            const {token, error} = await stripe.createToken(cardElement);
            
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
                    paymentBtn.textContent = 'Pay $' + bookingData.total.toFixed(2);
                }
                
                // Show discount if applicable
                if (bookingData.discount > 0) {
                    const discountRow = document.querySelector('.price-row.discount');
                    if (discountRow) {
                        discountRow.style.display = 'flex';
                        const discountAmount = discountRow.querySelector('span:last-child');
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
                
                const response = await fetch('process_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: token.id,
                        booking_data: bookingData,
                        user_id: <?= $user_id ?>
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    // Payment successful
                    window.location.href = 'payment_success.php?order_id=' + result.order_id;
                } else {
                    // Payment failed
                    showError(result.message || 'Payment failed. Please try again.');
                }
            } catch (error) {
                showError('An error occurred. Please try again.');
            }
            
            submitButton.disabled = false;
            loading.style.display = 'none';
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
