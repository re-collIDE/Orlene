<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/sos.php');
}

$alert_id = (int)($_POST['alert_id'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$alert_id || !$notes) {
    redirect('/admin/sos.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    $stmt = $db->prepare("CALL sp_resolve_sos(:alert_id, :admin_id, :notes)");
    $stmt->execute([
        ':alert_id' => $alert_id,
        ':admin_id' => $_SESSION['user_id'],
        ':notes'    => $notes
    ]);
    
    redirect('/admin/sos.php', 'SOS alert marked as resolved.');
} catch (PDOException $e) {
    redirect('/admin/sos.php', 'Error: ' . $e->getMessage(), 'error');
}
