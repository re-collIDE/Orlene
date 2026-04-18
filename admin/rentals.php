<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();
$status_filter = $_GET['status'] ?? 'ALL';

// Build query
$query = "
    SELECT r.*, u.name as user_name, u.phone as user_phone, p.name as product_name, c.name as category_name
    FROM rentals r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    JOIN subcategories s ON p.subcategory_id = s.id
    JOIN categories c ON s.category_id = c.id
";

$params = [];
if ($status_filter !== 'ALL') {
    $query .= " WHERE r.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$rentals = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2>Manage Rentals</h2>
    <div class="flex gap-10">
        <a href="?status=ALL" class="btn btn-sm <?= $status_filter === 'ALL' ? 'btn-primary' : 'btn-outline' ?>">All</a>
        <a href="?status=PENDING" class="btn btn-sm <?= $status_filter === 'PENDING' ? 'btn-primary' : 'btn-outline' ?>">Pending</a>
        <a href="?status=APPROVED" class="btn btn-sm <?= $status_filter === 'APPROVED' ? 'btn-primary' : 'btn-outline' ?>">Approved</a>
        <a href="?status=ACTIVE" class="btn btn-sm <?= $status_filter === 'ACTIVE' ? 'btn-primary' : 'btn-outline' ?>">Active</a>
        <a href="?status=RETURNED" class="btn btn-sm <?= $status_filter === 'RETURNED' ? 'btn-primary' : 'btn-outline' ?>">Returned</a>
        <a href="?status=OVERDUE" class="btn btn-sm <?= $status_filter === 'OVERDUE' ? 'btn-primary' : 'btn-outline' ?>">Overdue</a>
        <a href="?status=COMPLETED" class="btn btn-sm <?= $status_filter === 'COMPLETED' ? 'btn-primary' : 'btn-outline' ?>">Completed</a>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
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
                    <td>#<?= $r['id'] ?></td>
                    <td>
                        <div style="font-weight: 600;"><?= htmlspecialchars($r['user_name']) ?></div>
                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($r['user_phone']) ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 500;"><?= htmlspecialchars($r['product_name']) ?></div>
                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($r['category_name']) ?></div>
                    </td>
                    <td style="font-size: 12px;">
                        <?= date('M d, Y', strtotime($r['start_date'])) ?> - <?= date('M d, Y', strtotime($r['end_date'])) ?>
                        <div class="text-muted"><?= $r['days'] ?> days</div>
                    </td>
                    <td style="font-weight: 600;">৳<?= number_format($r['total_fee'], 0) ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($r['status']) ?>">
                            <?= str_replace('_', ' ', $r['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="flex align-center gap-5">
                            <a href="<?= BASE_URL ?>/admin/rental_detail.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">View</a>
                            
                            <?php if ($r['status'] === 'PENDING'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/actions/approve_rental.php" style="display: inline;">
                                    <input type="hidden" name="rental_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                </form>
                                <button onclick="rejectRental(<?= $r['id'] ?>)" class="btn btn-danger btn-sm">Reject</button>
                            <?php elseif ($r['status'] === 'RETURNED'): ?>
                                <a href="<?= BASE_URL ?>/admin/rental_detail.php?id=<?= $r['id'] ?>#inspect" class="btn btn-success btn-sm" style="background: var(--color-success); color: #fff;">Inspect</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Reject Modal (Hidden) -->
<div id="reject_modal" class="modal-overlay">
  <div class="modal-content">
    <h3 class="mb-10">Reject Rental Request</h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/actions/reject_rental.php">
      <input type="hidden" name="rental_id" id="reject_rental_id">
      <div class="form-group">
        <label>Reason for Rejection</label>
        <textarea name="reason" class="form-control" required rows="4" placeholder="e.g. Identity verification failed, product unavailable..."></textarea>
      </div>
      <div class="flex gap-10">
        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
        <button type="button" onclick="document.getElementById('reject_modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function rejectRental(id) {
    document.getElementById('reject_rental_id').value = id;
    document.getElementById('reject_modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
