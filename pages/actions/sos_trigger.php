<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/pages/my_rentals.php');
}

$rental_id = (int)($_POST['rental_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$rental_id) {
    redirect('/pages/my_rentals.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    
    // Verify ownership and status
    $stmt = $db->prepare("SELECT user_id, status FROM rentals WHERE id = ?");
    $stmt->execute([$rental_id]);
    $rental = $stmt->fetch();
    
    if (!$rental || $rental['user_id'] != $_SESSION['user_id']) {
        redirect('/pages/my_rentals.php', 'Access denied.', 'error');
    }

    if (!in_array($rental['status'], ['ACTIVE', 'OVERDUE'])) {
        redirect("/pages/rental.php?id=$rental_id", 'SOS can only be triggered for active rentals.', 'error');
    }

    $stmt = $db->prepare("INSERT INTO sos_alerts (rental_id, user_id, trigger_type, message, status) VALUES (?, ?, 'MANUAL', ?, 'OPEN')");
    $stmt->execute([$rental_id, $_SESSION['user_id'], $message]);
    
    redirect("/pages/rental.php?id=$rental_id", 'SOS alert sent. Help is being coordinated.');
} catch (PDOException $e) {
    redirect("/pages/rental.php?id=$rental_id", 'Error: ' . $e->getMessage(), 'error');
}
