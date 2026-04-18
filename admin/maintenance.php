<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();
$logs = $db->query("
    SELECT ml.*, p.name as product_name, p.image_path, p.rental_count
    FROM maintenance_log ml
    JOIN products p ON ml.product_id = p.id
    WHERE ml.resolved_at IS NULL
    ORDER BY ml.sent_at ASC
")->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2>Maintenance Management</h2>
</div>

<?php if (empty($logs)): ?>
    <div class="card text-center p-40">
        <p class="text-muted">No gear currently in maintenance. Everything is in service!</p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Reason</th>
                    <th>Sent At</th>
                    <th>Rentals</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td>
                            <div class="flex align-center gap-10">
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($l['image_path']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <div style="font-weight: 600;"><?= htmlspecialchars($l['product_name']) ?></div>
                            </div>
                        </td>
                        <td style="font-size: 13px; max-width: 300px;"><?= nl2br(htmlspecialchars($l['reason'])) ?></td>
                        <td style="font-size: 12px;">
                            <?= date('M d, Y', strtotime($l['sent_at'])) ?>
                            <div class="text-muted"><?= date('H:i', strtotime($l['sent_at'])) ?></div>
                        </td>
                        <td class="text-center"><?= $l['rental_count'] ?></td>
                        <td>
                            <button onclick="resolveMaintenance(<?= $l['id'] ?>)" class="btn btn-primary btn-sm">Mark Resolved</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Resolve Maintenance Modal -->
<div id="resolve_modal" class="modal-overlay">
  <div class="modal-content">
    <h3 class="mb-10">Resolve Maintenance</h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/actions/resolve_maintenance.php">
      <input type="hidden" name="log_id" id="resolve_log_id">
      <div class="form-group">
        <label>Resolution Notes</label>
        <textarea name="notes" class="form-control" required rows="4" placeholder="Describe the work done (e.g. Screen replaced, deep cleaned...)"></textarea>
      </div>
      <div class="flex gap-10">
        <button type="submit" class="btn btn-primary">Restore to Service</button>
        <button type="button" onclick="document.getElementById('resolve_modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function resolveMaintenance(id) {
    document.getElementById('resolve_log_id').value = id;
    document.getElementById('resolve_modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
