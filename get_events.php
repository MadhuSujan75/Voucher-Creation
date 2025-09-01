<?php
require 'db.php';

$category_id = $_GET['category_id'] ?? null;
$page = $_GET['page'] ?? 1;
$per_page = 20; // Load 20 events per page
$offset = ($page - 1) * $per_page;

if (!$category_id) {
    echo json_encode(['error' => 'Category ID required']);
    exit;
}

try {
    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE category_id = ?");
    $countStmt->execute([$category_id]);
    $total_events = $countStmt->fetchColumn();
    
    // Get events with pagination
    $stmt = $pdo->prepare("
        SELECT id, title as name, event_date, start_time, end_time, venue 
        FROM events 
        WHERE category_id = ? 
        ORDER BY event_date ASC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$category_id, $per_page, $offset]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $total_pages = ceil($total_events / $per_page);
    $has_more = $page < $total_pages;
    
    header('Content-Type: application/json');
    echo json_encode([
        'events' => $events,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_events' => $total_events,
            'per_page' => $per_page,
            'has_more' => $has_more
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
