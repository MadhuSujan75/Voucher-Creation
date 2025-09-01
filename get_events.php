<?php
require 'db.php';
$category_id = $_GET['category_id'] ?? 0;
$stmt = $pdo->prepare("SELECT id, title as name FROM events WHERE category_id=? ORDER BY title");
$stmt->execute([$category_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($events);
