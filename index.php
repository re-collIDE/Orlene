<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db = get_db();

// Fetch featured products (6 random available products)
$stmt = $db->query("SELECT * FROM products WHERE status = 'AVAILABLE' LIMIT 6");
$featured = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1 style="font-size: 48px; margin-bottom: 20px;">Rent Gear, Explore More.</h1>
        <p style="font-size: 18px; margin-bottom: 30px;">Premium phones and adventure equipment for your next journey.</p>
        <div class="flex justify-center gap-20" style="justify-content: center;">
            <a href="<?= BASE_URL ?>/pages/products.php?cat=PHONE" class="btn btn-primary" style="padding: 12px 40px; font-weight: 600;">Rent Phones</a>
            <a href="<?= BASE_URL ?>/pages/products.php?cat=GEAR" class="btn btn-primary" style="padding: 12px 40px; font-weight: 600; background: #fff; color: var(--color-primary-dk);">Rent Adventure Gear</a>
        </div>
    </div>
</section>

<section class="categories mb-40">
    <h2 class="text-center mb-20">Browse by Category</h2>
    <div class="grid">
        <div class="grid-col col-6">
            <a href="<?= BASE_URL ?>/pages/products.php?cat=PHONE" class="card card-accent" style="display: block; text-align: center; padding: 40px;">
                <h3 style="font-size: 24px;">Phones & Accessories</h3>
                <p class="text-muted">Flagship smartphones and travel power kits.</p>
            </a>
        </div>
        <div class="grid-col col-6">
            <a href="<?= BASE_URL ?>/pages/products.php?cat=GEAR" class="card card-accent" style="display: block; text-align: center; padding: 40px; border-left-color: var(--color-warning);">
                <h3 style="font-size: 24px;">Adventure Gear</h3>
                <p class="text-muted">Tents, backpacks, cycles, and more for the outdoors.</p>
            </a>
        </div>
    </div>
</section>

<section class="how-it-works mb-40" style="background: #fff; padding: 40px; border-radius: var(--radius-lg); border: 1px solid var(--color-border);">
    <h2 class="text-center mb-30">How It Works</h2>
    <div class="grid">
        <div class="grid-col col-3 flex-col text-center">
            <div style="font-size: 30px; margin-bottom: 10px;">1</div>
            <h4>Choose Gear</h4>
            <p class="text-muted">Browse our catalog and pick what you need.</p>
        </div>
        <div class="grid-col col-3 flex-col text-center">
            <div style="font-size: 30px; margin-bottom: 10px;">2</div>
            <h4>Book Dates</h4>
            <p class="text-muted">Select your rental period (up to 15 days).</p>
        </div>
        <div class="grid-col col-3 flex-col text-center">
            <div style="font-size: 30px; margin-bottom: 10px;">3</div>
            <h4>Pick Up</h4>
            <p class="text-muted">Visit our location to collect your items.</p>
        </div>
        <div class="grid-col col-3 flex-col text-center">
            <div style="font-size: 30px; margin-bottom: 10px;">4</div>
            <h4>Return & Save</h4>
            <p class="text-muted">Return on time and only pay for what you use.</p>
        </div>
    </div>
</section>

<section class="featured">
    <h2 class="mb-20">Featured Products</h2>
    <div class="product-grid">
        <?php foreach ($featured as $p): ?>
            <div class="product-card">
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-img">
                <div class="product-info">
                    <span class="badge badge-available mb-10">Available</span>
                    <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" class="product-name"><?= htmlspecialchars($p['name']) ?></a>
                    <div class="flex justify-between align-center mt-10">
                        <span class="product-price">৳<?= number_format($p['daily_rate'], 0) ?> <small>/ day</small></span>
                        <a href="<?= BASE_URL ?>/pages/product.php?id=<?= $p['id'] ?>" class="btn btn-outline">Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-20">
        <a href="<?= BASE_URL ?>/pages/products.php" class="btn btn-primary" style="padding: 10px 40px;">View All Products</a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
