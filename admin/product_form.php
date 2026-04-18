<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
$product = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

// Fetch categories and subcategories
$categories = $db->query("SELECT * FROM categories")->fetchAll();
$subcategories = $db->query("
    SELECT s.*, c.name as category_name 
    FROM subcategories s 
    JOIN categories c ON s.category_id = c.id
    ORDER BY c.name, s.name
")->fetchAll();

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h2><?= $id ? 'Edit Product' : 'Add New Product' ?></h2>
    <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-outline">Back to Inventory</a>
</div>

<div class="card" style="max-width: 800px;">
    <form method="POST" action="<?= BASE_URL ?>/admin/actions/save_product.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $id ?>">
        
        <div class="grid">
            <div class="grid-col col-6">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
                </div>
            </div>
            <div class="grid-col col-6">
                <div class="form-group">
                    <label>Subcategory</label>
                    <select name="subcategory_id" class="form-control" required>
                        <option value="">-- Select Subcategory --</option>
                        <?php 
                        $current_cat = '';
                        foreach ($subcategories as $s): 
                            if ($current_cat !== $s['category_name']):
                                if ($current_cat !== '') echo '</optgroup>';
                                $current_cat = $s['category_name'];
                                echo '<optgroup label="' . htmlspecialchars($current_cat) . '">';
                            endif;
                        ?>
                            <option value="<?= $s['id'] ?>" <?= (isset($product['subcategory_id']) && $product['subcategory_id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </div>

        <div class="grid">
            <div class="grid-col col-4">
                <div class="form-group">
                    <label>Daily Rate (৳)</label>
                    <input type="number" name="daily_rate" class="form-control" required step="0.01" value="<?= htmlspecialchars($product['daily_rate'] ?? '') ?>">
                </div>
            </div>
            <div class="grid-col col-4">
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_qty" class="form-control" required value="<?= htmlspecialchars($product['stock_qty'] ?? '1') ?>">
                </div>
            </div>
            <div class="grid-col col-4">
                <div class="form-group">
                    <label>Maint. Threshold</label>
                    <input type="number" name="maintenance_threshold" class="form-control" required value="<?= htmlspecialchars($product['maintenance_threshold'] ?? '5') ?>">
                    <small class="text-muted">Rentals before auto-maint.</small>
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="grid-col col-6">
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">JPG, PNG, WebP. Max 2MB.</small>
                </div>
            </div>
            <div class="grid-col col-6">
                <?php if ($product && $product['image_path']): ?>
                    <p class="text-muted mb-5">Current Image:</p>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($product['image_path']) ?>" alt="" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid var(--color-border); border-radius: 4px;">
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 40px;"><?= $id ? 'Update Product' : 'Create Product' ?></button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
