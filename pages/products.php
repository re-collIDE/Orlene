<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = get_db();

$cat_filter = $_GET['cat'] ?? '';
$sub_filter = (int)($_GET['sub'] ?? 0);

// Fetch categories for sidebar
$categories = $db->query("SELECT * FROM categories")->fetchAll();

// Build query
$query = "SELECT p.*, s.name as subcategory_name, c.name as category_name 
          FROM products p
          JOIN subcategories s ON p.subcategory_id = s.id
          JOIN categories c ON s.category_id = c.id
          WHERE p.status != 'RETIRED'";

$params = [];
if ($cat_filter) {
    $query .= " AND c.name = ?";
    $params[] = $cat_filter;
}
if ($sub_filter) {
    $query .= " AND s.id = ?";
    $params[] = $sub_filter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex gap-20">
    <aside style="width: 250px; flex-shrink: 0;">
        <div class="card mb-20">
            <h3>Categories</h3>
            <ul style="list-style: none;">
                <li style="margin-bottom: 8px;"><a href="<?= BASE_URL ?>/pages/products.php" class="<?= !$cat_filter ? 'font-bold' : '' ?>">All Gear</a></li>
                <?php foreach ($categories as $cat): ?>
                    <li style="margin-bottom: 8px;">
                        <a href="<?= BASE_URL ?>/pages/products.php?cat=<?= urlencode($cat['name']) ?>" class="<?= $cat_filter === $cat['name'] ? 'font-bold' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                        <?php if ($cat_filter === $cat['name']): ?>
                            <?php 
                            $subs = $db->prepare("SELECT * FROM subcategories WHERE category_id = ?");
                            $subs->execute([$cat['id']]);
                            ?>
                            <ul style="list-style: none; padding-left: 15px; margin-top: 5px;">
                                <?php foreach ($subs->fetchAll() as $sub): ?>
                                    <li style="margin-bottom: 5px; font-size: 13px;">
                                        <a href="<?= BASE_URL ?>/pages/products.php?cat=<?= urlencode($cat['name']) ?>&sub=<?= $sub['id'] ?>" class="<?= $sub_filter === (int)$sub['id'] ? 'text-primary' : '' ?>">
                                            - <?= htmlspecialchars($sub['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <div style="flex: 1;">
        <div class="flex justify-between align-center mb-20">
            <h2>
                <?= $cat_filter ? htmlspecialchars($cat_filter) : 'All Products' ?>
                <?php if ($sub_filter): ?>
                    <small style="font-size: 14px; font-weight: normal; color: #888;">(<?= htmlspecialchars($products[0]['subcategory_name'] ?? '') ?>)</small>
                <?php endif; ?>
            </h2>
            <p class="text-muted"><?= count($products) ?> products found</p>
        </div>

        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <div class="product-card">
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-img">
                    <div class="product-info">
                        <?php 
                        $status_cls = strtolower($p['status']);
                        $status_label = str_replace('_', ' ', $p['status']);
                        ?>
                        <span class="badge badge-<?= $status_cls ?> mb-10"><?= $status_label ?></span>
                        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" class="product-name"><?= htmlspecialchars($p['name']) ?></a>
                        <p class="text-muted" style="font-size: 12px; margin-bottom: 10px;"><?= htmlspecialchars($p['subcategory_name']) ?></p>
                        <div class="flex justify-between align-center mt-10">
                            <span class="product-price">৳<?= number_format($p['daily_rate'], 0) ?> <small>/ day</small></span>
                            <?php if ($p['status'] === 'AVAILABLE'): ?>
                                <a href="<?= BASE_URL ?>/pages/book.php?product_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">Book Now</a>
                            <?php else: ?>
                                <button class="btn btn-outline btn-sm" disabled>Unavailable</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
