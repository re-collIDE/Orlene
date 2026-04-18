<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/rentals.php');
}

$rental_id = (int)($_POST['rental_id'] ?? 0);
$result = $_POST['result'] ?? '';
$notes = trim($_POST['notes'] ?? '');

if (!$rental_id || !$result) {
    redirect('/admin/rentals.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    $stmt = $db->prepare("CALL sp_complete_rental(:rental_id, :admin_id, :result, :notes)");
    $stmt->execute([
        ':rental_id' => $rental_id,
        ':admin_id'  => $_SESSION['user_id'],
        ':result'    => $result,
        ':notes'     => $notes
    ]);
    
    $msg = ($result === 'PASS') ? 'Inspection passed. Rental completed.' : 'Inspection failed. Product sent to maintenance.';
    redirect("/admin/rental_detail.php?id=$rental_id", $msg);
} catch (PDOException $e) {
    redirect("/admin/rental_detail.php?id=$rental_id", 'Error: ' . $e->getMessage(), 'error');
}
