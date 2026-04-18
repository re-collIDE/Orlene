<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orlene - Gear & Phone Rental</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/mini-logo.png">
</head>
<body>
    <nav class="navbar">
        <div class="container flex justify-between align-center" style="width: 100%; max-width: 1100px; padding: 0;">
            <a href="<?= BASE_URL ?>/index.php" class="navbar-brand">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Orlene Logo" class="logo-img">
            </a>
            <ul class="navbar-nav">
                <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li><a href="<?= BASE_URL ?>/pages/products.php">Products</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?= BASE_URL ?>/pages/my_rentals.php">My Rentals</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/profile.php">Profile</a></li>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li><a href="<?= BASE_URL ?>/admin/dashboard.php" style="color: var(--color-primary); font-weight: 600;">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="<?= BASE_URL ?>/pages/actions/logout.php">Logout (<?= htmlspecialchars($_SESSION['user_name']) ?>)</a></li>
                <?php else: ?>
                    <li><a href="<?= BASE_URL ?>/pages/login.php">Login</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main class="main-content container">
        <?php flash(); ?>
