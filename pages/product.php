<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/pages/products.php');
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT p.*, s.name as subcategory_name, c.name as category_name 
                      FROM products p
                      JOIN subcategories s ON p.subcategory_id = s.id
                      JOIN categories c ON s.category_id = c.id
                      WHERE p.id = ? AND p.status != 'RETIRED'");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . BASE_URL . '/pages/products.php');
    exit;
}

// Fetch related products (same subcategory, excluding current)
$stmt = $db->prepare("SELECT * FROM products WHERE subcategory_id = ? AND id != ? AND status = 'AVAILABLE' LIMIT 3");
$stmt->execute([$product['subcategory_id'], $id]);
$related = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card p-20">
    <div class="grid">
        <div class="grid-col col-6">
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; border-radius: var(--radius-lg); border: 1px solid var(--color-border);">
        </div>
        <div class="grid-col col-6">
            <nav style="margin-bottom: 15px; font-size: 13px;">
                <a href="<?= BASE_URL ?>/pages/products.php">Products</a> &raquo; 
                <a href="<?= BASE_URL ?>/pages/products.php?cat=<?= urlencode($product['category_name']) ?>"><?= htmlspecialchars($product['category_name']) ?></a> &raquo;
                <span class="text-muted"><?= htmlspecialchars($product['subcategory_name']) ?></span>
            </nav>
            
            <h1 style="margin-bottom: 10px;"><?= htmlspecialchars($product['name']) ?></h1>
            
            <?php 
            $status_cls = strtolower($product['status']);
            $status_label = str_replace('_', ' ', $product['status']);
            ?>
            <span class="badge badge-<?= $status_cls ?> mb-20"><?= $status_label ?></span>
            
            <div style="font-size: 24px; font-weight: 600; color: var(--color-primary-dk); margin-bottom: 20px;">
                ৳<?= number_format($product['daily_rate'], 0) ?> <small style="font-weight: normal; font-size: 14px; color: #666;">/ per day</small>
            </div>
            
            <div style="margin-bottom: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <h3 style="margin-bottom: 10px;">Description</h3>
                <p class="text-muted"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            </div>
            
            <?php if ($product['status'] === 'AVAILABLE'): ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= BASE_URL ?>/pages/book.php?product_id=<?= $product['id'] ?>" class="btn btn-primary" style="padding: 12px 40px; font-size: 16px;">Book This Product</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/pages/login.php" class="btn btn-primary" style="padding: 12px 40px; font-size: 16px;">Login to Book</a>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn btn-outline" disabled style="padding: 12px 40px; font-size: 16px;">Currently Unavailable</button>
                <p class="text-muted mt-10" style="font-size: 13px;">Check back later or browse related items.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($related): ?>
    <section class="mt-40">
        <h2 class="mb-20">Related Products</h2>
        <div class="product-grid">
            <?php foreach ($related as $p): ?>
                <div class="product-card">
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-img">
                    <div class="product-info">
                        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" class="product-name"><?= htmlspecialchars($p['name']) ?></a>
                        <div class="flex justify-between align-center mt-10">
                            <span class="product-price">৳<?= number_format($p['daily_rate'], 0) ?></span>
                            <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
