<?php
require 'db.php';

$selected_category = $_GET['category'] ?? null;
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($selected_category) {
    $where_conditions[] = "e.category_id = ?";
    $params[] = $selected_category;
}

if ($search) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch events with category information
$events_query = "
    SELECT e.*, c.name as category_name
    FROM events e
    JOIN categories c ON e.category_id = c.category_id
    $where_clause
    ORDER BY e.event_date ASC, e.start_time ASC
";

$stmt = $pdo->prepare($events_query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get selected category name
$selected_category_name = '';
if ($selected_category) {
    foreach ($categories as $cat) {
        if ($cat['category_id'] == $selected_category) {
            $selected_category_name = $cat['name'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $selected_category_name ? "Events in $selected_category_name" : "All Events" ?></title>
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
            margin-bottom: 32px;
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

        .header h1 {
            font-size: 36px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .header p {
            color: var(--gray-600);
            font-size: 18px;
        }

        .filters {
            background: var(--white);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
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

        .filter-btn {
            padding: 12px 24px;
            background: var(--eventbrite-orange);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-btn:hover {
            background: var(--eventbrite-orange-dark);
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .event-card {
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .event-image {
            height: 200px;
            background: linear-gradient(135deg, var(--eventbrite-orange), var(--eventbrite-orange-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: var(--white);
        }

        .event-content {
            padding: 24px;
        }

        .event-category {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .event-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .event-description {
            color: var(--gray-600);
            font-size: 14px;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .event-details {
            display: grid;
            gap: 8px;
            margin-bottom: 20px;
        }

        .event-detail {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: var(--gray-600);
        }

        .event-detail::before {
            content: '';
            width: 4px;
            height: 4px;
            background: var(--eventbrite-orange);
            border-radius: 50%;
            margin-right: 8px;
        }

        .event-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--eventbrite-orange);
            color: var(--white);
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--eventbrite-orange-dark);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-secondary:hover {
            border-color: var(--gray-400);
        }

        .no-events {
            text-align: center;
            padding: 64px 24px;
            color: var(--gray-500);
        }

        .no-events h3 {
            font-size: 24px;
            margin-bottom: 8px;
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

            .filters-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .event-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="categories.php">Categories</a>
                <?php if ($selected_category_name): ?>
                    <span> → <?= htmlspecialchars($selected_category_name) ?></span>
                <?php endif; ?>
            </div>
            <h1><?= $selected_category_name ? "Events in $selected_category_name" : "All Events" ?></h1>
            <p><?= count($events) ?> <?= count($events) == 1 ? 'event' : 'events' ?> found</p>
        </div>

        <div class="filters">
            <form method="GET" class="filters-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['category_id'] ?>" <?= $selected_category == $category['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search Events</label>
                    <input type="text" name="search" id="search" placeholder="Search by title or description..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="filter-btn">Filter</button>
            </form>
        </div>

        <?php if (empty($events)): ?>
            <div class="no-events">
                <h3>No events found</h3>
                <p>Try adjusting your search criteria or browse all categories.</p>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                    <div class="event-card" onclick="window.location.href='event_detail.php?id=<?= $event['id'] ?>'">
                        <div class="event-image">
                            <?php
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
                            echo $icons[$event['category_name']] ?? '🎪';
                            ?>
                        </div>
                        <div class="event-content">
                            <div class="event-category"><?= htmlspecialchars($event['category_name']) ?></div>
                            <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                            <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>
                            
                            <div class="event-details">
                                <div class="event-detail">
                                    <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                </div>
                                <div class="event-detail">
                                    <?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?>
                                </div>
                                <?php if ($event['venue']): ?>
                                    <div class="event-detail">
                                        📍 <?= htmlspecialchars($event['venue']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="event-actions">
                                <a href="event_detail.php?id=<?= $event['id'] ?>" class="btn btn-primary">View Details</a>
                                <a href="event_detail.php?id=<?= $event['id'] ?>" class="btn btn-secondary">Book Now</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <a href="vouchers_list.php" class="admin-link">Admin Panel</a>
</body>
</html>
