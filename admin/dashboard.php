<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();

// Fallback overdue check (in case MySQL EVENT is not enabled)
$db->exec("
  UPDATE rentals SET status = 'OVERDUE'
  WHERE status = 'ACTIVE' AND end_date < CURDATE()
");
$db->exec("
  INSERT IGNORE INTO sos_alerts (rental_id, user_id, trigger_type, message, status)
  SELECT r.id, r.user_id, 'OVERDUE',
         CONCAT('Rental overdue since ', r.end_date), 'OPEN'
  FROM rentals r
  WHERE r.status = 'OVERDUE'
    AND NOT EXISTS (
      SELECT 1 FROM sos_alerts s
      WHERE s.rental_id = r.id AND s.trigger_type = 'OVERDUE'
    )
");

// Fetch stats from view
$stats = $db->query("SELECT * FROM v_admin_dashboard")->fetch();

// Fetch 5 most recent pending rentals
$stmt = $db->query("
    SELECT r.*, u.name as user_name, p.name as product_name
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.status = 'PENDING'
    ORDER BY r.created_at DESC
    LIMIT 5
");
$pending_rentals = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<h2 class="mb-20">Admin Dashboard</h2>

<div class="grid mb-40">
    <div class="grid-col col-4 flex-col">
        <div class="card card-accent h-100" style="border-left-color: var(--color-warning);">
            <p class="text-muted mb-5">Pending Approvals</p>
            <h1 style="font-size: 32px;"><?= $stats['pending_approvals'] ?></h1>
            <a href="<?= BASE_URL ?>/admin/rentals.php?status=PENDING" style="font-size: 13px;">View All &rarr;</a>
        </div>
    </div>
    <div class="grid-col col-4 flex-col">
        <div class="card card-accent h-100" style="border-left-color: var(--color-info);">
            <p class="text-muted mb-5">Active Rentals</p>
            <h1 style="font-size: 32px;"><?= $stats['active_rentals'] ?></h1>
            <a href="<?= BASE_URL ?>/admin/rentals.php?status=ACTIVE" style="font-size: 13px;">View All &rarr;</a>
        </div>
    </div>
    <div class="grid-col col-4 flex-col">
        <div class="card card-accent h-100" style="border-left-color: var(--color-danger);">
            <p class="text-muted mb-5">Open SOS Alerts</p>
            <h1 style="font-size: 32px;"><?= $stats['open_sos'] ?></h1>
            <a href="<?= BASE_URL ?>/admin/sos.php" style="font-size: 13px;">Take Action &rarr;</a>
        </div>
    </div>
    <div class="grid-col col-4 mt-20 flex-col">
        <div class="card card-accent h-100" style="border-left-color: var(--color-warning);">
            <p class="text-muted mb-5">In Maintenance</p>
            <h1 style="font-size: 32px;"><?= $stats['in_maintenance'] ?></h1>
            <a href="<?= BASE_URL ?>/admin/maintenance.php" style="font-size: 13px;">Manage Gear &rarr;</a>
        </div>
    </div>
    <div class="grid-col col-4 mt-20 flex-col">
        <div class="card card-accent h-100" style="border-left-color: var(--color-info);">
            <p class="text-muted mb-5">Pending Inspection</p>
            <h1 style="font-size: 32px;"><?= $stats['pending_inspection'] ?></h1>
            <a href="<?= BASE_URL ?>/admin/rentals.php?status=RETURNED" style="font-size: 13px;">Inspect Now &rarr;</a>
        </div>
    </div>
    <div class="grid-col col-4 mt-20 flex-col">
        <div class="card card-accent h-100" style="border-left-color: var(--color-success);">
            <p class="text-muted mb-5">Total Revenue</p>
            <h1 style="font-size: 32px;">৳<?= number_format($stats['total_revenue'], 0) ?></h1>
            <a href="<?= BASE_URL ?>/admin/reports.php" style="font-size: 13px;">View Reports &rarr;</a>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="mb-20">Recent Pending Approvals</h3>
    <?php if (empty($pending_rentals)): ?>
        <p class="text-muted">No pending approvals at the moment.</p>
    <?php else: ?>
        <div class="table-container" style="border: none; margin-bottom: 0;">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Product</th>
                        <th>Dates</th>
                        <th>Fee</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_rentals as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['user_name']) ?></td>
                            <td><?= htmlspecialchars($r['product_name']) ?></td>
                            <td style="font-size: 12px;">
                                <?= date('M d', strtotime($r['start_date'])) ?> - <?= date('M d', strtotime($r['end_date'])) ?>
                            </td>
                            <td>৳<?= number_format($r['total_fee'], 0) ?></td>
                            <td>
                                <div class="flex align-center gap-10">
                                    <form method="POST" action="<?= BASE_URL ?>/admin/actions/approve_rental.php" style="display: inline;">
                                        <input type="hidden" name="rental_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                    </form>
                                    <a href="<?= BASE_URL ?>/admin/rental_detail.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Review</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center mt-20">
            <a href="<?= BASE_URL ?>/admin/rentals.php?status=PENDING" class="btn btn-outline btn-sm">View All Pending</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
