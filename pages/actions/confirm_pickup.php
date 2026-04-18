<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/my_rentals.php');
}

$rental_id = (int)($_POST['rental_id'] ?? 0);
if (!$rental_id) {
    redirect('/pages/my_rentals.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    
    // Verify ownership
    $stmt = $db->prepare("SELECT user_id FROM rentals WHERE id = ?");
    $stmt->execute([$rental_id]);
    $rental = $stmt->fetch();
    
    if (!$rental || $rental['user_id'] != $_SESSION['user_id']) {
        redirect('/pages/my_rentals.php', 'Access denied.', 'error');
    }

    $stmt = $db->prepare("CALL sp_confirm_pickup(:rental_id, :user_id)");
    $stmt->execute([':rental_id' => $rental_id, ':user_id' => $_SESSION['user_id']]);
    
    redirect('/pages/my_rentals.php', 'Pickup confirmed! Your rental is now active.');
} catch (PDOException $e) {
    redirect('/pages/my_rentals.php', 'Error: ' . $e->getMessage(), 'error');
}
