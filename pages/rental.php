<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/pages/my_rentals.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare("
    SELECT r.*, p.name as product_name, c.name as category_name, p.image_path, p.daily_rate,
           u.name as user_name, u.phone as user_phone,
           (SELECT result FROM inspections WHERE rental_id = r.id LIMIT 1) as inspection_result,
           (SELECT notes FROM inspections WHERE rental_id = r.id LIMIT 1) as inspection_notes
    FROM rentals r
    JOIN products p ON r.product_id = p.id
    JOIN subcategories s ON p.subcategory_id = s.id
    JOIN categories c ON s.category_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND (r.user_id = ? OR ? = 'admin')
");
$stmt->execute([$id, $_SESSION['user_id'], $_SESSION['user_role']]);
$rental = $stmt->fetch();

if (!$rental) {
    redirect('/pages/my_rentals.php', 'Rental not found or access denied.', 'error');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <nav style="font-size: 14px;">
        <a href="<?= BASE_URL ?>/pages/my_rentals.php">My Rentals</a> &raquo; 
        <span class="text-muted">Rental #<?= $rental['id'] ?></span>
    </nav>
    <?php if (in_array($rental['status'], ['ACTIVE', 'OVERDUE'])): ?>
        <button id="sos_btn" class="btn btn-danger">SEND SOS ALERT</button>
    <?php endif; ?>
</div>

<div class="grid">
    <div class="grid-col col-8">
        <div class="card mb-20">
            <h3 class="mb-20" style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Rental Details</h3>
            <div class="grid">
                <div class="grid-col col-6">
                    <p class="text-muted mb-5">Product</p>
                    <p style="font-weight: 600; font-size: 16px;"><?= htmlspecialchars($rental['product_name']) ?></p>
                    <p class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($rental['category_name']) ?></p>
                </div>
                <div class="grid-col col-6">
                    <p class="text-muted mb-5">Status</p>
                    <span class="badge badge-<?= strtolower($rental['status']) ?>">
                        <?= str_replace('_', ' ', $rental['status']) ?>
                    </span>
                </div>
                <div class="grid-col col-6 mt-10">
                    <p class="text-muted mb-5">Rental Period</p>
                    <p style="font-weight: 500;">
                        <?= date('M d, Y', strtotime($rental['start_date'])) ?> to <?= date('M d, Y', strtotime($rental['end_date'])) ?>
                    </p>
                    <p class="text-muted" style="font-size: 12px;"><?= $rental['days'] ?> days @ ৳<?= number_format($rental['daily_rate'], 0) ?>/day</p>
                </div>
                <div class="grid-col col-6 mt-10">
                    <p class="text-muted mb-5">Total Fee</p>
                    <p style="font-weight: 600; font-size: 18px; color: var(--color-primary-dk);">৳<?= number_format($rental['total_fee'], 0) ?></p>
                </div>
            </div>
        </div>

        <div class="card mb-20">
            <h3 class="mb-20" style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Location of Use</h3>
            <div class="grid">
                <div class="grid-col col-6">
                    <p class="text-muted mb-5">Area</p>
                    <p><?= htmlspecialchars($rental['use_area']) ?></p>
                </div>
                <div class="grid-col col-6">
                    <p class="text-muted mb-5">City</p>
                    <p><?= htmlspecialchars($rental['use_city']) ?></p>
                </div>
                <?php if ($rental['use_landmark']): ?>
                    <div class="grid-col col-12 mt-10">
                        <p class="text-muted mb-5">Landmark</p>
                        <p><?= htmlspecialchars($rental['use_landmark']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($rental['status'] === 'COMPLETED' || $rental['inspection_result']): ?>
            <div class="card card-accent" style="border-left-color: <?= $rental['inspection_result'] === 'PASS' ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                <h3 class="mb-10">Post-Return Inspection</h3>
                <div class="flex align-center gap-10 mb-10">
                    <strong>Result:</strong>
                    <span class="badge badge-<?= $rental['inspection_result'] === 'PASS' ? 'success' : 'rejected' ?>">
                        <?= $rental['inspection_result'] ?>
                    </span>
                </div>
                <p class="text-muted"><strong>Admin Notes:</strong></p>
                <p><?= nl2br(htmlspecialchars($rental['inspection_notes'] ?? 'No notes provided.')) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($rental['status'] === 'REJECTED'): ?>
            <div class="card card-accent" style="border-left-color: var(--color-danger);">
                <h3 class="mb-10">Rejection Reason</h3>
                <p><?= nl2br(htmlspecialchars($rental['rejection_reason'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid-col col-4">
        <div class="card mb-20">
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($rental['image_path']) ?>" alt="" style="width: 100%; border-radius: var(--radius); margin-bottom: 15px;">
            <h3 class="mb-10">Timeline</h3>
            <ul style="list-style: none; padding-left: 10px; border-left: 2px solid #eee;">
                <li style="position: relative; margin-bottom: 15px; padding-left: 15px;">
                    <div style="position: absolute; left: -21px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: var(--color-success);"></div>
                    <div style="font-size: 12px; color: #888;"><?= date('M d, Y H:i', strtotime($rental['created_at'])) ?></div>
                    <div style="font-weight: 500;">Booking Submitted</div>
                </li>
                <?php if ($rental['pickup_at']): ?>
                    <li style="position: relative; margin-bottom: 15px; padding-left: 15px;">
                        <div style="position: absolute; left: -21px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: var(--color-success);"></div>
                        <div style="font-size: 12px; color: #888;"><?= date('M d, Y H:i', strtotime($rental['pickup_at'])) ?></div>
                        <div style="font-weight: 500;">Product Picked Up</div>
                    </li>
                <?php endif; ?>
                <?php if ($rental['returned_at']): ?>
                    <li style="position: relative; margin-bottom: 15px; padding-left: 15px;">
                        <div style="position: absolute; left: -21px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: var(--color-success);"></div>
                        <div style="font-size: 12px; color: #888;"><?= date('M d, Y H:i', strtotime($rental['returned_at'])) ?></div>
                        <div style="font-weight: 500;">Product Returned</div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <?php if ($rental['status'] === 'APPROVED'): ?>
            <div class="card card-accent" style="border-left-color: var(--color-primary);">
                <h3 class="mb-10">Ready for Pickup</h3>
                <p class="text-muted mb-20" style="font-size: 13px;">Please visit our store to collect your gear. Once you have it, click the button below.</p>
                <form method="POST" action="<?= BASE_URL ?>/pages/actions/confirm_pickup.php">
                    <input type="hidden" name="rental_id" value="<?= $rental['id'] ?>">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">I Have Picked Up the Gear</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SOS Modal -->
<div id="sos_modal" class="modal-overlay">
  <div class="modal-content">
    <h3 style="color:var(--color-danger);margin-bottom:12px">Send SOS Alert</h3>
    <p style="font-size:13px;color:#555;margin-bottom:12px">This will notify the Orlene team. Describe your situation (optional).</p>
    <form method="POST" action="<?= BASE_URL ?>/pages/actions/sos_trigger.php">
      <input type="hidden" name="rental_id" value="<?= $rental['id'] ?>">
      <textarea name="message" rows="4" placeholder="Describe your emergency..." class="form-control" style="margin-bottom:12px"></textarea>
      <div class="flex gap-10">
        <button type="submit" class="btn btn-danger">Send SOS</button>
        <button type="button" id="sos_cancel" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
