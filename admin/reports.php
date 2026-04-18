<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();

// Report 1: Product Revenue
$product_report = $db->query("SELECT * FROM v_product_status_report ORDER BY total_revenue DESC")->fetchAll();

// Report 2: User Activity
$user_report = $db->query("SELECT * FROM v_user_rental_summary ORDER BY total_rentals DESC")->fetchAll();

// Report 3: Revenue by Category
$cat_report = $db->query("
    SELECT cat.name, COUNT(r.id) as rentals, SUM(r.total_fee) as revenue 
    FROM rentals r 
    JOIN products p ON r.product_id=p.id 
    JOIN subcategories s ON p.subcategory_id=s.id 
    JOIN categories cat ON s.category_id=cat.id 
    WHERE r.status='COMPLETED' 
    GROUP BY cat.id
")->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<h2 class="mb-20">Business Reports</h2>

<div class="grid mb-40">
    <div class="grid-col col-12">
        <div class="card">
            <h3 class="mb-20">Revenue by Category</h3>
            <div class="table-container" style="border: none; margin-bottom: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Completed Rentals</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cat_report as $c): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= htmlspecialchars($c['name']) ?></td>
                                <td><?= $c['rentals'] ?></td>
                                <td style="font-weight: 600; color: var(--color-primary-dk);">৳<?= number_format($c['revenue'], 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cat_report)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No completed rentals yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-40">
    <h3 class="mb-20">Product Performance</h3>
    <div class="table-container" style="border: none; margin-bottom: 0;">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Rental Count</th>
                    <th>Revenue</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($product_report as $p): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= htmlspecialchars($p['product_name']) ?></td>
                        <td><?= htmlspecialchars($p['category']) ?></td>
                        <td><?= $p['total_rentals'] ?></td>
                        <td style="font-weight: 600;">৳<?= number_format($p['total_revenue'], 0) ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($p['status']) ?>">
                                <?= str_replace('_', ' ', $p['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 class="mb-20">User Activity Summary</h3>
    <div class="table-container" style="border: none; margin-bottom: 0;">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Total Rentals</th>
                    <th>Active</th>
                    <th>Completed</th>
                    <th>Total Spent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_report as $u): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($u['name']) ?></div>
                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($u['email']) ?></div>
                        </td>
                        <td><?= $u['total_rentals'] ?></td>
                        <td><?= $u['active'] ?></td>
                        <td><?= $u['completed'] ?></td>
                        <td style="font-weight: 600; color: var(--color-primary-dk);">৳<?= number_format($u['total_spent'], 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
