<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$db = get_db();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch summary stats
$stmt = $db->prepare("SELECT * FROM v_user_rental_summary WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$summary = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <h2 class="mb-20">My Profile</h2>
    
    <div class="grid">
        <div class="grid-col col-6">
            <div class="card">
                <h3 class="mb-20" style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Personal Information</h3>
                <div class="mb-15">
                    <p class="text-muted mb-5">Full Name</p>
                    <p style="font-weight: 500;"><?= htmlspecialchars($user['name']) ?></p>
                </div>
                <div class="mb-15">
                    <p class="text-muted mb-5">Email Address</p>
                    <p style="font-weight: 500;"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <div class="mb-15">
                    <p class="text-muted mb-5">Phone Number</p>
                    <p style="font-weight: 500;"><?= htmlspecialchars($user['phone']) ?></p>
                </div>
                <div class="mb-15">
                    <p class="text-muted mb-5">NID / Student ID</p>
                    <p style="font-weight: 500;"><?= htmlspecialchars($user['nid']) ?></p>
                </div>
                <div class="mb-15">
                    <p class="text-muted mb-5">Member Since</p>
                    <p style="font-weight: 500;"><?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                </div>
            </div>
        </div>
        
        <div class="grid-col col-6">
            <div class="card card-accent">
                <h3 class="mb-20" style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Rental Activity</h3>
                <div class="flex justify-between mb-15">
                    <span>Total Rentals:</span>
                    <span style="font-weight: 600;"><?= $summary['total_rentals'] ?? 0 ?></span>
                </div>
                <div class="flex justify-between mb-15">
                    <span>Completed:</span>
                    <span style="font-weight: 600; color: var(--color-success);"><?= $summary['completed'] ?? 0 ?></span>
                </div>
                <div class="flex justify-between mb-15">
                    <span>Active:</span>
                    <span style="font-weight: 600; color: var(--color-info);"><?= $summary['active'] ?? 0 ?></span>
                </div>
                <?php if (($summary['overdue_count'] ?? 0) > 0): ?>
                    <div class="flex justify-between mb-15">
                        <span>Overdue:</span>
                        <span style="font-weight: 600; color: var(--color-danger);"><?= $summary['overdue_count'] ?></span>
                    </div>
                <?php endif; ?>
                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
                <div class="flex justify-between" style="font-size: 16px;">
                    <span>Total Spent:</span>
                    <span style="font-weight: 600; color: var(--color-primary-dk);">৳<?= number_format($summary['total_spent'] ?? 0, 0) ?></span>
                </div>
            </div>
            
            <div class="mt-20">
                <a href="<?= BASE_URL ?>/pages/my_rentals.php" class="btn btn-outline" style="width: 100%;">View My Rental History</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
