<?php
require 'db.php';

// Fetch categories with event counts
$categories = $pdo->query("
    SELECT c.category_id, c.name, COUNT(e.id) as event_count
    FROM categories c
    LEFT JOIN events e ON c.category_id = e.category_id
    GROUP BY c.category_id, c.name
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Categories</title>
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

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
        }

        .category-card {
            background: var(--white);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: var(--eventbrite-orange);
        }

        .category-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            font-size: 28px;
            color: var(--white);
        }

        .category-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .category-count {
            font-size: 16px;
            color: var(--gray-600);
            margin-bottom: 16px;
        }

        .category-description {
            font-size: 14px;
            color: var(--gray-500);
            line-height: 1.6;
        }

        .view-events-btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background: var(--eventbrite-orange);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s ease;
            margin-top: 16px;
        }

        .view-events-btn:hover {
            background: var(--eventbrite-orange-dark);
            transform: translateY(-1px);
        }

        .view-events-btn::after {
            content: '→';
            margin-left: 8px;
            transition: transform 0.2s ease;
        }

        .view-events-btn:hover::after {
            transform: translateX(4px);
        }

        .admin-link {
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

        .admin-link:hover {
            background: var(--gray-900);
            transform: translateY(-2px);
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

            .categories-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .category-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Event Categories</h1>
            <p>Discover amazing events across different categories. Find your perfect experience and book with ease.</p>
            <div style="margin-top: 24px;">
                <a href="available_vouchers.php" style="display: inline-block; padding: 12px 24px; background: var(--eventbrite-orange); color: var(--white); text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.2s ease;">
                    🎟️ View Available Discount Codes
                </a>
            </div>
        </div>

        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <div class="category-card" onclick="window.location.href='events.php?category=<?= $category['category_id'] ?>'">
                    <div class="category-icon">
                        <?php
                        // Simple icon mapping based on category name
                        $icons = [
                            'Movies' => '🎬',
                            'Music' => '🎵',
                            'Sports' => '⚽',
                            'Standup Comedy' => '🎭',
                            'Technology' => '💻',
                            'Food & Drink' => '🍕',
                            'Art & Culture' => '🎨',
                            'Business' => '💼',
                            'Education' => '📚',
                            'Health & Wellness' => '💪'
                        ];
                        echo $icons[$category['name']] ?? '🎪';
                        ?>
                    </div>
                    <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                    <p class="category-count"><?= $category['event_count'] ?> <?= $category['event_count'] == 1 ? 'event' : 'events' ?> available</p>
                    <p class="category-description">
                        Explore exciting <?= strtolower($category['name']) ?> events in your area. 
                        From live performances to interactive experiences, find something that matches your interests.
                    </p>
                    <a href="events.php?category=<?= $category['category_id'] ?>" class="view-events-btn">
                        View Events
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="vouchers_list.php" class="admin-link">Admin Panel</a>
</body>
</html>
