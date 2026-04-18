<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();
$products = $db->query("
    SELECT p.*, s.name as subcategory_name, c.name as category_name
    FROM products p
    JOIN subcategories s ON p.subcategory_id = s.id
    JOIN categories c ON s.category_id = c.id
    ORDER BY p.id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2>Inventory Management</h2>
    <a href="<?= BASE_URL ?>/admin/product_form.php" class="btn btn-primary">Add New Product</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Rate</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Rentals</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div class="flex align-center gap-10">
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($p['image_path']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                            <div style="font-weight: 600;"><?= htmlspecialchars($p['name']) ?></div>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 13px;"><?= htmlspecialchars($p['category_name']) ?></div>
                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($p['subcategory_name']) ?></div>
                    </td>
                    <td>৳<?= number_format($p['daily_rate'], 0) ?></td>
                    <td><?= $p['stock_qty'] ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($p['status']) ?>">
                            <?= str_replace('_', ' ', $p['status']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?= $p['rental_count'] ?>
                        <div class="text-muted" style="font-size: 10px;">Thresh: <?= $p['maintenance_threshold'] ?></div>
                    </td>
                    <td>
                        <div class="flex align-center gap-5">
                            <a href="<?= BASE_URL ?>/admin/product_form.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                            
                            <?php if ($p['status'] !== 'RETIRED'): ?>
                                <?php if ($p['status'] !== 'MAINTENANCE'): ?>
                                    <button onclick="sendToMaintenance(<?= $p['id'] ?>)" class="btn btn-warning btn-sm" style="background: var(--color-warning); color: #fff; border: none;">Maint.</button>
                                <?php endif; ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/actions/retire_product.php" onsubmit="return confirm('Retire this product? It will no longer be rentable.')" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Retire</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Maintenance Modal -->
<div id="maint_modal" class="modal-overlay">
  <div class="modal-content">
    <h3 class="mb-10">Send to Maintenance</h3>
    <form method="POST" action="<?= BASE_URL ?>/admin/actions/send_maintenance.php">
      <input type="hidden" name="product_id" id="maint_product_id">
      <div class="form-group">
        <label>Reason for Maintenance</label>
        <textarea name="reason" class="form-control" required rows="4" placeholder="e.g. Regular cleaning, damaged screen, broken strap..."></textarea>
      </div>
      <div class="flex gap-10">
        <button type="submit" class="btn btn-warning" style="background: var(--color-warning); color: #fff; border: none;">Send to Maintenance</button>
        <button type="button" onclick="document.getElementById('maint_modal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function sendToMaintenance(id) {
    document.getElementById('maint_product_id').value = id;
    document.getElementById('maint_modal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
