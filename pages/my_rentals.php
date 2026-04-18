<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$db = get_db();
$stmt = $db->prepare("
    SELECT r.*, p.name as product_name, c.name as category_name, p.image_path
    FROM rentals r
    JOIN products p ON r.product_id = p.id
    JOIN subcategories s ON p.subcategory_id = s.id
    JOIN categories c ON s.category_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$rentals = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2>My Rentals</h2>
    <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-primary">Book New Gear</a>
</div>

<?php if (empty($rentals)): ?>
    <div class="card text-center p-20">
        <p class="text-muted mb-20">You haven't rented any gear yet.</p>
        <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-primary">Browse Catalog</a>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Dates</th>
                    <th>Fee</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rentals as $r): ?>
                    <tr>
                        <td>
                            <div class="flex align-center gap-10">
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($r['image_path']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($r['product_name']) ?></div>
                                    <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($r['category_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 13px;">
                                <?= date('M d, Y', strtotime($r['start_date'])) ?> - <?= date('M d, Y', strtotime($r['end_date'])) ?>
                                <div class="text-muted" style="font-size: 11px;"><?= $r['days'] ?> days</div>
                            </div>
                        </td>
                        <td style="font-weight: 600;">৳<?= number_format($r['total_fee'], 0) ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($r['status']) ?>">
                                <?= str_replace('_', ' ', $r['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex gap-10">
                                <a href="<?= BASE_URL ?>/pages/rental.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Details</a>
                                
                                <?php if ($r['status'] === 'APPROVED'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/pages/actions/confirm_pickup.php">
                                        <input type="hidden" name="rental_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Confirm Pickup</button>
                                    </form>
                                <?php elseif (in_array($r['status'], ['ACTIVE', 'OVERDUE'])): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/pages/actions/confirm_return.php">
                                        <input type="hidden" name="rental_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm" style="background: var(--color-success); color: #fff;">Confirm Return</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
