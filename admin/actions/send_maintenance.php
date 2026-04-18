<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/products.php');
}

$product_id = (int)($_POST['product_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$product_id || !$reason) {
    redirect('/admin/products.php', 'Invalid request.', 'error');
}

try {
    $db = get_db();
    $stmt = $db->prepare("CALL sp_send_maintenance(:product_id, :reason)");
    $stmt->execute([':product_id' => $product_id, ':reason' => $reason]);
    
    redirect('/admin/products.php', 'Product sent to maintenance.');
} catch (PDOException $e) {
    redirect('/admin/products.php', 'Error: ' . $e->getMessage(), 'error');
}
