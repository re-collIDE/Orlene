<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/maintenance.php');
}

$log_id = (int)($_POST['log_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$log_id || !$notes) {
    redirect('/admin/maintenance.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    $stmt = $db->prepare("CALL sp_resolve_maintenance(:log_id, :admin_id, :notes)");
    $stmt->execute([
        ':log_id'   => $log_id,
        ':admin_id' => $_SESSION['user_id'],
        ':notes'    => $notes
    ]);
    
    redirect('/admin/maintenance.php', 'Maintenance resolved. Product is now AVAILABLE.');
} catch (PDOException $e) {
    redirect('/admin/maintenance.php', 'Error: ' . $e->getMessage(), 'error');
}
