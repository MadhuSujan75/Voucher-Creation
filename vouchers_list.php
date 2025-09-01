<?php
require 'db.php';

// Fetch vouchers
$stmt = $pdo->query("SELECT * FROM vouchers ORDER BY created_at DESC");
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Vouchers</h1>
<a href="voucher_create.php">Create New Voucher</a>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Discount Type</th>
            <th>Status</th>
            <th>Start - End</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($vouchers as $v): ?>
            <tr>
                <td><?= $v['id'] ?></td>
                <td><?= htmlspecialchars($v['title']) ?></td>
                <td><?= $v['discount_type'] ?></td>
                <td><?= $v['status'] ?></td>
                <td><?= $v['start_at'] ?> - <?= $v['end_at'] ?></td>
                <td>
                    <a href="voucher_view.php?id=<?= $v['id'] ?>">View</a> |
                    <a href="voucher_edit.php?id=<?= $v['id'] ?>">Edit</a> |
                    <a href="voucher_batch_create.php?voucher_id=<?= $v['id'] ?>">Generate Batch</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>