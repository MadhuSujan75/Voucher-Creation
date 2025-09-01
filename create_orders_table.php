<?php
require 'db.php';

try {
    // Create orders table
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id bigint AUTO_INCREMENT PRIMARY KEY,
        user_id int NOT NULL,
        event_id bigint NOT NULL,
        tickets int NOT NULL,
        subtotal decimal(12,2) NOT NULL,
        discount decimal(12,2) DEFAULT 0,
        total decimal(12,2) NOT NULL,
        status enum('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
        stripe_charge_id varchar(255),
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (event_id) REFERENCES events(id)
    )";
    
    $pdo->exec($sql);
    echo "Orders table created successfully!\n";
    
} catch (PDOException $e) {
    echo "Error creating orders table: " . $e->getMessage() . "\n";
}
?>
