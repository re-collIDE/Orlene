<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();
$tab = $_GET['tab'] ?? 'OPEN';

if ($tab === 'OPEN') {
    $alerts = $db->query("SELECT * FROM v_sos_open")->fetchAll();
} else {
    $alerts = $db->query("
        SELECT sa.*, u.name as user_name, u.phone as user_phone, p.name as product_name, r.id as rental_id,
               r.use_area, r.use_city, r.use_lat, r.use_lng,
               res.name as resolved_by_name
        FROM sos_alerts sa
        JOIN rentals r ON sa.rental_id = r.id
        JOIN users u ON sa.user_id = u.id
        JOIN products p ON r.product_id = p.id
        JOIN users res ON sa.resolved_by = res.id
        WHERE sa.status = 'RESOLVED'
        ORDER BY sa.resolved_at DESC
    ")->fetchAll();
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2>SOS Alerts</h2>
    <div class="flex gap-10">
        <a href="?tab=OPEN" class="btn btn-sm <?= $tab === 'OPEN' ? 'btn-danger' : 'btn-outline' ?>">Open Alerts</a>
        <a href="?tab=RESOLVED" class="btn btn-sm <?= $tab === 'RESOLVED' ? 'btn-primary' : 'btn-outline' ?>">Resolved History</a>
    </div>
</div>

<?php if (empty($alerts)): ?>
    <div class="card text-center p-40">
        <p class="text-muted">No <?= strtolower($tab) ?> SOS alerts found.</p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>User / Contact</th>
                    <th>Product / Rental</th>
                    <th>Location</th>
                    <th><?= $tab === 'OPEN' ? 'Time Since' : 'Resolved Info' ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $a): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?= $a['trigger_type'] === 'MANUAL' ? 'rejected' : 'overdue' ?>">
                                <?= $a['trigger_type'] ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($a['user_name']) ?></div>
                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($a['user_phone']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 500;"><?= htmlspecialchars($a['product_name']) ?></div>
                            <div class="text-muted" style="font-size: 11px;">Rental #<?= $a['rental_id'] ?></div>
                        </td>
                        <td>
                            <?php if (!empty($a['use_area']) || !empty($a['use_city'])): ?>
                                <div style="font-size: 12px;"><?= htmlspecialchars($a['use_area'] ?? '') ?>, <?= htmlspecialchars($a['use_city'] ?? '') ?></div>
                                <?php if (!empty($a['use_lat'])): ?>
                                    <a href="<?= BASE_URL ?>/admin/rental_detail.php?id=<?= $a['rental_id'] ?>#map" style="font-size: 10px;">View Map &rarr;</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 12px;">No location found</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tab === 'OPEN'): ?>
                                <div style="font-weight: 600; color: var(--color-danger);">
                                    <?= $a['hours_since_alert'] ?>h ago
                                </div>
                                <div class="text-muted" style="font-size: 10px;"><?= date('M d, H:i', strtotime($a['alert_time'])) ?></div>
                            <?php else: ?>
                                <div style="font-size: 11px;">By <?= htmlspecialchars($a['resolved_by_name']) ?></div>
                                <div class="text-muted" style="font-size: 10px;"><?= date('M d, H:i', strtotime($a['resolved_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex gap-5">
                                <a href="<?= BASE_URL ?>/admin/rental_detail.php?id=<?= $a['rental_id'] ?>" class="btn btn-outline btn-sm">Details</a>
                                <?php if ($tab === 'OPEN'): ?>
                                    <button onclick="resolveSOS(<?= $a['alert_id'] ?>)" class="btn btn-primary btn-sm">Resolve</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ($a['message'] || ($tab === 'RESOLVED' && $a['resolution_notes'])): ?>
                        <tr style="background: #fffcfc;">
                            <td colspan="6" style="padding: 10px 16px; font-size: 13px; border-top: none;">
                                <?php if ($a['message']): ?>
                                    <div class="mb-5"><strong>User Message:</strong> <?= htmlspecialchars($a['message']) ?></div>
                                <?php endif; ?>
                                <?php if ($tab === 'RESOLVED' && $a['resolution_notes']): ?>
                                    <div style="color: var(--color-success);"><strong>Resolution:</strong> <?= htmlspecialchars($a['resolution_notes']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Resolve SOS Modal -->
<div id="resolve_modal" class="modal-overlay">
  <div class="modal-content">
    <h3 class="mb-10">Resolve SOS Alert</h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/actions/resolve_sos.php">
      <input type="hidden" name="alert_id" id="resolve_alert_id">
      <div class="form-group">
        <label>Resolution Notes</label>
        <textarea name="notes" class="form-control" required rows="4" placeholder="How was this emergency handled?"></textarea>
      </div>
      <div class="flex gap-10">
        <button type="submit" class="btn btn-primary">Mark as Resolved</button>
        <button type="button" onclick="document.getElementById('resolve_modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function resolveSOS(id) {
    document.getElementById('resolve_alert_id').value = id;
    document.getElementById('resolve_modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
