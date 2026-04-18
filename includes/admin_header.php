<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orlene Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-sticky">
            <div class="sidebar-header">
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="flex align-center gap-10" style="text-decoration: none;">
                    <img src="<?= BASE_URL ?>/assets/img/ormin_panel.jpg" alt="Orlene Logo" style="height: 30px; width: auto; display: block;">
                    <span style="color: #fff; font-weight: 600; font-size: 16px; letter-spacing: 0.5px;">Ormin Panel</span>
                </a>
            </div>
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/rentals.php">Rentals</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/products.php">Products</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/sos.php">SOS Alerts</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/maintenance.php">Maintenance</a></li>
                    <li><a href="<?= BASE_URL ?>/admin/reports.php">Reports</a></li>
                    <li style="margin-top: 20px;"><a href="<?= BASE_URL ?>/index.php">View Site</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/actions/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </aside>
    <main class="admin-main">
        <?php flash(); ?>
